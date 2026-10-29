<?php

namespace humhub\modules\bazaar\services;

use Yii;
use ZipArchive;
use yii\base\Component;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;

/**
 * Safely inspects and installs HumHub module archives.
 *
 * The service deliberately does not use ZipArchive::extractTo() directly.
 * Archives are validated first and extracted into a temporary directory so a
 * failed install cannot leave a partially written module behind.
 */
class ModuleInstaller extends Component
{
    public const MAX_ARCHIVE_SIZE = 67108864; // 64 MiB
    public const MAX_UNCOMPRESSED_SIZE = 268435456; // 256 MiB
    public const MAX_FILES = 5000;

    public function getModulesPath(): string
    {
        return rtrim(Yii::getAlias('@app') . '/modules', DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    /**
     * Inspect an archive without extracting it.
     *
     * @return array{
     *   root:string,
     *   manifest:array,
     *   files:int,
     *   uncompressedSize:int
     * }
     */
    public function inspect(string $archivePath): array
    {
        if (!is_file($archivePath)) {
            throw new \RuntimeException(Yii::t('BazaarModule.base', 'Archive file not found.'));
        }

        if ((int)filesize($archivePath) > self::MAX_ARCHIVE_SIZE) {
            throw new \RuntimeException(Yii::t('BazaarModule.base', 'The zip file must not exceed 64 MB.'));
        }

        $zip = new ZipArchive();
        $result = $zip->open($archivePath);

        if ($result !== true) {
            throw new \RuntimeException(Yii::t(
                'BazaarModule.base',
                'Could not open the zip archive (error code: {code}).',
                ['code' => $result]
            ));
        }

        $root = null;
        $fileCount = 0;
        $uncompressedSize = 0;
        $manifest = null;
        $seenEntries = [];

        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);

                if ($stat === false) {
                    throw new \RuntimeException(Yii::t('BazaarModule.base', 'Could not inspect a file in the archive.'));
                }

                $name = str_replace('\\', '/', (string)($stat['name'] ?? ''));

                if ($name === '' || str_contains($name, "\0") || str_starts_with($name, '/') || preg_match('/^[a-zA-Z]:/', $name) || preg_match('#(^|/)\.\.?(/|$)#', $name)) {
                    throw new \RuntimeException(Yii::t('BazaarModule.base', 'The archive contains an unsafe file path.'));
                }

                $entryKey = rtrim($name, '/');
                if (isset($seenEntries[$entryKey])) {
                    throw new \RuntimeException(Yii::t('BazaarModule.base', 'The archive contains duplicate file paths.'));
                }
                $seenEntries[$entryKey] = true;

                $parts = explode('/', trim($name, '/'));
                $top = $parts[0] ?? '';

                if (!preg_match('/^[a-zA-Z0-9_-]+$/', $top)) {
                    throw new \RuntimeException(Yii::t('BazaarModule.base', 'The archive contains an invalid module directory name.'));
                }

                if ($root === null) {
                    $root = $top;
                } elseif ($root !== $top) {
                    throw new \RuntimeException(Yii::t(
                        'BazaarModule.base',
                        'The archive must contain exactly one module directory at its root.'
                    ));
                }

                $isDirectory = str_ends_with($name, '/');

                if (!$isDirectory) {
                    $fileCount++;
                    $uncompressedSize += (int)($stat['size'] ?? 0);

                    if ($fileCount > self::MAX_FILES) {
                        throw new \RuntimeException(Yii::t(
                            'BazaarModule.base',
                            'The archive contains too many files. The maximum is {count}.',
                            ['count' => self::MAX_FILES]
                        ));
                    }

                    if ($uncompressedSize > self::MAX_UNCOMPRESSED_SIZE) {
                        throw new \RuntimeException(Yii::t(
                            'BazaarModule.base',
                            'The uncompressed archive is too large. The maximum is 256 MB.'
                        ));
                    }

                    // ZipArchive::statIndex() does not consistently expose external
                    // attributes. Read them through the dedicated API where available.
                    $opsys = 0;
                    $attributes = 0;
                    if (method_exists($zip, 'getExternalAttributesIndex')
                        && $zip->getExternalAttributesIndex($i, $opsys, $attributes)
                        && defined(ZipArchive::class . '::OPSYS_UNIX')
                        && $opsys === ZipArchive::OPSYS_UNIX
                    ) {
                        $mode = ($attributes >> 16) & 0xF000;
                        if ($mode === 0xA000) {
                            throw new \RuntimeException(Yii::t(
                                'BazaarModule.base',
                                'The archive contains symbolic links, which are not permitted.'
                            ));
                        }
                    }

                    if ($name === $root . '/module.json') {
                        $rawManifest = $zip->getFromIndex($i);
                        $manifest = is_string($rawManifest) ? json_decode($rawManifest, true) : null;

                        if (!is_array($manifest)) {
                            throw new \RuntimeException(Yii::t(
                                'BazaarModule.base',
                                'The module.json file is missing or invalid.'
                            ));
                        }
                    }
                }
            }

            if ($root === null || $manifest === null) {
                throw new \RuntimeException(Yii::t(
                    'BazaarModule.base',
                    'The archive must contain a module.json file.'
                ));
            }

            $manifestId = (string)($manifest['id'] ?? '');

            if ($manifestId === '' || !preg_match('/^[a-zA-Z0-9_-]+$/', $manifestId)) {
                throw new \RuntimeException(Yii::t(
                    'BazaarModule.base',
                    'The module.json file contains an invalid module ID.'
                ));
            }

            if ($manifestId !== $root) {
                throw new \RuntimeException(Yii::t(
                    'BazaarModule.base',
                    'The module ID in module.json ({manifestId}) does not match the archive directory ({directory}).',
                    ['manifestId' => $manifestId, 'directory' => $root]
                ));
            }

            if (empty($manifest['name']) || empty($manifest['version'])) {
                throw new \RuntimeException(Yii::t(
                    'BazaarModule.base',
                    'The module.json file must contain a name and version.'
                ));
            }

            return [
                'root' => $root,
                'manifest' => $manifest,
                'files' => $fileCount,
                'uncompressedSize' => $uncompressedSize,
            ];
        } finally {
            $zip->close();
        }
    }

    /**
     * Install an archive atomically.
     *
     * Existing modules are rejected unless $replace is true. Active modules
     * are never replaced.
     *
     * @return array{root:string, manifest:array, replaced:bool}
     */
    public function install(string $archivePath, bool $replace = false): array
    {
        $info = $this->inspect($archivePath);
        $modulesPath = $this->getModulesPath();

        if (!is_dir($modulesPath) && !mkdir($modulesPath, 0755, true)) {
            throw new \RuntimeException(Yii::t('BazaarModule.base', 'Could not create the modules directory.'));
        }

        if (!is_writable($modulesPath)) {
            throw new \RuntimeException(Yii::t('BazaarModule.base', 'The modules directory is not writable.'));
        }

        $target = $modulesPath . $info['root'];
        $existing = is_dir($target);

        if ($existing && !$replace) {
            throw new \RuntimeException(Yii::t(
                'BazaarModule.base',
                'Module "{id}" is already installed. Enable replacement to update it.',
                ['id' => $info['root']]
            ));
        }

        if ($existing && $this->isModuleEnabled($info['root'])) {
            throw new ForbiddenHttpException(Yii::t(
                'BazaarModule.base',
                'Module "{id}" is currently enabled. Disable it before replacing its files.',
                ['id' => $info['root']]
            ));
        }

        $tempRoot = $modulesPath . '.humhub_module_' . bin2hex(random_bytes(12));
        $backup = null;

        if (!mkdir($tempRoot, 0700, true)) {
            throw new \RuntimeException(Yii::t('BazaarModule.base', 'Could not create a temporary extraction directory.'));
        }

        try {
            $this->extractSafely($archivePath, $tempRoot);

            $extracted = $tempRoot . DIRECTORY_SEPARATOR . $info['root'];

            if (!is_dir($extracted) || !is_file($extracted . DIRECTORY_SEPARATOR . 'module.json')) {
                throw new \RuntimeException(Yii::t('BazaarModule.base', 'The extracted module is incomplete.'));
            }

            if ($existing) {
                $backup = $modulesPath . '.backup_' . $info['root'] . '_' . bin2hex(random_bytes(6));

                if (!rename($target, $backup)) {
                    throw new \RuntimeException(Yii::t('BazaarModule.base', 'Could not create a backup of the existing module.'));
                }
            }

            if (!rename($extracted, $target)) {
                if ($backup !== null) {
                    @rename($backup, $target);
                }

                throw new \RuntimeException(Yii::t('BazaarModule.base', 'Could not move the module into the modules directory.'));
            }

            if ($backup !== null) {
                $this->deleteDirectory($backup);
            }

            return [
                'root' => $info['root'],
                'manifest' => $info['manifest'],
                'replaced' => $existing,
            ];
        } catch (\Throwable $e) {
            if ($backup !== null && !is_dir($target)) {
                @rename($backup, $target);
            }

            throw $e;
        } finally {
            $this->deleteDirectory($tempRoot);
        }
    }

    private function extractSafely(string $archivePath, string $destination): void
    {
        $zip = new ZipArchive();
        $result = $zip->open($archivePath);

        if ($result !== true) {
            throw new \RuntimeException(Yii::t(
                'BazaarModule.base',
                'Could not open the zip archive (error code: {code}).',
                ['code' => $result]
            ));
        }

        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $name = str_replace('\\', '/', (string)($stat['name'] ?? ''));

                if ($name === '' || str_contains($name, "\0") || str_starts_with($name, '/') || preg_match('/^[a-zA-Z]:/', $name) || preg_match('#(^|/)\.\.?(/|$)#', $name)) {
                    throw new \RuntimeException(Yii::t('BazaarModule.base', 'The archive contains an unsafe file path.'));
                }

                $relative = ltrim($name, '/');
                $destinationFile = $destination . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);

                if (str_ends_with($relative, '/')) {
                    if (!is_dir($destinationFile) && !mkdir($destinationFile, 0700, true)) {
                        throw new \RuntimeException(Yii::t('BazaarModule.base', 'Could not create an extraction directory.'));
                    }
                    continue;
                }

                $parent = dirname($destinationFile);

                if (!is_dir($parent) && !mkdir($parent, 0700, true)) {
                    throw new \RuntimeException(Yii::t('BazaarModule.base', 'Could not create an extraction directory.'));
                }

                // Do not allow duplicate entries to overwrite an already extracted file.
                if (file_exists($destinationFile) || is_link($destinationFile)) {
                    throw new \RuntimeException(Yii::t('BazaarModule.base', 'The archive contains duplicate file paths.'));
                }

                $input = $zip->getStream($name);
                if ($input === false) {
                    throw new \RuntimeException(Yii::t('BazaarModule.base', 'Could not read a file from the module archive.'));
                }

                $output = fopen($destinationFile, 'xb');
                if ($output === false) {
                    fclose($input);
                    throw new \RuntimeException(Yii::t('BazaarModule.base', 'Could not create an extracted file.'));
                }

                try {
                    $copied = stream_copy_to_stream($input, $output);
                    if ($copied === false || !fflush($output)) {
                        throw new \RuntimeException(Yii::t('BazaarModule.base', 'Could not extract the module archive.'));
                    }
                } finally {
                    fclose($input);
                    fclose($output);
                }
            }
        } finally {
            $zip->close();
        }
    }

    public function isModuleEnabled(string $moduleId): bool
    {
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $moduleId)) {
            return false;
        }

        try {
            // ModuleManager knows about installed modules even when they are
            // disabled. Application::hasModule() alone cannot distinguish an
            // unregistered directory from a registered-but-disabled module.
            $moduleManager = Yii::$app->moduleManager;
            $module = $moduleManager->getModule($moduleId, false);

            if (!$module instanceof \humhub\components\Module) {
                $modulePath = $this->getModulesPath() . $moduleId;
                if (!is_dir($modulePath) || !is_file($modulePath . DIRECTORY_SEPARATOR . 'config.php')) {
                    return false;
                }

                $registeredId = $moduleManager->register($modulePath);
                if (!is_string($registeredId) || $registeredId === '') {
                    return false;
                }

                $module = $moduleManager->getModule($registeredId, false);
            }

            return $module instanceof \humhub\components\Module && $module->isEnabled;
        } catch (\Throwable $e) {
            Yii::debug(
                'Could not resolve module "' . $moduleId . '" while checking its enabled state: ' . $e->getMessage(),
                'bazaar'
            );

            return false;
        }
    }

    public function getInstalledModules(): array
    {
        $path = $this->getModulesPath();

        if (!is_dir($path)) {
            return [];
        }

        $modules = [];

        foreach (new \DirectoryIterator($path) as $entry) {
            if ($entry->isDot() || !$entry->isDir() || !preg_match('/^[a-zA-Z0-9_-]+$/', $entry->getFilename())) {
                continue;
            }

            $manifestPath = $entry->getPathname() . DIRECTORY_SEPARATOR . 'module.json';

            if (!is_file($manifestPath)) {
                continue;
            }

            $manifest = json_decode((string)file_get_contents($manifestPath), true);

            if (!is_array($manifest)) {
                continue;
            }

            $modules[] = [
                'id' => $entry->getFilename(),
                'name' => (string)($manifest['name'] ?? $entry->getFilename()),
                'version' => (string)($manifest['version'] ?? ''),
                'description' => (string)($manifest['description'] ?? ''),
                'enabled' => $this->isModuleEnabled($entry->getFilename()),
                'path' => $entry->getPathname(),
            ];
        }

        usort($modules, static fn(array $a, array $b): int => strcasecmp($a['name'], $b['name']));

        return $modules;
    }

    public function deleteModule(string $moduleId): bool
    {
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $moduleId)) {
            throw new BadRequestHttpException(Yii::t('BazaarModule.base', 'Invalid module ID.'));
        }

        if ($this->isModuleEnabled($moduleId)) {
            throw new ForbiddenHttpException(Yii::t(
                'BazaarModule.base',
                'Disable module "{id}" before removing its files.',
                ['id' => $moduleId]
            ));
        }

        $modulesPath = realpath($this->getModulesPath());
        $target = realpath($this->getModulesPath() . $moduleId);

        if ($modulesPath === false || $target === false || !str_starts_with($target, $modulesPath . DIRECTORY_SEPARATOR) || !is_dir($target)) {
            throw new BadRequestHttpException(Yii::t('BazaarModule.base', 'Module directory not found.'));
        }

        return $this->deleteDirectory($target);
    }

    private function deleteDirectory(string $path): bool
    {
        if (!is_dir($path)) {
            return true;
        }

        foreach (new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        ) as $item) {
            if ($item->isDir() && !$item->isLink()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }

        return @rmdir($path);
    }
}

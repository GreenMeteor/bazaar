<?php

namespace humhub\modules\bazaar\models;

use humhub\modules\bazaar\services\ModuleInstaller;
use Yii;
use yii\base\Model;
use yii\web\UploadedFile;

/**
 * Handles local module ZIP uploads from the Bazaar administration area.
 */
class ModuleUploadForm extends Model
{
    public ?UploadedFile $zipFile = null;
    public bool $replaceExisting = false;

    public function rules(): array
    {
        return [
            ['zipFile', 'file', 'skipOnEmpty' => false, 'extensions' => ['zip'], 'mimeTypes' => [
                'application/zip',
                'application/x-zip-compressed',
                'application/octet-stream',
            ], 'maxSize' => ModuleInstaller::MAX_ARCHIVE_SIZE],
            ['replaceExisting', 'boolean'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'zipFile' => Yii::t('BazaarModule.base', 'Module zip file'),
            'replaceExisting' => Yii::t('BazaarModule.base', 'Replace an existing module'),
        ];
    }

    public function upload(): ?string
    {
        if (!$this->validate()) {
            return null;
        }

        $path = tempnam(sys_get_temp_dir(), 'humhub_bazaar_upload_');

        if ($path === false || !$this->zipFile->saveAs($path)) {
            if ($path !== false) {
                @unlink($path);
            }

            $this->addError('zipFile', Yii::t('BazaarModule.base', 'Failed to save the uploaded file.'));

            return null;
        }

        return $path;
    }

    public function install(string $archivePath): ?array
    {
        try {
            return Yii::createObject(ModuleInstaller::class)->install($archivePath, $this->replaceExisting);
        } catch (\Throwable $e) {
            $this->addError('zipFile', $e->getMessage());

            return null;
        }
    }
}

# Changelog

## 1.3.3
- Fixed: Match purchased products to local module directories using a unique normalized prefix fallback when display names and directory IDs differ.
- Fixed: Resolve registered but disabled modules through HumHub's ModuleManager so status badges and Enable/Disable controls can be rendered correctly.
- Fixed: Register locally installed modules from their `config.php` when HumHub has not discovered them in the current runtime.

## 1.3.2
- Fixed: Handle module directories that are not registered with HumHub without throwing an exception on the Installed Modules page.
- Fixed: Treat modules that cannot be resolved by HumHub's module manager as disabled for status display and management checks.

## 1.3.1
- Fixed: Match purchased products to local module directories using directory IDs and manifest names or IDs.
- Fixed: Resolve module instances through HumHub's module manager when checking enabled state, including locally installed modules that are disabled.

## 1.3.0
- Fixed: Show the enabled or disabled state of locally installed purchased modules instead of a misleading "Not installed" badge.
- Enh: Add POST-only Enable and Disable actions to purchased module cards, using HumHub's module lifecycle and queued deactivation flow.
- Chg: Allow module removal only for installed, disabled purchased modules.

## 1.2.9
- Fixed: Detect local module directories for purchased products even when the directory does not contain a readable module manifest.
- Chg: Replace the lengthy local-installation warning with a compact "Not installed" state.

## 1.2.8
- Chg: Use HumHub's native `card-panel`, `card-header`, `card-body`, and `card-footer` structure for individual module cards while retaining the surrounding administration panels.
- Fixed: Determine module enabled state through `humhub\components\Module::isEnabled` instead of querying the `module_enabled` table directly.

## 1.2.7
- Fixed: Force a fresh API catalogue fetch on the Installed Modules page so cached purchase status does not hide recent purchases.
- Fixed: Keep confirmed purchased products visible when the API does not expose a reliable local HumHub module directory identifier.
- Chg: Mark unmatched local installation status as unverified and disable removal actions for those entries.

## 1.2.6
- Fixed: Match purchased API catalogue entries to installed HumHub modules using exact normalized module names when the API does not provide a directory identifier.
- Fixed: Avoid treating numeric catalogue IDs as local module directory IDs and skip ambiguous name matches.

## 1.2.5
- Fixed: Match purchased catalogue products to installed HumHub modules only through an explicit module or directory identifier, rather than treating the API catalogue product ID as the local module directory ID.
- Fixed: Avoid ambiguous API lookups using a local directory name when the API catalogue ID and module directory ID differ.

## 1.2.3
- Chg: Make the Installed Modules page use the same `isPurchased` status returned by the Bazaar catalogue API as the Purchased category.
- Chg: Redesign installed module listings as responsive, Space Card-inspired cards with a cover area, overlapping module icon, purchase badge, enabled status, module ID, installed version, description, and actions.
- Fixed: Correct duplicate image class attributes and improve small-screen card spacing.

## 1.2.2
- Chg: Limit Installed Modules to Bazaar modules marked as purchased for the current user and installed locally.
- Chg: Redesign the installed module inventory as responsive cards with a module icon or screenshot, purchase and enabled status, installed version, description, and detail or removal actions.
- Fixed: Prevent the removal endpoint from deleting modules that are not recognized as purchased Bazaar modules installed locally.

## 1.2.1
- Fixed: Respect the saved Enable Purchasing setting in the module catalogue and detail views.
- Fixed: Show a disabled "Not Available" action instead of Buy or Purchase when purchasing is disabled.
- Fixed: Enforce the saved purchasing setting on direct checkout requests and prevent installation flows from redirecting to checkout when purchasing is disabled.

## 1.2.0
- Enh: Add an Installed Modules administration page with module IDs, versions, descriptions, and enabled state.
- Enh: Allow administrators to remove files for disabled modules through a POST-only action with path containment checks.
- Enh: Add navigation between the Bazaar catalogue, local ZIP upload, and installed module inventory.
- Chg: Stream ZIP entries to disk during extraction instead of loading each complete file into PHP memory.
- Chg: Create temporary extraction directories inside the HumHub modules directory so final directory moves stay on the same filesystem.
- Chg: Reject duplicate ZIP entry paths and additional unsafe path forms before installation.
- Chg: Use the saved API key consistently for API requests and authenticated marketplace ZIP downloads.
- Fixed: Connect the local upload form to a controller action that validates and installs the uploaded archive.
- Fixed: Avoid cross-filesystem directory rename failures when PHP's system temporary directory and HumHub's modules directory are on different filesystems.

## 1.1.0
- Enh: Add a dedicated Upload Module administration page for installing local HumHub module ZIP files.
- Enh: Add `models/ModuleUploadForm.php` for validated local module uploads.
- Enh: Add `services/ModuleInstaller.php` for safe, atomic module archive installation shared by marketplace downloads and local uploads.
- Enh: Add explicit existing-module replacement support with enabled-module protection.
- Enh: Validate ZIP archives for a single module root, `module.json`, unsafe paths, symbolic links, file count, and uncompressed size.
- Enh: Add automatic rollback when replacing an existing module fails.
- Enh: Require POST requests for marketplace installation actions.
- Enh: Stream marketplace downloads with HTTPS enforcement and the same 64 MB archive limit used for local uploads.
- Chg: Route marketplace installations through the dedicated installer service instead of extracting directly with `ZipArchive::extractTo()`.
- Chg: Validate downloaded ZIPs before they can modify `protected/modules/`.
- Chg: Add a dedicated local module upload entry point alongside Settings.
- Fixed: Prevent invalid or incomplete marketplace archives from being partially extracted.
- Fixed: Avoid loading entire marketplace download responses into PHP memory before validation.
- Fixed: Prevent installation through a GET action when the operation modifies files on disk.

## 1.0.0-beta.5
- Enh: Add the Purchased category.
- Chg: Completely redesign the module list.
- Enh: Make the module fully translatable.
- Fixed: Resolve minor bugs.

## 1.0.0-beta.4
- Fixed: Capture the original order correctly when sorting modules.

## 1.0.0-beta.3
- Fixed: Make the search field, category filter, and sort order update the module list automatically without requiring the search button.
- Fixed: Prevent the category filter and sort dropdown from triggering a full page reload through a competing inline script.
- Fixed: Keep the search input, category filter, and sort controls in a single ActiveForm.
- Enh: Filter and sort module cards entirely client-side without a page reload.

## 1.0.0-beta.2
- Fixed: Prevent the "Clear cache" action from displaying an error before its success message in AJAX and full-page requests.
- Fixed: Correct the category filter and sort dropdown behavior in the Bazaar module.
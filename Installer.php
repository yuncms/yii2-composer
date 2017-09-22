<?php
/**
 * @link http://www.tintsoft.com/
 * @copyright Copyright (c) 2012 TintSoft Technology Co. Ltd.
 * @license http://www.tintsoft.com/license/
 */

namespace yuncms\composer;

use Composer\Package\PackageInterface;
use Composer\Installer\LibraryInstaller;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Script\CommandEvent;
use Composer\Script\Event;
use Composer\Util\Filesystem;

/**
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Installer extends LibraryInstaller
{
    const EXTRA_FIELD = 'yuncms';
    const TRANSLATE_FILE = 'yuncms/i18n.php';
    const MIGRATION_FILE = 'yuncms/migrations.php';

    const MODULE_FILE = 'yuncms/modules.php';
    const BACKEND_MODULE_FILE = 'yuncms/backend-modules.php';

    /**
     * @inheritdoc
     */
    public function supports($packageType)
    {
        return $packageType === 'yii2-extension';
    }

    /**
     * @inheritdoc
     */
    public function install(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        // install the package the normal composer way
        parent::install($repo, $package);
        // add the package to yuncms/modules.php
        $this->addModule($package);
        $this->addTranslate($package);
        $this->addMigration($package);
    }

    /**
     * @inheritdoc
     */
    public function update(InstalledRepositoryInterface $repo, PackageInterface $initial, PackageInterface $target)
    {
        parent::update($repo, $initial, $target);
        $this->removeModule($initial);
        $this->removeTranslate($initial);
        $this->removeMigration($initial);
        $this->addModule($target);
        $this->addTranslate($target);
        $this->addMigration($target);
    }

    /**
     * @inheritdoc
     */
    public function uninstall(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        // uninstall the package the normal composer way
        parent::uninstall($repo, $package);
        // remove the package from yuncms/modules.php
        $this->removeModule($package);

        // remove the package from yuncms/i18n.php
        $this->removeTranslate($package);

        // remove the package from yuncms/migrations.php
        $this->removeMigration($package);
    }

    /**
     * 安装模块
     * @param PackageInterface $package
     */
    protected function addModule(PackageInterface $package)
    {
        $extra = $package->getExtra();
        if (isset($extra[self::EXTRA_FIELD]['name']) && isset($extra[self::EXTRA_FIELD]['frontend'])) {
            $module = $extra[self::EXTRA_FIELD]['frontend'];
            if (isset($module['class'])) {
                $modules = $this->loadModules();
                $modules[$extra[self::EXTRA_FIELD]['name']] = $module;
                $this->saveModules($modules);
            }

            $backendModule = $extra[self::EXTRA_FIELD]['backend'];
            if (isset($backendModule['class'])) {
                $backendModules = $this->loadBackendModules();
                $backendModules[$extra[self::EXTRA_FIELD]['name']] = $backendModule;
                $this->saveBackendModules($backendModules);
            }
        }
    }

    /**
     * 删除模块
     * @param PackageInterface $package
     */
    protected function removeModule(PackageInterface $package)
    {
        $extra = $package->getExtra();
        if (isset($extra[self::EXTRA_FIELD]['name'])) {
            $modules = $this->loadModules();
            unset($modules[$extra[self::EXTRA_FIELD]['name']]);
            $this->saveModules($modules);
            $backendModules = $this->loadBackendModules();
            unset($backendModules[$extra[self::EXTRA_FIELD]['name']]);
            $this->saveModules($backendModules);
        }
    }

    /**
     * 加载模块
     * @return array|mixed
     */
    protected function loadModules()
    {
        $file = $this->vendorDir . '/' . static::MODULE_FILE;
        if (!is_file($file)) {
            return [];
        }
        // invalidate opcache of extensions.php if exists
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($file, true);
        }
        return require($file);
    }

    /**
     * 加载后端模块
     * @return array|mixed
     */
    protected function loadBackendModules()
    {
        $file = $this->vendorDir . '/' . static::BACKEND_MODULE_FILE;
        if (!is_file($file)) {
            return [];
        }
        // invalidate opcache of extensions.php if exists
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($file, true);
        }
        return require($file);
    }

    /**
     * 保存模块
     * @param array $modules
     */
    protected function saveModules(array $modules)
    {
        $file = $this->vendorDir . '/' . static::MODULE_FILE;
        if (!file_exists(dirname($file))) {
            mkdir(dirname($file), 0777, true);
        }
        $array = var_export($modules, true);
        file_put_contents($file, "<?php\n\nreturn $array;\n");
        // invalidate opcache of extensions.php if exists
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($file, true);
        }
    }

    /**
     * 保存后端模块
     * @param array $modules
     */
    protected function saveBackendModules(array $modules)
    {
        $file = $this->vendorDir . '/' . static::BACKEND_MODULE_FILE;
        if (!file_exists(dirname($file))) {
            mkdir(dirname($file), 0777, true);
        }
        $array = var_export($modules, true);
        file_put_contents($file, "<?php\n\nreturn $array;\n");
        // invalidate opcache of extensions.php if exists
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($file, true);
        }
    }

    /**
     * 加载迁移
     * @param PackageInterface $package
     */
    protected function addMigration(PackageInterface $package)
    {
        $extra = $package->getExtra();
        if (isset($extra[self::EXTRA_FIELD]['migrationNamespace'])) {
            $migrations = $this->loadMigrations();
            $migrations[] = $extra[self::EXTRA_FIELD]['migrationNamespace'];
            $migrations = array_unique($migrations);
            $this->saveMigrations($migrations);
        }
    }

    /**
     * 删除迁移
     * @param PackageInterface $package
     */
    protected function removeMigration(PackageInterface $package)
    {
        $translates = $this->loadTranslates();
        $extra = $package->getExtra();
        if (isset($extra[self::EXTRA_FIELD]['migrationNamespace'])) {
            foreach ($translates as $id => $translate) {
                if ($translate == $extra[self::EXTRA_FIELD]['migrationNamespace']) {
                    unset($translates[$id]);
                }
            }
            $this->saveTranslates($translates);
        }
    }

    /**
     * 加载迁移
     * @return array|mixed
     */
    protected function loadMigrations()
    {
        $file = $this->vendorDir . '/' . static::MIGRATION_FILE;
        if (!is_file($file)) {
            return [];
        }
        // invalidate opcache of extensions.php if exists
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($file, true);
        }
        return require($file);
    }

    /**
     * 保存迁移
     * @param array $migrations
     */
    protected function saveMigrations(array $migrations)
    {
        $file = $this->vendorDir . '/' . static::MIGRATION_FILE;
        if (!file_exists(dirname($file))) {
            mkdir(dirname($file), 0777, true);
        }
        $string = '';
        foreach ($migrations as $migration) {
            $string .= "'" . $migration . "',\n";
        }
        file_put_contents($file, "<?php\n\nreturn [{$string}];\n");
        // invalidate opcache of extensions.php if exists
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($file, true);
        }
    }

    /**
     * 加载翻译
     * @param PackageInterface $package
     */
    protected function addTranslate(PackageInterface $package)
    {
        $extra = $package->getExtra();
        if (isset($extra[self::EXTRA_FIELD]['name']) && isset($extra[self::EXTRA_FIELD]['i18n'])) {
            $translates = $this->loadTranslates();
            $translates[$extra[self::EXTRA_FIELD]['name']] = $extra[self::EXTRA_FIELD]['i18n'];
            $this->saveTranslates($translates);
        }
    }

    /**
     * 删除翻译
     * @param PackageInterface $package
     */
    protected function removeTranslate(PackageInterface $package)
    {
        $translates = $this->loadTranslates();
        $extra = $package->getExtra();
        if (isset($extra[self::EXTRA_FIELD]['name'])) {
            unset($translates[$extra[self::EXTRA_FIELD]['name']]);
            $this->saveTranslates($translates);
        }
    }

    /**
     * 加载翻译
     * @return array|mixed
     */
    protected function loadTranslates()
    {
        $file = $this->vendorDir . '/' . static::TRANSLATE_FILE;
        if (!is_file($file)) {
            return [];
        }
        // invalidate opcache of extensions.php if exists
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($file, true);
        }
        return require($file);
    }

    /**
     * 保存翻译
     * @param array $translates
     */
    protected function saveTranslates(array $translates)
    {
        $file = $this->vendorDir . '/' . static::TRANSLATE_FILE;
        if (!file_exists(dirname($file))) {
            mkdir(dirname($file), 0777, true);
        }
        $array = var_export($translates, true);
        file_put_contents($file, "<?php\n\nreturn $array;\n");
        // invalidate opcache of extensions.php if exists
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($file, true);
        }
    }
}
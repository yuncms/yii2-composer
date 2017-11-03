<?php
/**
 * @link http://www.tintsoft.com/
 * @copyright Copyright (c) 2012 TintSoft Technology Co. Ltd.
 * @license http://www.tintsoft.com/license/
 */

namespace yuncms\composer;

use Composer\Script\Event;
use Composer\Util\Filesystem;
use Composer\Script\CommandEvent;
use Composer\Package\PackageInterface;
use Composer\Installer\LibraryInstaller;
use Composer\Repository\InstalledRepositoryInterface;

/**
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @author Tongle Xu <xutongle@gmail.com>
 */
class Installer extends \yii\composer\Installer
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
        if (isset($extra[self::EXTRA_FIELD]['name'])) {
            //处理前端模块
            if (isset($extra[self::EXTRA_FIELD]['frontend']['class'])) {
                $modules = $this->loadModules();
                $modules[$extra[self::EXTRA_FIELD]['name']] = $extra[self::EXTRA_FIELD]['frontend'];
                $this->saveModules($modules);
            }
            //处理后端模块
            if (isset($extra[self::EXTRA_FIELD]['backend']['class'])) {
                $backendModules = $this->loadBackendModules();
                $backendModules[$extra[self::EXTRA_FIELD]['name']] = $extra[self::EXTRA_FIELD]['backend'];
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
            $this->saveBackendModules($backendModules);
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
        $this->opcacheInvalidate($file);
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
        $this->opcacheInvalidate($file);
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
        $this->opcacheInvalidate($file);
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
        $this->opcacheInvalidate($file);
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
        $translates = $this->loadConfig(self::MIGRATION_FILE);
        $extra = $package->getExtra();
        if (isset($extra[self::EXTRA_FIELD]['migrationNamespace'])) {
            foreach ($translates as $id => $translate) {
                if ($translate == $extra[self::EXTRA_FIELD]['migrationNamespace']) {
                    unset($translates[$id]);
                }
            }
            $this->saveConfig($translates,self::MIGRATION_FILE);
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
        $this->opcacheInvalidate($file);
        return require($file);
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
            $translateName = $extra[self::EXTRA_FIELD]['name'] . '*';
            $translates[$translateName] = $extra[self::EXTRA_FIELD]['i18n'];
            $this->saveConfig($translates,self::TRANSLATE_FILE);
        }
    }

    /**
     * 删除翻译
     * @param PackageInterface $package
     */
    protected function removeTranslate(PackageInterface $package)
    {
        $extra = $package->getExtra();
        if (isset($extra[self::EXTRA_FIELD]['name'])) {
            $translates = $this->loadTranslates();
            $translateName = $extra[self::EXTRA_FIELD]['name'] . '*';
            unset($translates[$translateName]);
            $this->saveConfig($translates,self::TRANSLATE_FILE);
        }
    }

    /**
     * 加载配置
     * @param string $file
     * @return array|mixed
     */
    protected function loadConfig($file): array
    {
        $file = $this->vendorDir . '/' . $file;
        if (!is_file($file)) {
            return [];
        }
        $this->opcacheInvalidate($file);
        return require($file);
    }

    /**
     * 保存配置到文件
     * @param array $config
     * @param string $file
     */
    protected function saveConfig(array $config, $file): void
    {
        $file = $this->vendorDir . '/' . $file;
        if (!file_exists(dirname($file))) {
            mkdir(dirname($file), 0777, true);
        }
        $array = var_export($config, true);
        file_put_contents($file, "<?php\n\nreturn $array;\n");
        $this->opcacheInvalidate($file);
    }

    /**
     * @param $file
     * @return void
     */
    protected function opcacheInvalidate($file): void
    {
        // invalidate opcache of extensions.php if exists
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($file, true);
        }
    }
}
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
class Installer extends LibraryInstaller
{
    const EXTRA_BOOTSTRAP = 'bootstrap';
    const EXTENSION_FILE = 'yiisoft/extensions.php';

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
        // add the package to yiisoft/extensions.php
        $this->addPackage($package);
        // ensure the yii2-dev package also provides Yii.php in the same place as yii2 does
        if ($package->getName() == 'yiisoft/yii2-dev') {
            $this->linkBaseYiiFiles();
        }
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
        $this->removePackage($initial);
        $this->addPackage($target);
        // ensure the yii2-dev package also provides Yii.php in the same place as yii2 does
        if ($initial->getName() == 'yiisoft/yii2-dev') {
            $this->linkBaseYiiFiles();
        }
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
        // remove the package from yiisoft/extensions.php
        $this->removePackage($package);
        // remove links for Yii.php
        if ($package->getName() == 'yiisoft/yii2-dev') {
            $this->removeBaseYiiFiles();
        }

        // remove the package from yuncms/modules.php
        $this->removeModule($package);

        // remove the package from yuncms/i18n.php
        $this->removeTranslate($package);

        // remove the package from yuncms/migrations.php
        $this->removeMigration($package);
    }

    protected function addPackage(PackageInterface $package)
    {
        $extension = [
            'name' => $package->getName(),
            'version' => $package->getVersion(),
        ];

        $alias = $this->generateDefaultAlias($package);
        if (!empty($alias)) {
            $extension['alias'] = $alias;
        }
        $extra = $package->getExtra();
        if (isset($extra[self::EXTRA_BOOTSTRAP])) {
            $extension['bootstrap'] = $extra[self::EXTRA_BOOTSTRAP];
        }

        $extensions = $this->loadExtensions();
        $extensions[$package->getName()] = $extension;
        $this->saveExtensions($extensions);
    }

    protected function generateDefaultAlias(PackageInterface $package)
    {
        $fs = new Filesystem;
        $vendorDir = $fs->normalizePath($this->vendorDir);
        $autoload = $package->getAutoload();

        $aliases = [];

        if (!empty($autoload['psr-0'])) {
            foreach ($autoload['psr-0'] as $name => $path) {
                $name = str_replace('\\', '/', trim($name, '\\'));
                if (!$fs->isAbsolutePath($path)) {
                    $path = $this->vendorDir . '/' . $package->getPrettyName() . '/' . $path;
                }
                $path = $fs->normalizePath($path);
                if (strpos($path . '/', $vendorDir . '/') === 0) {
                    $aliases["@$name"] = '<vendor-dir>' . substr($path, strlen($vendorDir)) . '/' . $name;
                } else {
                    $aliases["@$name"] = $path . '/' . $name;
                }
            }
        }

        if (!empty($autoload['psr-4'])) {
            foreach ($autoload['psr-4'] as $name => $path) {
                if (is_array($path)) {
                    // ignore psr-4 autoload specifications with multiple search paths
                    // we can not convert them into aliases as they are ambiguous
                    continue;
                }
                $name = str_replace('\\', '/', trim($name, '\\'));
                if (!$fs->isAbsolutePath($path)) {
                    $path = $this->vendorDir . '/' . $package->getPrettyName() . '/' . $path;
                }
                $path = $fs->normalizePath($path);
                if (strpos($path . '/', $vendorDir . '/') === 0) {
                    $aliases["@$name"] = '<vendor-dir>' . substr($path, strlen($vendorDir));
                } else {
                    $aliases["@$name"] = $path;
                }
            }
        }

        return $aliases;
    }

    protected function removePackage(PackageInterface $package)
    {
        $packages = $this->loadExtensions();
        unset($packages[$package->getName()]);
        $this->saveExtensions($packages);
    }

    protected function loadExtensions()
    {
        $file = $this->vendorDir . '/' . static::EXTENSION_FILE;
        if (!is_file($file)) {
            return [];
        }
        $this->opcacheInvalidate($file);
        $extensions = require($file);

        $vendorDir = str_replace('\\', '/', $this->vendorDir);
        $n = strlen($vendorDir);

        foreach ($extensions as &$extension) {
            if (isset($extension['alias'])) {
                foreach ($extension['alias'] as $alias => $path) {
                    $path = str_replace('\\', '/', $path);
                    if (strpos($path . '/', $vendorDir . '/') === 0) {
                        $extension['alias'][$alias] = '<vendor-dir>' . substr($path, $n);
                    }
                }
            }
        }

        return $extensions;
    }

    protected function saveExtensions(array $extensions)
    {
        $file = $this->vendorDir . '/' . static::EXTENSION_FILE;
        if (!file_exists(dirname($file))) {
            mkdir(dirname($file), 0777, true);
        }
        $array = str_replace("'<vendor-dir>", '$vendorDir . \'', var_export($extensions, true));
        file_put_contents($file, "<?php\n\n\$vendorDir = dirname(__DIR__);\n\nreturn $array;\n");
        $this->opcacheInvalidate($file);
    }

    protected function linkBaseYiiFiles()
    {
        $yiiDir = $this->vendorDir . '/yiisoft/yii2';
        if (!file_exists($yiiDir)) {
            mkdir($yiiDir, 0777, true);
        }
        foreach (['Yii.php', 'BaseYii.php', 'classes.php'] as $file) {
            file_put_contents($yiiDir . '/' . $file, <<<EOF
<?php
/**
 * This is a link provided by the yiisoft/yii2-dev package via yii2-composer plugin.
 *
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

return require(__DIR__ . '/../yii2-dev/framework/$file');

EOF
            );
        }
    }

    protected function removeBaseYiiFiles()
    {
        $yiiDir = $this->vendorDir . '/yiisoft/yii2';
        foreach (['Yii.php', 'BaseYii.php', 'classes.php'] as $file) {
            if (file_exists($yiiDir . '/' . $file)) {
                unlink($yiiDir . '/' . $file);
            }
        }
        if (file_exists($yiiDir)) {
            rmdir($yiiDir);
        }
    }

    /**
     * Special method to run tasks defined in `[extra][yii\composer\Installer::postCreateProject]` key in `composer.json`
     *
     * @param Event $event
     */
    public static function postCreateProject($event)
    {
        static::runCommands($event, __METHOD__);
    }

    /**
     * Special method to run tasks defined in `[extra][yii\composer\Installer::postInstall]` key in `composer.json`
     *
     * @param Event $event
     * @since 2.0.5
     */
    public static function postInstall($event)
    {
        static::runCommands($event, __METHOD__);
    }

    /**
     * Special method to run tasks defined in `[extra][$extraKey]` key in `composer.json`
     *
     * @param Event $event
     * @param string $extraKey
     * @since 2.0.5
     */
    protected static function runCommands($event, $extraKey)
    {
        $params = $event->getComposer()->getPackage()->getExtra();
        if (isset($params[$extraKey]) && is_array($params[$extraKey])) {
            foreach ($params[$extraKey] as $method => $args) {
                call_user_func_array([__CLASS__, $method], (array)$args);
            }
        }
    }

    /**
     * Sets the correct permission for the files and directories listed in the extra section.
     * @param array $paths the paths (keys) and the corresponding permission octal strings (values)
     */
    public static function setPermission(array $paths)
    {
        foreach ($paths as $path => $permission) {
            echo "chmod('$path', $permission)...";
            if (is_dir($path) || is_file($path)) {
                try {
                    if (chmod($path, octdec($permission))) {
                        echo "done.\n";
                    };
                } catch (\Exception $e) {
                    echo $e->getMessage() . "\n";
                }
            } else {
                echo "file not found.\n";
            }
        }
    }

    /**
     * Generates a cookie validation key for every app config listed in "config" in extra section.
     * You can provide one or multiple parameters as the configuration files which need to have validation key inserted.
     */
    public static function generateCookieValidationKey()
    {
        $configs = func_get_args();
        $key = self::generateRandomString();
        foreach ($configs as $config) {
            if (is_file($config)) {
                $content = preg_replace('/(("|\')cookieValidationKey("|\')\s*=>\s*)(""|\'\')/', "\\1'$key'", file_get_contents($config), -1, $count);
                if ($count > 0) {
                    file_put_contents($config, $content);
                }
            }
        }
    }

    protected static function generateRandomString()
    {
        if (!extension_loaded('openssl')) {
            throw new \Exception('The OpenSSL PHP extension is required by Yii2.');
        }
        $length = 32;
        $bytes = openssl_random_pseudo_bytes($length);
        return strtr(substr(base64_encode($bytes), 0, $length), '+/=', '_-.');
    }

    /**
     * Copy files to specified locations.
     * @param array $paths The source files paths (keys) and the corresponding target locations
     * for copied files (values). Location can be specified as an array - first element is target
     * location, second defines whether file can be overwritten (by default method don't overwrite
     * existing files).
     * @since 2.0.5
     */
    public static function copyFiles(array $paths)
    {
        foreach ($paths as $source => $target) {
            // handle file target as array [path, overwrite]
            $target = (array)$target;
            echo "Copying file $source to $target[0] - ";

            if (!is_file($source)) {
                echo "source file not found.\n";
                continue;
            }

            if (is_file($target[0]) && empty($target[1])) {
                echo "target file exists - skip.\n";
                continue;
            } elseif (is_file($target[0]) && !empty($target[1])) {
                echo "target file exists - overwrite - ";
            }

            try {
                if (!is_dir(dirname($target[0]))) {
                    mkdir(dirname($target[0]), 0777, true);
                }
                if (copy($source, $target[0])) {
                    echo "done.\n";
                }
            } catch (\Exception $e) {
                echo $e->getMessage() . "\n";
            }
        }
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
        $this->opcacheInvalidate($file);
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
        $array = var_export($migrations, true);
        file_put_contents($file, "<?php\n\nreturn $array;\n");
        $this->opcacheInvalidate($file);
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
        $extra = $package->getExtra();
        if (isset($extra[self::EXTRA_FIELD]['name'])) {
            $translates = $this->loadTranslates();
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
        $this->opcacheInvalidate($file);
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
        $this->opcacheInvalidate($file);
    }

    /**
     * @param $file
     * @return void
     */
    protected function opcacheInvalidate($file)
    {
        // invalidate opcache of extensions.php if exists
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($file, true);
        }
    }
}
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
    const MODULE_FILE = 'yuncms/modules.php';
    const BACKEND_MODULE_FILE = 'yuncms/modules.php';

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
        //$this->addModule($package);
        $this->addTranslate($package);
    }

    /**
     * @inheritdoc
     */
    public function update(InstalledRepositoryInterface $repo, PackageInterface $initial, PackageInterface $target)
    {
        parent::update($repo, $initial, $target);
        //$this->removeModule($initial);
        $this->removeTranslate($initial);
        //$this->addModule($target);
        $this->addTranslate($target);
    }

    /**
     * @inheritdoc
     */
    public function uninstall(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        // uninstall the package the normal composer way
        parent::uninstall($repo, $package);
        // remove the package from yuncms/modules.php
        //$this->removeModule($package);
        // remove the package from yuncms/i18n.php
        $this->removeTranslate($package);
    }

    protected function addModule(PackageInterface $package)
    {
        $module = [
            'name' => $package->getName(),
            'version' => $package->getVersion(),
        ];

        $extra = $package->getExtra();

        if (isset($extra[self::EXTRA_FIELD])) {
            $module['config'] = $extra[self::EXTRA_FIELD];
            //生成语言包配置
            if (isset($module['config']['backend'])) {//处理后端模块

            }
            if (isset($module['config']['frontend'])) {//处理 前端模块

            }
            if (isset($module['config']['i18n'])) {//处理语言包

            }
            if (isset($module['config']['migration'])) {//处理迁移

            }
        }

        $modules = $this->loadModules();
        $modules[$package->getName()] = $module;
        $this->saveModules($modules);
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

    protected function removeModule(PackageInterface $package)
    {
        $packages = $this->loadModules();
        unset($packages[$package->getName()]);
        $this->saveExtensions($packages);
    }

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

    /**
     * 保存模块
     * @param array $extensions
     */
    protected function saveModules(array $extensions)
    {
        $file = $this->vendorDir . '/' . static::MODULE_FILE;
        if (!file_exists(dirname($file))) {
            mkdir(dirname($file), 0777, true);
        }
        $array = str_replace("'<vendor-dir>", '$vendorDir . \'', var_export($extensions, true));
        file_put_contents($file, "<?php\n\n\$vendorDir = dirname(__DIR__);\n\nreturn $array;\n");
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
        if (isset($extra[self::EXTRA_FIELD])) {
            $extra = $extra[self::EXTRA_FIELD];
            $moduleName = $extra['name'];
            $translate = $extra['i18n'];
            $translates = $this->loadTranslates();
            $translates[$moduleName] = $translate;
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
        if (isset($extra[self::EXTRA_FIELD])) {
            $extra = $extra[self::EXTRA_FIELD];
            unset($translates[$extra['name']]);
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
        file_put_contents($file, "<?php\n\n\$vendorDir = dirname(__DIR__);\n\nreturn $array;\n");
        // invalidate opcache of extensions.php if exists
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($file, true);
        }
    }
}
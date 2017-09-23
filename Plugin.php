<?php
/**
 * @link http://www.tintsoft.com/
 * @copyright Copyright (c) 2012 TintSoft Technology Co. Ltd.
 * @license http://www.tintsoft.com/license/
 */

namespace yuncms\composer;

use Composer\Composer;
use Composer\IO\IOInterface;

/**
 * Plugin is the composer plugin that registers the Yii composer installer.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @author Tongle XU <xutongle@gmail.com>
 */
class Plugin implements \yii\composer\Plugin
{
    /**
     * @var string path to the vendor directory.
     */
    private $_vendorDir;

    /**
     * @inheritdoc
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        $installer = new Installer($io, $composer);
        $composer->getInstallationManager()->addInstaller($installer);
        $composer->getInstallationManager()->getInstaller('yii2-extension');

        $this->_vendorDir = rtrim($composer->getConfig()->get('vendor-dir'), '/');
        $this->mkFile($this->_vendorDir . '/yiisoft/extensions.php');
        $this->mkFile($this->_vendorDir . '/' . Installer::BACKEND_MODULE_FILE);
        $this->mkFile($this->_vendorDir . '/' . Installer::MODULE_FILE);
        $this->mkFile($this->_vendorDir . '/' . Installer::MIGRATION_FILE);
        $this->mkFile($this->_vendorDir . '/' . Installer::TRANSLATE_FILE);
    }

    /**
     * 创建文件
     * @param string $file
     */
    public function mkFile($file)
    {
        if (!is_file($file)) {
            @mkdir(dirname($file), 0777, true);
            file_put_contents($file, "<?php\n\nreturn [];\n");
        }
    }
}

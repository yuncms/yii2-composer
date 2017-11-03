<?php
/**
 * @link http://www.tintsoft.com/
 * @copyright Copyright (c) 2012 TintSoft Technology Co. Ltd.
 * @license http://www.tintsoft.com/license/
 */

namespace yuncms\composer;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\EventDispatcher\EventSubscriberInterface;

/**
 * Plugin is the composer plugin that registers the Yii composer installer.
 *
 * @author Tongle XU <xutongle@gmail.com>
 */
class Plugin extends \yii\composer\Plugin implements PluginInterface, EventSubscriberInterface
{
    /**
     * @inheritdoc
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        parent::activate($composer, $io);
        $installer = new Installer($io, $composer);
        $composer->getInstallationManager()->addInstaller($installer);
        $composer->getInstallationManager()->getInstaller('yii2-extension');//覆盖掉Yii2的

        $vendorDir = rtrim($composer->getConfig()->get('vendor-dir'), '/');

        $files = [
            $vendorDir . '/' . Installer::BACKEND_MODULE_FILE,
            $vendorDir . '/' . Installer::FRONTEND_MODULE_FILE,
            $vendorDir . '/' . Installer::MIGRATION_FILE,
            $vendorDir . '/' . Installer::TRANSLATE_FILE,
            $vendorDir . '/' . Installer::EVENT_FILE,
            $vendorDir . '/' . Installer::CRON_FILE
        ];
        $this->mkFile($files);
    }

    /**
     * 创建文件
     * @param array $files
     * @return void
     */
    public function mkFile($files)
    {
        foreach ($files as $file) {
            if (!is_file($file)) {
                @mkdir(dirname($file), 0777, true);
                file_put_contents($file, "<?php\n\nreturn [];\n");
            }
        }
    }

}

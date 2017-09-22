<p align="center">
    <a href="https://getcomposer.org/" target="_blank" rel="external">
        <img src="https://getcomposer.org/img/logo-composer-transparent3.png" height="178px">
    </a>
    <h1 align="center">YUNCMS Composer Installer</h1>
    <br>
</p>

This is the composer installer for [YUNCMS](http://www.yuncms.net) modules.
It implements a new composer package type named `yii2-extension`,
which should be used by all Yii 2 extensions if they are distributed as composer packages.

For license information check the [LICENSE](LICENSE.md)-file.

[![Latest Stable Version](https://poser.pugx.org/yuncms/yii2-composer/v/stable.png)](https://packagist.org/packages/yuncms/yii2-composer)
[![Total Downloads](https://poser.pugx.org/yuncms/yii2-composer/downloads.png)](https://packagist.org/packages/yuncms/yii2-composer)


Usage
-----

The Yii 2 Composer Installer is automatically installed with when installing the framework via Composer.

To use Yii 2 composer installer, simply set the package `type` to be `yii2-extension` in your `composer.json`,
like the following:

```json
{
    "type": "yii2-extension",
    "require": {
        "yiisoft/yii2": "~2.0.0"
    },
    ...
}
```

You may specify a bootstrapping class in the `extra` section. The `init()` method of the class will be executed each time
the Yii 2 application is responding to a request. For example,

```json
{
    "type": "yii2-extension",
    ...,
    "extra": {
        "yuncms": {
            "name": "admin",
            "i18n": {
                "class":"yii\\i18n\\PhpMessageSource",
                "basePath":"@yuncms/admin/message"
            },
            "migrationNamespace": "@yuncms/admin/migrations",
            "backend": {
                "class": "yuncms\\admin\\Module"
            },
            "frontend": {}
        }
    }
}
```

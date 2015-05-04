Selectel Storage Upload - Wodpress plugin
=========
This plugin allows you to automatically synchronize media files that are downloaded to the articles or just the library, to Selectel Storage.

----------

## Description
This plugin allows you to synchronize files that are uploaded from the media library Wordpress with [Selectel Storage](http://goo.gl/8Z0q8H) (or othet OpenStack Object Storage). Synchronization takes place either in an automatic mode (at upload time) or manually. Supported function to delete files from [Selectel Storage](http://goo.gl/8Z0q8H) when they are removed from the library.
This plugin allows you to securely store files, and save significant site traffic if you use a domain / subdomain with public container.

#####In Russian

Этот плагин позволяет синхронизировать файлы, загруженные из медиа-библиотеки Wordpress  в облачное хранилище [Selectel](http://goo.gl/8Z0q8H) (или любой другой OpenStack Object Storage). Синхронизация происходит либо в автоматическом режиме (на этапе загрузки), либо вручную. Поддерживается функция удаления файлов из облачного хранилища [Selectel](http://goo.gl/8Z0q8H), когда они удаляются из библиотеки.
Этот плагин позволяет безопасно хранить файлы, и значительно сэкономить трафик и деньги, затрачиваемые на хранение файлов, если использовать домен/поддомен и публичный контейнер.

----------

## Installation

1. Upload plugin directory to the `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to `Settings -> Selectel Upload` and set up the plugin

If the plugin has been downloaded from GitHub, after you must run `composer update` in the directory with the plugin.

#####In Russian:
1. Загрузить плагин в каталог `/wp-content/plugins/`
2. Активировать плагин через меню 'Плагины' в WordPress
3. Перейдите в раздел `Настройки -> Selectel Upload` и настроить плагин

Если плагин был загружен с GitHub, то после необходимо выполнить команду `composer update` в директории с плагином.

----------
## Frequently Asked Questions
* **Error 403 Forbidden**
    - Проверьте логин/пароль и указанный сервер авторизациии на корректность

* **Stream open failed for `https://auth.selcdn.ru:443/`**
    - Проблема с подключением к хранилищу.  Проверьте сетевое подлючение.

* **Impossible to upload a file**

    - Проблема с сохранением файла в облачном хранилище. Проверьте сетевое подлючение.

* **Do not have access to the file**

    - Проблема с чтением файла. Проверьте существует ли файл и права доступа к нему.

* **Invalid response from the authentication service.**

    - Возможны два варианта:
        1. Хостер блокирует доступ к серверу [Selectel](http://goo.gl/8Z0q8H) (по умолчанию: `auth.selcdn.ru`, порт `443`). Обратитесь к техподдержке хостера.
        2. Проблема на сервере [Selectel](http://goo.gl/8Z0q8H). Просто подождите, либо сообщите об ошиббке техподдержке.



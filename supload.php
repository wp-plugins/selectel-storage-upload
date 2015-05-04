<?php
/**
 * Plugin Name: Selectel Storage Upload
 * Plugin URI: http://wm-talk.net/supload-wordpress-plagin-dlya-zagruzki-na-selectel
 * Description: The plugin allows you to upload files from the library to Selectel Storage
 * Version: 1.3.9
 * Author: Mauhem
 * Author URI: http://wm-talk.net/
 * License: GNU GPLv2
 * Text Domain: selupload
 * Domain Path: /lang

 */
load_plugin_textdomain('selupload', false, dirname(plugin_basename(__FILE__)) . '/lang');

function selupload_incompatibile($msg)
{
    require_once ABSPATH . DIRECTORY_SEPARATOR . 'wp-admin' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'plugin.php';
    deactivate_plugins(__FILE__);
    wp_die($msg);
}

if (is_admin() && (!defined('DOING_AJAX') || !DOING_AJAX)) {
    if (version_compare(PHP_VERSION, '5.3.3', '<')) {
        selupload_incompatibile(
            __(
                'Plugin Selectel Cloud Uploader requires PHP 5.3.3 or higher. The plugin has now disabled itself.',
                'selupload'
            )
        );
    } elseif (!function_exists('curl_version')
        || !($curl = curl_version()) || empty($curl['version']) || empty($curl['features'])
        || version_compare($curl['version'], '7.16.2', '<')
    ) {
        selupload_incompatibile(
            __('Plugin Selectel Cloud Uploader requires cURL 7.16.2+. The plugin has now disabled itself.', 'selupload')
        );
    } elseif (!($curl['features'] & CURL_VERSION_SSL)) {
        selupload_incompatibile(
            __(
                'Plugin Selectel Cloud Uploader requires that cURL is compiled with OpenSSL. The plugin has now disabled itself.',
                'selupload'
            )
        );
    }
}
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'code.php';
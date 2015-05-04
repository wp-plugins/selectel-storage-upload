<?php

require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

use OpenStackStorage\Connection;

/**
 * Displays formatted message
 *
 * @param string $message Message string
 * @param bool $errormsg = false If the error message, then be true
 *
 * @return string
 */
function selupload_showMessage($message, $errormsg = false)
{
    if ($errormsg) {
        echo '<div id="message" class="error">';
    } else {
        echo '<div id="message" class="updated fade">';
    }

    echo "<p><strong>$message</strong></p></div>";
}

/**
 * Tests the connection to the server. Returns the response html. Ajax funtion.
 */
function selupload_testConnet()
{
    try {
        isset($_POST['login']) ? $login = $_POST['login'] : $login = get_option('selupload_username');
        isset($_POST['password']) ? $password = $_POST['password'] : $password = get_option('selupload_pass');
        isset($_POST['server']) ? $server = $_POST['server'] : $server = get_option('selupload_auth');
        isset($_POST['container']) ? $container = $_POST['container'] : $container = get_option('selupload_container');

        $connection = new Connection($login, $password, array('authurl' => 'https://' . $server . '/'), 15);
        $connection->getContainer($container);
        selupload_showMessage(__('Connection is successfully established. Save the settings.', 'selupload'));

        exit();
    } catch (Exception $e) {
        selupload_showMessage(__('Connection is not established.',
                'selupload') . ' : ' . $e->getMessage() . ($e->getCode() == 0 ? '' : ' - ' . $e->getCode()), true);
        exit();
    }
}

add_action('wp_ajax_selupload_testConnet', 'selupload_testConnet');

/**
 * It cuts a path relative to the uploads directory
 *
 * @param string $file Full url path. Example /var/www/example.com/wm-content/uploads/2015/05/simple.jpg
 *
 * @return string Short path. Example 2015/05/simple.jpg
 */
function selupload_getName($file)
{
    $dir = get_option('upload_path');
    $file = str_replace($dir, '', $file);
    $file = str_replace('\\', '/', $file);
    $file = str_replace(' ', '%20', $file);
    $file = ltrim($file, '/');

    return $file;
}

/**
 * Returns data as a string
 *
 * @param mixed $data
 *
 * @return string
 */
function selupload_dump($data)
{
    ob_start();
    print_r($data);
    $content = ob_get_contents();
    ob_end_clean();

    return $content;
}

/**
 * Upload files to the repository
 *
 * @param int $postID Id upload file
 *
 * @return bool
 */
function selupload_cloudUpload($postID)
{
    $file = get_attached_file($postID);
    if (get_option('selupload_debug') == 1) {
        $log = new Katzgrau\KLogger\Logger(plugin_dir_path(__FILE__) . '/logs', Psr\Log\LogLevel::DEBUG,
            array('prefix' => __FUNCTION__ . '_','extension' => 'log'));
        $log->info('Starts unload file');
        $log->info('File path: ' . $file);
    }
    if (selupload_checkForSync($file)) {
        try {
            $connection = new Connection(get_option('selupload_username'), get_option('selupload_pass'),
                array('authurl' => 'https://' . get_option('selupload_auth') . '/'), 15);
            if (get_option('selupload_debug') == 1 and isset($log)) {
                $log->debug("Connection dump:\n" . selupload_dump($connection));
            }
            $container = $connection->getContainer(get_option('selupload_container'));
            if (get_option('selupload_debug') == 1 and isset($log)) {
                $log->debug("Container dump\n" . selupload_dump($container));
            }
            if (is_readable($file) and ($container instanceof \OpenStackStorage\Container)) {
                if (((get_option('selupload_notoverewrite') == 1) and ($container->getObject(selupload_getName($file))->getSize() == sizeof($file))) == false) {
                    $fp = fopen($file, 'r');
                    $object = $container->createObject(selupload_getName($file));
                    $object->write($fp);
                    @fclose($fp);
                    //if (wp_attachment_is_image($postID) == false) {
                    $object = $container->getObject(selupload_getName($file));
                    if (get_option('selupload_debug') == 1 and isset($log)) {
                        $log->info(($object instanceof \OpenStackStorage\Object) ? 'Successfully uploaded' : 'File in storage not found');
                    }
                    if ((($object instanceof \OpenStackStorage\Object) == true) and ((get_option('selupload_delafter') == 1) and ((wp_attachment_is_image($postID) == false)))
                    ) {
                        if (get_option('selupload_debug') == 1 and isset($log)) {
                            $log->info('Deleting file: ' . $file);
                        }
                        unlink($file);
                    }
                    //}
                }
            }

            return true;
        } catch (Exception $e) {
            if (get_option('selupload_debug') == 1 and isset($log)) {
                $log->error(($e->getCode() != 0 ? $e->getCode() == 0 . ' :: ' : '') . $e->getMessage());
            }
            selupload_showMessage(($e->getCode() != 0 ? $e->getCode() == 0 . ' :: ' : '') . $e->getMessage());
        }
    } else {
        if (get_option('selupload_debug') == 1 and isset($log)) {
            $log->info('Skiped file. Reason: file mask');
        }

        return true;
    }

    return false;
}

/*
 * Based on $metadata uploads thumbnails in cloud storage
 * @param array $metadata
 * @return array It returns the $metadata array does not change for further processing
 */
function selupload_thumbUpload($metadata)
{
    if (get_option('selupload_debug') == 1) {
        $log = new Katzgrau\KLogger\Logger(plugin_dir_path(__FILE__) . '/logs', Psr\Log\LogLevel::DEBUG,
            array('prefix' => __FUNCTION__ . '_','extension' => 'log'));
            $log->debug("Metadata dump:\n" . selupload_dump($metadata));
    }
    if (isset($metadata['file'])) {
        try {
            $connection = new Connection(get_option('selupload_username'), get_option('selupload_pass'),
                array('authurl' => 'https://' . get_option('selupload_auth') . '/'), 15);
            if (get_option('selupload_debug') == 1 and isset($log)) {
                $log->debug("Connection dump:\n" . selupload_dump($connection));
            }
            $container = $connection->getContainer(get_option('selupload_container'));
            if (get_option('selupload_debug') == 1 and isset($log)) {
                $log->debug("Container dump:\n" . selupload_dump($container));
            }
            if ($container instanceof \OpenStackStorage\Container) {
                $upload_dir = wp_upload_dir();
                if (get_option('selupload_debug') == 1 and isset($log)) {
                    $log->info("Metadata file: " . $metadata['file']);
                }
                foreach ($metadata['sizes'] as $thumb) {
                    if (isset($thumb['file'])) {
                        $path = $upload_dir['path'] . DIRECTORY_SEPARATOR . $thumb['file'];
                        if (get_option('selupload_debug') == 1 and isset($log)) {
                            $log->info("Path to thumbnail: " . $path);
                            if (selupload_checkForSync($path)){
                                $log->info('File ' . $path. ' will be uploaded.');
                            }else{
                                $log->info('File ' . $path. ' does not fit the mask.');
                            }
                        }
                        if ((is_readable($path)) and (selupload_checkForSync($path))) {

                            if (((get_option('selupload_notoverewrite') == 1) and ($container->getObject(selupload_getName($path))->getSize() == sizeof($path))) == false) {

                                $fp = fopen($path, 'r');
                                $object = $container->createObject(selupload_getName($path));
                                $object->write($fp);
                                @fclose($fp);
                                $object = $container->getObject(selupload_getName($path));
                                if (get_option('selupload_debug') == 1 and isset($log)) {
                                    if($object instanceof \OpenStackStorage\Object) {
                                        $log->info("File " . $path . ' successfully uploaded');
                                    }else{
                                        $log->warning("File " . $path . ' not uploaded');
                                    }
                                }
                                if ((($object instanceof \OpenStackStorage\Object) == true) and (get_option('selupload_delafter') == 1)) {
                                    if (get_option('selupload_debug') == 1 and isset($log)) {
                                        $log->info("File " . $path . ' deleted');
                                    }
                                    unlink($path);

                                }
                            }
                        }
                    }
                }
                if (get_option('selupload_debug') == 1 and isset($log)) {
                    $log->info("Original file " . $upload_dir['basedir'] . DIRECTORY_SEPARATOR . $metadata['file'] . " getName - ".selupload_getName($upload_dir['basedir'] . DIRECTORY_SEPARATOR.$metadata['file']));
                }
                $object = $container->getObject(selupload_getName($upload_dir['basedir'] . DIRECTORY_SEPARATOR.$metadata['file']));
                if ((($object instanceof \OpenStackStorage\Object) == true) and (get_option('selupload_delafter') == 1)
                ) {
                    if (get_option('selupload_debug') == 1 and isset($log)) {
                        $log->info("File " . $upload_dir['basedir'] . DIRECTORY_SEPARATOR . $metadata['file'] . ' deleted');
                    }
                    unlink($upload_dir['basedir'] . DIRECTORY_SEPARATOR . $metadata['file']);
                }
            }
            return $metadata;
        } catch (Exception $e) {
            return $metadata;
            //selupload_showMessage($e->getCode() . ' :: ' . $e->getMessage());
        }
    }

    return $metadata;
}

/**
 * Checks directory for the presence of files. If files are missing returns true, otherwise null.
 * @param $dir
 * @return bool|null
 */
function selupload_isDirEmpty($dir)
{
    if (!is_readable($dir)) {
        return null;
    }

    return (count(scandir($dir)) == 2);
}

/**
 * @param string $pattern
 * @param int $flags = 0
 *
 * @return array|false
 */
function selupload_globRecursive($pattern, $flags = 0)
{
    $files = glob($pattern, $flags);
    foreach (glob(dirname($pattern) . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR | GLOB_NOSORT) as $dir) {
        $files = array_merge($files, selupload_globRecursive($dir . DIRECTORY_SEPARATOR . basename($pattern), $flags));
    }

    return $files;
}

/**
 * Returns an array list of files in a directory $dir
 * @param string $dir
 * @return array
 */
function selupload_getFilesArr($dir)
{
    get_option('selupload_filter') != '' ?
        $filter = trim(get_option('selupload_filter')) :
        $filter = '*';

    $dir = rtrim($dir, '/');

    return array_filter(selupload_globRecursive($dir . DIRECTORY_SEPARATOR . '{' . $filter . '}', GLOB_BRACE),
        'is_file');
}

/**
 * Faster search in an array with a large number of files
 * @param string $needle
 * @param array $haystack
 * @return bool
 */
function selupload_inArray($needle, $haystack)
{
    $flipped_haystack = array_flip($haystack);
    if (isset($flipped_haystack[$needle])) {
        return true;
    }

    return false;
}

/*
 * Checks if the file falls under the mask specified in the settings.
 * @param string @path Full path to file
 * @return bool
 */
function selupload_checkForSync($path)
{
    get_option('selupload_filter') != '' ?
        $mask = trim(get_option('selupload_filter')) :
        $mask = '*';
    if (get_option('selupload_debug') == 1) {
        $log = new Katzgrau\KLogger\Logger(plugin_dir_path(__FILE__) . '/logs', Psr\Log\LogLevel::DEBUG,
            array('prefix' => __FUNCTION__ . '_','extension' => 'log'));
        $log->info('File path: ' . $path);
        $log->info('Short path: ' . selupload_getName($path));
        $log->info('File mask: ' . $mask);
    }
    $dir = dirname($path);
    if (get_option('selupload_debug') == 1 and isset($log)) {
        $log->info('Directory: ' . $dir);
    }
    $files = glob($dir . DIRECTORY_SEPARATOR . '{' . $mask . '}', GLOB_BRACE);
    if (get_option('selupload_debug') == 1 and isset($log)) {
        $log->debug("Files dump (full name):\n" . selupload_dump($files));
    }
    $count = count($files) - 1;
    for ($i = 0; $i <= $count; $i++) {
        $files[$i] = selupload_getName($files[$i]);
    }
    if (get_option('selupload_debug') == 1 and isset($log)) {
        $log->debug("Files dump (full name):\n" . selupload_dump($files));
    }
    //$result = in_array(selupload_getName($path), $files,true);
    $result = selupload_inArray(selupload_getName($path), $files);
    if (get_option('selupload_debug') == 1 and isset($log)) {
        $result ? $log->info('Path found in files') : $log->info('Path not found in files');
    }

    return $result;

}

/* Removes escaping characters in path or array of paths
 * @param string|array $path
 * @return string|array
 */
function selupload_corURI($path)
{
    if (is_array($path)) {
        $count = count($path) - 1;
        for ($i = 0; $i <= $count; $i++) {
            $path[$i] = stripcslashes($path[$i]);
        }
    } elseif (is_string($path)) {
        $path = stripcslashes($path);
    } else {
        return false;
    }

    return $path;
}

/**
 * Function manual synch
 */
function selupload_allSynch()
{
    try {
        if (!empty($_POST['files'])) {
            if (get_option('selupload_debug') == 1) {
                $log = new Katzgrau\KLogger\Logger(plugin_dir_path(__FILE__) . '/logs', Psr\Log\LogLevel::DEBUG,
                    array('prefix' => __FUNCTION__ . '_','extension' => 'log'));
            }
            $files = selupload_corURI(explode('||', $_POST['files']));
            if (get_option('selupload_debug') == 1 and isset($log)) {
                $log->info("Manual synch files:\n" . selupload_dump($files));
            }
        }
        $error = '';
        if ((!empty($files)) and (!empty($_POST['count'])) and (count($files) >= 1)) {
            ob_start('ob_gzhandler');
            $connection = new Connection(get_option('selupload_username'), get_option('selupload_pass'),
                array('authurl' => 'https://' . get_option('selupload_auth') . '/'), 20);
            if (get_option('selupload_debug') == 1 and isset($log)) {
                $log->debug("Connection dump:\n" . selupload_dump($connection));
            }
            $container = $connection->getContainer(get_option('selupload_container'));
            if (get_option('selupload_debug') == 1 and isset($log)) {
                $log->debug("Container dump:\n" . selupload_dump($container));
            }
            if ($container instanceof \OpenStackStorage\Container) {
                $thisfile = $files[count($files) - 1];
                $filename = selupload_getName($files[count($files) - 1]);
                if (is_readable($thisfile)) {
                    $fp = fopen($thisfile, 'r');
                    $object = $container->createObject($filename);
                    $object->write($fp);
                    fclose($fp);
                    $object = $container->getObject($filename);
                    if (get_option('selupload_debug') == 1 and isset($log)) {
                        if($object instanceof \OpenStackStorage\Object) {
                            $log->info("File " . $filename . ' successfully uploaded');
                        }else{
                            $log->warning("File " . $filename . ' not uploaded');
                        }
                    }
                    if (($object instanceof \OpenStackStorage\Object) and get_option('selupload_delafter') == 1) {
                        if (get_option('selupload_debug') == 1 and isset($log)) {
                                $log->info("File " . $filename . ' deleted');
                        }
                        unlink($thisfile);
                    } elseif (($object instanceof \OpenStackStorage\Object) !== true) {
                        $error = __('Impossible to upload a file',
                                'selupload') . ': ' . $thisfile;
                    }
                } else {
                    $error = __('Do not have access to the file',
                            'selupload') . ': ' . $thisfile;
                }
                unset($files[count($files) - 1]);
                $progress = round(($_POST['count'] - count($files)) / $_POST['count'], 4) * 100;
                wp_send_json(array(
                    'files' => implode('||', $files),
                    'count' => $_POST['count'],
                    'progress' => $progress,
                    'error' => $error
                ));
            } else {
                $error = __('Unable to connect to the container',
                        'selupload') . ': ' . $_POST['files'][count($_POST['files']) - 1];
                wp_send_json(array(
                    'files' => $_POST['files'],
                    'count' => $_POST['count'],
                    'progress' => 'Error',
                    'error' => $error
                ));
            }
        } else {
            $error = __('Data empty',
                'selupload');
            wp_send_json(array(
                'files' => null,
                'count' => null,
                'progress' => 'Error',
                'error' => $error
            ));
        }
        exit();
    } catch (Exception $e) {
        $error = __('Impossible to upload a file',
                'selupload') . ': ' . $_POST['files'][count($_POST['files']) - 1];
        wp_send_json(array(
            'files' => !empty($_POST['files']) ? $_POST['files'] : null,
            'count' => !empty($_POST['count']) ? $_POST['count'] : null,
            'progress' => 'Error',
            'error' => $error . ' : ' . ($e->getCode() != 0 ? $e->getCode() . ' :: ' : '') . $e->getMessage()
        ));
        exit();
    }
}

add_action('wp_ajax_selupload_allsynch', 'selupload_allSynch');

/*
 * Create a page settings
 */
function selupload_settingsPage()
{
    ?>
    <div id="selupload_spinner" class="selupload_spinner" style="display:none;">
        <img id="img-spinner" src="<?php echo plugins_url() . '/' . dirname(
                plugin_basename(__FILE__)
            ); ?>/img/loading.gif" alt="Loading"/>
    </div>
    <div class="wrap" id="selupload_wrap">
        <div id="selupload_message" style="display: none"></div>
        <table>
            <tr>
                <td>
                    <h2><?php _e('Settings', 'selupload'); ?> Selectel Storage</h2>
                    <?php
                    // Default settings
                    if (get_option('upload_path') == 'wp-content' . DIRECTORY_SEPARATOR . 'uploads' || get_option('upload_path') == null
                    ) {
                        update_option('upload_path', WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'uploads');
                    }
                    if (get_option('selupload_auth') == null) {
                        update_option('selupload_auth', 'auth.selcdn.ru');
                    }
                    if (get_option('selupload_filter') == null) {
                        update_option('selupload_filter', '*');
                    }
                    ?>
                    <form method="post" action="options.php">
                        <?php settings_fields('selupload_settings'); ?>
                        <fieldset class="options">
                            <table class="form-table">
                                <tbody>
                                <tr>
                                    <td colspan="2"><?php _e(
                                            'Type the information for access to your container.',
                                            'selupload'
                                        );?> <?php _e('No account? <a href ="http://goo.gl/8Z0q8H">Sign up</a>',
                                            'selupload'); ?></td>
                                </tr>
                                <tr>
                                    <td><label for="selupload_username"><b><?php _e(
                                                    'Username',
                                                    'selupload'
                                                ); ?>:</b></label></td>
                                    <td>
                                        <input id="selupload_username" name="selupload_username" type="text"
                                               size="15" value="<?php echo esc_attr(
                                            get_option('selupload_username')
                                        ); ?>" class="regular-text code"/>
                                    </td>
                                </tr>
                                <tr>
                                    <td><label for="selupload_pass"><b><?php _e('Password', 'selupload'); ?>
                                                :</b></label></td>
                                    <td>
                                        <input id="selupload_pass" name="selupload_pass" type="password"
                                               size="15"
                                               value="<?php echo esc_attr(get_option('selupload_pass')); ?>"
                                               class="regular-text code"/>
                                    </td>
                                </tr>
                                <tr>
                                    <td><label for="selupload_container"><b><?php _e(
                                                    'Container',
                                                    'selupload'
                                                ); ?>:</b></label></td>
                                    <td>
                                        <input id="selupload_container" name="selupload_container"
                                               type="text" size="15" value="<?php echo esc_attr(
                                            get_option('selupload_container')
                                        ); ?>" class="regular-text code"/>
                                        <input type="button" name="test" id="submit" class="button button-primary"
                                               value="<?php _e('Check the connection', 'selupload'); ?>"
                                               onclick="selupload_testConnet()"/>
                                    </td>
                                </tr>
                                <tr>
                                    <td><label for="upload_path"><b><?php _e('Local path', 'selupload'); ?>
                                                :</b></label></td>
                                    <td>
                                        <input id="upload_path" name="upload_path" type="text"
                                               size="15"
                                               value="<?php echo esc_attr(get_option('upload_path')); ?>"
                                               class="regular-text code"/>

                                        <p class="description"><?php _e(
                                                'Local path to the uploaded files. By default',
                                                'selupload'
                                            ); ?>: <code>wp-content/uploads</code>
                                        </p><?php _e('Setting duplicates of the same name from the mediafiles settings. Changing one, you change and other',
                                            'selupload'); ?>.
                                    </td>
                                </tr>
                                <tr>
                                    <td><label for="upload_url_path"><b><?php _e(
                                                    'Full URL-path to files',
                                                    'selupload'
                                                ); ?>:</b></label></td>
                                    <td>
                                        <input id="upload_url_path" name="upload_url_path" type="text"
                                               size="15" value="<?php echo esc_attr(
                                            get_option('upload_url_path')
                                        ); ?>" class="regular-text code"/>

                                        <p class="description">
                                            <?php _e(
                                                'Enter the domain or subdomain if store files only in the Selectel Storage',
                                                'selupload'
                                            ); ?>
                                            <code>(http://uploads.example.com)</code>, <?php _e(
                                                'or full url path, if only used synchronization',
                                                'selupload'
                                            ); ?>
                                            <code>(http://example.com/wp-content/uploads)</code>.</p>
                                        <?php _e('Setting duplicates of the same name from the mediafiles settings. Changing one, you change and other',
                                            'selupload'); ?>.
                                    </td>
                                </tr>
                                <tr>
                                    <td><label for="selupload_auth"><b><?php _e(
                                                    'Authorization server',
                                                    'selupload'
                                                ); ?>:</b></label></td>
                                    <td>
                                        <input id="selupload_auth" name="selupload_auth" type="text"
                                               size="15"
                                               value="<?php echo esc_attr(get_option('selupload_auth')); ?>"
                                               class="regular-text code"/>

                                        <p class="description"><?php _e('By default', 'selupload'); ?>: <code>auth.selcdn.ru</code>
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <td><label for="selupload_filter"><b><?php _e(
                                                    'Mask files',
                                                    'selupload'
                                                ); ?>:</b></label></td>
                                    <td>
                                        <input id="selupload_filter" name="selupload_filter" type="text"
                                               size="15"
                                               value="<?php echo esc_attr(get_option('selupload_filter')); ?>"
                                               class="regular-text code"/>

                                        <p class="description"><?php _e('By default', 'selupload'); ?>:
                                            <code>*</code> <?php _e('for all files, 2 or more values separated by a comma (for example: ',
                                                'selupload'); ?> <code>*.jpg,*.png,n?ame.png</code>)
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="2">
                                        <p><b><?php _e('Synchronization settings', 'selupload'); ?>:</b></p>

                                        <p>
                                            <input id="onlystorage" type="checkbox" name="selupload_delafter"
                                                   value="1" <?php checked(get_option('selupload_delafter'), 1); ?> />
                                            <label for="onlystorage"><?php _e(
                                                    'Store files only in the Selectel Storage',
                                                    'selupload'
                                                ); ?></label>
                                            <code>(<?php _e(
                                                    'to attach a domain / subdomain to store and specify the settings',
                                                    'selupload'
                                                ); ?>).</code></p>

                                        <p class="description"><?php _e('The file will be deleted from the hosting after a successful download. It will only copy in the Selectel Storage',
                                                'selupload'); ?>.</p>

                                        <p>
                                            <input id="selupload_del" type="checkbox" name="selupload_del"
                                                   value="1" <?php checked(
                                                get_option('selupload_del'),
                                                1
                                            ); ?> />
                                            <label for="selupload_del"><?php _e(
                                                    'Delete files from the Selectel Storage if they are removed from the library',
                                                    'selupload'
                                                ); ?>.</label></p>

                                        <p>
                                            <input id="selupload_notoverewrite" type="checkbox"
                                                   name="selupload_notoverewrite"
                                                   value="1" <?php checked(
                                                get_option('selupload_notoverewrite'),
                                                1
                                            ); ?> />
                                            <label
                                                for="selupload_notoverewrite"><?php _e('Do not overwrite the file if it already exists in the storage',
                                                    'selupload'); ?>.</label></p>

                                        <p>
                                            <input id="selupload_debug" type="checkbox"
                                                   name="selupload_debug"
                                                   value="1" <?php checked(
                                                get_option('selupload_debug'),
                                                1
                                            ); ?> />
                                            <label
                                                for="selupload_debug"><?php _e('Enable debug mode. Do not enable unless you know what it is.',
                                                    'selupload'); ?></label></p>
                                    </td>
                                </tr>
                                </tbody>
                            </table>
                            <input type="hidden" name="action" value="update"/>
                            <?php submit_button(); ?>
                        </fieldset>
                    </form>
                    <div id="selupload_progressBar">
                        <div></div>
                    </div>
                    <div id="selupload_synchtext" style="display: none" class="error"></div>
                    <script type="text/javascript" language="JavaScript">
                        <?php
                            $upload_dir = wp_upload_dir();
                            $files = selupload_getFilesArr($upload_dir['basedir']);
                            echo 'var files_arr = '.json_encode(implode('||',$files)).';'."\n".'var files_count = '.count($files).';'."\n";
                        ?>
                    </script>
                    <form method="post">
                        <input type="button" name="archive" id="submit" class="synch button button-primary"
                               value="<?php _e('Full synchronization', 'selupload'); ?>"
                               onclick="selupload_mansynch(files_arr,files_count)"/>
                        <input type="button" name="archive" id="submit" class="synch button button-primary"
                               value="<?php _e('Show the list of files to synchronize', 'selupload'); ?>"
                               onclick="selupload_showfilelist(files_arr,<?php echo strlen($upload_dir['basedir']); ?>)"/>

                        <div id="selupload_filelist">
                            <div align="right"><a class="noun" href="javascript://"
                                                  onclick="jQuery('#selupload_filelist').slideToggle('slow');"><b>[Закрыть]</b></a>
                            </div>
                            <div id="selupload_filelistdiv"></div>

                        </div>
                        <p class="description"><?php _e('!WARNING! Full synchronization is not designed to synchronize a huge number of files. Firstly, it takes a lot of time, and secondly it will lead to an increase in the load on the server that might cause authorization of the host. With a large number of files is recommended that you synchronize files manually via FTP, and then use this plugin to sync.',
                                'selupload'); ?></p>
                    </form>

                </td>
                <td style="vertical-align: top; text-align: center; padding-top: 10em">
                    <p style="text-align: justify; text-indent: 3em;"><?php _e(
                            'You can always help the development of plug-in and contribute to the emergence of new functionality.',
                            'selupload'
                        ); ?>
                    </p>

                    <p style="text-align: justify; text-indent: 3em;"><?php _e(
                            'If you have any ideas or suggestions',
                            'selupload'
                        ); ?>: <a href="mailto:me@wm-talk.net"><?php _e(
                                'contact the author',
                                'selupload'
                            ); ?></a>.
                    </p>

                    <p style="text-align: justify; text-indent: 3em;"><?php _e(
                            'You can always thank the author of financially.',
                            'selupload'
                        ); ?>
                    </p>

                    <p><strong>PayPal</strong></p>

                    <form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_top">
                        <input type="hidden" name="cmd" value="_s-xclick">
                        <input type="hidden" name="hosted_button_id" value="2PSKHUBC5Z986">
                        <input type="image" src="<?php echo plugins_url() . '/' . dirname(
                                plugin_basename(__FILE__)
                            ); ?>/img/btn_donateCC_LG.gif" border="0" name="submit"
                               alt="PayPal - The safer, easier way to pay online!">
                        <img alt="" border="0" src="<?php echo plugins_url() . '/' . dirname(
                                plugin_basename(__FILE__)
                            ); ?>/img/pixel.gif" width="1" height="1">
                    </form>

                    <p><strong><?php _e('Yandex.Money', 'selupload'); ?></strong><br/>
                        410011704884638
                        <br/></p>
                    <strong>Webmoney</strong><br/>
                    WMZ - Z149988560659<br/>
                    WMR - R255107795656
                </td>
            </tr>
        </table>
    </div>
<?php
}

/*
 * The function creates the admin menu
 */
function selupload_createMenu()
{
    add_options_page(
        'Selectel Upload',
        'Selectel Upload',
        'manage_options',
        __FILE__,
        'selupload_settingsPage'
    );
    add_action('admin_init', 'selupload_regsettings');
}

/*
 * Add admin menu
 */
add_action('admin_menu', 'selupload_createMenu');
/*
 * Deletes the file from the clouds before removing from the server
 * @param string $file Full path ro file
 * @return string
 */
function selupload_cloudDelete($file)
{
    try {
        if (get_option('selupload_debug') == 1) {
            $log = new Katzgrau\KLogger\Logger(plugin_dir_path(__FILE__) . '/logs', Psr\Log\LogLevel::DEBUG,
                array('prefix' => __FUNCTION__ . '_','extension' => 'log'));
        }
        $connection = new Connection(get_option('selupload_username'), get_option('selupload_pass'),
            array('authurl' => 'https://' . get_option('selupload_auth') . '/'));
        if (get_option('selupload_debug') == 1 and isset($log)) {
            $log->debug("Connection dump\n" . selupload_dump($connection));
        }
        $container = $connection->getContainer(get_option('selupload_container'));
        if (get_option('selupload_debug') == 1 and isset($log)) {
            $log->debug("Container dump\n" . selupload_dump($container));
        }
        $container->deleteObject(selupload_getName($file));
        unlink($file);
        if (get_option('selupload_debug') == 1 and isset($log)) {
            $log->info("Delete file:\n" . $file);
        }
        return $file;
    } catch (Exception $e) {
        return $file;
    }

}

if (get_option('selupload_del') == 1) {
    add_filter('wp_delete_file', 'selupload_cloudDelete', 10, 1);
}

/*
 * Function registration js files
 */
function selupload_scripts()
{
    wp_enqueue_script('selupload_js', plugins_url('/js/script.js', __FILE__), array('jquery'), '1.4.0', true);
}

/*
 * Function registration css files
 */
function selupload_stylesheetToAdmin()
{
    wp_enqueue_style('selupload-progress', plugins_url('css/admin.css', __FILE__));
}

/*
 * Add filter to thumbnail
 */
add_filter('wp_generate_attachment_metadata', 'selupload_thumbUpload', 10, 1);
/*
 * Add filter to attachment
 */
add_action('add_attachment', 'selupload_cloudUpload', 10, 1);
/*
 * Registration css files
 */
add_action('admin_enqueue_scripts', 'selupload_stylesheetToAdmin');
/*
 * Registration js files
 */
add_action('admin_enqueue_scripts', 'selupload_scripts');

/*
 * Registration settings
 */
function selupload_regsettings()
{
    register_setting('selupload_settings', 'selupload_auth');
    register_setting('selupload_settings', 'upload_path');
    register_setting('selupload_settings', 'selupload_container');
    register_setting('selupload_settings', 'selupload_pass');
    register_setting('selupload_settings', 'selupload_username');
    register_setting('selupload_settings', 'selupload_delafter');
    register_setting('selupload_settings', 'upload_url_path');
    register_setting('selupload_settings', 'selupload_del');
    register_setting('selupload_settings', 'selupload_filter');
    register_setting('selupload_settings', 'selupload_notoverewrite');
    register_setting('selupload_settings', 'selupload_debug');
}
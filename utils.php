<?php

/**
 * HELPERS
 */
function affilio_log_me($message)
{
    if (WP_DEBUG === true) {
        if (is_array($message) || is_object($message)) {
            error_log(print_r($message, true));
        } else {
            error_log($message);
        }
    }
}

function affilio_wp_encrypt($stringToHandle = "", $encryptDecrypt = 'e')
{
    $output = null;
    // Set secret keys
    $secret_key = 'nf8gf8a^3*s';
    $secret_iv = 's&&9"da4%:@';
    $key = hash('sha256', $secret_key);
    $iv = substr(hash('sha256', $secret_iv), 0, 16);
    // Check whether encryption or decryption
    if ($encryptDecrypt == 'e') {
        // We are encrypting
        $output = base64_encode(openssl_encrypt($stringToHandle, "AES-256-CBC", $key, 0, $iv));
    } else if ($encryptDecrypt == 'd') {
        // We are decrypting
        $output = openssl_decrypt(base64_decode($stringToHandle), "AES-256-CBC", $key, 0, $iv);
    }
    // Return the final value
    return $output;
}

function affilio_set_option($name, $value)
{
    $isAdded = add_option($name, $value);
    if (!$isAdded) {
        $isAdded = update_option($name, $value);
    }
}

function affilio_admin_notice($type = 'success', $msg)
{
    if ($type !== 'success') {
        $class = 'notice notice-error is-dismissible';
        $message = __($msg, 'affilio');
    } else {
        $class = 'notice notice-success is-dismissible';
        $message = __($msg, 'affilio');
    }

    printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
}

function affilio_is_plugin_active($plugin_name)
{

    $active_plugins = (array) get_option('active_plugins', array());

    if (is_multisite()) {
        $active_plugins = array_merge($active_plugins, array_keys(get_site_option('active_sitewide_plugins', array())));
    }

    $plugin_filenames = array();

    foreach ($active_plugins as $plugin) {

        if (false !== strpos($plugin, '/')) {

            // normal plugin name (plugin-dir/plugin-filename.php)
            list(, $filename) = explode('/', $plugin);
        } else {

            // no directory, just plugin file
            $filename = $plugin;
        }

        $plugin_filenames[] = $filename;
    }

    return in_array($plugin_name, $plugin_filenames);
}

function affilio_get_url($url)
{
    $options = get_option('affilio_option_name');
    return isset($options["is_stage"]) ? esc_url_raw(AFFILIO_BASE_STAGE_API . $url) : esc_url_raw(AFFILIO_BASE_API . $url);
}

function affilio_set_log($body, $eventType, $type = "debug")
{
    $et = null;
    if($eventType === "PLUGIN_WP_SYNC_CATEGORY"){
        $et = $eventType;
    }
    if($eventType === "PLUGIN_WP_SYNC_CATEGORY_DATA"){
        $et = $eventType;
    }
    if($eventType === "PLUGIN_WP_SYNC_PRODUCT"){
        $et = $eventType;
    }
    if($eventType === "PLUGIN_WP_SYNC_PRODUCT_DATA"){
        $et = $eventType;
    }
    if($eventType === "PLUGIN_WP_SYNC_ORDER"){
        $et = $eventType;
    }
    if($eventType === "PLUGIN_WP_SYNC_ORDER_DATA"){
        $et = $eventType;
    }
    if($eventType === "PLUGIN_WP_AUTH"){
        $et = $eventType;
    }
    if(!$et) return;

    $affilio_options = get_option('affilio_option_name');
    $webId = $affilio_options['webstore']; // username

    // affilio_log_me($et);

    $mainBody = [
        "type" => $type,
        "eventType" => $et,
        "data" => $body,
        "web_store_id" => $webId
    ];

    $params = array(
        'body'    => json_encode($mainBody),
        'headers' => array(
            'Content-Type' => 'application/json;charset=' . get_bloginfo('charset'),
            'tkauth' => '6160316f6253298915239bee4b84211e'
        ),
    );

    $response = wp_remote_post(AFFILIO_LOG_API, $params);
}

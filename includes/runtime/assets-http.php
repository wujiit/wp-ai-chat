<?php
if (!defined('ABSPATH')) {
    exit;
}

function deepseek_get_asset_version($relative_path = '') {
    $fallback = defined('DEEPSEEK_PLUGIN_VERSION') ? DEEPSEEK_PLUGIN_VERSION : '1.0.0';
    $relative_path = ltrim((string) $relative_path, '/');

    if ($relative_path === '' || !defined('DEEPSEEK_PLUGIN_DIR')) {
        return $fallback;
    }

    $file_path = DEEPSEEK_PLUGIN_DIR . $relative_path;
    return file_exists($file_path) ? (string) filemtime($file_path) : $fallback;
}

function deepseek_get_http_timeout($context = 'default', $default = 30) {
    $timeout = max(5, min(120, intval($default)));
    return (int) apply_filters('deepseek_http_timeout', $timeout, sanitize_key((string) $context));
}

function deepseek_get_http_connect_timeout($context = 'default', $default = 10) {
    $timeout = max(3, min(30, intval($default)));
    return (int) apply_filters('deepseek_http_connect_timeout', $timeout, sanitize_key((string) $context));
}

function deepseek_apply_curl_timeouts($ch, $context = 'default', $timeout = 30, $connect_timeout = 10) {
    if (!is_resource($ch) && !(is_object($ch) && get_class($ch) === 'CurlHandle')) {
        return;
    }

    curl_setopt($ch, CURLOPT_TIMEOUT, deepseek_get_http_timeout($context, $timeout));
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, deepseek_get_http_connect_timeout($context, $connect_timeout));
}

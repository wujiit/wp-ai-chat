<?php
if (!defined('ABSPATH')) {
    exit;
}

function deepseek_get_request_device_id($allow_ip_fallback = true) {
    $device_id = '';

    if (isset($_SERVER['HTTP_X_DEVICE_ID'])) {
        $device_id = sanitize_text_field(wp_unslash($_SERVER['HTTP_X_DEVICE_ID']));
    } elseif ($allow_ip_fallback && isset($_SERVER['REMOTE_ADDR'])) {
        $device_id = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
    }

    if ($device_id === '') {
        return '';
    }

    return substr($device_id, 0, 100);
}

function deepseek_get_request_ip_hash() {
    if (!isset($_SERVER['REMOTE_ADDR'])) {
        return '';
    }

    $ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
    if ($ip === '') {
        return '';
    }

    return md5(substr($ip, 0, 64));
}

function deepseek_get_guest_session_id() {
    $device_hash = deepseek_get_request_device_hash();
    if ($device_hash === '') {
        return '';
    }

    return 'guest_' . $device_hash;
}

function deepseek_get_guest_conversation_owner_transient_key($conversation_id) {
    return 'deepseek_guest_conv_owner_' . absint($conversation_id);
}

function deepseek_get_guest_conversation_owner($conversation_id) {
    $conversation_id = absint($conversation_id);
    if ($conversation_id <= 0) {
        return '';
    }

    return (string) get_transient(deepseek_get_guest_conversation_owner_transient_key($conversation_id));
}

function deepseek_set_guest_conversation_owner($conversation_id, $device_hash = '') {
    $conversation_id = absint($conversation_id);
    if ($conversation_id <= 0) {
        return '';
    }

    if ($device_hash === '') {
        $device_hash = deepseek_get_request_device_hash();
    }

    if ($device_hash === '') {
        return '';
    }

    set_transient(
        deepseek_get_guest_conversation_owner_transient_key($conversation_id),
        $device_hash,
        30 * DAY_IN_SECONDS
    );

    return $device_hash;
}

function deepseek_clear_guest_conversation_owner($conversation_id) {
    $conversation_id = absint($conversation_id);
    if ($conversation_id <= 0) {
        return;
    }

    delete_transient(deepseek_get_guest_conversation_owner_transient_key($conversation_id));
}

function deepseek_guest_can_access_conversation($conversation_id, $device_hash = '') {
    $conversation_id = absint($conversation_id);
    if ($conversation_id <= 0) {
        return false;
    }

    if ($device_hash === '') {
        $device_hash = deepseek_get_request_device_hash();
    }

    if ($device_hash === '') {
        return false;
    }

    $stored_owner = deepseek_get_guest_conversation_owner($conversation_id);
    if ($stored_owner === '') {
        return false;
    }

    if (hash_equals($stored_owner, $device_hash)) {
        return true;
    }

    $legacy_device_id = deepseek_get_request_device_id(true);
    if ($legacy_device_id !== '' && hash_equals($stored_owner, $legacy_device_id)) {
        deepseek_set_guest_conversation_owner($conversation_id, $device_hash);
        return true;
    }

    return false;
}

function deepseek_get_guest_usage_transient_key($action, $scope = 'device') {
    $action = sanitize_key((string) $action);
    $suffix = date('Ymd');

    if ($scope === 'ip') {
        return 'wpai_guest_' . $action . '_ip_' . deepseek_get_request_ip_hash() . '_' . $suffix;
    }

    return 'wpai_guest_' . $action . '_' . deepseek_get_request_device_hash() . '_' . $suffix;
}

function deepseek_current_user_can_access_chat_row($log_row) {
    if (!$log_row || !isset($log_row->conversation_id, $log_row->user_id)) {
        return false;
    }

    if (current_user_can('manage_options')) {
        return true;
    }

    $current_user_id = get_current_user_id();
    if ($current_user_id > 0) {
        return intval($log_row->user_id) === $current_user_id;
    }

    return intval($log_row->user_id) === 0 && deepseek_guest_can_access_conversation((int) $log_row->conversation_id);
}

function deepseek_current_user_can_access_chat_conversation($conversation_id) {
    global $wpdb;

    $conversation_id = absint($conversation_id);
    if ($conversation_id <= 0) {
        return false;
    }

    if (current_user_can('manage_options')) {
        return true;
    }

    $current_user_id = get_current_user_id();
    $table_name = deepseek_get_chat_logs_table_name();

    if ($current_user_id > 0) {
        $count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE conversation_id = %d AND user_id = %d",
            $conversation_id,
            $current_user_id
        ));

        return $count > 0;
    }

    return deepseek_guest_can_access_conversation($conversation_id);
}

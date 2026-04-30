<?php
if (!defined('ABSPATH')) {
    exit;
}

function deepseek_rest_request_has_valid_wp_nonce(WP_REST_Request $request) {
    $nonce = $request->get_header('x-wp-nonce');
    if (empty($nonce)) {
        $nonce = $request->get_header('X-WP-Nonce');
    }
    return !empty($nonce) && wp_verify_nonce($nonce, 'wp_rest');
}

function deepseek_send_message_permission(WP_REST_Request $request) {
    if (!deepseek_rest_request_has_valid_wp_nonce($request)) {
        return new WP_Error(
            'rest_forbidden',
            '请求验证失败',
            ['status' => 403]
        );
    }

    if (!is_user_logged_in()) {
        $guest_chat_limit = intval(deepseek_get_setting('deepseek_guest_chat_limit', 5));
        if ($guest_chat_limit <= 0) {
            return new WP_Error(
                'rest_forbidden',
                '游客功能已关闭，请先登录后再使用对话功能',
                ['status' => 401]
            );
        }
    }

    return true;
}

add_action('rest_api_init', function () {
    register_rest_route('deepseek/v1', '/send-message', array(
        'methods' => 'POST',
        'callback' => 'deepseek_send_message_rest',
        'permission_callback' => 'deepseek_send_message_permission',
    ));
});

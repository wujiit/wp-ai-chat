<?php
if (!defined('ABSPATH')) {
    exit;
}

function deepseek_prepare_chat_request_files($file_ids, $interface, $model) {
    if (empty($file_ids) || !is_array($file_ids)) {
        return array();
    }

    $prepared = array();
    foreach ($file_ids as $file_info) {
        if (!is_array($file_info)) {
            continue;
        }

        $record_id = isset($file_info['record_id']) ? absint($file_info['record_id']) : 0;
        if ($record_id > 0) {
            $record = deepseek_get_file_record($record_id);
            if (!$record || !deepseek_current_actor_can_access_file_record($record)) {
                return new WP_Error('deepseek_file_forbidden', '无权使用该文件', array('status' => 403));
            }

            if (!deepseek_file_record_is_compatible_with_chat_model($record, $interface, $model)) {
                return new WP_Error('deepseek_file_incompatible', '该文件不适用于当前接口或模型', array('status' => 400));
            }

            $payload = deepseek_normalize_file_record_for_chat($record);
            if ($payload) {
                $prepared[] = $payload;
            }
            continue;
        }

        $legacy_file_id = isset($file_info['file_id']) ? sanitize_text_field($file_info['file_id']) : '';
        if ($legacy_file_id === '') {
            continue;
        }

        $legacy_payload = array(
            'file_id' => $legacy_file_id,
            'filename' => isset($file_info['filename']) ? sanitize_file_name($file_info['filename']) : '',
            'interface' => isset($file_info['interface']) ? sanitize_key($file_info['interface']) : sanitize_key($interface),
        );

        if (!empty($file_info['image_url'])) {
            $legacy_payload['image_url'] = esc_url_raw($file_info['image_url']);
        }

        $prepared[] = $legacy_payload;
    }

    return $prepared;
}

function deepseek_chat_rest_record_usage($started_at, $args = array()) {
    if (!function_exists('deepseek_record_usage_event')) {
        return 0;
    }

    $message_text = isset($args['message']) ? (string) $args['message'] : '';
    $response_text = isset($args['response']) ? (string) $args['response'] : '';

    if (!array_key_exists('message_chars', $args)) {
        $args['message_chars'] = function_exists('deepseek_usage_strlen') ? deepseek_usage_strlen($message_text) : strlen($message_text);
    }

    if (!array_key_exists('response_chars', $args)) {
        $args['response_chars'] = function_exists('deepseek_usage_strlen') ? deepseek_usage_strlen($response_text) : strlen($response_text);
    }

    if (!array_key_exists('latency_ms', $args)) {
        $args['latency_ms'] = function_exists('deepseek_usage_elapsed_ms')
            ? deepseek_usage_elapsed_ms($started_at)
            : max(0, (int) round((microtime(true) - (float) $started_at) * 1000));
    }

    return deepseek_record_usage_event($args);
}

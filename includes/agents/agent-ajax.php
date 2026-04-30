<?php
if (!defined('ABSPATH')) {
    exit;
}

// 删除智能体对话记录
function deepseek_delete_agent_log() {
    check_ajax_referer('delete_agent_log_nonce', 'nonce');

    // 检查是否通过POST传递了必要参数
    if (!isset($_POST['user_id']) || !isset($_POST['app_id'])) {
        wp_send_json_error(['message' => '缺少必要参数']);
        return;
    }

    $user_id = intval(wp_unslash($_POST['user_id']));
    $app_id = sanitize_text_field(wp_unslash($_POST['app_id']));

    // 验证管理员权限
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => '无权删除记录']);
        return;
    }

    // 执行删除操作
    $deleted = deepseek_delete_agent_conversation($user_id, $app_id);

    // 检查删除结果
    global $wpdb;
    if ($wpdb->last_error) {
        error_log("删除对话记录失败: " . $wpdb->last_error);
        wp_send_json_error(['message' => '删除失败: ' . $wpdb->last_error]);
    } elseif ($deleted === 0) {
        wp_send_json_error(['message' => '没有找到匹配的记录']);
    } else {
        wp_send_json_success(['message' => '记录已删除']);
    }
}
add_action('wp_ajax_deepseek_delete_agent_log', 'deepseek_delete_agent_log');

// 前台清除智能体对话记录
function deepseek_clear_agent_conversation() {
    $nonce = deepseek_agent_get_rest_nonce_from_request();
    if (!wp_verify_nonce($nonce, 'wp_rest')) {
        wp_send_json(['success' => false, 'message' => '验证请求失败']);
        return;
    }

    $app_id = isset($_POST['app_id']) ? sanitize_text_field(wp_unslash($_POST['app_id'])) : '';

    if (empty($app_id)) {
        wp_send_json(['success' => false, 'message' => '缺少智能体应用 ID']);
        return;
    }

    $deleted = deepseek_clear_current_agent_conversation($app_id);

    global $wpdb;
    if ($wpdb->last_error) {
        error_log("清除对话记录失败: " . $wpdb->last_error);
        wp_send_json(['success' => false, 'message' => '清除失败: 数据库错误']);
    } elseif ($deleted === false) {
        wp_send_json(['success' => false, 'message' => '清除失败: 操作无效']);
    } else {
        wp_send_json(['success' => true, 'message' => '对话记录已清除']);
    }
}
add_action('wp_ajax_deepseek_clear_agent_conversation', 'deepseek_clear_agent_conversation');

// 处理智能体文件上传
add_action('wp_ajax_deepseek_upload_agent_file', 'deepseek_upload_agent_file');
function deepseek_upload_agent_file() {
    if (!is_user_logged_in() || !current_user_can('upload_files')) {
        wp_send_json_error(['message' => '请登录后上传文件']);
    }

    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'agent_file_upload_action')) {
        wp_send_json_error(['message' => '验证请求失败']);
    }

    if (!isset($_FILES['file']) || $_FILES['file']['error'] == UPLOAD_ERR_NO_FILE) {
        wp_send_json_error(['message' => '未选择文件']);
    }

    $file = $_FILES['file'];
    $allowed_types = deepseek_get_csv_setting_list('agent_file_formats', 'pdf'); // 支持的文件格式
    $max_size = intval(deepseek_get_setting('agent_file_max_size', 10)) * 1024 * 1024; // 最大文件大小（MB转换为字节）

    $file_type = pathinfo($file['name'], PATHINFO_EXTENSION);
    if (!in_array(strtolower($file_type), array_map('strtolower', $allowed_types))) {
        wp_send_json_error(['message' => '不支持的文件格式', 'allowed_types' => implode(', ', $allowed_types)]);
    }

    if ($file['size'] > $max_size) {
        wp_send_json_error(['message' => '文件大小超过限制 (' . ($max_size / 1024 / 1024) . 'MB)']);
    }

    $upload_overrides = array('test_form' => false);
    $uploaded_file = wp_handle_upload($file, $upload_overrides);

    if (isset($uploaded_file['error'])) {
        wp_send_json_error(['message' => '文件上传失败: ' . $uploaded_file['error']]);
    }

    $attachment = array(
        'guid' => $uploaded_file['url'],
        'post_mime_type' => $uploaded_file['type'],
        'post_title' => sanitize_file_name($file['name']),
        'post_content' => '',
        'post_status' => 'inherit'
    );

    $attachment_id = wp_insert_attachment($attachment, $uploaded_file['file']);
    if (is_wp_error($attachment_id)) {
        wp_send_json_error(['message' => '保存文件到媒体库失败']);
    }

    require_once ABSPATH . 'wp-admin/includes/image.php';
    $attachment_metadata = wp_generate_attachment_metadata($attachment_id, $uploaded_file['file']);
    if (!empty($attachment_metadata)) {
        wp_update_attachment_metadata($attachment_id, $attachment_metadata);
    }

    $record_id = 0;
    if (function_exists('deepseek_record_file_upload')) {
        $record_id = deepseek_record_file_upload(array(
            'user_id' => get_current_user_id(),
            'record_source' => 'agent',
            'interface_key' => 'agent',
            'provider_name' => 'local',
            'storage_engine' => 'wp_media',
            'purpose' => 'agent_context',
            'attachment_id' => $attachment_id,
            'original_filename' => $file['name'],
            'mime_type' => isset($uploaded_file['type']) ? $uploaded_file['type'] : '',
            'file_ext' => $file_type,
            'file_size' => isset($file['size']) ? intval($file['size']) : 0,
            'local_url' => $uploaded_file['url'],
            'meta' => array(
                'upload_path' => $uploaded_file['file'],
            ),
        ));
        deepseek_mark_attachment_file_record($attachment_id, $record_id, 'agent');
    }

    wp_send_json_success([
        'file_url' => $uploaded_file['url'],
        'file_name' => $file['name'],
        'suffix_type' => $file_type,
        'record_id' => $record_id
    ]);
}

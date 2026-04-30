<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!defined('DEEPSEEK_CONVERSATION_TOOLS_SCHEMA_VERSION')) {
    define('DEEPSEEK_CONVERSATION_TOOLS_SCHEMA_VERSION', '1.0.0');
}

function deepseek_conversation_meta_table() {
    global $wpdb;
    return $wpdb->prefix . 'deepseek_conversation_meta';
}

function deepseek_conversation_ensure_schema() {
    global $wpdb;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $table_name = deepseek_conversation_meta_table();
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table_name} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        conversation_id bigint(20) unsigned NOT NULL DEFAULT 0,
        user_id bigint(20) unsigned NOT NULL DEFAULT 0,
        guest_device_hash varchar(64) NOT NULL DEFAULT '',
        title varchar(191) NOT NULL DEFAULT '',
        is_pinned tinyint(1) unsigned NOT NULL DEFAULT 0,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY conversation_actor (conversation_id, user_id, guest_device_hash),
        KEY user_pinned_updated (user_id, is_pinned, updated_at),
        KEY guest_pinned_updated (guest_device_hash, is_pinned, updated_at),
        KEY conversation_id (conversation_id)
    ) {$charset_collate};";

    dbDelta($sql);
}

function deepseek_conversation_bootstrap_schema($verify_tables = false) {
    static $bootstrapped = false;

    if ($bootstrapped && !$verify_tables) {
        return;
    }

    $bootstrapped = true;
    $stored_version = get_option('deepseek_conversation_tools_schema_version', '');

    if ($stored_version !== DEEPSEEK_CONVERSATION_TOOLS_SCHEMA_VERSION) {
        deepseek_conversation_ensure_schema();
        update_option('deepseek_conversation_tools_schema_version', DEEPSEEK_CONVERSATION_TOOLS_SCHEMA_VERSION, false);
        return;
    }

    if ($verify_tables && function_exists('deepseek_storage_table_exists') && !deepseek_storage_table_exists(deepseek_conversation_meta_table())) {
        deepseek_conversation_ensure_schema();
    }
}

function deepseek_conversation_activate_schema() {
    deepseek_conversation_ensure_schema();
    update_option('deepseek_conversation_tools_schema_version', DEEPSEEK_CONVERSATION_TOOLS_SCHEMA_VERSION, false);
}

function deepseek_conversation_cleanup_schema() {
    global $wpdb;

    $wpdb->query('DROP TABLE IF EXISTS ' . deepseek_conversation_meta_table());
    delete_option('deepseek_conversation_tools_schema_version');
}

function deepseek_conversation_normalize_title($title) {
    $title = sanitize_text_field(wp_strip_all_tags((string) $title));
    $title = trim(preg_replace('/\s+/', ' ', $title));

    if ($title === '') {
        return '';
    }

    return function_exists('mb_substr') ? mb_substr($title, 0, 80, 'UTF-8') : substr($title, 0, 80);
}

function deepseek_conversation_actor_identity($user_id = null) {
    $user_id = null === $user_id ? get_current_user_id() : (int) $user_id;
    $guest_device_hash = '';

    if ($user_id <= 0 && function_exists('deepseek_get_request_device_hash')) {
        $guest_device_hash = deepseek_get_request_device_hash();
    }

    return array(
        'user_id' => max(0, $user_id),
        'guest_device_hash' => $guest_device_hash,
    );
}

function deepseek_conversation_get_meta_for_actor($conversation_id, $user_id = null, $guest_device_hash = null) {
    global $wpdb;

    deepseek_conversation_bootstrap_schema();

    $conversation_id = absint($conversation_id);
    if ($conversation_id <= 0) {
        return null;
    }

    $identity = deepseek_conversation_actor_identity($user_id);
    $user_id = $identity['user_id'];
    $guest_device_hash = null === $guest_device_hash ? $identity['guest_device_hash'] : sanitize_text_field((string) $guest_device_hash);
    $table_name = deepseek_conversation_meta_table();

    if ($user_id > 0) {
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE conversation_id = %d AND user_id = %d LIMIT 1",
            $conversation_id,
            $user_id
        ));
    }

    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table_name} WHERE conversation_id = %d AND user_id = 0 AND guest_device_hash = %s LIMIT 1",
        $conversation_id,
        $guest_device_hash
    ));
}

function deepseek_upsert_conversation_meta($args = array()) {
    global $wpdb;

    deepseek_conversation_bootstrap_schema();

    $defaults = array(
        'conversation_id' => 0,
        'user_id' => get_current_user_id(),
        'guest_device_hash' => '',
    );
    $data = wp_parse_args($args, $defaults);
    $conversation_id = absint($data['conversation_id']);
    if ($conversation_id <= 0) {
        return null;
    }

    $identity = deepseek_conversation_actor_identity((int) $data['user_id']);
    $user_id = $identity['user_id'];
    $guest_device_hash = sanitize_text_field((string) $data['guest_device_hash']);
    if ($user_id <= 0 && $guest_device_hash === '') {
        $guest_device_hash = $identity['guest_device_hash'];
    }

    $existing = deepseek_conversation_get_meta_for_actor($conversation_id, $user_id, $guest_device_hash);
    $table_name = deepseek_conversation_meta_table();
    $now = current_time('mysql');
    $row = array('updated_at' => $now);
    $formats = array('%s');

    if (array_key_exists('title', $args)) {
        $row['title'] = deepseek_conversation_normalize_title($data['title']);
        $formats[] = '%s';
    }

    if (array_key_exists('is_pinned', $args)) {
        $row['is_pinned'] = !empty($data['is_pinned']) ? 1 : 0;
        $formats[] = '%d';
    }

    if ($existing) {
        $wpdb->update(
            $table_name,
            $row,
            array('id' => (int) $existing->id),
            $formats,
            array('%d')
        );

        return deepseek_conversation_get_meta_for_actor($conversation_id, $user_id, $guest_device_hash);
    }

    $insert = array(
        'conversation_id' => $conversation_id,
        'user_id' => $user_id,
        'guest_device_hash' => $guest_device_hash,
        'title' => array_key_exists('title', $args) ? deepseek_conversation_normalize_title($data['title']) : '',
        'is_pinned' => array_key_exists('is_pinned', $args) && !empty($data['is_pinned']) ? 1 : 0,
        'created_at' => $now,
        'updated_at' => $now,
    );

    $inserted = $wpdb->insert(
        $table_name,
        $insert,
        array('%d', '%d', '%s', '%s', '%d', '%s', '%s')
    );

    if (false === $inserted) {
        return null;
    }

    return deepseek_conversation_get_meta_for_actor($conversation_id, $user_id, $guest_device_hash);
}

function deepseek_maybe_create_conversation_meta($conversation_id, $title = '', $user_id = 0) {
    $conversation_id = absint($conversation_id);
    if ($conversation_id <= 0) {
        return null;
    }

    $existing = deepseek_conversation_get_meta_for_actor($conversation_id, $user_id);
    $title = deepseek_conversation_normalize_title($title);

    if ($existing) {
        $args = array(
            'conversation_id' => $conversation_id,
            'user_id' => $user_id,
        );
        if ($title !== '' && empty($existing->title)) {
            $args['title'] = $title;
        }

        return deepseek_upsert_conversation_meta($args);
    }

    return deepseek_upsert_conversation_meta(array(
        'conversation_id' => $conversation_id,
        'user_id' => $user_id,
        'title' => $title,
        'is_pinned' => 0,
    ));
}

function deepseek_delete_conversation_meta_for_current_actor($conversation_id) {
    global $wpdb;

    $conversation_id = absint($conversation_id);
    if ($conversation_id <= 0) {
        return false;
    }

    $identity = deepseek_conversation_actor_identity();
    $table_name = deepseek_conversation_meta_table();

    if ($identity['user_id'] > 0) {
        return $wpdb->delete(
            $table_name,
            array(
                'conversation_id' => $conversation_id,
                'user_id' => $identity['user_id'],
            ),
            array('%d', '%d')
        );
    }

    return $wpdb->delete(
        $table_name,
        array(
            'conversation_id' => $conversation_id,
            'user_id' => 0,
            'guest_device_hash' => $identity['guest_device_hash'],
        ),
        array('%d', '%d', '%s')
    );
}

function deepseek_delete_conversation_meta_all($conversation_id) {
    global $wpdb;

    $conversation_id = absint($conversation_id);
    if ($conversation_id <= 0) {
        return false;
    }

    return $wpdb->delete(
        deepseek_conversation_meta_table(),
        array('conversation_id' => $conversation_id),
        array('%d')
    );
}

function deepseek_conversation_rest_permission(WP_REST_Request $request) {
    $nonce = $request->get_header('x-wp-nonce');
    if (empty($nonce)) {
        $nonce = $request->get_header('X-WP-Nonce');
    }

    if (empty($nonce) || !wp_verify_nonce($nonce, 'wp_rest')) {
        return new WP_Error('rest_forbidden', '请求验证失败', array('status' => 403));
    }

    if (!is_user_logged_in()) {
        $guest_chat_limit = intval(deepseek_get_setting('deepseek_guest_chat_limit', 5));
        if ($guest_chat_limit <= 0) {
            return new WP_Error('rest_forbidden', '游客功能已关闭，请先登录后再管理对话', array('status' => 401));
        }
    }

    return true;
}

function deepseek_conversation_update_rest(WP_REST_Request $request) {
    $conversation_id = absint($request->get_param('conversation_id'));
    if ($conversation_id <= 0) {
        return new WP_REST_Response(array('success' => false, 'message' => '缺少对话 ID'), 400);
    }

    if (!function_exists('deepseek_current_user_can_access_chat_conversation') || !deepseek_current_user_can_access_chat_conversation($conversation_id)) {
        return new WP_REST_Response(array('success' => false, 'message' => '无权管理该对话'), 403);
    }

    $updates = array('conversation_id' => $conversation_id);
    if ($request->has_param('title')) {
        $updates['title'] = deepseek_conversation_normalize_title($request->get_param('title'));
        if ($updates['title'] === '') {
            return new WP_REST_Response(array('success' => false, 'message' => '标题不能为空'), 400);
        }
    }

    if ($request->has_param('is_pinned')) {
        $updates['is_pinned'] = filter_var($request->get_param('is_pinned'), FILTER_VALIDATE_BOOLEAN);
    }

    if (count($updates) === 1) {
        return new WP_REST_Response(array('success' => false, 'message' => '没有可更新的内容'), 400);
    }

    $meta = deepseek_upsert_conversation_meta($updates);
    if (!$meta) {
        return new WP_REST_Response(array('success' => false, 'message' => '保存失败'), 500);
    }

    return new WP_REST_Response(array(
        'success' => true,
        'conversation_id' => $conversation_id,
        'title' => $meta->title,
        'is_pinned' => (int) $meta->is_pinned,
    ), 200);
}

function deepseek_conversation_response_to_text($response) {
    $decoded = json_decode((string) $response, true);
    if (is_array($decoded)) {
        if (!empty($decoded['content']) || !empty($decoded['reasoning_content'])) {
            $parts = array();
            if (!empty($decoded['reasoning_content'])) {
                $parts[] = "推理过程：\n" . $decoded['reasoning_content'];
            }
            if (!empty($decoded['content'])) {
                $parts[] = $decoded['content'];
            }
            return trim(implode("\n\n", $parts));
        }

        if (!empty($decoded['image_url'])) {
            return '图片：' . esc_url_raw($decoded['image_url']);
        }

        if (!empty($decoded['video_url'])) {
            return '视频：' . esc_url_raw($decoded['video_url']);
        }

        if (!empty($decoded['task_id'])) {
            return '异步任务：' . sanitize_text_field($decoded['task_id']) . "\n状态：" . sanitize_text_field($decoded['status'] ?? '');
        }

        return wp_json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    return trim(wp_strip_all_tags((string) $response));
}

function deepseek_conversation_export_rest(WP_REST_Request $request) {
    $conversation_id = absint($request->get_param('conversation_id'));
    if ($conversation_id <= 0) {
        return new WP_REST_Response(array('success' => false, 'message' => '缺少对话 ID'), 400);
    }

    if (!function_exists('deepseek_get_chat_conversation_logs')) {
        return new WP_REST_Response(array('success' => false, 'message' => '对话模块不可用'), 500);
    }

    $logs = deepseek_get_chat_conversation_logs($conversation_id);
    if (empty($logs)) {
        return new WP_REST_Response(array('success' => false, 'message' => '未找到可导出的对话'), 404);
    }

    $meta = deepseek_conversation_get_meta_for_actor($conversation_id);
    $fallback_title = !empty($logs[0]->conversation_title) ? $logs[0]->conversation_title : $logs[0]->message;
    $title = $meta && !empty($meta->title) ? $meta->title : deepseek_conversation_normalize_title($fallback_title);
    $markdown = '# ' . ($title ?: ('对话 #' . $conversation_id)) . "\n\n";
    $markdown .= '- 导出时间：' . current_time('mysql') . "\n";
    $markdown .= '- 对话 ID：' . $conversation_id . "\n\n";

    foreach ($logs as $index => $log) {
        $markdown .= '## 第 ' . ((int) $index + 1) . " 轮\n\n";
        $markdown .= "### 用户\n\n" . trim(wp_strip_all_tags((string) $log->message)) . "\n\n";
        $markdown .= "### AI\n\n" . deepseek_conversation_response_to_text($log->response) . "\n\n";
    }

    return new WP_REST_Response(array(
        'success' => true,
        'conversation_id' => $conversation_id,
        'title' => $title,
        'filename' => sanitize_file_name('deepseek-chat-' . $conversation_id . '-' . current_time('Ymd-His') . '.md'),
        'markdown' => $markdown,
    ), 200);
}

function deepseek_conversation_register_rest_routes() {
    register_rest_route('deepseek/v1', '/conversation-meta', array(
        'methods' => 'POST',
        'callback' => 'deepseek_conversation_update_rest',
        'permission_callback' => 'deepseek_conversation_rest_permission',
    ));

    register_rest_route('deepseek/v1', '/conversation-export', array(
        'methods' => 'GET',
        'callback' => 'deepseek_conversation_export_rest',
        'permission_callback' => 'deepseek_conversation_rest_permission',
        'args' => array(
            'conversation_id' => array(
                'required' => true,
                'sanitize_callback' => 'absint',
            ),
        ),
    ));
}
add_action('rest_api_init', 'deepseek_conversation_register_rest_routes');

deepseek_conversation_bootstrap_schema();
register_activation_hook(DEEPSEEK_PLUGIN_FILE, 'deepseek_conversation_activate_schema');

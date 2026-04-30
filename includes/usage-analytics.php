<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!defined('DEEPSEEK_USAGE_ANALYTICS_SCHEMA_VERSION')) {
    define('DEEPSEEK_USAGE_ANALYTICS_SCHEMA_VERSION', '1.0.1');
}

function deepseek_usage_events_table() {
    global $wpdb;
    return $wpdb->prefix . 'deepseek_usage_events';
}

function deepseek_message_feedback_table() {
    global $wpdb;
    return $wpdb->prefix . 'deepseek_message_feedback';
}

function deepseek_usage_ensure_schema() {
    global $wpdb;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $charset_collate = $wpdb->get_charset_collate();
    $events_table = deepseek_usage_events_table();
    $feedback_table = deepseek_message_feedback_table();

    $events_sql = "CREATE TABLE {$events_table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        user_id bigint(20) unsigned NOT NULL DEFAULT 0,
        guest_device_hash varchar(64) NOT NULL DEFAULT '',
        conversation_id bigint(20) unsigned NOT NULL DEFAULT 0,
        message_id bigint(20) unsigned NOT NULL DEFAULT 0,
        event_type varchar(40) NOT NULL DEFAULT 'chat',
        interface_key varchar(32) NOT NULL DEFAULT '',
        model varchar(120) NOT NULL DEFAULT '',
        prompt_template_id bigint(20) unsigned NOT NULL DEFAULT 0,
        kb_source_count int(10) unsigned NOT NULL DEFAULT 0,
        message_chars int(10) unsigned NOT NULL DEFAULT 0,
        response_chars int(10) unsigned NOT NULL DEFAULT 0,
        latency_ms int(10) unsigned NOT NULL DEFAULT 0,
        status varchar(20) NOT NULL DEFAULT 'success',
        meta longtext NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id),
        KEY created_at (created_at),
        KEY user_created_at (user_id, created_at),
        KEY guest_created_at (guest_device_hash, created_at),
        KEY conversation_id (conversation_id),
        KEY message_id (message_id),
        KEY interface_created_at (interface_key, created_at),
        KEY model_created_at (model, created_at),
        KEY status_created_at (status, created_at)
    ) {$charset_collate};";

    $feedback_sql = "CREATE TABLE {$feedback_table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        user_id bigint(20) unsigned NOT NULL DEFAULT 0,
        guest_device_hash varchar(64) NOT NULL DEFAULT '',
        conversation_id bigint(20) unsigned NOT NULL DEFAULT 0,
        message_id bigint(20) unsigned NOT NULL DEFAULT 0,
        rating varchar(12) NOT NULL DEFAULT '',
        reason varchar(80) NOT NULL DEFAULT '',
        comment text NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id),
        KEY conversation_id (conversation_id),
        KEY message_id (message_id),
        KEY rating_created_at (rating, created_at),
        KEY user_conversation (user_id, conversation_id),
        KEY guest_conversation (guest_device_hash, conversation_id),
        KEY user_message (user_id, message_id),
        KEY guest_message (guest_device_hash, message_id),
        KEY updated_at (updated_at)
    ) {$charset_collate};";

    dbDelta($events_sql);
    dbDelta($feedback_sql);
}

function deepseek_usage_bootstrap_schema() {
    static $bootstrapped = false;

    if ($bootstrapped) {
        return;
    }

    $bootstrapped = true;
    $stored_version = get_option('deepseek_usage_analytics_schema_version', '');

    if ($stored_version !== DEEPSEEK_USAGE_ANALYTICS_SCHEMA_VERSION) {
        deepseek_usage_ensure_schema();
        update_option('deepseek_usage_analytics_schema_version', DEEPSEEK_USAGE_ANALYTICS_SCHEMA_VERSION, false);
        return;
    }

    if (function_exists('deepseek_storage_table_exists')) {
        $tables = array(
            deepseek_usage_events_table(),
            deepseek_message_feedback_table(),
        );

        foreach ($tables as $table_name) {
            if (!deepseek_storage_table_exists($table_name)) {
                deepseek_usage_ensure_schema();
                return;
            }
        }
    }
}

function deepseek_usage_activate_schema() {
    deepseek_usage_ensure_schema();
    update_option('deepseek_usage_analytics_schema_version', DEEPSEEK_USAGE_ANALYTICS_SCHEMA_VERSION, false);
}

function deepseek_usage_cleanup_schema() {
    global $wpdb;

    $wpdb->query('DROP TABLE IF EXISTS ' . deepseek_message_feedback_table());
    $wpdb->query('DROP TABLE IF EXISTS ' . deepseek_usage_events_table());
    delete_option('deepseek_usage_analytics_schema_version');
}

function deepseek_usage_strlen($text) {
    $text = (string) $text;
    return function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
}

function deepseek_usage_elapsed_ms($started_at) {
    $started_at = (float) $started_at;
    if ($started_at <= 0) {
        return 0;
    }

    return max(0, (int) round((microtime(true) - $started_at) * 1000));
}

function deepseek_record_usage_event($args = array()) {
    global $wpdb;

    deepseek_usage_bootstrap_schema();

    $defaults = array(
        'user_id' => get_current_user_id(),
        'guest_device_hash' => '',
        'conversation_id' => 0,
        'message_id' => 0,
        'event_type' => 'chat',
        'interface_key' => '',
        'model' => '',
        'prompt_template_id' => 0,
        'kb_source_count' => 0,
        'message_chars' => 0,
        'response_chars' => 0,
        'latency_ms' => 0,
        'status' => 'success',
        'meta' => array(),
    );
    $data = wp_parse_args($args, $defaults);

    if ((int) $data['user_id'] <= 0 && $data['guest_device_hash'] === '' && function_exists('deepseek_get_request_device_hash')) {
        $data['guest_device_hash'] = deepseek_get_request_device_hash();
    }

    $meta = $data['meta'];
    if (is_array($meta) || is_object($meta)) {
        $meta = wp_json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } else {
        $meta = (string) $meta;
    }

    $inserted = $wpdb->insert(
        deepseek_usage_events_table(),
        array(
            'user_id' => max(0, (int) $data['user_id']),
            'guest_device_hash' => sanitize_text_field((string) $data['guest_device_hash']),
            'conversation_id' => absint($data['conversation_id']),
            'message_id' => absint($data['message_id']),
            'event_type' => sanitize_key((string) $data['event_type']),
            'interface_key' => sanitize_key((string) $data['interface_key']),
            'model' => sanitize_text_field((string) $data['model']),
            'prompt_template_id' => absint($data['prompt_template_id']),
            'kb_source_count' => max(0, (int) $data['kb_source_count']),
            'message_chars' => max(0, (int) $data['message_chars']),
            'response_chars' => max(0, (int) $data['response_chars']),
            'latency_ms' => max(0, (int) $data['latency_ms']),
            'status' => sanitize_key((string) $data['status']),
            'meta' => $meta,
        ),
        array('%d', '%s', '%d', '%d', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%s', '%s')
    );

    return false === $inserted ? 0 : (int) $wpdb->insert_id;
}

function deepseek_record_message_feedback($args = array()) {
    global $wpdb;

    deepseek_usage_bootstrap_schema();

    $defaults = array(
        'user_id' => get_current_user_id(),
        'guest_device_hash' => '',
        'conversation_id' => 0,
        'message_id' => 0,
        'rating' => '',
        'reason' => '',
        'comment' => '',
    );
    $data = wp_parse_args($args, $defaults);
    $user_id = max(0, (int) $data['user_id']);
    $conversation_id = absint($data['conversation_id']);
    $message_id = absint($data['message_id']);
    $guest_device_hash = sanitize_text_field((string) $data['guest_device_hash']);

    if ($user_id <= 0 && $guest_device_hash === '' && function_exists('deepseek_get_request_device_hash')) {
        $guest_device_hash = deepseek_get_request_device_hash();
    }

    if ($conversation_id <= 0 && $message_id <= 0) {
        return 0;
    }

    $rating = sanitize_key((string) $data['rating']);
    if (!in_array($rating, array('up', 'down'), true)) {
        return 0;
    }

    $table_name = deepseek_message_feedback_table();
    if ($message_id > 0 && $user_id > 0) {
        $existing_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table_name} WHERE message_id = %d AND user_id = %d LIMIT 1",
            $message_id,
            $user_id
        ));
    } elseif ($message_id > 0) {
        $existing_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table_name} WHERE message_id = %d AND user_id = 0 AND guest_device_hash = %s LIMIT 1",
            $message_id,
            $guest_device_hash
        ));
    } elseif ($user_id > 0) {
        $existing_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table_name} WHERE conversation_id = %d AND user_id = %d LIMIT 1",
            $conversation_id,
            $user_id
        ));
    } else {
        $existing_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table_name} WHERE conversation_id = %d AND user_id = 0 AND guest_device_hash = %s LIMIT 1",
            $conversation_id,
            $guest_device_hash
        ));
    }

    $row = array(
        'user_id' => $user_id,
        'guest_device_hash' => $guest_device_hash,
        'conversation_id' => $conversation_id,
        'message_id' => $message_id,
        'rating' => $rating,
        'reason' => sanitize_text_field((string) $data['reason']),
        'comment' => sanitize_textarea_field((string) $data['comment']),
        'updated_at' => current_time('mysql'),
    );

    if ($existing_id > 0) {
        $updated = $wpdb->update(
            $table_name,
            $row,
            array('id' => $existing_id),
            array('%d', '%s', '%d', '%d', '%s', '%s', '%s', '%s'),
            array('%d')
        );

        return false === $updated ? 0 : $existing_id;
    }

    $row['created_at'] = current_time('mysql');
    $inserted = $wpdb->insert(
        $table_name,
        $row,
        array('%d', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s')
    );

    return false === $inserted ? 0 : (int) $wpdb->insert_id;
}

function deepseek_usage_rest_permission(WP_REST_Request $request) {
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
            return new WP_Error('rest_forbidden', '游客功能已关闭，请先登录后再反馈', array('status' => 401));
        }
    }

    return true;
}

function deepseek_usage_get_accessible_message_context($message_id, $conversation_id = 0) {
    global $wpdb;

    $message_id = absint($message_id);
    $conversation_id = absint($conversation_id);

    if ($message_id <= 0) {
        if ($conversation_id > 0 && function_exists('deepseek_current_user_can_access_chat_conversation') && deepseek_current_user_can_access_chat_conversation($conversation_id)) {
            return array(
                'message_id' => 0,
                'conversation_id' => $conversation_id,
            );
        }

        return null;
    }

    if (!function_exists('deepseek_get_chat_logs_table_name') || !function_exists('deepseek_current_user_can_access_chat_conversation')) {
        return null;
    }

    $table_name = deepseek_get_chat_logs_table_name();
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT id, conversation_id FROM {$table_name} WHERE id = %d LIMIT 1",
        $message_id
    ));

    if (!$row) {
        return null;
    }

    $row_conversation_id = absint($row->conversation_id);
    if ($conversation_id > 0 && $conversation_id !== $row_conversation_id) {
        return null;
    }

    if (!deepseek_current_user_can_access_chat_conversation($row_conversation_id)) {
        return null;
    }

    return array(
        'message_id' => (int) $row->id,
        'conversation_id' => $row_conversation_id,
    );
}

function deepseek_handle_message_feedback_rest(WP_REST_Request $request) {
    $conversation_id = absint($request->get_param('conversation_id'));
    $message_id = absint($request->get_param('message_id'));
    $rating = sanitize_key((string) $request->get_param('rating'));

    if ($conversation_id <= 0 && $message_id <= 0) {
        return new WP_REST_Response(array('success' => false, 'message' => '缺少反馈对象'), 400);
    }

    if (!in_array($rating, array('up', 'down'), true)) {
        return new WP_REST_Response(array('success' => false, 'message' => '无效的反馈类型'), 400);
    }

    $message_context = deepseek_usage_get_accessible_message_context($message_id, $conversation_id);
    if (!$message_context) {
        return new WP_REST_Response(array('success' => false, 'message' => '无权反馈该对话'), 403);
    }

    $feedback_id = deepseek_record_message_feedback(array(
        'conversation_id' => $message_context['conversation_id'],
        'message_id' => $message_context['message_id'],
        'rating' => $rating,
        'reason' => sanitize_text_field((string) $request->get_param('reason')),
        'comment' => sanitize_textarea_field((string) $request->get_param('comment')),
    ));

    if ($feedback_id <= 0) {
        return new WP_REST_Response(array('success' => false, 'message' => '反馈保存失败'), 500);
    }

    return new WP_REST_Response(array(
        'success' => true,
        'feedback_id' => $feedback_id,
        'message' => '反馈已记录',
    ), 200);
}

function deepseek_usage_register_rest_routes() {
    register_rest_route('deepseek/v1', '/message-feedback', array(
        'methods' => 'POST',
        'callback' => 'deepseek_handle_message_feedback_rest',
        'permission_callback' => 'deepseek_usage_rest_permission',
        'args' => array(
            'conversation_id' => array(
                'required' => true,
                'sanitize_callback' => 'absint',
            ),
            'rating' => array(
                'required' => true,
                'sanitize_callback' => 'sanitize_key',
            ),
            'message_id' => array(
                'required' => false,
                'sanitize_callback' => 'absint',
            ),
        ),
    ));
}
add_action('rest_api_init', 'deepseek_usage_register_rest_routes');

function deepseek_usage_render_admin_page() {
    if (!current_user_can('manage_options')) {
        wp_die('权限不足');
    }

    global $wpdb;
    deepseek_usage_bootstrap_schema();

    $events_table = deepseek_usage_events_table();
    $feedback_table = deepseek_message_feedback_table();
    $today_start = current_time('Y-m-d') . ' 00:00:00';

    $total_events = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$events_table}");
    $today_events = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$events_table} WHERE created_at >= %s",
        $today_start
    ));
    $success_events = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$events_table} WHERE status IN ('success', 'queued')");
    $error_events = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$events_table} WHERE status = 'error'");
    $avg_latency = (int) $wpdb->get_var("SELECT AVG(latency_ms) FROM {$events_table} WHERE latency_ms > 0");
    $positive_feedback = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$feedback_table} WHERE rating = 'up'");
    $negative_feedback = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$feedback_table} WHERE rating = 'down'");

    $model_rows = $wpdb->get_results(
        "SELECT interface_key, model, event_type, COUNT(*) AS total, SUM(message_chars) AS message_chars, SUM(response_chars) AS response_chars, AVG(NULLIF(latency_ms, 0)) AS avg_latency
        FROM {$events_table}
        GROUP BY interface_key, model, event_type
        ORDER BY total DESC
        LIMIT 20"
    );

    $recent_events = $wpdb->get_results(
        "SELECT * FROM {$events_table} ORDER BY created_at DESC LIMIT 15"
    );

    $recent_feedback = $wpdb->get_results(
        "SELECT f.*, u.user_login
        FROM {$feedback_table} f
        LEFT JOIN {$wpdb->users} u ON f.user_id = u.ID
        ORDER BY f.updated_at DESC
        LIMIT 20"
    );

    echo '<div class="wrap">';
    echo '<h1>使用统计</h1>';
    echo '<p>这里统计模型调用、知识库/提示词命中和用户回答反馈，数据全部写入插件自己的数据表。</p>';

    echo '<style>
        .deepseek-usage-cards { display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:12px; margin:18px 0; }
        .deepseek-usage-card { background:#fff; border:1px solid #dcdcde; border-radius:8px; padding:14px 16px; }
        .deepseek-usage-card strong { display:block; font-size:26px; line-height:1.25; margin-top:6px; }
        .deepseek-usage-card span { color:#646970; }
        .deepseek-usage-section { margin-top:24px; }
        .deepseek-rating-up { color:#11843b; font-weight:600; }
        .deepseek-rating-down { color:#b42318; font-weight:600; }
        .deepseek-meta { color:#646970; font-size:12px; }
    </style>';

    echo '<div class="deepseek-usage-cards">';
    echo '<div class="deepseek-usage-card"><span>总调用</span><strong>' . esc_html(number_format_i18n($total_events)) . '</strong></div>';
    echo '<div class="deepseek-usage-card"><span>今日调用</span><strong>' . esc_html(number_format_i18n($today_events)) . '</strong></div>';
    echo '<div class="deepseek-usage-card"><span>成功/排队</span><strong>' . esc_html(number_format_i18n($success_events)) . '</strong></div>';
    echo '<div class="deepseek-usage-card"><span>失败</span><strong>' . esc_html(number_format_i18n($error_events)) . '</strong></div>';
    echo '<div class="deepseek-usage-card"><span>平均耗时</span><strong>' . esc_html(number_format_i18n($avg_latency)) . 'ms</strong></div>';
    echo '<div class="deepseek-usage-card"><span>反馈</span><strong><span class="deepseek-rating-up">' . esc_html(number_format_i18n($positive_feedback)) . '</span> / <span class="deepseek-rating-down">' . esc_html(number_format_i18n($negative_feedback)) . '</span></strong></div>';
    echo '</div>';

    echo '<div class="deepseek-usage-section">';
    echo '<h2>接口与模型分布</h2>';
    echo '<table class="widefat striped"><thead><tr><th>接口</th><th>模型</th><th>类型</th><th>调用</th><th>输入字符</th><th>输出字符</th><th>平均耗时</th></tr></thead><tbody>';
    if (empty($model_rows)) {
        echo '<tr><td colspan="7">暂无统计数据。</td></tr>';
    } else {
        foreach ($model_rows as $row) {
            echo '<tr>';
            echo '<td>' . esc_html($row->interface_key) . '</td>';
            echo '<td>' . esc_html($row->model) . '</td>';
            echo '<td>' . esc_html($row->event_type) . '</td>';
            echo '<td>' . esc_html(number_format_i18n((int) $row->total)) . '</td>';
            echo '<td>' . esc_html(number_format_i18n((int) $row->message_chars)) . '</td>';
            echo '<td>' . esc_html(number_format_i18n((int) $row->response_chars)) . '</td>';
            echo '<td>' . esc_html(number_format_i18n((int) $row->avg_latency)) . 'ms</td>';
            echo '</tr>';
        }
    }
    echo '</tbody></table>';
    echo '</div>';

    echo '<div class="deepseek-usage-section">';
    echo '<h2>最近反馈</h2>';
    echo '<table class="widefat striped"><thead><tr><th>对话</th><th>消息</th><th>用户</th><th>反馈</th><th>备注</th><th>时间</th></tr></thead><tbody>';
    if (empty($recent_feedback)) {
        echo '<tr><td colspan="6">暂无反馈。</td></tr>';
    } else {
        foreach ($recent_feedback as $feedback) {
            $rating_label = $feedback->rating === 'up' ? '有用' : '没用';
            $rating_class = $feedback->rating === 'up' ? 'deepseek-rating-up' : 'deepseek-rating-down';
            $actor = (int) $feedback->user_id > 0
                ? ($feedback->user_login ? $feedback->user_login : ('用户 #' . (int) $feedback->user_id))
                : '游客';

            echo '<tr>';
            echo '<td>#' . esc_html((int) $feedback->conversation_id) . '</td>';
            echo '<td>' . ($feedback->message_id ? '#' . esc_html((int) $feedback->message_id) : '-') . '</td>';
            echo '<td>' . esc_html($actor) . '</td>';
            echo '<td><span class="' . esc_attr($rating_class) . '">' . esc_html($rating_label) . '</span></td>';
            echo '<td>' . esc_html($feedback->comment ?: $feedback->reason) . '</td>';
            echo '<td>' . esc_html($feedback->updated_at) . '</td>';
            echo '</tr>';
        }
    }
    echo '</tbody></table>';
    echo '</div>';

    echo '<div class="deepseek-usage-section">';
    echo '<h2>最近调用</h2>';
    echo '<table class="widefat striped"><thead><tr><th>时间</th><th>状态</th><th>类型</th><th>接口</th><th>模型</th><th>对话</th><th>消息</th><th>知识库</th><th>提示词</th><th>耗时</th></tr></thead><tbody>';
    if (empty($recent_events)) {
        echo '<tr><td colspan="10">暂无调用记录。</td></tr>';
    } else {
        foreach ($recent_events as $event) {
            echo '<tr>';
            echo '<td>' . esc_html($event->created_at) . '</td>';
            echo '<td>' . esc_html($event->status) . '</td>';
            echo '<td>' . esc_html($event->event_type) . '</td>';
            echo '<td>' . esc_html($event->interface_key) . '</td>';
            echo '<td>' . esc_html($event->model) . '</td>';
            echo '<td>#' . esc_html((int) $event->conversation_id) . '</td>';
            echo '<td>' . ($event->message_id ? '#' . esc_html((int) $event->message_id) : '-') . '</td>';
            echo '<td>' . esc_html((int) $event->kb_source_count) . '</td>';
            echo '<td>' . ($event->prompt_template_id ? '#' . esc_html((int) $event->prompt_template_id) : '-') . '</td>';
            echo '<td>' . esc_html(number_format_i18n((int) $event->latency_ms)) . 'ms</td>';
            echo '</tr>';
        }
    }
    echo '</tbody></table>';
    echo '</div>';
    echo '</div>';
}

function deepseek_usage_register_admin_menu() {
    add_submenu_page(
        'deepseek',
        '使用统计',
        '使用统计',
        'manage_options',
        'deepseek-usage',
        'deepseek_usage_render_admin_page'
    );
}
add_action('admin_menu', 'deepseek_usage_register_admin_menu', 20);

deepseek_usage_bootstrap_schema();
register_activation_hook(DEEPSEEK_PLUGIN_FILE, 'deepseek_usage_activate_schema');

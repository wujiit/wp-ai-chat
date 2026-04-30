<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!defined('DEEPSEEK_STORAGE_SCHEMA_VERSION')) {
    define('DEEPSEEK_STORAGE_SCHEMA_VERSION', '1.0.0');
}

function deepseek_get_chat_logs_table_name() {
    global $wpdb;
    return $wpdb->prefix . 'deepseek_chat_logs';
}

function deepseek_get_agent_chat_logs_table_name() {
    global $wpdb;
    return $wpdb->prefix . 'deepseek_agent_chat_logs';
}

function deepseek_get_file_records_table_name() {
    global $wpdb;
    return $wpdb->prefix . 'deepseek_file_records';
}

function deepseek_storage_table_exists($table_name) {
    global $wpdb;

    $found_table = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name));
    return $found_table === $table_name;
}

function deepseek_ensure_storage_schema() {
    global $wpdb;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $charset_collate = $wpdb->get_charset_collate();
    $chat_logs_table = deepseek_get_chat_logs_table_name();
    $agent_logs_table = deepseek_get_agent_chat_logs_table_name();
    $file_records_table = deepseek_get_file_records_table_name();

    $chat_logs_sql = "CREATE TABLE {$chat_logs_table} (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        user_id mediumint(9) NOT NULL,
        conversation_id mediumint(9) NOT NULL,
        conversation_title text NOT NULL,
        message text NOT NULL,
        response text NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id),
        KEY user_created_at (user_id, created_at),
        KEY conversation_created_at (conversation_id, created_at),
        KEY user_conversation (user_id, conversation_id)
    ) {$charset_collate};";

    $agent_logs_sql = "CREATE TABLE {$agent_logs_table} (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        user_id mediumint(9) NOT NULL,
        app_id varchar(255) NOT NULL,
        message text NOT NULL,
        response text NOT NULL,
        session_id varchar(255) DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id),
        KEY user_created_at (user_id, created_at),
        KEY app_created_at (app_id(100), created_at),
        KEY session_id (session_id(100))
    ) {$charset_collate};";

    $file_records_sql = "CREATE TABLE {$file_records_table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        user_id bigint(20) unsigned NOT NULL DEFAULT 0,
        guest_device_hash varchar(64) NOT NULL DEFAULT '',
        record_source varchar(32) NOT NULL DEFAULT 'chat',
        interface_key varchar(32) NOT NULL DEFAULT '',
        provider_name varchar(32) NOT NULL DEFAULT '',
        storage_engine varchar(32) NOT NULL DEFAULT '',
        purpose varchar(32) NOT NULL DEFAULT '',
        provider_file_id varchar(191) NOT NULL DEFAULT '',
        attachment_id bigint(20) unsigned NOT NULL DEFAULT 0,
        original_filename varchar(255) NOT NULL DEFAULT '',
        mime_type varchar(120) NOT NULL DEFAULT '',
        file_ext varchar(32) NOT NULL DEFAULT '',
        file_size bigint(20) unsigned NOT NULL DEFAULT 0,
        local_url text NULL,
        remote_url text NULL,
        status varchar(20) NOT NULL DEFAULT 'active',
        meta longtext NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id),
        KEY user_created_at (user_id, created_at),
        KEY guest_created_at (guest_device_hash, created_at),
        KEY source_created_at (record_source, created_at),
        KEY provider_file_id (provider_file_id),
        KEY attachment_id (attachment_id),
        KEY status_created_at (status, created_at)
    ) {$charset_collate};";

    dbDelta($chat_logs_sql);
    dbDelta($agent_logs_sql);
    dbDelta($file_records_sql);
}

function deepseek_bootstrap_storage_schema($verify_tables = false) {
    static $bootstrapped = false;

    if ($bootstrapped && !$verify_tables) {
        return;
    }

    $bootstrapped = true;

    $stored_version = get_option('deepseek_storage_schema_version', '');
    if ($stored_version !== DEEPSEEK_STORAGE_SCHEMA_VERSION) {
        deepseek_ensure_storage_schema();
        update_option('deepseek_storage_schema_version', DEEPSEEK_STORAGE_SCHEMA_VERSION, false);
        return;
    }

    if (!$verify_tables) {
        return;
    }

    foreach (deepseek_get_storage_schema_tables() as $table_name) {
        if (!deepseek_storage_table_exists($table_name)) {
            deepseek_ensure_storage_schema();
            return;
        }
    }
}

function deepseek_get_storage_schema_tables() {
    return array(
        deepseek_get_chat_logs_table_name(),
        deepseek_get_agent_chat_logs_table_name(),
        deepseek_get_file_records_table_name(),
    );
}

function deepseek_activate_storage_schema() {
    deepseek_ensure_storage_schema();
    update_option('deepseek_storage_schema_version', DEEPSEEK_STORAGE_SCHEMA_VERSION, false);
}

function deepseek_get_request_device_hash() {
    $device_id = '';
    if (isset($_SERVER['HTTP_X_DEVICE_ID'])) {
        $device_id = sanitize_text_field(wp_unslash($_SERVER['HTTP_X_DEVICE_ID']));
    } elseif (isset($_SERVER['REMOTE_ADDR'])) {
        $device_id = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
    }

    if ($device_id === '') {
        return '';
    }

    return md5(substr($device_id, 0, 100));
}

function deepseek_record_file_upload($args) {
    global $wpdb;

    $defaults = array(
        'user_id' => 0,
        'guest_device_hash' => '',
        'record_source' => 'chat',
        'interface_key' => '',
        'provider_name' => '',
        'storage_engine' => '',
        'purpose' => '',
        'provider_file_id' => '',
        'attachment_id' => 0,
        'original_filename' => '',
        'mime_type' => '',
        'file_ext' => '',
        'file_size' => 0,
        'local_url' => '',
        'remote_url' => '',
        'status' => 'active',
        'meta' => array(),
    );

    $data = wp_parse_args($args, $defaults);
    if ($data['guest_device_hash'] === '' && intval($data['user_id']) === 0) {
        $data['guest_device_hash'] = deepseek_get_request_device_hash();
    }

    $meta = $data['meta'];
    if (is_array($meta) || is_object($meta)) {
        $meta = wp_json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } else {
        $meta = (string) $meta;
    }

    $inserted = $wpdb->insert(
        deepseek_get_file_records_table_name(),
        array(
            'user_id' => intval($data['user_id']),
            'guest_device_hash' => sanitize_text_field($data['guest_device_hash']),
            'record_source' => sanitize_key($data['record_source']),
            'interface_key' => sanitize_key($data['interface_key']),
            'provider_name' => sanitize_key($data['provider_name']),
            'storage_engine' => sanitize_key($data['storage_engine']),
            'purpose' => sanitize_key($data['purpose']),
            'provider_file_id' => sanitize_text_field($data['provider_file_id']),
            'attachment_id' => absint($data['attachment_id']),
            'original_filename' => sanitize_file_name($data['original_filename']),
            'mime_type' => sanitize_text_field($data['mime_type']),
            'file_ext' => sanitize_text_field($data['file_ext']),
            'file_size' => max(0, intval($data['file_size'])),
            'local_url' => esc_url_raw($data['local_url']),
            'remote_url' => esc_url_raw($data['remote_url']),
            'status' => sanitize_key($data['status']),
            'meta' => $meta,
            'updated_at' => current_time('mysql'),
        ),
        array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s')
    );

    if (false === $inserted) {
        return 0;
    }

    return intval($wpdb->insert_id);
}

function deepseek_mark_attachment_file_record($attachment_id, $record_id, $record_source) {
    $attachment_id = absint($attachment_id);
    $record_id = absint($record_id);

    if (!$attachment_id || !$record_id) {
        return;
    }

    update_post_meta($attachment_id, '_deepseek_file_record_id', $record_id);
    update_post_meta($attachment_id, '_deepseek_file_record_source', sanitize_key($record_source));
}

function deepseek_mark_provider_file_deleted($provider_name, $provider_file_id) {
    $record = deepseek_get_file_record_by_provider_file($provider_name, $provider_file_id);
    if ($record) {
        deepseek_update_file_record_status((int) $record->id, 'deleted');
        return;
    }

    global $wpdb;

    if ($provider_file_id === '') {
        return;
    }

    $wpdb->update(
        deepseek_get_file_records_table_name(),
        array(
            'status' => 'deleted',
            'updated_at' => current_time('mysql'),
        ),
        array(
            'provider_name' => sanitize_key($provider_name),
            'provider_file_id' => sanitize_text_field($provider_file_id),
        ),
        array('%s', '%s'),
        array('%s', '%s')
    );
}

function deepseek_get_file_record_by_provider_file($provider_name, $provider_file_id) {
    global $wpdb;

    if ($provider_file_id === '') {
        return null;
    }

    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM " . deepseek_get_file_records_table_name() . " WHERE provider_name = %s AND provider_file_id = %s ORDER BY id DESC LIMIT 1",
        sanitize_key($provider_name),
        sanitize_text_field($provider_file_id)
    ));
}

function deepseek_get_file_record($record_id) {
    global $wpdb;

    $record_id = absint($record_id);
    if (!$record_id) {
        return null;
    }

    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM " . deepseek_get_file_records_table_name() . " WHERE id = %d",
        $record_id
    ));
}

function deepseek_update_file_record_status($record_id, $status, $extra = array()) {
    global $wpdb;

    $record_id = absint($record_id);
    if (!$record_id) {
        return false;
    }

    $data = array(
        'status' => sanitize_key($status),
        'updated_at' => current_time('mysql'),
    );
    $format = array('%s', '%s');

    if (isset($extra['remote_url'])) {
        $data['remote_url'] = esc_url_raw($extra['remote_url']);
        $format[] = '%s';
    }

    if (isset($extra['local_url'])) {
        $data['local_url'] = esc_url_raw($extra['local_url']);
        $format[] = '%s';
    }

    if (isset($extra['meta'])) {
        $meta = $extra['meta'];
        if (is_array($meta) || is_object($meta)) {
            $meta = wp_json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        $data['meta'] = (string) $meta;
        $format[] = '%s';
    }

    $updated = $wpdb->update(
        deepseek_get_file_records_table_name(),
        $data,
        array('id' => $record_id),
        $format,
        array('%d')
    );

    return false !== $updated;
}

function deepseek_current_actor_can_access_file_record($record) {
    if (!$record) {
        return false;
    }

    if (current_user_can('manage_options')) {
        return true;
    }

    $current_user_id = get_current_user_id();
    if ($current_user_id > 0) {
        return intval($record->user_id) === $current_user_id;
    }

    $guest_device_hash = deepseek_get_request_device_hash();
    return $guest_device_hash !== '' && hash_equals((string) $record->guest_device_hash, $guest_device_hash);
}

function deepseek_get_file_record_download_url($record) {
    if (!$record) {
        return '';
    }

    if (!empty($record->local_url)) {
        return esc_url_raw($record->local_url);
    }

    if (!empty($record->attachment_id)) {
        $attachment_url = wp_get_attachment_url((int) $record->attachment_id);
        return $attachment_url ? esc_url_raw($attachment_url) : '';
    }

    return !empty($record->remote_url) ? esc_url_raw($record->remote_url) : '';
}

function deepseek_normalize_file_record_for_chat($record) {
    if (!$record || $record->status !== 'active') {
        return null;
    }

    $file_id = '';
    if (!empty($record->provider_file_id)) {
        $file_id = (string) $record->provider_file_id;
    } elseif (!empty($record->attachment_id)) {
        $file_id = (string) absint($record->attachment_id);
    }

    if ($file_id === '') {
        return null;
    }

    $file_url = deepseek_get_file_record_download_url($record);
    $payload = array(
        'record_id' => (int) $record->id,
        'file_id' => $file_id,
        'filename' => $record->original_filename ?: '未命名文件',
        'interface' => $record->interface_key ?: $record->provider_name,
        'provider' => $record->provider_name,
        'storage_engine' => $record->storage_engine,
        'purpose' => $record->purpose,
        'file_size' => (int) $record->file_size,
        'created_at' => $record->created_at,
        'url' => $file_url,
    );

    if ($file_url !== '' && in_array($record->purpose, array('image_video', 'image'), true)) {
        $payload['image_url'] = $file_url;
    }

    return $payload;
}

function deepseek_file_record_is_compatible_with_chat_model($record, $interface, $model) {
    if (!$record || $record->status !== 'active') {
        return false;
    }

    $interface = sanitize_key((string) $interface);
    $model = sanitize_text_field((string) $model);

    $qwen_video_models = function_exists('deepseek_get_csv_setting_list')
        ? deepseek_get_csv_setting_list('qwen_video_model', 'wanx2.1-t2v-turbo')
        : array('wanx2.1-t2v-turbo');

    if ($interface === 'qwen' && in_array($model, $qwen_video_models, true)) {
        $payload = deepseek_normalize_file_record_for_chat($record);
        return is_array($payload) && !empty($payload['image_url']);
    }

    $support_doc_models = array(
        'kimi' => function_exists('deepseek_get_csv_setting_list') ? deepseek_get_csv_setting_list('kimi_model', '') : array(),
        'openai' => function_exists('deepseek_get_csv_setting_list') ? deepseek_get_csv_setting_list('openai_model', '') : array(),
        'qwen' => array('qwen-long'),
    );

    if (!isset($support_doc_models[$interface]) || !in_array($model, $support_doc_models[$interface], true)) {
        return false;
    }

    return $record->provider_file_id !== ''
        && $record->storage_engine === 'remote_provider'
        && ($record->interface_key === $interface || $record->provider_name === $interface);
}

function deepseek_build_file_records_query($args = array(), $count_only = false) {
    global $wpdb;

    $defaults = array(
        'record_source' => '',
        'user_id' => null,
        'guest_device_hash' => '',
        'status' => '',
        'interface_key' => '',
        'provider_name' => '',
        'storage_engine' => '',
        'purpose' => '',
        'search' => '',
        'older_than_days' => 0,
        'limit' => 20,
        'offset' => 0,
        'orderby' => 'created_at',
        'order' => 'DESC',
    );
    $args = wp_parse_args($args, $defaults);

    $where = array('1=1');
    $params = array();

    if ($args['record_source'] !== '') {
        $where[] = 'record_source = %s';
        $params[] = sanitize_key($args['record_source']);
    }

    if ($args['status'] !== '') {
        $where[] = 'status = %s';
        $params[] = sanitize_key($args['status']);
    }

    if ($args['interface_key'] !== '') {
        $where[] = 'interface_key = %s';
        $params[] = sanitize_key($args['interface_key']);
    }

    if ($args['provider_name'] !== '') {
        $where[] = 'provider_name = %s';
        $params[] = sanitize_key($args['provider_name']);
    }

    if ($args['storage_engine'] !== '') {
        $where[] = 'storage_engine = %s';
        $params[] = sanitize_key($args['storage_engine']);
    }

    if ($args['purpose'] !== '') {
        $where[] = 'purpose = %s';
        $params[] = sanitize_key($args['purpose']);
    }

    if ($args['user_id'] !== null) {
        $where[] = 'user_id = %d';
        $params[] = intval($args['user_id']);
    }

    if ($args['guest_device_hash'] !== '') {
        $where[] = 'guest_device_hash = %s';
        $params[] = sanitize_text_field($args['guest_device_hash']);
    }

    if ($args['search'] !== '') {
        $where[] = '(original_filename LIKE %s OR provider_file_id LIKE %s)';
        $search_like = '%' . $wpdb->esc_like(sanitize_text_field($args['search'])) . '%';
        $params[] = $search_like;
        $params[] = $search_like;
    }

    if (intval($args['older_than_days']) > 0) {
        $where[] = 'created_at < %s';
        $params[] = date('Y-m-d H:i:s', current_time('timestamp') - (intval($args['older_than_days']) * DAY_IN_SECONDS));
    }

    $table_name = deepseek_get_file_records_table_name();
    $sql = ($count_only ? 'SELECT COUNT(*)' : 'SELECT *') . " FROM {$table_name} WHERE " . implode(' AND ', $where);

    if (!$count_only) {
        $allowed_orderby = array('id', 'created_at', 'updated_at', 'file_size', 'original_filename', 'status');
        $orderby = in_array($args['orderby'], $allowed_orderby, true) ? $args['orderby'] : 'created_at';
        $order = strtoupper((string) $args['order']) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
        $params[] = max(1, intval($args['limit']));
        $params[] = max(0, intval($args['offset']));
    }

    return array($sql, $params);
}

function deepseek_get_file_records($args = array()) {
    global $wpdb;

    list($sql, $params) = deepseek_build_file_records_query($args, false);
    return $wpdb->get_results($wpdb->prepare($sql, $params));
}

function deepseek_count_file_records($args = array()) {
    global $wpdb;

    list($sql, $params) = deepseek_build_file_records_query($args, true);
    if (empty($params)) {
        return intval($wpdb->get_var($sql));
    }

    return intval($wpdb->get_var($wpdb->prepare($sql, $params)));
}

function deepseek_get_current_actor_file_records($args = array()) {
    $current_user_id = get_current_user_id();

    if ($current_user_id > 0) {
        $args['user_id'] = $current_user_id;
    } else {
        $guest_device_hash = deepseek_get_request_device_hash();
        if ($guest_device_hash === '') {
            return array();
        }
        $args['user_id'] = 0;
        $args['guest_device_hash'] = $guest_device_hash;
    }

    return deepseek_get_file_records($args);
}

function deepseek_get_recent_file_records($limit = 20) {
    return deepseek_get_file_records(array(
        'limit' => $limit,
    ));
}

function deepseek_delete_file_record($record_id) {
    global $wpdb;

    $record_id = absint($record_id);
    if (!$record_id) {
        return false;
    }

    return false !== $wpdb->delete(
        deepseek_get_file_records_table_name(),
        array('id' => $record_id),
        array('%d')
    );
}

function deepseek_mark_orphan_file_records() {
    global $wpdb;

    $records = deepseek_get_file_records(array(
        'storage_engine' => 'wp_media',
        'status' => 'active',
        'limit' => 500,
    ));
    $marked = 0;

    foreach ($records as $record) {
        if (!empty($record->attachment_id) && !get_post((int) $record->attachment_id)) {
            if (deepseek_update_file_record_status((int) $record->id, 'missing')) {
                $marked++;
            }
        }
    }

    return $marked;
}

function deepseek_mark_expired_remote_file_records($older_than_days = 30) {
    global $wpdb;

    $older_than_days = max(1, intval($older_than_days));
    $cutoff = date('Y-m-d H:i:s', current_time('timestamp') - ($older_than_days * DAY_IN_SECONDS));

    $updated = $wpdb->query($wpdb->prepare(
        "UPDATE " . deepseek_get_file_records_table_name() . " SET status = %s, updated_at = %s WHERE storage_engine = %s AND status = %s AND created_at < %s",
        'expired',
        current_time('mysql'),
        'remote_provider',
        'active',
        $cutoff
    ));

    return false === $updated ? 0 : intval($updated);
}

function deepseek_cleanup_file_records($args = array()) {
    global $wpdb;

    $defaults = array(
        'older_than_days' => 30,
        'statuses' => array('deleted', 'missing', 'expired'),
        'limit' => 500,
    );
    $args = wp_parse_args($args, $defaults);

    $statuses = array_map('sanitize_key', (array) $args['statuses']);
    $statuses = array_values(array_filter($statuses));
    if (empty($statuses)) {
        return 0;
    }

    $older_than_days = max(1, intval($args['older_than_days']));
    $cutoff = date('Y-m-d H:i:s', current_time('timestamp') - ($older_than_days * DAY_IN_SECONDS));
    $placeholders = implode(',', array_fill(0, count($statuses), '%s'));
    $limit = max(1, intval($args['limit']));

    $params = array_merge($statuses, array($cutoff, $limit));
    $sql = "DELETE FROM " . deepseek_get_file_records_table_name() . " WHERE status IN ({$placeholders}) AND created_at < %s LIMIT %d";
    $deleted = $wpdb->query($wpdb->prepare($sql, $params));

    return false === $deleted ? 0 : intval($deleted);
}

function deepseek_cleanup_storage_schema() {
    global $wpdb;

    $wpdb->query('DROP TABLE IF EXISTS ' . deepseek_get_file_records_table_name());
    delete_option('deepseek_storage_schema_version');
}

deepseek_bootstrap_storage_schema();
register_activation_hook(DEEPSEEK_PLUGIN_FILE, 'deepseek_activate_storage_schema');

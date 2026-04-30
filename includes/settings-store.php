<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!defined('DEEPSEEK_SETTINGS_DB_VERSION')) {
    define('DEEPSEEK_SETTINGS_DB_VERSION', '1.0.2');
}

function deepseek_get_settings_table_name() {
    global $wpdb;
    return $wpdb->prefix . 'deepseek_settings';
}

function deepseek_get_managed_option_names() {
    static $options = null;

    if (null !== $options) {
        return $options;
    }

    $options = array(
        'agent_file_formats',
        'agent_file_max_size',
        'ai_helper_background',
        'ai_helper_bottom',
        'ai_helper_icon',
        'ai_helper_name',
        'ai_helper_right',
        'ai_tutorial_title',
        'ai_tutorial_url',
        'ali_agent_api_key',
        'allowed_file_types',
        'article_analysis_interface',
        'chat_interface_choice',
        'chat_interfaces',
        'claude_api_key',
        'claude_model',
        'coze_access_token',
        'coze_access_token_expiry',
        'custom_api_key',
        'custom_entry_title',
        'custom_entry_url',
        'custom_model_params',
        'custom_model_url',
        'deepseek_agents',
        'deepseek_announcement',
        'deepseek_api_key',
        'deepseek_context_memory_limit',
        'deepseek_custom_prompts',
        'deepseek_guest_chat_limit',
        'deepseek_guest_upload_limit',
        'deepseek_kb_chunk_size',
        'deepseek_kb_enabled',
        'deepseek_kb_interfaces',
        'deepseek_kb_page_ids',
        'deepseek_kb_post_types',
        'deepseek_kb_result_limit',
        'deepseek_kb_source_types',
        'deepseek_login_prompt',
        'deepseek_model',
        'deepseek_vip_check_enabled',
        'deepseek_vip_keyword',
        'deepseek_vip_prompt_page',
        'default_chat_interface',
        'docmee_api_key',
        'docmee_container_width',
        'docmee_ppt_height',
        'docmee_token_limit',
        'docmee_vip_check_enabled',
        'docmee_vip_keyword',
        'docmee_vip_prompt_page',
        'doubao_api_key',
        'doubao_model',
        'enable_ai_summary',
        'enable_ai_voice_reading',
        'enable_article_analysis',
        'enable_custom_entry',
        'enable_file_upload',
        'enable_intelligent_agent',
        'enable_keyword_detection',
        'gemini_api_key',
        'gemini_model',
        'grok_api_key',
        'grok_model',
        'hunyuan_api_key',
        'hunyuan_model',
        'keyword_list',
        'kimi_api_key',
        'kimi_model',
        'max_file_size',
        'ollama_api_url',
        'ollama_model',
        'openrouter_api_key',
        'openrouter_model',
        'openai_api_key',
        'openai_model',
        'pollinations_model',
        'qianfan_api_key',
        'qianfan_model',
        'qwen_api_key',
        'qwen_enable_search',
        'qwen_image_model',
        'qwen_text_model',
        'qwen_video_model',
        'show_ai_helper',
        'show_interface_switch',
        'siliconflow_api_key',
        'siliconflow_model',
        'summary_interface_choice',
        'volc_agent_api_key',
        'wpatai_settings',
        'mistral_api_key',
        'mistral_model',
        'xunfei_api_key',
        'xunfei_model',
    );

    sort($options);

    return $options;
}

function deepseek_is_managed_option($option_name) {
    return in_array($option_name, deepseek_get_managed_option_names(), true);
}

function deepseek_get_settings_map($force_refresh = false) {
    static $settings_map = null;

    if (!$force_refresh && null !== $settings_map) {
        return $settings_map;
    }

    global $wpdb;

    $table_name = deepseek_get_settings_table_name();
    $settings_map = array();

    $should_probe_table = $force_refresh || get_option('deepseek_settings_db_version', '') !== DEEPSEEK_SETTINGS_DB_VERSION;
    if ($should_probe_table && !deepseek_settings_table_exists()) {
        return $settings_map;
    }

    $rows = $wpdb->get_results("SELECT option_name, option_value FROM {$table_name}", ARRAY_A);
    if (empty($rows)) {
        return $settings_map;
    }

    foreach ($rows as $row) {
        if (!isset($row['option_name'])) {
            continue;
        }
        $settings_map[$row['option_name']] = maybe_unserialize($row['option_value']);
    }

    return $settings_map;
}

function deepseek_settings_table_exists() {
    global $wpdb;

    $table_name = deepseek_get_settings_table_name();
    $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name));

    return $table_exists === $table_name;
}

function deepseek_get_setting_from_table($option_name, &$found = false) {
    $settings_map = deepseek_get_settings_map();
    if (array_key_exists($option_name, $settings_map)) {
        $found = true;
        return $settings_map[$option_name];
    }

    $found = false;
    return null;
}

function deepseek_set_setting_in_table($option_name, $value) {
    global $wpdb;

    if (!deepseek_is_managed_option($option_name)) {
        return false;
    }

    $table_name = deepseek_get_settings_table_name();
    $data = array(
        'option_name'  => $option_name,
        'option_value' => maybe_serialize($value),
        'updated_at'   => current_time('mysql'),
    );

    $result = $wpdb->replace(
        $table_name,
        $data,
        array('%s', '%s', '%s')
    );

    if (false !== $result) {
        deepseek_get_settings_map(true);
    }

    return false !== $result;
}

function deepseek_mark_shadow_option_nonautoload($option_name) {
    global $wpdb;

    if (!deepseek_is_managed_option($option_name)) {
        return;
    }

    if (function_exists('wp_set_option_autoload_values')) {
        wp_set_option_autoload_values(array($option_name => false));
        return;
    }

    $wpdb->update(
        $wpdb->options,
        array('autoload' => 'no'),
        array('option_name' => $option_name),
        array('%s'),
        array('%s')
    );
}

function deepseek_create_settings_table() {
    global $wpdb;

    $table_name = deepseek_get_settings_table_name();
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE {$table_name} (
        option_name varchar(191) NOT NULL,
        option_value longtext NULL,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (option_name)
    ) {$charset_collate};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}

function deepseek_migrate_managed_options_to_table() {
    global $wpdb;

    $option_names = deepseek_get_managed_option_names();
    if (empty($option_names)) {
        return;
    }

    $table_name = deepseek_get_settings_table_name();
    $existing_map = deepseek_get_settings_map(true);

    $placeholders = implode(', ', array_fill(0, count($option_names), '%s'));
    $sql = $wpdb->prepare(
        "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name IN ({$placeholders})",
        $option_names
    );
    $rows = $wpdb->get_results($sql, ARRAY_A);

    if (empty($rows)) {
        return;
    }

    foreach ($rows as $row) {
        if (empty($row['option_name']) || array_key_exists($row['option_name'], $existing_map)) {
            continue;
        }

        $wpdb->insert(
            $table_name,
            array(
                'option_name'  => $row['option_name'],
                'option_value' => $row['option_value'],
                'updated_at'   => current_time('mysql'),
            ),
            array('%s', '%s', '%s')
        );
    }

    foreach ($option_names as $option_name) {
        deepseek_mark_shadow_option_nonautoload($option_name);
    }

    deepseek_get_settings_map(true);
}

function deepseek_restore_shadow_options_from_table() {
    global $wpdb;

    $settings_map = deepseek_get_settings_map(true);
    if (empty($settings_map)) {
        return;
    }

    $option_names = array_keys($settings_map);
    $placeholders = implode(', ', array_fill(0, count($option_names), '%s'));
    $sql = $wpdb->prepare(
        "SELECT option_name FROM {$wpdb->options} WHERE option_name IN ({$placeholders})",
        $option_names
    );
    $existing_options = $wpdb->get_col($sql);
    $existing_options = is_array($existing_options) ? $existing_options : array();

    foreach ($settings_map as $option_name => $value) {
        if (in_array($option_name, $existing_options, true)) {
            continue;
        }

        $wpdb->insert(
            $wpdb->options,
            array(
                'option_name'  => $option_name,
                'option_value' => maybe_serialize($value),
                'autoload'     => 'no',
            ),
            array('%s', '%s', '%s')
        );
    }

    foreach ($option_names as $option_name) {
        deepseek_mark_shadow_option_nonautoload($option_name);
    }
}

function deepseek_bootstrap_settings_store($verify_table = false) {
    static $bootstrapped = false;

    if ($bootstrapped && !$verify_table) {
        return;
    }

    $bootstrapped = true;

    $stored_version = get_option('deepseek_settings_db_version', '');
    $needs_schema = DEEPSEEK_SETTINGS_DB_VERSION !== $stored_version;
    if (!$needs_schema && $verify_table) {
        $needs_schema = !deepseek_settings_table_exists();
    }

    if ($needs_schema) {
        deepseek_create_settings_table();
        deepseek_migrate_managed_options_to_table();
        deepseek_restore_shadow_options_from_table();
        update_option('deepseek_settings_db_version', DEEPSEEK_SETTINGS_DB_VERSION, false);
    }
}

function deepseek_pre_option_from_settings_table($pre_option, $option, $default_value) {
    $option_name = str_replace('pre_option_', '', current_filter());
    $found = false;
    $value = deepseek_get_setting_from_table($option_name, $found);

    if ($found) {
        return $value;
    }

    return false;
}

function deepseek_sync_added_option_to_settings($option_name, $value) {
    if (!deepseek_is_managed_option($option_name)) {
        return;
    }

    deepseek_set_setting_in_table($option_name, $value);
    deepseek_mark_shadow_option_nonautoload($option_name);
}

function deepseek_sync_updated_option_to_settings($option_name, $old_value, $value) {
    if (!deepseek_is_managed_option($option_name)) {
        return;
    }

    deepseek_set_setting_in_table($option_name, $value);
    deepseek_mark_shadow_option_nonautoload($option_name);
}

function deepseek_activate_settings_store() {
    deepseek_bootstrap_settings_store();
}

function deepseek_cleanup_managed_settings_storage() {
    global $wpdb;

    $table_name = deepseek_get_settings_table_name();
    $wpdb->query("DROP TABLE IF EXISTS {$table_name}");

    foreach (deepseek_get_managed_option_names() as $option_name) {
        delete_option($option_name);
    }

    delete_option('deepseek_settings_db_version');
}

deepseek_bootstrap_settings_store();

foreach (deepseek_get_managed_option_names() as $option_name) {
    add_filter("pre_option_{$option_name}", 'deepseek_pre_option_from_settings_table', 10, 3);
}

add_action('added_option', 'deepseek_sync_added_option_to_settings', 10, 2);
add_action('updated_option', 'deepseek_sync_updated_option_to_settings', 10, 3);
register_activation_hook(DEEPSEEK_PLUGIN_FILE, 'deepseek_activate_settings_store');

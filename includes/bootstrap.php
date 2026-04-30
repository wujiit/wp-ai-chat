<?php
if (!defined('ABSPATH')) {
    exit;
}

require_once DEEPSEEK_PLUGIN_DIR . 'includes/settings-store.php';
require_once DEEPSEEK_PLUGIN_DIR . 'includes/storage-schema.php';
require_once DEEPSEEK_PLUGIN_DIR . 'includes/runtime-helpers.php';
require_once DEEPSEEK_PLUGIN_DIR . 'includes/knowledge-base.php';
require_once DEEPSEEK_PLUGIN_DIR . 'includes/prompt-library.php';
require_once DEEPSEEK_PLUGIN_DIR . 'includes/usage-analytics.php';
require_once DEEPSEEK_PLUGIN_DIR . 'includes/conversation-tools.php';

require_once DEEPSEEK_PLUGIN_DIR . 'wpaitranslate.php';
require_once DEEPSEEK_PLUGIN_DIR . 'wpaippt.php';
require_once DEEPSEEK_PLUGIN_DIR . 'wpaidashscope.php';
require_once DEEPSEEK_PLUGIN_DIR . 'wpaifiles.php';

require_once DEEPSEEK_PLUGIN_DIR . 'includes/core.php';
require_once DEEPSEEK_PLUGIN_DIR . 'includes/chat-core.php';
require_once DEEPSEEK_PLUGIN_DIR . 'includes/content-tools.php';

function deepseek_register_rest_isolation_default_rule($rules) {
    $rules = is_array($rules) ? $rules : array();
    $plugin_basename = plugin_basename(DEEPSEEK_PLUGIN_FILE);
    $default_rules = array(
        '/deepseek/v1/ => ' . $plugin_basename,
    );

    foreach ($default_rules as $rule) {
        if (!in_array($rule, $rules, true)) {
            $rules[] = $rule;
        }
    }

    return $rules;
}
add_filter('qs_rest_plugin_isolation_default_rule_lines', 'deepseek_register_rest_isolation_default_rule');

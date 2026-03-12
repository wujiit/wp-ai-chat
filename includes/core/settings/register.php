<?php
// Backward-compatible loader: register logic is grouped by option registration and field binding.
require_once __DIR__ . '/register/options.php';
require_once __DIR__ . '/register/fields.php';

function deepseek_register_settings() {
    deepseek_register_setting_options();
    deepseek_register_setting_fields();
}
add_action('admin_init', 'deepseek_register_settings');

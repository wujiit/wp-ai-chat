<?php
// Backward-compatible loader: admin settings pages are defined in core/settings/pages.php.
if (!function_exists('deepseek_render_settings_page')) {
    require_once __DIR__ . '/settings/pages.php';
}

<?php
if (!defined('ABSPATH')) {
    exit;
}

// Backward-compatible loader: REST chat logic is split into smaller modules.
require_once __DIR__ . '/rest/files.php';
require_once __DIR__ . '/rest/send-message.php';
require_once __DIR__ . '/rest/routes.php';

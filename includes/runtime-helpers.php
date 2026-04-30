<?php
if (!defined('ABSPATH')) {
    exit;
}

// Backward-compatible loader: runtime helpers are split by responsibility.
require_once __DIR__ . '/runtime/assets-http.php';
require_once __DIR__ . '/runtime/settings.php';
require_once __DIR__ . '/runtime/request-context.php';
require_once __DIR__ . '/runtime/chat-storage.php';
require_once __DIR__ . '/runtime/agent-storage.php';

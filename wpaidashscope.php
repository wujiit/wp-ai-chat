<?php
if (!defined('ABSPATH')) {
    exit;
}

// Backward-compatible loader: agent logic is split into chat, admin, and ajax modules.
require_once __DIR__ . '/includes/agents/agent-chat.php';
require_once __DIR__ . '/includes/agents/agent-admin.php';
require_once __DIR__ . '/includes/agents/agent-ajax.php';

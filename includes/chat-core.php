<?php
// Backward-compatible loader: chat logic has been split into dedicated modules.
require_once __DIR__ . '/chat/chat-ui.php';
require_once __DIR__ . '/chat/chat-rest.php';
require_once __DIR__ . '/chat/chat-ajax.php';

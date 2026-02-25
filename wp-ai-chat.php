<?php
/*
Plugin Name: 启灵Ai助手
Description: 启灵Ai助手插件，支持对话聊天、文章生成、文章总结、AI生成PPT，可对接 DeepSeek、通义千问、豆包等模型以及智能体应用。
Plugin URI: https://www.jingxialai.com/4827.html
Version: 4.0.5
Author: Summer
License: GPL License
Author URI: https://www.jingxialai.com/
*/

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('DEEPSEEK_PLUGIN_FILE')) {
    define('DEEPSEEK_PLUGIN_FILE', __FILE__);
}

if (!defined('DEEPSEEK_PLUGIN_DIR')) {
    define('DEEPSEEK_PLUGIN_DIR', plugin_dir_path(DEEPSEEK_PLUGIN_FILE));
}

if (!defined('DEEPSEEK_PLUGIN_URL')) {
    define('DEEPSEEK_PLUGIN_URL', plugin_dir_url(DEEPSEEK_PLUGIN_FILE));
}

require_once DEEPSEEK_PLUGIN_DIR . 'wpaitranslate.php';
require_once DEEPSEEK_PLUGIN_DIR . 'wpaippt.php';
require_once DEEPSEEK_PLUGIN_DIR . 'wpaidashscope.php';
require_once DEEPSEEK_PLUGIN_DIR . 'wpaifiles.php';

require_once DEEPSEEK_PLUGIN_DIR . 'includes/core.php';
require_once DEEPSEEK_PLUGIN_DIR . 'includes/chat-core.php';
require_once DEEPSEEK_PLUGIN_DIR . 'includes/content-tools.php';

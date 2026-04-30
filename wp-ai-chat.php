<?php
/**
 * Plugin Name: 启灵Ai助手
 * Description: 启灵Ai助手插件，支持对话聊天、会话管理、提示词库、知识库、使用统计、文章生成、文章总结、AI生成PPT，可对接 DeepSeek、通义千问、硅基流动、OpenRouter、Mistral 等模型以及智能体应用。
 * Plugin URI: https://www.jingxialai.com
 * Version: 4.0.8
 * Author: Summer
 * License: GPL License
 * Author URI: https://www.jingxialai.com
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('DEEPSEEK_PLUGIN_FILE')) {
    define('DEEPSEEK_PLUGIN_FILE', __FILE__);
}

if (!defined('DEEPSEEK_PLUGIN_VERSION')) {
    define('DEEPSEEK_PLUGIN_VERSION', '4.0.8');
}

if (!defined('DEEPSEEK_PLUGIN_DIR')) {
    define('DEEPSEEK_PLUGIN_DIR', plugin_dir_path(DEEPSEEK_PLUGIN_FILE));
}

if (!defined('DEEPSEEK_PLUGIN_URL')) {
    define('DEEPSEEK_PLUGIN_URL', plugin_dir_url(DEEPSEEK_PLUGIN_FILE));
}

require_once DEEPSEEK_PLUGIN_DIR . 'includes/bootstrap.php';

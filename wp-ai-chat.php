<?php
/*
Plugin Name: 小半WordPress ai助手
Description: WordPress Ai助手插件，支持对话聊天、文章生成、文章总结、ai生成PPT，可对接deepseek、通义千问、豆包等模型以及智能体应用。
Plugin URI: https://www.jingxialai.com/4827.html
Version: 4.0.2
Author: Summer
License: GPL License
Author URI: https://www.jingxialai.com/
*/

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

// 创建数据表
function deepseek_create_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'deepseek_chat_logs';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        user_id mediumint(9) NOT NULL,
        conversation_id mediumint(9) NOT NULL,
        conversation_title text NOT NULL,
        message text NOT NULL,
        response text NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'deepseek_create_table');

// 创建智能体对话记录表
function deepseek_create_agent_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'deepseek_agent_chat_logs';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        user_id mediumint(9) NOT NULL,
        app_id varchar(255) NOT NULL,
        message text NOT NULL,
        response text NOT NULL,
        session_id varchar(255) DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'deepseek_create_agent_table');

require_once plugin_dir_path(__FILE__) . 'wpaitranslate.php';
require_once plugin_dir_path(__FILE__) . 'wpaippt.php';
require_once plugin_dir_path(__FILE__) . 'wpaidashscope.php';
require_once plugin_dir_path(__FILE__) . 'wpaifiles.php';

// 插件列表页面添加设置入口
function deepseek_add_settings_link($links) {
    $settings_link = '<a href="admin.php?page=deepseek">设置</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'deepseek_add_settings_link');

// 注册激活钩子，确保插件启用时会调用子文件中的函数
register_activation_hook(__FILE__, 'deepseek_create_ppt_page');

// 创建对话页面
function deepseek_create_chat_page() {
    // 查询是否已有包含短代码 [deepseek_chat] 的页面
    $pages = get_posts(array(
        'post_type'   => 'page', // 只查询页面
        'post_status' => 'publish', // 只查询已发布的页面
        's'           => '[deepseek_chat]', // 搜索包含短代码的内容
        'numberposts' => 1, // 只获取一个结果
    ));

    // 如果没有找到包含短代码的页面
    if (empty($pages)) {
        // 创建页面
        $page_id = wp_insert_post(array(
            'post_title'    => 'Ai小助手',
            'post_content'  => '[deepseek_chat]',
            'post_status'   => 'publish',
            'post_author'   => 1,
            'post_type'     => 'page',
        ));
    }
}
register_activation_hook(__FILE__, 'deepseek_create_chat_page');

// 添加菜单入口
function deepseek_add_menu() {
    // 主菜单项
    add_menu_page(
        '小半Ai助手', // 页面标题
        '小半Ai助手', // 菜单标题
        'manage_options',
        'deepseek', // 菜单slug
        'deepseek_render_settings_page', // 默认加载设置页面
        'dashicons-format-chat', // 图标
        6 // 菜单位置
    );
    // 子菜单项 - 设置
    add_submenu_page(
        'deepseek', // 父菜单slug
        'AI参数设置', // 页面标题
        '对话设置', // 菜单标题
        'manage_options',
        'deepseek', // 菜单slug和主菜单一致
        'deepseek_render_settings_page' // 指向设置页面的回调函数
    );
    // 子菜单项 - 对话记录
    add_submenu_page(
        'deepseek',
        'Ai对话记录',
        '对话记录',
        'manage_options',
        'deepseek-logs',
        'deepseek_render_logs_page' // 对话记录页面的回调函数
    );
    // 子菜单项 - 文章生成
    add_submenu_page(
        'deepseek',
        '文章生成',
        '文章生成',
        'manage_options',
        'deepseek-article-generator',
        'deepseek_render_article_generator_page' // 文章生成页面回调函数
    );
    // 子菜单项 - 翻译语音
    add_submenu_page(
        'deepseek',
        '翻译语音',
        '翻译语音',
        'manage_options',
        'deepseek-translate',
        'wpatai_settings_page' // 翻译页面回调函数
    );
    // 子菜单项 - PPT生成
    add_submenu_page(
        'deepseek',
        'PPT生成',
        'PPT生成',
        'manage_options',
        'deepseek-aippt',
        'wpaippt_settings_page' // PPT生成页面回调函数
    );
    // 子菜单 - 智能体应用管理    
    add_submenu_page(
        'deepseek',
        '智能体应用管理',
        '智能体应用',
        'manage_options',
        'deepseek-agents',
        'deepseek_render_agents_page' // 智能体应用页面回调函数
    );
    // 子菜单 - 智能体应用对话记录管理
    add_submenu_page(
        'deepseek',
        '智能体应用对话记录',
        '智能体记录',
        'manage_options',
        'deepseek-agent-logs',
        'deepseek_render_agent_logs_page' // 智能体应用对话记录页面回调函数
    );
    // 子菜单 - 文件列表管理
    add_submenu_page(
        'deepseek',
        '文件列表管理',
        '文件列表',
        'manage_options',
        'deepseek-files',
        'deepseek_render_files_page'
    );
    
}
add_action('admin_menu', 'deepseek_add_menu');

// 注册设置
function deepseek_register_settings() {
    register_setting('deepseek_chat_options_group', 'deepseek_api_key'); // DeepSeek API Key
    register_setting('deepseek_chat_options_group', 'deepseek_model', array('sanitize_callback' => 'sanitize_text_field')); // DeepSeek 模型参数
    register_setting('deepseek_chat_options_group', 'doubao_api_key'); // 豆包AI API Key
    register_setting('deepseek_chat_options_group', 'doubao_model', array('sanitize_callback' => 'sanitize_text_field')); // 豆包AI 模型参数
    register_setting('deepseek_chat_options_group', 'kimi_api_key'); // kimi AI API Key
    register_setting('deepseek_chat_options_group', 'kimi_model', array('sanitize_callback' => 'sanitize_text_field')); // kimi AI 模型参数
    register_setting('deepseek_chat_options_group', 'openai_api_key'); // openai API Key
    register_setting('deepseek_chat_options_group', 'openai_model', array('sanitize_callback' => 'sanitize_text_field')); // openai 模型参数
    register_setting('deepseek_chat_options_group', 'grok_api_key'); // grok API Key
    register_setting('deepseek_chat_options_group', 'grok_model', array('sanitize_callback' => 'sanitize_text_field')); // grok 模型参数
    register_setting('deepseek_chat_options_group', 'qianfan_api_key'); // 千帆 API Key
    register_setting('deepseek_chat_options_group', 'qianfan_model', array('sanitize_callback' => 'sanitize_text_field')); // 千帆 模型参数
    register_setting('deepseek_chat_options_group', 'hunyuan_api_key'); // 腾讯混元 API Key
    register_setting('deepseek_chat_options_group', 'hunyuan_model', array('sanitize_callback' => 'sanitize_text_field')); // 腾讯混元 模型参数
    register_setting('deepseek_chat_options_group', 'xunfei_api_key'); // 讯飞星火 API Key
    register_setting('deepseek_chat_options_group', 'xunfei_model', array('sanitize_callback' => 'sanitize_text_field')); // 讯飞星火模型参数   

    // Gemini
    register_setting('deepseek_chat_options_group', 'gemini_api_key');
    register_setting('deepseek_chat_options_group', 'gemini_model', array('sanitize_callback' => 'sanitize_text_field'));

    // Claude
    register_setting('deepseek_chat_options_group', 'claude_api_key');
    register_setting('deepseek_chat_options_group', 'claude_model', array('sanitize_callback' => 'sanitize_text_field'));

    // 通义千问
    register_setting('deepseek_chat_options_group', 'qwen_api_key'); // 通义千问 API Key
    register_setting('deepseek_chat_options_group', 'qwen_text_model', array('sanitize_callback' => 'sanitize_text_field')); // 文本模型
    register_setting('deepseek_chat_options_group', 'qwen_image_model', array('sanitize_callback' => 'sanitize_text_field')); // 图像模型
    register_setting('deepseek_chat_options_group', 'qwen_video_model', array('sanitize_callback' => 'sanitize_text_field')); // 视频模型

    // 自定义模型设置
    register_setting('deepseek_chat_options_group', 'custom_api_key');       // 自定义模型API Key
    register_setting('deepseek_chat_options_group', 'custom_model_params', array('sanitize_callback' => 'sanitize_text_field')); // 自定义模型参数
    register_setting('deepseek_chat_options_group', 'custom_model_url');       // 自定义模型请求 URL

    // Pollinations模型参数设置
    register_setting('deepseek_chat_options_group', 'pollinations_model', array('sanitize_callback' => 'sanitize_text_field'));

    register_setting('deepseek_chat_options_group', 'show_ai_helper'); // ai助手显示
    register_setting('deepseek_chat_options_group', 'enable_ai_summary'); // 文章总结
    register_setting('deepseek_chat_options_group', 'enable_ai_voice_reading'); // AI对话语音朗读
    register_setting('deepseek_chat_options_group', 'deepseek_custom_prompts'); // 自定义提示词
    register_setting('deepseek_chat_options_group', 'ai_tutorial_title'); // AI使用教程标题
    register_setting('deepseek_chat_options_group', 'ai_tutorial_url');   // AI使用教程链接
    register_setting('deepseek_chat_options_group', 'enable_keyword_detection'); // 启用关键词检测
    register_setting('deepseek_chat_options_group', 'keyword_list'); // 违规关键词列表
    register_setting('deepseek_chat_options_group', 'enable_intelligent_agent'); // 启用智能体应用
    register_setting('deepseek_chat_options_group', 'deepseek_login_prompt'); // 未登录提示
    register_setting('deepseek_chat_options_group', 'qwen_enable_search'); // 模型联网搜索

    //自定义按钮位置设置（右边距和底边距）    
    register_setting('deepseek_chat_options_group', 'ai_helper_right');
    register_setting('deepseek_chat_options_group', 'ai_helper_bottom');
    register_setting('deepseek_chat_options_group', 'ai_helper_name'); // 助手名称
    register_setting('deepseek_chat_options_group', 'ai_helper_icon'); // 图标链接
    // 自定义入口相关设置
    register_setting('deepseek_chat_options_group', 'enable_custom_entry');
    register_setting('deepseek_chat_options_group', 'custom_entry_title');
    register_setting('deepseek_chat_options_group', 'custom_entry_url');

    // 文章总结接口选择设置
    register_setting('deepseek_chat_options_group', 'summary_interface_choice');

    // 多选和默认接口设置
    register_setting('deepseek_chat_options_group', 'chat_interfaces', array('default' => array('deepseek'),'sanitize_callback' => 'sanitize_text_field_array'));
    register_setting('deepseek_chat_options_group', 'default_chat_interface', array('default' => 'deepseek','sanitize_callback' => 'sanitize_text_field'));

    // 接口切换开关设置
    register_setting('deepseek_chat_options_group', 'show_interface_switch', array('default' => '0','sanitize_callback' => 'sanitize_text_field'));

    // 添加文件上传相关设置
    register_setting('deepseek_chat_options_group', 'enable_file_upload', array('default' => '0','sanitize_callback' => 'sanitize_text_field'));
    register_setting('deepseek_chat_options_group', 'allowed_file_types', array('default' => 'txt,docx,pdf,xlsx,md','sanitize_callback' => 'sanitize_text_field'));
    register_setting('deepseek_chat_options_group', 'max_file_size', array('default' => '10','sanitize_callback' => 'sanitize_text_field'));

    // 会员设置
    register_setting('deepseek_chat_options_group', 'deepseek_vip_check_enabled', 'intval');
    register_setting('deepseek_chat_options_group', 'deepseek_vip_prompt_page');
    register_setting('deepseek_chat_options_group', 'deepseek_vip_keyword');

    // 底部公告设置
    register_setting('deepseek_chat_options_group', 'deepseek_announcement', array('sanitize_callback' => 'wp_kses_post'));

    add_settings_section('deepseek_main_section', '基础设置', null, 'deepseek-chat');

    // 接口选择和默认接口设置
    add_settings_field('chat_interfaces', '启用的对话接口', 'chat_interfaces_callback', 'deepseek-chat', 'deepseek_main_section');
    add_settings_field('default_chat_interface', '默认对话接口', 'default_chat_interface_callback', 'deepseek-chat', 'deepseek_main_section');

    // DeepSeek配置项
    add_settings_field('deepseek_api_key', 'DeepSeek API Key', 'deepseek_api_key_callback', 'deepseek-chat', 'deepseek_main_section');
    add_settings_field('deepseek_model', 'DeepSeek 模型参数', 'deepseek_model_callback', 'deepseek-chat', 'deepseek_main_section');

    // 豆包AI配置项
    add_settings_field('doubao_api_key', '豆包AI API Key', 'doubao_api_key_callback', 'deepseek-chat', 'deepseek_main_section');
    add_settings_field('doubao_model', '豆包AI 模型参数', 'doubao_model_callback', 'deepseek-chat', 'deepseek_main_section');

    // kimi AI配置项
    add_settings_field('kimi_api_key', 'Kimi API Key', 'kimi_api_key_callback', 'deepseek-chat', 'deepseek_main_section');
    add_settings_field('kimi_model', 'Kimi 模型参数', 'kimi_model_callback', 'deepseek-chat', 'deepseek_main_section');

    // openai AI配置项
    add_settings_field('openai_api_key', 'Openai API Key', 'openai_api_key_callback', 'deepseek-chat', 'deepseek_main_section');
    add_settings_field('openai_model', 'Openai 模型参数', 'openai_model_callback', 'deepseek-chat', 'deepseek_main_section');

    // Grok AI配置项
    add_settings_field('grok_api_key', 'Grok API Key', 'grok_api_key_callback', 'deepseek-chat', 'deepseek_main_section');
    add_settings_field('grok_model', 'Grok 模型参数', 'grok_model_callback', 'deepseek-chat', 'deepseek_main_section');

    // Gemini配置项
    add_settings_field('gemini_api_key', 'Gemini API Key', 'gemini_api_key_callback', 'deepseek-chat', 'deepseek_main_section');
    add_settings_field('gemini_model', 'Gemini 模型参数', 'gemini_model_callback', 'deepseek-chat', 'deepseek_main_section');

    // Claude配置项
    add_settings_field('claude_api_key', 'Claude API Key', 'claude_api_key_callback', 'deepseek-chat', 'deepseek_main_section');
    add_settings_field('claude_model', 'Claude 模型参数', 'claude_model_callback', 'deepseek-chat', 'deepseek_main_section');

    // 千帆 AI配置项
    add_settings_field('qianfan_api_key', '千帆 API Key(文心一言)', 'qianfan_api_key_callback', 'deepseek-chat', 'deepseek_main_section');
    add_settings_field('qianfan_model', '千帆 模型参数(文心一言)', 'qianfan_model_callback', 'deepseek-chat', 'deepseek_main_section');

    // 腾讯混元 AI配置项
    add_settings_field('hunyuan_api_key', '腾讯混元 API Key', 'hunyuan_api_key_callback', 'deepseek-chat', 'deepseek_main_section');
    add_settings_field('hunyuan_model', '腾讯混元 模型参数', 'hunyuan_model_callback', 'deepseek-chat', 'deepseek_main_section');

    // 讯飞 AI配置项
    add_settings_field('xunfei_api_key', '讯飞星火 API Key', 'xunfei_api_key_callback', 'deepseek-chat', 'deepseek_main_section');
    add_settings_field('xunfei_model', '讯飞星火 模型参数', 'xunfei_model_callback', 'deepseek-chat', 'deepseek_main_section');

    // 通义千问配置项
    add_settings_field('qwen_api_key', '通义千问 API Key', 'qwen_api_key_callback', 'deepseek-chat', 'deepseek_main_section');
    add_settings_field('qwen_text_model', '通义千问 文本模型参数', 'qwen_text_model_callback', 'deepseek-chat', 'deepseek_main_section');
    add_settings_field('qwen_image_model', '通义千问 图像模型参数', 'qwen_image_model_callback', 'deepseek-chat', 'deepseek_main_section');
    add_settings_field('qwen_video_model', '通义千问 视频模型参数', 'qwen_video_model_callback', 'deepseek-chat', 'deepseek_main_section');

    // 自定义模型配置项
    add_settings_field('custom_api_key', '自定义模型 API Key', 'custom_api_key_callback', 'deepseek-chat', 'deepseek_main_section');
    add_settings_field('custom_model_params', '自定义模型参数', 'custom_model_params_callback', 'deepseek-chat', 'deepseek_main_section');
    add_settings_field('custom_model_url', '自定义模型请求 URL', 'custom_model_url_callback', 'deepseek-chat', 'deepseek_main_section');

    // Pollinations模型参数字段
    add_settings_field('pollinations_model', 'Pollinations 模型参数', 'pollinations_model_callback', 'deepseek-chat', 'deepseek_main_section');

    // 自定义提示词
    add_settings_field('deepseek_custom_prompts', '自定义提示词', 'deepseek_custom_prompts_callback', 'deepseek-chat', 'deepseek_main_section');

    // AI使用教程标题
    add_settings_field('ai_tutorial_title', '提示词教程标题', 'ai_tutorial_title_callback', 'deepseek-chat', 'deepseek_main_section');

    // AI使用教程链接 
    add_settings_field('ai_tutorial_url', '提示词教程链接', 'ai_tutorial_url_callback', 'deepseek-chat', 'deepseek_main_section');

    // 启用关键词检测
    add_settings_field('enable_keyword_detection', '启用关键词检测', 'enable_keyword_detection_callback', 'deepseek-chat', 'deepseek_main_section');
    // 违规关键词
    add_settings_field('keyword_list', '违规关键词列表', 'keyword_list_callback', 'deepseek-chat', 'deepseek_main_section');

    // ai助手入口
    add_settings_field('show_ai_helper', '网站前台显示AI助手入口', 'show_ai_helper_callback', 'deepseek-chat', 'deepseek_main_section');    

    // 启用智能体应用
    add_settings_field('enable_intelligent_agent', '前台显示智能体应用入口', 'enable_intelligent_agent_callback', 'deepseek-chat', 'deepseek_main_section');

    // AI助手按钮位置设置
    add_settings_field('ai_helper_right', 'AI助手按钮右边距', 'ai_helper_right_callback', 'deepseek-chat', 'deepseek_main_section');
    add_settings_field('ai_helper_bottom', 'AI助手按钮底边距', 'ai_helper_bottom_callback', 'deepseek-chat', 'deepseek_main_section');

    // 助手名称设置
    add_settings_field('ai_helper_name', 'AI助手名称', 'ai_helper_name_callback', 'deepseek-chat', 'deepseek_main_section');
    
    // 助手图标链接设置
    add_settings_field('ai_helper_icon', 'AI助手图标链接', 'ai_helper_icon_callback', 'deepseek-chat', 'deepseek_main_section');    

    // 未登录提示文字
    add_settings_field('deepseek_login_prompt', '未登录提示文字', 'deepseek_login_prompt_callback', 'deepseek-chat', 'deepseek_main_section');

    // 自定义入口设置项
    add_settings_field('enable_custom_entry', '对话页面显示自定义入口', 'enable_custom_entry_callback', 'deepseek-chat', 'deepseek_main_section');
    add_settings_field('custom_entry_title', '自定义入口标题', 'custom_entry_title_callback', 'deepseek-chat', 'deepseek_main_section');
    add_settings_field('custom_entry_url', '自定义入口链接', 'custom_entry_url_callback', 'deepseek-chat', 'deepseek_main_section');  

    // AI对话语音朗读
    add_settings_field('enable_ai_voice_reading', '启用AI对话语音播放', 'enable_ai_voice_reading_callback', 'deepseek-chat', 'deepseek_main_section');

    // 文章总结框
    add_settings_field('enable_ai_summary', '文章AI总结', 'enable_ai_summary_callback', 'deepseek-chat', 'deepseek_main_section');

    // 文章总结接口
    add_settings_field('summary_interface_choice', '文章总结接口', 'summary_interface_choice_callback', 'deepseek-chat', 'deepseek_main_section');

    // 接口切换显示开关
    add_settings_field('show_interface_switch', '前台显示接口切换', 'show_interface_switch_callback', 'deepseek-chat', 'deepseek_main_section');

    // 在线联网搜索
    add_settings_field('qwen_enable_search', '启用在线联网搜索', 'qwen_enable_search_callback', 'deepseek-chat', 'deepseek_main_section');

    // 用户选择接口的处理
    add_action('wp_ajax_deepseek_switch_interface', 'deepseek_handle_interface_switch');

    // 文件上传相关字段
    add_settings_field('enable_file_upload', '启用文件上传', 'enable_file_upload_callback', 'deepseek-chat', 'deepseek_main_section');
    add_settings_field('allowed_file_types', '允许的文件格式', 'allowed_file_types_callback', 'deepseek-chat', 'deepseek_main_section');
    add_settings_field('max_file_size', '最大文件大小(MB)', 'max_file_size_callback', 'deepseek-chat', 'deepseek_main_section');

    // 会员验证相关字段
    add_settings_field('deepseek_vip_check_enabled', '启用网站会员验证', 'deepseek_vip_check_enabled_callback', 'deepseek-chat', 'deepseek_main_section');
    add_settings_field('deepseek_vip_keyword', '会员验证关键词', 'deepseek_vip_keyword_callback', 'deepseek-chat', 'deepseek_main_section');
    add_settings_field('deepseek_vip_prompt_page', '开通会员页面链接', 'deepseek_vip_prompt_page_callback', 'deepseek-chat', 'deepseek_main_section');

    // 公告设置字段
    add_settings_field('deepseek_announcement', '公告说明', 'deepseek_announcement_callback', 'deepseek-chat', 'deepseek_main_section');

    // AJAX处理文件上传
    add_action('wp_ajax_deepseek_upload_file', 'deepseek_handle_file_upload');    
    
}
add_action('admin_init', 'deepseek_register_settings');


// 底部公告说明回调函数
function deepseek_announcement_callback() {
    $announcement = get_option('deepseek_announcement', '');
    ?>
    <textarea name="deepseek_announcement" rows="5" cols="50" style="width: 400px;"><?php echo esc_textarea($announcement); ?></textarea>
    <p class="description">输入公告内容，支持HTML格式，如果留空则前台不显示公告。</p>
    <?php
}

// 启用网站会员验证回调函数
function deepseek_vip_check_enabled_callback() {
    $enabled = get_option('deepseek_vip_check_enabled', '0');
    echo '<input type="checkbox" name="deepseek_vip_check_enabled" value="1" ' . checked(1, $enabled, false) . ' />';
    echo '<span class="description">启用后未开通网站会员的用户将无法使用对话功能</span>';
}
function deepseek_vip_keyword_callback() {
    $keyword = get_option('deepseek_vip_keyword', '升级VIP享受精彩下载');
    echo '<input type="text" name="deepseek_vip_keyword" value="' . esc_attr($keyword) . '" style="width:400px;" />';
    echo '<span class="description">设置会员验证时的提示关键词，需要你网站带会员级别系统，且有关键词</span>';
}
function deepseek_vip_prompt_page_callback() {
    $page_url = get_option('deepseek_vip_prompt_page', '');
    echo '<input type="text" name="deepseek_vip_prompt_page" value="' . esc_attr($page_url) . '" style="width:400px;" />';
    echo '<span class="description">用户点击开通按钮后跳转的页面URL</span>';
}

// 文件上传相关回调函数
function enable_file_upload_callback() {
    $enabled = get_option('enable_file_upload', '0');
    ?>
    <input type="checkbox" name="enable_file_upload" value="1" <?php checked(1, $enabled); ?> />
    <p class="description">启用后，前台底部状态栏将显示文件上传按钮，只支持kimi和通义千问的qwen-long模型，分析用户上传的文档</p>
    <?php
}

function allowed_file_types_callback() {
    $types = get_option('allowed_file_types', 'txt,docx,pdf,xlsx,md');
    ?>
    <input type="text" name="allowed_file_types" value="<?php echo esc_attr($types); ?>" style="width: 300px;" />
    <p class="description">多个格式用英文逗号分隔，例如：txt,docx,pdf,xlsx,md，具体以你选择的模型为准(图片生成视频的文件格式不受这个设置限制)</p>
    <?php
}

function max_file_size_callback() {
    $size = get_option('max_file_size', '100');
    ?>
    <input type="number" name="max_file_size" value="<?php echo esc_attr($size); ?>" min="1" max="500" style="width: 100px;" />
    <p class="description">单位：MB，最大根据你调用的模型确定</p>
    <?php
}

// 接口切换显示开关回调函数
function show_interface_switch_callback() {
    $enabled = get_option('show_interface_switch', '0');
    ?>
    <input type="checkbox" name="show_interface_switch" value="1" <?php checked(1, $enabled); ?> />
    <p class="description">启用后，前台页面底部状态栏将显示接口选择选项，用户可自行切换接口</p>
    <?php
}

// 数组sanitize回调函数
function sanitize_text_field_array($input) {
    if (!is_array($input)) {
        return array();
    }
    return array_map('sanitize_text_field', $input);
}

// 多选接口回调
function chat_interfaces_callback() {
    $options = get_option('chat_interfaces', array('deepseek'));
    $interfaces = array(
        'deepseek' => 'DeepSeek',
        'openai' => 'OpenAI',
        'grok' => 'Grok',
        'gemini' => 'Gemini',
        'claude' => 'Claude',
        'qwen' => '通义千问',
        'kimi' => 'Kimi',
        'doubao' => '豆包AI',
        'qianfan' => '千帆(文心一言)',
        'hunyuan' => '腾讯混元',
        'xunfei' => '讯飞星火',
        'pollinations' => 'Pollinations(文生图)',
        'custom' => '自定义接口'
    );
    ?>
    <select name="chat_interfaces[]" multiple style="height: 190px;">
        <?php foreach ($interfaces as $value => $label): ?>
            <option value="<?php echo esc_attr($value); ?>" <?php echo in_array($value, $options) ? 'selected' : ''; ?>>
                <?php echo esc_html($label); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <p class="description">按住Ctrl或Cmd键可多选启用的接口</p>
    <?php
}

// 对话默认接口回调
function default_chat_interface_callback() {
    $default = get_option('default_chat_interface', 'deepseek');
    $options = get_option('chat_interfaces', array('deepseek'));
    $interfaces = array(
        'deepseek' => 'DeepSeek',
        'openai' => 'OpenAI',
        'grok' => 'Grok',
        'gemini' => 'Gemini',
        'claude' => 'Claude',
        'qwen' => '通义千问',
        'kimi' => 'Kimi',
        'doubao' => '豆包AI',
        'qianfan' => '千帆(文心一言)',
        'hunyuan' => '腾讯混元',
        'xunfei' => '讯飞星火',
        'pollinations' => 'Pollinations(文生图)',
        'custom' => '自定义接口'
    );
    ?>
    <select name="default_chat_interface">
        <?php foreach ($interfaces as $value => $label): ?>
            <?php if (in_array($value, $options)): // 只显示已启用的接口 ?>
                <option value="<?php echo esc_attr($value); ?>" <?php selected($default, $value); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endif; ?>
        <?php endforeach; ?>
    </select>
    <p class="description">选择默认使用的对话接口</p>
    <?php
}

// 文章AI总结复选框回调
function enable_ai_summary_callback() {
    $enable_ai_summary = get_option('enable_ai_summary');
    echo '<input type="checkbox" name="enable_ai_summary" value="1" ' . checked(1, $enable_ai_summary, false) . ' />';
}

// 文章总结接口选择回调函数
function summary_interface_choice_callback() {
    $choice = get_option('summary_interface_choice', 'deepseek'); // 默认选择DeepSeek
    ?>
    <select name="summary_interface_choice">
        <option value="deepseek" <?php selected($choice, 'deepseek'); ?>>DeepSeek</option>
        <option value="kimi" <?php selected($choice, 'kimi'); ?>>Kimi</option>
        <option value="openai" <?php selected($choice, 'openai'); ?>>Openai</option>
        <option value="grok" <?php selected($choice, 'grok'); ?>>Grok</option>          
        <option value="doubao" <?php selected($choice, 'doubao'); ?>>豆包AI</option>
        <option value="qwen" <?php selected($choice, 'qwen'); ?>>通义千问</option>
        <option value="qianfan" <?php selected($choice, 'qianfan'); ?>>千帆(文心一言)</option>
        <option value="hunyuan" <?php selected($choice, 'hunyuan'); ?>>腾讯混元</option>
        <option value="xunfei" <?php selected($choice, 'xunfei'); ?>>讯飞星火</option>
        <option value="custom" <?php selected($choice, 'custom'); ?>>自定义接口</option>
    </select>
    <p class="description">选择用于生成文章总结的AI接口，需要长文本模型，多模型参数默认采用第一个</p>
    <?php
}

// 自定义入口回调函数
function enable_custom_entry_callback() {
    $enabled = get_option('enable_custom_entry', '0');
    echo '<input type="checkbox" name="enable_custom_entry" value="1" ' . checked(1, $enabled, false) . ' />';
}

function custom_entry_title_callback() {
    $title = get_option('custom_entry_title', '');
    echo '<input type="text" name="custom_entry_title" value="' . esc_attr($title) . '" style="width: 300px;" />';
}

function custom_entry_url_callback() {
    $url = get_option('custom_entry_url', '');
    echo '<input type="url" name="custom_entry_url" value="' . esc_attr($url) . '" style="width: 500px;" />';
}

// 未登录提示文字输入框回调函数
function deepseek_login_prompt_callback() {
    $login_prompt = get_option('deepseek_login_prompt', '请先登录才能使用Ai对话功能');
    echo '<input type="text" name="deepseek_login_prompt" value="' . esc_attr($login_prompt) . '" style="width: 500px;" />';
}

// 启用智能体应用回调函数
function enable_intelligent_agent_callback() {
    $enabled = get_option('enable_intelligent_agent', '0');
    echo '<input type="checkbox" name="enable_intelligent_agent" value="1" ' . checked(1, $enabled, false) . ' />';
}

// 启用关键词检测的回调函数
function enable_keyword_detection_callback() {
    $enabled = get_option('enable_keyword_detection', '0');
    echo '<input type="checkbox" name="enable_keyword_detection" value="1" ' . checked(1, $enabled, false) . ' />';
}

// 关键词列表回调函数
function keyword_list_callback() {
    $keywords = get_option('keyword_list', '');
    echo '<textarea name="keyword_list" rows="5" cols="60" placeholder="请输入逗号分隔的关键词">' . esc_textarea($keywords) . '</textarea>';
    echo '<p class="description">请输入需要检测的关键词，多个关键词用英文逗号分隔。</p>';
}

// AI使用教程标题回调函数
function ai_tutorial_title_callback() {
    $title = get_option('ai_tutorial_title', '');
    echo '<input type="text" name="ai_tutorial_title" value="' . esc_attr($title) . '" style="width: 500px;" />';
}
function ai_tutorial_url_callback() {
    $url = get_option('ai_tutorial_url', '');
    echo '<input type="text" name="ai_tutorial_url" value="' . esc_attr($url) . '" style="width: 500px;" />';
}

// 自定义提示词回调函数
function deepseek_custom_prompts_callback() {
    $prompts = get_option('deepseek_custom_prompts', '');
    echo '<textarea name="deepseek_custom_prompts" rows="5" cols="60" placeholder="每行一个提示词">' . esc_textarea($prompts) . '</textarea>';
}

// AI对话语音朗读函数回调
function enable_ai_voice_reading_callback() {
    $checked = get_option('enable_ai_voice_reading', '0');
    echo '<input type="checkbox" name="enable_ai_voice_reading" value="1" ' . checked(1, $checked, false) . ' />';
}


// 助手入口处理函数回调
function show_ai_helper_callback() {
    $checked = get_option('show_ai_helper', '0');
    echo '<input type="checkbox" name="show_ai_helper" value="1" ' . checked(1, $checked, false) . ' />';
}

// 助手名称回调函数
function ai_helper_name_callback() {
    $name = get_option('ai_helper_name', 'AI 助手'); // 默认名称为"AI 助手"
    echo '<input type="text" name="ai_helper_name" value="' . esc_attr($name) . '" style="width:200px;" />';
    echo '<p class="description">输入AI助手的自定义名称</p>';
}

// 图标链接回调函数
function ai_helper_icon_callback() {
    $icon = get_option('ai_helper_icon', ''); // 默认空值
    echo '<input type="text" name="ai_helper_icon" value="' . esc_attr($icon) . '" style="width:300px;" />';
    echo '<p class="description">输入图标图片的URL链接</p>';
}

// AI助手按钮位置右边距回调函数
function ai_helper_right_callback() {
    $right = get_option('ai_helper_right', '5%'); // 默认右边距为5%
    echo '<input type="text" name="ai_helper_right" value="' . esc_attr($right) . '" style="width:100px;" />';
    echo '<p class="description">输入按钮距离右侧的距离，例如：5% 或 20px</p>';
}

// AI助手按钮位置底边距回调函数
function ai_helper_bottom_callback() {
    $bottom = get_option('ai_helper_bottom', '50%'); // 默认底边距为50%
    echo '<input type="text" name="ai_helper_bottom" value="' . esc_attr($bottom) . '" style="width:100px;" />';
    echo '<p class="description">输入按钮距离底部的距离，例如：50% 或 30px</p>';
}

// 在网站前台显示AI助手入口
function deepseek_display_ai_helper() {
    if (get_option('show_ai_helper', '0') == '1' && !is_page_with_deepseek_chat_shortcode()) {
        $ai_helper_right = get_option('ai_helper_right', '5%');
        $ai_helper_bottom = get_option('ai_helper_bottom', '50%');
        $ai_helper_name = get_option('ai_helper_name', 'AI 助手'); // 获取自定义名称
        $ai_helper_icon = get_option('ai_helper_icon', ''); // 获取自定义图标链接

        // 根据是否设置图标链接来决定图标显示方式
        $icon_html = $ai_helper_icon ? 
            '<img src="' . esc_url($ai_helper_icon) . '" style="width: 24px; height: 24px; vertical-align: middle;">' : 
            '<span style="font-size: 24px;">&#129503;</span>';

        echo '<div id="ai-helper-button" style="
            position: fixed;
            right: ' . esc_attr($ai_helper_right) . ';
            bottom: ' . esc_attr($ai_helper_bottom) . ';
            transform: translateY(50%);
            z-index: 9999;
            cursor: pointer;
            font-size: 18px;
            font-weight: bold;
            color: #fff;
            background: linear-gradient(135deg, #6EE7B7, #3B82F6);
            padding: 5px 10px;
            border-radius: 15px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease-in-out;
            display: flex;
            align-items: center;
            gap: 5px;
        ">
            ' . $icon_html . ' ' . esc_html($ai_helper_name) . '
        </div>';

        echo '<script>
            document.getElementById("ai-helper-button").addEventListener("click", function() {
                fetch("' . admin_url('admin-ajax.php') . '?action=get_deepseek_chat_page")
                    .then(response => response.json())
                    .then(data => {
                        window.location.href = data.url;
                    })
                    .catch(() => {
                        window.location.href = "' . home_url() . '";
                    });
            });

            document.getElementById("ai-helper-button").addEventListener("mouseover", function() {
                this.style.transform = "translateY(50%) scale(1.1)";
                this.style.boxShadow = "0 6px 15px rgba(0, 0, 0, 0.3)";
            });

            document.getElementById("ai-helper-button").addEventListener("mouseout", function() {
                this.style.transform = "translateY(50%) scale(1)";
                this.style.boxShadow = "0 4px 10px rgba(0, 0, 0, 0.2)";
            });
        </script>';
    }
}
add_action('wp_footer', 'deepseek_display_ai_helper');


// 检查页面是否包含 [deepseek_chat] 短代码 用于显示ai助手按钮
function is_page_with_deepseek_chat_shortcode() {
    global $post;

    // 如果没有，直接返回false
    if (empty($post) || empty($post->post_content)) {
        return false;
    }

    // 检查页面内容是否包含 [deepseek_chat] 短代码
    return has_shortcode($post->post_content, 'deepseek_chat');
}

// 查找包含 [deepseek_chat] 短代码的页面 用于跳转对话页面
function get_deepseek_chat_page() {
    global $wpdb;
    $page = $wpdb->get_row("
        SELECT ID, post_title 
        FROM $wpdb->posts 
        WHERE post_type = 'page' 
        AND post_status = 'publish' 
        AND post_content LIKE '%[deepseek_chat]%' 
        LIMIT 1
    ");

    if ($page) {
        $url = get_permalink($page->ID);
    } else {
        $url = home_url(); // 默认跳转首页
    }

    wp_send_json(['url' => $url]);
}
add_action('wp_ajax_get_deepseek_chat_page', 'get_deepseek_chat_page');
add_action('wp_ajax_nopriv_get_deepseek_chat_page', 'get_deepseek_chat_page');

// Pollinations模型参数回调函数
function pollinations_model_callback() {
    $model = get_option('pollinations_model', 'flux'); // 默认模型为flux
    echo '<input type="text" name="pollinations_model" value="' . esc_attr($model) . '" style="width: 500px;" />';
    echo '<p class="description">可用模型参考: <a href="https://image.pollinations.ai/models" target="_blank">Pollinations Models</a>，默认: flux，使用Pollinations最好是海外服务器，内地服务器请注意请求时间。</p>';
}

// Gemini 回调函数
function gemini_api_key_callback() {
    $api_key = get_option('gemini_api_key');
    echo '<input type="text" name="gemini_api_key" value="' . esc_attr($api_key) . '" style="width: 500px;" />';
}
function gemini_model_callback() {
    $model = get_option('gemini_model', 'gemini-2.0-flash');
    echo '<input type="text" name="gemini_model" value="' . esc_attr($model) . '" style="width: 500px;" />';
}

// Claude 回调函数
function claude_api_key_callback() {
    $api_key = get_option('claude_api_key');
    echo '<input type="text" name="claude_api_key" value="' . esc_attr($api_key) . '" style="width: 500px;" />';
}
function claude_model_callback() {
    $model = get_option('claude_model', 'claude-3-7-sonnet-20250219');
    echo '<input type="text" name="claude_model" value="' . esc_attr($model) . '" style="width: 500px;" />';
}

// 讯飞回调函数
function xunfei_api_key_callback() {
    $api_key = get_option('xunfei_api_key');
    echo '<input type="text" name="xunfei_api_key" value="' . esc_attr($api_key) . '" style="width: 500px;" />';
}
function xunfei_model_callback() {
    $model = get_option('xunfei_model', 'generalv3.5');
    echo '<input type="text" name="xunfei_model" value="' . esc_attr($model) . '" style="width: 500px;" />';
}

// 通义千问相关回调
function qwen_api_key_callback() {
    $api_key = get_option('qwen_api_key');
    echo '<input type="text" name="qwen_api_key" value="' . esc_attr($api_key) . '" style="width: 500px;" />';
}
function qwen_text_model_callback() {
    $model = get_option('qwen_text_model', 'qwen-max');
    echo '<input type="text" name="qwen_text_model" value="' . esc_attr($model) . '" style="width: 500px;" />';
}
function qwen_image_model_callback() {
    $model = get_option('qwen_image_model', 'wanx2.1-t2i-turbo');
    echo '<input type="text" name="qwen_image_model" value="' . esc_attr($model) . '" style="width: 500px;" />';
}
function qwen_video_model_callback() {
    $model = get_option('qwen_video_model', 'wanx2.1-t2v-turbo');
    echo '<input type="text" name="qwen_video_model" value="' . esc_attr($model) . '" style="width: 500px;" />';
}

function qwen_enable_search_callback() {
    $enabled = get_option('qwen_enable_search', 0);
    ?>
    <input type="checkbox" name="qwen_enable_search" value="1" <?php checked(1, $enabled); ?> />
    <p class="description">仅通义千问qwen-max、qwen-plus、qwen-turbo和讯飞星火Pro、Max、4.0Ultra模型支持（模型的联网搜索和智能体应用的联网搜索不一样）</p>
    <?php
}


// 自定义模型相关回调
function custom_api_key_callback() {
    $api_key = get_option('custom_api_key');
    echo '<input type="text" name="custom_api_key" value="' . esc_attr($api_key) . '" style="width: 500px;" />';
}
function custom_model_params_callback() {
    $params = get_option('custom_model_params');
    echo '<input type="text" name="custom_model_params" value="' . esc_attr($params) . '" style="width: 500px;" />';
}
function custom_model_url_callback() {
    $url = get_option('custom_model_url');
    echo '<input type="text" name="custom_model_url" value="' . esc_attr($url) . '" style="width: 500px;" />';
    echo '<p class="description">需要支持OpenAI Chat Completions接口的格式和请求方式，比如：https://api.openai.com/v1/chat/completions</p>';
}

// 腾讯混元api函数回调
function hunyuan_api_key_callback() {
    $api_key = get_option('hunyuan_api_key');
    echo '<input type="text" name="hunyuan_api_key" value="' . esc_attr($api_key) . '" style="width: 500px;" />';
}

// 腾讯混元参数函数回调
function hunyuan_model_callback() {
    $model = get_option('hunyuan_model');
    echo '<input type="text" name="hunyuan_model" value="' . esc_attr($model) . '" style="width: 500px;" />';
}

// 豆包api函数回调
function doubao_api_key_callback() {
    $api_key = get_option('doubao_api_key');
    echo '<input type="text" name="doubao_api_key" value="' . esc_attr($api_key) . '" style="width: 500px;" />';
}

// 豆包参数函数回调
function doubao_model_callback() {
    $model = get_option('doubao_model');
    echo '<input type="text" name="doubao_model" value="' . esc_attr($model) . '" placeholder="ep-2025*****" style="width: 500px;" />';
    echo '<p class="description">在线推理里面创建的推理接入点，接入点名称下面，有一个：ep- 开头的值</p>';
}

// kimi api函数回调
function kimi_api_key_callback() {
    $api_key = get_option('kimi_api_key');
    echo '<input type="text" name="kimi_api_key" value="' . esc_attr($api_key) . '" style="width: 500px;" />';
}

// kimi 参数函数回调
function kimi_model_callback() {
    $model = get_option('kimi_model');
    echo '<input type="text" name="kimi_model" value="' . esc_attr($model) . '" style="width: 500px;" />';
}

// openai api函数回调
function openai_api_key_callback() {
    $api_key = get_option('openai_api_key');
    echo '<input type="text" name="openai_api_key" value="' . esc_attr($api_key) . '" style="width: 500px;" />';
}

// openai 参数函数回调
function openai_model_callback() {
    $model = get_option('openai_model');
    echo '<input type="text" name="openai_model" value="' . esc_attr($model) . '" style="width: 500px;" />';
}

// Grok api函数回调
function grok_api_key_callback() {
    $api_key = get_option('grok_api_key');
    echo '<input type="text" name="grok_api_key" value="' . esc_attr($api_key) . '" style="width: 500px;" />';
}

// Grok 参数函数回调
function grok_model_callback() {
    $model = get_option('grok_model');
    echo '<input type="text" name="grok_model" value="' . esc_attr($model) . '" style="width: 500px;" />';
}

// 千帆 api函数回调
function qianfan_api_key_callback() {
    $api_key = get_option('qianfan_api_key');
    echo '<input type="text" name="qianfan_api_key" value="' . esc_attr($api_key) . '" style="width: 500px;" />';
}

// 千帆 参数函数回调
function qianfan_model_callback() {
    $model = get_option('qianfan_model');
    echo '<input type="text" name="qianfan_model" value="' . esc_attr($model) . '" style="width: 500px;" />';
}

// deepseek api函数回调
function deepseek_api_key_callback() {
    $api_key = get_option('deepseek_api_key');
    echo '<input type="text" name="deepseek_api_key" value="' . esc_attr($api_key) . '" style="width: 500px;" />';
}

// deepseek模型函数回调
function deepseek_model_callback() {
    $model = get_option('deepseek_model', 'deepseek-chat'); // 默认模型为deepseek-chat
    echo '<input type="text" name="deepseek_model" value="' . esc_attr($model) . '" style="width: 500px;" />';
    echo '<p class="description" style="color: red;">多个参数用英文逗号分隔，第一个为默认参数，其他模型也一样，例如：deepseek-chat,deepseek-coder</p>';    
}

// 获取DeepSeek余额信息
function get_deepseek_balance() {
    $api_key = get_option('deepseek_api_key');
    if (empty($api_key)) {
        return false;
    }

    $response = wp_remote_get('https://api.deepseek.com/user/balance', array(
        'headers' => array(
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $api_key,
        ),
    ));

    if (is_wp_error($response)) {
        return false;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (isset($data['balance_infos'][0]['total_balance'])) {
        return $data['balance_infos'][0]['total_balance'];
    }

    return false;
}

// 设置页面
function deepseek_render_settings_page() {
    $balance = get_deepseek_balance();
    ?>
    <style>
        /* 设置页面整体样式 */
        .ai-wrap {
            margin: 20px auto;
            padding: 20px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .ai-wrap h1 {
            font-size: 24px;
            color: #23282d;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }
        .ai-wrap form {
            margin-top: 20px;
        }
        .ai-wrap th {
            width: 180px;
        }        
        .ai-wrap input[type="text"]
        {
            width: 100%;
            max-width: 500px;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            color: #333;
            background: #fff;
            transition: border-color 0.3s ease;
        }
        .ai-wrap select {
            width: 100%;
            max-width: 500px;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            color: #333;
            background: #f9f9f9;
        }
        .ai-wrap input[type="text"]:focus,
        .ai-wrap select:focus {
            border-color: #0073aa;
            outline: none;
        }
        .ai-wrap input[type="checkbox"] {
            margin-right: 10px;
        }
        .ai-wrap .description {
            font-size: 13px;
            color: #666;
            margin-top: 5px;
        }
        #deepseek-save-success {
            background: #d4edda;
            color: #155724;
            padding: 10px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
            border-radius: 5px;
            text-align: center;
        }
        .ai-wrap div[style*="margin-top: 20px;"] {
            padding: 15px;
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-top: 20px;
            font-size: 14px;
            color: #333;
        }
        .ai-wrap div[style*="color: red;"] {
            color: #dc3545 !important;
            background: #f8d7da;
            border-color: #f5c6cb;
        }
        .ai-wrap .button-primary {
            background: #0073aa;
            border-color: #006799;
            color: #fff;
            padding: 8px 20px;
            font-size: 14px;
            border-radius: 4px;
            transition: background 0.3s ease;
        }
        .ai-wrap .button-primary:hover {
            background: #005177;
            border-color: #004165;
        }
    </style>    
    <div class="ai-wrap">
        <h1>小半Ai助手设置</h1>
        <?php if (isset($_GET['settings-updated']) && $_GET['settings-updated']): ?>
            <div id="deepseek-save-success">保存成功！
            </div>
            <script>
                setTimeout(() => {
                    document.getElementById('deepseek-save-success').style.display = 'none';
                }, 1000);
            </script>
        <?php endif; ?>
        <form method="post" action="options.php">
            <?php
            settings_fields('deepseek_chat_options_group');
            do_settings_sections('deepseek-chat');
            submit_button();
            ?>
        </form>
        <?php if ($balance !== false): ?>
            <div style="margin-top: 20px;">
                <strong>DeepSeek 余额:</strong> <?php echo esc_html($balance); ?> CNY
            </div>
        <?php else: ?>
            <div style="margin-top: 20px; color: red;">
                无法获取DeepSeek余额信息，请检查DeepSeek官方API Key是否正确，如果你不用DeepSeek官方接口就无视。
            </div>
        <?php endif; ?>
       <p> 插件设置说明：<a href="https://www.wujiit.com/wpaidocs" target="_blank">https://www.wujiit.com/wpaidocs</a><br>
        Openai、Gemini、Claude接口只有在官方允许的地区才能访问<br>
    反馈问题请带上错误提示，插件加入了多处的日志调用，方便快速查找问题所在，所以遇到问题了直接把网站错误日志发来。</p>
    </div>
    <?php
}


// 加载前台CSS文件
function deepseek_enqueue_assets() {
    if (is_singular('page')) {
        global $post;
        if (has_shortcode($post->post_content, 'deepseek_chat')) { // 检查是否包含短代码
            wp_enqueue_style('deepseek-chat-style', plugin_dir_url(__FILE__) . 'wpai-style.css');
            wp_enqueue_script('marked-js', plugin_dir_url(__FILE__) . 'marked.min.js', array(), null, true);
            wp_enqueue_script('deepseek-chat-script', plugin_dir_url(__FILE__) . 'wpai-chat.js', array('marked-js'), null, true);

            // 传递PHP变量到JavaScript
            wp_localize_script(
                'deepseek-chat-script',
                'DEEPSEEK_VARS',
                array(
                    'AI_VOICE_ENABLED' => get_option('enable_ai_voice_reading', '0'),
                    'REST_NONCE' => wp_create_nonce('wp_rest'),
                    'REST_URL' => esc_url(rest_url('deepseek/v1/send-message')),
                    'ADMIN_AJAX_URL' => admin_url('admin-ajax.php'),
                    'ENABLE_KEYWORD_DETECTION' => get_option('enable_keyword_detection', '0'),
                    'KEYWORDS' => get_option('keyword_list', ''),
                    'FILE_UPLOAD_NONCE' => wp_create_nonce('file_upload_action'),
                    'AGENT_FILE_UPLOAD_NONCE' => wp_create_nonce('agent_file_upload_action')
                )
            );
        }
    }
}
add_action('wp_enqueue_scripts', 'deepseek_enqueue_assets');

// 处理文件上传的AJAX请求
function deepseek_handle_file_upload() {
    check_ajax_referer('file_upload_action', 'nonce');

    if (!isset($_FILES['file'])) {
        wp_send_json_error(['message' => '没有文件被上传']);
        return;
    }

    $allowed_types = array_map('trim', explode(',', get_option('allowed_file_types', 'txt,docx,pdf,xlsx,md')));
    $max_size = (int)get_option('max_file_size', 100) * 1024 * 1024;
    $file = $_FILES['file'];
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $interface = isset($_POST['interface']) ? sanitize_text_field($_POST['interface']) : 'qwen';
    $model = isset($_POST['model']) ? sanitize_text_field($_POST['model']) : '';
    $qwen_video_models = explode(',', get_option('qwen_video_model', 'wanx2.1-t2v-turbo'));

    // 如果是通义千问视频模型，限制文件类型为图片
    if ($interface === 'qwen' && in_array($model, $qwen_video_models)) {
        $allowed_image_types = ['jpeg', 'jpg', 'png', 'bmp', 'webp'];
        if (!in_array($file_extension, $allowed_image_types)) {
            wp_send_json_error([
                'message' => '仅支持JPEG、JPG、PNG、BMP、WEBP格式的图片',
                'type' => 'invalid_type',
                'allowed_types' => implode(', ', $allowed_image_types)
            ]);
            return;
        }

        // 验证文件大小（通义千问要求不超过10MB）
        if ($file['size'] > 10 * 1024 * 1024) {
            wp_send_json_error([
                'message' => '图片大小超过限制: 10MB',
                'type' => 'size_exceeded',
                'max_size' => 10
            ]);
            return;
        }

        // 上传到媒体库
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        $attachment_id = media_handle_upload('file', 0);
        if (is_wp_error($attachment_id)) {
            wp_send_json_error(['message' => '图片上传失败: ' . $attachment_id->get_error_message()]);
            return;
        }

        $image_url = wp_get_attachment_url($attachment_id);
        wp_send_json_success([
            'file_id' => $attachment_id,
            'filename' => $file['name'],
            'image_url' => $image_url,
            'interface' => 'qwen'
        ]);
        return;
    }

    // 验证文件类型（非视频模型）
    if (!in_array($file_extension, $allowed_types)) {
        wp_send_json_error([
            'message' => '此文件是不支持的文件类型',
            'type' => 'invalid_type',
            'allowed_types' => implode(', ', $allowed_types)
        ]);
        return;
    }

    // 验证文件大小（非视频模型）
    if ($file['size'] > $max_size) {
        wp_send_json_error([
            'message' => '文件大小超过限制: ' . round($max_size / (1024 * 1024), 2) . 'MB',
            'type' => 'size_exceeded',
            'max_size' => round($max_size / (1024 * 1024), 2)
        ]);
        return;
    }

    // 判断模型是否支持文档分析
    $support_doc_models = [
        'kimi' => explode(',', get_option('kimi_model', '')),
        'openai' => explode(',', get_option('openai_model', '')),
        'qwen' => ['qwen-long']
    ];
    $is_doc_supported = false;
    if (isset($support_doc_models[$interface])) {
        $is_doc_supported = in_array($model, $support_doc_models[$interface]);
    }
    if (!$is_doc_supported) {
        wp_send_json_error(['message' => '该模型不支持分析文档，分析文档请使用Kimi或者通义千问的qwen-long模型']);
        return;
    }

    // 接口选择及文件上传
    switch ($interface) {
        case 'qwen':
            $api_key = get_option('qwen_api_key');
            $upload_url = 'https://dashscope.aliyuncs.com/compatible-mode/v1/files';
            break;
        case 'openai':
            $api_key = get_option('openai_api_key');
            $upload_url = 'https://api.openai.com/v1/files';
            break;
        case 'kimi':
            $api_key = get_option('kimi_api_key');
            $upload_url = 'https://api.moonshot.cn/v1/files';
            break;
        default:
            wp_send_json_error(['message' => '无效的接口选择']);
            return;
    }

    if (empty($api_key)) {
        wp_send_json_error(['message' => 'API Key 未设置']);
        return;
    }

    $cfile = new CURLFile($file['tmp_name'], $file['type'], $file['name']);
    $data = [
        'file' => $cfile,
        'purpose' => 'file-extract'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $upload_url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $api_key",
        "Content-Type: multipart/form-data"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 600);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $http_code != 200) {
        wp_send_json_error([
            'message' => '文件上传失败: ' . (curl_error($ch) ?: '未知错误'),
            'http_code' => $http_code,
            'response' => substr($response, 0, 200)
        ]);
        return;
    }

    $response_data = json_decode($response, true);
    if (!isset($response_data['id'])) {
        wp_send_json_error([
            'message' => 'API返回数据格式错误',
            'response' => $response_data
        ]);
        return;
    }

    wp_send_json_success([
        'file_id' => $response_data['id'],
        'filename' => $file['name'],
        'interface' => $interface
    ]);
}

add_action('wp_ajax_deepseek_upload_file', 'deepseek_handle_file_upload');

// 处理接口切换的AJAX请求
function deepseek_handle_interface_switch() {
    check_ajax_referer('interface_switch_action', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error('请先登录');
        return;
    }

    $user_id = get_current_user_id();
    $selected_interface = isset($_POST['selected_interface']) ? sanitize_text_field($_POST['selected_interface']) : '';
    $enabled_interfaces = get_option('chat_interfaces', array('deepseek'));
    
    if (in_array($selected_interface, $enabled_interfaces)) {
        update_user_meta($user_id, 'selected_chat_interface', $selected_interface);
        wp_send_json_success("接口已切换为: $selected_interface");
    } else {
        wp_send_json_error('无效的接口选择');
    }
}

// 获取用户当前选择的对话接口
function deepseek_get_user_interface() {
    $user_id = get_current_user_id();
    $enabled_interfaces = get_option('chat_interfaces', array('deepseek'));
    $default_interface = get_option('default_chat_interface', 'deepseek');
    
    if (is_user_logged_in()) {
        $user_interface = get_user_meta($user_id, 'selected_chat_interface', true);
        return $user_interface && in_array($user_interface, $enabled_interfaces) ? $user_interface : $default_interface;
    }
    return $default_interface;
}

function deepseek_get_current_interface() {
    if (!is_user_logged_in()) {
        wp_send_json_error('请先登录');
        return;
    }
    $current_interface = deepseek_get_user_interface();
    wp_send_json_success(['interface' => $current_interface]);
}
add_action('wp_ajax_deepseek_get_current_interface', 'deepseek_get_current_interface');

// 对话 开始
function deepseek_chat_shortcode() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'deepseek_chat_logs';
    $user_id = get_current_user_id();
    $show_interface_switch = get_option('show_interface_switch', '0');
    $enabled_interfaces = get_option('chat_interfaces', array('deepseek'));
    $default_interface = get_option('default_chat_interface', 'deepseek');
    $qwen_enable_search = get_option('qwen_enable_search', '0');
    $current_interface = deepseek_get_user_interface();
    $enable_file_upload = get_option('enable_file_upload', '0');

    $history = array();
    if (is_user_logged_in()) {
        $history = $wpdb->get_results( 
            $wpdb->prepare(
                "SELECT * FROM $table_name 
                 WHERE user_id = %d 
                 GROUP BY conversation_id 
                 ORDER BY created_at DESC",
                $user_id
            ) 
        );
    }

    // 支持联网搜索的模型列表
    $search_supported_models = [
        'qwen' => ['qwen-max', 'qwen-plus', 'qwen-turbo'],
        'xunfei' => ['generalv3', 'generalv3.5', '4.0Ultra']
    ];

    ob_start();
    ?>
    <div id="deepseek-chat-container">
        <!-- 历史记录区域 -->
        <div id="deepseek-chat-history">
            <?php if (is_user_logged_in()): ?>
                <button id="deepseek-new-chat">开启新对话</button>
                <?php if (get_option('enable_intelligent_agent', '0') == '1'): ?>
                    <div id="deepseek-agent-title" class="deepseek-agent-title" style="cursor: pointer;">智能体应用</div>
                    <?php 
                    if (get_option('enable_custom_entry', '0') == '1') {
                        $custom_title = get_option('custom_entry_title', '');
                        $custom_url = get_option('custom_entry_url', '');
                        if (!empty($custom_title) && !empty($custom_url)) {
                            echo '<a href="' . esc_url($custom_url) . '" target="_blank" class="deepseek-custom-entry-title">' . esc_html($custom_title) . '</a>';
                        }
                    }
                    ?>
                <?php endif; ?>
                <ul>
                    <?php if (!empty($history)): ?>
                        <?php foreach ($history as $log): ?>
                            <li data-conversation-id="<?php echo $log->conversation_id; ?>">
                                <span class="deepseek-chat-title">
                                    <?php 
                                        $title = mb_strlen($log->conversation_title, 'UTF-8') > 6 
                                            ? mb_substr($log->conversation_title, 0, 6, 'UTF-8') . '...' 
                                            : $log->conversation_title;
                                        echo esc_html($title);
                                    ?>
                                </span>
                                <button class="deepseek-delete-log" data-conversation-id="<?php echo $log->conversation_id; ?>">删除</button>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            <?php else: ?>
                <p>未登录，暂无历史记录</p>
            <?php endif; ?>
        </div>

        <!-- 主对话区域 -->
        <div id="deepseek-chat-main">
            <div id="deepseek-chat-messages">
                <div class="message-bubble bot" id="chatbot-prompt">你好，我可以帮你写作、写文案、翻译，有问题请问我~</div>
                <?php
                $custom_prompts = get_option('deepseek_custom_prompts', '');
                if (!empty($custom_prompts)) {
                    $prompts = array_filter(array_map('trim', explode("\n", $custom_prompts)));
                    if (!empty($prompts)) {
                        echo '<div id="deepseek-custom-prompts">';
                        foreach ($prompts as $prompt) {
                            echo '<span class="deepseek-prompt">' . esc_html($prompt) . '</span>';
                        }
                        echo '</div>';
                    }
                }
                ?>
            </div>

            <div id="clear-conversation-container">
                <button id="clear-conversation-button" style="display: none;">清除对话</button>
            </div>

            <div id="deepseek-chat-input-container">
                <?php if (is_user_logged_in()): ?>
                    <textarea id="deepseek-chat-input" placeholder="输入你的消息..." rows="4"></textarea>
                    <button id="deepseek-chat-send">发送</button>
                <?php else: 
                    $login_prompt = get_option('deepseek_login_prompt', '请先登录才能使用Ai对话功能');
                ?>
                    <div class="deepseek-login-overlay">
                        <?php echo esc_html($login_prompt); ?>
                    </div>
                <?php endif; ?>
            </div>

            <div id="keyword-error-message" style="color: red; display: none; margin-top: 5px; margin-left: 10px;">
                内容包含违规关键词，小助手无法正常处理，请刷新网页修改之后再试。
            </div>

            <div id="deepseek-options-bar">
                <?php if ($show_interface_switch == '1' && is_user_logged_in()): ?>
                    <div class="deepseek-option-item deepseek-interface-select" style="display: none;">
                        <form id="interface-switch-form" method="post" action="">
                            <?php wp_nonce_field('interface_switch_action', 'interface_switch_nonce'); ?>
                            <label for="chat-interface-select">选择接口:</label>
                            <select name="selected_interface" id="chat-interface-select">
                                <?php
                                $interfaces = array(
                                    'deepseek' => 'DeepSeek',
                                    'openai' => 'OpenAI',
                                    'grok' => 'Grok',
                                    'gemini' => 'Gemini',
                                    'claude' => 'Claude',
                                    'qwen' => '通义千问',
                                    'kimi' => 'Kimi',
                                    'doubao' => '豆包AI',
                                    'qianfan' => '文心一言',
                                    'hunyuan' => '腾讯混元',
                                    'xunfei' => '讯飞星火',
                                    'pollinations' => '英文生图',
                                    'custom' => '备份接口'
                                );
                                foreach ($enabled_interfaces as $interface) {
                                    if (isset($interfaces[$interface])) {
                                        $selected = ($interface === $current_interface) ? 'selected' : '';
                                        echo '<option value="' . esc_attr($interface) . '" ' . $selected . '>' . 
                                             esc_html($interfaces[$interface]) . '</option>';
                                    }
                                }
                                ?>
                            </select>
                        </form>
                        <label for="chat-model-select">选择参数:</label>
                        <select name="selected_model" id="chat-model-select">
                            <!-- 模型参数选项动态填充 -->
                        </select>
                    </div>
                <?php endif; ?>

                <!-- 联网搜索开关 -->
                <?php if ($qwen_enable_search == '1' && in_array($current_interface, array_keys($search_supported_models))): ?>
                    <div class="deepseek-option-item deepseek-search-toggle" style="display: none;" data-supported-models='<?php echo json_encode($search_supported_models); ?>'>
                <label class="switch">
                    <input type="checkbox" id="enable-search">
                    <span class="slider round"></span>
                </label>
                <span>联网搜索</span>
            </div>
        <?php endif; ?>

                <?php
                $tutorial_title = get_option('ai_tutorial_title', '');
                $tutorial_url   = get_option('ai_tutorial_url', '');
                if (!empty($tutorial_title) && !empty($tutorial_url)): ?>
                    <div class="deepseek-option-item deepseek-tutorial-link">
                        <a href="<?php echo esc_url($tutorial_url); ?>" target="_blank">
                            <?php echo esc_html($tutorial_title); ?>
                        </a>
                    </div>
                <?php endif; ?>
                
                <?php if ($enable_file_upload == '1' && is_user_logged_in()): ?>
                    <div class="deepseek-option-item upload-section" style="display: none;">
                        <button id="deepseek-upload-file-btn">上传文件</button>
                        <input type="file" id="deepseek-file-input" multiple style="display: none;" />
                        <div id="uploaded-files-list"></div>
                    </div>
                <?php endif; ?> 

                <!-- 智能体文件上传板块 -->
                <div class="agent-upload-section" style="display: none;">
                    <button id="deepseek-agent-upload-btn">本地文件</button>
                    <input type="file" id="deepseek-agent-file-input" style="display: none;" />
                    <div id="agent-uploaded-file">
                        <span class="file-name"></span>
                        <button class="remove-file-btn">删除</button>
                    </div>
                </div>
            </div>
        
        <!-- 图片生成视频预览区域 -->
        <div id="qwen-video-image-preview" style="display: none; margin-top: 10px;">
        <div id="uploaded-image-container"></div>
        <button id="remove-uploaded-image">删除</button>
    </div>
    </div>
</div>
<?php
    // 获取公告内容并显示
    $announcement = get_option('deepseek_announcement', '');
    if (!empty($announcement)) {
        echo '<div id="deepseek-announcement">';
        echo wp_kses_post($announcement);
        echo '</div>';
    }
    ?>
    <script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function() {
        const input = document.getElementById('deepseek-chat-input');
        const sendButton = document.getElementById('deepseek-chat-send');
        if (input && sendButton && !sendButton.disabled) {
            input.addEventListener('keypress', function(event) {
                if (event.key === 'Enter' && !event.shiftKey) {
                    event.preventDefault();
                    sendButton.click();
                }
            });
        }

        const clearButton = document.getElementById('clear-conversation-button');
        if (clearButton) {
            clearButton.addEventListener('click', function() {
                showClearConfirmation(document.getElementById('deepseek-chat-messages'));
            });
        }
    });
    </script>

    <script type="text/javascript">
        var ajax_nonce = '<?php echo wp_create_nonce("interface_switch_action"); ?>';
        var ajax_url = '<?php echo admin_url("admin-ajax.php"); ?>';
        var enabled_interfaces = <?php echo json_encode($enabled_interfaces); ?>;
        var model_params = {
            'deepseek': '<?php echo get_option('deepseek_model', 'deepseek-chat'); ?>',
            'doubao': '<?php echo get_option('doubao_model', ''); ?>',
            'kimi': '<?php echo get_option('kimi_model', ''); ?>',
            'openai': '<?php echo get_option('openai_model', ''); ?>',
            'grok': '<?php echo get_option('grok_model', ''); ?>',
            'gemini': '<?php echo get_option('gemini_model', 'gemini-2.0-flash'); ?>',
            'claude': '<?php echo get_option('claude_model', 'claude-3-7-sonnet-20250219'); ?>', 
            'qianfan': '<?php echo get_option('qianfan_model', ''); ?>',
            'hunyuan': '<?php echo get_option('hunyuan_model', ''); ?>',
            'xunfei': '<?php echo get_option('xunfei_model', 'generalv3.5'); ?>',
            'qwen': '<?php echo get_option('qwen_text_model', 'qwen-max') . ',' . get_option('qwen_image_model', 'wanx2.1-t2i-turbo') . ',' . get_option('qwen_video_model', 'wanx2.1-t2v-turbo'); ?>',
            'pollinations': '<?php echo get_option('pollinations_model', 'flux'); ?>',
            'custom': '<?php echo get_option('custom_model_params', ''); ?>'
        };
    </script>

    <?php
    return ob_get_clean();
}
add_shortcode('deepseek_chat', 'deepseek_chat_shortcode');

// 使用REST API方式处理消息
function deepseek_send_message_rest(WP_REST_Request $request) {
    global $wpdb;

    $table_name = $wpdb->prefix . 'deepseek_chat_logs';
    $message = sanitize_text_field($request->get_param('message'));
    $conversation_id = $request->get_param('conversation_id') ? intval($request->get_param('conversation_id')) : null;
    $user_id = get_current_user_id();
    $interface_choice = $request->get_param('interface') ?: deepseek_get_user_interface();
    $model_choice = sanitize_text_field($request->get_param('model')); // 用户选择的模型参数
    $enable_search = filter_var($request->get_param('enable_search'), FILTER_VALIDATE_BOOLEAN);
    $file_ids = $request->get_param('file_ids') ? json_decode($request->get_param('file_ids'), true) : [];

    // 关键词检测
    $enable_keyword_detection = get_option('enable_keyword_detection', '0');
    if ($enable_keyword_detection) {
        $keywords = get_option('keyword_list', '');
        $keywords = array_map('trim', explode(',', $keywords));
        foreach ($keywords as $keyword) {
            if (stripos($message, $keyword) !== false) {
                return new WP_REST_Response([
                    'success' => false,
                    'message' => '内容包含违规关键词'
                ], 400);
            }
        }
    }

    // 获取模型参数列表并验证
    $model_list = [];
    switch ($interface_choice) {
        case 'deepseek':
            $model_list = explode(',', get_option('deepseek_model', 'deepseek-chat'));
            break;
        case 'doubao':
            $model_list = explode(',', get_option('doubao_model', ''));
            break;
        case 'kimi':
            $model_list = explode(',', get_option('kimi_model', ''));
            break;
        case 'openai':
            $model_list = explode(',', get_option('openai_model', ''));
            break;
        case 'grok':
            $model_list = explode(',', get_option('grok_model', ''));
            break;
        case 'gemini':
            $model_list = explode(',', get_option('gemini_model', 'gemini-2.0-flash'));
            break;
        case 'claude':
            $model_list = explode(',', get_option('claude_model', 'claude-3-7-sonnet-20250219'));
            break;            
        case 'qianfan':
            $model_list = explode(',', get_option('qianfan_model', ''));
            break;
        case 'hunyuan':
            $model_list = explode(',', get_option('hunyuan_model', ''));
            break;
        case 'xunfei':
            $model_list = explode(',', get_option('xunfei_model', 'generalv3.5'));
            break;    
        case 'pollinations':
            $model_list = explode(',', get_option('pollinations_model', 'flux'));
            break;    
        case 'qwen':
            $model_list = array_merge(
                explode(',', get_option('qwen_text_model', 'qwen-max')),
                explode(',', get_option('qwen_image_model', 'wanx2.1-t2i-turbo')),
                explode(',', get_option('qwen_video_model', 'wanx2.1-t2v-turbo'))
            );
            break;
        case 'custom':
            $model_list = explode(',', get_option('custom_model_params', ''));
            break;
        default:
            return new WP_REST_Response(['success' => false, 'message' => '无效的接口选择'], 400);
    }
    $model_list = array_map('trim', $model_list);
    $model = in_array($model_choice, $model_list) ? $model_choice : $model_list[0]; // 默认使用第一个参数

    // 判断是否为Pollinations文生图请求
    if ($interface_choice === 'pollinations') {
    $encoded_prompt = urlencode($message);
    $api_url = "https://image.pollinations.ai/prompt/{$encoded_prompt}?model={$model}&width=1024&height=1024&nologo=true&private=true";

    $max_retries = 3;
    $retry_delay = 2; //秒
    $attempt = 0;
    $image_url = null;

    do {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120); // 增加超时时间
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // 跟随重定向
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: image/*', // 明确要求图片响应
            'User-Agent: Mozilla/5.0'
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($http_code == 200 && $response !== false) {
            // Pollinations返回的是图片二进制数据，直接使用URL
            $image_url = $api_url;
            break;
        } else {
            $attempt++;
            if ($attempt < $max_retries) {
                sleep($retry_delay); // 等待后重试
            }
        }
    } while ($attempt < $max_retries && !$image_url);

    if ($image_url) {
        // 保存到数据库
        $wpdb->insert($table_name, [
            'user_id' => $user_id,
            'conversation_id' => $conversation_id ?: 0,
            'conversation_title' => $message,
            'message' => $message,
            'response' => json_encode(['image_url' => $image_url])
        ]);

        if (!$conversation_id) {
            $conversation_id = $wpdb->insert_id;
            $wpdb->update($table_name, ['conversation_id' => $conversation_id], ['id' => $conversation_id]);
        }

        return new WP_REST_Response([
            'success' => true,
            'is_pollinations_image' => true,
            'image_url' => $image_url,
            'conversation_id' => $conversation_id,
            'conversation_title' => $message,
        ], 200);
    } else {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Pollinations 图片生成失败: ' . ($error ?: '多次尝试后仍无响应'),
            'attempts' => $attempt
        ], 500);
    }
}

    // 判断是否为通义千问图像模型
    $qwen_image_models = explode(',', get_option('qwen_image_model', 'wanx2.1-t2i-turbo'));
    $is_image_model = ($interface_choice === 'qwen' && in_array($model, $qwen_image_models));
    if ($is_image_model) {
        $api_key = get_option('qwen_api_key');
        $api_url = 'https://dashscope.aliyuncs.com/api/v1/services/aigc/text2image/image-synthesis';
        $headers = [
            'X-DashScope-Async: enable',
            'Authorization: Bearer ' . $api_key,
            'Content-Type: application/json'
        ];
        $body = [
            'model' => $model,
            'input' => ['prompt' => $message],
            'parameters' => ['size' => '1024*1024', 'n' => 1]
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api_url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code == 200) {
            $response_data = json_decode($response, true);
            $task_id = $response_data['output']['task_id'];

            $wpdb->insert($table_name, [
                'user_id' => $user_id,
                'conversation_id' => $conversation_id ?: 0,
                'conversation_title' => $message,
                'message' => $message,
                'response' => json_encode([
                    'task_id' => $task_id,
                    'status' => 'pending',
                    'message' => '图片生成中...'
                ])
            ]);

            if (!$conversation_id) {
                $conversation_id = $wpdb->insert_id;
                $wpdb->update($table_name, ['conversation_id' => $conversation_id], ['id' => $conversation_id]);
            }

            return new WP_REST_Response([
                'success' => true,
                'is_image' => true,
                'task_id' => $task_id,
                'conversation_id' => $conversation_id,
                'conversation_title' => $message,
            ], 200);
        } else {
            return new WP_REST_Response([
                'success' => false,
                'message' => '图片生成请求失败: ' . $response
            ], 500);
        }
    }

    // 通义千问视频模型
    $qwen_video_models = explode(',', get_option('qwen_video_model', 'wanx2.1-t2v-turbo'));
    $is_video_model = ($interface_choice === 'qwen' && in_array($model, $qwen_video_models));
    if ($is_video_model) {
    $api_key = get_option('qwen_api_key');
    $api_url = 'https://dashscope.aliyuncs.com/api/v1/services/aigc/video-generation/video-synthesis';
    $headers = [
        'X-DashScope-Async: enable',
        'Authorization: Bearer ' . $api_key,
        'Content-Type: application/json'
    ];
    $body = [
        'model' => $model,
        'input' => ['prompt' => $message],
        'parameters' => [
            'prompt_extend' => true
        ]
    ];

    // 如果有上传的图片，添加img_url
    if (!empty($file_ids) && isset($file_ids[0]['image_url'])) {
        $body['input']['img_url'] = $file_ids[0]['image_url'];
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code == 200) {
        $response_data = json_decode($response, true);
        $task_id = $response_data['output']['task_id'];

        $wpdb->insert($table_name, [
            'user_id' => $user_id,
            'conversation_id' => $conversation_id ?: 0,
            'conversation_title' => $message,
            'message' => $message,
            'response' => json_encode([
                'task_id' => $task_id,
                'status' => 'pending',
                'message' => '视频生成中，请稍后查看结果（约5-10分钟）'
            ])
        ]);

        if (!$conversation_id) {
            $conversation_id = $wpdb->insert_id;
            $wpdb->update($table_name, ['conversation_id' => $conversation_id], ['id' => $conversation_id]);
        }

        return new WP_REST_Response([
            'success' => true,
            'is_video' => true,
            'task_id' => $task_id,
            'conversation_id' => $conversation_id,
            'conversation_title' => $message,
        ], 200);
    } else {
        return new WP_REST_Response([
            'success' => false,
            'message' => '视频生成请求失败: ' . $response
        ], 500);
    }
}

    // 文本对话分支
    switch ($interface_choice) {
        case 'deepseek':
            $api_key = get_option('deepseek_api_key');
            $api_url = 'https://api.deepseek.com/chat/completions';
            break;
        case 'doubao':
            $api_key = get_option('doubao_api_key');
            $api_url = 'https://ark.cn-beijing.volces.com/api/v3/chat/completions';
            break;
        case 'hunyuan':
            $api_key = get_option('hunyuan_api_key');
            $api_url = 'https://api.hunyuan.cloud.tencent.com/v1/chat/completions';
            break;
        case 'kimi':
            $api_key = get_option('kimi_api_key');
            $api_url = 'https://api.moonshot.cn/v1/chat/completions';
            break;
        case 'openai':
            $api_key = get_option('openai_api_key');
            $api_url = 'https://api.openai.com/v1/chat/completions';
            break;
        case 'grok':
            $api_key = get_option('grok_api_key');
            $api_url = 'https://api.x.ai/v1/chat/completions';
            break;
        case 'gemini':
            $api_key = get_option('gemini_api_key');
            $api_url = 'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions';
            break;
        case 'claude':
            $api_key = get_option('claude_api_key');
            $api_url = 'https://api.anthropic.com/v1/messages';
            break;            
        case 'qianfan':
            $api_key = get_option('qianfan_api_key');
            $api_url = 'https://qianfan.baidubce.com/v2/chat/completions';
            break;
        case 'xunfei':
            $api_key = get_option('xunfei_api_key');
            $api_url = 'https://spark-api-open.xf-yun.com/v1/chat/completions';
            break;    
        case 'qwen':
            $api_key = get_option('qwen_api_key');
            $api_url = 'https://dashscope.aliyuncs.com/compatible-mode/v1/chat/completions';
            break;
        case 'custom':
            $api_key = get_option('custom_api_key');
            $api_url = get_option('custom_model_url');
            if (empty($api_key) || empty($model) || empty($api_url)) {
                return new WP_REST_Response(['success' => false, 'message' => '自定义模型设置不完整'], 400);
            }
            break;
        default:
            return new WP_REST_Response(['success' => false, 'message' => '无效的接口选择'], 400);
    }

    if (empty($api_key)) {
        return new WP_REST_Response(['success' => false, 'message' => 'API Key 未设置'], 400);
    }

    // 构建消息历史
    $messages = [['role' => 'system', 'content' => 'You are a helpful assistant capable of analyzing uploaded files.']];
    if ($conversation_id) {
        $history = $wpdb->get_results($wpdb->prepare(
            "SELECT message, response FROM $table_name 
            WHERE conversation_id = %d 
            ORDER BY id ASC",
            $conversation_id
        ));
        
        foreach ($history as $item) {
            $messages[] = ['role' => 'user', 'content' => $item->message];
            $response = json_decode($item->response, true);
            $content = is_array($response) && isset($response['content']) ? $response['content'] : $item->response;
            $messages[] = ['role' => 'assistant', 'content' => $content];
        }
    }

    // 处理文件内容
    if (!empty($file_ids)) {
        $support_doc_models = [
            'kimi' => explode(',', get_option('kimi_model', '')),
            'openai' => explode(',', get_option('openai_model', '')),
            'qwen' => ['qwen-long'] // 通义千问仅支持qwen-long
        ];
        $is_doc_supported = false;
        if (isset($support_doc_models[$interface_choice])) {
            $is_doc_supported = in_array($model, $support_doc_models[$interface_choice]);
        }
        if (!$is_doc_supported) {
            return new WP_REST_Response([
                'success' => false,
                'message' => '该模型不支持分析文档，分析文档请选择Kimi或者通义千问的qwen-long模型'
            ], 400);
        }

        if ($interface_choice === 'qwen') {
            $file_ids_str = '';
            foreach ($file_ids as $file_info) {
                $file_id = $file_info['file_id'];
                $interface = $file_info['interface'];
                if ($interface === 'qwen') {
                    $file_ids_str .= ($file_ids_str ? ',' : '') . "fileid://$file_id";
                }
            }
            if ($file_ids_str) {
                $messages[] = [
                    'role' => 'system',
                    'content' => $file_ids_str
                ];
            }
            $messages[] = [
                'role' => 'user',
                'content' => "请分析这些文件并回答我的问题:\n\n问题: $message"
            ];
        } else {
            $file_content = '';
            foreach ($file_ids as $file_info) {
                $file_id = $file_info['file_id'];
                $interface = $file_info['interface'];
                $content_url = '';
                $content_api_key = '';

                switch ($interface) {
                    case 'openai':
                        $content_api_key = get_option('openai_api_key');
                        $content_url = "https://api.openai.com/v1/files/$file_id/content";
                        break;
                    case 'kimi':
                        $content_api_key = get_option('kimi_api_key');
                        $content_url = "https://api.moonshot.cn/v1/files/$file_id/content";
                        break;
                    default:
                        // 不支持的接口直接跳过本次循环
                        continue 2; // 明确跳出外层foreach循环
                }

                if (empty($content_api_key)) {
                    continue; // 如果API Key为空，跳过本次循环
                }

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $content_url);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $content_api_key"]);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPGET, true);
                $response = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($http_code == 200) {
                    $file_content .= $response . "\n\n";
                }
            }
            $messages[] = [
                'role' => 'user',
                'content' => "请分析以下文件内容并回答我的问题:\n\n$file_content\n\n问题: $message"
            ];
        }
    } else {
        $messages[] = ['role' => 'user', 'content' => $message];
    }

    // 构建请求数据
    if ($interface_choice === 'claude') {
        // Claude使用自己的消息格式
        $data = [
            'model' => $model,
            'max_tokens' => 4096, // Claude的token限制
            'messages' => array_map(function($msg) {
                return [
                    'role' => $msg['role'] === 'system' ? 'system' : ($msg['role'] === 'user' ? 'user' : 'assistant'),
                    'content' => $msg['content']
                ];
            }, $messages),
            'stream' => true
        ];
    } else {
        // OpenAI接口
        $data = [
            'model' => $model,
            'messages' => $messages,
            'stream' => true
        ];
    }

    // 如果启用了联网搜索，且当前是讯飞星火接口，则启用讯飞的web_search
    $qwen_enable_search = get_option('qwen_enable_search', '0');
    if ($interface_choice === 'xunfei' && $qwen_enable_search == '1' && $enable_search) {
        $data['tools'] = [
            [
                'type' => 'web_search',
                'web_search' => [
                    'enable' => true
                ]
            ]
        ];
    } elseif ($interface_choice === 'qwen' && $qwen_enable_search == '1' && $enable_search) {
        $data['enable_search'] = true; // 通义千问的联网搜索参数
    }

    // 清空缓冲区，设置流式响应头
    if (ob_get_length()) { ob_end_clean(); }
    while (ob_get_level()) { ob_end_flush(); }
    ini_set('output_buffering', 'off');
    ini_set('zlib.output_compression', false);
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('X-Accel-Buffering: no');
    header('Connection: keep-alive');

    // 发送请求并处理流式响应
    $fullReply = [];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $api_key,
        $interface_choice === 'claude' ? 'x-api-key: ' . $api_key : '', // Claude x-api-key
        $interface_choice === 'claude' ? 'anthropic-version: 2023-06-01' : '' // Claude API版本
    ]);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
    curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
    curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
    curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $chunk) use (&$fullReply, $interface_choice) {
        echo $chunk;
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
        $fullReply[] = $chunk;
        return strlen($chunk);
    });
    curl_setopt($ch, CURLOPT_TIMEOUT, 0);
    $curl_result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // 调试日志记录 - wp-config.php配置里面开了才记录
    if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
        $log_data = [
            'timestamp' => current_time('mysql'),
            'request' => $data,
            'raw_response' => $fullReply
        ];
        error_log("[AI_REQUEST_DEBUG] " . json_encode($log_data) . "\n", 3, WP_CONTENT_DIR . '/debug.log');
    }

    if ($curl_result === false || $http_code != 200) {
        $error_msg = "API request failed with HTTP code: $http_code";
        echo "data: " . json_encode(['error' => $error_msg]) . "\n\n";
        flush();
        exit();
    }

    // 处理流式数据并提取内容
    $processedReply = ['content' => '', 'reasoning_content' => ''];
    $fullReplyString = implode('', $fullReply);
    $lines = explode("\n", $fullReplyString);

    foreach ($lines as $line) {
        $line = trim($line);
        if ($interface_choice === 'claude') {
            // Claude流式输出格式
            if (strpos($line, 'data:') === 0) {
                $dataPart = trim(substr($line, 5));
                if ($dataPart === '[DONE]') {
                    continue;
                }
                $jsonData = json_decode($dataPart, true);
                if ($jsonData && isset($jsonData['type'])) {
                    if ($jsonData['type'] === 'content_block_delta' && isset($jsonData['delta']['text'])) {
                        $processedReply['content'] .= $jsonData['delta']['text'];
                    }
                }
            }
        } else {
            // OpenAI兼容格式
            if (strpos($line, 'data:') === 0) {
                $dataPart = trim(substr($line, 5));
                if ($dataPart === '[DONE]') {
                    continue;
                }
                $jsonData = json_decode($dataPart, true);
                if ($jsonData && isset($jsonData['choices'][0]['delta'])) {
                    $delta = $jsonData['choices'][0]['delta'];
                    if (isset($delta['content'])) {
                        $processedReply['content'] .= $delta['content'];
                    }
                    if (isset($delta['reasoning_content'])) {
                        $processedReply['reasoning_content'] .= $delta['reasoning_content'];
                    }
                }
            }
        }
    }

    // 保存到数据库
    $reply = json_encode($processedReply);
    $wpdb->insert($table_name, [
        'user_id' => $user_id,
        'conversation_id' => $conversation_id ?: 0,
        'conversation_title' => $conversation_id ? '' : $message,
        'message' => $message,
        'response' => $reply
    ]);

    if (!$conversation_id) {
        $conversation_id = $wpdb->insert_id;
        $wpdb->update($table_name, ['conversation_id' => $conversation_id], ['id' => $conversation_id]);
    }

    // 输出conversation_id
    echo "\n";
    echo "data: " . json_encode(['conversation_id' => $conversation_id]) . "\n\n";
    if (ob_get_level() > 0) {
        ob_flush();
    }
    flush();

    exit();
}

add_action('rest_api_init', function () {
    register_rest_route('deepseek/v1', '/send-message', array(
        'methods' => 'POST',
        'callback' => 'deepseek_send_message_rest',
        'permission_callback' => '__return_true',
    ));
});

// 图片任务状态检查接口
function deepseek_check_image_task() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'deepseek_chat_logs';
    
    $task_id = sanitize_text_field($_POST['task_id']);
    $api_key = get_option('qwen_api_key');
    
    $url = 'https://dashscope.aliyuncs.com/api/v1/tasks/' . $task_id;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $api_key,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code == 200) {
        $response_data = json_decode($response, true);
        if ($response_data['output']['task_status'] === 'SUCCEEDED') {
            $actual_prompt = $response_data['output']['results'][0]['actual_prompt'] ?? '';
            $image_url = $response_data['output']['results'][0]['url'] ?? '';

            $record = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_name WHERE response LIKE %s",
                '%' . $wpdb->esc_like($task_id) . '%'
            ));

            if ($record) {
                $wpdb->update($table_name, 
                    ['response' => json_encode([
                        'status' => 'succeeded',
                        'actual_prompt' => $actual_prompt,
                        'image_url' => $image_url
                    ])], 
                    ['id' => $record->id]
                );
            }

            wp_send_json([
                'success' => true,
                'task_status' => 'SUCCEEDED',
                'actual_prompt' => $actual_prompt,
                'image_url' => $image_url
            ]);
        } else {
            wp_send_json([
                'success' => true,
                'task_status' => $response_data['output']['task_status']
            ]);
        }
    } else {
        wp_send_json(['success' => false, 'message' => '任务状态查询失败']);
    }
}
add_action('wp_ajax_deepseek_check_image_task', 'deepseek_check_image_task');
add_action('wp_ajax_nopriv_deepseek_check_image_task', 'deepseek_check_image_task');

function deepseek_check_video_task() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'deepseek_chat_logs';
    
    $task_id = sanitize_text_field($_POST['task_id']);
    $api_key = get_option('qwen_api_key');
    
    $url = 'https://dashscope.aliyuncs.com/api/v1/tasks/' . $task_id;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $api_key,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code == 200) {
        $response_data = json_decode($response, true);
        $task_status = $response_data['output']['task_status'];
        
        if ($task_status === 'SUCCEEDED') {
            $video_url = $response_data['output']['video_url'] ?? '';

            $record = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_name WHERE response LIKE %s",
                '%' . $wpdb->esc_like($task_id) . '%'
            ));

            if ($record) {
                $wpdb->update($table_name, 
                    ['response' => json_encode([
                        'status' => 'succeeded',
                        'video_url' => $video_url
                    ])], 
                    ['id' => $record->id]
                );
            }

            wp_send_json([
                'success' => true,
                'task_status' => 'SUCCEEDED',
                'video_url' => $video_url
            ]);
        } else {
            wp_send_json([
                'success' => true,
                'task_status' => $task_status
            ]);
        }
    } else {
        wp_send_json(['success' => false, 'message' => '视频任务状态查询失败']);
    }
}
add_action('wp_ajax_deepseek_check_video_task', 'deepseek_check_video_task');
add_action('wp_ajax_nopriv_deepseek_check_video_task', 'deepseek_check_video_task');

// 加载历史对话记录
function deepseek_load_log() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'deepseek_chat_logs';
    $conversation_id = intval($_GET['conversation_id']);
    $user_id = get_current_user_id();

    $logs = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name 
        WHERE conversation_id = %d 
        AND user_id = %d 
        ORDER BY id ASC",
        $conversation_id,
        $user_id
    ));

    if (empty($logs)) {
        wp_send_json(array('success' => false, 'message' => '未找到对话记录。'));
        return;
    }

    $processed = array();
    foreach ($logs as $log) {
        $response = json_decode($log->response, true);
        
        // 处理视频
        if ($response && isset($response['video_url'])) {
            $html = '<video controls src="' . esc_url($response['video_url']) . '" style="max-width:100%;height:auto;"></video>';
            $processed[] = array(
                'message'  => esc_html($log->message),
                'response' => $html
            );
        }
        // 处理图片
        else if ($response && isset($response['image_url'])) {
            // 使用message作为默认提示词，如果actual_prompt不存在
            $actual_prompt = isset($response['actual_prompt']) ? esc_html($response['actual_prompt']) : esc_html($log->message);
            $html = '<div class="image-prompt">' . $actual_prompt . '</div>';
            $html .= '<img src="' . esc_url($response['image_url']) . '" style="max-width:100%;height:auto;" />';
            $processed[] = array(
                'message'  => esc_html($log->message),
                'response' => $html
            );
        }
        // 处理文本
        else {
            $content = '';
            $reasoning_content = '';
            if (is_array($response)) {
                $content = isset($response['content']) ? $response['content'] : '';
                $reasoning_content = isset($response['reasoning_content']) ? $response['reasoning_content'] : '';
            } else {
                $content = $log->response;
            }

            $processed[] = array(
                'message'  => esc_html($log->message),
                'response' => [
                    'content' => $content,
                    'reasoning_content' => $reasoning_content
                ]
            );
        }
    }

    wp_send_json([
        'success'  => true, 
        'messages' => $processed
    ]);
}
add_action('wp_ajax_deepseek_load_log', 'deepseek_load_log');
add_action('wp_ajax_nopriv_deepseek_load_log', 'deepseek_load_log');

// 删除对话记录
function deepseek_delete_log() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'deepseek_chat_logs';
    $conversation_id = intval($_POST['conversation_id']);
    $user_id = get_current_user_id();

    // 检查是否为管理员
    if (!current_user_can('manage_options') && !$wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_name WHERE conversation_id = %d AND user_id = %d",
        $conversation_id, $user_id
    ))) {
        wp_send_json(['success' => false, 'message' => '无权删除此记录']);
        return;
    }

    // 删除所有与该conversation_id相关的记录
    $deleted = $wpdb->delete(
        $table_name,
        ['conversation_id' => $conversation_id],
        ['%d']
    );

    if ($deleted === false) {
        error_log("删除对话记录失败: " . $wpdb->last_error);
        wp_send_json(['success' => false, 'message' => '删除失败: 数据库错误']);
    } elseif ($deleted === 0) {
        wp_send_json(['success' => false, 'message' => '未找到可删除的记录']);
    } else {
        wp_send_json(['success' => true, 'message' => '对话记录已删除']);
    }
}
add_action('wp_ajax_deepseek_delete_log', 'deepseek_delete_log');

// 对话记录管理页面
function deepseek_render_logs_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'deepseek_chat_logs';
    
    // 处理用户ID搜索
    $search_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : '';
    $where_clause = $search_user_id ? $wpdb->prepare("WHERE user_id = %d", $search_user_id) : '';

    // 删除记录
    if (isset($_GET['delete_conversation'])) {
        $conversation_id = intval($_GET['delete_conversation']);
        $wpdb->delete($table_name, ['conversation_id' => $conversation_id], ['%d']);
        echo '<div class="notice notice-success"><p>对话记录已删除。</p></div>';
    }

    // 分页处理
    $per_page = 20;
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $per_page;

    // 获取总记录数
    $total_logs = $wpdb->get_var("SELECT COUNT(DISTINCT conversation_id) FROM $table_name $where_clause");

    // 获取当前页的记录，并关联用户信息
    $logs = $wpdb->get_results($wpdb->prepare(
        "SELECT cl.*, u.user_login 
         FROM $table_name cl 
         LEFT JOIN {$wpdb->users} u ON cl.user_id = u.ID 
         $where_clause 
         GROUP BY cl.conversation_id 
         ORDER BY cl.created_at DESC 
         LIMIT %d OFFSET %d",
        $per_page, $offset
    ));

    ?>
    <div class="wrap">
        <h1>用户AI对话记录</h1>
        
        <!-- 用户ID搜索表单 -->
        <form method="get" class="search-form" style="margin-bottom: 20px;">
            <input type="hidden" name="page" value="deepseek-logs">
            <label for="user_id">按用户ID搜索: </label>
            <input type="number" name="user_id" id="user_id" value="<?php echo esc_attr($search_user_id); ?>" min="1" style="width: 100px;">
            <input type="submit" class="button" value="搜索">
            <?php if ($search_user_id): ?>
                <a href="?page=deepseek-logs" class="button">显示所有记录</a>
            <?php endif; ?>
        </form>

        <?php if ($search_user_id): ?>
            <p>当前显示用户ID <?php echo esc_html($search_user_id); ?> 的对话记录</p>
        <?php endif; ?>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 80px;">用户ID</th>
                    <th style="width: 150px;">用户名</th>
                    <th style="width: 300px;">首句消息</th>
                    <th style="width: 160px;">时间</th>
                    <th style="width: 100px;">操作</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($logs)) : ?>
                    <?php foreach ($logs as $log) : ?>
                        <tr>
                            <td><?php echo esc_html($log->user_id); ?></td>
                            <td><?php echo esc_html($log->user_login ? $log->user_login : '未知用户'); ?></td>
                            <td><?php 
                                $title = mb_strlen($log->conversation_title, 'UTF-8') > 50 
                                    ? mb_substr($log->conversation_title, 0, 50, 'UTF-8') . '...' 
                                    : $log->conversation_title;
                                echo esc_html($title);
                            ?></td>
                            <td><?php echo esc_html($log->created_at); ?></td>
                            <td>
                                <a href="?page=deepseek-logs&delete_conversation=<?php echo esc_attr($log->conversation_id); ?><?php echo $search_user_id ? '&user_id=' . $search_user_id : ''; ?>" 
                                   class="button" 
                                   onclick="return confirm('确定要删除此对话记录吗？');">删除</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="5">暂无记录。</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- 分页导航 -->
        <?php if ($total_logs > $per_page): ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <?php
                    $total_pages = ceil($total_logs / $per_page);
                    $args = [
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => __('« 上一页'),
                        'next_text' => __('下一页 »'),
                        'total' => $total_pages,
                        'current' => $current_page,
                    ];
                    if ($search_user_id) {
                        $args['add_args'] = ['user_id' => $search_user_id];
                    }
                    echo paginate_links($args);
                    ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

// 会员验证功能实现
add_action('template_redirect', 'deepseek_start_output_buffer');
function deepseek_start_output_buffer() {
    if (is_admin()) return;

    $deepseek_vip_check_enabled = get_option('deepseek_vip_check_enabled');
    if (!$deepseek_vip_check_enabled) return;

    global $post;
    // 只在页面类型执行
    if (is_page() && is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'deepseek_chat') && is_user_logged_in()) {
        ob_start();
        add_action('shutdown', 'deepseek_check_vip_prompt', 0);
    }
}

// 会员验证弹窗
function deepseek_check_vip_prompt() {
    $deepseekhtml = ob_get_clean();

    $deepseek_target_string = get_option('deepseek_vip_keyword', '升级VIP享受精彩下载');  // 默认值为 "升级VIP享受精彩下载" Modown主题是这个
    $deepseek_vip_prompt_page = get_option('deepseek_vip_prompt_page');

    if (strpos($deepseekhtml, $deepseek_target_string) !== false && !empty($deepseek_vip_prompt_page)) {
        $deepseekprompt = '
        <div id="vip-prompt-overlay" style="position:fixed;top:0;left:0;width:100%;height:100%;z-index:99998;backdrop-filter:blur(3px);">
            <div id="vip-prompt" style="position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:#fff;padding:30px 40px;border-radius:12px;box-shadow:0 8px 30px rgba(0,0,0,0.2);text-align:center;z-index:99999;min-width:380px;">
                <h3 style="margin:0 0 20px 0;font-size:20px;color:#333;">&#128274; 会员专属功能</h3>
                <p style="margin:0 0 25px 0;font-size:16px;color:#666;">请先开通会员才能使用AI对话服务</p>
                <div style="display:flex;gap:15px;justify-content:center;">
                    <button onclick="handleVipAction(\'confirm\')" style="padding:12px 30px;background:#0073aa;color:#fff;border:none;border-radius:25px;cursor:pointer;font-size:16px;transition:all 0.3s;flex:1;">
                        &#128640; 立即开通
                    </button>
                </div>
            </div>
        </div>
        <script>
            // 禁止滚动条出现
            document.body.style.overflow = "hidden";
            
            // 统一操作处理
            function handleVipAction(type) {
                const overlay = document.getElementById("vip-prompt-overlay");
                if (type === "confirm") {
                    window.location.href = "'.esc_url($deepseek_vip_prompt_page).'";
                } else {
                    overlay.style.display = "none";
                    document.body.style.overflow = "auto";
                }
            }

            // 禁用ESC键关闭
            document.addEventListener("keydown", function(e) {
                if (e.key === "Escape") {
                    e.preventDefault();
                }
            });

            // 禁用右键菜单
            document.addEventListener("contextmenu", function(e) {
                e.preventDefault();
            }, false);
        </script>';
        
        $deepseekhtml = str_replace('</body>', $deepseekprompt.'</body>', $deepseekhtml);
    }
    echo $deepseekhtml;
}
// 对话 结束

// 文章总结 开始
// 文章发布时标记为需要生成文章总结
function deepseek_mark_post_for_summary($post_id, $post, $update) {
    if (!get_option('enable_ai_summary') || $post->post_status !== 'publish' || wp_is_post_revision($post_id)) {
        return;
    }

    update_post_meta($post_id, '_needs_ai_summary', 1);
}
add_action('wp_after_insert_post', 'deepseek_mark_post_for_summary', 10, 3);

// 文章第一次访问时生成总结
function deepseek_generate_summary_on_first_visit() {
    if (!get_option('enable_ai_summary') || !is_single()) {
        return;
    }

    $post_id = get_the_ID();
    if (!get_post_meta($post_id, '_needs_ai_summary', true)) {
        return;
    }

    $post = get_post($post_id);
    $content = $post->post_content;

    // 明确传入类型
    $summary = deepseek_call_ai_api($content, 'summary');

    if ($summary) {
        update_post_meta($post_id, '_ai_summary', $summary);
        delete_post_meta($post_id, '_needs_ai_summary');
    }
}
add_action('template_redirect', 'deepseek_generate_summary_on_first_visit');

// 调用AI接口生成文章总结
function deepseek_call_ai_api($content, $interface_type = 'summary') {
    $api_key = '';
    $model = '';
    $url = '';

    // 根据传入的接口类型选择配置，默认使用summary
    $interface_choice = ($interface_type === 'summary') 
        ? get_option('summary_interface_choice', 'deepseek') 
        : get_option('chat_interface_choice', 'deepseek');

    // 获取模型参数并取第一个模型
    switch ($interface_choice) {
        case 'deepseek':
            $api_key = get_option('deepseek_api_key');
            $model_string = get_option('deepseek_model', 'deepseek-chat');
            $url = 'https://api.deepseek.com/chat/completions';
            break;
        case 'doubao':
            $api_key = get_option('doubao_api_key');
            $model_string = get_option('doubao_model', '');
            $url = 'https://ark.cn-beijing.volces.com/api/v3/chat/completions';
            break;
        case 'hunyuan':
            $api_key = get_option('hunyuan_api_key');
            $model_string = get_option('hunyuan_model', '');
            $url = 'https://api.hunyuan.cloud.tencent.com/v1/chat/completions';
            break;            
        case 'qwen':
            $api_key = get_option('qwen_api_key');
            $model_string = get_option('qwen_text_model', 'qwen-max');
            $url = 'https://dashscope.aliyuncs.com/compatible-mode/v1/chat/completions';
            break;
        case 'kimi':
            $api_key = get_option('kimi_api_key');
            $model_string = get_option('kimi_model', '');
            $url = 'https://api.moonshot.cn/v1/chat/completions';
            break;       
        case 'openai':
            $api_key = get_option('openai_api_key');
            $model_string = get_option('openai_model', '');
            $url = 'https://api.openai.com/v1/chat/completions';
            break;
        case 'grok':
            $api_key = get_option('grok_api_key');
            $model_string = get_option('grok_model', '');
            $url = 'https://api.x.ai/v1/chat/completions';
            break;               
        case 'qianfan':
            $api_key = get_option('qianfan_api_key');
            $model_string = get_option('qianfan_model', '');
            $url = 'https://qianfan.baidubce.com/v2/chat/completions';
            break;
        case 'xunfei':
            $api_key = get_option('xunfei_api_key');
            $model_string = get_option('xunfei_model', '');
            $url = 'https://spark-api-open.xf-yun.com/v1/chat/completions';
            break;                                               
        case 'custom':
            $api_key = get_option('custom_api_key');
            $model_string = get_option('custom_model_params', '');
            $url = get_option('custom_model_url');
            if (empty($api_key) || empty($model_string) || empty($url)) {
                error_log('自定义模型设置不完整');
                return false;
            }
            break;            
        default:
            error_log('未知的接口类型: ' . $interface_choice);
            return false;
    }

    // 处理多模型参数，取第一个模型
    $model_list = array_filter(array_map('trim', explode(',', $model_string)));
    $model = !empty($model_list) ? $model_list[0] : ''; // 如果有多个模型，取第一个；否则为空

    // 检查必要参数
    if (empty($api_key) || empty($model) || empty($url)) {
        error_log('AI接口配置缺失 - API Key: ' . ($api_key ? '已设置' : '未设置') . 
                  ', Model: ' . ($model ? $model : '未设置') . 
                  ', URL: ' . ($url ? $url : '未设置'));
        return false;
    }

    // 构建请求数据
    $data = array(
        'model' => $model,
        'messages' => array(
            array(
                'role' => 'system',
                'content' => 'You are a helpful assistant.'
            ),
            array(
                'role' => 'user',
                'content' => '请为以下文章生成一句话总结，总结不要超过50个字，不要添加任何前缀或标题：' . $content
            )
        )
    );

    // 发送请求
    $response = wp_remote_post($url, array(
        'headers' => array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $api_key
        ),
        'body' => json_encode($data),
        'timeout' => 30
    ));

    if (is_wp_error($response)) {
        error_log('AI 接口请求失败：' . $response->get_error_message());
        return false;
    }

    $body = wp_remote_retrieve_body($response);
    $result = json_decode($body, true);

    if (isset($result['choices'][0]['message']['content'])) {
        $summary = trim($result['choices'][0]['message']['content']);
        $summary = preg_replace('/^(摘要|总结|文章摘要|摘要：|文章摘要：)\s*/', '', $summary);
        return $summary;
    }

    error_log('AI 接口返回结果异常：' . print_r($result, true));
    return false;
}

// 在前台文章页面插入总结
function deepseek_display_ai_summary($content) {
    if (!get_option('enable_ai_summary') || !is_single()) {
        return $content;
    }

    $post_id = get_the_ID();
    $summary = get_post_meta($post_id, '_ai_summary', true);

    if ($summary) {
        $title = '来自AI助手的总结';
        $summary_html = '
            <div class="ai-summary-container">
                <div class="ai-summary-title">' . esc_html($title) . '</div>
                <div class="ai-summary-content">' . esc_html($summary) . '</div>
            </div>
        ';
        $content = $summary_html . $content;
    }

    return $content;
}
add_filter('the_content', 'deepseek_display_ai_summary');

// 动态加载总结CSS和JavaScript
function deepseek_output_inline_styles() {
    if (!get_option('enable_ai_summary') || !is_single()) {
        return;
    }

    // 获取当前文章ID
    $post_id = get_the_ID();

    // 检查文章是否有AI总结
    if (!get_post_meta($post_id, '_ai_summary', true)) {
        return;
    }

    // 输出CSS样式
    $css = '
        <style type="text/css">
            .ai-summary-container {
                background-color: #f0f4f8;
                border: 1px solid #d1e0e8;
                border-radius: 12px;
                padding: 10px;
                margin: 10px 0;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
                transition: box-shadow 0.3s ease, transform 0.3s ease;
            }

            .ai-summary-container:hover {
                box-shadow: 0 6px 16px rgba(0, 0, 0, 0.15);
                transform: translateY(-2px);
            }

            .ai-summary-title {
                text-align: center;
                font-size: 15px;
                font-weight: bold;
                color: #2c3e50;
                margin-bottom: 5px;
            }

            .ai-summary-content {
                font-size: 16px;
                line-height: 1.6;
                color: #34495e;
                text-align: center;
                position: relative;
                padding: 5px 0;
            }
        </style>
    ';
    echo $css;

    // 打字效果JavaScript
    echo '
        <script type="text/javascript">
            document.addEventListener("DOMContentLoaded", function() {
                var aiSummaryContent = document.querySelector(".ai-summary-content");
                if (aiSummaryContent) {
                    var summaryText = aiSummaryContent.innerHTML;
                    aiSummaryContent.innerHTML = "";

                    var i = 0;
                    var typingSpeed = 50; // 每个字符的显示速度（毫秒）

                    function typeSummary() {
                        if (i < summaryText.length) {
                            aiSummaryContent.innerHTML += summaryText.charAt(i);
                            i++;
                            requestAnimationFrame(typeSummary, typingSpeed);
                        }
                    }

                    // 页面加载完后再开始打字效果
                    requestAnimationFrame(typeSummary, 300); // 延时300ms开始
                }
            });
        </script>
    ';
}
add_action('wp_head', 'deepseek_output_inline_styles');
// 文章总结 结束


// 文章生成 开始
// 文章生成页面
function deepseek_render_article_generator_page() {
    ?>
    <div class="wrap">
        <h1>文章生成</h1>
        <form method="post" action="" id="article-form">
            <p><strong>关键词(比如: 人工智能)：</strong></p>
            <input type="text" name="keyword" style="width: 500px;" />

            <p><strong>选择文章分类：</strong></p>
            <?php
            $categories = get_categories();
            if ($categories) {
                echo '<select name="category_id">';
                foreach ($categories as $category) {
                    echo '<option value="' . $category->term_id . '">' . $category->name . '</option>';
                }
                echo '</select>';
            }
            ?>

            <p><strong>文章标签：</strong></p>
            <input type="text" name="post_tags" id="post_tags" style="width: 500px;" placeholder="多个标签用英文逗号分隔，如：科技,AI,教程" />

            <p><strong>选择接口(模型需要支持长文本)：</strong></p>
            <?php
            $interface_choice = get_option('chat_interface_choice', 'deepseek');
            ?>
            <select name="interface_choice">
                <option value="deepseek" <?php selected($interface_choice, 'deepseek'); ?>>DeepSeek</option>
                <option value="doubao" <?php selected($interface_choice, 'doubao'); ?>>豆包AI</option>
                <option value="qwen" <?php selected($interface_choice, 'qwen'); ?>>通义千问</option>
                <option value="custom" <?php selected($interface_choice, 'custom'); ?>>自定义模型</option>
            </select>

            <p><strong>启用联网搜索（仅限通义千问qwen-max、qwen-plus、qwen-turbo模型）：</strong></p>
            <input type="checkbox" name="enable_search" id="enable_search" value="1" />

            <p><input type="button" value="生成文章" class="button-primary" id="generate-button" /></p>

            <div id="generation-status" style="display: none; color: #666;">正在生成中...</div>
            <div id="timeout-status" style="display: none; color: red;"></div> <!-- 显示错误信息 -->

            <p><strong>文章标题：</strong></p>
            <input type="text" name="post_title" id="post_title" value="" style="width: 50%;"/>

            <p><strong>文章内容：</strong></p>
            <?php
            wp_editor('', 'post_content', array('textarea_name' => 'post_content', 'textarea_rows' => 10));
            ?>

            <p><input type="submit" name="publish_article" value="发布文章" class="button-primary" id="publish-button" /></p>

            <div id="publish-result" style="display: none; margin-top: 10px;"></div>
        </form>
        生成的标题和内容还是需要自己再修改下，只适合纯文本内容的文章生成。
    </div>

    <script>
    document.getElementById('generate-button').addEventListener('click', function() {
        document.getElementById('generation-status').style.display = 'block';
        document.getElementById('timeout-status').style.display = 'none';

        var keyword = document.querySelector('input[name="keyword"]').value;
        var interface_choice = document.querySelector('select[name="interface_choice"]').value;
        var enable_search = document.querySelector('input[name="enable_search"]').checked ? 1 : 0;

        var sseUrl = ajaxurl + '?action=generate_article_stream_ajax'
            + '&keyword=' + encodeURIComponent(keyword)
            + '&interface_choice=' + encodeURIComponent(interface_choice)
            + '&enable_search=' + encodeURIComponent(enable_search);

        if (typeof(EventSource) !== "undefined") {
            var eventSource = new EventSource(sseUrl);
            var articleContent = "";
            eventSource.onmessage = function(event) {
                try {
                    var data = JSON.parse(event.data);
                    if (data.error) {
                        // 如果后端返回错误，显示在timeout-status中
                        document.getElementById('timeout-status').innerText = data.error;
                        document.getElementById('timeout-status').style.display = 'block';
                        document.getElementById('generation-status').style.display = 'none';
                        eventSource.close();
                    } else if (data.content) {
                        articleContent += data.content;
                        var contentWithBr = articleContent.replace(/\n/g, '<br>');
                        if (tinymce.get('post_content')) {
                            tinymce.get('post_content').setContent(contentWithBr);
                        } else {
                            document.getElementById('post_content').value = contentWithBr;
                        }
                    }
                } catch (e) {
                    console.error("解析SSE数据错误", e);
                    document.getElementById('timeout-status').innerText = '数据解析错误，请重试。';
                    document.getElementById('timeout-status').style.display = 'block';
                    document.getElementById('generation-status').style.display = 'none';
                    eventSource.close();
                }
            };
            eventSource.addEventListener('done', function(event) {
                var lines = articleContent.split("\n");
                if (lines.length > 0) {
                    document.getElementById('post_title').value = lines[0];
                }
                document.getElementById('generation-status').style.display = 'none';
                eventSource.close();
            });
            eventSource.onerror = function(event) {
                console.error("SSE 连接错误", event);
                document.getElementById('timeout-status').innerText = '连接错误，请检查网络或接口配置。';
                document.getElementById('timeout-status').style.display = 'block';
                document.getElementById('generation-status').style.display = 'none';
                eventSource.close();
            };
        } else {
            document.getElementById('generation-status').style.display = 'none';
            document.getElementById('timeout-status').innerText = '您的浏览器不支持服务器发送事件 (SSE)，请更换浏览器。';
            document.getElementById('timeout-status').style.display = 'block';
        }
    });

    // 发布文章
    document.getElementById('publish-button').addEventListener('click', function(e) {
        e.preventDefault();
        var post_title = document.getElementById('post_title').value;
        var post_content = tinymce.get('post_content').getContent();
        var category_id = document.querySelector('select[name="category_id"]').value;
        var post_tags = document.getElementById('post_tags').value;

        var data = {
            action: 'publish_article_ajax',
            post_title: post_title,
            post_content: post_content,
            category_id: category_id,
            post_tags: post_tags
        };

        jQuery.post(ajaxurl, data, function(response) {
            var resultDiv = document.getElementById('publish-result');
            if (response.success) {
                resultDiv.style.display = 'block';
                resultDiv.innerHTML = '<span style="color: green;">' + response.data.message + '</span>';
            } else {
                resultDiv.style.display = 'block';
                resultDiv.innerHTML = '<span style="color: red;">' + response.data.message + '</span>';
            }
        }).fail(function() {
            var resultDiv = document.getElementById('publish-result');
            resultDiv.style.display = 'block';
            resultDiv.innerHTML = '<span style="color: red;">发布文章失败，请重试。</span>';
        });
    });
    </script>
    <?php
}

// SSE流式文章生成处理函数
function deepseek_generate_article_stream_ajax() {
    ignore_user_abort(true);
    set_time_limit(0);
    if (ob_get_length()) ob_end_clean();

    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('X-Accel-Buffering: no');
    header('Content-Encoding: none');

    while (ob_get_level()) ob_end_flush();
    ob_implicit_flush(true);

    $keyword = isset($_GET['keyword']) ? sanitize_text_field($_GET['keyword']) : '';
    $interface_choice = isset($_GET['interface_choice']) ? sanitize_text_field($_GET['interface_choice']) : '';
    $enable_search = isset($_GET['enable_search']) ? intval($_GET['enable_search']) : 0;

    if (empty($keyword) || empty($interface_choice)) {
        echo "data: " . json_encode(['error' => '缺少必要参数']) . "\n\n";
        flush();
        exit;
    }

    $api_key = get_option($interface_choice . '_api_key');
    $model_string = ($interface_choice === 'custom') ? get_option('custom_model_params') : 
                    ($interface_choice === 'qwen' ? get_option('qwen_text_model') : get_option($interface_choice . '_model'));

    if ($interface_choice === 'deepseek') {
        $url = 'https://api.deepseek.com/chat/completions';
        $default_model = 'deepseek-chat';
    } elseif ($interface_choice === 'doubao') {
        $url = 'https://ark.cn-beijing.volces.com/api/v3/chat/completions';
        $default_model = '';
    } elseif ($interface_choice === 'qwen') {
        $url = 'https://dashscope.aliyuncs.com/compatible-mode/v1/chat/completions';
        $default_model = 'qwen-max';
    } elseif ($interface_choice === 'custom') {
        $url = get_option('custom_model_url');
        $default_model = '';
    } else {
        echo "data: " . json_encode(['error' => '不支持的接口']) . "\n\n";
        flush();
        exit;
    }

    // 处理多模型参数，取第一个模型
    $model_list = array_filter(array_map('trim', explode(',', $model_string)));
    $model = !empty($model_list) ? $model_list[0] : $default_model;

    if (empty($api_key) || empty($model) || empty($url)) {
        echo "data: " . json_encode(['error' => '接口配置缺失 - API Key: ' . ($api_key ? '已设置' : '未设置') . 
                                     ', Model: ' . ($model ? $model : '未设置') . 
                                     ', URL: ' . ($url ? $url : '未设置')]) . "\n\n";
        flush();
        exit;
    }

    // 检查联网搜索条件
    $supported_qwen_models = ['qwen-max', 'qwen-plus', 'qwen-turbo'];
    $is_qwen_search_supported = ($interface_choice === 'qwen' && in_array($model, $supported_qwen_models));
    
    if ($enable_search && !$is_qwen_search_supported) {
        echo "data: " . json_encode(['error' => '联网搜索仅支持通义千问的qwen-max、qwen-plus、qwen-turbo 模型']) . "\n\n";
        flush();
        exit;
    }

    // 根据是否启用联网搜索设置提示词
    $prompt = $enable_search && $is_qwen_search_supported
        ? "请根据关键词 '{$keyword}' 进行实时全网联网搜索，获取今天最新的资料、数据或资讯报道。确保搜索结果是最新的，并且基于这些最新信息撰写一篇相关文章。文章标题应简洁明了，直接反映文章的核心内容。文章内容应结构清晰，逻辑严谨，包含必要的背景信息、最新动态、数据分析或专家观点等。确保文章内容准确、权威，并注明信息来源。文章行首不要带*号或多个#号，也不要带Markdown格式符号。请务必在撰写文章前完成实时联网搜索，以确保内容基于最新资料。"
        : "根据关键词 '{$keyword}' 写一篇文章，包括标题和内容。要求：1. 标题简洁且与关键词相关；2. 内容逻辑清晰，围绕关键词展开，语言流畅；3. 行首不要使用Markdown符号，也不要带特殊符号；4. 文章结构完整，段落分明。";

    $payload = [
        'model' => $model,
        'messages' => [
            ['role' => 'system', 'content' => 'You are a helpful assistant.'],
            ['role' => 'user', 'content' => $prompt]
        ],
        'stream' => true,
    ];

    // 如果是通义千问且启用联网搜索，添加enable_search参数
    if ($is_qwen_search_supported && $enable_search) {
        $payload['enable_search'] = true;
    }

    $headers = [
        'Authorization: Bearer ' . $api_key,
        'Content-Type: application/json',
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);

    curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $chunk) {
        $lines = explode("\n", $chunk);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            if (strpos($line, 'data: [DONE]') !== false) {
                echo "event: done\n";
                echo "data: " . json_encode(['message' => '流结束']) . "\n\n";
                flush();
                continue;
            }
            if (strpos($line, 'data:') === 0) {
                $jsonStr = trim(substr($line, 5));
                if (!empty($jsonStr)) {
                    $data = json_decode($jsonStr, true);
                    if (isset($data['choices'][0]['delta']['content'])) {
                        $content = $data['choices'][0]['delta']['content'];
                        echo "data: " . json_encode(['content' => $content]) . "\n\n";
                        flush();
                    }
                }
            }
        }
        return strlen($chunk);
    });

    curl_exec($ch);
    if (curl_errno($ch)) {
        echo "data: " . json_encode(['error' => curl_error($ch)]) . "\n\n";
        flush();
    }
    curl_close($ch);
    exit;
}
add_action('wp_ajax_generate_article_stream_ajax', 'deepseek_generate_article_stream_ajax');
add_action('wp_ajax_nopriv_generate_article_stream_ajax', 'deepseek_generate_article_stream_ajax');

// 发布文章的AJAX处理函数
function deepseek_publish_article_ajax() {
    // 获取请求参数
    $post_title = sanitize_text_field($_POST['post_title']);
    $post_content = wp_kses_post($_POST['post_content']); // 确保内容安全
    $category_id = intval($_POST['category_id']);
    $post_tags = isset($_POST['post_tags']) ? sanitize_text_field($_POST['post_tags']) : '';

    // 创建新的文章
    $post_data = array(
        'post_title'    => $post_title,
        'post_content'  => $post_content,
        'post_status'   => 'publish',
        'post_category' => array($category_id),
        'post_author'   => get_current_user_id(),
    );

    // 插入文章
    $post_id = wp_insert_post($post_data);

    if ($post_id) {
        // 处理标签
        if (!empty($post_tags)) {
            $tags_array = array_map('trim', explode(',', $post_tags));
            wp_set_post_tags($post_id, $tags_array, true);
        }
        wp_send_json_success(array('message' => '文章已成功发布!', 'post_id' => $post_id));
    } else {
        wp_send_json_error(array('message' => '文章发布失败'));
    }

    wp_die();
}
add_action('wp_ajax_publish_article_ajax', 'deepseek_publish_article_ajax');
add_action('wp_ajax_nopriv_publish_article_ajax', 'deepseek_publish_article_ajax');
// 文章生成 结束


// 处理AI对话语音朗读的TTS请求
function deepseek_tts() {
    $text = isset($_POST['text']) ? wp_strip_all_tags($_POST['text']) : '';
    if ( empty($text) ) {
        wp_send_json_error('文本为空');
    }

    // 每50个字符一段
    $segment_length = 50;
    $segments = array();
    $text_length = mb_strlen($text, 'UTF-8');
    for ($i = 0; $i < $text_length; $i += $segment_length) {
        $segments[] = mb_substr($text, $i, $segment_length, 'UTF-8');
    }

    // 从wpatai_settings中读取语音合成接口设置
    $options = get_option('wpatai_settings');
    $interface = isset($options['tts_interface']) ? $options['tts_interface'] : 'tencent';

    $audio_urls = array();
    // 按分段调用wpatai_generate_tts_audio进行语音合成
    foreach ($segments as $segment) {
        $audio_url = wpatai_generate_tts_audio( $segment, $interface );
        if ( is_wp_error($audio_url) ) {
            wp_send_json_error( $audio_url->get_error_message() );
        }
        $audio_urls[] = $audio_url;
    }
    wp_send_json_success( array('audio_urls' => $audio_urls) );
}
add_action('wp_ajax_deepseek_tts', 'deepseek_tts');
add_action('wp_ajax_nopriv_deepseek_tts', 'deepseek_tts');

// 插件卸载时删除相关设置项
function deepseek_uninstall() {
    delete_option('deepseek_api_key');
    delete_option('deepseek_model');
    delete_option('doubao_api_key');
    delete_option('doubao_model');
    delete_option('hunyuan_api_key');
    delete_option('hunyuan_model');    
    delete_option('kimi_api_key');
    delete_option('kimi_model');
    delete_option('openai_api_key');
    delete_option('openai_model');
    delete_option('grok_api_key');
    delete_option('grok_model');    
    delete_option('qianfan_api_key');
    delete_option('qianfan_model');
    delete_option('qwen_api_key');
    delete_option('qwen_text_model');
    delete_option('qwen_image_model');
    delete_option('custom_api_key');
    delete_option('custom_model_params');
    delete_option('custom_model_url');
    delete_option('chat_interface_choice');
    delete_option('show_ai_helper');
    delete_option('enable_ai_summary');
    delete_option('enable_ai_voice_reading');
    delete_option('deepseek_custom_prompts');
    delete_option('keyword_list');
    delete_option('allowed_file_types');
}
register_uninstall_hook(__FILE__, 'deepseek_uninstall');

?>
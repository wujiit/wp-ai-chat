<?php
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
register_activation_hook(DEEPSEEK_PLUGIN_FILE, 'deepseek_create_table');

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
register_activation_hook(DEEPSEEK_PLUGIN_FILE, 'deepseek_create_agent_table');

// 插件列表页面添加设置入口
function deepseek_add_settings_link($links) {
    $settings_link = '<a href="admin.php?page=deepseek">设置</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(DEEPSEEK_PLUGIN_FILE), 'deepseek_add_settings_link');

// 注册激活钩子，确保插件启用时会调用子文件中的函数
register_activation_hook(DEEPSEEK_PLUGIN_FILE, 'docmee_create_ppt_page');

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
            'post_title'    => '启灵Ai助手',
            'post_content'  => '[deepseek_chat]',
            'post_status'   => 'publish',
            'post_author'   => 1,
            'post_type'     => 'page',
        ));
    }
}
register_activation_hook(DEEPSEEK_PLUGIN_FILE, 'deepseek_create_chat_page');

// 添加菜单入口
function deepseek_add_menu() {
    // 主菜单项
    add_menu_page(
        '启灵Ai助手', // 页面标题
        '启灵Ai助手', // 菜单标题
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

// 后台加载管理页面的 CSS
function deepseek_enqueue_admin_assets($hook_suffix) {
    // 检查是否是启灵Ai助手插件相关的页面（slug 都包含 deepseek 或 wpatai/wpaippt 相关的注册项，这里统一检查）
    if (strpos($hook_suffix, 'deepseek') !== false || strpos($hook_suffix, 'wpatai_settings_page') !== false || strpos($hook_suffix, 'wpaippt_settings_page') !== false) {
        wp_enqueue_style('deepseek-admin-style', DEEPSEEK_PLUGIN_URL . 'wpai-admin-style.css');
    }
}
add_action('admin_enqueue_scripts', 'deepseek_enqueue_admin_assets');

// 注册设置

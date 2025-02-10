<?php
/*
Plugin Name: 小半WordPress ai助手
Description: WordPress Ai助手插件，支持对话聊天、文章生成、文章总结，可对接deepseek、通义千问、豆包模型。
Plugin URI: https://www.jingxialai.com/4827.html
Version: 2.2
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

require_once plugin_dir_path(__FILE__) . 'wptranslate.php';

// 插件列表页面添加设置入口
function deepseek_add_settings_link($links) {
    $settings_link = '<a href="admin.php?page=deepseek">设置</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'deepseek_add_settings_link');

// 创建对话页面
function deepseek_create_chat_page() {
    // 检查页面是否已经存在
    $page = get_page_by_path('deepseek-chat');
    if (!$page) {
        // 创建页面
        $page_id = wp_insert_post(array(
            'post_title'    => 'Ai小助手',
            'post_content'  => '[deepseek_chat]',
            'post_status'   => 'publish',
            'post_author'   => 1,
            'post_type'     => 'page',
           // 'post_name'     => 'aichat'
        ));
    }
}
register_activation_hook(__FILE__, 'deepseek_create_chat_page');

// 注册设置
function deepseek_register_settings() {
    register_setting('deepseek_chat_options_group', 'deepseek_api_key'); // DeepSeek API Key
    register_setting('deepseek_chat_options_group', 'deepseek_model'); // DeepSeek 模型选择
    register_setting('deepseek_chat_options_group', 'doubao_api_key'); // 豆包AI API Key
    register_setting('deepseek_chat_options_group', 'doubao_model'); // 豆包AI 模型参数
    register_setting('deepseek_chat_options_group', 'qwen_api_key'); // 通义千问 API Key
    register_setting('deepseek_chat_options_group', 'qwen_model'); // 通义千问 模型选择
    register_setting('deepseek_chat_options_group', 'chat_interface_choice'); // 接口选择

    register_setting('deepseek_chat_options_group', 'show_ai_helper'); // ai助手显示
    register_setting('deepseek_chat_options_group', 'enable_ai_summary'); // 文章总结

    register_setting('deepseek_chat_options_group', 'enable_ai_voice_reading'); // AI对话语音朗读

    add_settings_section('deepseek_main_section', '基础设置', null, 'deepseek-chat');

    // DeepSeek 配置项
    add_settings_field('deepseek_api_key', 'DeepSeek API Key', 'deepseek_api_key_callback', 'deepseek-chat', 'deepseek_main_section');
    add_settings_field('deepseek_model', 'DeepSeek 模型', 'deepseek_model_callback', 'deepseek-chat', 'deepseek_main_section');

    // 豆包AI 配置项
    add_settings_field('doubao_api_key', '豆包AI API Key', 'doubao_api_key_callback', 'deepseek-chat', 'deepseek_main_section');
    add_settings_field('doubao_model', '豆包AI 模型参数', 'doubao_model_callback', 'deepseek-chat', 'deepseek_main_section');

    // 通义千问 配置项
    add_settings_field('qwen_api_key', '通义千问 API Key', 'qwen_api_key_callback', 'deepseek-chat', 'deepseek_main_section');
    add_settings_field('qwen_model', '通义千问 模型选择', 'qwen_model_callback', 'deepseek-chat', 'deepseek_main_section');

    // 接口选择框
    add_settings_field('chat_interface_choice', '选择对话接口', 'chat_interface_choice_callback', 'deepseek-chat', 'deepseek_main_section');

    // 文章总结框
    add_settings_field('enable_ai_summary', '文章AI总结(需要文本模型)', 'enable_ai_summary_callback', 'deepseek-chat', 'deepseek_main_section');

    // ai入口
    add_settings_field('show_ai_helper', '网站前台显示AI助手入口', 'show_ai_helper_callback', 'deepseek-chat', 'deepseek_main_section');

    // AI对话语音朗读
    add_settings_field('enable_ai_voice_reading', 'AI对话语音朗读', 'enable_ai_voice_reading_callback', 'deepseek-chat', 'deepseek_main_section');
}
add_action('admin_init', 'deepseek_register_settings');

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

// 在网站前台显示AI助手入口
function deepseek_display_ai_helper() {
    // 只在没有 [deepseek_chat] 短代码的页面显示入口
    if (get_option('show_ai_helper', '0') == '1' && !is_page_with_deepseek_chat_shortcode()) {
        echo '<div id="ai-helper-button" style="
            position: fixed;
            right: 5%;
            bottom: 50%;
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
            <span style="font-size: 24px;">&#129503;</span> AI 助手
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

    // 如果没有，直接返回 false
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

// 通义千问 API Key 输入框回调
function qwen_api_key_callback() {
    $api_key = get_option('qwen_api_key');
    echo '<input type="text" name="qwen_api_key" value="' . esc_attr($api_key) . '" style="width: 500px;" />';
}

// 通义千问 模型选择框回调
function qwen_model_callback() {
    $model = get_option('qwen_model', 'qwen-max'); // 默认模型为 qwen-max
    ?>
    <select name="qwen_model">
        <option value="qwen-max" <?php selected($model, 'qwen-max'); ?>>qwen-max</option>
        <option value="qwen-plus" <?php selected($model, 'qwen-plus'); ?>>qwen-plus</option>
        <option value="qwen-turbo" <?php selected($model, 'qwen-turbo'); ?>>qwen-turbo</option>
        <option value="qwen-long" <?php selected($model, 'qwen-long'); ?>>qwen-long</option>
        <option value="qwen2.5-14b-instruct-1m" <?php selected($model, 'qwen2.5-14b-instruct-1m'); ?>>qwen2.5-14b-instruct-1m</option>
        <option value="qwen2.5-7b-instruct-1m" <?php selected($model, 'qwen2.5-7b-instruct-1m'); ?>>qwen2.5-7b-instruct-1m</option>
        <option value="qwen2.5-1.5b-instruct" <?php selected($model, 'qwen2.5-1.5b-instruct'); ?>>qwen2.5-1.5b-instruct</option>
        <option value="wanx2.1-t2i-turbo" <?php selected($model, 'wanx2.1-t2i-turbo'); ?>>wanx2.1-t2i-turbo图片生成</option>
        <option value="wanx2.1-t2i-plus" <?php selected($model, 'wanx2.1-t2i-plus'); ?>>wanx2.1-t2i-plus图片生成</option>
    </select>
    <?php
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

// deepseek api函数回调
function deepseek_api_key_callback() {
    $api_key = get_option('deepseek_api_key');
    echo '<input type="text" name="deepseek_api_key" value="' . esc_attr($api_key) . '" style="width: 500px;" />';
}

// deepseek 模型函数回调
function deepseek_model_callback() {
    $model = get_option('deepseek_model', 'deepseek-chat'); // 默认模型为deepseek-chat
    ?>
    <select name="deepseek_model">
        <option value="deepseek-chat" <?php selected($model, 'deepseek-chat'); ?>>DeepSeek-V3</option>
        <option value="deepseek-reasoner" <?php selected($model, 'deepseek-reasoner'); ?>>DeepSeek-R1</option>
    </select>
    <?php
}

// 接口选择函数回调
function chat_interface_choice_callback() {
    $choice = get_option('chat_interface_choice', 'deepseek'); // 默认选择DeepSeek
    ?>
    <select name="chat_interface_choice">
        <option value="deepseek" <?php selected($choice, 'deepseek'); ?>>DeepSeek</option>
        <option value="doubao" <?php selected($choice, 'doubao'); ?>>豆包AI</option>
        <option value="qwen" <?php selected($choice, 'qwen'); ?>>通义千问</option>
    </select>
    <?php
}

// 文章AI总结复选框回调
function enable_ai_summary_callback() {
    $enable_ai_summary = get_option('enable_ai_summary');
    echo '<input type="checkbox" name="enable_ai_summary" value="1" ' . checked(1, $enable_ai_summary, false) . ' />';
}

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
    // 子菜单项 - 翻译朗读
    add_submenu_page(
        'deepseek',
        '翻译朗读',
        '翻译朗读',
        'manage_options',
        'deepseek-translate',
        'wpatai_settings_page' // 翻译页面回调函数
    );
    
}
add_action('admin_menu', 'deepseek_add_menu');

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
        /* 标题样式 */
        .ai-wrap h1 {
            font-size: 24px;
            color: #23282d;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }
        /* 表单样式 */
        .ai-wrap form {
            margin-top: 20px;
        }
        /* 输入框样式 */
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
        /* 复选框样式 */
        .ai-wrap input[type="checkbox"] {
            margin-right: 10px;
        }
        /* 描述文字样式 */
        .ai-wrap .description {
            font-size: 13px;
            color: #666;
            margin-top: 5px;
        }
        /* 保存成功提示样式 */
        #deepseek-save-success {
            background: #d4edda;
            color: #155724;
            padding: 10px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
            border-radius: 5px;
            text-align: center;
        }
        /* 余额信息样式 */
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
        /* 提交按钮样式 */
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
                无法获取DeepSeek余额信息，请检查API Key是否正确。
            </div>
        <?php endif; ?>
        插件设置说明：https://www.wujiit.com/wpaidocs
    </div>
    <?php
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

// 加载CSS文件
function deepseek_enqueue_assets() {
    if (is_singular('page')) {
        global $post;
        if (has_shortcode($post->post_content, 'deepseek_chat')) { // 检查是否包含短代码
            // 加载 CSS
            wp_enqueue_style('deepseek-chat-style', plugin_dir_url(__FILE__) . 'style.css');
            
            // 加载 marked.min.js
            wp_enqueue_script('marked-js', plugin_dir_url(__FILE__) . 'marked.min.js', array(), null, true);
        }
    }
}
add_action('wp_enqueue_scripts', 'deepseek_enqueue_assets');


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

    // 调用AI接口生成总结
    $summary = deepseek_call_ai_api($content);

    if ($summary) {
        update_post_meta($post_id, '_ai_summary', $summary);
        delete_post_meta($post_id, '_needs_ai_summary');
    }
}
add_action('template_redirect', 'deepseek_generate_summary_on_first_visit');

// 调用AI接口生成文章总结
function deepseek_call_ai_api($content) {
    $api_key = '';
    $model = '';
    $url = '';

    // 根据选择的接口设置API Key、模型和URL
    $interface_choice = get_option('chat_interface_choice');
    switch ($interface_choice) {
        case 'deepseek':
            $api_key = get_option('deepseek_api_key');
            $model = get_option('deepseek_model');
            $url = 'https://api.deepseek.com/chat/completions';
            break;
        case 'doubao':
            $api_key = get_option('doubao_api_key');
            $model = get_option('doubao_model');
            $url = 'https://ark.cn-beijing.volces.com/api/v3/chat/completions';
            break;
        case 'qwen':
            $api_key = get_option('qwen_api_key');
            $model = get_option('qwen_model');
            $url = 'https://dashscope.aliyuncs.com/compatible-mode/v1/chat/completions';
            break;
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
        'body' => json_encode($data)
    ));

    // 记录错误日志
    if (is_wp_error($response)) {
        error_log('AI 接口请求失败：' . $response->get_error_message());
        return false;
    }

    $body = wp_remote_retrieve_body($response);
    $result = json_decode($body, true);

    if (isset($result['choices'][0]['message']['content'])) {
        // 移除可能的前缀
        $summary = trim($result['choices'][0]['message']['content']);
        $summary = preg_replace('/^(摘要|总结|文章摘要|摘要：|文章摘要：)\s*/', '', $summary);
        return $summary;
    }

    // 记录API返回错误日志
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
        $interface_choice = get_option('chat_interface_choice', 'deepseek');
        $title = '来自' . ($interface_choice === 'doubao' ? '豆包' : ($interface_choice === 'qwen' ? '通义千问' : 'DeepSeek')) . '的总结';

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

    // 获取当前文章 ID
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


// 对话 开始
// 添加对话页面的短代码
function deepseek_chat_shortcode() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'deepseek_chat_logs';
    $user_id = get_current_user_id();

    ob_start();
    if (!is_user_logged_in()) {
        echo '<div id="deepseek-chat-container">';
        echo '<div class="deepseek-login-prompt">请先登录才能使用Ai对话功能。</div>';
        echo '</div>';
    } else {
        // 加载历史记录
        $history = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_id = %d GROUP BY conversation_id ORDER BY created_at DESC",
            $user_id
        ));
        ?>
<div id="deepseek-chat-container">
    <!-- 历史对话框 -->
    <div id="deepseek-chat-history">
        <button id="deepseek-new-chat">开启新对话</button>
        <ul>
            <?php if (!empty($history)) : ?>
                <?php foreach ($history as $log) : ?>
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
    </div>

    <!-- 主对话框 -->
    <div id="deepseek-chat-main">
        <!-- 消息框 -->
        <div id="deepseek-chat-messages">
            <!-- 初始为空，点击历史记录后动态加载 -->
            <div class="message-bubble bot" id="chatbot-prompt">你好，我可以帮你写作、写文案、翻译，有问题请问我~</div>
        </div>

        <!-- 输入框和发送按钮 -->
        <div id="deepseek-chat-input-container">
            <textarea id="deepseek-chat-input" placeholder="输入你的消息..." rows="4"></textarea>
            <button id="deepseek-chat-send">发送</button>
        </div>
    </div>
</div>
<script>
    // 将Markdown文本转换为HTML
    function convertMarkdownToHTML(markdownText) {
        // 使用Marked库进行转换
        return marked.parse(markdownText);
    }
    var aiVoiceEnabled = <?php echo get_option('enable_ai_voice_reading', '0'); ?>;
</script>
<!-- REST API nonce，用于权限验证 -->
<script>
    var deepseek_rest_nonce = '<?php echo wp_create_nonce('wp_rest'); ?>';
</script>
<script>
    // 全局当前对话ID变量
    var currentConversationId = null; 

    // 封装一个设置对话ID的函数，同时更新localStorage
    function setCurrentConversationId(id) {
        currentConversationId = id;
        if (id) {
            localStorage.setItem('currentConversationId', id);
        } else {
            localStorage.removeItem('currentConversationId');
        }
    }

    // 页面加载时，检测localStorage中是否有对话ID
    window.addEventListener('load', function() {
        var storedId = localStorage.getItem('currentConversationId');
        if (storedId) {
            setCurrentConversationId(storedId);
            loadChatLog(storedId);
        }
    });
    
    // 默认提示    
    document.getElementById('deepseek-chat-input').addEventListener('input', function() {
        var prompt = document.getElementById('chatbot-prompt');
        if (prompt) {
            prompt.style.display = 'none'; // 隐藏提示消息
        }
    });

    // 发送消息
    document.getElementById('deepseek-chat-send').addEventListener('click', function() {
        var message = document.getElementById('deepseek-chat-input').value;
        if (message) {
            // 判断是否为新对话，保存当前消息作为对话标题
            var newConversation = false;
            var currentMessage = message; // 新对话时，用第一句作为标题
            if (!currentConversationId) {
                newConversation = true;
            }
            
            // 显示“小助手正在思考中...”的提示
            var thinkingMessage = document.createElement('div');
            thinkingMessage.id = 'deepseek-thinking-message';
            thinkingMessage.className = 'message-bubble bot';
            thinkingMessage.innerHTML = '小助手正在思考中...';
            document.getElementById('deepseek-chat-messages').appendChild(thinkingMessage);

            // 使用REST API接口，传输JSON数据，并附加nonce进行权限验证
            fetch('<?php echo esc_url( rest_url('deepseek/v1/send-message') ); ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': deepseek_rest_nonce
                },
                body: JSON.stringify({
                    message: message,
                    conversation_id: currentConversationId
                })
            })
            .then(response => {
                const contentType = response.headers.get('Content-Type') || '';
                // 如果返回JSON，则按非流式逻辑处理(图片生成)
                if (contentType.indexOf('application/json') !== -1) {
                    return response.json().then(data => ({ data, isJson: true }));
                } else {
                    // 否则走流式文本返回
                    return { response };
                }
            })
            .then(result => {
                if (result.isJson) {
                    var data = result.data;
                    if (data.success) {
                        if (data.is_image) {
                            // 图片生成处理
                            handleImageGeneration(data.task_id);

                            // 如果当前对话为空，则更新历史记录（仅新对话需要更新对话id）
                            if (!currentConversationId) {
                                var historyContainer = document.querySelector('#deepseek-chat-history ul');
                                var newChatItem = document.createElement('li');
                                newChatItem.setAttribute('data-conversation-id', data.conversation_id);
                                newChatItem.innerHTML = '<span class="deepseek-chat-title">' + data.conversation_title + '</span>' +
                                    '<button class="deepseek-delete-log" data-conversation-id="' + data.conversation_id + '">删除</button>';
                                historyContainer.insertBefore(newChatItem, historyContainer.firstChild);

                                // 绑定新历史记录的点击事件
                                newChatItem.addEventListener('click', function() {
                                    loadChatLog(data.conversation_id);
                                });

                                // 绑定新历史记录的删除按钮事件
                                newChatItem.querySelector('.deepseek-delete-log').addEventListener('click', function() {
                                    var conversationId = this.getAttribute('data-conversation-id');
                                    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/x-www-form-urlencoded',
                                        },
                                        body: 'action=deepseek_delete_log&conversation_id=' + conversationId
                                    }).then(response => response.json())
                                    .then(data => {
                                        if (data.success) {
                                            this.parentElement.remove();
                                            // 清空消息框内容
                                            document.getElementById('deepseek-chat-messages').innerHTML = '';
                                            // 重置当前对话id
                                            currentConversationId = null;
                                        }
                                    });
                                });

                                setCurrentConversationId(data.conversation_id);
                            }
                        }
                    }
                } else {
                    // 流式文本回复处理（文本对话分支）
                    var messagesContainer = document.getElementById('deepseek-chat-messages');
                    
                    // 移除“小助手正在思考中...”的提示
                    var thinkingMessage = document.getElementById('deepseek-thinking-message');
                    if (thinkingMessage) {
                        thinkingMessage.remove();
                    }

                    // 添加用户消息
                    messagesContainer.innerHTML += '<div class="message-bubble user">' + message + '</div>';
                    
                    // 添加一个消息框 div，用于显示AI回复
                    var botMessageContainer = document.createElement('div');
                    botMessageContainer.classList.add('message-bubble', 'bot');
                    botMessageContainer.textContent = '';  // 初始为空，逐步填充
                    messagesContainer.appendChild(botMessageContainer);

                    // 使用ReadableStream API 对SSE格式的数据做处理
                    const reader = result.response.body.getReader();
                    const decoder = new TextDecoder();
                    let botReply = '';
                    let buffer = '';
                    
                    // 修改后的processLine函数：在解析到conversation_id时，如果是新对话则更新历史记录
                    function processLine(line) {
                        line = line.trim();
                        if (!line.startsWith("data:")) return;
                        let dataPart = line.substring(5).trim();
                        if (dataPart === "[DONE]") return;
                        try {
                            let jsonData = JSON.parse(dataPart);
                            if (jsonData.conversation_id) {
                                // 如果是新对话，则在历史对话列表中插入对话标题（即用户第一条消息）
                                if (newConversation) {
                                    setCurrentConversationId(jsonData.conversation_id);
                                    var historyContainer = document.querySelector('#deepseek-chat-history ul');
                                    var newChatItem = document.createElement('li');
                                    newChatItem.setAttribute('data-conversation-id', jsonData.conversation_id);
                                    newChatItem.innerHTML = '<span class="deepseek-chat-title">' + currentMessage + '</span>' +
                                        '<button class="deepseek-delete-log" data-conversation-id="' + jsonData.conversation_id + '">删除</button>';
                                    historyContainer.insertBefore(newChatItem, historyContainer.firstChild);
                                    // 绑定点击加载对话
                                    newChatItem.addEventListener('click', function() {
                                        loadChatLog(jsonData.conversation_id);
                                    });
                                    // 绑定删除按钮事件
                                    newChatItem.querySelector('.deepseek-delete-log').addEventListener('click', function(e) {
                                        e.stopPropagation();
                                        var conversationId = this.getAttribute('data-conversation-id');
                                        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                                            method: 'POST',
                                            headers: {
                                                'Content-Type': 'application/x-www-form-urlencoded',
                                            },
                                            body: 'action=deepseek_delete_log&conversation_id=' + conversationId
                                        }).then(response => response.json())
                                        .then(data => {
                                            if (data.success) {
                                                this.parentElement.remove();
                                                document.getElementById('deepseek-chat-messages').innerHTML = '';
                                                setCurrentConversationId(null);
                                            }
                                        });
                                    });
                                    newConversation = false; // 标记已更新历史记录
                                } else {
                                    setCurrentConversationId(jsonData.conversation_id);
                                }
                                return;
                            }
                            if (jsonData && jsonData.choices && jsonData.choices.length > 0) {
                                let delta = jsonData.choices[0].delta;
                                if (delta && delta.content) {
                                    botReply += delta.content;
                                    botMessageContainer.textContent = botReply;
                                }
                            }
                        } catch(e) {
                            console.error("Error parsing SSE JSON", e);
                        }
                    }
                    
                    function readStream() {
                        reader.read().then(({done, value}) => {
                            if (done) {
                                // 处理最后剩余的不完整数据
                                if (buffer.length > 0) {
                                    let lines = buffer.split("\n");
                                    lines.forEach(processLine);
                                }
                                // 流结束后，将Markdown转换为HTML显示
                                botMessageContainer.innerHTML = convertMarkdownToHTML(botReply);
                            if (aiVoiceEnabled) {
                                var playIcon = document.createElement('span');
                                playIcon.classList.add('ai-tts-play');
                                playIcon.innerHTML = '&#128266;';
                                playIcon.style.marginLeft = '10px';

                                playIcon.addEventListener('click', function() {
                                    var audioElem = document.getElementById('ai-tts-audio');
                                    if (!audioElem) {
                                        audioElem = document.createElement('audio');
                                        audioElem.id = 'ai-tts-audio';
                                        audioElem.style.display = 'none';
                                        document.body.appendChild(audioElem);
                                    }
                                        // 先移除可能存在的旧提示
                                    var existingMessage = playIcon.nextElementSibling;
                                    if (existingMessage && existingMessage.classList.contains('tts-message')) {
                                        existingMessage.remove();
                                    }

                                    // 创建“语音准备中”提示
                                    var messageSpan = document.createElement('span');
                                    messageSpan.classList.add('tts-message');
                                    messageSpan.textContent = '语音准备中...';
                                    messageSpan.style.marginLeft = '5px';
                                    messageSpan.style.color = '#666';
                                    playIcon.after(messageSpan);

                                    if (audioElem.audioUrls) {
                                        if (!audioElem.paused) {
                                            audioElem.pause();
                                            playIcon.innerHTML = '&#128264;';
                                        } else {
                                            audioElem.play();
                                            playIcon.innerHTML = '&#128266;';
                                        }
                                        return;
                                    }
                                    var dataParams = new URLSearchParams();
                                    dataParams.append('action', 'deepseek_tts');
                                    dataParams.append('text', botReply);
                                    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/x-www-form-urlencoded',
                                        },
                                        body: dataParams
                                    })
                                    .then(response => response.json())
                                    .then(ttsData => {
                                        if (ttsData.success) {
                                            var audio_urls = ttsData.data.audio_urls;
                                            if (audio_urls && audio_urls.length > 0) {
                                                audioElem.audioUrls = audio_urls;
                                                audioElem.currentIndex = 0;
                                                audioElem.src = audio_urls[0];
                                                // 当音频开始播放时移除提示
                                                audioElem.onplay = function() {
                                                    messageSpan.remove();
                                                };

                                                audioElem.play();
                                                playIcon.innerHTML = '&#128266;';
                                                audioElem.onended = function() {
                                                    audioElem.currentIndex++;
                                                    if (audioElem.currentIndex < audio_urls.length) {
                                                        audioElem.src = audio_urls[audioElem.currentIndex];
                                                        audioElem.play();
                                                    } else {
                                                        delete audioElem.audioUrls;
                                                        audioElem.currentIndex = 0;
                                                        playIcon.innerHTML = '&#128266;';
                                                    }
                                                };
                                            }
                                        } else {
                                            messageSpan.textContent = '语音朗读失败';
                                            messageSpan.style.color = 'red';
                                        }
                                    })
                                    .catch(() => {
                                        messageSpan.textContent = '请求错误，请重试';
                                        messageSpan.style.color = 'red';
                                    });
                                });

                                botMessageContainer.appendChild(playIcon);
                                }
                                // 清空输入框
                                document.getElementById('deepseek-chat-input').value = '';
                                // 滚动消息框到最底部
                                messagesContainer.scrollTop = messagesContainer.scrollHeight;
                                return;
                            }
                            const chunk = decoder.decode(value, {stream: true});
                            buffer += chunk;
                            let lines = buffer.split("\n");
                            // 将最后一行可能不完整的数据保留到buffer中
                            buffer = lines.pop();
                            lines.forEach(processLine);
                            readStream();
                        });
                    }
                    readStream();
                }
            })
        // 如果fetch请求出错，则将提示信息修改为“网络错误，请稍后重试”
        .catch(error => {
            console.error('Fetch request failed:', error);
            var errorMsg = document.getElementById('deepseek-thinking-message');
            if (errorMsg) {
                errorMsg.innerHTML = '网络错误，请稍后重试';
            }
        });
        }
    });

    // 图片生成处理函数
    function handleImageGeneration(taskId) {
        var messagesContainer = document.getElementById('deepseek-chat-messages');
        var thinkingMessage = document.getElementById('deepseek-thinking-message');
        if (thinkingMessage) thinkingMessage.remove();

        // 添加用户消息
        var userMessage = document.createElement('div');
        userMessage.className = 'message-bubble user';
        userMessage.textContent = document.getElementById('deepseek-chat-input').value;
        messagesContainer.appendChild(userMessage);

        // 添加加载状态
        var loadingContainer = document.createElement('div');
        loadingContainer.className = 'message-bubble bot';
        loadingContainer.innerHTML = '图片生成中...';
        messagesContainer.appendChild(loadingContainer);

        // 轮询检查任务状态
        var checkInterval = setInterval(function() {
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=deepseek_check_image_task&task_id=' + taskId
            }).then(response => response.json())
            .then(data => {
                if (data.task_status === 'SUCCEEDED') {
                    clearInterval(checkInterval);
                    loadingContainer.remove();
                    
                    // 创建消息容器
                    var botMessage = document.createElement('div');
                    botMessage.className = 'message-bubble bot';

                    // 创建图片描述容器
                    var promptContainer = document.createElement('div');
                    promptContainer.className = 'image-prompt';
                    botMessage.appendChild(promptContainer);

                    // 创建图片容器
                    var imageContainer = document.createElement('img');
                    imageContainer.src = data.image_url;
                    imageContainer.style.maxWidth = '100%';
                    imageContainer.style.height = 'auto';
                    botMessage.appendChild(imageContainer);

                    // 将消息容器添加到消息框中
                    messagesContainer.appendChild(botMessage);

                    // 实现逐字显示效果
                    var actualPrompt = data.actual_prompt;
                    var index = 0;
                    var typingSpeed = 50; // 控制打字速度

                    function typeWriter() {
                        if (index < actualPrompt.length) {
                            promptContainer.innerHTML += actualPrompt.charAt(index);
                            index++;
                            requestAnimationFrame(typeWriter, typingSpeed);
                        }
                    }
                    typeWriter();
                    messagesContainer.scrollTop = messagesContainer.scrollHeight;
                }
            });
        }, 2000);
    }

    // 打字效果显示AI回复的函数
    function typeWriter(text, container, callback) {
        var i = 0;
        var speed = 50;
        container.innerHTML = '';
        
        function addChar() {
            if (i < text.length) {
                container.innerHTML += text.charAt(i);
                i++;
                requestAnimationFrame(addChar, speed);
            } else if (callback) {
                callback();
            }
        }
        addChar();
    }

    // 开启新对话时，清空消息区和localStorage中保存的当前对话ID
    document.getElementById('deepseek-new-chat').addEventListener('click', function() {
        document.getElementById('deepseek-chat-messages').innerHTML = '';
        document.getElementById('deepseek-chat-input').value = '';
        setCurrentConversationId(null);
    });

    //加载历史对话记录时的内容渲染
    function loadChatLog(conversationId) {
        fetch('<?php echo admin_url('admin-ajax.php'); ?>?action=deepseek_load_log&conversation_id=' + conversationId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                var messagesContainer = document.getElementById('deepseek-chat-messages');
                messagesContainer.innerHTML = '';
                data.messages.forEach(message => {
                    messagesContainer.innerHTML += '<div class="message-bubble user">' + message.message + '</div>';
                    messagesContainer.innerHTML += '<div class="message-bubble bot">' + convertMarkdownToHTML(message.response) + '</div>';
                });
                // 加载完历史对话后更新当前对话ID（同时存入localStorage）
                setCurrentConversationId(conversationId);
            }
        });
    }

    // 绑定历史对话框的点击事件
    document.querySelectorAll('#deepseek-chat-history li').forEach(item => {
        item.addEventListener('click', function() {
            var conversationId = this.getAttribute('data-conversation-id');
            loadChatLog(conversationId);
        });

        var deleteButton = item.querySelector('.deepseek-delete-log');
        if (deleteButton) {
            deleteButton.addEventListener('click', function() {
                var conversationId = this.getAttribute('data-conversation-id');
                fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=deepseek_delete_log&conversation_id=' + conversationId
                }).then(response => response.json())
                .then(data => {
                    if (data.success) {
                        this.parentElement.remove();
                        document.getElementById('deepseek-chat-messages').innerHTML = '';
                        setCurrentConversationId(null);
                    }
                });
            });
        }
    });
</script>
        <?php
    }
    return ob_get_clean();
}
add_shortcode('deepseek_chat', 'deepseek_chat_shortcode');


// 使用REST API 方式处理消息
function deepseek_send_message_rest( WP_REST_Request $request ) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'deepseek_chat_logs';

    $message = sanitize_text_field( $request->get_param('message') );
    $conversation_id = $request->get_param('conversation_id') ? intval($request->get_param('conversation_id')) : null;
    $user_id = get_current_user_id();
    $interface_choice = get_option('chat_interface_choice', 'deepseek');

    // 判断是否是图片生成模型
    $is_image_model = in_array(get_option('qwen_model'), ['wanx2.1-t2i-turbo', 'wanx2.1-t2i-plus']);

    if ($interface_choice === 'qwen' && $is_image_model) {
        // 图片生成分支（保持原有逻辑）
        $api_key = get_option('qwen_api_key');
        $model = get_option('qwen_model');
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
                'conversation_title'  => $message,
                'message' => $message,
                'response' => json_encode([
                    'task_id' => $task_id,
                    'status' => 'pending',
                    'message' => '图片生成中...'
                ])
            ]);

            if (!$conversation_id) {
                $conversation_id = $wpdb->insert_id;
                $wpdb->update($table_name, 
                    ['conversation_id' => $conversation_id], 
                    ['id' => $conversation_id]
                );
            }

            wp_send_json([
                'success' => true,
                'is_image' => true,
                'task_id' => $task_id,
                'conversation_id' => $conversation_id,
                'conversation_title'  => $message,
            ]);
        } else {
            wp_send_json([
                'success' => false, 
                'message' => '图片生成请求失败: ' . $response
            ]);
        }
    } else {
        // 文本对话分支（流式返回）
        switch ($interface_choice) {
            case 'deepseek':
                $api_key = get_option('deepseek_api_key');
                $model = get_option('deepseek_model', 'deepseek-chat');
                $api_url = 'https://api.deepseek.com/chat/completions';
                break;
            case 'doubao':
                $api_key = get_option('doubao_api_key');
                $model = get_option('doubao_model');
                $api_url = 'https://ark.cn-beijing.volces.com/api/v3/chat/completions';
                break;
            case 'qwen':
                $api_key = get_option('qwen_api_key');
                $model = get_option('qwen_model', 'qwen-max');
                $api_url = 'https://dashscope.aliyuncs.com/compatible-mode/v1/chat/completions';
                break;
            default:
                wp_send_json(['success' => false, 'message' => '无效的接口选择']);
        }

        if (empty($api_key)) {
            wp_send_json(['success' => false, 'message' => 'API Key 未设置']);
        }

        $messages = [['role' => 'system', 'content' => 'You are a helpful assistant.']];
        if ($conversation_id) {
            $history = $wpdb->get_results($wpdb->prepare(
                "SELECT message, response FROM $table_name 
                WHERE conversation_id = %d 
                ORDER BY id ASC",
                $conversation_id
            ));
            
            foreach ($history as $item) {
                $messages[] = ['role' => 'user', 'content' => $item->message];
                $messages[] = ['role' => 'assistant', 'content' => $item->response];
            }
        }
        $messages[] = ['role' => 'user', 'content' => $message];

        $data = [
            'model' => $model,
            'messages' => $messages,
            'stream' => true
        ];

        // 清空缓冲区，设置流式响应头
        if (ob_get_length()) { ob_end_clean(); }
        header('Content-Type: text/plain');
        header('Cache-Control: no-cache');
        header('Transfer-Encoding: chunked');
        header('Connection: keep-alive');

        $fullReply = '';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api_url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $api_key
        ]);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        // 利用write callback实现流式输出，并累计完整回复
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $chunk) use (&$fullReply) {
            echo $chunk;
            flush();
            $fullReply .= $chunk;
            return strlen($chunk);
        });
        curl_setopt($ch, CURLOPT_TIMEOUT, 0);
        curl_exec($ch);
        curl_close($ch);

        // 流式输出结束后，解析SSE数据提取有效内容并保存到数据库
        $lines = explode("\n", $fullReply);
        $processedReply = '';
        foreach ($lines as $line) {
            $line = trim($line);
            if (strpos($line, 'data:') === 0) {
                $dataPart = trim(substr($line, 5));
                if ($dataPart === '[DONE]') {
                    continue;
                }
                $jsonData = json_decode($dataPart, true);
                if ($jsonData && isset($jsonData['choices'][0]['delta']['content'])) {
                    $processedReply .= $jsonData['choices'][0]['delta']['content'];
                }
            }
        }
        $reply = $processedReply;
        $wpdb->insert($table_name, [
            'user_id'             => $user_id,
            'conversation_id'     => $conversation_id ?: 0,
            'conversation_title'  => $conversation_id ? '' : $message,
            'message'             => $message,
            'response'            => $reply
        ]);
        if (!$conversation_id) {
            $conversation_id = $wpdb->insert_id;
            $wpdb->update($table_name, 
                ['conversation_id' => $conversation_id], 
                ['id' => $conversation_id]
            );
        }

        // 输出一条SSE事件，将conversation_id返回给前端
        echo "\n";
        echo "data: " . json_encode(['conversation_id' => $conversation_id]) . "\n\n";
        flush();

        exit();
    }
}
//add_action('wp_ajax_deepseek_send_message', 'deepseek_send_message_rest');
//add_action('wp_ajax_nopriv_deepseek_send_message', 'deepseek_send_message_rest');

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
        
        if ($response && isset($response['image_url'])) {
            $html = '<div class="image-prompt">'.esc_html($response['actual_prompt']).'</div>';
            $html .= '<img src="'.esc_url($response['image_url']).'" style="max-width:100%;height:auto;" />';
            $processed[] = array(
                'message'  => esc_html($log->message),
                'response' => $html
            );
        } else {
            $processed[] = array(
                'message'  => $log->message,
                'response' => $log->response
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

    // 检查权限
    $log = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE conversation_id = %d AND user_id = %d LIMIT 1",
        $conversation_id, $user_id
    ));

    if ($log || current_user_can('manage_options')) {
        $wpdb->delete($table_name, array('conversation_id' => $conversation_id));
        wp_send_json(array('success' => true));
    } else {
        wp_send_json(array('success' => false, 'message' => '无权删除此记录。'));
    }
}
add_action('wp_ajax_deepseek_delete_log', 'deepseek_delete_log');

// 对话记录管理页面
function deepseek_render_logs_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'deepseek_chat_logs';

    // 删除记录
    if (isset($_GET['delete_log']) && current_user_can('manage_options')) {
        $log_id = intval($_GET['delete_log']);
        $wpdb->delete($table_name, array('id' => $log_id));
        echo '<div class="notice notice-success"><p>记录已删除。</p></div>';
    }

    // 分页处理
    $per_page = 20; // 每页显示的记录数
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $per_page;

    // 获取总记录数
    $total_logs = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");

    // 获取当前页的记录
    $logs = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name GROUP BY conversation_id ORDER BY created_at DESC LIMIT %d OFFSET %d",
        $per_page, $offset
    ));

    ?>
    <div class="wrap">
        <h1>用户AI对话记录</h1>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>用户ID</th>
                    <th>标题</th>
                    <th>时间</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($logs)) : ?>
                    <?php foreach ($logs as $log) : ?>
                        <tr>
                            <td><?php echo $log->user_id; ?></td>
                            <td><?php echo esc_html($log->conversation_title); ?></td>
                            <td><?php echo $log->created_at; ?></td>
                            <td>
                                <a href="?page=deepseek-logs&delete_log=<?php echo $log->id; ?>" class="button">删除</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="4">暂无记录。</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- 分页导航 -->
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <?php
                $total_pages = ceil($total_logs / $per_page);
                echo paginate_links(array(
                    'base' => add_query_arg('paged', '%#%'),
                    'format' => '',
                    'prev_text' => __('&laquo; 上一页'),
                    'next_text' => __('下一页 &raquo;'),
                    'total' => $total_pages,
                    'current' => $current_page,
                ));
                ?>
            </div>
        </div>
    </div>
    <?php
}
// 对话 结束


// 文章生成 开始
// 文章生成页面
function deepseek_render_article_generator_page() {
    ?>
    <div class="wrap">
        <h1>文章生成</h1>
        <form method="post" action="" id="article-form">
            <p><strong>提示词：</strong></p>
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

            <p><strong>选择接口(模型需要支持长文本)：</strong></p>
            <?php
            $interface_choice = get_option('chat_interface_choice', 'deepseek');
            ?>
            <select name="interface_choice">
                <option value="deepseek" <?php selected($interface_choice, 'deepseek'); ?>>DeepSeek</option>
                <option value="doubao" <?php selected($interface_choice, 'doubao'); ?>>豆包AI</option>
                <option value="qwen" <?php selected($interface_choice, 'qwen'); ?>>通义千问</option>
            </select>

            <p><input type="button" value="生成文章" class="button-primary" id="generate-button" /></p>

            <div id="generation-status" style="display: none; color: #666;">正在生成中...</div>
            <div id="timeout-status" style="display: none; color: red;">超时，请更换模型或者接口再重试，模型需要支持长文本。</div>

            <p><strong>文章标题：</strong></p>
            <input type="text" name="post_title" id="post_title" value="" style="width: 50%;"/>

            <p><strong>文章内容：</strong></p>
            <?php
            wp_editor('', 'post_content', array('textarea_name' => 'post_content', 'textarea_rows' => 10));
            ?>

            <p><input type="submit" name="publish_article" value="发布文章" class="button-primary" id="publish-button" /></p>

            <!-- 显示发布结果的区域 -->
            <div id="publish-result" style="display: none; margin-top: 10px;"></div>
        </form>
        生成的标题和内容还是需要自己再修改下。
    </div>

    <script>
    // 监听生成文章按钮点击事件
    document.getElementById('generate-button').addEventListener('click', function() {
        // 显示“正在生成中”提示
        document.getElementById('generation-status').style.display = 'block';
        document.getElementById('timeout-status').style.display = 'none'; // 隐藏超时提示

        var keyword = document.querySelector('input[name="keyword"]').value;
        var category_id = document.querySelector('select[name="category_id"]').value;
        var interface_choice = document.querySelector('select[name="interface_choice"]').value;

        var data = {
            action: 'generate_article_ajax',
            keyword: keyword,
            category_id: category_id,
            interface_choice: interface_choice
        };

        // 使用 AJAX 请求生成文章
        jQuery.post(ajaxurl, data, function(response) {
            document.getElementById('generation-status').style.display = 'none';
            if (response.success) {
                // 如果成功，填充标题和内容到文章编辑器
                document.getElementById("post_title").value = response.data.title;
                tinymce.get('post_content').setContent(response.data.content);
            } else {
                // 如果失败，显示超时提示
                document.getElementById('timeout-status').style.display = 'block';
            }
        }).fail(function() {
            // AJAX 请求失败时显示超时提示
            document.getElementById('generation-status').style.display = 'none';
            document.getElementById('timeout-status').style.display = 'block';
        });
    });

    // 监听发布文章按钮点击事件
    document.getElementById('publish-button').addEventListener('click', function(e) {
        e.preventDefault();  // 防止表单提交

        var post_title = document.getElementById('post_title').value;
        var post_content = tinymce.get('post_content').getContent();
        var category_id = document.querySelector('select[name="category_id"]').value;

        var data = {
            action: 'publish_article_ajax',
            post_title: post_title,
            post_content: post_content,
            category_id: category_id
        };

        // 使用 AJAX 请求发布文章
        jQuery.post(ajaxurl, data, function(response) {
            var resultDiv = document.getElementById('publish-result');
            if (response.success) {
                resultDiv.style.display = 'block';
                resultDiv.innerHTML = '<span style="color: green;">' + response.data.message + '</span>'; // 发布成功
            } else {
                resultDiv.style.display = 'block';
                resultDiv.innerHTML = '<span style="color: red;">' + response.data.message + '</span>'; // 发布失败
            }
        }).fail(function() {
            var resultDiv = document.getElementById('publish-result');
            resultDiv.style.display = 'block';
            resultDiv.innerHTML = '<span style="color: red;">发布文章失败，请重试。</span>'; // 请求失败
        });
    });
    </script>

    <?php
}

// 生成文章的 AJAX 处理函数
function deepseek_generate_article_ajax() {
    // 获取请求参数
    $keyword = sanitize_text_field($_POST['keyword']);
    $category_id = intval($_POST['category_id']);
    $interface_choice = sanitize_text_field($_POST['interface_choice']);

    // 调用生成文章的函数
    $article_data = generate_article($keyword, $interface_choice);

    if ($article_data) {
        wp_send_json_success($article_data); // 成功返回数据
    } else {
        wp_send_json_error(); // 失败时返回错误
    }

    wp_die();
}
add_action('wp_ajax_generate_article_ajax', 'deepseek_generate_article_ajax');
add_action('wp_ajax_nopriv_generate_article_ajax', 'deepseek_generate_article_ajax');

// 生成文章内容的函数
function generate_article($keyword, $interface_choice) {
    $api_key = get_option($interface_choice . '_api_key');
    $model = get_option($interface_choice . '_model');
    // 根据接口选择设置请求的 URL
    if ($interface_choice === 'deepseek') {
        $url = 'https://api.deepseek.com/chat/completions';
    } elseif ($interface_choice === 'doubao') {
        $url = 'https://ark.cn-beijing.volces.com/api/v3/chat/completions';
    } elseif ($interface_choice === 'qwen') {
        $url = 'https://dashscope.aliyuncs.com/compatible-mode/v1/chat/completions';
    } else {
        return null; // 不支持的接口
    }

    $body = json_encode(array(
        'model' => $model,
        'messages' => array(
            array('role' => 'system', 'content' => 'You are a helpful assistant.'),
            array('role' => 'user', 'content' => '根据关键词 "' . $keyword . '" 生成文章和标题')
        ),
        'stream' => ($interface_choice === 'deepseek') ? false : null
    ));

    $args = array(
        'body' => $body,
        'headers' => array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type'  => 'application/json',
        ),
        'timeout' => 120, // 超时时间设置为2分钟
    );

    $response = wp_remote_post($url, $args);

    if (is_wp_error($response)) {
        return null;
    }

    $body = wp_remote_retrieve_body($response);
    $status_code = wp_remote_retrieve_response_code($response);

    if ($status_code !== 200) {
        return null;
    }

    $data = json_decode($body, true);
    if (!isset($data['choices'][0]['message']['content'])) {
        return null;
    }

    $article_content = $data['choices'][0]['message']['content'];
    $article_title = substr($article_content, 0, strpos($article_content, "\n"));

    // 处理换行符并转换为合适的格式
    $article_content = nl2br($article_content); // 保留换行符

    // 将文章内容返回给前端
    return array(
        'title'   => $article_title,
        'content' => $article_content,
    );
}

// 发布文章的 AJAX 处理函数
function deepseek_publish_article_ajax() {
    // 获取请求参数
    $post_title = sanitize_text_field($_POST['post_title']);
    $post_content = wp_kses_post($_POST['post_content']); // 确保内容安全
    $category_id = intval($_POST['category_id']);

    // 创建新的文章
    $post_data = array(
        'post_title'    => $post_title,
        'post_content'  => $post_content,
        'post_status'   => 'publish', // 设置为发布状态
        'post_category' => array($category_id),
        'post_author'   => get_current_user_id(),
    );

    // 插入文章
    $post_id = wp_insert_post($post_data);

    if ($post_id) {
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
    // 按分段调用 wpatai_generate_tts_audio 进行语音合成
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

?>

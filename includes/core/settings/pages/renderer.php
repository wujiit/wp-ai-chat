<?php

function deepseek_get_settings_page_configs() {
    return array(
        'overview' => array(
            'slug' => 'deepseek',
            'menu_title' => '设置总览',
            'page_title' => '设置总览',
            'description' => '快速查看关键配置状态、风险提示与常用设置入口。',
            'fields' => array(),
        ),
        'general' => array(
            'slug' => 'deepseek-general',
            'menu_title' => '基础设置',
            'page_title' => '基础设置',
            'description' => '管理默认对话行为、上下文记忆与基础前台参数。',
            'fields' => array(
                'chat_interfaces',
                'default_chat_interface',
                'show_interface_switch',
                'deepseek_context_memory_limit',
                'deepseek_custom_prompts',
                'ai_tutorial_title',
                'ai_tutorial_url',
            ),
        ),
        'providers' => array(
            'slug' => 'deepseek-models',
            'menu_title' => '接口模型',
            'page_title' => '接口与模型',
            'description' => '按服务商配置 API Key 和模型参数，统一维护对话后端。',
            'fields' => array(
                'deepseek_api_key',
                'deepseek_model',
                'doubao_api_key',
                'doubao_model',
                'kimi_api_key',
                'kimi_model',
                'openai_api_key',
                'openai_model',
                'grok_api_key',
                'grok_model',
                'gemini_api_key',
                'gemini_model',
                'claude_api_key',
                'claude_model',
                'qianfan_api_key',
                'qianfan_model',
                'hunyuan_api_key',
                'hunyuan_model',
                'xunfei_api_key',
                'xunfei_model',
                'qwen_api_key',
                'qwen_text_model',
                'qwen_image_model',
                'qwen_video_model',
                'ollama_api_url',
                'ollama_model',
                'pollinations_model',
                'custom_api_key',
                'custom_model_params',
                'custom_model_url',
            ),
        ),
        'security' => array(
            'slug' => 'deepseek-security',
            'menu_title' => '风控权限',
            'page_title' => '风控与权限',
            'description' => '配置游客额度、关键词过滤、会员门槛和未登录提示。',
            'fields' => array(
                'deepseek_guest_chat_limit',
                'deepseek_guest_upload_limit',
                'deepseek_login_prompt',
                'enable_keyword_detection',
                'keyword_list',
                'deepseek_vip_check_enabled',
                'deepseek_vip_keyword',
                'deepseek_vip_prompt_page',
            ),
        ),
        'features' => array(
            'slug' => 'deepseek-features',
            'menu_title' => '功能内容',
            'page_title' => '功能与内容',
            'description' => '集中管理文件上传、语音、文章工具、智能体入口与公告。',
            'fields' => array(
                'enable_intelligent_agent',
                'enable_custom_entry',
                'custom_entry_title',
                'custom_entry_url',
                'enable_ai_voice_reading',
                'qwen_enable_search',
                'enable_file_upload',
                'allowed_file_types',
                'max_file_size',
                'show_ai_helper',
                'ai_helper_name',
                'ai_helper_icon',
                'ai_helper_background',
                'ai_helper_right',
                'ai_helper_bottom',
                'enable_ai_summary',
                'summary_interface_choice',
                'enable_article_analysis',
                'deepseek_announcement',
            ),
        ),
    );
}

function deepseek_render_settings_page() {
    deepseek_render_settings_overview_page();
}

function deepseek_render_settings_overview_page() {
    deepseek_render_settings_group_page('overview');
}

function deepseek_render_general_settings_page() {
    deepseek_render_settings_group_page('general');
}

function deepseek_render_model_settings_page() {
    deepseek_render_settings_group_page('providers');
}

function deepseek_render_security_settings_page() {
    deepseek_render_settings_group_page('security');
}

function deepseek_render_feature_settings_page() {
    deepseek_render_settings_group_page('features');
}

function deepseek_render_settings_group_page($group_key = 'general') {
    $configs = deepseek_get_settings_page_configs();
    if (!isset($configs[$group_key])) {
        $group_key = 'overview';
    }
    $config = $configs[$group_key];

    $balance = get_deepseek_balance();
    $api_key = get_option('deepseek_api_key');
    ?>
    <div class="ai-wrap">
        <h1>启灵Ai助手 · <?php echo esc_html($config['page_title']); ?></h1>

        <?php deepseek_render_settings_page_nav($group_key, $configs); ?>

        <?php if (isset($_GET['settings-updated']) && $_GET['settings-updated']): ?>
            <div id="deepseek-save-success">保存成功！</div>
            <script>
                setTimeout(function () {
                    var notice = document.getElementById('deepseek-save-success');
                    if (notice) {
                        notice.style.display = 'none';
                    }
                }, 1000);
            </script>
        <?php endif; ?>
        <?php if ('overview' === $group_key && isset($_GET['autofix']) && '1' === sanitize_text_field(wp_unslash($_GET['autofix']))): ?>
            <?php $autofix_count = isset($_GET['autofix_count']) ? intval($_GET['autofix_count']) : 0; ?>
            <div class="notice notice-success is-dismissible" style="margin: 12px 0;">
                <p>一键修复已完成，本次共处理 <?php echo intval($autofix_count); ?> 项配置。</p>
            </div>
        <?php endif; ?>

        <p class="description" style="margin: 12px 0 18px;">
            <?php echo esc_html($config['description']); ?>
        </p>

        <?php if ('overview' === $group_key): ?>
            <?php deepseek_render_settings_overview_content(); ?>
        <?php else: ?>
            <form method="post" action="options.php">
                <?php settings_fields('deepseek_chat_options_group'); ?>
                <?php deepseek_render_settings_group_fields($config['fields']); ?>
                <?php submit_button(); ?>
            </form>
        <?php endif; ?>

        <?php if ('providers' === $group_key): ?>
            <?php if ($balance !== false): ?>
                <div style="margin-top: 20px;">
                    <strong>DeepSeek 余额:</strong> <?php echo esc_html($balance); ?> CNY
                </div>
            <?php elseif (!empty($api_key)): ?>
                <div style="margin-top: 20px; color: red;">
                    无法获取DeepSeek余额信息，请检查DeepSeek官方API Key是否正确，如果你不用DeepSeek官方接口就无视。
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <p style="margin-top: 24px;">
            插件设置说明：<a href="https://www.wujiit.com/wpaidocs" target="_blank">https://www.wujiit.com/wpaidocs</a><br>
            Openai、Gemini、Claude接口只有在官方允许的地区才能访问<br>
            反馈问题请带上错误提示，插件加入了日志调用，方便快速查找问题所在，所以遇到问题了直接把网站错误日志发来。
        </p>
    </div>
    <?php
}

function deepseek_render_settings_page_nav($current_key, $configs) {
    echo '<h2 class="nav-tab-wrapper" style="margin-bottom: 16px;">';
    foreach ($configs as $key => $config) {
        $url = admin_url('admin.php?page=' . $config['slug']);
        $active_class = ($key === $current_key) ? ' nav-tab-active' : '';
        $count = count($config['fields']);
        echo '<a href="' . esc_url($url) . '" class="nav-tab' . esc_attr($active_class) . '">';
        echo esc_html($config['menu_title']) . ' (' . intval($count) . ')';
        echo '</a>';
    }
    echo '</h2>';
}

function deepseek_render_settings_group_fields($field_ids) {
    global $wp_settings_fields;

    $page = 'deepseek-chat';
    $section = 'deepseek_main_section';
    $registered_fields = isset($wp_settings_fields[$page][$section]) ? $wp_settings_fields[$page][$section] : array();

    if (empty($registered_fields)) {
        echo '<p>暂无可渲染的设置项，请检查设置注册逻辑。</p>';
        return;
    }

    echo '<table class="form-table" role="presentation">';

    foreach ($field_ids as $field_id) {
        if (!isset($registered_fields[$field_id])) {
            continue;
        }

        $field = $registered_fields[$field_id];
        $title = isset($field['title']) ? $field['title'] : '';
        $args = isset($field['args']) ? $field['args'] : array();

        echo '<tr>';
        if (!empty($args['label_for'])) {
            echo '<th scope="row"><label for="' . esc_attr($args['label_for']) . '">' . wp_kses_post($title) . '</label></th>';
        } else {
            echo '<th scope="row">' . wp_kses_post($title) . '</th>';
        }

        echo '<td>';
        if (is_callable($field['callback'])) {
            call_user_func($field['callback'], $args);
        }
        echo '</td>';
        echo '</tr>';
    }

    echo '</table>';
}

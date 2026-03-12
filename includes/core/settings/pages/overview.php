<?php

function deepseek_render_settings_overview_content() {
    $health = deepseek_collect_settings_health();
    $status_ok = empty($health['issues']);
    $auto_fixes = deepseek_get_auto_fix_recommendations();

    $quick_links = array(
        '基础设置' => 'deepseek-general',
        '接口模型' => 'deepseek-models',
        '风控权限' => 'deepseek-security',
        '功能内容' => 'deepseek-features',
        '对话记录' => 'deepseek-logs',
        '智能体应用' => 'deepseek-agents',
    );

    echo '<style>
        .deepseek-overview-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 14px; margin: 18px 0; }
        .deepseek-overview-card { background: #fff; border: 1px solid #dcdcde; border-left: 4px solid #2271b1; border-radius: 6px; padding: 14px 16px; }
        .deepseek-overview-card h3 { margin: 0 0 10px; font-size: 15px; }
        .deepseek-overview-status { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 12px; font-weight: 600; }
        .deepseek-overview-status.ok { background: #edfaef; color: #096f2e; }
        .deepseek-overview-status.warn { background: #fff8e5; color: #8a5a00; }
        .deepseek-overview-status.error { background: #fdecec; color: #8a1f1f; }
        .deepseek-overview-list { margin: 0; padding-left: 16px; }
        .deepseek-overview-list li { margin-bottom: 6px; }
        .deepseek-overview-links { margin-top: 12px; display: flex; flex-wrap: wrap; gap: 8px; }
        .deepseek-overview-links .button { margin: 0; }
        .deepseek-kv { margin: 0; }
        .deepseek-kv dt { font-weight: 600; margin-top: 6px; }
        .deepseek-kv dd { margin: 2px 0 0; }
    </style>';

    echo '<div class="deepseek-overview-grid">';

    echo '<div class="deepseek-overview-card">';
    echo '<h3>整体健康状态</h3>';
    if ($status_ok) {
        echo '<span class="deepseek-overview-status ok">正常</span>';
        echo '<p>当前关键配置未发现明显冲突，可直接投入使用。</p>';
    } else {
        echo '<span class="deepseek-overview-status warn">需关注</span>';
        echo '<p>检测到 ' . intval(count($health['issues'])) . ' 个建议处理项，建议按下方提示逐项处理。</p>';
    }
    echo '</div>';

    echo '<div class="deepseek-overview-card">';
    echo '<h3>接口与额度</h3>';
    echo '<dl class="deepseek-kv">';
    echo '<dt>启用接口数</dt><dd>' . intval(count($health['enabled_interfaces'])) . '</dd>';
    echo '<dt>默认接口</dt><dd>' . esc_html($health['default_interface']) . '</dd>';
    echo '<dt>缺失 API Key</dt><dd>' . (empty($health['missing_keys']) ? '无' : esc_html(implode(', ', $health['missing_keys']))) . '</dd>';
    echo '<dt>游客对话限制</dt><dd>' . intval($health['guest_chat_limit']) . ' 次/设备/日</dd>';
    echo '<dt>游客上传限制</dt><dd>' . intval($health['guest_upload_limit']) . ' 次/设备/日</dd>';
    echo '</dl>';
    echo '</div>';

    echo '<div class="deepseek-overview-card">';
    echo '<h3>安全与风控开关</h3>';
    echo '<ul class="deepseek-overview-list">';
    echo '<li>关键词检测：' . ($health['keyword_detection'] ? '开启' : '关闭') . '</li>';
    echo '<li>会员验证：' . ($health['vip_check'] ? '开启' : '关闭') . '</li>';
    foreach ($health['feature_toggles'] as $label => $enabled) {
        echo '<li>' . esc_html($label) . '：' . ($enabled ? '开启' : '关闭') . '</li>';
    }
    echo '</ul>';
    echo '</div>';

    echo '<div class="deepseek-overview-card">';
    echo '<h3>建议处理项</h3>';
    if ($status_ok) {
        echo '<span class="deepseek-overview-status ok">无阻塞项</span>';
        echo '<p>你可以继续优化模型参数与提示词，当前结构已可维护。</p>';
    } else {
        echo '<ul class="deepseek-overview-list">';
        foreach ($health['issues'] as $issue) {
            $type_class = 'warn';
            if (!empty($issue['type']) && $issue['type'] === 'error') {
                $type_class = 'error';
            }
            echo '<li><span class="deepseek-overview-status ' . esc_attr($type_class) . '">' . esc_html(strtoupper($issue['type'])) . '</span> ';
            echo esc_html($issue['message']) . '</li>';
        }
        echo '</ul>';
    }
    echo '</div>';

    echo '<div class="deepseek-overview-card">';
    echo '<h3>一键修复</h3>';
    if (empty($auto_fixes)) {
        echo '<span class="deepseek-overview-status ok">可执行项 0</span>';
        echo '<p>未发现可自动修复的问题，当前配置已经较稳定。</p>';
    } else {
        echo '<span class="deepseek-overview-status warn">可执行项 ' . intval(count($auto_fixes)) . '</span>';
        echo '<ul class="deepseek-overview-list">';
        foreach ($auto_fixes as $fix) {
            echo '<li>' . esc_html($fix['message']) . '</li>';
        }
        echo '</ul>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-top:10px;">';
        echo '<input type="hidden" name="action" value="deepseek_auto_fix_settings" />';
        wp_nonce_field('deepseek_auto_fix_settings');
        echo '<button type="submit" class="button button-primary">执行一键修复</button>';
        echo '</form>';
    }
    echo '</div>';

    echo '</div>';

    echo '<h2 style="margin-top: 6px;">快速入口</h2>';
    echo '<div class="deepseek-overview-links">';
    foreach ($quick_links as $label => $slug) {
        $url = admin_url('admin.php?page=' . $slug);
        echo '<a class="button button-secondary" href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
    }
    echo '</div>';

    echo '<p style="margin-top: 12px;color:#50575e;">提示：本页只负责概览与健康检查，具体参数请进入各分组页面修改并保存。</p>';
}

function deepseek_handle_auto_fix_settings() {
    if (!current_user_can('manage_options')) {
        wp_die('权限不足，无法执行修复。');
    }

    check_admin_referer('deepseek_auto_fix_settings');

    $fixes = deepseek_get_auto_fix_recommendations();
    $updated_count = 0;

    foreach ($fixes as $fix) {
        if (!isset($fix['option'])) {
            continue;
        }
        $option = sanitize_key($fix['option']);
        $value = isset($fix['value']) ? $fix['value'] : '';
        $updated = update_option($option, $value);
        if ($updated) {
            $updated_count++;
        }
    }

    $redirect_url = add_query_arg(
        array(
            'page' => 'deepseek',
            'autofix' => '1',
            'autofix_count' => $updated_count,
        ),
        admin_url('admin.php')
    );

    wp_safe_redirect($redirect_url);
    exit;
}
add_action('admin_post_deepseek_auto_fix_settings', 'deepseek_handle_auto_fix_settings');

<?php

function deepseek_get_interface_api_key_option_map() {
    return array(
        'deepseek' => 'deepseek_api_key',
        'doubao' => 'doubao_api_key',
        'kimi' => 'kimi_api_key',
        'openai' => 'openai_api_key',
        'grok' => 'grok_api_key',
        'gemini' => 'gemini_api_key',
        'claude' => 'claude_api_key',
        'qianfan' => 'qianfan_api_key',
        'hunyuan' => 'hunyuan_api_key',
        'xunfei' => 'xunfei_api_key',
        'qwen' => 'qwen_api_key',
        'custom' => 'custom_api_key',
    );
}

function deepseek_collect_settings_health() {
    $enabled_interfaces = get_option('chat_interfaces', array('deepseek'));
    if (!is_array($enabled_interfaces)) {
        $enabled_interfaces = array('deepseek');
    }

    $default_interface = get_option('default_chat_interface', 'deepseek');
    $interface_key_map = deepseek_get_interface_api_key_option_map();
    $missing_keys = array();

    foreach ($enabled_interfaces as $interface) {
        if (!isset($interface_key_map[$interface])) {
            continue;
        }
        $api_key = trim((string) get_option($interface_key_map[$interface], ''));
        if ($api_key === '') {
            $missing_keys[] = $interface;
        }
    }

    $issues = array();

    if (!in_array($default_interface, $enabled_interfaces, true)) {
        $issues[] = array(
            'type' => 'error',
            'message' => '默认接口不在已启用接口列表中，前台可能回退到非预期接口。',
        );
    }

    if (count($enabled_interfaces) <= 1 && get_option('show_interface_switch', '0') === '1') {
        $issues[] = array(
            'type' => 'warning',
            'message' => '仅启用了一个接口，但开启了前台接口切换，可关闭以减少用户困惑。',
        );
    }

    if (!empty($missing_keys)) {
        $issues[] = array(
            'type' => 'error',
            'message' => '以下启用接口缺少 API Key：' . implode(', ', $missing_keys),
        );
    }

    if (in_array('custom', $enabled_interfaces, true)) {
        $custom_url = trim((string) get_option('custom_model_url', ''));
        $custom_params = trim((string) get_option('custom_model_params', ''));
        if ($custom_url === '' || $custom_params === '') {
            $issues[] = array(
                'type' => 'warning',
                'message' => '已启用自定义接口，但请求 URL 或模型参数未配置完整。',
            );
        }
    }

    if (get_option('enable_keyword_detection', '0') === '1' && trim((string) get_option('keyword_list', '')) === '') {
        $issues[] = array(
            'type' => 'warning',
            'message' => '已启用关键词检测，但关键词列表为空，等同于未生效。',
        );
    }

    $guest_chat_limit = intval(get_option('deepseek_guest_chat_limit', 5));
    if ($guest_chat_limit <= 0 && trim((string) get_option('deepseek_login_prompt', '')) === '') {
        $issues[] = array(
            'type' => 'warning',
            'message' => '游客对话已禁用，建议填写“未登录提示文字”减少前台疑惑。',
        );
    }

    if (get_option('enable_file_upload', '0') === '1' && trim((string) get_option('allowed_file_types', '')) === '') {
        $issues[] = array(
            'type' => 'warning',
            'message' => '文件上传已启用，但允许文件格式为空，用户上传会频繁失败。',
        );
    }

    return array(
        'enabled_interfaces' => $enabled_interfaces,
        'default_interface' => $default_interface,
        'missing_keys' => $missing_keys,
        'issues' => $issues,
        'guest_chat_limit' => intval(get_option('deepseek_guest_chat_limit', 5)),
        'guest_upload_limit' => intval(get_option('deepseek_guest_upload_limit', 2)),
        'keyword_detection' => get_option('enable_keyword_detection', '0') === '1',
        'vip_check' => get_option('deepseek_vip_check_enabled', '0') === '1',
        'feature_toggles' => array(
            '智能体入口' => get_option('enable_intelligent_agent', '0') === '1',
            '文件上传' => get_option('enable_file_upload', '0') === '1',
            '语音播放' => get_option('enable_ai_voice_reading', '0') === '1',
            '前台助手按钮' => get_option('show_ai_helper', '0') === '1',
            '联网搜索' => get_option('qwen_enable_search', '0') === '1',
            '文章总结' => get_option('enable_ai_summary', '0') === '1',
        ),
    );
}

function deepseek_get_auto_fix_recommendations() {
    $fixes = array();

    $add_fix = function ($option, $value, $message) use (&$fixes) {
        $fixes[$option] = array(
            'option' => $option,
            'value' => $value,
            'message' => $message,
        );
    };

    $enabled_interfaces = get_option('chat_interfaces', array('deepseek'));
    if (!is_array($enabled_interfaces) || empty($enabled_interfaces)) {
        $enabled_interfaces = array('deepseek');
        $add_fix('chat_interfaces', $enabled_interfaces, '已恢复至少一个可用接口（deepseek）。');
    }

    $default_interface = get_option('default_chat_interface', 'deepseek');
    if (!in_array($default_interface, $enabled_interfaces, true)) {
        $safe_default = reset($enabled_interfaces);
        if (empty($safe_default)) {
            $safe_default = 'deepseek';
        }
        $add_fix('default_chat_interface', $safe_default, '默认接口不在启用列表，已自动同步为可用接口。');
    }

    if (count($enabled_interfaces) <= 1 && get_option('show_interface_switch', '0') === '1') {
        $add_fix('show_interface_switch', '0', '仅启用一个接口时，已自动关闭前台接口切换。');
    }

    if (in_array('custom', $enabled_interfaces, true)) {
        $custom_url = trim((string) get_option('custom_model_url', ''));
        $custom_params = trim((string) get_option('custom_model_params', ''));
        if ($custom_url === '' || $custom_params === '') {
            $filtered = array_values(array_filter($enabled_interfaces, function ($interface) {
                return $interface !== 'custom';
            }));
            if (empty($filtered)) {
                $filtered = array('deepseek');
            }
            $add_fix('chat_interfaces', $filtered, '自定义接口配置不完整，已暂时从启用接口中移除。');

            if (get_option('default_chat_interface', 'deepseek') === 'custom') {
                $add_fix('default_chat_interface', reset($filtered), '默认接口为 custom 且配置不完整，已切换为可用接口。');
            }
        }
    }

    if (get_option('enable_keyword_detection', '0') === '1' && trim((string) get_option('keyword_list', '')) === '') {
        $add_fix('enable_keyword_detection', '0', '关键词列表为空时已自动关闭关键词检测。');
    }

    $guest_chat_limit = intval(get_option('deepseek_guest_chat_limit', 5));
    if ($guest_chat_limit <= 0 && trim((string) get_option('deepseek_login_prompt', '')) === '') {
        $add_fix('deepseek_login_prompt', '游客对话暂不可用，请登录后继续使用。', '已补全游客禁用场景下的未登录提示文案。');
    }

    if (get_option('enable_file_upload', '0') === '1' && trim((string) get_option('allowed_file_types', '')) === '') {
        $add_fix('allowed_file_types', 'txt,docx,pdf,xlsx,md', '文件上传格式为空时已恢复默认格式。');
    }

    return array_values($fixes);
}

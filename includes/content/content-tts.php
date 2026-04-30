<?php
// 处理AI对话语音朗读的TTS请求
function deepseek_tts() {
    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'wp_rest')) {
        wp_send_json_error('验证请求失败');
        return;
    }

    $text = isset($_POST['text']) ? wp_strip_all_tags(wp_unslash($_POST['text'])) : '';
    if ( empty($text) ) {
        wp_send_json_error('文本为空');
    }

    // 检查游客发送的频率限制
    if (!deepseek_check_guest_limit('chat')) {
        wp_send_json_error('今日语音生成次数已达上限，请登录。');
        return;
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

// 插件卸载时删除相关设置项
function deepseek_uninstall() {
    if (function_exists('deepseek_cleanup_managed_settings_storage')) {
        deepseek_cleanup_managed_settings_storage();
    }
    if (function_exists('deepseek_cleanup_storage_schema')) {
        deepseek_cleanup_storage_schema();
    }
    if (function_exists('deepseek_kb_cleanup_schema')) {
        deepseek_kb_cleanup_schema();
    }
    if (function_exists('deepseek_prompt_library_cleanup_schema')) {
        deepseek_prompt_library_cleanup_schema();
    }
    if (function_exists('deepseek_usage_cleanup_schema')) {
        deepseek_usage_cleanup_schema();
    }
    if (function_exists('deepseek_conversation_cleanup_schema')) {
        deepseek_conversation_cleanup_schema();
    }
}
register_uninstall_hook(DEEPSEEK_PLUGIN_FILE, 'deepseek_uninstall');


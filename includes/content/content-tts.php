<?php
// 处理AI对话语音朗读的TTS请求
function deepseek_tts() {
    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'wp_rest')) {
        wp_send_json_error('验证请求失败');
        return;
    }

    // 检查游客发送的频率限制
    if (!deepseek_check_guest_limit('chat')) {
        wp_send_json_error('今日语音生成次数已达上限，请登录。');
        return;
    }

    $text = isset($_POST['text']) ? wp_strip_all_tags(wp_unslash($_POST['text'])) : '';
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
    delete_option('ali_agent_api_key');
    delete_option('coze_access_token');
    delete_option('coze_access_token_expiry');
    delete_option('deepseek_agents');
    delete_option('volc_agent_api_key');
    delete_option('agent_file_formats');
    delete_option('agent_file_max_size');
}
register_uninstall_hook(DEEPSEEK_PLUGIN_FILE, 'deepseek_uninstall');


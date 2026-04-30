<?php
if (!defined('ABSPATH')) {
    exit;
}

function deepseek_get_setting($option_name, $default = false) {
    if (!is_string($option_name) || $option_name === '') {
        return $default;
    }

    if (function_exists('deepseek_is_managed_option') && deepseek_is_managed_option($option_name)) {
        $found = false;
        $value = deepseek_get_setting_from_table($option_name, $found);
        if ($found) {
            return $value;
        }
    }

    return get_option($option_name, $default);
}

function deepseek_update_setting($option_name, $value, $autoload = null) {
    if (!is_string($option_name) || $option_name === '') {
        return false;
    }

    if (null === $autoload) {
        return update_option($option_name, $value);
    }

    return update_option($option_name, $value, (bool) $autoload);
}

function deepseek_get_array_setting($option_name, $default = array()) {
    $value = deepseek_get_setting($option_name, $default);
    if (is_array($value)) {
        return $value;
    }

    if ($value === false || $value === null || $value === '') {
        return is_array($default) ? $default : array();
    }

    return (array) $value;
}

function deepseek_get_csv_setting_list($option_name, $default = '') {
    $raw_value = deepseek_get_setting($option_name, $default);
    $items = is_array($raw_value) ? $raw_value : explode(',', (string) $raw_value);

    $items = array_map('trim', $items);
    $items = array_filter($items, static function ($item) {
        return $item !== '';
    });

    return array_values($items);
}

function deepseek_get_chat_interface_labels() {
    return array(
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
        'siliconflow' => '硅基流动',
        'openrouter' => 'OpenRouter',
        'mistral' => 'Mistral AI',
        'pollinations' => 'Pollinations(文生图)',
        'ollama' => 'Ollama本地模型',
        'custom' => '自定义接口',
    );
}

function deepseek_get_openai_compatible_provider_configs() {
    return array(
        'deepseek' => array(
            'api_key_option' => 'deepseek_api_key',
            'model_option' => 'deepseek_model',
            'default_model' => 'deepseek-chat',
            'api_url' => 'https://api.deepseek.com/chat/completions',
        ),
        'doubao' => array(
            'api_key_option' => 'doubao_api_key',
            'model_option' => 'doubao_model',
            'default_model' => '',
            'api_url' => 'https://ark.cn-beijing.volces.com/api/v3/chat/completions',
        ),
        'hunyuan' => array(
            'api_key_option' => 'hunyuan_api_key',
            'model_option' => 'hunyuan_model',
            'default_model' => '',
            'api_url' => 'https://api.hunyuan.cloud.tencent.com/v1/chat/completions',
        ),
        'kimi' => array(
            'api_key_option' => 'kimi_api_key',
            'model_option' => 'kimi_model',
            'default_model' => '',
            'api_url' => 'https://api.moonshot.cn/v1/chat/completions',
        ),
        'openai' => array(
            'api_key_option' => 'openai_api_key',
            'model_option' => 'openai_model',
            'default_model' => '',
            'api_url' => 'https://api.openai.com/v1/chat/completions',
        ),
        'grok' => array(
            'api_key_option' => 'grok_api_key',
            'model_option' => 'grok_model',
            'default_model' => '',
            'api_url' => 'https://api.x.ai/v1/chat/completions',
        ),
        'gemini' => array(
            'api_key_option' => 'gemini_api_key',
            'model_option' => 'gemini_model',
            'default_model' => 'gemini-2.0-flash',
            'api_url' => 'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions',
        ),
        'qianfan' => array(
            'api_key_option' => 'qianfan_api_key',
            'model_option' => 'qianfan_model',
            'default_model' => '',
            'api_url' => 'https://qianfan.baidubce.com/v2/chat/completions',
        ),
        'xunfei' => array(
            'api_key_option' => 'xunfei_api_key',
            'model_option' => 'xunfei_model',
            'default_model' => 'generalv3.5',
            'api_url' => 'https://spark-api-open.xf-yun.com/v1/chat/completions',
        ),
        'qwen' => array(
            'api_key_option' => 'qwen_api_key',
            'model_option' => 'qwen_text_model',
            'default_model' => 'qwen-max',
            'api_url' => 'https://dashscope.aliyuncs.com/compatible-mode/v1/chat/completions',
        ),
        'siliconflow' => array(
            'api_key_option' => 'siliconflow_api_key',
            'model_option' => 'siliconflow_model',
            'default_model' => 'Qwen/QwQ-32B',
            'api_url' => 'https://api.siliconflow.com/v1/chat/completions',
        ),
        'openrouter' => array(
            'api_key_option' => 'openrouter_api_key',
            'model_option' => 'openrouter_model',
            'default_model' => 'openai/gpt-4o-mini',
            'api_url' => 'https://openrouter.ai/api/v1/chat/completions',
        ),
        'mistral' => array(
            'api_key_option' => 'mistral_api_key',
            'model_option' => 'mistral_model',
            'default_model' => 'mistral-small-latest',
            'api_url' => 'https://api.mistral.ai/v1/chat/completions',
        ),
        'custom' => array(
            'api_key_option' => 'custom_api_key',
            'model_option' => 'custom_model_params',
            'default_model' => '',
            'api_url_option' => 'custom_model_url',
            'api_url' => '',
        ),
    );
}

function deepseek_get_openai_compatible_provider_config($interface_choice) {
    $configs = deepseek_get_openai_compatible_provider_configs();
    $interface_choice = sanitize_key((string) $interface_choice);
    if (!isset($configs[$interface_choice])) {
        return null;
    }

    $config = $configs[$interface_choice];
    $config['interface'] = $interface_choice;
    $config['api_key'] = deepseek_get_setting($config['api_key_option'], '');
    $config['model_string'] = deepseek_get_setting($config['model_option'], $config['default_model']);
    $config['api_url'] = isset($config['api_url_option'])
        ? deepseek_get_setting($config['api_url_option'], $config['api_url'])
        : $config['api_url'];

    return $config;
}

function deepseek_get_enabled_chat_interfaces() {
    $interfaces = deepseek_get_array_setting('chat_interfaces', array('deepseek'));
    $interfaces = array_map('sanitize_key', $interfaces);
    $interfaces = array_values(array_filter($interfaces, static function ($item) {
        return $item !== '';
    }));

    if (empty($interfaces)) {
        $interfaces = array('deepseek');
    }

    return array_values(array_unique($interfaces));
}

function deepseek_get_default_chat_interface() {
    $default_interface = sanitize_key((string) deepseek_get_setting('default_chat_interface', 'deepseek'));
    $enabled_interfaces = deepseek_get_enabled_chat_interfaces();

    if (!in_array($default_interface, $enabled_interfaces, true)) {
        return $enabled_interfaces[0];
    }

    return $default_interface;
}

function deepseek_get_interface_model_params_map() {
    return array(
        'deepseek' => (string) deepseek_get_setting('deepseek_model', 'deepseek-chat'),
        'doubao' => (string) deepseek_get_setting('doubao_model', ''),
        'kimi' => (string) deepseek_get_setting('kimi_model', ''),
        'openai' => (string) deepseek_get_setting('openai_model', ''),
        'grok' => (string) deepseek_get_setting('grok_model', ''),
        'gemini' => (string) deepseek_get_setting('gemini_model', 'gemini-2.0-flash'),
        'claude' => (string) deepseek_get_setting('claude_model', 'claude-3-7-sonnet-20250219'),
        'qianfan' => (string) deepseek_get_setting('qianfan_model', ''),
        'hunyuan' => (string) deepseek_get_setting('hunyuan_model', ''),
        'xunfei' => (string) deepseek_get_setting('xunfei_model', 'generalv3.5'),
        'siliconflow' => (string) deepseek_get_setting('siliconflow_model', 'Qwen/QwQ-32B'),
        'openrouter' => (string) deepseek_get_setting('openrouter_model', 'openai/gpt-4o-mini'),
        'mistral' => (string) deepseek_get_setting('mistral_model', 'mistral-small-latest'),
        'qwen' => implode(',', array_filter(array(
            (string) deepseek_get_setting('qwen_text_model', 'qwen-max'),
            (string) deepseek_get_setting('qwen_image_model', 'wanx2.1-t2i-turbo'),
            (string) deepseek_get_setting('qwen_video_model', 'wanx2.1-t2v-turbo'),
        ))),
        'pollinations' => (string) deepseek_get_setting('pollinations_model', 'flux'),
        'ollama' => (string) deepseek_get_setting('ollama_model', 'llama3'),
        'custom' => (string) deepseek_get_setting('custom_model_params', ''),
    );
}

function deepseek_get_interface_model_list($interface_choice) {
    $interface_choice = sanitize_key((string) $interface_choice);
    $model_map = deepseek_get_interface_model_params_map();
    $model_params = isset($model_map[$interface_choice]) ? $model_map[$interface_choice] : '';

    if ($model_params === '') {
        return array();
    }

    $items = explode(',', (string) $model_params);
    $items = array_map('trim', $items);
    $items = array_filter($items, static function ($item) {
        return $item !== '';
    });

    return array_values($items);
}

<?php
// Pollinations模型参数回调函数
function pollinations_model_callback() {
    $model = get_option('pollinations_model', 'flux'); // 默认模型为flux
    echo '<input type="text" name="pollinations_model" value="' . esc_attr($model) . '" style="width: 500px;" />';
    echo '<p class="description">使用Pollinations最好是海外服务器，内地服务器请注意请求时间。</p>';
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

// Ollama api函数回调
function ollama_api_url_callback() {
    $url = get_option('ollama_api_url', 'http://127.0.0.1:11434/api/chat');
    echo '<input type="text" name="ollama_api_url" value="' . esc_attr($url) . '" style="width: 500px;" />';
    echo '<p class="description">默认值为 http://127.0.0.1:11434/api/chat。如果 Ollama 未部署在同一服务器，请使用内网/公网IP代替 127.0.0.1。</p>';
}

// Ollama 参数函数回调
function ollama_model_callback() {
    $model = get_option('ollama_model', 'llama3');
    echo '<input type="text" name="ollama_model" value="' . esc_attr($model) . '" style="width: 500px;" />';
    echo '<p class="description">输入已经下载到 Ollama 里的模型名称，例如：<code>llama3</code>、<code>qwen2:7b</code> 等等。</p>';
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


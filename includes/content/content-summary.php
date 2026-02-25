<?php
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



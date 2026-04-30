<?php
if (!defined('ABSPATH')) {
    exit;
}

function deepseek_send_message_rest(WP_REST_Request $request) {
    $request_started_at = microtime(true);
    $message = sanitize_textarea_field($request->get_param('message'));
    $conversation_id = $request->get_param('conversation_id') ? intval($request->get_param('conversation_id')) : null;
    $user_id = get_current_user_id();
    $interface_choice = $request->get_param('interface') ?: deepseek_get_user_interface();
    $model_choice = sanitize_text_field($request->get_param('model')); // 用户选择的模型参数
    $enable_search = filter_var($request->get_param('enable_search'), FILTER_VALIDATE_BOOLEAN);
    $page_id = absint($request->get_param('page_id'));
    $prompt_template_id = absint($request->get_param('prompt_template_id'));
    $file_ids = $request->get_param('file_ids') ? json_decode($request->get_param('file_ids'), true) : [];

    if (empty($message) && empty($file_ids)) {
        return new WP_REST_Response(['success' => false, 'message' => '消息内容不能为空'], 400);
    }

    // 游客二次回复时的所有权验证
    if ($conversation_id && $user_id == 0 && !deepseek_guest_can_access_conversation($conversation_id)) {
            return new WP_REST_Response(['success' => false, 'message' => '无权在此对话中发送消息'], 403);
    }

    // 关键词检测
    $enable_keyword_detection = deepseek_get_setting('enable_keyword_detection', '0');
    if ($enable_keyword_detection) {
        $keywords = deepseek_get_setting('keyword_list', '');
        $keywords = array_map('trim', explode(',', $keywords));
        foreach ($keywords as $keyword) {
            if (stripos($message, $keyword) !== false) {
                return new WP_REST_Response([
                    'success' => false,
                    'message' => '内容包含违规关键词'
                ], 400);
            }
        }
    }

    // 获取模型参数列表并验证
    $enabled_interfaces = deepseek_get_enabled_chat_interfaces();
    if (!in_array($interface_choice, $enabled_interfaces, true)) {
        return new WP_REST_Response(['success' => false, 'message' => '无效的接口选择'], 400);
    }

    $model_list = deepseek_get_interface_model_list($interface_choice);
    if (empty($model_list)) {
        return new WP_REST_Response(['success' => false, 'message' => '当前接口未配置可用模型'], 400);
    }

    $model = in_array($model_choice, $model_list) ? $model_choice : $model_list[0]; // 默认使用第一个参数

    $file_ids = deepseek_prepare_chat_request_files($file_ids, $interface_choice, $model);
    if (is_wp_error($file_ids)) {
        return new WP_REST_Response(
            array('success' => false, 'message' => $file_ids->get_error_message()),
            $file_ids->get_error_data('status') ?: 400
        );
    }

    if (!deepseek_check_guest_limit('chat')) {
        return new WP_REST_Response(['success' => false, 'message' => '今日使用次数已达上限，请登录后继续体验。'], 403);
    }
	
    // 判断是否为Pollinations文生图请求
    if ($interface_choice === 'pollinations') {
    $encoded_prompt = urlencode($message);
    $api_url = "https://image.pollinations.ai/prompt/{$encoded_prompt}?model={$model}&width=1024&height=1024&nologo=true&private=true";

    $max_retries = 3;
    $retry_delay = 2; //秒
    $attempt = 0;
    $image_url = null;

    do {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120); // 增加超时时间
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // 跟随重定向
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: image/*', // 明确要求图片响应
            'User-Agent: Mozilla/5.0'
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($http_code == 200 && $response !== false) {
            // Pollinations返回的是图片二进制数据，直接使用URL
            $image_url = $api_url;
            break;
        } else {
            $attempt++;
            if ($attempt < $max_retries) {
                sleep($retry_delay); // 等待后重试
            }
        }
    } while ($attempt < $max_retries && !$image_url);

    if ($image_url) {
        // 保存到数据库
        $saved_log = deepseek_save_chat_message(array(
            'user_id' => $user_id,
            'conversation_id' => $conversation_id ?: 0,
            'conversation_title' => $message,
            'message' => $message,
            'response' => array('image_url' => $image_url),
        ));
        $conversation_id = $saved_log['conversation_id'];

        deepseek_chat_rest_record_usage($request_started_at, array(
            'user_id' => $user_id,
            'conversation_id' => $conversation_id,
            'message_id' => isset($saved_log['insert_id']) ? (int) $saved_log['insert_id'] : 0,
            'event_type' => 'pollinations_image',
            'interface_key' => $interface_choice,
            'model' => $model,
            'prompt_template_id' => $prompt_template_id,
            'status' => 'success',
            'message' => $message,
            'meta' => array(
                'attempts' => $attempt + 1,
                'image_url' => $image_url,
            ),
        ));

        return new WP_REST_Response([
            'success' => true,
            'is_pollinations_image' => true,
            'image_url' => $image_url,
            'conversation_id' => $conversation_id,
            'message_id' => isset($saved_log['insert_id']) ? (int) $saved_log['insert_id'] : 0,
            'conversation_title' => $message,
        ], 200);
    } else {
        deepseek_chat_rest_record_usage($request_started_at, array(
            'user_id' => $user_id,
            'conversation_id' => $conversation_id ?: 0,
            'event_type' => 'pollinations_image',
            'interface_key' => $interface_choice,
            'model' => $model,
            'prompt_template_id' => $prompt_template_id,
            'status' => 'error',
            'message' => $message,
            'meta' => array(
                'attempts' => $attempt,
                'http_code' => isset($http_code) ? (int) $http_code : 0,
                'error' => isset($error) ? $error : '',
            ),
        ));

        return new WP_REST_Response([
            'success' => false,
            'message' => 'Pollinations 图片生成失败: ' . ($error ?: '多次尝试后仍无响应'),
            'attempts' => $attempt
        ], 500);
    }
}

    // 判断是否为通义千问图像模型
    $qwen_image_models = deepseek_get_csv_setting_list('qwen_image_model', 'wanx2.1-t2i-turbo');
    $is_image_model = ($interface_choice === 'qwen' && in_array($model, $qwen_image_models));
    if ($is_image_model) {
        $api_key = deepseek_get_setting('qwen_api_key');
        if (empty($api_key)) {
            deepseek_chat_rest_record_usage($request_started_at, array(
                'user_id' => $user_id,
                'conversation_id' => $conversation_id ?: 0,
                'event_type' => 'qwen_image',
                'interface_key' => $interface_choice,
                'model' => $model,
                'prompt_template_id' => $prompt_template_id,
                'status' => 'error',
                'message' => $message,
                'meta' => array('error' => 'missing_api_key'),
            ));

            return new WP_REST_Response([
                'success' => false,
                'message' => '通义千问 API Key 未设置'
            ], 400);
        }

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
        deepseek_apply_curl_timeouts($ch, 'qwen_image_create', 30, 10);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($http_code == 200) {
            $response_data = json_decode($response, true);
            if (!is_array($response_data) || empty($response_data['output']['task_id'])) {
                deepseek_chat_rest_record_usage($request_started_at, array(
                    'user_id' => $user_id,
                    'conversation_id' => $conversation_id ?: 0,
                    'event_type' => 'qwen_image',
                    'interface_key' => $interface_choice,
                    'model' => $model,
                    'prompt_template_id' => $prompt_template_id,
                    'status' => 'error',
                    'message' => $message,
                    'meta' => array(
                        'http_code' => (int) $http_code,
                        'response' => substr(wp_strip_all_tags((string) $response), 0, 500),
                        'json_error' => json_last_error_msg(),
                    ),
                ));

                return new WP_REST_Response([
                    'success' => false,
                    'message' => '图片生成请求返回格式异常'
                ], 500);
            }

            $task_id = sanitize_text_field($response_data['output']['task_id']);

            $saved_log = deepseek_save_chat_message(array(
                'user_id' => $user_id,
                'conversation_id' => $conversation_id ?: 0,
                'conversation_title' => $message,
                'message' => $message,
                'response' => array(
                    'task_id' => $task_id,
                    'status' => 'pending',
                    'message' => '图片生成中...',
                ),
            ));
            $conversation_id = $saved_log['conversation_id'];

            deepseek_chat_rest_record_usage($request_started_at, array(
                'user_id' => $user_id,
                'conversation_id' => $conversation_id,
                'message_id' => isset($saved_log['insert_id']) ? (int) $saved_log['insert_id'] : 0,
                'event_type' => 'qwen_image',
                'interface_key' => $interface_choice,
                'model' => $model,
                'prompt_template_id' => $prompt_template_id,
                'status' => 'queued',
                'message' => $message,
                'meta' => array(
                    'task_id' => $task_id,
                    'http_code' => (int) $http_code,
                ),
            ));

            return new WP_REST_Response([
                'success' => true,
                'is_image' => true,
                'task_id' => $task_id,
                'conversation_id' => $conversation_id,
                'message_id' => isset($saved_log['insert_id']) ? (int) $saved_log['insert_id'] : 0,
                'conversation_title' => $message,
            ], 200);
        } else {
            deepseek_chat_rest_record_usage($request_started_at, array(
                'user_id' => $user_id,
                'conversation_id' => $conversation_id ?: 0,
                'event_type' => 'qwen_image',
                'interface_key' => $interface_choice,
                'model' => $model,
                'prompt_template_id' => $prompt_template_id,
                'status' => 'error',
                'message' => $message,
                'meta' => array(
                    'http_code' => (int) $http_code,
                    'curl_error' => $curl_error,
                    'response' => substr(wp_strip_all_tags((string) $response), 0, 500),
                ),
            ));

            return new WP_REST_Response([
                'success' => false,
                'message' => '图片生成请求失败: ' . $response
            ], 500);
        }
    }

    // 通义千问视频模型
    $qwen_video_models = deepseek_get_csv_setting_list('qwen_video_model', 'wanx2.1-t2v-turbo');
    $is_video_model = ($interface_choice === 'qwen' && in_array($model, $qwen_video_models));
    if ($is_video_model) {
    $api_key = deepseek_get_setting('qwen_api_key');
    if (empty($api_key)) {
        deepseek_chat_rest_record_usage($request_started_at, array(
            'user_id' => $user_id,
            'conversation_id' => $conversation_id ?: 0,
            'event_type' => 'qwen_video',
            'interface_key' => $interface_choice,
            'model' => $model,
            'prompt_template_id' => $prompt_template_id,
            'status' => 'error',
            'message' => $message,
            'meta' => array('error' => 'missing_api_key'),
        ));

        return new WP_REST_Response([
            'success' => false,
            'message' => '通义千问 API Key 未设置'
        ], 400);
    }

    $api_url = 'https://dashscope.aliyuncs.com/api/v1/services/aigc/video-generation/video-synthesis';
    $headers = [
        'X-DashScope-Async: enable',
        'Authorization: Bearer ' . $api_key,
        'Content-Type: application/json'
    ];
    $body = [
        'model' => $model,
        'input' => ['prompt' => $message],
        'parameters' => [
            'prompt_extend' => true
        ]
    ];

    // 如果有上传的图片，添加img_url
    if (!empty($file_ids) && isset($file_ids[0]['image_url'])) {
        $body['input']['img_url'] = $file_ids[0]['image_url'];
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    deepseek_apply_curl_timeouts($ch, 'qwen_video_create', 30, 10);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($http_code == 200) {
        $response_data = json_decode($response, true);
        if (!is_array($response_data) || empty($response_data['output']['task_id'])) {
            deepseek_chat_rest_record_usage($request_started_at, array(
                'user_id' => $user_id,
                'conversation_id' => $conversation_id ?: 0,
                'event_type' => 'qwen_video',
                'interface_key' => $interface_choice,
                'model' => $model,
                'prompt_template_id' => $prompt_template_id,
                'status' => 'error',
                'message' => $message,
                'meta' => array(
                    'http_code' => (int) $http_code,
                    'response' => substr(wp_strip_all_tags((string) $response), 0, 500),
                    'json_error' => json_last_error_msg(),
                ),
            ));

            return new WP_REST_Response([
                'success' => false,
                'message' => '视频生成请求返回格式异常'
            ], 500);
        }

        $task_id = sanitize_text_field($response_data['output']['task_id']);

        $saved_log = deepseek_save_chat_message(array(
            'user_id' => $user_id,
            'conversation_id' => $conversation_id ?: 0,
            'conversation_title' => $message,
            'message' => $message,
            'response' => array(
                'task_id' => $task_id,
                'status' => 'pending',
                'message' => '视频生成中，请稍后查看结果（约5-10分钟）',
            ),
        ));
        $conversation_id = $saved_log['conversation_id'];

        deepseek_chat_rest_record_usage($request_started_at, array(
            'user_id' => $user_id,
            'conversation_id' => $conversation_id,
            'message_id' => isset($saved_log['insert_id']) ? (int) $saved_log['insert_id'] : 0,
            'event_type' => 'qwen_video',
            'interface_key' => $interface_choice,
            'model' => $model,
            'prompt_template_id' => $prompt_template_id,
            'status' => 'queued',
            'message' => $message,
            'meta' => array(
                'task_id' => $task_id,
                'http_code' => (int) $http_code,
                'has_input_image' => !empty($file_ids) && isset($file_ids[0]['image_url']),
            ),
        ));

        return new WP_REST_Response([
            'success' => true,
            'is_video' => true,
            'task_id' => $task_id,
            'conversation_id' => $conversation_id,
            'message_id' => isset($saved_log['insert_id']) ? (int) $saved_log['insert_id'] : 0,
            'conversation_title' => $message,
        ], 200);
    } else {
        deepseek_chat_rest_record_usage($request_started_at, array(
            'user_id' => $user_id,
            'conversation_id' => $conversation_id ?: 0,
            'event_type' => 'qwen_video',
            'interface_key' => $interface_choice,
            'model' => $model,
            'prompt_template_id' => $prompt_template_id,
            'status' => 'error',
            'message' => $message,
            'meta' => array(
                'http_code' => (int) $http_code,
                'curl_error' => $curl_error,
                'response' => substr(wp_strip_all_tags((string) $response), 0, 500),
            ),
        ));

        return new WP_REST_Response([
            'success' => false,
            'message' => '视频生成请求失败: ' . $response
        ], 500);
    }
}

    // 文本对话分支
    switch ($interface_choice) {
        case 'deepseek':
            $api_key = deepseek_get_setting('deepseek_api_key');
            $api_url = 'https://api.deepseek.com/chat/completions';
            break;
        case 'doubao':
            $api_key = deepseek_get_setting('doubao_api_key');
            $api_url = 'https://ark.cn-beijing.volces.com/api/v3/chat/completions';
            break;
        case 'hunyuan':
            $api_key = deepseek_get_setting('hunyuan_api_key');
            $api_url = 'https://api.hunyuan.cloud.tencent.com/v1/chat/completions';
            break;
        case 'kimi':
            $api_key = deepseek_get_setting('kimi_api_key');
            $api_url = 'https://api.moonshot.cn/v1/chat/completions';
            break;
        case 'openai':
            $api_key = deepseek_get_setting('openai_api_key');
            $api_url = 'https://api.openai.com/v1/chat/completions';
            break;
        case 'grok':
            $api_key = deepseek_get_setting('grok_api_key');
            $api_url = 'https://api.x.ai/v1/chat/completions';
            break;
        case 'gemini':
            $api_key = deepseek_get_setting('gemini_api_key');
            $api_url = 'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions';
            break;
        case 'claude':
            $api_key = deepseek_get_setting('claude_api_key');
            $api_url = 'https://api.anthropic.com/v1/messages';
            break;            
        case 'qianfan':
            $api_key = deepseek_get_setting('qianfan_api_key');
            $api_url = 'https://qianfan.baidubce.com/v2/chat/completions';
            break;
        case 'xunfei':
            $api_key = deepseek_get_setting('xunfei_api_key');
            $api_url = 'https://spark-api-open.xf-yun.com/v1/chat/completions';
            break;    
        case 'siliconflow':
            $api_key = deepseek_get_setting('siliconflow_api_key');
            $api_url = 'https://api.siliconflow.com/v1/chat/completions';
            break;
        case 'openrouter':
            $api_key = deepseek_get_setting('openrouter_api_key');
            $api_url = 'https://openrouter.ai/api/v1/chat/completions';
            break;
        case 'mistral':
            $api_key = deepseek_get_setting('mistral_api_key');
            $api_url = 'https://api.mistral.ai/v1/chat/completions';
            break;
        case 'qwen':
            $api_key = deepseek_get_setting('qwen_api_key');
            $api_url = 'https://dashscope.aliyuncs.com/compatible-mode/v1/chat/completions';
            break;
        case 'ollama':
            $api_key = 'none'; // Ollama不需要ApiKey
            $api_url = deepseek_get_setting('ollama_api_url', 'http://127.0.0.1:11434/api/chat');
            break;
        case 'custom':
            $api_key = deepseek_get_setting('custom_api_key');
            $api_url = deepseek_get_setting('custom_model_url');
            if (empty($api_key) || empty($model) || empty($api_url)) {
                return new WP_REST_Response(['success' => false, 'message' => '自定义模型设置不完整'], 400);
            }
            break;
        default:
            return new WP_REST_Response(['success' => false, 'message' => '无效的接口选择'], 400);
    }

    if (empty($api_key) && $interface_choice !== 'ollama') {
        return new WP_REST_Response(['success' => false, 'message' => 'API Key 未设置'], 400);
    }

    // 构建消息历史
    $kb_context = array('context' => '', 'sources' => array());
    $prompt_template = null;
    $messages = [['role' => 'system', 'content' => 'You are a helpful assistant capable of analyzing uploaded files.']];
    if ($prompt_template_id > 0 && function_exists('deepseek_prompt_template_for_request')) {
        $prompt_template = deepseek_prompt_template_for_request($prompt_template_id, $interface_choice);
        if ($prompt_template && !empty($prompt_template->system_prompt)) {
            $messages[] = array('role' => 'system', 'content' => $prompt_template->system_prompt);
        }
    }
    if (function_exists('deepseek_kb_build_prompt_context')) {
        $kb_context = deepseek_kb_build_prompt_context($message, $interface_choice, $page_id);
        if (!empty($kb_context['context'])) {
            $messages[] = array('role' => 'system', 'content' => $kb_context['context']);
        }
    }

    if ($conversation_id) {
        $memory_limit = intval(deepseek_get_setting('deepseek_context_memory_limit', 5));
        $history = deepseek_get_chat_conversation_history($conversation_id, $memory_limit);

        foreach ($history as $item) {
            $messages[] = ['role' => 'user', 'content' => $item->message];
            $response = json_decode($item->response, true);
            $content = is_array($response) && isset($response['content']) ? $response['content'] : $item->response;
            $messages[] = ['role' => 'assistant', 'content' => $content];
        }
    }

    // 处理文件内容
    if (!empty($file_ids)) {
        $support_doc_models = [
            'kimi' => deepseek_get_csv_setting_list('kimi_model', ''),
            'openai' => deepseek_get_csv_setting_list('openai_model', ''),
            'qwen' => ['qwen-long'] // 通义千问仅支持qwen-long
        ];
        $is_doc_supported = false;
        if (isset($support_doc_models[$interface_choice])) {
            $is_doc_supported = in_array($model, $support_doc_models[$interface_choice]);
        }
        if (!$is_doc_supported) {
            return new WP_REST_Response([
                'success' => false,
                'message' => '该模型不支持分析文档，分析文档请选择Kimi或者通义千问的qwen-long模型'
            ], 400);
        }

        if ($interface_choice === 'qwen') {
            $file_ids_str = '';
            foreach ($file_ids as $file_info) {
                $file_id = $file_info['file_id'];
                $interface = $file_info['interface'];
                if ($interface === 'qwen') {
                    $file_ids_str .= ($file_ids_str ? ',' : '') . "fileid://$file_id";
                }
            }
            if ($file_ids_str) {
                $messages[] = [
                    'role' => 'system',
                    'content' => $file_ids_str
                ];
            }
            $messages[] = [
                'role' => 'user',
                'content' => "请分析这些文件并回答我的问题:\n\n问题: $message"
            ];
        } else {
            $file_content = '';
            foreach ($file_ids as $file_info) {
                $file_id = $file_info['file_id'];
                $interface = $file_info['interface'];
                $content_url = '';
                $content_api_key = '';

                switch ($interface) {
                    case 'openai':
                        $content_api_key = deepseek_get_setting('openai_api_key');
                        $content_url = "https://api.openai.com/v1/files/$file_id/content";
                        break;
                    case 'kimi':
                        $content_api_key = deepseek_get_setting('kimi_api_key');
                        $content_url = "https://api.moonshot.cn/v1/files/$file_id/content";
                        break;
                    default:
                        // 不支持的接口直接跳过本次循环
                        continue 2; // 明确跳出外层foreach循环
                }

                if (empty($content_api_key)) {
                    continue; // 如果API Key为空，跳过本次循环
                }

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $content_url);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $content_api_key"]);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPGET, true);
                deepseek_apply_curl_timeouts($ch, 'file_content', 30, 10);
                $response = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($http_code == 200) {
                    $file_content .= $response . "\n\n";
                }
            }
            $messages[] = [
                'role' => 'user',
                'content' => "请分析以下文件内容并回答我的问题:\n\n$file_content\n\n问题: $message"
            ];
        }
    } else {
        $messages[] = ['role' => 'user', 'content' => $message];
    }

    // 构建请求数据
    if ($interface_choice === 'claude') {
        // Claude使用自己的消息格式
        $data = [
            'model' => $model,
            'max_tokens' => 4096, // Claude的token限制
            'messages' => array_map(function($msg) {
                return [
                    'role' => $msg['role'] === 'system' ? 'system' : ($msg['role'] === 'user' ? 'user' : 'assistant'),
                    'content' => $msg['content']
                ];
            }, $messages),
            'stream' => true
        ];
    } elseif ($interface_choice === 'ollama') {
        // Ollama 使用自己的消息格式
        $data = [
            'model' => $model,
            'messages' => array_map(function($msg) {
                return [
                    'role' => $msg['role'] === 'system' ? 'system' : ($msg['role'] === 'user' ? 'user' : 'assistant'),
                    'content' => $msg['content']
                ];
            }, $messages),
            'stream' => true
        ];
    } else {
        // OpenAI兼容接口
        $data = [
            'model' => $model,
            'messages' => $messages,
            'stream' => true
        ];
    }

    // 如果启用了联网搜索，且当前是讯飞星火接口，则启用讯飞的web_search
    $qwen_enable_search = deepseek_get_setting('qwen_enable_search', '0');
    if ($interface_choice === 'xunfei' && $qwen_enable_search == '1' && $enable_search) {
        $data['tools'] = [
            [
                'type' => 'web_search',
                'web_search' => [
                    'enable' => true
                ]
            ]
        ];
    } elseif ($interface_choice === 'qwen' && $qwen_enable_search == '1' && $enable_search) {
        $data['enable_search'] = true; // 通义千问的联网搜索参数
    }

    // 清空缓冲区，设置流式响应头
    if (ob_get_length()) { ob_end_clean(); }
    while (ob_get_level()) { ob_end_flush(); }
    ini_set('output_buffering', 'off');
    ini_set('zlib.output_compression', false);
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('X-Accel-Buffering: no');
    header('Connection: keep-alive');

    // 发送请求并处理流式响应
    $fullReply = [];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    
    $headers = ['Content-Type: application/json'];
    if ($interface_choice !== 'ollama') {
        $headers[] = 'Authorization: Bearer ' . $api_key;
        if ($interface_choice === 'claude') {
            $headers[] = 'x-api-key: ' . $api_key;
            $headers[] = 'anthropic-version: 2023-06-01';
        }
    }
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
    curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
    curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
    curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $chunk) use (&$fullReply, $interface_choice) {
        if ($interface_choice === 'ollama') {
            // Ollama 并不是以 data: 开头的，而是返回一个个 JSON 行
            $chunk_lines = explode("\n", trim($chunk));
            foreach ($chunk_lines as $chunk_line) {
                if (empty(trim($chunk_line))) continue;
                $json = json_decode($chunk_line, true);
                if ($json && isset($json['message']['content'])) {
                    // 转成前端期待的标准的 OpenAI 流式格式输出给前端气泡
                    echo 'data: ' . json_encode(['choices' => [['delta' => ['content' => $json['message']['content']]]]]) . "\n\n";
                    if (isset($json['done']) && $json['done']) {
                        echo "data: [DONE]\n\n";
                    }
                }
            }
        } else {
            echo $chunk;
        }

        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
        $fullReply[] = $chunk;
        return strlen($chunk);
    });
    curl_setopt($ch, CURLOPT_TIMEOUT, 0);
    $curl_result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_result === false || $http_code != 200) {
        $error_msg = "API request failed with HTTP code: $http_code";
        deepseek_chat_rest_record_usage($request_started_at, array(
            'user_id' => $user_id,
            'conversation_id' => $conversation_id ?: 0,
            'event_type' => 'chat',
            'interface_key' => $interface_choice,
            'model' => $model,
            'prompt_template_id' => $prompt_template ? (int) $prompt_template->id : $prompt_template_id,
            'kb_source_count' => !empty($kb_context['sources']) ? count($kb_context['sources']) : 0,
            'status' => 'error',
            'message' => $message,
            'meta' => array(
                'http_code' => (int) $http_code,
                'curl_error' => $curl_error,
                'enable_search' => $enable_search ? 1 : 0,
                'file_count' => is_array($file_ids) ? count($file_ids) : 0,
            ),
        ));
        echo "data: " . json_encode(['error' => $error_msg]) . "\n\n";
        flush();
        exit();
    }

    // 处理流式数据并提取内容
    $processedReply = ['content' => '', 'reasoning_content' => ''];
    $fullReplyString = implode('', $fullReply);
    $lines = explode("\n", $fullReplyString);

    foreach ($lines as $line) {
        $line = trim($line);
        if ($interface_choice === 'claude') {
            // Claude流式输出格式
            if (strpos($line, 'data:') === 0) {
                $dataPart = trim(substr($line, 5));
                if ($dataPart === '[DONE]') {
                    continue;
                }
                $jsonData = json_decode($dataPart, true);
                if ($jsonData && isset($jsonData['type'])) {
                    if ($jsonData['type'] === 'content_block_delta' && isset($jsonData['delta']['text'])) {
                        $processedReply['content'] .= $jsonData['delta']['text'];
                    }
                }
            }
        } elseif ($interface_choice === 'ollama') {
            // Ollama流式输出格式解析用于数据库保存
            if (empty($line)) continue;
            $jsonData = json_decode($line, true);
            if ($jsonData && isset($jsonData['message']['content'])) {
                $processedReply['content'] .= $jsonData['message']['content'];
            }
        } else {
            // OpenAI兼容格式
            if (strpos($line, 'data:') === 0) {
                $dataPart = trim(substr($line, 5));
                if ($dataPart === '[DONE]') {
                    continue;
                }
                $jsonData = json_decode($dataPart, true);
                if ($jsonData && isset($jsonData['choices'][0]['delta'])) {
                    $delta = $jsonData['choices'][0]['delta'];
                    if (isset($delta['content'])) {
                        $processedReply['content'] .= $delta['content'];
                    }
                    if (isset($delta['reasoning_content'])) {
                        $processedReply['reasoning_content'] .= $delta['reasoning_content'];
                    }
                }
            }
        }
    }

    if (!empty($kb_context['sources'])) {
        $processedReply['kb_sources'] = $kb_context['sources'];
    }
    if ($prompt_template) {
        $processedReply['prompt_template'] = array(
            'id' => (int) $prompt_template->id,
            'title' => $prompt_template->title,
        );
    }

    // 保存到数据库
    $reply = json_encode($processedReply);
    $saved_log = deepseek_save_chat_message(array(
        'user_id' => $user_id,
        'conversation_id' => $conversation_id ?: 0,
        'conversation_title' => $conversation_id ? '' : $message,
        'message' => $message,
        'response' => $reply,
    ));
    $conversation_id = $saved_log['conversation_id'];
    if ($prompt_template && function_exists('deepseek_prompt_template_increment_usage')) {
        deepseek_prompt_template_increment_usage((int) $prompt_template->id);
    }

    deepseek_chat_rest_record_usage($request_started_at, array(
        'user_id' => $user_id,
        'conversation_id' => $conversation_id,
        'message_id' => isset($saved_log['insert_id']) ? (int) $saved_log['insert_id'] : 0,
        'event_type' => 'chat',
        'interface_key' => $interface_choice,
        'model' => $model,
        'prompt_template_id' => $prompt_template ? (int) $prompt_template->id : 0,
        'kb_source_count' => !empty($kb_context['sources']) ? count($kb_context['sources']) : 0,
        'status' => 'success',
        'message' => $message,
        'response' => $processedReply['content'] . $processedReply['reasoning_content'],
        'meta' => array(
            'enable_search' => $enable_search ? 1 : 0,
            'file_count' => is_array($file_ids) ? count($file_ids) : 0,
        ),
    ));

    // 输出conversation_id
    echo "\n";
    echo "data: " . json_encode([
        'conversation_id' => $conversation_id,
        'message_id' => isset($saved_log['insert_id']) ? (int) $saved_log['insert_id'] : 0,
    ]) . "\n\n";
    if (!empty($kb_context['sources'])) {
        echo "data: " . json_encode(['kb_sources' => $kb_context['sources']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
    }
    if (ob_get_level() > 0) {
        ob_flush();
    }
    flush();

    exit();
}

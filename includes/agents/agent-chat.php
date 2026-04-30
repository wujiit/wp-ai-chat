<?php
if (!defined('ABSPATH')) {
    exit;
}

// 设置全局编码为UTF-8
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

function deepseek_agent_get_rest_nonce_from_request() {
    $nonce = '';
    if (isset($_REQUEST['nonce'])) {
        $nonce = sanitize_text_field(wp_unslash($_REQUEST['nonce']));
    } elseif (isset($_SERVER['HTTP_X_WP_NONCE'])) {
        $nonce = sanitize_text_field(wp_unslash($_SERVER['HTTP_X_WP_NONCE']));
    }
    return $nonce;
}

function deepseek_agent_rest_request_has_valid_wp_nonce(WP_REST_Request $request) {
    $nonce = $request->get_header('x-wp-nonce');
    if (empty($nonce)) {
        $nonce = $request->get_header('X-WP-Nonce');
    }
    return !empty($nonce) && wp_verify_nonce($nonce, 'wp_rest');
}

function deepseek_send_agent_message_permission(WP_REST_Request $request) {
    if (!is_user_logged_in()) {
        return new WP_Error(
            'rest_forbidden',
            '请先登录后再使用智能体对话',
            ['status' => 401]
        );
    }
    if (!deepseek_agent_rest_request_has_valid_wp_nonce($request)) {
        return new WP_Error(
            'rest_forbidden',
            '请求验证失败',
            ['status' => 403]
        );
    }
    return true;
}

// 获取智能体应用列表
add_action('wp_ajax_deepseek_get_agents', 'deepseek_get_agents');
function deepseek_get_agents() {
    $nonce = deepseek_agent_get_rest_nonce_from_request();
    if (!wp_verify_nonce($nonce, 'wp_rest')) {
        wp_send_json_error(['message' => '验证请求失败']);
        return;
    }

    $agents = deepseek_get_setting('deepseek_agents', []);
    $public_agents = [];

    if (is_array($agents)) {
        foreach ($agents as $agent) {
            if (!is_array($agent)) {
                continue;
            }
            $opening_questions = [];
            if (isset($agent['opening_questions']) && is_array($agent['opening_questions'])) {
                $opening_questions = array_map('sanitize_text_field', $agent['opening_questions']);
            }

            $public_agents[] = [
                'provider' => sanitize_text_field($agent['provider'] ?? ''),
                'name' => sanitize_text_field($agent['name'] ?? ''),
                'description' => sanitize_text_field($agent['description'] ?? ''),
                'icon' => esc_url_raw($agent['icon'] ?? ''),
                'app_id' => sanitize_text_field($agent['app_id'] ?? ''),
                'opening_questions' => $opening_questions,
                'enable_file_upload' => isset($agent['enable_file_upload']) && intval($agent['enable_file_upload']) === 1 ? 1 : 0,
            ];
        }
    }

    wp_send_json_success(['agents' => $public_agents]);
}

// 加载智能体对话历史
add_action('wp_ajax_deepseek_load_agent_log', 'deepseek_load_agent_log');
function deepseek_load_agent_log() {
    $nonce = deepseek_agent_get_rest_nonce_from_request();
    if (!wp_verify_nonce($nonce, 'wp_rest')) {
        wp_send_json_error(['message' => '验证请求失败']);
        return;
    }

    $app_id = isset($_GET['app_id']) ? sanitize_text_field(wp_unslash($_GET['app_id'])) : '';
    if (empty($app_id)) {
        wp_send_json_error(['message' => '缺少智能体应用 ID']);
        return;
    }
    $messages = deepseek_get_agent_logs_for_current_actor($app_id);
    wp_send_json_success(['messages' => $messages]);
}

// 注册REST API路由
add_action('rest_api_init', function () {
    register_rest_route('deepseek/v1', '/send-agent-message', [
        'methods' => 'POST',
        'callback' => 'deepseek_send_agent_message',
        'permission_callback' => 'deepseek_send_agent_message_permission',
    ]);
});

// 处理智能体消息发送
function deepseek_send_agent_message(WP_REST_Request $request) {
    global $wpdb;
    $table_name = deepseek_get_agent_chat_logs_table_name();
    $user_id = get_current_user_id();
    $message = sanitize_text_field($request->get_param('message'));
    $app_id = sanitize_text_field($request->get_param('app_id'));
    $session_id = sanitize_text_field((string) $request->get_param('session_id'));
    $file_data = $request->get_param('file_data'); // 接收前端传来的文件数据

    // 游客的 session_id 强制绑定为其设备指纹 MD5，以防止越权读取
    if ($user_id == 0) {
        $guest_session_id = deepseek_get_guest_session_id();
        if ($guest_session_id === '') {
            return new WP_REST_Response(['success' => false, 'message' => '非法请求'], 403);
        }
        $session_id = $guest_session_id;
    }

    if (!empty($session_id) && $user_id > 0) {
        $owned_session = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE user_id = %d AND app_id = %s AND session_id = %s",
            $user_id,
            $app_id,
            $session_id
        ));
        if ($owned_session <= 0) {
            $session_id = '';
        }
    }

    $agents = deepseek_get_setting('deepseek_agents', []);
    $agent = array_filter($agents, function ($a) use ($app_id) {
        return $a['app_id'] === $app_id;
    });
    $agent = reset($agent);

    // 检查智能体是否存在
    if (!$agent || !is_array($agent) || !isset($agent['provider'])) {
        header('Content-Type: text/event-stream; charset=UTF-8');
        echo "data: " . json_encode(['error' => '智能体未找到或配置无效'], JSON_UNESCAPED_UNICODE) . "\n\n";
        flush();
        exit;
    }
    $provider = $agent['provider'];

    // 保存用户消息到数据库
    $wpdb->insert($table_name, [
        'user_id' => $user_id,
        'app_id' => $app_id,
        'message' => $message,
        'response' => '',
        'session_id' => $session_id ?: null,
        'created_at' => current_time('mysql')
    ]);
    if ($wpdb->last_error) {
        error_log("保存用户消息失败: " . $wpdb->last_error);
    }

    // 设置流式输出头
    while (ob_get_level() > 0) ob_end_clean();
    ini_set('output_buffering', 'off');
    ini_set('zlib.output_compression', false);
    header('Content-Type: text/event-stream; charset=UTF-8');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');
    header('X-Accel-Buffering: no');

    $full_response = '';
    $current_session_id = $session_id;
    $access_token = '';

    // 根据提供商配置API请求
    switch ($provider) {
        case 'volc': // 火山引擎
            $api_key = deepseek_get_setting('volc_agent_api_key');
            if (empty($api_key)) {
                echo "data: " . json_encode(['error' => '火山引擎API Key未配置'], JSON_UNESCAPED_UNICODE) . "\n\n";
                flush();
                exit;
            }
            $url = "https://ark.cn-beijing.volces.com/api/v3/bots/chat/completions";
            $headers = [
                "Authorization: Bearer $api_key",
                "Content-Type: application/json; charset=UTF-8"
            ];
            // 构建历史对话消息 (如果 session_id 存在且需要带上文)
            $messages = [];
            if ($session_id) {
                $memory_limit = intval(deepseek_get_setting('deepseek_context_memory_limit', 5));
                $history = deepseek_get_agent_conversation_history($session_id, $memory_limit);
                foreach ($history as $item) {
                    if (!empty($item->message)) {
                        $messages[] = ['role' => 'user', 'content' => $item->message];
                    }
                    if (!empty($item->response)) {
                        $messages[] = ['role' => 'assistant', 'content' => $item->response];
                    }
                }
            }
            // 追加当前用户消息
            $messages[] = ['role' => 'user', 'content' => $message];

            $body = [
                'model' => $app_id,
                'stream' => true,
                'messages' => $messages
            ];
            $body = json_encode($body, JSON_UNESCAPED_UNICODE);
            break;

        case 'ali': // 阿里
            $api_key = deepseek_get_setting('ali_agent_api_key');
            if (empty($api_key)) {
                echo "data: " . json_encode(['error' => '阿里API Key未配置'], JSON_UNESCAPED_UNICODE) . "\n\n";
                flush();
                exit;
            }
            $url = "https://dashscope.aliyuncs.com/api/v1/apps/{$app_id}/completion";
            $headers = [
                "Authorization: Bearer $api_key",
                "Content-Type: application/json; charset=UTF-8",
                "X-DashScope-SSE: enable"
            ];
            $body = [
                'input' => ['prompt' => $message],
                'parameters' => ['incremental_output' => true],
                'debug' => []
            ];
            if ($session_id) {
                $body['input']['session_id'] = $session_id;
            }
            $body = json_encode($body, JSON_UNESCAPED_UNICODE);
            break;

        case 'tencent': // 腾讯
            $tencent_token = $agent['token'] ?? '';
            if (empty($tencent_token)) {
                echo "data: " . json_encode(['error' => '此腾讯智能体Token未配置'], JSON_UNESCAPED_UNICODE) . "\n\n";
                flush();
                exit;
            }
            $url = "https://open.hunyuan.tencent.com/openapi/v1/agent/chat/completions";
            $headers = [
                "X-Source: openapi",
                "Content-Type: application/json; charset=UTF-8",
                "Authorization: Bearer $tencent_token"
            ];
            // 构建历史对话消息 (如果 session_id 存在且需要带上文)
            $messages = [];
            if ($session_id) {
                $memory_limit = intval(deepseek_get_setting('deepseek_context_memory_limit', 5));
                $history = deepseek_get_agent_conversation_history($session_id, $memory_limit);
                foreach ($history as $item) {
                    if (!empty($item->message)) {
                        $messages[] = [
                            "role" => "user",
                            "content" => [
                                ["type" => "text", "text" => $item->message]
                            ]
                        ];
                    }
                    if (!empty($item->response)) {
                        $messages[] = [
                            "role" => "assistant",
                            "content" => [
                                ["type" => "text", "text" => $item->response]
                            ]
                        ];
                    }
                }
            }
            // 追加当前用户消息
            $messages[] = [
                "role" => "user",
                "content" => [
                    ["type" => "text", "text" => $message]
                ]
            ];

            $body = [
                "assistant_id" => $app_id,
                "user_id" => strval($user_id),
                "stream" => true,
                "messages" => $messages
            ];
            $body = json_encode($body, JSON_UNESCAPED_UNICODE);
            break;

        case 'coze': // 扣子
            $access_token = deepseek_get_setting('coze_access_token');
            $expiry = deepseek_get_setting('coze_access_token_expiry');
            if (empty($access_token)) {
                echo "data: " . json_encode(['error' => '扣子平台Access Token未配置'], JSON_UNESCAPED_UNICODE) . "\n\n";
                flush();
                exit;
            }
            if ($expiry && strtotime($expiry) < time()) {
                echo "data: " . json_encode(['error' => '扣子平台Access Token已过期，请更新'], JSON_UNESCAPED_UNICODE) . "\n\n";
                flush();
                exit;
            }
            $url = "https://api.coze.cn/v3/chat";
            $headers = [
                "Authorization: Bearer $access_token",
                "Content-Type: application/json; charset=UTF-8"
            ];

            // 构造合并的additional_messages
            $additional_messages = [];
            $combined_content = '';
            if ($file_data && $agent['enable_file_upload']) {
            // 将文件URL添加到内容中
                $combined_content .= $file_data['file_url'];
            }
            if ($message) {
            // 如果有用户输入，将其与文件URL合并，添加空格分隔
                $combined_content .= ($combined_content ? ' ' : '') . $message;
            }
            if ($combined_content) {
                $additional_messages[] = [
                    'role' => 'user',
                    'content' => $combined_content
                ];
            }

            $body = [
                'bot_id' => $app_id,
                'user_id' => strval($user_id),
                'stream' => true,
                'auto_save_history' => true,
                'additional_messages' => $additional_messages

            ];
            if ($session_id) {
                $body['conversation_id'] = $session_id;
            }
            $body = json_encode($body, JSON_UNESCAPED_UNICODE);
            break;

        default:
            echo "data: " . json_encode(['error' => '未知的智能体提供商'], JSON_UNESCAPED_UNICODE) . "\n\n";
            flush();
            exit;
        }

        // 定义统一的响应处理函数
        $write_function = function ($ch, $data) use (&$full_response, &$current_session_id, $provider, $app_id, $user_id, $access_token) {
        $original_data = $data;
        $data = mb_convert_encoding($data, 'UTF-8', 'UTF-8');
        $lines = explode("\n", $data);
        $should_flush = false;
        $event = null;

        foreach ($lines as $line) {
            $line = trim($line);

            if ($provider === 'coze' && strpos($line, 'event:') === 0) {
                $event = trim(substr($line, 6));
                continue;
            }

            if ($line && strpos($line, 'data:') === 0) {
                $json_str = trim(substr($line, 5));

                if ($json_str === '[DONE]') {
                    echo "data: [DONE]\n\n";
                    $should_flush = true;
                    continue;
                }

                $json_data = json_decode($json_str, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    error_log("JSON decode error: " . json_last_error_msg());
                    continue;
                }

                // 根据提供商处理响应内容
                switch ($provider) {
                    case 'volc':
                        if (isset($json_data['choices'][0]['delta']['content'])) {
                            $content = $json_data['choices'][0]['delta']['content'];
                            $full_response .= $content;
                            echo "data: " . json_encode(['text' => $content], JSON_UNESCAPED_UNICODE) . "\n\n";
                            $should_flush = true;
                        }
                        break;

                    case 'ali':
                        if (isset($json_data['output']['text']) && !empty($json_data['output']['text'])) {
                            $full_response .= $json_data['output']['text'];
                            echo "data: " . json_encode(['text' => $json_data['output']['text']], JSON_UNESCAPED_UNICODE) . "\n\n";
                            $should_flush = true;
                        }
                        if (isset($json_data['output']['session_id'])) {
                            $current_session_id = $json_data['output']['session_id'];
                        }
                        break;

                    case 'tencent':
                        if (isset($json_data['choices'][0]['delta']['role']) && 
                            $json_data['choices'][0]['delta']['role'] === 'assistant' && 
                            isset($json_data['choices'][0]['delta']['content'])) {
                            $content = $json_data['choices'][0]['delta']['content'];
                            $full_response .= $content;
                            echo "data: " . json_encode(['text' => $content], JSON_UNESCAPED_UNICODE) . "\n\n";
                            $should_flush = true;
                        }
                        break;

                    case 'coze':
                        // 处理普通消息
                        if (isset($event) && $event === 'conversation.message.delta' && 
                            isset($json_data['content']) && 
                            isset($json_data['role']) && $json_data['role'] === 'assistant' && 
                            isset($json_data['type']) && $json_data['type'] === 'answer') {
                            $full_response .= $json_data['content'];
                            echo "data: " . json_encode(['text' => $json_data['content']], JSON_UNESCAPED_UNICODE) . "\n\n";
                            $should_flush = true;
                        }
                        // 处理function_call
                        elseif (isset($event) && $event === 'conversation.chat.tool_call.invoke' && 
                                isset($json_data['tool_calls']) && !empty($json_data['tool_calls'])) {
                            $tool_call = $json_data['tool_calls'][0];
                            $tool_call_id = $tool_call['id'];
                            $function_name = $tool_call['function']['name'];
                            $arguments = json_decode($tool_call['function']['arguments'], true);

                            $tool_output = "文件已处理"; // 插件处理结果
                            submit_tool_output($app_id, $user_id, $access_token, $tool_call_id, $tool_output);
                        }
                        if (isset($json_data['conversation_id'])) {
                            $current_session_id = $json_data['conversation_id'];
                        }
                        break;
                }
            }
        }

        if ($should_flush) flush();
        return strlen($original_data);
    };

    // 执行cURL请求
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
    curl_setopt($ch, CURLOPT_WRITEFUNCTION, $write_function);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

    $result = curl_exec($ch);

    if ($result === false) {
        $curl_error = curl_error($ch);
        $curl_errno = curl_errno($ch);
        error_log("cURL Error ($curl_errno): " . $curl_error);
        echo "data: " . json_encode(['text' => "数据获取失败（错误代码: $curl_errno - $curl_error），请重试"], JSON_UNESCAPED_UNICODE) . "\n\n";
        echo "data: [DONE]\n\n";
        flush();
    } else {
        echo "data: [DONE]\n\n";
        flush();

        if (!empty($full_response)) {
            $wpdb->insert($table_name, [
                'user_id' => $user_id,
                'app_id' => $app_id,
                'message' => '',
                'response' => $full_response,
                'session_id' => $current_session_id,
                'created_at' => current_time('mysql')
            ]);
            if ($wpdb->last_error) {
                error_log("保存智能体回复失败: " . $wpdb->last_error);
            }
        } else {
            error_log("No response content received");
            echo "data: " . json_encode(['text' => '没有收到响应内容，请重试'], JSON_UNESCAPED_UNICODE) . "\n\n";
            echo "data: [DONE]\n\n";
            flush();
        }
    }
    curl_close($ch);
    exit;
}

    // 提交工具执行结果给Coze API
    function submit_tool_output($bot_id, $user_id, $access_token, $tool_call_id, $output) {
    $url = "https://api.coze.cn/v3/chat/submit_tool_outputs";
    $headers = [
        "Authorization: Bearer $access_token",
        "Content-Type: application/json; charset=UTF-8"
    ];
    $body = [
        'bot_id' => $bot_id,
        'user_id' => strval($user_id),
        'stream' => true,
        'tool_outputs' => [
            [
                'tool_call_id' => $tool_call_id,
                'output' => $output
            ]
        ]
    ];
    $body = json_encode($body, JSON_UNESCAPED_UNICODE);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    deepseek_apply_curl_timeouts($ch, 'agent_tool_output', 30, 10);

    $response = curl_exec($ch);
    curl_close($ch);
}

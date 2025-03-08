<?php

if (!defined('ABSPATH')) {
    exit; // 防止直接访问
}

// 设置全局编码为UTF-8
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

// 获取智能体应用列表
add_action('wp_ajax_deepseek_get_agents', 'deepseek_get_agents');
function deepseek_get_agents() {
    $agents = get_option('deepseek_agents', []);
    wp_send_json_success(['agents' => $agents]);
}

// 加载智能体对话历史
add_action('wp_ajax_deepseek_load_agent_log', 'deepseek_load_agent_log');
function deepseek_load_agent_log() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'deepseek_agent_chat_logs';
    $user_id = get_current_user_id();
    $app_id = sanitize_text_field($_GET['app_id']);
    $messages = $wpdb->get_results($wpdb->prepare(
        "SELECT message, response, session_id FROM $table_name WHERE user_id = %d AND app_id = %s ORDER BY created_at ASC",
        $user_id, $app_id
    ));
    wp_send_json_success(['messages' => $messages]);
}

// 注册REST API路由
add_action('rest_api_init', function () {
    register_rest_route('deepseek/v1', '/send-agent-message', [
        'methods' => 'POST',
        'callback' => 'deepseek_send_agent_message',
        'permission_callback' => function () {
            return is_user_logged_in();
        },
    ]);
});

// 处理智能体消息发送
function deepseek_send_agent_message(WP_REST_Request $request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'deepseek_agent_chat_logs';
    $user_id = get_current_user_id();
    $message = sanitize_text_field($request->get_param('message'));
    $app_id = sanitize_text_field($request->get_param('app_id'));
    $session_id = $request->get_param('session_id');
    $file_data = $request->get_param('file_data'); // 接收前端传来的文件数据

    $agents = get_option('deepseek_agents', []);
    $agent = array_filter($agents, function ($a) use ($app_id) {
        return $a['app_id'] === $app_id;
    });
    $agent = reset($agent);
    if (!$agent) {
        header('Content-Type: text/event-stream; charset=UTF-8');
        echo "data: " . json_encode(['error' => '智能体未找到'], JSON_UNESCAPED_UNICODE) . "\n\n";
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

    // 根据提供商配置API请求
    switch ($provider) {
        case 'volc': // 火山引擎
            $api_key = get_option('volc_agent_api_key');
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
            $body = [
                'model' => $app_id,
                'stream' => true,
                'messages' => [
                    ['role' => 'user', 'content' => $message]
                ]
            ];
            $body = json_encode($body, JSON_UNESCAPED_UNICODE);
            break;

        case 'ali': // 阿里
            $api_key = get_option('ali_agent_api_key');
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
            $body = [
                "assistant_id" => $app_id,
                "user_id" => strval($user_id),
                "stream" => true,
                "messages" => [
                    [
                        "role" => "user",
                        "content" => [
                            [
                                "type" => "text",
                                "text" => $message
                            ]
                        ]
                    ]
                ]
            ];
            $body = json_encode($body, JSON_UNESCAPED_UNICODE);
            break;

        case 'coze': // 扣子
            $access_token = get_option('coze_access_token');
            $expiry = get_option('coze_access_token_expiry');
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
        error_log("Raw response data: " . $data);
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
                error_log("Parsed line: " . $json_str);

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
    curl_setopt($ch, CURLOPT_VERBOSE, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

    error_log("Request URL: " . $url);
    error_log("Request Headers: " . json_encode($headers));
    error_log("Request Body: " . $body);

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
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    error_log("Tool output submission response: " . $response);
    curl_close($ch);
}

// 智能体设置页面
function deepseek_render_agents_page() {
    $saved = isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true';
    ?>
    <style>
        .dashscope-wrap {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin: 20px 0;
        }
        .dashscope-wrap h1 {
            font-size: 24px;
            color: #333;
        }
        .dashscope-section {
            margin-bottom: 30px;
        }
        .dashscope-section h2 {
            font-size: 20px;
            color: #444;
            margin-bottom: 15px;
        }
        .dashscope-wrap table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
        }
        .dashscope-wrap th, .dashscope-wrap td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }
        .dashscope-wrap input[type="text"], .dashscope-wrap input[type="url"], .dashscope-wrap textarea, .dashscope-wrap input[type="datetime-local"] {
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }
        #deepseek-agents-table th:nth-child(1), #deepseek-agents-table td:nth-child(1),
        #deepseek-agents-table th:nth-child(8), #deepseek-agents-table td:nth-child(8) {
            width: 100px;
            white-space: nowrap;
        }
        .dashscope-wrap button {
            background: #0073aa;
            color: #fff;
            border: none;
            padding: 8px 12px;
            cursor: pointer;
            border-radius: 4px;
        }
        .dashscope-wrap button:hover {
            background: #005a87;
        }
        .dashscope-wrap .success-message {
            display: none;
            margin-top: 15px;
            padding: 10px;
            background-color: #dff0d8;
            color: #3c763d;
            border: 1px solid #d6e9c6;
            border-radius: 4px;
            text-align: center;
        }
    </style>

    <div class="dashscope-wrap">
        <h1>智能体应用管理</h1>
        <form method="post" action="options.php">
            <?php settings_fields('deepseek_agents_group'); ?>
            
            <div class="dashscope-section">
                <h2>智能体配置</h2>
                <?php
                do_settings_sections('deepseek-agents');
                ?>
                <p>
                    <strong>支持的文件格式</strong>
                    <input type="text" id="agent_file_formats" name="agent_file_formats" value="<?php echo esc_attr(get_option('agent_file_formats', 'pdf')); ?>" style="width: 500px;" />
                    <p class="description">多种用英文逗号分隔，例如：pdf,docx,txt</p>
                </p>
                <p>
                    <strong>最大文件大小（MB）</strong>
                    <input type="number" id="agent_file_max_size" name="agent_file_max_size" value="<?php echo esc_attr(get_option('agent_file_max_size', 10)); ?>" min="1" style="width: 100px;" />
                    <p class="description">设置支持上传的最大文件大小，单位为MB</p>
                </p>

                <p>支持阿里、腾讯、火山引擎和扣子平台的智能体应用。阿里API Key就是百炼里面的，腾讯需为每个智能体单独设置Token，火山引擎和模型apikey一样，扣子的个人访问令牌Token需定期更换，文件上传只支持扣子应用。</p>
            </div>

            <div class="dashscope-section">
                <h2>智能体应用列表</h2>
                <?php deepseek_agents_list_callback(); ?>
            </div>

            <?php submit_button('保存设置'); ?>
        </form>
        <div class="success-message" <?php echo $saved ? 'style="display: block;"' : ''; ?>>设置已保存</div>
        <p>只支持普通对话，只支持联网搜索插件，其他插件可能有最终结果，但是不一定显示过程，文件上传只支持扣子应用。</p>
    </div>

    <?php if ($saved) : ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const successMessage = document.querySelector('.dashscope-wrap .success-message');
        setTimeout(() => {
            successMessage.style.display = 'none';
        }, 2000);
    });
    </script>
    <?php endif; ?>
    <?php
}

// 智能体应用对话记录管理页面
function deepseek_render_agent_logs_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'deepseek_agent_chat_logs';

    $per_page = 20;
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $per_page;

    $total_logs = $wpdb->get_var("SELECT COUNT(DISTINCT user_id, app_id) FROM $table_name WHERE message != ''");

    $logs = $wpdb->get_results($wpdb->prepare(
        "SELECT user_id, app_id, MIN(id) as first_id, message, created_at 
         FROM $table_name 
         WHERE message != '' 
         GROUP BY user_id, app_id 
         ORDER BY created_at DESC 
         LIMIT %d OFFSET %d",
        $per_page, $offset
    ));

    $agents = get_option('deepseek_agents', []);
    $agent_map = [];
    foreach ($agents as $agent) {
        $agent_map[$agent['app_id']] = $agent['name'];
    }
    ?>
    <div class="wrap">
        <h1>智能体应用对话记录</h1>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>用户ID</th>
                    <th>智能体应用</th>
                    <th>首句消息</th>
                    <th>时间</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($logs)) : ?>
                    <?php foreach ($logs as $log) : ?>
                        <tr data-user-id="<?php echo esc_attr($log->user_id); ?>" data-app-id="<?php echo esc_attr($log->app_id); ?>">
                            <td><?php echo esc_html($log->user_id); ?></td>
                            <td><?php echo esc_html(isset($agent_map[$log->app_id]) ? $agent_map[$log->app_id] : $log->app_id); ?></td>
                            <td><?php echo esc_html(mb_strimwidth($log->message, 0, 50, '...', 'UTF-8')); ?></td>
                            <td><?php echo esc_html($log->created_at); ?></td>
                            <td><button class="button delete-agent-log">删除</button></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr><td colspan="5">暂无记录。</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <?php
                $total_pages = ceil($total_logs / $per_page);
                echo paginate_links([
                    'base' => add_query_arg('paged', '%#%'),
                    'format' => '',
                    'prev_text' => __('« 上一页'),
                    'next_text' => __('下一页 »'),
                    'total' => $total_pages,
                    'current' => $current_page,
                ]);
                ?>
            </div>
        </div>
    </div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.delete-agent-log').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            if (!confirm('确定要删除此对话记录吗？')) return;

            const row = this.closest('tr');
            const userId = row.getAttribute('data-user-id');
            const appId = row.getAttribute('data-app-id');

            // 添加nonce以提高安全性
            const data = new URLSearchParams({
                action: 'deepseek_delete_agent_log',
                user_id: userId,
                app_id: appId,
                nonce: '<?php echo wp_create_nonce('delete_agent_log_nonce'); ?>'
            });

            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: data
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    row.remove();
                    alert('记录已删除');
                } else {
                    alert('删除失败: ' + (data.data?.message || '未知错误'));
                }
            })
            .catch(error => {
                console.error('删除请求失败:', error);
                alert('删除请求失败，请稍后重试');
            });
        });
    });
});
</script>
    <?php
}

// 注册设置
function deepseek_register_agents_settings() {
    register_setting('deepseek_agents_group', 'ali_agent_api_key', 'sanitize_text_field');
    register_setting('deepseek_agents_group', 'coze_access_token', 'sanitize_text_field');
    register_setting('deepseek_agents_group', 'coze_access_token_expiry', 'deepseek_sanitize_expiry');
    register_setting('deepseek_agents_group', 'deepseek_agents', 'deepseek_sanitize_agents');
    register_setting('deepseek_agents_group', 'volc_agent_api_key', 'sanitize_text_field');
    register_setting('deepseek_agents_group', 'agent_file_formats', 'sanitize_text_field');
    register_setting('deepseek_agents_group', 'agent_file_max_size', 'intval');

    add_settings_section('deepseek_agents_section', '', null, 'deepseek-agents');
    add_settings_field('ali_agent_api_key', '阿里智能体API KEY', 'ali_agent_api_key_callback', 'deepseek-agents', 'deepseek_agents_section');
    add_settings_field('volc_agent_api_key', '火山引擎API Key', 'volc_agent_api_key_callback', 'deepseek-agents', 'deepseek_agents_section');
    add_settings_field('coze_access_token', '扣子访问令牌Token', 'coze_access_token_callback', 'deepseek-agents', 'deepseek_agents_section');
}
add_action('admin_init', 'deepseek_register_agents_settings');

// 火山引擎API Key回调函数
function volc_agent_api_key_callback() {
    $api_key = get_option('volc_agent_api_key');
    echo '<input type="text" name="volc_agent_api_key" value="' . esc_attr($api_key) . '" style="width: 500px;" />';
    echo '<p class="description">输入火山引擎应用的API Key。</p>';
}

// 阿里API Key回调函数
function ali_agent_api_key_callback() {
    $api_key = get_option('ali_agent_api_key');
    echo '<input type="text" name="ali_agent_api_key" value="' . esc_attr($api_key) . '" style="width: 500px;" />';
    echo '<p class="description">输入阿里智能体应用的API KEY。</p>';
}

// 扣子回调函数
function coze_access_token_callback() {
    $token = get_option('coze_access_token');
    $expiry = get_option('coze_access_token_expiry');
    ?>
    <input type="text" name="coze_access_token" value="<?php echo esc_attr($token); ?>" style="width: 500px;" />
    <p class="description">输入扣子平台的个人访问令牌。</p>
    <p>
        <label for="coze_access_token_expiry">Token 到期时间：</label><br>
        <input type="datetime-local" id="coze_access_token_expiry" name="coze_access_token_expiry" value="<?php echo esc_attr($expiry); ?>" />
        <p class="description">设置Token的到期时间，例如：2025-03-01</p>
    </p>
    <?php if ($expiry) : ?>
        <p style="color: #d63638;">当前Token到期时间：<?php echo esc_html(date('Y-m-d H:i', strtotime($expiry))); ?></p>
    <?php endif; ?>
    <?php
}

// 添加应用
function deepseek_agents_list_callback() {
    $agents = get_option('deepseek_agents', []);
    ?>
    <table class="widefat" id="deepseek-agents-table">
        <thead>
            <tr>
                <th>提供商</th>
                <th>名称</th>
                <th>描述</th>
                <th>图标URL</th>
                <th>应用ID</th>
                <th>腾讯Token</th>
                <th>开场问题(一行一个)</th>
                <th>文件上传</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($agents as $index => $agent) : ?>
                <tr>
                    <td>
                        <select name="deepseek_agents[<?php echo $index; ?>][provider]" onchange="toggleTokenField(this)">
                            <option value="ali" <?php selected($agent['provider'], 'ali'); ?>>阿里</option>
                            <option value="tencent" <?php selected($agent['provider'], 'tencent'); ?>>腾讯</option>
                            <option value="coze" <?php selected($agent['provider'], 'coze'); ?>>扣子</option>
                            <option value="volc" <?php selected($agent['provider'], 'volc'); ?>>火山引擎</option>
                        </select>
                    </td>
                    <td><input type="text" name="deepseek_agents[<?php echo $index; ?>][name]" value="<?php echo esc_attr($agent['name']); ?>" /></td>
                    <td><input type="text" name="deepseek_agents[<?php echo $index; ?>][description]" value="<?php echo esc_attr($agent['description']); ?>" /></td>
                    <td><input type="url" name="deepseek_agents[<?php echo $index; ?>][icon]" value="<?php echo esc_attr($agent['icon']); ?>" /></td>
                    <td><input type="text" name="deepseek_agents[<?php echo $index; ?>][app_id]" value="<?php echo esc_attr($agent['app_id']); ?>" /></td>
                    <td>
                        <?php if ($agent['provider'] === 'tencent') : ?>
                            <input type="text" name="deepseek_agents[<?php echo $index; ?>][token]" value="<?php echo esc_attr($agent['token'] ?? ''); ?>" style="width: 200px;" />
                        <?php else : ?>
                            <span>-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <textarea name="deepseek_agents[<?php echo $index; ?>][opening_questions]" rows="3" cols="30"><?php 
                            echo esc_textarea(implode("\n", $agent['opening_questions'] ?? [])); 
                        ?></textarea>
                    </td>
                    <td>
                        <input type="checkbox" name="deepseek_agents[<?php echo $index; ?>][enable_file_upload]" value="1" <?php checked($agent['enable_file_upload'] ?? 0, 1); ?> />
                    </td>
                    <td><button type="button" class="button delete-agent">删除</button></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <button type="button" class="button" id="add-agent">添加新智能体</button>
    <script>
        document.getElementById('add-agent').addEventListener('click', function() {
            var table = document.getElementById('deepseek-agents-table').getElementsByTagName('tbody')[0];
            var rowCount = table.rows.length;
            var row = table.insertRow();
            row.innerHTML = `
                <td>
                    <select name="deepseek_agents[${rowCount}][provider]" onchange="toggleTokenField(this)">
                        <option value="ali">阿里</option>
                        <option value="tencent">腾讯</option>
                        <option value="coze">扣子</option>
                        <option value="volc">火山引擎</option>
                    </select>
                </td>
                <td><input type="text" name="deepseek_agents[${rowCount}][name]" value="" /></td>
                <td><input type="text" name="deepseek_agents[${rowCount}][description]" value="" /></td>
                <td><input type="url" name="deepseek_agents[${rowCount}][icon]" value="" /></td>
                <td><input type="text" name="deepseek_agents[${rowCount}][app_id]" value="" /></td>
                <td><span>-</span></td>
                <td>
                    <textarea name="deepseek_agents[${rowCount}][opening_questions]" rows="3" cols="30"></textarea>
                </td>
                <td><input type="checkbox" name="deepseek_agents[${rowCount}][enable_file_upload]" value="1" /></td>
                <td><button type="button" class="button delete-agent">删除</button></td>
            `;
        });
        
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('delete-agent')) {
                e.target.closest('tr').remove();
            }
        });

        function toggleTokenField(select) {
            var row = select.closest('tr');
            var tokenCell = row.cells[5];
            if (select.value === 'tencent') {
                tokenCell.innerHTML = '<input type="text" name="' + select.name.replace('provider', 'token') + '" value="" style="width: 200px;" />';
            } else {
                tokenCell.innerHTML = '<span>-</span>';
            }
        }
    </script>
    <?php
}

// 清理智能体数据
function deepseek_sanitize_agents($input) {
    $agents = [];
    if (is_array($input)) {
        foreach ($input as $agent) {
            if (!empty($agent['name']) && !empty($agent['app_id'])) {
                $opening_questions = [];
                if (!empty($agent['opening_questions']) && is_string($agent['opening_questions'])) {
                    $opening_questions = array_filter(array_map('trim', explode("\n", $agent['opening_questions'])));
                }

                $sanitized_agent = [
                    'provider' => sanitize_text_field($agent['provider'] ?? 'ali'),
                    'name' => sanitize_text_field($agent['name']),
                    'description' => sanitize_text_field($agent['description']),
                    'icon' => esc_url_raw($agent['icon']),
                    'app_id' => sanitize_text_field($agent['app_id']),
                    'opening_questions' => array_map('sanitize_text_field', $opening_questions),
                    'enable_file_upload' => isset($agent['enable_file_upload']) && $agent['enable_file_upload'] == '1' ? 1 : 0,
                ];
                
                if ($agent['provider'] === 'tencent' && !empty($agent['token'])) {
                    $sanitized_agent['token'] = sanitize_text_field($agent['token']);
                }
                
                $agents[] = $sanitized_agent;
            }
        }
    }
    return $agents;
}

// 清理Token到期时间格式
function deepseek_sanitize_expiry($input) {
    if (empty($input)) {
        return '';
    }
    $timestamp = strtotime($input);
    if ($timestamp === false) {
        add_settings_error(
            'deepseek_agents_group',
            'invalid_expiry',
            'Token 到期时间格式无效，请使用正确的日期时间格式（如 2025-03-01）。',
            'error'
        );
        return get_option('coze_access_token_expiry');
    }
    return date('Y-m-d\TH:i', $timestamp);
}

// 删除智能体对话记录
function deepseek_delete_agent_log() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'deepseek_agent_chat_logs';
    
    // 检查是否通过POST传递了必要参数
    if (!isset($_POST['user_id']) || !isset($_POST['app_id'])) {
        wp_send_json_error(['message' => '缺少必要参数']);
        return;
    }

    $user_id = intval($_POST['user_id']);
    $app_id = sanitize_text_field($_POST['app_id']);

    // 验证管理员权限
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => '无权删除记录']);
        return;
    }

    // 执行删除操作
    $deleted = $wpdb->delete(
        $table_name,
        ['user_id' => $user_id, 'app_id' => $app_id],
        ['%d', '%s']
    );

    // 检查删除结果
    if ($wpdb->last_error) {
        error_log("删除对话记录失败: " . $wpdb->last_error);
        wp_send_json_error(['message' => '删除失败: ' . $wpdb->last_error]);
    } elseif ($deleted === 0) {
        wp_send_json_error(['message' => '没有找到匹配的记录']);
    } else {
        wp_send_json_success(['message' => '记录已删除']);
    }
}
add_action('wp_ajax_deepseek_delete_agent_log', 'deepseek_delete_agent_log');

// 前台清除智能体对话记录
function deepseek_clear_agent_conversation() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'deepseek_agent_chat_logs';
    $app_id = sanitize_text_field($_POST['app_id']);
    $user_id = get_current_user_id();

    if (!$user_id) {
        wp_send_json(['success' => false, 'message' => '请先登录']);
        return;
    }

    if (empty($app_id)) {
        wp_send_json(['success' => false, 'message' => '缺少智能体应用 ID']);
        return;
    }

    $deleted = $wpdb->delete(
        $table_name,
        ['user_id' => $user_id, 'app_id' => $app_id],
        ['%d', '%s']
    );

    if ($wpdb->last_error) {
        error_log("清除对话记录失败: " . $wpdb->last_error);
        wp_send_json(['success' => false, 'message' => '清除失败: 数据库错误']);
    } elseif ($deleted === false) {
        wp_send_json(['success' => false, 'message' => '清除失败: 操作无效']);
    } else {
        wp_send_json(['success' => true, 'message' => '对话记录已清除']);
    }
}
add_action('wp_ajax_deepseek_clear_agent_conversation', 'deepseek_clear_agent_conversation');

// 处理智能体文件上传
add_action('wp_ajax_deepseek_upload_agent_file', 'deepseek_upload_agent_file');
function deepseek_upload_agent_file() {
    check_ajax_referer('agent_file_upload_action', 'nonce');

    if (!isset($_FILES['file']) || $_FILES['file']['error'] == UPLOAD_ERR_NO_FILE) {
        wp_send_json_error(['message' => '未选择文件']);
    }

    $file = $_FILES['file'];
    $allowed_types = explode(',', get_option('agent_file_formats', 'pdf')); // 支持的文件格式
    $max_size = intval(get_option('agent_file_max_size', 10)) * 1024 * 1024; // 最大文件大小（MB转换为字节）

    $file_type = pathinfo($file['name'], PATHINFO_EXTENSION);
    if (!in_array(strtolower($file_type), array_map('strtolower', $allowed_types))) {
        wp_send_json_error(['message' => '不支持的文件格式', 'allowed_types' => implode(', ', $allowed_types)]);
    }

    if ($file['size'] > $max_size) {
        wp_send_json_error(['message' => '文件大小超过限制 (' . ($max_size / 1024 / 1024) . 'MB)']);
    }

    $upload_overrides = array('test_form' => false);
    $uploaded_file = wp_handle_upload($file, $upload_overrides);

    if (isset($uploaded_file['error'])) {
        wp_send_json_error(['message' => '文件上传失败: ' . $uploaded_file['error']]);
    }

    $attachment = array(
        'guid' => $uploaded_file['url'],
        'post_mime_type' => $uploaded_file['type'],
        'post_title' => sanitize_file_name($file['name']),
        'post_content' => '',
        'post_status' => 'inherit'
    );

    $attachment_id = wp_insert_attachment($attachment, $uploaded_file['file']);
    if (is_wp_error($attachment_id)) {
        wp_send_json_error(['message' => '保存文件到媒体库失败']);
    }

    wp_send_json_success([
        'file_url' => $uploaded_file['url'],
        'file_name' => $file['name'],
        'suffix_type' => $file_type
    ]);
}

// 插件卸载清理
function deepseek_cleanup_options() {
    if (!defined('WP_UNINSTALL_PLUGIN')) {
        exit;
    }
    delete_option('ali_agent_api_key');
    delete_option('coze_access_token');
    delete_option('coze_access_token_expiry');
    delete_option('deepseek_agents');
    delete_option('tencent_token');
    delete_option('volc_agent_api_key');
}
register_uninstall_hook(__FILE__, 'deepseek_cleanup_options');

?>
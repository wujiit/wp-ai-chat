<?php

if (!defined('ABSPATH')) {
    exit; // 防止直接访问
}

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

    // 获取智能体配置
    $agents = get_option('deepseek_agents', []);
    $agent = array_filter($agents, function ($a) use ($app_id) {
        return $a['app_id'] === $app_id;
    });
    $agent = reset($agent); // 获取第一个匹配的智能体
    if (!$agent) {
        header('Content-Type: text/event-stream');
        echo "data: " . json_encode(['error' => '智能体未找到']) . "\n\n";
        flush();
        exit;
    }
    $provider = $agent['provider'];

    // 保存用户消息
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

    // 清空缓冲区，设置流式响应头
    while (ob_get_level() > 0) {
        ob_end_clean(); // 确保清除所有现有的输出缓冲
    }
    ini_set('output_buffering', 'off');
    ini_set('zlib.output_compression', false);
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');
    header('X-Accel-Buffering: no');

    $full_response = '';
    $current_session_id = $session_id;

    // 根据提供商处理请求
    if ($provider === 'ali') {
        $api_key = get_option('ali_agent_api_key');
        if (empty($api_key)) {
            echo "data: " . json_encode(['error' => '阿里API Key未配置']) . "\n\n";
            flush();
            exit;
        }
        $url = "https://dashscope.aliyuncs.com/api/v1/apps/{$app_id}/completion";
        $headers = [
            "Authorization: Bearer $api_key",
            "Content-Type: application/json",
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
        $body = json_encode($body);

        $write_function = function ($ch, $data) use (&$full_response, &$current_session_id) {
            $lines = explode("\n", $data);
            foreach ($lines as $line) {
                $line = trim($line);
                if (strpos($line, 'data:') === 0) {
                    $json_str = trim(substr($line, 5));
                    if ($json_str !== '[DONE]') {
                        $json_data = json_decode($json_str, true);
                        if (isset($json_data['output']['text']) && !empty($json_data['output']['text'])) {
                            $full_response .= $json_data['output']['text'];
                            echo "data: " . json_encode(['text' => $json_data['output']['text']]) . "\n\n";
                        }
                        if (isset($json_data['output']['session_id'])) {
                            $current_session_id = $json_data['output']['session_id'];
                        }
                    }
                }
            }
            // 如果ob_flush()也可以不过要先判断
            flush();
            return strlen($data);
        };
    } elseif ($provider === 'tencent') {
        $tencent_token = get_option('tencent_token');
        if (empty($tencent_token)) {
            echo "data: " . json_encode(['error' => '腾讯Token未配置']) . "\n\n";
            flush();
            exit;
        }
        $url = "https://open.hunyuan.tencent.com/openapi/v1/agent/chat/completions";
        $headers = [
            "X-Source: openapi",
            "Content-Type: application/json",
            "Authorization: Bearer $tencent_token"
        ];
        $body = json_encode([
            "assistant_id" => $app_id, // 腾讯使用assistant_id作为app_id
            "user_id" => strval($user_id), // 用户ID转为字符串
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
        ]);

        $write_function = function ($ch, $data) use (&$full_response) {
            $lines = explode("\n", $data);
            foreach ($lines as $line) {
                $line = trim($line);
                if (strpos($line, 'data:') === 0) {
                    $json_str = trim(substr($line, 5));
                    if ($json_str !== '[DONE]') {
                        $json_data = json_decode($json_str, true);
                        if (isset($json_data['choices'][0]['delta']['role']) && $json_data['choices'][0]['delta']['role'] === 'assistant' && !empty($json_data['choices'][0]['delta']['content'])) {
                            $full_response .= $json_data['choices'][0]['delta']['content'];
                            echo "data: " . json_encode(['text' => $json_data['choices'][0]['delta']['content']]) . "\n\n";
                        }
                    }
                }
            }
            flush();
            return strlen($data);
        };
    } else {
        echo "data: " . json_encode(['error' => '未知的智能体提供商']) . "\n\n";
        flush();
        exit;
    }

    // 执行cURL请求
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
    curl_setopt($ch, CURLOPT_WRITEFUNCTION, $write_function);

    $result = curl_exec($ch);
    if ($result === false) {
        echo "data: " . json_encode(['error' => 'API请求失败: ' . curl_error($ch)]) . "\n\n";
        flush();
    } else {
        echo "data: [DONE]\n\n";
        flush();

        // 保存智能体回复
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
        }
    }
    curl_close($ch);
    exit;
}

// 智能体设置页面
function deepseek_render_agents_page() {
// 检查是否保存成功
    $saved = isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true';
    ?>
    <style>
/* 限制整体容器宽度 */
.dashscope-wrap {
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    margin: 0 auto;
}

.dashscope-wrap h1 {
    font-size: 24px;
    color: #333;
}

/* 表格样式 */
.dashscope-wrap table {
    width: 100%;
    border-collapse: collapse;
    background: #fff;
}

.dashscope-wrap th,
.dashscope-wrap td {
    padding: 10px;
    border: 1px solid #ddd;
    text-align: left;
}

/* 统一input与textarea的样式 */
.dashscope-wrap input[type="text"],
.dashscope-wrap input[type="url"],
.dashscope-wrap textarea {
    width: 100%;
    padding: 8px;
    border: 1px solid #ccc;
    border-radius: 4px;
    box-sizing: border-box;
}

/* 限制表格中“提供商”（第1列）和“操作”（第7列）的宽度 */
#deepseek-agents-table th:nth-child(1),
#deepseek-agents-table td:nth-child(1),
#deepseek-agents-table th:nth-child(7),
#deepseek-agents-table td:nth-child(7) {
    width: 100px; /* 根据需要调整宽度 */
    white-space: nowrap;
}

/* 为API Key、Token输入框设置最大宽度 */
.api-input {
    max-width: 300px;
}

/* 按钮样式 */
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
/* 成功消息样式 */
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
            <?php
            settings_fields('deepseek_agents_group');
            do_settings_sections('deepseek-agents');
            submit_button('保存设置');
            ?>
        </form>
        <div class="success-message" <?php echo $saved ? 'style="display: block;"' : ''; ?>>设置已保存</div>
        <p>支持阿里和腾讯的普通智能体应用，不支持使用了插件的智能体应用(支持部分阿里智能体应用的插件)，需分别配置API Key和Token。</p>
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

                fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=deepseek_delete_agent_log&user_id=' + encodeURIComponent(userId) + '&app_id=' + encodeURIComponent(appId)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) { row.remove(); alert('记录已删除'); } 
                    else { alert('删除失败: ' + (data.message || '未知错误')); }
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
    // 注册选项
    register_setting('deepseek_agents_group', 'ali_agent_api_key', 'sanitize_text_field');
    register_setting('deepseek_agents_group', 'tencent_token', 'sanitize_text_field');
    register_setting('deepseek_agents_group', 'deepseek_agents', 'deepseek_sanitize_agents');

    // 添加设置区域
    add_settings_section('deepseek_agents_section', '智能体配置', null, 'deepseek-agents');
    add_settings_field('ali_agent_api_key', '阿里智能体 API_KEY', 'ali_agent_api_key_callback', 'deepseek-agents', 'deepseek_agents_section');
    add_settings_field('tencent_token', '腾讯智能体 Token', 'tencent_token_callback', 'deepseek-agents', 'deepseek_agents_section');
    add_settings_field('deepseek_agents_list', '智能体应用列表', 'deepseek_agents_list_callback', 'deepseek-agents', 'deepseek_agents_section');
}
add_action('admin_init', 'deepseek_register_agents_settings');

// 阿里API Key回调
function ali_agent_api_key_callback() {
    $api_key = get_option('ali_agent_api_key');
    echo '<input type="text" name="ali_agent_api_key" value="' . esc_attr($api_key) . '" style="width: 500px;" />';
    echo '<p class="description">输入阿里智能体应用的API_KEY，用于对接阿里智能体应用。</p>';
}

// 腾讯Token回调
function tencent_token_callback() {
    $token = get_option('tencent_token');
    echo '<input type="text" name="tencent_token" value="' . esc_attr($token) . '" style="width: 500px;" />';
    echo '<p class="description">输入腾讯智能体应用的Token，用于对接腾讯智能体应用。</p>';
}

// 智能体应用列表回调
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
                <th>APP_ID</th>
                <th>开场问题</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($agents as $index => $agent) : ?>
                <tr>
                    <td>
                        <select name="deepseek_agents[<?php echo $index; ?>][provider]">
                            <option value="ali" <?php selected($agent['provider'], 'ali'); ?>>阿里</option>
                            <option value="tencent" <?php selected($agent['provider'], 'tencent'); ?>>腾讯</option>
                        </select>
                    </td>
                    <td><input type="text" name="deepseek_agents[<?php echo $index; ?>][name]" value="<?php echo esc_attr($agent['name']); ?>" /></td>
                    <td><input type="text" name="deepseek_agents[<?php echo $index; ?>][description]" value="<?php echo esc_attr($agent['description']); ?>" /></td>
                    <td><input type="url" name="deepseek_agents[<?php echo $index; ?>][icon]" value="<?php echo esc_attr($agent['icon']); ?>" /></td>
                    <td><input type="text" name="deepseek_agents[<?php echo $index; ?>][app_id]" value="<?php echo esc_attr($agent['app_id']); ?>" /></td>
                    <td>
                        <textarea name="deepseek_agents[<?php echo $index; ?>][opening_questions]" rows="3" cols="30"><?php 
                            echo esc_textarea(implode("\n", $agent['opening_questions'] ?? [])); 
                        ?></textarea>
                        <p class="description">每行一个开场问题，用换行分隔。</p>
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
                    <select name="deepseek_agents[${rowCount}][provider]">
                        <option value="ali">阿里</option>
                        <option value="tencent">腾讯</option>
                    </select>
                </td>
                <td><input type="text" name="deepseek_agents[${rowCount}][name]" value="" /></td>
                <td><input type="text" name="deepseek_agents[${rowCount}][description]" value="" /></td>
                <td><input type="url" name="deepseek_agents[${rowCount}][icon]" value="" /></td>
                <td><input type="text" name="deepseek_agents[${rowCount}][app_id]" value="" /></td>
                <td>
                    <textarea name="deepseek_agents[${rowCount}][opening_questions]" rows="3" cols="30"></textarea>
                    <p class="description">每行一个开场问题，用换行分隔。</p>
                </td>
                <td><button type="button" class="button delete-agent">删除</button></td>
            `;
        });
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('delete-agent')) {
                e.target.closest('tr').remove();
            }
        });
    </script>
    <?php
}

// 清理智能体数据
function deepseek_sanitize_agents($input) {
    $agents = [];
    if (is_array($input)) {
        foreach ($input as $agent) {
            if (!empty($agent['name']) && !empty($agent['app_id'])) {
                // 处理开场问题从textarea输入的字符串按换行符分割成数组
                $opening_questions = [];
                if (!empty($agent['opening_questions']) && is_string($agent['opening_questions'])) {
                    $opening_questions = array_filter(array_map('trim', explode("\n", $agent['opening_questions'])));
                }

                $agents[] = [
                    'provider' => sanitize_text_field($agent['provider'] ?? 'ali'), // 默认阿里
                    'name' => sanitize_text_field($agent['name']),
                    'description' => sanitize_text_field($agent['description']),
                    'icon' => esc_url_raw($agent['icon']),
                    'app_id' => sanitize_text_field($agent['app_id']),
                    'opening_questions' => array_map('sanitize_text_field', $opening_questions),
                ];
            }
        }
    }
    return $agents;
}

// 删除智能体对话记录
function deepseek_delete_agent_log() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'deepseek_agent_chat_logs';
    $user_id = intval($_POST['user_id']);
    $app_id = sanitize_text_field($_POST['app_id']);

    if (!current_user_can('manage_options')) {
        wp_send_json(['success' => false, 'message' => '无权删除记录']);
        return;
    }

    $wpdb->delete($table_name, ['user_id' => $user_id, 'app_id' => $app_id]);
    if ($wpdb->last_error) {
        wp_send_json(['success' => false, 'message' => '删除失败: ' . $wpdb->last_error]);
    } else {
        wp_send_json(['success' => true]);
    }
}
add_action('wp_ajax_deepseek_delete_agent_log', 'deepseek_delete_agent_log');

// 清除智能体对话记录
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

// 注册插件卸载钩子
function deepseek_cleanup_options() {
    if (!defined('WP_UNINSTALL_PLUGIN')) {
        exit;
    }
    delete_option('ali_agent_api_key');
    delete_option('tencent_token');
    delete_option('deepseek_agents');
}
register_uninstall_hook(__FILE__, 'deepseek_cleanup_options');
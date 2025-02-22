<?php

if ( ! defined( 'ABSPATH' ) ) {
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
        'permission_callback' => function() { return is_user_logged_in(); },
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

    $api_key = get_option('ali_agent_api_key');
    if (empty($api_key)) {
        header('Content-Type: text/event-stream');
        echo "data: " . json_encode(['error' => 'API Key未配置，请在后台设置']) . "\n\n";
        flush();
        exit;
    }

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

    // 清空缓冲区，设置流式响应头
    while (ob_get_level()) ob_end_clean();
    ini_set('output_buffering', 'off');
    ini_set('zlib.output_compression', false);
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');
    header('X-Accel-Buffering: no');

    $full_response = '';
    $current_session_id = $session_id;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);

    curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) use (&$full_response, &$current_session_id) {
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
        ob_flush();
        flush();
        return strlen($data);
    });

    $result = curl_exec($ch);
    if ($result === false) {
        echo "data: " . json_encode(['error' => 'API 请求失败: ' . curl_error($ch)]) . "\n\n";
        flush();
    } else {
        echo "data: [DONE]\n\n";
        flush();

        global $wpdb;
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
            } else {
                error_log("保存智能体回复成功: " . $full_response);
            }
        }
    }
    curl_close($ch);
    exit;
}

// 智能体设置页面
function deepseek_render_agents_page() {
    ?>
    <style>
        .dashscope-wrap {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .dashscope-wrap h1 {
            font-size: 24px;
            color: #333;
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
        .dashscope-wrap input[type="text"],
        .dashscope-wrap input[type="url"] {
            width: 100%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
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
    <p>智能体应用只支持阿里的，就基础功能，如果不需要就不用管</p>        
    </div>
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
                            <td>
                                <button class="button delete-agent-log">删除</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="5">暂无记录。</td>
                    </tr>
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
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=deepseek_delete_agent_log&user_id=' + encodeURIComponent(userId) + '&app_id=' + encodeURIComponent(appId)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        row.remove();
                        alert('记录已删除');
                    } else {
                        alert('删除失败: ' + (data.message || '未知错误'));
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

function deepseek_register_agents_settings() {
    register_setting('deepseek_agents_group', 'ali_agent_api_key', 'sanitize_text_field');
    register_setting('deepseek_agents_group', 'deepseek_agents', 'deepseek_sanitize_agents');

    add_settings_section('deepseek_agents_section', '智能体配置', null, 'deepseek-agents');
    add_settings_field('ali_agent_api_key', '阿里智能体 API_KEY', 'ali_agent_api_key_callback', 'deepseek-agents', 'deepseek_agents_section');
    add_settings_field('deepseek_agents_list', '智能体应用列表', 'deepseek_agents_list_callback', 'deepseek-agents', 'deepseek_agents_section');
}
add_action('admin_init', 'deepseek_register_agents_settings');

function ali_agent_api_key_callback() {
    $api_key = get_option('ali_agent_api_key');
    echo '<input type="text" name="ali_agent_api_key" value="' . esc_attr($api_key) . '" style="width: 500px;" />';
    echo '<p class="description">输入阿里智能体的通用 API_KEY，用于对接所有智能体应用。</p>';
}

function deepseek_agents_list_callback() {
    $agents = get_option('deepseek_agents', []);
    ?>
    <table class="widefat" id="deepseek-agents-table">
        <thead>
            <tr>
                <th>名称</th><th>描述</th><th>图标URL</th><th>APP_ID</th><th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($agents as $index => $agent) : ?>
                <tr>
                    <td><input type="text" name="deepseek_agents[<?php echo $index; ?>][name]" value="<?php echo esc_attr($agent['name']); ?>" /></td>
                    <td><input type="text" name="deepseek_agents[<?php echo $index; ?>][description]" value="<?php echo esc_attr($agent['description']); ?>" /></td>
                    <td><input type="url" name="deepseek_agents[<?php echo $index; ?>][icon]" value="<?php echo esc_attr($agent['icon']); ?>" /></td>
                    <td><input type="text" name="deepseek_agents[<?php echo $index; ?>][app_id]" value="<?php echo esc_attr($agent['app_id']); ?>" /></td>
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
                <td><input type="text" name="deepseek_agents[${rowCount}][name]" value="" /></td>
                <td><input type="text" name="deepseek_agents[${rowCount}][description]" value="" /></td>
                <td><input type="url" name="deepseek_agents[${rowCount}][icon]" value="" /></td>
                <td><input type="text" name="deepseek_agents[${rowCount}][app_id]" value="" /></td>
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

function deepseek_sanitize_agents($input) {
    $agents = [];
    if (is_array($input)) {
        foreach ($input as $agent) {
            if (!empty($agent['name']) && !empty($agent['app_id'])) {
                $agents[] = [
                    'name' => sanitize_text_field($agent['name']),
                    'description' => sanitize_text_field($agent['description']),
                    'icon' => esc_url_raw($agent['icon']),
                    'app_id' => sanitize_text_field($agent['app_id']),
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

// 注册插件卸载钩子
function deepseek_cleanup_options() {
    if (!defined('WP_UNINSTALL_PLUGIN')) {
        exit;
    }
    
    // 仅删除插件相关的选项
    delete_option('ali_agent_api_key');
    delete_option('deepseek_agents');
}
register_uninstall_hook(__FILE__, 'deepseek_cleanup_options');

?>
<?php
/*
Plugin Name: 小半DeepSeek助手
Description: 对接DeepSeek模型的Ai助手插件，仅限文本对话。
Plugin URI: https://www.jingxialai.com/4827.html
Version: 1.0
Author: Summer
License: GPL License
Author URI: https://www.jingxialai.com/
*/

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

// 创建数据表
function deepseek_create_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'deepseek_chat_logs';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        user_id mediumint(9) NOT NULL,
        conversation_id mediumint(9) NOT NULL,
        conversation_title text NOT NULL,
        message text NOT NULL,
        response text NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'deepseek_create_table');

// 创建对话页面
function deepseek_create_chat_page() {
    // 检查页面是否已经存在
    $page = get_page_by_path('deepseek-chat');
    if (!$page) {
        // 创建页面
        $page_id = wp_insert_post(array(
            'post_title'    => 'Ai小助手',
            'post_content'  => '[deepseek_chat]',
            'post_status'   => 'publish',
            'post_author'   => 1,
            'post_type'     => 'page',
            'post_name'     => 'deepseek-chat'
        ));
    }
}
register_activation_hook(__FILE__, 'deepseek_create_chat_page');

// 添加短代码
function deepseek_chat_shortcode() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'deepseek_chat_logs';
    $user_id = get_current_user_id();

    ob_start();
    if (!is_user_logged_in()) {
        echo '<div id="deepseek-chat-container">';
        echo '<div class="deepseek-login-prompt">请先登录才能使用Ai对话功能。</div>';
        echo '</div>';
    } else {
        // 加载历史记录
        $history = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_id = %d GROUP BY conversation_id ORDER BY created_at DESC",
            $user_id
        ));

        ?>
<div id="deepseek-chat-container">
    <!-- 历史对话框 -->
    <div id="deepseek-chat-history">
        <button id="deepseek-new-chat">开启新对话</button>
        <ul>
            <?php if (!empty($history)) : ?>
                <?php foreach ($history as $log) : ?>
                    <li data-conversation-id="<?php echo $log->conversation_id; ?>">
                        <span class="deepseek-chat-title"><?php echo esc_html(wp_trim_words($log->conversation_title, 10, '...')); ?></span>
                        <button class="deepseek-delete-log" data-conversation-id="<?php echo $log->conversation_id; ?>">删除</button>
                    </li>
                <?php endforeach; ?>
            <?php endif; ?>
        </ul>
    </div>

    <!-- 主对话框 -->
    <div id="deepseek-chat-main">
        <!-- 消息框 -->
        <div id="deepseek-chat-messages">
            <!-- 初始为空，点击历史记录后动态加载 -->
        </div>

        <!-- 输入框和发送按钮 -->
        <div id="deepseek-chat-input-container">
            <textarea id="deepseek-chat-input" placeholder="输入你的消息..." rows="4"></textarea>
            <button id="deepseek-chat-send">发送</button>
        </div>
    </div>
</div>
<script>
    var currentConversationId = null; // 当前对话的conversation_id

// 发送消息
document.getElementById('deepseek-chat-send').addEventListener('click', function() {
    var message = document.getElementById('deepseek-chat-input').value;
    if (message) {
        // 显示“小助手正在思考中...”的提示
        var thinkingMessage = document.createElement('div');
        thinkingMessage.id = 'deepseek-thinking-message';
        thinkingMessage.className = 'message-bubble bot';
        thinkingMessage.innerHTML = '小助手正在思考中...';
        document.getElementById('deepseek-chat-messages').appendChild(thinkingMessage);

        var data = new URLSearchParams();
        data.append('action', 'deepseek_send_message');
        data.append('message', message);
        if (currentConversationId) {
            data.append('conversation_id', currentConversationId);
        }

        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: data
        }).then(response => response.json())
        .then(data => {
            if (data.success) {
                var messagesContainer = document.getElementById('deepseek-chat-messages');
                // 移除“小助手正在思考中...”的提示
                var thinkingMessage = document.getElementById('deepseek-thinking-message');
                if (thinkingMessage) {
                    thinkingMessage.remove();
                }

                // 添加用户消息和DeepSeek回复
                messagesContainer.innerHTML += '<div class="message-bubble user">' + message + '</div>';
                messagesContainer.innerHTML += '<div class="message-bubble bot">' + data.message + '</div>';
                document.getElementById('deepseek-chat-input').value = '';
                messagesContainer.scrollTop = messagesContainer.scrollHeight;

                // 动态更新历史对话框
                if (!currentConversationId) {
                    var historyContainer = document.querySelector('#deepseek-chat-history ul');
                    var newChatItem = document.createElement('li');
                    newChatItem.setAttribute('data-conversation-id', data.conversation_id);
                    newChatItem.innerHTML = '<span class="deepseek-chat-title">' + message.substring(0, 20) + '...</span>' +
                        '<button class="deepseek-delete-log" data-conversation-id="' + data.conversation_id + '">删除</button>';
                    historyContainer.insertBefore(newChatItem, historyContainer.firstChild);

                    // 绑定新历史记录的点击事件
                    newChatItem.addEventListener('click', function() {
                        loadChatLog(data.conversation_id);
                    });

                    // 绑定新历史记录的删除按钮事件
                    newChatItem.querySelector('.deepseek-delete-log').addEventListener('click', function() {
                        var conversationId = this.getAttribute('data-conversation-id');
                        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: 'action=deepseek_delete_log&conversation_id=' + conversationId
                        }).then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                this.parentElement.remove();
                                // 清空消息框内容
                                document.getElementById('deepseek-chat-messages').innerHTML = '';
                                // 重置当前对话的conversation_id
                                currentConversationId = null;
                            }
                        });
                    });

                    currentConversationId = data.conversation_id;
                }
            }
        });
    }
});

    // 开启新对话
    document.getElementById('deepseek-new-chat').addEventListener('click', function() {
        document.getElementById('deepseek-chat-messages').innerHTML = '';
        document.getElementById('deepseek-chat-input').value = '';
        currentConversationId = null; // 重置当前对话的conversation_id
    });

    // 加载历史对话记录
    function loadChatLog(conversationId) {
        fetch('<?php echo admin_url('admin-ajax.php'); ?>?action=deepseek_load_log&conversation_id=' + conversationId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                var messagesContainer = document.getElementById('deepseek-chat-messages');
                messagesContainer.innerHTML = ''; // 清空当前内容
                data.messages.forEach(message => {
                    messagesContainer.innerHTML += '<div class="message-bubble user">' + message.message + '</div>';
                    messagesContainer.innerHTML += '<div class="message-bubble bot">' + message.response + '</div>';
                });
                currentConversationId = conversationId; // 设置当前对话的conversation_id
            }
        });
    }

    // 绑定历史对话框的点击事件
    document.querySelectorAll('#deepseek-chat-history li').forEach(item => {
        item.addEventListener('click', function() {
            var conversationId = this.getAttribute('data-conversation-id');
            loadChatLog(conversationId);
        });

        // 绑定删除按钮事件
        var deleteButton = item.querySelector('.deepseek-delete-log');
        if (deleteButton) {
            deleteButton.addEventListener('click', function() {
                var conversationId = this.getAttribute('data-conversation-id');
                fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=deepseek_delete_log&conversation_id=' + conversationId
                }).then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // 从历史对话框中移除
                        this.parentElement.remove();
                        // 清空消息框内容
                        document.getElementById('deepseek-chat-messages').innerHTML = '';
                        // 重置当前对话的conversation_id
                        currentConversationId = null;
                    }
                });
            });
        }
    });
</script>
        <?php
    }
    return ob_get_clean();
}
add_shortcode('deepseek_chat', 'deepseek_chat_shortcode');

// 处理AJAX请求
function deepseek_send_message() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'deepseek_chat_logs';

    $message = sanitize_text_field($_POST['message']);
    $conversation_id = isset($_POST['conversation_id']) ? intval($_POST['conversation_id']) : null;
    $api_key = get_option('deepseek_api_key');
    $user_id = get_current_user_id();
    $model = get_option('deepseek_model', 'deepseek-chat'); // 获取用户选择的模型

    if (empty($api_key)) {
        wp_send_json(array('success' => false, 'message' => 'API Key 未设置。'));
    }

    // 如果是新对话，设置第一条消息为标题
    if (!$conversation_id) {
        $conversation_title = $message;
    } else {
        $conversation_title = $wpdb->get_var($wpdb->prepare(
            "SELECT conversation_title FROM $table_name WHERE conversation_id = %d LIMIT 1",
            $conversation_id
        ));
    }

    // 调用DeepSeek API
    $data = array(
        'model' => $model, // 使用用户选择的模型
        'messages' => array(
            array('role' => 'system', 'content' => 'You are a helpful assistant.'),
            array('role' => 'user', 'content' => $message)
        ),
        'stream' => false
    );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.deepseek.com/chat/completions');
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Authorization: Bearer ' . $api_key
    ));
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code == 200) {
        $response_data = json_decode($response, true);
        $reply = $response_data['choices'][0]['message']['content'];

        // 将换行符 \n 转换为 <br> 标签
        $reply = nl2br($reply);

        // 保存对话记录
        $wpdb->insert($table_name, array(
            'user_id' => $user_id,
            'conversation_id' => $conversation_id ? $conversation_id : 0,
            'conversation_title' => $conversation_title,
            'message' => $message,
            'response' => $reply
        ));

        $new_log_id = $wpdb->insert_id;

        // 如果是新对话，返回新的conversation_id
        if (!$conversation_id) {
            $conversation_id = $new_log_id;
            $wpdb->update($table_name, array('conversation_id' => $conversation_id), array('id' => $new_log_id));
        }

        wp_send_json(array('success' => true, 'message' => $reply, 'conversation_id' => $conversation_id));
    } else {
        wp_send_json(array('success' => false, 'message' => '请求失败，请检查
            API Key 或网络连接。'));
    }
}
add_action('wp_ajax_deepseek_send_message', 'deepseek_send_message');
add_action('wp_ajax_nopriv_deepseek_send_message', 'deepseek_send_message');

// 加载历史对话记录
function deepseek_load_log() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'deepseek_chat_logs';
    $conversation_id = intval($_GET['conversation_id']);
    $user_id = get_current_user_id();

    // 检查权限
    $logs = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name WHERE conversation_id = %d AND user_id = %d ORDER BY created_at ASC",
        $conversation_id, $user_id
    ));

    if ($logs) {
        $messages = array();
        foreach ($logs as $log) {
            $messages[] = array(
                'message' => $log->message,
                'response' => $log->response
            );
        }
        wp_send_json(array('success' => true, 'messages' => $messages));
    } else {
        wp_send_json(array('success' => false, 'message' => '未找到对话记录。'));
    }
}
add_action('wp_ajax_deepseek_load_log', 'deepseek_load_log');

// 删除对话记录
function deepseek_delete_log() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'deepseek_chat_logs';
    $conversation_id = intval($_POST['conversation_id']);
    $user_id = get_current_user_id();

    // 检查权限
    $log = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE conversation_id = %d AND user_id = %d LIMIT 1",
        $conversation_id, $user_id
    ));

    if ($log || current_user_can('manage_options')) {
        $wpdb->delete($table_name, array('conversation_id' => $conversation_id));
        wp_send_json(array('success' => true));
    } else {
        wp_send_json(array('success' => false, 'message' => '无权删除此记录。'));
    }
}
add_action('wp_ajax_deepseek_delete_log', 'deepseek_delete_log');

// 添加DeepSeek菜单入口
function deepseek_add_menu() {
    // 主菜单项
    add_menu_page(
        'DeepSeek助手', // 页面标题
        'DeepSeek助手', // 菜单标题
        'manage_options',
        'deepseek', // 菜单slug
        'deepseek_render_settings_page', // 默认加载设置页面
        'dashicons-format-chat', // 图标
        6 // 菜单位置
    );

    // 子菜单项 - 设置
    add_submenu_page(
        'deepseek', // 父菜单slug
        'DeepSeek 设置', // 页面标题
        '设置', // 菜单标题
        'manage_options',
        'deepseek', // 菜单slug和主菜单一致
        'deepseek_render_settings_page' // 指向设置页面的回调函数
    );

    // 子菜单项 - 对话记录
    add_submenu_page(
        'deepseek',
        'DeepSeek 对话记录',
        '对话记录',
        'manage_options',
        'deepseek-logs',
        'deepseek_render_logs_page' // 对话记录页面的回调函数
    );
}
add_action('admin_menu', 'deepseek_add_menu');

// 设置页面
function deepseek_render_settings_page() {
    ?>
    <div class="wrap">
        <h1>DeepSeek 设置</h1>
        <?php if (isset($_GET['settings-updated']) && $_GET['settings-updated']): ?>
            <div id="deepseek-save-success" style="background: #d4edda; color: #155724; padding: 10px; margin-bottom: 20px; border: 1px solid #c3e6cb; border-radius: 5px;">
                保存成功！
            </div>
            <script>
                setTimeout(() => {
                    document.getElementById('deepseek-save-success').style.display = 'none';
                }, 1000);
            </script>
        <?php endif; ?>
        <form method="post" action="options.php">
            <?php
            settings_fields('deepseek_chat_options_group');
            do_settings_sections('deepseek-chat');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

// 对话记录管理页面
function deepseek_render_logs_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'deepseek_chat_logs';

    // 删除记录
    if (isset($_GET['delete_log']) && current_user_can('manage_options')) {
        $log_id = intval($_GET['delete_log']);
        $wpdb->delete($table_name, array('id' => $log_id));
        echo '<div class="notice notice-success"><p>记录已删除。</p></div>';
    }

    // 分页处理
    $per_page = 20; // 每页显示的记录数
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $per_page;

    // 获取总记录数
    $total_logs = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");

    // 获取当前页的记录
    $logs = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name GROUP BY conversation_id ORDER BY created_at DESC LIMIT %d OFFSET %d",
        $per_page, $offset
    ));

    ?>
    <div class="wrap">
        <h1>DeepSeek 对话记录</h1>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>用户ID</th>
                    <th>标题</th>
                    <th>时间</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($logs)) : ?>
                    <?php foreach ($logs as $log) : ?>
                        <tr>
                            <td><?php echo $log->user_id; ?></td>
                            <td><?php echo esc_html(wp_trim_words($log->conversation_title, 5, '...')); ?></td>
                            <td><?php echo $log->created_at; ?></td>
                            <td>
                                <a href="?page=deepseek-logs&delete_log=<?php echo $log->id; ?>" class="button">删除</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="4">暂无记录。</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- 分页导航 -->
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <?php
                $total_pages = ceil($total_logs / $per_page);
                echo paginate_links(array(
                    'base' => add_query_arg('paged', '%#%'),
                    'format' => '',
                    'prev_text' => __('&laquo; 上一页'),
                    'next_text' => __('下一页 &raquo;'),
                    'total' => $total_pages,
                    'current' => $current_page,
                ));
                ?>
            </div>
        </div>
    </div>
    <?php
}

// 注册设置
function deepseek_register_settings() {
    register_setting('deepseek_chat_options_group', 'deepseek_api_key'); // api
    register_setting('deepseek_chat_options_group', 'deepseek_model'); // 模型选择

    add_settings_section('deepseek_main_section', '基础设置', null, 'deepseek-chat');

    add_settings_field('deepseek_api_key', 'API Key', 'deepseek_api_key_callback', 'deepseek-chat', 'deepseek_main_section');
    add_settings_field('deepseek_model', '模型', 'deepseek_model_callback', 'deepseek-chat', 'deepseek_main_section');
}
add_action('admin_init', 'deepseek_register_settings');

function deepseek_api_key_callback() {
    $api_key = get_option('deepseek_api_key');
    echo '<input type="text" name="deepseek_api_key" value="' . esc_attr($api_key) . '" style="width: 500px;" />';
}

function deepseek_model_callback() {
    $model = get_option('deepseek_model', 'deepseek-chat'); // 默认模型为deepseek-chat
    ?>
    <select name="deepseek_model">
        <option value="deepseek-chat" <?php selected($model, 'deepseek-chat'); ?>>DeepSeek-V3</option>
        <option value="deepseek-reasoner" <?php selected($model, 'deepseek-reasoner'); ?>>DeepSeek-R1</option>
    </select>
    <?php
}

// 加载CSS文件
function deepseek_enqueue_styles() {
    if (is_singular('page')) {
        global $post;
        if (has_shortcode($post->post_content, 'deepseek_chat')) { // 检查是否包含短代码
            wp_enqueue_style('deepseek-chat-style', plugin_dir_url(__FILE__) . 'style.css');
        }
    }
}
add_action('wp_enqueue_scripts', 'deepseek_enqueue_styles');
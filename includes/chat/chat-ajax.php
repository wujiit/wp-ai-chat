<?php
function deepseek_chat_get_rest_nonce_from_request() {
    $nonce = '';
    if (isset($_REQUEST['nonce'])) {
        $nonce = sanitize_text_field(wp_unslash($_REQUEST['nonce']));
    } elseif (isset($_SERVER['HTTP_X_WP_NONCE'])) {
        $nonce = sanitize_text_field(wp_unslash($_SERVER['HTTP_X_WP_NONCE']));
    }
    return $nonce;
}

function deepseek_handle_file_upload() {
    check_ajax_referer('file_upload_action', 'nonce');

    // 检查上传文件权限或游客频率限制
    if (!is_user_logged_in()) {
        if (!deepseek_check_guest_limit('upload')) {
            wp_send_json_error(['message' => '游客今日文件上传次数已达上限，请登录。']);
            return;
        }
    }

    if (!isset($_FILES['file'])) {
        wp_send_json_error(['message' => '没有文件被上传']);
        return;
    }

    $allowed_types = array_map('trim', explode(',', get_option('allowed_file_types', 'txt,docx,pdf,xlsx,md')));
    $max_size = (int)get_option('max_file_size', 100) * 1024 * 1024;
    $file = $_FILES['file'];
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $interface = isset($_POST['interface']) ? sanitize_text_field(wp_unslash($_POST['interface'])) : 'qwen';
    $model = isset($_POST['model']) ? sanitize_text_field(wp_unslash($_POST['model'])) : '';
    $qwen_video_models = explode(',', get_option('qwen_video_model', 'wanx2.1-t2v-turbo'));

    // 如果是通义千问视频模型，限制文件类型为图片
    if ($interface === 'qwen' && in_array($model, $qwen_video_models)) {
        $allowed_image_types = ['jpeg', 'jpg', 'png', 'bmp', 'webp'];
        if (!in_array($file_extension, $allowed_image_types)) {
            wp_send_json_error([
                'message' => '仅支持JPEG、JPG、PNG、BMP、WEBP格式的图片',
                'type' => 'invalid_type',
                'allowed_types' => implode(', ', $allowed_image_types)
            ]);
            return;
        }

        // 验证文件大小（通义千问要求不超过10MB）
        if ($file['size'] > 10 * 1024 * 1024) {
            wp_send_json_error([
                'message' => '图片大小超过限制: 10MB',
                'type' => 'size_exceeded',
                'max_size' => 10
            ]);
            return;
        }

        // 上传到媒体库
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        $attachment_id = media_handle_upload('file', 0);
        if (is_wp_error($attachment_id)) {
            wp_send_json_error(['message' => '图片上传失败: ' . $attachment_id->get_error_message()]);
            return;
        }

        $image_url = wp_get_attachment_url($attachment_id);
        wp_send_json_success([
            'file_id' => $attachment_id,
            'filename' => $file['name'],
            'image_url' => $image_url,
            'interface' => 'qwen'
        ]);
        return;
    }

    // 验证文件类型（非视频模型）
    if (!in_array($file_extension, $allowed_types)) {
        wp_send_json_error([
            'message' => '此文件是不支持的文件类型',
            'type' => 'invalid_type',
            'allowed_types' => implode(', ', $allowed_types)
        ]);
        return;
    }

    // 验证文件大小（非视频模型）
    if ($file['size'] > $max_size) {
        wp_send_json_error([
            'message' => '文件大小超过限制: ' . round($max_size / (1024 * 1024), 2) . 'MB',
            'type' => 'size_exceeded',
            'max_size' => round($max_size / (1024 * 1024), 2)
        ]);
        return;
    }

    // 判断模型是否支持文档分析
    $support_doc_models = [
        'kimi' => explode(',', get_option('kimi_model', '')),
        'openai' => explode(',', get_option('openai_model', '')),
        'qwen' => ['qwen-long']
    ];
    $is_doc_supported = false;
    if (isset($support_doc_models[$interface])) {
        $is_doc_supported = in_array($model, $support_doc_models[$interface]);
    }
    if (!$is_doc_supported) {
        wp_send_json_error(['message' => '该模型不支持分析文档，分析文档请使用Kimi或者通义千问的qwen-long模型']);
        return;
    }

    // 接口选择及文件上传
    switch ($interface) {
        case 'qwen':
            $api_key = get_option('qwen_api_key');
            $upload_url = 'https://dashscope.aliyuncs.com/compatible-mode/v1/files';
            break;
        case 'openai':
            $api_key = get_option('openai_api_key');
            $upload_url = 'https://api.openai.com/v1/files';
            break;
        case 'kimi':
            $api_key = get_option('kimi_api_key');
            $upload_url = 'https://api.moonshot.cn/v1/files';
            break;
        default:
            wp_send_json_error(['message' => '无效的接口选择']);
            return;
    }

    if (empty($api_key)) {
        wp_send_json_error(['message' => 'API Key 未设置']);
        return;
    }

    $cfile = new CURLFile($file['tmp_name'], $file['type'], $file['name']);
    $data = [
        'file' => $cfile,
        'purpose' => 'file-extract'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $upload_url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $api_key",
        "Content-Type: multipart/form-data"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 600);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($response === false || $http_code != 200) {
        wp_send_json_error([
            'message' => '文件上传失败: ' . ($curl_error ?: '未知错误'),
            'http_code' => $http_code,
            'response' => substr($response, 0, 200)
        ]);
        return;
    }

    $response_data = json_decode($response, true);
    if (!isset($response_data['id'])) {
        wp_send_json_error([
            'message' => 'API返回数据格式错误',
            'response' => $response_data
        ]);
        return;
    }

    wp_send_json_success([
        'file_id' => $response_data['id'],
        'filename' => $file['name'],
        'interface' => $interface
    ]);
}

add_action('wp_ajax_deepseek_upload_file', 'deepseek_handle_file_upload');

// 处理接口切换的AJAX请求
function deepseek_check_image_task() {
    $nonce = deepseek_chat_get_rest_nonce_from_request();
    if (!wp_verify_nonce($nonce, 'wp_rest')) {
        wp_send_json(['success' => false, 'message' => '验证请求失败']);
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'deepseek_chat_logs';
    
    $task_id = isset($_POST['task_id']) ? sanitize_text_field(wp_unslash($_POST['task_id'])) : '';
    if (empty($task_id)) {
        wp_send_json(['success' => false, 'message' => '缺少任务ID']);
        return;
    }

    // API频率限制
    if (!is_user_logged_in() && function_exists('deepseek_check_guest_limit') && !deepseek_check_guest_limit('chat')) {
        wp_send_json(['success' => false, 'message' => '游客请求频率超限']);
        return;
    }

    // 格式合法性校验 (阿里云任务ID为 UUID 格式)
    if (!preg_match('/^[a-fA-F0-9\-]+$/', $task_id)) {
        wp_send_json(['success' => false, 'message' => '非法的任务ID格式']);
        return;
    }

    // 查询本地数据库验证该任务是否是真的在这个表里生成过的
    $record = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE response LIKE %s ORDER BY id DESC LIMIT 1",
        '%' . $wpdb->esc_like($task_id) . '%'
    ));
    if (!$record) {
        wp_send_json(['success' => false, 'message' => '未找到对应的任务记录']);
        return;
    }
    
    // 权限校验
    $user_id = get_current_user_id();
    if ($user_id == 0) {
        $device_id = isset($_SERVER['HTTP_X_DEVICE_ID']) ? sanitize_text_field($_SERVER['HTTP_X_DEVICE_ID']) : '';
        $owner_device_id = get_transient('deepseek_guest_conv_owner_' . $record->conversation_id);
        if (empty($device_id) || $device_id !== $owner_device_id) {
            wp_send_json(['success' => false, 'message' => '无权查看此任务']);
            return;
        }
    } else if ($record->user_id != $user_id) {
        wp_send_json(['success' => false, 'message' => '无权查看此任务']);
        return;
    }

    $api_key = get_option('qwen_api_key');
    
    $url = 'https://dashscope.aliyuncs.com/api/v1/tasks/' . $task_id;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $api_key,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code == 200) {
        $response_data = json_decode($response, true);
        if ($response_data['output']['task_status'] === 'SUCCEEDED') {
            $actual_prompt = $response_data['output']['results'][0]['actual_prompt'] ?? '';
            $image_url = $response_data['output']['results'][0]['url'] ?? '';

            if ($record) {
                $wpdb->update($table_name, 
                    ['response' => json_encode([
                        'status' => 'succeeded',
                        'actual_prompt' => $actual_prompt,
                        'image_url' => $image_url
                    ])], 
                    ['id' => $record->id]
                );
            }

            wp_send_json([
                'success' => true,
                'task_status' => 'SUCCEEDED',
                'actual_prompt' => $actual_prompt,
                'image_url' => $image_url
            ]);
        } else {
            wp_send_json([
                'success' => true,
                'task_status' => $response_data['output']['task_status']
            ]);
        }
    } else {
        wp_send_json(['success' => false, 'message' => '任务状态查询失败']);
    }
}
add_action('wp_ajax_deepseek_check_image_task', 'deepseek_check_image_task');

function deepseek_check_video_task() {
    $nonce = deepseek_chat_get_rest_nonce_from_request();
    if (!wp_verify_nonce($nonce, 'wp_rest')) {
        wp_send_json(['success' => false, 'message' => '验证请求失败']);
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'deepseek_chat_logs';
    
    $task_id = isset($_POST['task_id']) ? sanitize_text_field(wp_unslash($_POST['task_id'])) : '';
    if (empty($task_id)) {
        wp_send_json(['success' => false, 'message' => '缺少任务ID']);
        return;
    }

    // API频率限制
    if (!is_user_logged_in() && function_exists('deepseek_check_guest_limit') && !deepseek_check_guest_limit('chat')) {
        wp_send_json(['success' => false, 'message' => '游客请求频率超限']);
        return;
    }

    // 格式合法性校验 (阿里云任务ID为 UUID 格式)
    if (!preg_match('/^[a-fA-F0-9\-]+$/', $task_id)) {
        wp_send_json(['success' => false, 'message' => '非法的任务ID格式']);
        return;
    }

    // 查询本地数据库验证该任务是否是真的在这个表里生成过的
    $record = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE response LIKE %s ORDER BY id DESC LIMIT 1",
        '%' . $wpdb->esc_like($task_id) . '%'
    ));
    if (!$record) {
        wp_send_json(['success' => false, 'message' => '未找到对应的任务记录']);
        return;
    }
    
    // 权限校验
    $user_id = get_current_user_id();
    if ($user_id == 0) {
        $device_id = isset($_SERVER['HTTP_X_DEVICE_ID']) ? sanitize_text_field($_SERVER['HTTP_X_DEVICE_ID']) : '';
        $owner_device_id = get_transient('deepseek_guest_conv_owner_' . $record->conversation_id);
        if (empty($device_id) || $device_id !== $owner_device_id) {
            wp_send_json(['success' => false, 'message' => '无权查看此任务']);
            return;
        }
    } else if ($record->user_id != $user_id) {
        wp_send_json(['success' => false, 'message' => '无权查看此任务']);
        return;
    }

    $api_key = get_option('qwen_api_key');
    
    $url = 'https://dashscope.aliyuncs.com/api/v1/tasks/' . $task_id;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $api_key,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code == 200) {
        $response_data = json_decode($response, true);
        $task_status = $response_data['output']['task_status'];
        
        if ($task_status === 'SUCCEEDED') {
            $video_url = $response_data['output']['video_url'] ?? '';

            if ($record) {
                $wpdb->update($table_name, 
                    ['response' => json_encode([
                        'status' => 'succeeded',
                        'video_url' => $video_url
                    ])], 
                    ['id' => $record->id]
                );
            }

            wp_send_json([
                'success' => true,
                'task_status' => 'SUCCEEDED',
                'video_url' => $video_url
            ]);
        } else {
            wp_send_json([
                'success' => true,
                'task_status' => $task_status
            ]);
        }
    } else {
        wp_send_json(['success' => false, 'message' => '视频任务状态查询失败']);
    }
}
add_action('wp_ajax_deepseek_check_video_task', 'deepseek_check_video_task');

// 加载历史对话记录
function deepseek_load_log() {
    $nonce = deepseek_chat_get_rest_nonce_from_request();
    if (!wp_verify_nonce($nonce, 'wp_rest')) {
        wp_send_json(['success' => false, 'message' => '验证请求失败']);
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'deepseek_chat_logs';
    $conversation_id = isset($_GET['conversation_id']) ? intval($_GET['conversation_id']) : 0;
    if ($conversation_id <= 0) {
        wp_send_json(['success' => false, 'message' => '缺少有效的对话ID']);
        return;
    }
    $user_id = get_current_user_id();

    // 游客验证
    if ($user_id == 0) {
        $device_id = isset($_SERVER['HTTP_X_DEVICE_ID']) ? sanitize_text_field($_SERVER['HTTP_X_DEVICE_ID']) : '';
        $owner_device_id = get_transient('deepseek_guest_conv_owner_' . $conversation_id);
        if (empty($device_id) || $device_id !== $owner_device_id) {
            wp_send_json(array('success' => false, 'message' => '无权加载此对话。'));
            return;
        }
    }

    $logs = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name 
        WHERE conversation_id = %d 
        AND user_id = %d 
        ORDER BY id ASC",
        $conversation_id,
        $user_id
    ));

    if (empty($logs)) {
        wp_send_json(array('success' => false, 'message' => '未找到对话记录。'));
        return;
    }

    $processed = array();
    foreach ($logs as $log) {
        $response = json_decode($log->response, true);
        
        // 处理视频
        if ($response && isset($response['video_url'])) {
            $html = '<video controls src="' . esc_url($response['video_url']) . '" style="max-width:100%;height:auto;"></video>';
            $processed[] = array(
                'message'  => esc_html($log->message),
                'response' => $html
            );
        }
        // 处理图片
        else if ($response && isset($response['image_url'])) {
            // 使用message作为默认提示词，如果actual_prompt不存在
            $actual_prompt = isset($response['actual_prompt']) ? esc_html($response['actual_prompt']) : esc_html($log->message);
            $html = '<div class="image-prompt">' . $actual_prompt . '</div>';
            $html .= '<img src="' . esc_url($response['image_url']) . '" style="max-width:100%;height:auto;" />';
            $processed[] = array(
                'message'  => esc_html($log->message),
                'response' => $html
            );
        }
        // 处理文本
        else {
            $content = '';
            $reasoning_content = '';
            if (is_array($response)) {
                $content = isset($response['content']) ? $response['content'] : '';
                $reasoning_content = isset($response['reasoning_content']) ? $response['reasoning_content'] : '';
            } else {
                $content = $log->response;
            }

            $processed[] = array(
                'message'  => esc_html($log->message),
                'response' => [
                    'content' => $content,
                    'reasoning_content' => $reasoning_content
                ]
            );
        }
    }

    wp_send_json([
        'success'  => true, 
        'messages' => $processed
    ]);
}
add_action('wp_ajax_deepseek_load_log', 'deepseek_load_log');

// 删除对话记录
function deepseek_delete_log() {
    $nonce = deepseek_chat_get_rest_nonce_from_request();
    if (!wp_verify_nonce($nonce, 'wp_rest')) {
        wp_send_json(['success' => false, 'message' => '验证请求失败']);
        return;
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'deepseek_chat_logs';
    $conversation_id = isset($_POST['conversation_id']) ? intval($_POST['conversation_id']) : 0;
    if ($conversation_id <= 0) {
        wp_send_json(['success' => false, 'message' => '缺少有效的对话ID']);
        return;
    }
    $user_id = get_current_user_id();

    // 检查所有权权限
    if (!current_user_can('manage_options')) {
        if ($user_id == 0) {
            $device_id = isset($_SERVER['HTTP_X_DEVICE_ID']) ? sanitize_text_field($_SERVER['HTTP_X_DEVICE_ID']) : '';
            $owner_device_id = get_transient('deepseek_guest_conv_owner_' . $conversation_id);
            if (empty($device_id) || $device_id !== $owner_device_id) {
                wp_send_json(['success' => false, 'message' => '无权删除此记录']);
                return;
            }
        } else {
            $has_record = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE conversation_id = %d AND user_id = %d",
                $conversation_id, $user_id
            ));
            if (!$has_record) {
                wp_send_json(['success' => false, 'message' => '无权删除此记录']);
                return;
            }
        }
    }

    // 删除所有与该conversation_id相关的记录
    $deleted = $wpdb->delete(
        $table_name,
        ['conversation_id' => $conversation_id],
        ['%d']
    );

    if ($deleted === false) {
        error_log("删除对话记录失败: " . $wpdb->last_error);
        wp_send_json(['success' => false, 'message' => '删除失败: 数据库错误']);
    } elseif ($deleted === 0) {
        wp_send_json(['success' => false, 'message' => '未找到可删除的记录']);
    } else {
        wp_send_json(['success' => true, 'message' => '对话记录已删除']);
    }
}
add_action('wp_ajax_deepseek_delete_log', 'deepseek_delete_log');

// 对话记录管理页面
function deepseek_render_logs_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'deepseek_chat_logs';
    
    // 处理用户ID搜索
    $search_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : '';
    $where_clause = $search_user_id ? $wpdb->prepare("WHERE user_id = %d", $search_user_id) : '';

    // 删除记录
    if (isset($_GET['delete_conversation'])) {
        check_admin_referer('delete_chat_log_' . intval($_GET['delete_conversation']));
        $conversation_id = intval($_GET['delete_conversation']);
        $wpdb->delete($table_name, ['conversation_id' => $conversation_id], ['%d']);
        echo '<div class="notice notice-success"><p>对话记录已删除。</p></div>';
    }

    // 分页处理
    $per_page = 20;
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $per_page;

    // 获取总记录数
    $total_logs = $wpdb->get_var("SELECT COUNT(DISTINCT conversation_id) FROM $table_name $where_clause");

    // 获取当前页的记录，并关联用户信息
    $logs = $wpdb->get_results($wpdb->prepare(
        "SELECT cl.*, u.user_login 
         FROM $table_name cl 
         LEFT JOIN {$wpdb->users} u ON cl.user_id = u.ID 
         $where_clause 
         GROUP BY cl.conversation_id 
         ORDER BY cl.created_at DESC 
         LIMIT %d OFFSET %d",
        $per_page, $offset
    ));

    ?>
    <div class="wrap">
        <h1>用户AI对话记录</h1>
        
        <!-- 用户ID搜索表单 -->
        <form method="get" class="search-form" style="margin-bottom: 20px;">
            <input type="hidden" name="page" value="deepseek-logs">
            <label for="user_id">按用户ID搜索: </label>
            <input type="number" name="user_id" id="user_id" value="<?php echo esc_attr($search_user_id); ?>" min="1" style="width: 100px;">
            <input type="submit" class="button" value="搜索">
            <?php if ($search_user_id): ?>
                <a href="?page=deepseek-logs" class="button">显示所有记录</a>
            <?php endif; ?>
        </form>

        <?php if ($search_user_id): ?>
            <p>当前显示用户ID <?php echo esc_html($search_user_id); ?> 的对话记录</p>
        <?php endif; ?>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 80px;">用户ID</th>
                    <th style="width: 150px;">用户名</th>
                    <th style="width: 300px;">首句消息</th>
                    <th style="width: 160px;">时间</th>
                    <th style="width: 100px;">操作</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($logs)) : ?>
                    <?php foreach ($logs as $log) : ?>
                        <tr>
                            <td><?php echo esc_html($log->user_id); ?></td>
                            <td><?php echo esc_html($log->user_login ? $log->user_login : '未知用户'); ?></td>
                            <td><?php 
                                $title = mb_strlen($log->conversation_title, 'UTF-8') > 50 
                                    ? mb_substr($log->conversation_title, 0, 50, 'UTF-8') . '...' 
                                    : $log->conversation_title;
                                echo esc_html($title);
                            ?></td>
                            <td><?php echo esc_html($log->created_at); ?></td>
                            <td>
                                <?php 
                                    $delete_url = "?page=deepseek-logs&delete_conversation=" . esc_attr($log->conversation_id) . ($search_user_id ? '&user_id=' . $search_user_id : '');
                                    $nonce_url = wp_nonce_url($delete_url, 'delete_chat_log_' . $log->conversation_id);
                                ?>
                                <a href="<?php echo esc_url($nonce_url); ?>" 
                                   class="button" 
                                   onclick="return confirm('确定要删除此对话记录吗？');">删除</a>
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

        <!-- 分页导航 -->
        <?php if ($total_logs > $per_page): ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <?php
                    $total_pages = ceil($total_logs / $per_page);
                    $args = [
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => __('« 上一页'),
                        'next_text' => __('下一页 »'),
                        'total' => $total_pages,
                        'current' => $current_page,
                    ];
                    if ($search_user_id) {
                        $args['add_args'] = ['user_id' => $search_user_id];
                    }
                    echo paginate_links($args);
                    ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php
}


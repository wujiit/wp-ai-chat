<?php
// 检查是否需要加载WP_List_Table类
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

// 自定义文件列表表格类
class Deepseek_Files_Table extends WP_List_Table {
    public function __construct() {
        parent::__construct([
            'singular' => 'file',
            'plural' => 'files',
            'ajax' => false
        ]);
    }

    // 定义列标题
    public function get_columns() {
        return [
            'cb' => '<input type="checkbox" />',
            'filename' => '文件名',
            'file_id' => '文件 ID',
            'size' => '文件大小 (字节)',
            'created_at' => '上传时间',
            'status' => '状态',
            'actions' => '操作'
        ];
    }

    // 定义可排序列
    public function get_sortable_columns() {
        return [
            'filename' => ['filename', false],
            'created_at' => ['created_at', false],
            'size' => ['bytes', false]
        ];
    }

    // 获取文件数据
    private function fetch_files($per_page, $after = '') {
        $api_key = get_option('qwen_api_key');
        if (empty($api_key)) {
            return [];
        }

        $url = "https://dashscope.aliyuncs.com/compatible-mode/v1/files?limit=$per_page";
        if (!empty($after)) {
            $url .= "&after=" . urlencode($after);
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $api_key"]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPGET, true);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code == 200) {
            $data = json_decode($response, true);
            return $data['data'] ?? [];
        } else {
            error_log("Failed to fetch files: HTTP $http_code, Response: $response");
            return [];
        }
    }

    // 获取总文件数
    private function get_total_files() {
        $api_key = get_option('qwen_api_key');
        if (empty($api_key)) {
            return 0;
        }

        $total = 0;
        $per_page = 100;
        $after = '';
        $has_more = true;

        while ($has_more) {
            $url = "https://dashscope.aliyuncs.com/compatible-mode/v1/files?limit=$per_page";
            if (!empty($after)) {
                $url .= "&after=" . urlencode($after);
            }

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $api_key"]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPGET, true);
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($http_code != 200) {
                error_log("Failed to get total files: HTTP $http_code, Response: $response");
                return $total;
            }

            $data = json_decode($response, true);
            $files = $data['data'] ?? [];
            $total += count($files);
            $has_more = $data['has_more'] ?? false;

            if ($has_more && !empty($files)) {
                $after = end($files)['id'];
            } else {
                $has_more = false;
            }
        }

        return $total;
    }

    // 准备数据
    public function prepare_items() {
        $per_page = 50;
        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;

        $columns = $this->get_columns();
        $hidden = [];
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = [$columns, $hidden, $sortable];

        $this->process_bulk_action();

        $after = '';
        if ($offset > 0) {
            $prev_files = $this->fetch_files($offset);
            if (!empty($prev_files)) {
                $after = end($prev_files)['id'];
            }
        }

        $data = $this->fetch_files($per_page, $after);
        $total_items = $this->get_total_files();

        $this->items = $data;
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page' => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ]);
    }

    // 默认列显示
    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'filename':
                return esc_html($item['filename']);
            case 'file_id':
                return esc_html($item['id']);
            case 'size':
                return esc_html($item['bytes']);
            case 'created_at':
                $timestamp = $item['created_at'];
                return wp_date('Y-m-d H:i:s', $timestamp);
            case 'status':
                return esc_html($item['status']);
            default:
                return '';
        }
    }

    // 操作列
    public function column_actions($item) {
        $delete_url = wp_nonce_url(admin_url('admin.php?page=deepseek-files&action=delete_file&file_id=' . urlencode($item['id'])), 'delete_file_' . $item['id']);
        return '<a href="' . $delete_url . '" onclick="return confirm(\'确定删除此文件吗？\');">删除</a>';
    }

    // 复选框列
    public function column_cb($item) {
        return sprintf('<input type="checkbox" name="file_ids[]" value="%s" />', esc_attr($item['id']));
    }

    // 获取批量操作
    public function get_bulk_actions() {
        return [
            'delete' => '删除'
        ];
    }

    // 处理批量操作
    public function process_bulk_action() {
        if ('delete' === $this->current_action() && !empty($_POST['file_ids']) && check_admin_referer('bulk-' . $this->_args['plural'])) {
            $file_ids = array_map('sanitize_text_field', $_POST['file_ids']);
            $batch_size = 10; // 每批处理的文件数
            $batches = array_chunk($file_ids, $batch_size);

            foreach ($batches as $batch) {
                $this->delete_files_batch($batch);
                usleep(500000); // 暂停0.5秒，避免请求过快
            }

            // 添加成功提示
            add_settings_error(
                'deepseek_files',
                'files_deleted',
                sprintf('成功删除 %d 个文件', count($file_ids)),
                'success'
            );
        } elseif ('delete_file' === $this->current_action() && !empty($_GET['file_id']) && wp_verify_nonce($_GET['_wpnonce'], 'delete_file_' . $_GET['file_id'])) {
            $file_id = sanitize_text_field($_GET['file_id']);
            $this->delete_files_batch([$file_id]);

            // 添加单文件删除成功提示
            add_settings_error(
                'deepseek_files',
                'file_deleted',
                '文件已删除',
                'success'
            );
        }
    }

    // 批量删除文件
    private function delete_files_batch($file_ids) {
        $api_key = get_option('qwen_api_key');
        if (empty($api_key)) {
            error_log("API key not set for file deletion");
            return;
        }

        foreach ($file_ids as $file_id) {
            $url = "https://dashscope.aliyuncs.com/compatible-mode/v1/files/" . urlencode($file_id);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $api_key"]);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10); // 设置超时为10秒
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($http_code == 200) {
                error_log("File $file_id deleted successfully");
            } else {
                error_log("Failed to delete file $file_id: HTTP $http_code, Error: $error, Response: $response");
                add_settings_error(
                    'deepseek_files',
                    'file_delete_failed_' . $file_id,
                    sprintf('删除文件 %s 失败: HTTP %d', $file_id, $http_code),
                    'error'
                );
            }
        }
    }
}

// 文件列表页面回调函数
function deepseek_render_files_page() {
    $files_table = new Deepseek_Files_Table();
    $files_table->prepare_items();
    ?>
    <div class="wrap">
        <h1>文件列表管理</h1>
        <?php settings_errors('deepseek_files'); ?>
        <form method="post">
            <?php
            $files_table->display();
            ?>
        </form>
    </div>
    <p>只展示阿里通义千问的文件，阿里官方目前没文件控制台，只能自己调用，kimi官方有自己的文件管理控制台。一次性批量删除太多文件，可能会出现网页错误，但实际上文件已经删掉了，这是网站服务器问题。</p>
    <?php
}
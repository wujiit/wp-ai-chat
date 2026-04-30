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
        $api_key = deepseek_get_setting('qwen_api_key');
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
        deepseek_apply_curl_timeouts($ch, 'qwen_files', 30, 10);
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
        $api_key = deepseek_get_setting('qwen_api_key');
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
            deepseek_apply_curl_timeouts($ch, 'qwen_files', 30, 10);
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
        $delete_url = wp_nonce_url(admin_url('admin.php?page=deepseek-files&remote=1&action=delete_file&file_id=' . urlencode($item['id'])), 'delete_file_' . $item['id']);
        return '<a href="' . esc_url($delete_url) . '" onclick="return confirm(\'确定删除此文件吗？\');">删除</a>';
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
        } elseif ('delete_file' === $this->current_action() && !empty($_GET['file_id']) && isset($_GET['_wpnonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'delete_file_' . sanitize_text_field(wp_unslash($_GET['file_id'])))) {
            $file_id = sanitize_text_field(wp_unslash($_GET['file_id']));
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
        $api_key = deepseek_get_setting('qwen_api_key');
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
                if (function_exists('deepseek_mark_provider_file_deleted')) {
                    deepseek_mark_provider_file_deleted('qwen', $file_id);
                }
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

function deepseek_format_file_size($bytes) {
    $bytes = max(0, intval($bytes));
    if ($bytes >= 1073741824) {
        return round($bytes / 1073741824, 2) . ' GB';
    }
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 2) . ' MB';
    }
    if ($bytes >= 1024) {
        return round($bytes / 1024, 2) . ' KB';
    }
    return $bytes . ' B';
}

function deepseek_get_file_workspace_filters() {
    $filters = array(
        'record_source' => isset($_GET['record_source']) ? sanitize_key(wp_unslash($_GET['record_source'])) : '',
        'status' => isset($_GET['record_status']) ? sanitize_key(wp_unslash($_GET['record_status'])) : '',
        'interface_key' => isset($_GET['interface_key']) ? sanitize_key(wp_unslash($_GET['interface_key'])) : '',
        'storage_engine' => isset($_GET['storage_engine']) ? sanitize_key(wp_unslash($_GET['storage_engine'])) : '',
        'search' => isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '',
        'limit' => 20,
    );

    if (isset($_GET['user_id']) && $_GET['user_id'] !== '') {
        $filters['user_id'] = intval($_GET['user_id']);
    }

    return $filters;
}

function deepseek_render_file_workspace_filter_select($name, $current, $options) {
    echo '<select name="' . esc_attr($name) . '">';
    foreach ($options as $value => $label) {
        echo '<option value="' . esc_attr($value) . '" ' . selected($current, $value, false) . '>' . esc_html($label) . '</option>';
    }
    echo '</select>';
}

class Deepseek_Local_Files_Table extends WP_List_Table {
    public function __construct() {
        parent::__construct(array(
            'singular' => 'deepseek_local_file',
            'plural' => 'deepseek_local_files',
            'ajax' => false,
        ));
    }

    public function get_columns() {
        return array(
            'cb' => '<input type="checkbox" />',
            'file' => '文件',
            'owner' => '用户',
            'record_source' => '来源',
            'interface_key' => '接口',
            'storage_engine' => '存储',
            'purpose' => '用途',
            'file_size' => '大小',
            'status' => '状态',
            'created_at' => '时间',
        );
    }

    public function get_sortable_columns() {
        return array(
            'created_at' => array('created_at', true),
            'file_size' => array('file_size', false),
            'status' => array('status', false),
            'file' => array('original_filename', false),
        );
    }

    public function get_bulk_actions() {
        return array(
            'mark_deleted' => '标记为已删除',
            'delete_records' => '删除记录',
        );
    }

    public function prepare_items() {
        $per_page = 20;
        $current_page = $this->get_pagenum();
        $filters = deepseek_get_file_workspace_filters();
        $filters['limit'] = $per_page;
        $filters['offset'] = ($current_page - 1) * $per_page;

        $orderby = isset($_GET['orderby']) ? sanitize_key(wp_unslash($_GET['orderby'])) : 'created_at';
        $order = isset($_GET['order']) ? sanitize_key(wp_unslash($_GET['order'])) : 'DESC';
        $filters['orderby'] = $orderby;
        $filters['order'] = $order;

        $this->_column_headers = array($this->get_columns(), array(), $this->get_sortable_columns());
        $this->process_bulk_action();

        $this->items = deepseek_get_file_records($filters);
        unset($filters['limit'], $filters['offset'], $filters['orderby'], $filters['order']);
        $total_items = deepseek_count_file_records($filters);

        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page' => $per_page,
            'total_pages' => ceil($total_items / $per_page),
        ));
    }

    public function process_bulk_action() {
        $action = $this->current_action();
        if (!in_array($action, array('mark_deleted', 'delete_records'), true)) {
            return;
        }

        if (!current_user_can('manage_options') || !check_admin_referer('bulk-' . $this->_args['plural'])) {
            return;
        }

        $record_ids = isset($_POST['record_ids']) ? array_map('absint', (array) wp_unslash($_POST['record_ids'])) : array();
        $record_ids = array_values(array_filter($record_ids));
        if (empty($record_ids)) {
            return;
        }

        $handled = 0;
        foreach ($record_ids as $record_id) {
            if ($action === 'mark_deleted') {
                $handled += deepseek_update_file_record_status($record_id, 'deleted') ? 1 : 0;
            } else {
                $handled += deepseek_delete_file_record($record_id) ? 1 : 0;
            }
        }

        add_settings_error(
            'deepseek_files',
            'local_bulk_done',
            sprintf('已处理 %d 条本地文件记录。', $handled),
            'success'
        );
    }

    public function column_cb($item) {
        return '<input type="checkbox" name="record_ids[]" value="' . esc_attr($item->id) . '" />';
    }

    public function column_file($item) {
        $filename = $item->original_filename ?: '未命名文件';
        $url = deepseek_get_file_record_download_url($item);
        $label = $url
            ? '<a href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer">' . esc_html($filename) . '</a>'
            : esc_html($filename);

        $actions = array();
        if ($item->status !== 'deleted') {
            $actions['mark_deleted'] = '<a href="' . esc_url(deepseek_get_file_workspace_action_url('mark_deleted', $item->id)) . '">标记已删除</a>';
        }
        $actions['delete_record'] = '<a href="' . esc_url(deepseek_get_file_workspace_action_url('delete_record', $item->id)) . '" onclick="return confirm(\'确定删除这条本地记录吗？不会删除远端文件。\');">删除记录</a>';

        return $label . $this->row_actions($actions);
    }

    public function column_owner($item) {
        if (!empty($item->user_id)) {
            $user = get_userdata((int) $item->user_id);
            return $user ? esc_html($user->display_name . ' (#' . $item->user_id . ')') : esc_html('用户 #' . $item->user_id);
        }

        if (!empty($item->guest_device_hash)) {
            return esc_html('游客 ' . substr($item->guest_device_hash, 0, 8));
        }

        return '游客';
    }

    public function column_file_size($item) {
        return esc_html(deepseek_format_file_size($item->file_size));
    }

    public function column_created_at($item) {
        return esc_html($item->created_at);
    }

    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'record_source':
            case 'interface_key':
            case 'storage_engine':
            case 'purpose':
            case 'status':
                return esc_html($item->{$column_name});
            default:
                return '';
        }
    }
}

function deepseek_get_file_workspace_action_url($action, $record_id) {
    $url = add_query_arg(
        array(
            'page' => 'deepseek-files',
            'deepseek_file_action' => sanitize_key($action),
            'record_id' => absint($record_id),
        ),
        admin_url('admin.php')
    );

    return wp_nonce_url($url, 'deepseek_file_action_' . absint($record_id));
}

function deepseek_handle_file_workspace_actions() {
    if (!current_user_can('manage_options')) {
        return;
    }

    if (isset($_GET['deepseek_file_action'], $_GET['record_id'])) {
        $record_id = absint($_GET['record_id']);
        $action = sanitize_key(wp_unslash($_GET['deepseek_file_action']));
        if ($record_id && isset($_GET['_wpnonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'deepseek_file_action_' . $record_id)) {
            if ($action === 'mark_deleted') {
                deepseek_update_file_record_status($record_id, 'deleted');
                add_settings_error('deepseek_files', 'local_mark_deleted', '文件记录已标记为已删除。', 'success');
            } elseif ($action === 'delete_record') {
                deepseek_delete_file_record($record_id);
                add_settings_error('deepseek_files', 'local_record_deleted', '本地文件记录已删除。', 'success');
            }
        }
    }

    if (isset($_POST['deepseek_cleanup_action']) && check_admin_referer('deepseek_file_cleanup')) {
        $cleanup_action = sanitize_key(wp_unslash($_POST['deepseek_cleanup_action']));
        if ($cleanup_action === 'mark_orphans') {
            $marked = deepseek_mark_orphan_file_records();
            add_settings_error('deepseek_files', 'local_orphans_marked', sprintf('已标记 %d 条孤儿媒体记录。', $marked), 'success');
        } elseif ($cleanup_action === 'mark_expired_remote') {
            $days = isset($_POST['cleanup_days']) ? max(1, intval($_POST['cleanup_days'])) : 30;
            $marked = deepseek_mark_expired_remote_file_records($days);
            add_settings_error('deepseek_files', 'remote_expired_marked', sprintf('已标记 %d 条超过 %d 天的远端文件记录为 expired。', $marked, $days), 'success');
        } elseif ($cleanup_action === 'cleanup_deleted') {
            $days = isset($_POST['cleanup_days']) ? max(1, intval($_POST['cleanup_days'])) : 30;
            $deleted = deepseek_cleanup_file_records(array('older_than_days' => $days));
            add_settings_error('deepseek_files', 'local_cleanup_done', sprintf('已清理 %d 条超过 %d 天的已删除/失效记录。', $deleted, $days), 'success');
        }
    }
}
	
// 文件列表页面回调函数
function deepseek_render_files_page() {
    deepseek_handle_file_workspace_actions();

    $local_table = new Deepseek_Local_Files_Table();
    $local_table->prepare_items();
    $filters = deepseek_get_file_workspace_filters();
    $record_total = function_exists('deepseek_count_file_records') ? deepseek_count_file_records() : 0;
    $show_remote_files = isset($_GET['remote']) || (isset($_GET['action']) && $_GET['action'] === 'delete_file') || !empty($_POST['file_ids']);

    $files_table = null;
    if ($show_remote_files) {
        $files_table = new Deepseek_Files_Table();
        $files_table->prepare_items();
    }
    ?>
    <div class="wrap">
        <h1>文件工作区</h1>
        <?php settings_errors('deepseek_files'); ?>

        <h2>本地上传记录</h2>
        <p>当前已记录 <?php echo intval($record_total); ?> 条文件流水，可按来源、用户、接口、状态筛选。</p>

        <form method="get" style="margin: 12px 0 16px; display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
            <input type="hidden" name="page" value="deepseek-files" />
            <input type="search" name="s" value="<?php echo esc_attr($filters['search']); ?>" placeholder="文件名或远端 ID" />
            <input type="number" name="user_id" value="<?php echo isset($filters['user_id']) ? esc_attr($filters['user_id']) : ''; ?>" placeholder="用户ID" style="width: 90px;" />
            <?php
            deepseek_render_file_workspace_filter_select('record_source', $filters['record_source'], array(
                '' => '全部来源',
                'chat' => '普通对话',
                'agent' => '智能体',
            ));
            deepseek_render_file_workspace_filter_select('record_status', $filters['status'], array(
                '' => '全部状态',
                'active' => 'active',
                'deleted' => 'deleted',
                'missing' => 'missing',
                'expired' => 'expired',
            ));
            deepseek_render_file_workspace_filter_select('interface_key', $filters['interface_key'], array(
                '' => '全部接口',
                'qwen' => 'qwen',
                'kimi' => 'kimi',
                'openai' => 'openai',
                'coze' => 'coze',
            ));
            deepseek_render_file_workspace_filter_select('storage_engine', $filters['storage_engine'], array(
                '' => '全部存储',
                'remote_provider' => 'remote_provider',
                'wp_media' => 'wp_media',
            ));
            submit_button('筛选', 'secondary', '', false);
            ?>
            <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=deepseek-files')); ?>">重置</a>
        </form>

        <form method="post">
            <?php $local_table->display(); ?>
        </form>

        <hr style="margin: 24px 0;" />
        <h2>清理策略</h2>
        <form method="post" style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
            <?php wp_nonce_field('deepseek_file_cleanup'); ?>
            <label>
                保留天数
                <input type="number" name="cleanup_days" value="30" min="1" style="width: 80px;" />
            </label>
            <button type="submit" class="button" name="deepseek_cleanup_action" value="mark_orphans">标记孤儿媒体记录</button>
            <button type="submit" class="button" name="deepseek_cleanup_action" value="mark_expired_remote">标记过期远端记录</button>
            <button type="submit" class="button button-primary" name="deepseek_cleanup_action" value="cleanup_deleted">清理已删除/失效记录</button>
        </form>

        <hr style="margin: 24px 0;" />
        <h2>通义远端文件</h2>
        <p>远端列表需要请求通义接口，只有需要核对或删除远端文件时再加载。</p>
        <?php if ($show_remote_files && $files_table): ?>
            <form method="post">
                <input type="hidden" name="remote" value="1" />
                <?php $files_table->display(); ?>
            </form>
        <?php else: ?>
            <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=deepseek-files&remote=1')); ?>">加载通义远端文件</a>
        <?php endif; ?>
    </div>
    <p>通义远端列表只展示阿里通义千问文件；Kimi 等平台仍以本地记录为准。</p>
    <?php
}

<?php
if (!defined('ABSPATH')) {
    exit;
}

// 智能体设置页面
function deepseek_render_agents_page() {
    $saved = isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true';
    ?>
    <div class="ai-wrap">
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
                    <input type="text" id="agent_file_formats" name="agent_file_formats" value="<?php echo esc_attr(deepseek_get_setting('agent_file_formats', 'pdf')); ?>" style="width: 500px;" />
                    <p class="description">多种用英文逗号分隔，例如：pdf,docx,txt</p>
                </p>
                <p>
                    <strong>最大文件大小（MB）</strong>
                    <input type="number" id="agent_file_max_size" name="agent_file_max_size" value="<?php echo esc_attr(deepseek_get_setting('agent_file_max_size', 10)); ?>" min="1" style="width: 100px;" />
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
        <p>只支持普通对话，只支持联网搜索插件，文件上传只支持扣子应用。</p>
    </div>

    <?php if ($saved) : ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const successMessage = document.querySelector('.ai-wrap .success-message');
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
    // 处理用户ID搜索
    $search_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : '';

    // 分页处理
    $per_page = 20;
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $per_page;

    // 获取总记录数
    $total_logs = deepseek_count_agent_conversations($search_user_id);

    // 获取当前页的记录，并关联用户信息
    $logs = deepseek_get_admin_agent_conversations($search_user_id, $per_page, $offset);

    // 获取智能体名称映射
    $agents = deepseek_get_setting('deepseek_agents', []);
    $agent_map = [];
    foreach ($agents as $agent) {
        $agent_map[$agent['app_id']] = $agent['name'];
    }

    ?>
    <div class="wrap">
        <h1>智能体应用对话记录</h1>
        
        <!-- 用户ID搜索表单 -->
        <form method="get" class="search-form" style="margin-bottom: 20px;">
            <input type="hidden" name="page" value="deepseek-agent-logs">
            <label for="user_id">按用户ID搜索: </label>
            <input type="number" name="user_id" id="user_id" value="<?php echo esc_attr($search_user_id); ?>" min="1" style="width: 100px;">
            <input type="submit" class="button" value="搜索">
            <?php if ($search_user_id): ?>
                <a href="?page=deepseek-agent-logs" class="button">显示所有记录</a>
            <?php endif; ?>
        </form>

        <?php if ($search_user_id): ?>
            <p>当前显示用户ID <?php echo esc_html($search_user_id); ?> 的智能体应用对话记录</p>
        <?php endif; ?>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 80px;">用户ID</th>
                    <th style="width: 150px;">用户名</th>
                    <th style="width: 200px;">智能体应用</th>
                    <th style="width: 300px;">首句消息</th>
                    <th style="width: 160px;">时间</th>
                    <th style="width: 100px;">操作</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($logs)) : ?>
                    <?php foreach ($logs as $log) : ?>
                        <tr data-user-id="<?php echo esc_attr($log->user_id); ?>" data-app-id="<?php echo esc_attr($log->app_id); ?>">
                            <td><?php echo esc_html($log->user_id); ?></td>
                            <td><?php echo esc_html($log->user_login ? $log->user_login : '未知用户'); ?></td>
                            <td><?php echo esc_html(isset($agent_map[$log->app_id]) ? $agent_map[$log->app_id] : $log->app_id); ?></td>
                            <td><?php echo esc_html(mb_strimwidth($log->message, 0, 50, '...', 'UTF-8')); ?></td>
                            <td><?php echo esc_html($log->created_at); ?></td>
                            <td><button class="button delete-agent-log">删除</button></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr><td colspan="6">暂无记录。</td></tr>
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
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.delete-agent-log').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                if (!confirm('确定要删除此对话记录吗？')) return;

                const row = this.closest('tr');
                const userId = row.getAttribute('data-user-id');
                const appId = row.getAttribute('data-app-id');

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
    $api_key = deepseek_get_setting('volc_agent_api_key');
    echo '<input type="text" name="volc_agent_api_key" value="' . esc_attr($api_key) . '" style="width: 500px;" />';
    echo '<p class="description">输入火山引擎应用的API Key。</p>';
}

// 阿里API Key回调函数
function ali_agent_api_key_callback() {
    $api_key = deepseek_get_setting('ali_agent_api_key');
    echo '<input type="text" name="ali_agent_api_key" value="' . esc_attr($api_key) . '" style="width: 500px;" />';
    echo '<p class="description">输入阿里智能体应用的API KEY。</p>';
}

// 扣子回调函数
function coze_access_token_callback() {
    $token = deepseek_get_setting('coze_access_token');
    $expiry = deepseek_get_setting('coze_access_token_expiry');
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
    $agents = deepseek_get_setting('deepseek_agents', []);
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
        return deepseek_get_setting('coze_access_token_expiry');
    }
    return date('Y-m-d\TH:i', $timestamp);
}

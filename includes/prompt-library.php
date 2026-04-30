<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!defined('DEEPSEEK_PROMPT_LIBRARY_SCHEMA_VERSION')) {
    define('DEEPSEEK_PROMPT_LIBRARY_SCHEMA_VERSION', '1.0.0');
}

function deepseek_prompt_templates_table() {
    global $wpdb;
    return $wpdb->prefix . 'deepseek_prompt_templates';
}

function deepseek_prompt_library_table_exists($table_name) {
    global $wpdb;
    return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name)) === $table_name;
}

function deepseek_prompt_library_ensure_schema() {
    global $wpdb;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $table_name = deepseek_prompt_templates_table();
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table_name} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        title varchar(120) NOT NULL DEFAULT '',
        category varchar(80) NOT NULL DEFAULT '',
        prompt longtext NOT NULL,
        system_prompt longtext NULL,
        interface_key varchar(32) NOT NULL DEFAULT '',
        status varchar(20) NOT NULL DEFAULT 'active',
        sort_order int(11) NOT NULL DEFAULT 0,
        usage_count bigint(20) unsigned NOT NULL DEFAULT 0,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id),
        KEY status_sort (status, sort_order),
        KEY interface_status (interface_key, status),
        KEY category (category)
    ) {$charset_collate};";

    dbDelta($sql);
}

function deepseek_prompt_library_default_templates() {
    return array(
        array(
            'title' => 'SEO文章大纲',
            'category' => '内容创作',
            'prompt' => '请围绕这个主题生成一份适合站点发布的 SEO 文章大纲，包含标题建议、核心关键词、段落结构和写作重点：',
            'system_prompt' => '你是专业中文内容策划，输出结构清晰、可直接交给编辑执行。',
            'sort_order' => 10,
        ),
        array(
            'title' => '文案润色',
            'category' => '内容创作',
            'prompt' => '请帮我润色下面这段文案，要求更自然、更有说服力，同时保留原意：',
            'system_prompt' => '你是资深中文文案编辑，优先提升表达质感，不要夸张营销。',
            'sort_order' => 20,
        ),
        array(
            'title' => '产品卖点提炼',
            'category' => '商业运营',
            'prompt' => '请根据下面的信息提炼产品卖点，输出目标用户、核心痛点、3-5条卖点和一句宣传语：',
            'system_prompt' => '你是产品营销顾问，擅长把复杂信息转成普通用户能理解的卖点。',
            'sort_order' => 30,
        ),
        array(
            'title' => '知识库问答',
            'category' => '知识库',
            'prompt' => '请优先结合站点知识库回答这个问题，并给出依据：',
            'system_prompt' => '你是严谨的站点知识库助手。没有依据时明确说明，不要编造。',
            'sort_order' => 40,
        ),
        array(
            'title' => '代码解释',
            'category' => '开发辅助',
            'prompt' => '请解释下面这段代码的作用、关键逻辑、潜在问题和优化建议：',
            'system_prompt' => '你是耐心的高级工程师，解释要清楚，重点指出真实风险。',
            'sort_order' => 50,
        ),
    );
}

function deepseek_prompt_library_seed_defaults($force = false) {
    global $wpdb;

    if (!$force && get_option('deepseek_prompt_library_defaults_seeded', '0') === '1') {
        return;
    }

    $table_name = deepseek_prompt_templates_table();
    foreach (deepseek_prompt_library_default_templates() as $template) {
        $exists = intval($wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table_name} WHERE title = %s LIMIT 1",
            $template['title']
        )));
        if ($exists > 0) {
            continue;
        }

        $wpdb->insert(
            $table_name,
            array(
                'title' => sanitize_text_field($template['title']),
                'category' => sanitize_text_field($template['category']),
                'prompt' => sanitize_textarea_field($template['prompt']),
                'system_prompt' => sanitize_textarea_field($template['system_prompt']),
                'interface_key' => '',
                'status' => 'active',
                'sort_order' => intval($template['sort_order']),
                'updated_at' => current_time('mysql'),
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s')
        );
    }

    update_option('deepseek_prompt_library_defaults_seeded', '1', false);
}

function deepseek_prompt_library_bootstrap_schema($verify_table = false) {
    static $bootstrapped = false;

    if ($bootstrapped && !$verify_table) {
        return;
    }

    $bootstrapped = true;
    $table_name = deepseek_prompt_templates_table();
    $stored_version = get_option('deepseek_prompt_library_schema_version', '');
    $missing_table = $verify_table ? !deepseek_prompt_library_table_exists($table_name) : false;

    if ($stored_version !== DEEPSEEK_PROMPT_LIBRARY_SCHEMA_VERSION || $missing_table) {
        deepseek_prompt_library_ensure_schema();
        update_option('deepseek_prompt_library_schema_version', DEEPSEEK_PROMPT_LIBRARY_SCHEMA_VERSION, false);
        deepseek_prompt_library_seed_defaults(true);
        return;
    }

    if (get_option('deepseek_prompt_library_defaults_seeded', '') !== '1') {
        deepseek_prompt_library_seed_defaults(false);
    }
}

function deepseek_prompt_library_activate_schema() {
    deepseek_prompt_library_ensure_schema();
    update_option('deepseek_prompt_library_schema_version', DEEPSEEK_PROMPT_LIBRARY_SCHEMA_VERSION, false);
    deepseek_prompt_library_seed_defaults();
}

function deepseek_prompt_library_cleanup_schema() {
    global $wpdb;

    $wpdb->query('DROP TABLE IF EXISTS ' . deepseek_prompt_templates_table());
    delete_option('deepseek_prompt_library_schema_version');
    delete_option('deepseek_prompt_library_defaults_seeded');
}

function deepseek_prompt_library_get_allowed_interfaces() {
    $interfaces = deepseek_get_chat_interface_labels();
    return array('' => '所有接口') + $interfaces;
}

function deepseek_prompt_template_get($template_id) {
    global $wpdb;

    $template_id = absint($template_id);
    if ($template_id <= 0) {
        return null;
    }

    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM " . deepseek_prompt_templates_table() . " WHERE id = %d LIMIT 1",
        $template_id
    ));
}

function deepseek_prompt_template_for_request($template_id, $interface_key = '') {
    $template = deepseek_prompt_template_get($template_id);
    if (!$template || $template->status !== 'active') {
        return null;
    }

    $interface_key = sanitize_key($interface_key);
    if ($template->interface_key !== '' && $template->interface_key !== $interface_key) {
        return null;
    }

    return $template;
}

function deepseek_prompt_template_increment_usage($template_id) {
    global $wpdb;

    $template_id = absint($template_id);
    if ($template_id <= 0) {
        return;
    }

    $wpdb->query($wpdb->prepare(
        "UPDATE " . deepseek_prompt_templates_table() . " SET usage_count = usage_count + 1, updated_at = %s WHERE id = %d",
        current_time('mysql'),
        $template_id
    ));
}

function deepseek_prompt_library_get_active_templates() {
    global $wpdb;

    return $wpdb->get_results(
        "SELECT * FROM " . deepseek_prompt_templates_table() . " WHERE status = 'active' ORDER BY sort_order ASC, usage_count DESC, id ASC"
    );
}

function deepseek_prompt_library_get_admin_templates() {
    global $wpdb;

    return $wpdb->get_results(
        "SELECT * FROM " . deepseek_prompt_templates_table() . " ORDER BY status ASC, sort_order ASC, id DESC"
    );
}

function deepseek_prompt_library_sanitize_interface($interface_key) {
    $interface_key = sanitize_key($interface_key);
    $allowed = deepseek_prompt_library_get_allowed_interfaces();
    return isset($allowed[$interface_key]) ? $interface_key : '';
}

function deepseek_prompt_library_handle_admin_actions() {
    if (!current_user_can('manage_options')) {
        return;
    }

    if (!isset($_POST['deepseek_prompt_action'])) {
        return;
    }

    check_admin_referer('deepseek_prompt_library_admin');

    global $wpdb;
    $action = sanitize_key(wp_unslash($_POST['deepseek_prompt_action']));
    $table_name = deepseek_prompt_templates_table();

    if ($action === 'save') {
        $template_id = isset($_POST['template_id']) ? absint($_POST['template_id']) : 0;
        $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
        $category = isset($_POST['category']) ? sanitize_text_field(wp_unslash($_POST['category'])) : '';
        $prompt = isset($_POST['prompt']) ? sanitize_textarea_field(wp_unslash($_POST['prompt'])) : '';
        $system_prompt = isset($_POST['system_prompt']) ? sanitize_textarea_field(wp_unslash($_POST['system_prompt'])) : '';
        $interface_key = isset($_POST['interface_key']) ? deepseek_prompt_library_sanitize_interface(wp_unslash($_POST['interface_key'])) : '';
        $status = isset($_POST['status']) && $_POST['status'] === 'inactive' ? 'inactive' : 'active';
        $sort_order = isset($_POST['sort_order']) ? intval($_POST['sort_order']) : 0;

        if ($title === '' || $prompt === '') {
            add_settings_error('deepseek_prompt_library', 'prompt_required', '标题和提示词内容不能为空。', 'error');
            return;
        }

        $data = array(
            'title' => $title,
            'category' => $category,
            'prompt' => $prompt,
            'system_prompt' => $system_prompt,
            'interface_key' => $interface_key,
            'status' => $status,
            'sort_order' => $sort_order,
            'updated_at' => current_time('mysql'),
        );

        if ($template_id > 0) {
            $wpdb->update(
                $table_name,
                $data,
                array('id' => $template_id),
                array('%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s'),
                array('%d')
            );
            add_settings_error('deepseek_prompt_library', 'prompt_updated', '提示词模板已更新。', 'success');
            return;
        }

        $data['created_at'] = current_time('mysql');
        $wpdb->insert(
            $table_name,
            $data,
            array('%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s')
        );
        add_settings_error('deepseek_prompt_library', 'prompt_created', '提示词模板已新增。', 'success');
        return;
    }

    if ($action === 'seed_defaults') {
        deepseek_prompt_library_seed_defaults(true);
        add_settings_error('deepseek_prompt_library', 'prompt_seeded', '默认提示词模板已检查并补齐。', 'success');
        return;
    }

    $template_id = isset($_POST['template_id']) ? absint($_POST['template_id']) : 0;
    if ($template_id <= 0) {
        return;
    }

    if ($action === 'toggle') {
        $template = deepseek_prompt_template_get($template_id);
        if ($template) {
            $new_status = $template->status === 'active' ? 'inactive' : 'active';
            $wpdb->update(
                $table_name,
                array('status' => $new_status, 'updated_at' => current_time('mysql')),
                array('id' => $template_id),
                array('%s', '%s'),
                array('%d')
            );
            add_settings_error('deepseek_prompt_library', 'prompt_toggled', '提示词模板状态已更新。', 'success');
        }
    } elseif ($action === 'delete') {
        $wpdb->delete($table_name, array('id' => $template_id), array('%d'));
        add_settings_error('deepseek_prompt_library', 'prompt_deleted', '提示词模板已删除。', 'success');
    }
}

function deepseek_prompt_library_render_form($editing_template = null) {
    $allowed_interfaces = deepseek_prompt_library_get_allowed_interfaces();
    $template_id = $editing_template ? intval($editing_template->id) : 0;
    $title = $editing_template ? $editing_template->title : '';
    $category = $editing_template ? $editing_template->category : '';
    $prompt = $editing_template ? $editing_template->prompt : '';
    $system_prompt = $editing_template ? $editing_template->system_prompt : '';
    $interface_key = $editing_template ? $editing_template->interface_key : '';
    $status = $editing_template ? $editing_template->status : 'active';
    $sort_order = $editing_template ? intval($editing_template->sort_order) : 0;
    ?>
    <form method="post" class="card" style="max-width: 980px; padding: 18px; margin-top: 16px;">
        <?php wp_nonce_field('deepseek_prompt_library_admin'); ?>
        <input type="hidden" name="deepseek_prompt_action" value="save" />
        <input type="hidden" name="template_id" value="<?php echo esc_attr($template_id); ?>" />
        <h2><?php echo $template_id ? '编辑提示词模板' : '新增提示词模板'; ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="deepseek-prompt-title">标题</label></th>
                <td><input id="deepseek-prompt-title" type="text" name="title" class="regular-text" value="<?php echo esc_attr($title); ?>" required /></td>
            </tr>
            <tr>
                <th scope="row"><label for="deepseek-prompt-category">分类</label></th>
                <td><input id="deepseek-prompt-category" type="text" name="category" class="regular-text" value="<?php echo esc_attr($category); ?>" placeholder="例如：内容创作、运营、开发辅助" /></td>
            </tr>
            <tr>
                <th scope="row"><label for="deepseek-prompt-interface">适用接口</label></th>
                <td>
                    <select id="deepseek-prompt-interface" name="interface_key">
                        <?php foreach ($allowed_interfaces as $key => $label): ?>
                            <option value="<?php echo esc_attr($key); ?>" <?php selected($interface_key, $key); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">选择“所有接口”时，前台任意模型都可使用这个模板。</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="deepseek-prompt-content">用户提示词</label></th>
                <td>
                    <textarea id="deepseek-prompt-content" name="prompt" rows="5" class="large-text" required><?php echo esc_textarea($prompt); ?></textarea>
                    <p class="description">用户点击模板时会自动填入输入框；如果用户已输入内容，会追加到模板后方。</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="deepseek-prompt-system">系统提示词</label></th>
                <td>
                    <textarea id="deepseek-prompt-system" name="system_prompt" rows="4" class="large-text"><?php echo esc_textarea($system_prompt); ?></textarea>
                    <p class="description">发送对话时作为 system message 注入，用来固定回答角色、语气和规则。</p>
                </td>
            </tr>
            <tr>
                <th scope="row">状态和排序</th>
                <td>
                    <select name="status">
                        <option value="active" <?php selected($status, 'active'); ?>>启用</option>
                        <option value="inactive" <?php selected($status, 'inactive'); ?>>停用</option>
                    </select>
                    <input type="number" name="sort_order" value="<?php echo esc_attr($sort_order); ?>" style="width: 100px;" />
                    <span class="description">数字越小越靠前。</span>
                </td>
            </tr>
        </table>
        <?php submit_button($template_id ? '保存模板' : '新增模板'); ?>
    </form>
    <?php
}

function deepseek_prompt_library_render_admin_page() {
    deepseek_prompt_library_handle_admin_actions();

    $editing_template = null;
    if (isset($_GET['edit_prompt'])) {
        $editing_template = deepseek_prompt_template_get(absint($_GET['edit_prompt']));
    }

    $templates = deepseek_prompt_library_get_admin_templates();
    $allowed_interfaces = deepseek_prompt_library_get_allowed_interfaces();
    ?>
    <div class="wrap">
        <h1>提示词库</h1>
        <?php settings_errors('deepseek_prompt_library'); ?>
        <p>管理前台快捷提示词模板。模板可以限定接口，也可以注入系统提示词，让用户点击后直接进入高质量对话场景。</p>

        <?php deepseek_prompt_library_render_form($editing_template); ?>

        <h2 style="margin-top: 24px;">模板列表</h2>
        <form method="post" style="margin-bottom: 10px;">
            <?php wp_nonce_field('deepseek_prompt_library_admin'); ?>
            <input type="hidden" name="deepseek_prompt_action" value="seed_defaults" />
            <?php submit_button('补齐默认模板', 'secondary', 'submit', false); ?>
        </form>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>标题</th>
                    <th>分类</th>
                    <th>适用接口</th>
                    <th>状态</th>
                    <th>排序</th>
                    <th>使用次数</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($templates): foreach ($templates as $template): ?>
                    <tr>
                        <td><?php echo intval($template->id); ?></td>
                        <td><?php echo esc_html($template->title); ?></td>
                        <td><?php echo esc_html($template->category ?: '-'); ?></td>
                        <td><?php echo esc_html($allowed_interfaces[$template->interface_key] ?? $template->interface_key); ?></td>
                        <td><?php echo $template->status === 'active' ? '启用' : '停用'; ?></td>
                        <td><?php echo intval($template->sort_order); ?></td>
                        <td><?php echo intval($template->usage_count); ?></td>
                        <td>
                            <a class="button button-small" href="<?php echo esc_url(add_query_arg(array('page' => 'deepseek-prompts', 'edit_prompt' => intval($template->id)), admin_url('admin.php'))); ?>">编辑</a>
                            <form method="post" style="display:inline;">
                                <?php wp_nonce_field('deepseek_prompt_library_admin'); ?>
                                <input type="hidden" name="deepseek_prompt_action" value="toggle" />
                                <input type="hidden" name="template_id" value="<?php echo intval($template->id); ?>" />
                                <button class="button button-small" type="submit"><?php echo $template->status === 'active' ? '停用' : '启用'; ?></button>
                            </form>
                            <form method="post" style="display:inline;" onsubmit="return confirm('确定删除这个提示词模板吗？');">
                                <?php wp_nonce_field('deepseek_prompt_library_admin'); ?>
                                <input type="hidden" name="deepseek_prompt_action" value="delete" />
                                <input type="hidden" name="template_id" value="<?php echo intval($template->id); ?>" />
                                <button class="button button-small" type="submit">删除</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="8">暂无提示词模板。</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}

function deepseek_prompt_library_add_admin_menu() {
    add_submenu_page(
        'deepseek',
        '提示词库',
        '提示词库',
        'manage_options',
        'deepseek-prompts',
        'deepseek_prompt_library_render_admin_page'
    );
}

deepseek_prompt_library_bootstrap_schema();
add_action('admin_menu', 'deepseek_prompt_library_add_admin_menu', 21);
register_activation_hook(DEEPSEEK_PLUGIN_FILE, 'deepseek_prompt_library_activate_schema');

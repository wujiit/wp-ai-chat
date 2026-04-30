<?php
// 加载前台CSS文件
function deepseek_enqueue_assets() {
    if (is_singular('page')) {
        global $post;
        if (has_shortcode($post->post_content, 'deepseek_chat')) { // 检查是否包含短代码
            wp_enqueue_style('deepseek-chat-style', DEEPSEEK_PLUGIN_URL . 'wpai-style.css', array(), deepseek_get_asset_version('wpai-style.css'));
            wp_enqueue_script('marked-js', DEEPSEEK_PLUGIN_URL . 'marked.min.js', array(), deepseek_get_asset_version('marked.min.js'), true);
            wp_enqueue_script('deepseek-chat-script', DEEPSEEK_PLUGIN_URL . 'wpai-chat.js', array('marked-js'), deepseek_get_asset_version('wpai-chat.js'), true);

            // 传递PHP变量到JavaScript
            wp_localize_script(
                'deepseek-chat-script',
                'DEEPSEEK_VARS',
                array(
                    'AI_VOICE_ENABLED' => deepseek_get_setting('enable_ai_voice_reading', '0'),
                    'REST_NONCE' => wp_create_nonce('wp_rest'),
                    'REST_URL' => esc_url(rest_url('deepseek/v1/send-message')),
                    'FEEDBACK_URL' => esc_url(rest_url('deepseek/v1/message-feedback')),
                    'CONVERSATION_META_URL' => esc_url(rest_url('deepseek/v1/conversation-meta')),
                    'CONVERSATION_EXPORT_URL' => esc_url(rest_url('deepseek/v1/conversation-export')),
                    'ADMIN_AJAX_URL' => admin_url('admin-ajax.php'),
                    'PAGE_ID' => get_queried_object_id(),
                    'ENABLE_KEYWORD_DETECTION' => deepseek_get_setting('enable_keyword_detection', '0'),
                    'KEYWORDS' => deepseek_get_setting('keyword_list', ''),
                    'FILE_UPLOAD_NONCE' => wp_create_nonce('file_upload_action'),
                    'AGENT_FILE_UPLOAD_NONCE' => wp_create_nonce('agent_file_upload_action')
                )
            );
        }
    }
}
add_action('wp_enqueue_scripts', 'deepseek_enqueue_assets');

// 处理文件上传的AJAX请求
function deepseek_get_guest_interface_cookie_name() {
    return 'deepseek_guest_interface';
}

function deepseek_get_guest_selected_interface() {
    $cookie_name = deepseek_get_guest_interface_cookie_name();
    if (!isset($_COOKIE[$cookie_name])) {
        return '';
    }

    return sanitize_text_field(wp_unslash($_COOKIE[$cookie_name]));
}

function deepseek_set_guest_selected_interface($selected_interface) {
    $cookie_name = deepseek_get_guest_interface_cookie_name();
    $expire = time() + (30 * DAY_IN_SECONDS);
    $path = defined('COOKIEPATH') && COOKIEPATH ? COOKIEPATH : '/';
    $domain = defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : '';
    $secure = is_ssl();
    $httponly = true;

    setcookie($cookie_name, $selected_interface, $expire, $path, $domain, $secure, $httponly);
    $_COOKIE[$cookie_name] = $selected_interface;
}

function deepseek_handle_interface_switch() {
    check_ajax_referer('interface_switch_action', 'nonce');

    $user_id = get_current_user_id();
    $selected_interface = isset($_POST['selected_interface']) ? sanitize_text_field(wp_unslash($_POST['selected_interface'])) : '';
    $enabled_interfaces = deepseek_get_enabled_chat_interfaces();
    
    if (in_array($selected_interface, $enabled_interfaces)) {
        if (is_user_logged_in()) {
            update_user_meta($user_id, 'selected_chat_interface', $selected_interface);
        } else {
            deepseek_set_guest_selected_interface($selected_interface);
        }
        wp_send_json_success("接口已切换为: $selected_interface");
    } else {
        wp_send_json_error('无效的接口选择');
    }
}

// 获取用户当前选择的对话接口
function deepseek_get_user_interface() {
    $user_id = get_current_user_id();
    $enabled_interfaces = deepseek_get_enabled_chat_interfaces();
    $default_interface = deepseek_get_default_chat_interface();
    
    if (is_user_logged_in()) {
        $user_interface = get_user_meta($user_id, 'selected_chat_interface', true);
        return $user_interface && in_array($user_interface, $enabled_interfaces) ? $user_interface : $default_interface;
    }

    $guest_interface = deepseek_get_guest_selected_interface();
    return $guest_interface && in_array($guest_interface, $enabled_interfaces, true) ? $guest_interface : $default_interface;
}

function deepseek_get_current_interface() {
    $current_interface = deepseek_get_user_interface();
    wp_send_json_success(['interface' => $current_interface]);
}
add_action('wp_ajax_deepseek_get_current_interface', 'deepseek_get_current_interface');
add_action('wp_ajax_nopriv_deepseek_get_current_interface', 'deepseek_get_current_interface');
add_action('wp_ajax_deepseek_switch_interface', 'deepseek_handle_interface_switch');
add_action('wp_ajax_nopriv_deepseek_switch_interface', 'deepseek_handle_interface_switch');

// 对话 开始
function deepseek_chat_shortcode() {
    $user_id = get_current_user_id();
    $show_interface_switch = deepseek_get_setting('show_interface_switch', '0');
    $enabled_interfaces = deepseek_get_enabled_chat_interfaces();
    $qwen_enable_search = deepseek_get_setting('qwen_enable_search', '0');
    $current_interface = deepseek_get_user_interface();
    $enable_file_upload = deepseek_get_setting('enable_file_upload', '0');
    $guest_chat_limit = intval(deepseek_get_setting('deepseek_guest_chat_limit', 5));
    $guest_upload_limit = intval(deepseek_get_setting('deepseek_guest_upload_limit', 2));
    $guest_chat_enabled = $guest_chat_limit > 0;
    $guest_upload_enabled = $guest_upload_limit > 0;
    $model_params = deepseek_get_interface_model_params_map();

    $history = array();
    if (is_user_logged_in()) {
        $history = deepseek_get_user_chat_history_groups($user_id);
    }

    // 支持联网搜索的模型列表
    $search_supported_models = [
        'qwen' => ['qwen-max', 'qwen-plus', 'qwen-turbo'],
        'xunfei' => ['generalv3', 'generalv3.5', '4.0Ultra']
    ];
    $has_search_capable_interface = !empty(array_intersect($enabled_interfaces, array_keys($search_supported_models)));

    ob_start();
    ?>
    <div id="deepseek-chat-container">
        <!-- 历史记录区域 -->
        <div id="deepseek-chat-history">
            <?php if (is_user_logged_in()): ?>
                <button id="deepseek-new-chat">开启新对话</button>
                <?php if (deepseek_get_setting('enable_intelligent_agent', '0') == '1'): ?>
                    <div id="deepseek-agent-title" class="deepseek-agent-title" style="cursor: pointer;">智能体应用</div>
                <?php endif; ?>
                <?php 
                // 独立显示自定义入口，不依赖智能体应用入口
                if (deepseek_get_setting('enable_custom_entry', '0') == '1') {
                    $custom_title = deepseek_get_setting('custom_entry_title', '');
                    $custom_url = deepseek_get_setting('custom_entry_url', '');
                    if (!empty($custom_title) && !empty($custom_url)) {
                        echo '<a href="' . esc_url($custom_url) . '" target="_blank" class="deepseek-custom-entry-title">' . esc_html($custom_title) . '</a>';
                    }
                }
                ?>
                <ul>
                    <?php if (!empty($history)): ?>
                        <?php foreach ($history as $log): ?>
                            <?php
                                $raw_title = !empty($log->meta_title) ? $log->meta_title : (!empty($log->conversation_title) ? $log->conversation_title : $log->message);
                                $is_pinned = !empty($log->is_pinned);
                            ?>
                            <li data-conversation-id="<?php echo esc_attr($log->conversation_id); ?>" data-full-title="<?php echo esc_attr($raw_title); ?>" data-pinned="<?php echo $is_pinned ? '1' : '0'; ?>" class="<?php echo $is_pinned ? 'deepseek-history-pinned' : ''; ?>">
                                <span class="deepseek-chat-title">
                                    <?php 
                                        $title = mb_strlen($raw_title, 'UTF-8') > 6 
                                            ? mb_substr($raw_title, 0, 6, 'UTF-8') . '...' 
                                            : $raw_title;
                                        echo esc_html($title);
                                    ?>
                                </span>
                                <div class="deepseek-history-actions">
                                    <button class="deepseek-pin-log" data-conversation-id="<?php echo esc_attr($log->conversation_id); ?>" data-pinned="<?php echo $is_pinned ? '1' : '0'; ?>"><?php echo $is_pinned ? '取消置顶' : '置顶'; ?></button>
                                    <button class="deepseek-rename-log" data-conversation-id="<?php echo esc_attr($log->conversation_id); ?>">重命名</button>
                                    <button class="deepseek-export-log" data-conversation-id="<?php echo esc_attr($log->conversation_id); ?>">导出</button>
                                    <button class="deepseek-delete-log" data-conversation-id="<?php echo esc_attr($log->conversation_id); ?>">删除</button>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            <?php else: ?>
                <p>未登录，暂无历史记录</p>
            <?php endif; ?>
        </div>

        <!-- 主对话区域 -->
        <div id="deepseek-chat-main">
            <div id="deepseek-chat-messages">
                <div class="message-bubble bot" id="chatbot-prompt">你好，我可以帮你写作、写文案、翻译，有问题请问我~</div>
                <?php
                $prompt_templates = function_exists('deepseek_prompt_library_get_active_templates') ? deepseek_prompt_library_get_active_templates() : array();
                if (!empty($prompt_templates)) {
                    echo '<div id="deepseek-prompt-library">';
                    echo '<div class="deepseek-prompt-library-title">选择一个场景开始</div>';
                    echo '<div class="deepseek-prompt-library-grid">';
                    foreach ($prompt_templates as $template) {
                        echo '<button type="button" class="deepseek-prompt-template" data-prompt-id="' . esc_attr($template->id) . '" data-interface="' . esc_attr($template->interface_key) . '" data-prompt="' . esc_attr($template->prompt) . '">';
                        echo '<span class="deepseek-prompt-template-title">' . esc_html($template->title) . '</span>';
                        if (!empty($template->category)) {
                            echo '<span class="deepseek-prompt-template-category">' . esc_html($template->category) . '</span>';
                        }
                        echo '</button>';
                    }
                    echo '</div>';
                    echo '</div>';
                }

                $custom_prompts = deepseek_get_setting('deepseek_custom_prompts', '');
                if (!empty($custom_prompts)) {
                    $prompts = array_filter(array_map('trim', explode("\n", $custom_prompts)));
                    if (!empty($prompts)) {
                        echo '<div id="deepseek-custom-prompts">';
                        foreach ($prompts as $prompt) {
                            echo '<span class="deepseek-prompt">' . esc_html($prompt) . '</span>';
                        }
                        echo '</div>';
                    }
                }
                ?>
            </div>

            <div id="clear-conversation-container">
                <button id="clear-conversation-button" style="display: none;">清除对话</button>
            </div>

            <div id="deepseek-chat-input-container">
                <?php if (is_user_logged_in() || $guest_chat_enabled): ?>
                    <textarea id="deepseek-chat-input" placeholder="输入你的消息..." rows="4"></textarea>
                    <button id="deepseek-chat-send">发送</button>
                <?php else: 
                    $login_prompt = deepseek_get_setting('deepseek_login_prompt', '请先登录才能使用Ai对话功能');
                ?>
                    <div class="deepseek-login-overlay">
                        <?php echo esc_html($login_prompt); ?>
                    </div>
                <?php endif; ?>
            </div>

            <div id="keyword-error-message" style="color: red; display: none; margin-top: 5px; margin-left: 10px;">
                内容包含违规关键词，小助手无法正常处理，请刷新网页修改之后再试。
            </div>

            <div id="deepseek-options-bar">
                <?php if ($show_interface_switch == '1' && (is_user_logged_in() || $guest_chat_enabled)): ?>
                    <div class="deepseek-option-item deepseek-interface-select" style="display: none;">
                        <form id="interface-switch-form" method="post" action="">
                            <?php wp_nonce_field('interface_switch_action', 'interface_switch_nonce'); ?>
                            <label for="chat-interface-select">选择接口:</label>
                            <select name="selected_interface" id="chat-interface-select">
                                <?php
                                $interfaces = deepseek_get_chat_interface_labels();
                                foreach ($enabled_interfaces as $interface) {
                                    if (isset($interfaces[$interface])) {
                                        $selected = ($interface === $current_interface) ? 'selected' : '';
                                        echo '<option value="' . esc_attr($interface) . '" ' . $selected . '>' . 
                                             esc_html($interfaces[$interface]) . '</option>';
                                    }
                                }
                                ?>
                            </select>
                        </form>
                        <label for="chat-model-select">选择参数:</label>
                        <select name="selected_model" id="chat-model-select">
                            <!-- 模型参数选项动态填充 -->
                        </select>
                    </div>
                <?php endif; ?>

                <!-- 联网搜索开关 -->
                <?php if ($qwen_enable_search == '1' && $has_search_capable_interface): ?>
                    <div class="deepseek-option-item deepseek-search-toggle" style="display: none;" data-supported-models='<?php echo json_encode($search_supported_models); ?>'>
                <label class="switch">
                    <input type="checkbox" id="enable-search">
                    <span class="slider round"></span>
                </label>
                <span>联网搜索</span>
            </div>
        <?php endif; ?>

                <?php
                $tutorial_title = deepseek_get_setting('ai_tutorial_title', '');
                $tutorial_url   = deepseek_get_setting('ai_tutorial_url', '');
                if (!empty($tutorial_title) && !empty($tutorial_url)): ?>
                    <div class="deepseek-option-item deepseek-tutorial-link">
                        <a href="<?php echo esc_url($tutorial_url); ?>" target="_blank">
                            <?php echo esc_html($tutorial_title); ?>
                        </a>
                    </div>
                <?php endif; ?>
                
                <?php if ($enable_file_upload == '1' && (is_user_logged_in() || ($guest_chat_enabled && $guest_upload_enabled))): ?>
                    <div class="deepseek-option-item upload-section" style="display: none;">
                        <button id="deepseek-upload-file-btn">上传文件</button>
                        <button id="deepseek-recent-files-btn" type="button">最近文件</button>
                        <input type="file" id="deepseek-file-input" multiple style="display: none;" />
                        <div id="uploaded-files-list"></div>
                        <div id="deepseek-recent-files-panel"></div>
                    </div>
                <?php endif; ?> 

                <!-- 智能体文件上传板块 -->
                <div class="agent-upload-section" style="display: none;">
                    <button id="deepseek-agent-upload-btn">本地文件</button>
                    <input type="file" id="deepseek-agent-file-input" style="display: none;" />
                    <div id="agent-uploaded-file">
                        <span class="file-name"></span>
                        <button class="remove-file-btn">删除</button>
                    </div>
                </div>
            </div>
        
        <!-- 图片生成视频预览区域 -->
        <div id="qwen-video-image-preview" style="display: none; margin-top: 10px;">
        <div id="uploaded-image-container"></div>
        <button id="remove-uploaded-image">删除</button>
    </div>
    </div>
</div>
<?php
    // 获取公告内容并显示
    $announcement = deepseek_get_setting('deepseek_announcement', '');
    if (!empty($announcement)) {
        echo '<div id="deepseek-announcement">';
        echo wp_kses_post($announcement);
        echo '</div>';
    }
    ?>
    <script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function() {
        const input = document.getElementById('deepseek-chat-input');
        const sendButton = document.getElementById('deepseek-chat-send');
        if (input && sendButton && !sendButton.disabled) {
            input.addEventListener('keypress', function(event) {
                if (event.key === 'Enter' && !event.shiftKey) {
                    event.preventDefault();
                    sendButton.click();
                }
            });
        }

        const clearButton = document.getElementById('clear-conversation-button');
        if (clearButton) {
            clearButton.addEventListener('click', function() {
                showClearConfirmation(document.getElementById('deepseek-chat-messages'));
            });
        }
    });
    </script>

    <script type="text/javascript">
        var ajax_nonce = '<?php echo wp_create_nonce("interface_switch_action"); ?>';
        var ajax_url = '<?php echo admin_url("admin-ajax.php"); ?>';
        var enabled_interfaces = <?php echo wp_json_encode($enabled_interfaces); ?>;
        var model_params = <?php echo wp_json_encode($model_params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        var current_interface = '<?php echo esc_js($current_interface); ?>';
        var default_model = (model_params[current_interface] || '').split(',')[0];
    </script>

    <?php
    return ob_get_clean();
}
add_shortcode('deepseek_chat', 'deepseek_chat_shortcode');

// 使用REST API方式处理消息

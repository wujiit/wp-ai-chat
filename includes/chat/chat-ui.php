<?php
// 加载前台CSS文件
function deepseek_enqueue_assets() {
    if (is_singular('page')) {
        global $post;
        if (has_shortcode($post->post_content, 'deepseek_chat')) { // 检查是否包含短代码
            wp_enqueue_style('deepseek-chat-style', DEEPSEEK_PLUGIN_URL . 'wpai-style.css');
            wp_enqueue_script('marked-js', DEEPSEEK_PLUGIN_URL . 'marked.min.js', array(), null, true);
            wp_enqueue_script('deepseek-chat-script', DEEPSEEK_PLUGIN_URL . 'wpai-chat.js', array('marked-js'), null, true);

            // 传递PHP变量到JavaScript
            wp_localize_script(
                'deepseek-chat-script',
                'DEEPSEEK_VARS',
                array(
                    'AI_VOICE_ENABLED' => get_option('enable_ai_voice_reading', '0'),
                    'REST_NONCE' => wp_create_nonce('wp_rest'),
                    'REST_URL' => esc_url(rest_url('deepseek/v1/send-message')),
                    'ADMIN_AJAX_URL' => admin_url('admin-ajax.php'),
                    'ENABLE_KEYWORD_DETECTION' => get_option('enable_keyword_detection', '0'),
                    'KEYWORDS' => get_option('keyword_list', ''),
                    'FILE_UPLOAD_NONCE' => wp_create_nonce('file_upload_action'),
                    'AGENT_FILE_UPLOAD_NONCE' => wp_create_nonce('agent_file_upload_action')
                )
            );
        }
    }
}
add_action('wp_enqueue_scripts', 'deepseek_enqueue_assets');

// 处理文件上传的AJAX请求
function deepseek_handle_interface_switch() {
    check_ajax_referer('interface_switch_action', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error('请先登录');
        return;
    }

    $user_id = get_current_user_id();
    $selected_interface = isset($_POST['selected_interface']) ? sanitize_text_field($_POST['selected_interface']) : '';
    $enabled_interfaces = get_option('chat_interfaces', array('deepseek'));
    
    if (in_array($selected_interface, $enabled_interfaces)) {
        update_user_meta($user_id, 'selected_chat_interface', $selected_interface);
        wp_send_json_success("接口已切换为: $selected_interface");
    } else {
        wp_send_json_error('无效的接口选择');
    }
}

// 获取用户当前选择的对话接口
function deepseek_get_user_interface() {
    $user_id = get_current_user_id();
    $enabled_interfaces = get_option('chat_interfaces', array('deepseek'));
    $default_interface = get_option('default_chat_interface', 'deepseek');
    
    if (is_user_logged_in()) {
        $user_interface = get_user_meta($user_id, 'selected_chat_interface', true);
        return $user_interface && in_array($user_interface, $enabled_interfaces) ? $user_interface : $default_interface;
    }
    return $default_interface;
}

function deepseek_get_current_interface() {
    $current_interface = deepseek_get_user_interface();
    wp_send_json_success(['interface' => $current_interface]);
}
add_action('wp_ajax_deepseek_get_current_interface', 'deepseek_get_current_interface');
add_action('wp_ajax_nopriv_deepseek_get_current_interface', 'deepseek_get_current_interface');

// 对话 开始
function deepseek_chat_shortcode() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'deepseek_chat_logs';
    $user_id = get_current_user_id();
    $show_interface_switch = get_option('show_interface_switch', '0');
    $enabled_interfaces = get_option('chat_interfaces', array('deepseek'));
    $default_interface = get_option('default_chat_interface', 'deepseek');
    $qwen_enable_search = get_option('qwen_enable_search', '0');
    $current_interface = deepseek_get_user_interface();
    $enable_file_upload = get_option('enable_file_upload', '0');
    $guest_chat_limit = intval(get_option('deepseek_guest_chat_limit', 5));
    $guest_chat_enabled = $guest_chat_limit > 0;

    $history = array();
    if (is_user_logged_in()) {
        $history = $wpdb->get_results( 
            $wpdb->prepare(
                "SELECT * FROM $table_name 
                 WHERE user_id = %d 
                 GROUP BY conversation_id 
                 ORDER BY created_at DESC",
                $user_id
            ) 
        );
    }

    // 支持联网搜索的模型列表
    $search_supported_models = [
        'qwen' => ['qwen-max', 'qwen-plus', 'qwen-turbo'],
        'xunfei' => ['generalv3', 'generalv3.5', '4.0Ultra']
    ];

    ob_start();
    ?>
    <div id="deepseek-chat-container">
        <!-- 历史记录区域 -->
        <div id="deepseek-chat-history">
            <?php if (is_user_logged_in()): ?>
                <button id="deepseek-new-chat">开启新对话</button>
                <?php if (get_option('enable_intelligent_agent', '0') == '1'): ?>
                    <div id="deepseek-agent-title" class="deepseek-agent-title" style="cursor: pointer;">智能体应用</div>
                <?php endif; ?>
                <?php 
                // 独立显示自定义入口，不依赖智能体应用入口
                if (get_option('enable_custom_entry', '0') == '1') {
                    $custom_title = get_option('custom_entry_title', '');
                    $custom_url = get_option('custom_entry_url', '');
                    if (!empty($custom_title) && !empty($custom_url)) {
                        echo '<a href="' . esc_url($custom_url) . '" target="_blank" class="deepseek-custom-entry-title">' . esc_html($custom_title) . '</a>';
                    }
                }
                ?>
                <ul>
                    <?php if (!empty($history)): ?>
                        <?php foreach ($history as $log): ?>
                            <li data-conversation-id="<?php echo $log->conversation_id; ?>">
                                <span class="deepseek-chat-title">
                                    <?php 
                                        $title = mb_strlen($log->conversation_title, 'UTF-8') > 6 
                                            ? mb_substr($log->conversation_title, 0, 6, 'UTF-8') . '...' 
                                            : $log->conversation_title;
                                        echo esc_html($title);
                                    ?>
                                </span>
                                <button class="deepseek-delete-log" data-conversation-id="<?php echo $log->conversation_id; ?>">删除</button>
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
                $custom_prompts = get_option('deepseek_custom_prompts', '');
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
                    $login_prompt = get_option('deepseek_login_prompt', '请先登录才能使用Ai对话功能');
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
                <?php if ($show_interface_switch == '1' && is_user_logged_in()): ?>
                    <div class="deepseek-option-item deepseek-interface-select" style="display: none;">
                        <form id="interface-switch-form" method="post" action="">
                            <?php wp_nonce_field('interface_switch_action', 'interface_switch_nonce'); ?>
                            <label for="chat-interface-select">选择接口:</label>
                            <select name="selected_interface" id="chat-interface-select">
                                <?php
                                $interfaces = array(
                                    'deepseek' => 'DeepSeek',
                                    'openai' => 'OpenAI',
                                    'grok' => 'Grok',
                                    'gemini' => 'Gemini',
                                    'claude' => 'Claude',
                                    'qwen' => '通义千问',
                                    'kimi' => 'Kimi',
                                    'doubao' => '豆包AI',
                                    'qianfan' => '文心一言',
                                    'hunyuan' => '腾讯混元',
                                    'xunfei' => '讯飞星火',
                                    'pollinations' => '英文生图',
                                    'custom' => '备份接口'
                                );
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
                <?php if ($qwen_enable_search == '1' && in_array($current_interface, array_keys($search_supported_models))): ?>
                    <div class="deepseek-option-item deepseek-search-toggle" style="display: none;" data-supported-models='<?php echo json_encode($search_supported_models); ?>'>
                <label class="switch">
                    <input type="checkbox" id="enable-search">
                    <span class="slider round"></span>
                </label>
                <span>联网搜索</span>
            </div>
        <?php endif; ?>

                <?php
                $tutorial_title = get_option('ai_tutorial_title', '');
                $tutorial_url   = get_option('ai_tutorial_url', '');
                if (!empty($tutorial_title) && !empty($tutorial_url)): ?>
                    <div class="deepseek-option-item deepseek-tutorial-link">
                        <a href="<?php echo esc_url($tutorial_url); ?>" target="_blank">
                            <?php echo esc_html($tutorial_title); ?>
                        </a>
                    </div>
                <?php endif; ?>
                
                <?php if ($enable_file_upload == '1' && is_user_logged_in()): ?>
                    <div class="deepseek-option-item upload-section" style="display: none;">
                        <button id="deepseek-upload-file-btn">上传文件</button>
                        <input type="file" id="deepseek-file-input" multiple style="display: none;" />
                        <div id="uploaded-files-list"></div>
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
    $announcement = get_option('deepseek_announcement', '');
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
        var enabled_interfaces = <?php echo json_encode($enabled_interfaces); ?>;
        var model_params = {
            'deepseek': '<?php echo get_option('deepseek_model', 'deepseek-chat'); ?>',
            'doubao': '<?php echo get_option('doubao_model', ''); ?>',
            'kimi': '<?php echo get_option('kimi_model', ''); ?>',
            'openai': '<?php echo get_option('openai_model', ''); ?>',
            'grok': '<?php echo get_option('grok_model', ''); ?>',
            'gemini': '<?php echo get_option('gemini_model', 'gemini-2.0-flash'); ?>',
            'claude': '<?php echo get_option('claude_model', 'claude-3-7-sonnet-20250219'); ?>', 
            'qianfan': '<?php echo get_option('qianfan_model', ''); ?>',
            'hunyuan': '<?php echo get_option('hunyuan_model', ''); ?>',
            'xunfei': '<?php echo get_option('xunfei_model', 'generalv3.5'); ?>',
            'qwen': '<?php echo get_option('qwen_text_model', 'qwen-max') . ',' . get_option('qwen_image_model', 'wanx2.1-t2i-turbo') . ',' . get_option('qwen_video_model', 'wanx2.1-t2v-turbo'); ?>',
            'pollinations': '<?php echo get_option('pollinations_model', 'flux'); ?>',
            'custom': '<?php echo get_option('custom_model_params', ''); ?>'
        };
        var current_interface = '<?php echo esc_js($current_interface); ?>';
        var default_model = model_params[current_interface].split(',')[0];
    </script>

    <?php
    return ob_get_clean();
}
add_shortcode('deepseek_chat', 'deepseek_chat_shortcode');

// 使用REST API方式处理消息

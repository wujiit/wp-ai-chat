<?php
// 启用文章分析回调函数
function enable_article_analysis_callback() {
    $enabled = deepseek_get_setting('enable_article_analysis', '0');
    ?>
    <input type="checkbox" name="enable_article_analysis" value="1" <?php checked(1, $enabled); ?> />
    <p class="description">启用后，在经典编辑器下将显示文章分析功能，让ai模型对文章内容进行分析。</p>
    <?php
}


// 底部公告说明回调函数
function deepseek_announcement_callback() {
    $announcement = deepseek_get_setting('deepseek_announcement', '');
    ?>
    <textarea name="deepseek_announcement" rows="5" cols="50" style="width: 400px;"><?php echo esc_textarea($announcement); ?></textarea>
    <p class="description">输入公告内容，支持HTML格式，如果留空则前台不显示公告。</p>
    <?php
}

// 启用网站会员验证回调函数
function deepseek_vip_check_enabled_callback() {
    $enabled = deepseek_get_setting('deepseek_vip_check_enabled', '0');
    echo '<input type="checkbox" name="deepseek_vip_check_enabled" value="1" ' . checked(1, $enabled, false) . ' />';
    echo '<span class="description">启用后未开通网站会员的用户将无法使用对话功能</span>';
}
function deepseek_vip_keyword_callback() {
    $keyword = deepseek_get_setting('deepseek_vip_keyword', '升级VIP享受精彩下载');
    echo '<input type="text" name="deepseek_vip_keyword" value="' . esc_attr($keyword) . '" style="width:400px;" />';
    echo '<span class="description">设置会员验证时的提示关键词，需要你网站带会员级别系统，且有关键词</span>';
}
function deepseek_vip_prompt_page_callback() {
    $page_url = deepseek_get_setting('deepseek_vip_prompt_page', '');
    echo '<input type="text" name="deepseek_vip_prompt_page" value="' . esc_attr($page_url) . '" style="width:400px;" />';
    echo '<span class="description">用户点击开通按钮后跳转的页面URL</span>';
}

function deepseek_guest_chat_limit_callback() {
    $limit = deepseek_get_setting('deepseek_guest_chat_limit', 5);
    echo '<input type="number" name="deepseek_guest_chat_limit" value="' . esc_attr($limit) . '" style="width: 100px;" />';
    echo '<p class="description">未登录游客每天可以通过一个设备进行多少次对话或生成语音。<br>防刷机制：0 表示禁止游客使用对话，留空或填9999表示不限制。</p>';
}

function deepseek_guest_upload_limit_callback() {
    $limit = deepseek_get_setting('deepseek_guest_upload_limit', 2);
    echo '<input type="number" name="deepseek_guest_upload_limit" value="' . esc_attr($limit) . '" style="width: 100px;" />';
    echo '<p class="description">未登录游客每天可以通过一个设备上传多少次文件。<br>防刷机制：0 表示禁止游客上传。</p>';
}

function deepseek_context_memory_limit_callback() {
    $limit = deepseek_get_setting('deepseek_context_memory_limit', 5);
    echo '<input type="number" name="deepseek_context_memory_limit" value="' . esc_attr($limit) . '" min="0" style="width: 100px;" />';
    echo '<p class="description">控制模型发问时最多能够携带最近的几轮对话历史。<br>数字越小：越省钱，但遗忘之前说过的话越快。数字越大：回答越连贯，但极度消耗 Token 额度。建议值：3 ~ 8。</p>';
}

/**
 * 游客频率限制核心方法 (基于 Device ID Fingerprint)
 * 
 * @param string $action 'chat' 或 'upload'
 * @param bool $consume 是否实际消耗额度，默认 true
 * @return bool 是否通过限制 (true=通过, false=禁止)
 */
function deepseek_check_guest_limit($action = 'chat', $consume = true) {
    // 登录用户不受此限制或使用特定VIP控制
    if (is_user_logged_in()) {
        return true;
    }

    $limit = ($action === 'upload')
        ? intval(deepseek_get_setting('deepseek_guest_upload_limit', 2))
        : intval(deepseek_get_setting('deepseek_guest_chat_limit', 5));
    
    if ($limit <= 0) {
        return false; // 如果设置为 0 则直接禁止
    }

    $device_hash = deepseek_get_request_device_hash();
    if ($device_hash === '') {
        return false;
    }

    $transient_key = deepseek_get_guest_usage_transient_key($action, 'device');
    $current_usage = intval(get_transient($transient_key));
    $ip_transient_key = deepseek_get_guest_usage_transient_key($action, 'ip');
    $ip_usage = intval(get_transient($ip_transient_key));
    $ip_limit = max(1, $limit * 3);

    if ($current_usage >= $limit || $ip_usage >= $ip_limit) {
        return false;
    }

    if (!$consume) {
        return true;
    }

    // 更新次数，缓存24小时（86400秒）
    set_transient($transient_key, $current_usage + 1, DAY_IN_SECONDS);
    set_transient($ip_transient_key, $ip_usage + 1, DAY_IN_SECONDS);
    return true;
}

// 文件上传相关回调函数
function enable_file_upload_callback() {
    $enabled = deepseek_get_setting('enable_file_upload', '0');
    ?>
    <input type="checkbox" name="enable_file_upload" value="1" <?php checked(1, $enabled); ?> />
    <p class="description">启用后，前台底部状态栏将显示文件上传按钮，只支持kimi和通义千问的qwen-long模型，分析用户上传的文档</p>
    <?php
}

function allowed_file_types_callback() {
    $types = deepseek_get_setting('allowed_file_types', 'txt,docx,pdf,xlsx,md');
    ?>
    <input type="text" name="allowed_file_types" value="<?php echo esc_attr($types); ?>" style="width: 300px;" />
    <p class="description">多个格式用英文逗号分隔，例如：txt,docx,pdf,xlsx,md，具体以你选择的模型为准(图片生成视频的文件格式不受这个设置限制)</p>
    <?php
}

function max_file_size_callback() {
    $size = deepseek_get_setting('max_file_size', '100');
    ?>
    <input type="number" name="max_file_size" value="<?php echo esc_attr($size); ?>" min="1" max="500" style="width: 100px;" />
    <p class="description">单位：MB，最大根据你调用的模型确定</p>
    <?php
}

// 接口切换显示开关回调函数
function show_interface_switch_callback() {
    $enabled = deepseek_get_setting('show_interface_switch', '0');
    ?>
    <input type="checkbox" name="show_interface_switch" value="1" <?php checked(1, $enabled); ?> />
    <p class="description">启用后，前台页面底部状态栏将显示接口选择选项，用户可自行切换接口</p>
    <?php
}

// 数组sanitize回调函数
function sanitize_text_field_array($input) {
    if (!is_array($input)) {
        return array();
    }
    return array_map('sanitize_text_field', $input);
}

// 多选接口回调
function chat_interfaces_callback() {
    $options = deepseek_get_setting('chat_interfaces', array('deepseek'));
    $interfaces = deepseek_get_chat_interface_labels();
    ?>
    <select name="chat_interfaces[]" multiple style="height: 190px;">
        <?php foreach ($interfaces as $value => $label): ?>
            <option value="<?php echo esc_attr($value); ?>" <?php echo in_array($value, $options) ? 'selected' : ''; ?>>
                <?php echo esc_html($label); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <p class="description">按住Ctrl或Cmd键可多选启用的接口</p>
    <?php
}

// 对话默认接口回调
function default_chat_interface_callback() {
    $default = deepseek_get_setting('default_chat_interface', 'deepseek');
    $options = deepseek_get_setting('chat_interfaces', array('deepseek'));
    $interfaces = deepseek_get_chat_interface_labels();
    ?>
    <select name="default_chat_interface">
        <?php foreach ($interfaces as $value => $label): ?>
            <?php if (in_array($value, $options)): // 只显示已启用的接口 ?>
                <option value="<?php echo esc_attr($value); ?>" <?php selected($default, $value); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endif; ?>
        <?php endforeach; ?>
    </select>
    <p class="description">选择默认使用的对话接口</p>
    <?php
}

// 文章AI总结复选框回调
function enable_ai_summary_callback() {
    $enable_ai_summary = deepseek_get_setting('enable_ai_summary');
    echo '<input type="checkbox" name="enable_ai_summary" value="1" ' . checked(1, $enable_ai_summary, false) . ' />';
}

// 文章总结接口选择回调函数
function summary_interface_choice_callback() {
    $choice = deepseek_get_setting('summary_interface_choice', 'deepseek'); // 默认选择DeepSeek
    $interface_labels = deepseek_get_chat_interface_labels();
    $text_provider_configs = deepseek_get_openai_compatible_provider_configs();
    ?>
    <select name="summary_interface_choice">
        <?php foreach ($text_provider_configs as $value => $config): ?>
            <option value="<?php echo esc_attr($value); ?>" <?php selected($choice, $value); ?>>
                <?php echo esc_html($interface_labels[$value] ?? $value); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <p class="description">选择用于生成文章总结的AI接口，需要长文本模型，多模型参数默认采用第一个</p>
    <?php
}

// 自定义入口回调函数
function enable_custom_entry_callback() {
    $enabled = deepseek_get_setting('enable_custom_entry', '0');
    echo '<input type="checkbox" name="enable_custom_entry" value="1" ' . checked(1, $enabled, false) . ' />';
}

function custom_entry_title_callback() {
    $title = deepseek_get_setting('custom_entry_title', '');
    echo '<input type="text" name="custom_entry_title" value="' . esc_attr($title) . '" style="width: 300px;" />';
}

function custom_entry_url_callback() {
    $url = deepseek_get_setting('custom_entry_url', '');
    echo '<input type="url" name="custom_entry_url" value="' . esc_attr($url) . '" style="width: 500px;" />';
}

// 未登录提示文字输入框回调函数
function deepseek_login_prompt_callback() {
    $login_prompt = deepseek_get_setting('deepseek_login_prompt', '请先登录才能使用Ai对话功能');
    echo '<input type="text" name="deepseek_login_prompt" value="' . esc_attr($login_prompt) . '" style="width: 500px;" />';
}

// 启用智能体应用回调函数
function enable_intelligent_agent_callback() {
    $enabled = deepseek_get_setting('enable_intelligent_agent', '0');
    echo '<input type="checkbox" name="enable_intelligent_agent" value="1" ' . checked(1, $enabled, false) . ' />';
}

// 启用关键词检测的回调函数
function enable_keyword_detection_callback() {
    $enabled = deepseek_get_setting('enable_keyword_detection', '0');
    echo '<input type="checkbox" name="enable_keyword_detection" value="1" ' . checked(1, $enabled, false) . ' />';
}

// 关键词列表回调函数
function keyword_list_callback() {
    $keywords = deepseek_get_setting('keyword_list', '');
    echo '<textarea name="keyword_list" rows="5" cols="60" placeholder="请输入逗号分隔的关键词">' . esc_textarea($keywords) . '</textarea>';
    echo '<p class="description">请输入需要检测的关键词，多个关键词用英文逗号分隔。</p>';
}

// AI使用教程标题回调函数
function ai_tutorial_title_callback() {
    $title = deepseek_get_setting('ai_tutorial_title', '');
    echo '<input type="text" name="ai_tutorial_title" value="' . esc_attr($title) . '" style="width: 500px;" />';
}
function ai_tutorial_url_callback() {
    $url = deepseek_get_setting('ai_tutorial_url', '');
    echo '<input type="text" name="ai_tutorial_url" value="' . esc_attr($url) . '" style="width: 500px;" />';
}

// 自定义提示词回调函数
function deepseek_custom_prompts_callback() {
    $prompts = deepseek_get_setting('deepseek_custom_prompts', '');
    echo '<textarea name="deepseek_custom_prompts" rows="5" cols="60" placeholder="每行一个提示词">' . esc_textarea($prompts) . '</textarea>';
}

// AI对话语音朗读函数回调
function enable_ai_voice_reading_callback() {
    $checked = deepseek_get_setting('enable_ai_voice_reading', '0');
    echo '<input type="checkbox" name="enable_ai_voice_reading" value="1" ' . checked(1, $checked, false) . ' />';
}

// 前台ai助手按钮
// 助手入口处理函数回调
function show_ai_helper_callback() {
    $checked = deepseek_get_setting('show_ai_helper', '0');
    echo '<input type="checkbox" name="show_ai_helper" value="1" ' . checked(1, $checked, false) . ' />';
}
// 助手名称回调函数
function ai_helper_name_callback() {
    $name = deepseek_get_setting('ai_helper_name', 'AI 助手'); // 默认名称为"AI 助手"
    echo '<input type="text" name="ai_helper_name" value="' . esc_attr($name) . '" style="width:200px;" />';
    echo '<p class="description">输入AI助手的自定义名称</p>';
}
// 助手按钮图标链接回调函数
function ai_helper_icon_callback() {
    $icon = deepseek_get_setting('ai_helper_icon', ''); // 默认空值
    echo '<input type="text" name="ai_helper_icon" value="' . esc_attr($icon) . '" style="width:300px;" />';
    echo '<p class="description">输入图标图片的URL链接</p>';
}
// 助手按钮背景颜色回调函数
function ai_helper_background_callback() {
    $background = deepseek_get_setting('ai_helper_background', 'linear-gradient(135deg, #6EE7B7, #3B82F6)');
    echo '<input type="text" name="ai_helper_background" value="' . esc_attr($background) . '" style="width:300px;" />';
    echo '<p class="description">输入CSS背景颜色值，例如：#6EE7B7 或 linear-gradient(135deg, #6EE7B7, #3B82F6)</p>';
}
// AI助手按钮位置右边距回调函数
function ai_helper_right_callback() {
    $right = deepseek_get_setting('ai_helper_right', '5%'); // 默认右边距为5%
    echo '<input type="text" name="ai_helper_right" value="' . esc_attr($right) . '" style="width:100px;" />';
    echo '<p class="description">输入按钮距离右侧的距离，例如：5% 或 20px</p>';
}
// AI助手按钮位置底边距回调函数
function ai_helper_bottom_callback() {
    $bottom = deepseek_get_setting('ai_helper_bottom', '50%'); // 默认底边距为50%
    echo '<input type="text" name="ai_helper_bottom" value="' . esc_attr($bottom) . '" style="width:100px;" />';
    echo '<p class="description">输入按钮距离底部的距离，例如：50% 或 30px</p>';
}
// 在网站前台显示AI助手入口
function deepseek_display_ai_helper() {
    if (deepseek_get_setting('show_ai_helper', '0') == '1' && !is_page_with_deepseek_chat_shortcode()) {
        $ai_helper_right = deepseek_get_setting('ai_helper_right', '5%');
        $ai_helper_bottom = deepseek_get_setting('ai_helper_bottom', '50%');
        $ai_helper_name = deepseek_get_setting('ai_helper_name', 'AI 助手');
        $ai_helper_icon = deepseek_get_setting('ai_helper_icon', '');
        $ai_helper_background = deepseek_get_setting('ai_helper_background', 'linear-gradient(135deg, #6EE7B7, #3B82F6)');

        $icon_html = $ai_helper_icon ? 
            '<img src="' . esc_url($ai_helper_icon) . '" style="width: 24px; height: 24px; vertical-align: middle;">' : 
            '<span style="font-size: 24px;">&#129503;</span>';

        echo '<div id="ai-helper-button" style="
            position: fixed;
            right: ' . esc_attr($ai_helper_right) . ';
            bottom: ' . esc_attr($ai_helper_bottom) . ';
            transform: translateY(50%);
            z-index: 9999;
            cursor: pointer;
            font-size: 18px;
            font-weight: bold;
            color: #fff;
            background: ' . esc_attr($ai_helper_background) . ';
            padding: 5px 10px;
            border-radius: 15px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease-in-out;
            display: flex;
            align-items: center;
            gap: 5px;
        ">
            ' . $icon_html . '
            <span class="ai-helper-text">' . esc_html($ai_helper_name) . '</span>
        </div>';

        echo '<style>
            @media (max-width: 768px) {
                #ai-helper-button {
                    padding: 8px;
                    border-radius: 50%;
                    width: 40px;
                    height: 40px;
                    justify-content: center;
                }
                #ai-helper-button .ai-helper-text {
                    display: none;
                }
                #ai-helper-button img,
                #ai-helper-button span {
                    margin: 0 !important;
                }
            }
        </style>';

        echo '<script>
            document.getElementById("ai-helper-button").addEventListener("click", function() {
                fetch("' . admin_url('admin-ajax.php') . '?action=get_deepseek_chat_page")
                    .then(response => response.json())
                    .then(data => {
                        window.location.href = data.url;
                    })
                    .catch(() => {
                        window.location.href = "' . home_url() . '";
                    });
            });

            document.getElementById("ai-helper-button").addEventListener("mouseover", function() {
                this.style.transform = "translateY(50%) scale(1.1)";
                this.style.boxShadow = "0 6px 15px rgba(0, 0, 0, 0.3)";
            });

            document.getElementById("ai-helper-button").addEventListener("mouseout", function() {
                this.style.transform = "translateY(50%) scale(1)";
                this.style.boxShadow = "0 4px 10px rgba(0, 0, 0, 0.2)";
            });
        </script>';
    }
}
add_action('wp_footer', 'deepseek_display_ai_helper');


// 检查页面是否包含 [deepseek_chat] 短代码 用于显示ai助手按钮
function is_page_with_deepseek_chat_shortcode() {
    global $post;

    // 如果没有，直接返回false
    if (empty($post) || empty($post->post_content)) {
        return false;
    }

    // 检查页面内容是否包含 [deepseek_chat] 短代码
    return has_shortcode($post->post_content, 'deepseek_chat');
}
// 前台ai助手按钮end

// 查找包含 [deepseek_chat] 短代码的页面 用于跳转对话页面
function deepseek_clear_chat_page_cache($post_id = 0) {
    $post_type = $post_id ? get_post_type($post_id) : '';
    if ($post_type && $post_type !== 'page') {
        return;
    }

    delete_transient('deepseek_chat_page_id');
}
add_action('save_post_page', 'deepseek_clear_chat_page_cache');
add_action('deleted_post', 'deepseek_clear_chat_page_cache');
add_action('trashed_post', 'deepseek_clear_chat_page_cache');

function deepseek_get_chat_page_id_cached() {
    $cached = get_transient('deepseek_chat_page_id');
    if ($cached !== false) {
        return absint($cached);
    }

    global $wpdb;
    $page_id = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT ID FROM {$wpdb->posts}
        WHERE post_type = %s
        AND post_status = %s
        AND post_content LIKE %s
        ORDER BY ID ASC
        LIMIT 1",
        'page',
        'publish',
        '%' . $wpdb->esc_like('[deepseek_chat]') . '%'
    ));

    set_transient('deepseek_chat_page_id', $page_id, 12 * HOUR_IN_SECONDS);
    return $page_id;
}

function get_deepseek_chat_page() {
    $page_id = deepseek_get_chat_page_id_cached();

    if ($page_id) {
        $url = get_permalink($page_id);
    } else {
        $url = home_url(); // 默认跳转首页
    }

    wp_send_json(['url' => $url]);
}
add_action('wp_ajax_get_deepseek_chat_page', 'get_deepseek_chat_page');
add_action('wp_ajax_nopriv_get_deepseek_chat_page', 'get_deepseek_chat_page');


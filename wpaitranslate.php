<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // 防止直接访问
}

// 注册设置
function wpatai_register_settings() {
    register_setting( 'wpatai_options_group', 'wpatai_settings' );
}
add_action( 'admin_init', 'wpatai_register_settings' );

// 后台设置页面
function wpatai_settings_page() {
    $options = get_option( 'wpatai_settings' );
    if ( ! is_array( $options ) ) {
        $options = array();
    }
    ?>
    <style>
        .wpatai_wrap {
            background: #ffffff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .wpatai_wrap h1 {
            font-size: 24px;
            color: #333;
            border-bottom: 2px solid #007cba;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .wpatai_wrap .form-table th {
            width: 200px;
            text-align: left;
            font-weight: 600;
            padding: 10px;
        }
        .wpatai_wrap .form-table td {
            padding: 10px;
        }
        .wpatai_wrap input[type="text"],
        .wpatai_wrap select {
            width: 100%;
            max-width: 400px;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .wpatai_wrap input[type="checkbox"],
        .wpatai_wrap input[type="radio"] {
            margin-right: 5px;
        }
        .wpatai_wrap .description {
            font-size: 12px;
            color: #666;
        }
        .wpatai_wrap input[type="submit"] {
            background: #007cba;
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
        }
        .wpatai_wrap input[type="submit"]:hover {
            background: #005a8e;
        }
        /* 保存成功提示框 */
        #wpatai-save-success {
            background: #d4edda;
            color: #155724;
            padding: 10px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
            border-radius: 5px;
            text-align: center;
        }
    </style>
    <div class="wpatai_wrap">
        <h1>文章翻译朗读设置</h1>

        <?php if (isset($_GET['settings-updated']) && $_GET['settings-updated']): ?>
            <div id="wpatai-save-success">保存成功！</div>
            <script>
                setTimeout(() => {
                    let successMsg = document.getElementById('wpatai-save-success');
                    if (successMsg) {
                        successMsg.style.display = 'none';
                    }
                }, 1000);
            </script>
        <?php endif; ?>

        <form method="post" action="options.php">
            <?php settings_fields( 'wpatai_options_group' ); ?>
            <table class="form-table">
                <!-- 启用翻译服务 -->
                <tr valign="top">
                    <th scope="row">启用翻译服务</th>
                    <td>
                        <input type="checkbox" name="wpatai_settings[enable_translation]" value="1" <?php checked( 1, isset( $options['enable_translation'] ) ? $options['enable_translation'] : 0 ); ?> />
                        <label for="enable_translation">启用文章内容AI翻译功能</label>
                    </td>
                </tr>
                <!-- 选择调用的 API 接口 -->
                <tr valign="top">
                    <th scope="row">选择API接口</th>
                    <td>
                        <?php $selected_api = isset( $options['selected_api'] ) ? $options['selected_api'] : 'deepseek'; ?>
                        <select name="wpatai_settings[selected_api]">
                            <option value="deepseek" <?php selected( $selected_api, 'deepseek' ); ?>>DeepSeek</option>
                            <option value="tongyi" <?php selected( $selected_api, 'tongyi' ); ?>>通义千问</option>
                            <option value="doubao" <?php selected( $selected_api, 'doubao' ); ?>>豆包AI</option>
                        </select>
                    </td>
                </tr>
                <!-- DeepSeek 设置 -->
                <tr valign="top">
                    <th scope="row">DeepSeek API Key</th>
                    <td>
                        <input type="text" name="wpatai_settings[deepseek_api_key]" value="<?php echo isset( $options['deepseek_api_key'] ) ? esc_attr( $options['deepseek_api_key'] ) : ''; ?>" size="50" />
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">DeepSeek 模型参数</th>
                    <td>
                        <input type="text" name="wpatai_settings[deepseek_model]" value="<?php echo isset( $options['deepseek_model'] ) ? esc_attr( $options['deepseek_model'] ) : 'deepseek-chat'; ?>" size="50" />
                    </td>
                </tr>
                <!-- 通义千问 设置 -->
                <tr valign="top">
                    <th scope="row">通义千问 API Key</th>
                    <td>
                        <input type="text" name="wpatai_settings[tongyi_api_key]" value="<?php echo isset( $options['tongyi_api_key'] ) ? esc_attr( $options['tongyi_api_key'] ) : ''; ?>" size="50" />
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">通义千问 模型参数</th>
                    <td>
                        <input type="text" name="wpatai_settings[tongyi_model]" value="<?php echo isset( $options['tongyi_model'] ) ? esc_attr( $options['tongyi_model'] ) : 'qwen-plus'; ?>" size="50" />
                    </td>
                </tr>
                <!-- 豆包AI 设置 -->
                <tr valign="top">
                    <th scope="row">豆包AI API Key</th>
                    <td>
                        <input type="text" name="wpatai_settings[doubao_api_key]" value="<?php echo isset( $options['doubao_api_key'] ) ? esc_attr( $options['doubao_api_key'] ) : ''; ?>" size="50" />
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">豆包AI 模型参数</th>
                    <td>
                        <input type="text" name="wpatai_settings[doubao_model]" value="<?php echo isset( $options['doubao_model'] ) ? esc_attr( $options['doubao_model'] ) : ''; ?>" size="50" />
                    </td>
                </tr>
                <!-- 翻译语言设置 -->
                <tr valign="top">
                    <th scope="row">翻译语言设置</th>
                    <td>
                        <input type="text" name="wpatai_settings[translation_languages]" value="<?php echo isset( $options['translation_languages'] ) ? esc_attr( $options['translation_languages'] ) : '中文,英文,韩语,日语'; ?>" size="50" />
                        <p class="description">请输入翻译语言，多个语言请用逗号分隔，如：中文,英文,韩语,日语</p>
                    </td>
                </tr>
                <!-- 文章翻译方式 -->
                <tr valign="top">
                    <th scope="row">文章翻译方式</th>
                    <td>
                        <?php $translation_type = isset( $options['translation_type'] ) ? $options['translation_type'] : 'full'; ?>
                        <label>
                            <input type="radio" name="wpatai_settings[translation_type]" value="full" <?php checked( $translation_type, 'full' ); ?> />
                            全文覆盖翻译（完全替换原文，仅显示翻译内容）
                        </label><br>
                        <label>
                            <input type="radio" name="wpatai_settings[translation_type]" value="compare" <?php checked( $translation_type, 'compare' ); ?> />
                            对比翻译（每段文字显示：原文 + 翻译结果）
                        </label>
                    </td>
                </tr>
                <!-- 语音朗读设置 -->
                <tr valign="top">
                    <th scope="row">启用语音朗读</th>
                    <td>
                        <input type="checkbox" name="wpatai_settings[enable_tts]" value="1" <?php checked( 1, isset( $options['enable_tts'] ) ? $options['enable_tts'] : 0 ); ?> />
                        <label for="enable_tts">启用文章内容语音朗读功能</label>
                    </td>
                </tr>
                <!-- 语音合成接口选择 -->
                <tr valign="top">
                    <th scope="row">语音合成接口</th>
                    <td>
                        <?php $tts_interface = isset( $options['tts_interface'] ) ? $options['tts_interface'] : 'tencent'; ?>
                        <select name="wpatai_settings[tts_interface]">
                            <option value="tencent" <?php selected( $tts_interface, 'tencent' ); ?>>腾讯云</option>
                            <option value="baidu" <?php selected( $tts_interface, 'baidu' ); ?>>百度云</option>
                        </select>
                    </td>
                </tr>
                <!-- 腾讯云设置 -->
                <tr valign="top">
                    <th scope="row">腾讯云 SecretId</th>
                    <td>
                        <input type="text" name="wpatai_settings[tencent_secret_id]" value="<?php echo isset( $options['tencent_secret_id'] ) ? esc_attr( $options['tencent_secret_id'] ) : ''; ?>" size="50" />
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">腾讯云 SecretKey</th>
                    <td>
                        <input type="text" name="wpatai_settings[tencent_secret_key]" value="<?php echo isset( $options['tencent_secret_key'] ) ? esc_attr( $options['tencent_secret_key'] ) : ''; ?>" size="50" />
                    </td>
                </tr>
                <!-- 腾讯云音库值 -->
                <tr valign="top">
                    <th scope="row">腾讯云音库值</th>
                    <td>
                        <input type="text" name="wpatai_settings[tencent_voice_type]" value="<?php echo isset( $options['tencent_voice_type'] ) ? esc_attr( $options['tencent_voice_type'] ) : '0'; ?>" size="10" />
                        <p class="description">请输入腾讯云语音合成的音库值（VoiceType），默认值为 0。</p>
                    </td>
                </tr>
                <!-- 百度云设置 -->
                <tr valign="top">
                    <th scope="row">百度云 API_KEY</th>
                    <td>
                        <input type="text" name="wpatai_settings[baidu_api_key]" value="<?php echo isset( $options['baidu_api_key'] ) ? esc_attr( $options['baidu_api_key'] ) : ''; ?>" size="50" />
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">百度云 SECRET_KEY</th>
                    <td>
                        <input type="text" name="wpatai_settings[baidu_secret_key]" value="<?php echo isset( $options['baidu_secret_key'] ) ? esc_attr( $options['baidu_secret_key'] ) : ''; ?>" size="50" />
                    </td>
                </tr>
                <!-- 百度云音库值 -->
                <tr valign="top">
                    <th scope="row">百度云音库值</th>
                    <td>
                        <input type="text" name="wpatai_settings[baidu_per]" value="<?php echo isset( $options['baidu_per'] ) ? esc_attr( $options['baidu_per'] ) : '0'; ?>" size="10" />
                        <p class="description">请输入百度云语音合成的音库值（per），默认值为 0。</p>
                    </td>
                </tr>
                <!-- 排除的文章不加载翻译和语音 -->
                <tr valign="top">
                    <th scope="row">排除文章的ID</th>
                    <td>
                        <input type="text" name="wpatai_settings[exclude_post_ids]" value="<?php echo isset( $options['exclude_post_ids'] ) ? esc_attr( $options['exclude_post_ids'] ) : ''; ?>" size="50" />
                        <p class="description">请输入要排除的文章 ID，多个 ID 用英文逗号分隔，排除的文章不加载翻译和语音。</p>
                    </td>
                </tr>                
            </table>
            <?php submit_button(); ?>
        </form>
        <p>文章翻译和朗读功能原本是单独的插件，是后面合并进ai助手里面的。<br>
        模型要支持长文本才能翻译，毕竟文章内容一般都很多，而有的ai模型一次只能接收很短的内容。<br>
    语音合成接口采用的都是短文本，通过分段提交实现朗读，如果用长文本收费会更贵。<br>
如果你要全站免费翻译，推荐我另外一个专业的多语言插件：<a href="https://www.wujiit.com/wptr" target="_blank">小半多语言翻译</a></p>
    </div>
    <?php
}

// 文章添加控制面板
function wpatai_append_control_bar( $content ) {
    if ( ! is_singular( 'post' ) ) {
        return $content;
    }
    $options = get_option( 'wpatai_settings' );
    $post_id = get_the_ID();

    // 检查是否在排除列表中
    $exclude_post_ids = isset( $options['exclude_post_ids'] ) ? explode( ',', $options['exclude_post_ids'] ) : [];
    $exclude_post_ids = array_map( 'trim', $exclude_post_ids );
    if ( in_array( strval( $post_id ), $exclude_post_ids ) ) {
        return $content;
    }

    // 若翻译与朗读均未启用，则不添加
    if ( empty( $options['enable_translation'] ) && empty( $options['enable_tts'] ) ) {
        return $content;
    }
    
    // 构造控制面板容器，附带文章ID及翻译模式
    $control_panel  = '<div class="wpatai-control-panel" data-postid="' . $post_id . '" data-translation-type="' . esc_attr( isset( $options['translation_type'] ) ? $options['translation_type'] : 'full' ) . '">';
    
    // 语音朗读按钮
    if ( ! empty( $options['enable_tts'] ) ) {
        $control_panel .= '<span class="wpatai-tts-btn" title="朗读文章" style="cursor:pointer; margin-right:15px; font-size:22px;">&#128266;</span>';
    }
    
    // 翻译语言按钮
    if ( ! empty( $options['enable_translation'] ) ) {
        $languages = array_filter( array_map( 'trim', explode( ',', $options['translation_languages'] ) ) );
        if ( ! empty( $languages ) ) {
            $control_panel .= '<div class="wpatai-language-switcher" style="display:inline-block;">';
            foreach ( $languages as $lang ) {
                $control_panel .= '<span class="wpatai-translate-btn" data-language="' . esc_attr( $lang ) . '">' . esc_html( $lang ) . '</span> ';
            }
            $control_panel .= '</div>';
        }
    }
    
    $control_panel .= '</div>';
    
    // 包裹文章内容，便于后续更新翻译结果
    $wrapped_content = '<div id="wpatai-post-content">' . $content . '</div>';
    
    return $control_panel . $wrapped_content;
}
add_filter( 'the_content', 'wpatai_append_control_bar' );


// 文章页加载
function wpatai_enqueue_assets() {
    if ( ! is_singular( 'post' ) ) {
        return;
    }

    $options = get_option( 'wpatai_settings' );
    // 获取当前文章ID
    $post_id = get_the_ID();
    $exclude_post_ids = isset( $options['exclude_post_ids'] ) ? explode( ',', $options['exclude_post_ids'] ) : [];
    $exclude_post_ids = array_map( 'trim', $exclude_post_ids );
    // 检查当前文章ID是否在排除列表中
    if ( in_array( strval( $post_id ), $exclude_post_ids ) ) {
        return;
    }

    if ( empty( $options['enable_translation'] ) && empty( $options['enable_tts'] ) ) {
        return;
    }

    // 输出内联CSS
    add_action('wp_head', function() {
        ?>
        <style>
            /* 整体控制面板 */
            .wpatai-control-panel {
                background: #f9f9f9;
                padding: 8px 12px;
                margin-bottom: 15px;
                border: 1px solid #ddd;
                border-radius: 12px;
                display: flex;
                align-items: center;
                flex-wrap: wrap;
            }
            /* 语音朗读图标按钮 */
            .wpatai-tts-btn {
                font-size: 22px;
                cursor: pointer;
                margin-right: 15px;
            }
            .wpatai-tts-btn:hover {
                opacity: 0.8;
            }
            /* 翻译按钮 */
            .wpatai-language-switcher span.wpatai-translate-btn {
                display: inline-block;
                padding: 5px 10px;
                margin-right: 8px;
                background: linear-gradient(135deg, #1E3A8A, #3B82F6);
                color: #fff;
                border-radius: 5px;
                cursor: pointer;
                font-size: 14px;
                transition: background-color 0.3s ease;
            }
            .wpatai-language-switcher span.wpatai-translate-btn:hover {
                background-color: #005177;
            }
        </style>
        <?php
    });

    // 加载JS脚本
    wp_enqueue_script( 'wpatai-script', plugin_dir_url( __FILE__ ) . 'wpai-script.js', array( 'jquery' ), '2.2', true );
    wp_localize_script( 'wpatai-script', 'wpatai_ajax_obj', array(
        'ajax_url'  => admin_url( 'admin-ajax.php' ),
        'nonce'     => wp_create_nonce( 'wpatai_translate_nonce' ),
        'tts_nonce' => wp_create_nonce( 'wpatai_tts_nonce' )
    ) );
}
add_action( 'wp_enqueue_scripts', 'wpatai_enqueue_assets' );


// AJAX 处理翻译请求
function wpatai_handle_translation() {
    check_ajax_referer( 'wpatai_translate_nonce', 'nonce' );
    
    $post_id         = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
    $target_language = isset( $_POST['target_language'] ) ? sanitize_text_field( $_POST['target_language'] ) : '';
    
    if ( ! $post_id || empty( $target_language ) ) {
        wp_send_json_error( '无效参数' );
    }
    
    $post = get_post( $post_id );
    if ( ! $post ) {
        wp_send_json_error( '未找到文章' );
    }
    $original_content = $post->post_content;
    
    $options          = get_option( 'wpatai_settings' );
    $selected_api     = isset( $options['selected_api'] ) ? $options['selected_api'] : 'deepseek';
    $translation_type = isset( $options['translation_type'] ) ? $options['translation_type'] : 'full';
    
    // 对文章HTML进行处理，提取纯文字部分，调用AI翻译API，并生成最终HTML。
    $translated_content = wpatai_translate_content( $original_content, $target_language, $translation_type, $selected_api, $options );
    
    wp_send_json_success( array( 'translated_text' => $translated_content ) );
}
add_action( 'wp_ajax_wpatai_translate', 'wpatai_handle_translation' );
add_action( 'wp_ajax_nopriv_wpatai_translate', 'wpatai_handle_translation' );

// 处理语音朗读请求
function wpatai_handle_tts() {
    set_time_limit(0); // 取消PHP执行时间限制
    check_ajax_referer( 'wpatai_tts_nonce', 'nonce' );
    
    $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
    if ( ! $post_id ) {
        wp_send_json_error( '无效参数' );
    }
    
    $post = get_post( $post_id );
    if ( ! $post ) {
        wp_send_json_error( '未找到文章' );
    }
    // 只提取纯文本（排除图片、视频、代码等）
    $text = wp_strip_all_tags( $post->post_content );
    
    $options = get_option( 'wpatai_settings' );
    if ( empty( $options['enable_tts'] ) ) {
        wp_send_json_error( '语音朗读功能未启用' );
    }
    
    // 根据后台设置选择使用哪种语音合成接口
    $tts_interface = isset( $options['tts_interface'] ) ? $options['tts_interface'] : 'tencent';
    
    $audio_urls = array();
    $chunk_size = 50;
    $total_length = mb_strlen($text, 'UTF-8');
    
    if ( $tts_interface === 'tencent' ) {
        $secret_id  = isset( $options['tencent_secret_id'] ) ? trim( $options['tencent_secret_id'] ) : '';
        $secret_key = isset( $options['tencent_secret_key'] ) ? trim( $options['tencent_secret_key'] ) : '';
        if ( empty( $secret_id ) || empty( $secret_key ) ) {
            wp_send_json_error( '腾讯云凭证未配置' );
        }
        $tencent_voice_type = isset($options['tencent_voice_type']) ? $options['tencent_voice_type'] : 0;
        for ($i = 0; $i < $total_length; $i += $chunk_size) {
            $chunk = mb_substr($text, $i, $chunk_size, 'UTF-8');
            $result = wpatai_call_tts_api( $chunk, $secret_id, $secret_key, $tencent_voice_type );
            if ( is_wp_error( $result ) ) {
                wp_send_json_error( "语音合成错误，段落 $i: " . $result->get_error_message() );
            }
            $audio_urls[] = $result;
        }
    } elseif ( $tts_interface === 'baidu' ) {
        $baidu_api_key    = isset( $options['baidu_api_key'] ) ? trim( $options['baidu_api_key'] ) : '';
        $baidu_secret_key = isset( $options['baidu_secret_key'] ) ? trim( $options['baidu_secret_key'] ) : '';
        if ( empty( $baidu_api_key ) || empty( $baidu_secret_key ) ) {
            wp_send_json_error( '百度云凭证未配置' );
        }
        $baidu_per = isset($options['baidu_per']) ? $options['baidu_per'] : 0;
        for ($i = 0; $i < $total_length; $i += $chunk_size) {
            $chunk = mb_substr($text, $i, $chunk_size, 'UTF-8');
            $result = wpatai_call_baidu_tts_api( $chunk, $baidu_api_key, $baidu_secret_key, $baidu_per );
            if ( is_wp_error( $result ) ) {
                wp_send_json_error( "语音合成错误，段落 $i: " . $result->get_error_message() );
            }
            $audio_urls[] = $result;
        }
    } else {
        wp_send_json_error( '未配置正确的语音合成接口' );
    }
    
    wp_send_json_success( array( 'audio_urls' => $audio_urls ) );
}
add_action( 'wp_ajax_wpatai_tts', 'wpatai_handle_tts' );
add_action( 'wp_ajax_nopriv_wpatai_tts', 'wpatai_handle_tts' );

// 翻译处理函数
function wpatai_translate_content( $html, $target_language, $translation_type, $selected_api, $options ) {
    $delimiter = "%%WPATAI_DELIM%%"; // 分隔符
    $tokens = array();
    $original_texts = array();
    
    libxml_use_internal_errors(true);
    $doc = new DOMDocument();
    $doc->loadHTML('<?xml encoding="UTF-8"><div id="wpatai_wrapper">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    $xpath = new DOMXPath($doc);
    
    // 查询所有非空文本节点（排除code、pre、script、style标签内的内容）
    $textNodes = $xpath->query('//text()[normalize-space(.) and not(ancestor::code) and not(ancestor::pre) and not(ancestor::script) and not(ancestor::style)]');
    
    $index = 0;
    foreach ( $textNodes as $node ) {
        $text = $node->nodeValue;
        if ( trim( $text ) === '' ) {
            continue;
        }
        $token = $delimiter . $index . $delimiter;
        $tokens[] = $token;
        $original_texts[] = $text;
        $node->nodeValue = $token;
        $index++;
    }
    
    $wrapper = $doc->getElementById('wpatai_wrapper');
    $modified_html = '';
    foreach ( $wrapper->childNodes as $child ) {
        $modified_html .= $doc->saveHTML( $child );
    }
    
    $joined_text = implode( $delimiter, $original_texts );
    $prompt = "请将以下文本翻译成{$target_language}。文本由分隔符 \"{$delimiter}\" 分隔，请在翻译结果中使用相同的分隔符分隔每一段，并严格保持分隔符不变。请仅返回翻译后的文本，不要添加其他内容。\n\n" . $joined_text;
    
    $api_result = wpatai_call_api( $prompt, $selected_api, $options );
    if ( is_wp_error( $api_result ) ) {
        return "翻译API调用错误：" . $api_result->get_error_message();
    }
    
    $translated_segments = explode( $delimiter, $api_result );
    $final_html = $modified_html;
    foreach ( $tokens as $i => $token ) {
        $original = $original_texts[ $i ];
        $translated = isset( $translated_segments[ $i ] ) ? $translated_segments[ $i ] : '';
        if ( $translation_type === 'compare' ) {
            $replacement = htmlspecialchars( $original ) . '<br/>' . htmlspecialchars( $translated );
        } else {
            $replacement = htmlspecialchars( $translated );
        }
        $final_html = str_replace( $token, $replacement, $final_html );
    }
    
    return $final_html;
}

// 调用指定AI接口进行翻译
function wpatai_call_api( $prompt, $selected_api, $options ) {
    // 初始化返回错误变量
    $last_error = null;

    // DeepSeek 接口
    if ( $selected_api === 'deepseek' ) {
        $api_key = isset( $options['deepseek_api_key'] ) ? $options['deepseek_api_key'] : '';
        // 允许多个模型参数，用英文逗号分隔
        $models = isset( $options['deepseek_model'] ) ? explode( ',', $options['deepseek_model'] ) : array('deepseek-chat');
        $models = array_map('trim', $models);
        $endpoint = 'https://api.deepseek.com/chat/completions';
        $headers = array(
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $api_key,
        );
        foreach ( $models as $model ) {
            $payload = array(
                'model'    => $model,
                'messages' => array(
                    array( 'role' => 'system', 'content' => 'You are a helpful assistant.' ),
                    array( 'role' => 'user', 'content' => $prompt ),
                ),
                'stream'   => false,
            );
            $args = array(
                'body'    => json_encode( $payload ),
                'headers' => $headers,
                'timeout' => 60,
            );
            $response = wp_remote_post( $endpoint, $args );
            if ( is_wp_error( $response ) ) {
                $last_error = new WP_Error( 'api_request_error', $response->get_error_message() );
                continue;
            }
            $response_body = wp_remote_retrieve_body( $response );
            $result = json_decode( $response_body, true );
            if ( ! $result ) {
                $last_error = new WP_Error( 'api_response_parse_error', 'API 返回数据解析失败' );
                continue;
            }
            if ( isset( $result['choices'][0]['message']['content'] ) ) {
                return $result['choices'][0]['message']['content'];
            } else {
                $last_error = new WP_Error( 'api_invalid_format', 'API 返回格式不正确' );
                continue;
            }
        }
        return $last_error ? $last_error : new WP_Error( 'unknown_error', '未知错误' );
    }
    // 通义千问 接口
    elseif ( $selected_api === 'tongyi' ) {
        $api_key = isset( $options['tongyi_api_key'] ) ? $options['tongyi_api_key'] : '';
        $models = isset( $options['tongyi_model'] ) ? explode( ',', $options['tongyi_model'] ) : array('qwen-plus');
        $models = array_map('trim', $models);
        $endpoint = 'https://dashscope.aliyuncs.com/compatible-mode/v1/chat/completions';
        $headers = array(
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $api_key,
        );
        foreach ( $models as $model ) {
            $payload = array(
                'model'    => $model,
                'messages' => array(
                    array( 'role' => 'system', 'content' => 'You are a helpful assistant.' ),
                    array( 'role' => 'user', 'content' => $prompt ),
                ),
            );
            $args = array(
                'body'    => json_encode( $payload ),
                'headers' => $headers,
                'timeout' => 60,
            );
            $response = wp_remote_post( $endpoint, $args );
            if ( is_wp_error( $response ) ) {
                $last_error = new WP_Error( 'api_request_error', $response->get_error_message() );
                continue;
            }
            $response_body = wp_remote_retrieve_body( $response );
            $result = json_decode( $response_body, true );
            if ( ! $result ) {
                $last_error = new WP_Error( 'api_response_parse_error', 'API 返回数据解析失败' );
                continue;
            }
            if ( isset( $result['choices'][0]['message']['content'] ) ) {
                return $result['choices'][0]['message']['content'];
            } else {
                $last_error = new WP_Error( 'api_invalid_format', 'API 返回格式不正确' );
                continue;
            }
        }
        return $last_error ? $last_error : new WP_Error( 'unknown_error', '未知错误' );
    }
    // 豆包AI 接口
    elseif ( $selected_api === 'doubao' ) {
        $api_key = isset( $options['doubao_api_key'] ) ? $options['doubao_api_key'] : '';
        $models = isset( $options['doubao_model'] ) ? explode( ',', $options['doubao_model'] ) : array('');
        $models = array_map('trim', $models);
        $endpoint = 'https://ark.cn-beijing.volces.com/api/v3/chat/completions';
        $headers = array(
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $api_key,
        );
        foreach ( $models as $model ) {
            $payload = array(
                'model'    => $model,
                'messages' => array(
                    array( 'role' => 'system', 'content' => 'You are a helpful assistant.' ),
                    array( 'role' => 'user', 'content' => $prompt ),
                ),
            );
            $args = array(
                'body'    => json_encode( $payload ),
                'headers' => $headers,
                'timeout' => 60,
            );
            $response = wp_remote_post( $endpoint, $args );
            if ( is_wp_error( $response ) ) {
                $last_error = new WP_Error( 'api_request_error', $response->get_error_message() );
                continue;
            }
            $response_body = wp_remote_retrieve_body( $response );
            $result = json_decode( $response_body, true );
            if ( ! $result ) {
                $last_error = new WP_Error( 'api_response_parse_error', 'API 返回数据解析失败' );
                continue;
            }
            if ( isset( $result['choices'][0]['message']['content'] ) ) {
                return $result['choices'][0]['message']['content'];
            } else {
                $last_error = new WP_Error( 'api_invalid_format', 'API 返回格式不正确' );
                continue;
            }
        }
        return $last_error ? $last_error : new WP_Error( 'unknown_error', '未知错误' );
    } else {
        return new WP_Error( 'invalid_api', '未支持的 API 接口' );
    }
}


// 腾讯云TTS接口合成语音(官方API Explorer的代码示例)
function wpatai_call_tts_api( $text, $secret_id, $secret_key, $voice_type = 0 ) {
    // 腾讯TTS接口基本参数
    $service    = "tts";
    $host       = "tts.tencentcloudapi.com";
    $req_region = "";
    $version    = "2019-08-23";
    $action     = "TextToVoice";
    $endpoint   = "https://tts.tencentcloudapi.com";
    $algorithm  = "TC3-HMAC-SHA256";
    $timestamp  = time();
    $date       = gmdate("Y-m-d", $timestamp);
    
    // 构造 TTS 请求参数（示例中传递 Text 与其他默认参数，可根据腾讯文档调整）
    $payload_arr = array(
        "Text" => $text,
        "SessionId" => uniqid(),
        "ModelType" => 1,          // 模型类型，默认1
        "VoiceType" => (int)$voice_type, // 使用后台设置的腾讯云音库值
        "PrimaryLanguage" => 1,    // 语言类型（1 中文，2 英文）
        "Codec" => "mp3"
    );
    $payload = json_encode($payload_arr, JSON_UNESCAPED_UNICODE);
    
    // ************* 步骤 1：拼接规范请求串 *************
    $http_request_method   = "POST";
    $canonical_uri         = "/";
    $canonical_querystring = "";
    $ct                    = "application/json; charset=utf-8";
    $canonical_headers     = "content-type:".$ct."\nhost:".$host."\nx-tc-action:".strtolower($action)."\n";
    $signed_headers        = "content-type;host;x-tc-action";
    $hashed_request_payload= hash("sha256", $payload);
    $canonical_request     = "$http_request_method\n$canonical_uri\n$canonical_querystring\n$canonical_headers\n$signed_headers\n$hashed_request_payload";
    
    // ************* 步骤 2：拼接待签名字符串 *************
    $credential_scope       = "$date/$service/tc3_request";
    $hashed_canonical_request = hash("sha256", $canonical_request);
    $string_to_sign         = "$algorithm\n$timestamp\n$credential_scope\n$hashed_canonical_request";
    
    // ************* 步骤 3：计算签名 *************
    $secret_date    = wpatai_sign("TC3".$secret_key, $date);
    $secret_service = wpatai_sign($secret_date, $service);
    $secret_signing = wpatai_sign($secret_service, "tc3_request");
    $signature      = hash_hmac("sha256", $string_to_sign, $secret_signing);
    
    // ************* 步骤 4：拼接 Authorization *************
    $authorization  = "$algorithm Credential=$secret_id/$credential_scope, SignedHeaders=$signed_headers, Signature=$signature";
    
    // ************* 步骤 5：构造请求头并发起请求 *************
    $headers = array(
        "Authorization"   => $authorization,
        "Content-Type"    => $ct,
        "Host"            => $host,
        "X-TC-Action"     => $action,
        "X-TC-Timestamp"  => $timestamp,
        "X-TC-Version"    => $version,
    );
    if ( $req_region ) {
        $headers["X-TC-Region"] = $req_region;
    }
    
    $args = array(
        'body'    => $payload,
        'headers' => $headers,
        'timeout' => 60,
    );
    
    $response = wp_remote_post( $endpoint, $args );
    if ( is_wp_error( $response ) ) {
        return new WP_Error( 'tts_request_error', $response->get_error_message() );
    }
    $response_body = wp_remote_retrieve_body( $response );
    $result = json_decode( $response_body, true );
    if ( ! $result || ! isset( $result['Response'] ) ) {
        return new WP_Error( 'tts_response_error', '语音合成API返回数据解析失败' );
    }
    if ( isset( $result['Response']['Error'] ) ) {
        return new WP_Error( 'tts_api_error', '语音合成API错误: ' . $result['Response']['Error']['Message'] );
    }
    
    // 腾讯TTS返回的Audio字段为base64编码的音频数据
    if ( isset( $result['Response']['Audio'] ) ) {
        $audio_base64 = $result['Response']['Audio'];
        // 生成data URI格式的音频链接（mp3格式）
        $audio_data_uri = 'data:audio/mp3;base64,' . $audio_base64;
        return $audio_data_uri;
    } else {
        return new WP_Error( 'tts_invalid_response', '语音合成API返回格式不正确' );
    }
}

// 百度云TTS接口合成语音
function wpatai_call_baidu_tts_api( $text, $api_key, $secret_key, $per = 0 ) {
    // 获取access token
    $token = wpatai_get_baidu_access_token( $api_key, $secret_key );
    if ( is_wp_error( $token ) ) {
        return $token;
    }
    
    $cuid = wp_generate_password( 16, false ); // 生成一个随机字符串，60个字符以内即可
    
    $params = array(
        'tex' => $text,
        'tok' => $token,
        'cuid' => $cuid,
        'ctp' => '1',
        'lan' => 'zh',
        'spd' => '5',
        'pit' => '5',
        'vol' => '5',
        'per' => $per,  // 使用后台设置的百度云音库值
        'aue' => '3'
    );
    
    $url = "https://tsn.baidu.com/text2audio";
    $args = array(
        'body'        => http_build_query($params),
        'timeout'     => 60,
        'sslverify'   => false,
        'headers'     => array(
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Accept'       => '*/*'
        ),
    );
    
    $response = wp_remote_post( $url, $args );
    if ( is_wp_error( $response ) ) {
        return new WP_Error( 'baidu_tts_request_error', $response->get_error_message() );
    }
    
    // 百度接口若成功，直接返回音频二进制数据，否则返回JSON错误信息
    $content_type = wp_remote_retrieve_header( $response, 'content-type' );
    $body = wp_remote_retrieve_body( $response );
    if ( strpos($content_type, 'application/json') !== false ) {
        $result = json_decode($body, true);
        if ( isset($result['err_no']) && $result['err_no'] != 0 ) {
            return new WP_Error( 'baidu_tts_api_error', '百度语音合成错误: ' . $result['err_msg'] );
        }
        return new WP_Error( 'baidu_tts_invalid_response', '百度语音合成返回格式不正确' );
    } else {
        // 将返回的二进制音频数据转换为 base64 并生成 data URI
        $audio_data_uri = 'data:audio/mp3;base64,' . base64_encode($body);
        return $audio_data_uri;
    }
}

// 百度云API_KEY与SECRET_KEY获取Access Token
function wpatai_get_baidu_access_token( $api_key, $secret_key ) {
    $url = 'https://aip.baidubce.com/oauth/2.0/token';
    $post_data = array(
        'grant_type'    => 'client_credentials',
        'client_id'     => $api_key,
        'client_secret' => $secret_key
    );
    
    $args = array(
        'body'      => http_build_query($post_data),
        'timeout'   => 60,
        'sslverify' => false,
    );
    
    $response = wp_remote_post( $url, $args );
    if ( is_wp_error( $response ) ) {
        return new WP_Error( 'baidu_token_request_error', $response->get_error_message() );
    }
    
    $body = wp_remote_retrieve_body( $response );
    $result = json_decode( $body, true );
    if ( isset($result['access_token']) ) {
        return $result['access_token'];
    } else {
        return new WP_Error( 'baidu_token_error', '获取百度access token失败' );
    }
}

// 计算签名函数
function wpatai_sign($key, $msg) {
    return hash_hmac("sha256", $msg, $key, true);
}

// 对外公开的语音合成功能(其他插件可以调用语音朗读)
function wpatai_generate_tts_audio( $text, $interface = 'tencent' ) {
    $options = get_option( 'wpatai_settings' );

    // 确保只处理纯文本（排除HTML标签等）
    $text = wp_strip_all_tags( $text );

    if ( $interface === 'baidu' ) {
        $baidu_api_key    = isset( $options['baidu_api_key'] ) ? trim( $options['baidu_api_key'] ) : '';
        $baidu_secret_key = isset( $options['baidu_secret_key'] ) ? trim( $options['baidu_secret_key'] ) : '';
        $baidu_per        = isset( $options['baidu_per'] ) ? $options['baidu_per'] : 0;
        if ( empty( $baidu_api_key ) || empty( $baidu_secret_key ) ) {
            return new WP_Error( 'baidu_credentials_error', '百度云凭证未配置' );
        }
        return wpatai_call_baidu_tts_api( $text, $baidu_api_key, $baidu_secret_key, $baidu_per );
    } else {
        // 默认使用腾讯云接口
        $tencent_secret_id   = isset( $options['tencent_secret_id'] ) ? trim( $options['tencent_secret_id'] ) : '';
        $tencent_secret_key  = isset( $options['tencent_secret_key'] ) ? trim( $options['tencent_secret_key'] ) : '';
        $tencent_voice_type  = isset( $options['tencent_voice_type'] ) ? (int)$options['tencent_voice_type'] : 0;
        if ( empty( $tencent_secret_id ) || empty( $tencent_secret_key ) ) {
            return new WP_Error( 'tencent_credentials_error', '腾讯云凭证未配置' );
        }
        return wpatai_call_tts_api( $text, $tencent_secret_id, $tencent_secret_key, $tencent_voice_type );
    }
}

// 用于接收生成的音频URL
function wpatai_tts_generate_action( $text, $interface, $callback ) {
    $result = wpatai_generate_tts_audio( $text, $interface );
    if ( is_callable( $callback ) ) {
        call_user_func( $callback, $result );
    }
}
add_action( 'wpatai_tts_generate', 'wpatai_tts_generate_action', 10, 3 );

// 卸载插件的时候删掉设置项
function wpatai_delete_plugin_settings() {
    delete_option('wpatai_settings');
}
register_uninstall_hook(__FILE__, 'wpatai_delete_plugin_settings');

?>

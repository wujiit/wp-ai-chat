<?php
// 文章生成 开始
// 文章生成页面
function deepseek_render_article_generator_page() {
    $article_generator_nonce = wp_create_nonce('deepseek_article_generator_nonce');
    ?>
    <div class="wrap">
        <h1>文章生成</h1>
        <form method="post" action="" id="article-form">
            <p><strong>关键词(比如: 人工智能)：</strong></p>
            <input type="text" name="keyword" style="width: 500px;" />

            <p><strong>选择文章分类：</strong></p>
            <?php
            $categories = get_categories();
            if ($categories) {
                echo '<select name="category_id">';
                foreach ($categories as $category) {
                    echo '<option value="' . $category->term_id . '">' . $category->name . '</option>';
                }
                echo '</select>';
            }
            ?>

            <p><strong>文章标签：</strong></p>
            <input type="text" name="post_tags" id="post_tags" style="width: 500px;" placeholder="多个标签用英文逗号分隔，如：科技,AI,教程" />

            <p><strong>选择接口(模型需要支持长文本)：</strong></p>
            <?php
            $interface_choice = get_option('chat_interface_choice', 'deepseek');
            ?>
            <select name="interface_choice">
                <option value="deepseek" <?php selected($interface_choice, 'deepseek'); ?>>DeepSeek</option>
                <option value="doubao" <?php selected($interface_choice, 'doubao'); ?>>豆包AI</option>
                <option value="qwen" <?php selected($interface_choice, 'qwen'); ?>>通义千问</option>
                <option value="custom" <?php selected($interface_choice, 'custom'); ?>>自定义模型</option>
            </select>

            <p><strong>启用联网搜索（仅限通义千问qwen-max、qwen-plus、qwen-turbo模型）：</strong></p>
            <input type="checkbox" name="enable_search" id="enable_search" value="1" />

            <p><input type="button" value="生成文章" class="button-primary" id="generate-button" /></p>

            <div id="generation-status" style="display: none; color: #666;">正在生成中...</div>
            <div id="timeout-status" style="display: none; color: red;"></div> <!-- 显示错误信息 -->

            <p><strong>文章标题：</strong></p>
            <input type="text" name="post_title" id="post_title" value="" style="width: 50%;"/>

            <p><strong>文章内容：</strong></p>
            <?php
            wp_editor('', 'post_content', array('textarea_name' => 'post_content', 'textarea_rows' => 10));
            ?>

            <p><input type="submit" name="publish_article" value="发布文章" class="button-primary" id="publish-button" /></p>

            <div id="publish-result" style="display: none; margin-top: 10px;"></div>
        </form>
        生成的标题和内容还是需要自己再修改下，只适合纯文本内容的文章生成。
    </div>

    <script>
    document.getElementById('generate-button').addEventListener('click', function() {
        document.getElementById('generation-status').style.display = 'block';
        document.getElementById('timeout-status').style.display = 'none';

        var keyword = document.querySelector('input[name="keyword"]').value;
        var interface_choice = document.querySelector('select[name="interface_choice"]').value;
        var enable_search = document.querySelector('input[name="enable_search"]').checked ? 1 : 0;
        var nonce = '<?php echo esc_js($article_generator_nonce); ?>';

        var sseUrl = ajaxurl + '?action=generate_article_stream_ajax'
            + '&keyword=' + encodeURIComponent(keyword)
            + '&interface_choice=' + encodeURIComponent(interface_choice)
            + '&enable_search=' + encodeURIComponent(enable_search)
            + '&nonce=' + encodeURIComponent(nonce);

        if (typeof(EventSource) !== "undefined") {
            var eventSource = new EventSource(sseUrl);
            var articleContent = "";
            eventSource.onmessage = function(event) {
                try {
                    var data = JSON.parse(event.data);
                    if (data.error) {
                        // 如果后端返回错误，显示在timeout-status中
                        document.getElementById('timeout-status').innerText = data.error;
                        document.getElementById('timeout-status').style.display = 'block';
                        document.getElementById('generation-status').style.display = 'none';
                        eventSource.close();
                    } else if (data.content) {
                        articleContent += data.content;
                        var contentWithBr = articleContent.replace(/\n/g, '<br>');
                        if (tinymce.get('post_content')) {
                            tinymce.get('post_content').setContent(contentWithBr);
                        } else {
                            document.getElementById('post_content').value = contentWithBr;
                        }
                    }
                } catch (e) {
                    console.error("解析SSE数据错误", e);
                    document.getElementById('timeout-status').innerText = '数据解析错误，请重试。';
                    document.getElementById('timeout-status').style.display = 'block';
                    document.getElementById('generation-status').style.display = 'none';
                    eventSource.close();
                }
            };
            eventSource.addEventListener('done', function(event) {
                var lines = articleContent.split("\n");
                if (lines.length > 0) {
                    document.getElementById('post_title').value = lines[0];
                }
                document.getElementById('generation-status').style.display = 'none';
                eventSource.close();
            });
            eventSource.onerror = function(event) {
                console.error("SSE 连接错误", event);
                document.getElementById('timeout-status').innerText = '连接错误，请检查网络或接口配置。';
                document.getElementById('timeout-status').style.display = 'block';
                document.getElementById('generation-status').style.display = 'none';
                eventSource.close();
            };
        } else {
            document.getElementById('generation-status').style.display = 'none';
            document.getElementById('timeout-status').innerText = '您的浏览器不支持服务器发送事件 (SSE)，请更换浏览器。';
            document.getElementById('timeout-status').style.display = 'block';
        }
    });

    // 发布文章
    document.getElementById('publish-button').addEventListener('click', function(e) {
        e.preventDefault();
        var post_title = document.getElementById('post_title').value;
        var post_content = tinymce.get('post_content').getContent();
        var category_id = document.querySelector('select[name="category_id"]').value;
        var post_tags = document.getElementById('post_tags').value;

        var data = {
            action: 'publish_article_ajax',
            post_title: post_title,
            post_content: post_content,
            category_id: category_id,
            post_tags: post_tags,
            nonce: '<?php echo esc_js($article_generator_nonce); ?>'
        };

        jQuery.post(ajaxurl, data, function(response) {
            var resultDiv = document.getElementById('publish-result');
            if (response.success) {
                resultDiv.style.display = 'block';
                resultDiv.innerHTML = '<span style="color: green;">' + response.data.message + '</span>';
            } else {
                resultDiv.style.display = 'block';
                resultDiv.innerHTML = '<span style="color: red;">' + response.data.message + '</span>';
            }
        }).fail(function() {
            var resultDiv = document.getElementById('publish-result');
            resultDiv.style.display = 'block';
            resultDiv.innerHTML = '<span style="color: red;">发布文章失败，请重试。</span>';
        });
    });
    </script>
    <?php
}

// SSE流式文章生成处理函数
function deepseek_generate_article_stream_ajax() {
    // 检查是否有管理员权限
    if (!current_user_can('manage_options')) {
        echo "data: " . json_encode(['error' => '权限不足']) . "\n\n";
        flush();
        exit;
    }

    $nonce = isset($_GET['nonce']) ? sanitize_text_field(wp_unslash($_GET['nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'deepseek_article_generator_nonce')) {
        echo "data: " . json_encode(['error' => '验证请求失败']) . "\n\n";
        flush();
        exit;
    }

    ignore_user_abort(true);
    set_time_limit(0);
    if (ob_get_length()) ob_end_clean();

    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('X-Accel-Buffering: no');
    header('Content-Encoding: none');

    while (ob_get_level()) ob_end_flush();
    ob_implicit_flush(true);

    $keyword = isset($_GET['keyword']) ? sanitize_text_field(wp_unslash($_GET['keyword'])) : '';
    $interface_choice = isset($_GET['interface_choice']) ? sanitize_text_field(wp_unslash($_GET['interface_choice'])) : '';
    $enable_search = isset($_GET['enable_search']) ? intval($_GET['enable_search']) : 0;

    if (empty($keyword) || empty($interface_choice)) {
        echo "data: " . json_encode(['error' => '缺少必要参数']) . "\n\n";
        flush();
        exit;
    }

    $api_key = get_option($interface_choice . '_api_key');
    $model_string = ($interface_choice === 'custom') ? get_option('custom_model_params') : 
                    ($interface_choice === 'qwen' ? get_option('qwen_text_model') : get_option($interface_choice . '_model'));

    if ($interface_choice === 'deepseek') {
        $url = 'https://api.deepseek.com/chat/completions';
        $default_model = 'deepseek-chat';
    } elseif ($interface_choice === 'doubao') {
        $url = 'https://ark.cn-beijing.volces.com/api/v3/chat/completions';
        $default_model = '';
    } elseif ($interface_choice === 'qwen') {
        $url = 'https://dashscope.aliyuncs.com/compatible-mode/v1/chat/completions';
        $default_model = 'qwen-max';
    } elseif ($interface_choice === 'custom') {
        $url = get_option('custom_model_url');
        $default_model = '';
    } else {
        echo "data: " . json_encode(['error' => '不支持的接口']) . "\n\n";
        flush();
        exit;
    }

    // 处理多模型参数，取第一个模型
    $model_list = array_filter(array_map('trim', explode(',', $model_string)));
    $model = !empty($model_list) ? $model_list[0] : $default_model;

    if (empty($api_key) || empty($model) || empty($url)) {
        echo "data: " . json_encode(['error' => '接口配置缺失 - API Key: ' . ($api_key ? '已设置' : '未设置') . 
                                     ', Model: ' . ($model ? $model : '未设置') . 
                                     ', URL: ' . ($url ? $url : '未设置')]) . "\n\n";
        flush();
        exit;
    }

    // 检查联网搜索条件
    $supported_qwen_models = ['qwen-max', 'qwen-plus', 'qwen-turbo'];
    $is_qwen_search_supported = ($interface_choice === 'qwen' && in_array($model, $supported_qwen_models));
    
    if ($enable_search && !$is_qwen_search_supported) {
        echo "data: " . json_encode(['error' => '联网搜索仅支持通义千问的qwen-max、qwen-plus、qwen-turbo 模型']) . "\n\n";
        flush();
        exit;
    }

    // 根据是否启用联网搜索设置提示词
    $prompt = $enable_search && $is_qwen_search_supported
        ? "请根据关键词 '{$keyword}' 进行实时全网联网搜索，获取今天最新的资料、数据或资讯报道。确保搜索结果是最新的，并且基于这些最新信息撰写一篇相关文章。文章标题应简洁明了，直接反映文章的核心内容。文章内容应结构清晰，逻辑严谨，包含必要的背景信息、最新动态、数据分析或专家观点等。确保文章内容准确、权威，并注明信息来源。文章行首不要带*号或多个#号，也不要带Markdown格式符号。请务必在撰写文章前完成实时联网搜索，以确保内容基于最新资料。"
        : "根据关键词 '{$keyword}' 写一篇文章，包括标题和内容。要求：1. 标题简洁且与关键词相关；2. 内容逻辑清晰，围绕关键词展开，语言流畅；3. 行首不要使用Markdown符号，也不要带特殊符号；4. 文章结构完整，段落分明。";

    $payload = [
        'model' => $model,
        'messages' => [
            ['role' => 'system', 'content' => 'You are a helpful assistant.'],
            ['role' => 'user', 'content' => $prompt]
        ],
        'stream' => true,
    ];

    // 如果是通义千问且启用联网搜索，添加enable_search参数
    if ($is_qwen_search_supported && $enable_search) {
        $payload['enable_search'] = true;
    }

    $headers = [
        'Authorization: Bearer ' . $api_key,
        'Content-Type: application/json',
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);

    curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $chunk) {
        $lines = explode("\n", $chunk);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            if (strpos($line, 'data: [DONE]') !== false) {
                echo "event: done\n";
                echo "data: " . json_encode(['message' => '流结束']) . "\n\n";
                flush();
                continue;
            }
            if (strpos($line, 'data:') === 0) {
                $jsonStr = trim(substr($line, 5));
                if (!empty($jsonStr)) {
                    $data = json_decode($jsonStr, true);
                    if (isset($data['choices'][0]['delta']['content'])) {
                        $content = $data['choices'][0]['delta']['content'];
                        echo "data: " . json_encode(['content' => $content]) . "\n\n";
                        flush();
                    }
                }
            }
        }
        return strlen($chunk);
    });

    curl_exec($ch);
    if (curl_errno($ch)) {
        echo "data: " . json_encode(['error' => curl_error($ch)]) . "\n\n";
        flush();
    }
    curl_close($ch);
    exit;
}
add_action('wp_ajax_generate_article_stream_ajax', 'deepseek_generate_article_stream_ajax');

// 发布文章的AJAX处理函数
function deepseek_publish_article_ajax() {
    // 检查是否有管理员权限
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => '权限不足'));
        exit;
    }

    check_ajax_referer('deepseek_article_generator_nonce', 'nonce');

    // 获取请求参数
    $post_title = isset($_POST['post_title']) ? sanitize_text_field(wp_unslash($_POST['post_title'])) : '';
    $post_content = isset($_POST['post_content']) ? wp_kses_post(wp_unslash($_POST['post_content'])) : '';
    $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
    $post_tags = isset($_POST['post_tags']) ? sanitize_text_field(wp_unslash($_POST['post_tags'])) : '';

    // 创建新的文章
    $post_data = array(
        'post_title'    => $post_title,
        'post_content'  => $post_content,
        'post_status'   => 'publish',
        'post_category' => array($category_id),
        'post_author'   => get_current_user_id(),
    );

    // 插入文章
    $post_id = wp_insert_post($post_data);

    if ($post_id) {
        // 处理标签
        if (!empty($post_tags)) {
            $tags_array = array_map('trim', explode(',', $post_tags));
            wp_set_post_tags($post_id, $tags_array, true);
        }
        wp_send_json_success(array('message' => '文章已成功发布!', 'post_id' => $post_id));
    } else {
        wp_send_json_error(array('message' => '文章发布失败'));
    }

    wp_die();
}
add_action('wp_ajax_publish_article_ajax', 'deepseek_publish_article_ajax');
// 文章生成 结束


// 文章分析 开始
// 在经典编辑器下添加文章分析板块
function deepseek_add_article_analysis_meta_box() {
    if (get_option('enable_article_analysis', '0') !== '1') {
        return; // 未启用则不添加
    }

    // 仅在经典编辑器中添加
    global $current_screen;
    if (isset($current_screen) && $current_screen->is_block_editor()) {
        return; // Gutenberg 编辑器不显示
    }

    add_meta_box(
        'deepseek_article_analysis',
        '文章AI分析',
        'deepseek_article_analysis_meta_box_callback',
        'post',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'deepseek_add_article_analysis_meta_box');

// 文章分析板块内容
function deepseek_article_analysis_meta_box_callback($post) {
    wp_nonce_field('deepseek_article_analysis_nonce', 'deepseek_article_analysis_nonce');
    $interface = get_option('article_analysis_interface', 'deepseek');
    ?>
    <div id="article-analysis-section">
        <p>
            <label for="analysis_interface"><strong>选择AI接口：</strong></label>
            <select id="analysis_interface" name="analysis_interface">
                <?php
                $options = array(
                    'deepseek' => 'DeepSeek',
                    'openai' => 'OpenAI',
                    'qwen' => '通义千问',
                    'kimi' => 'Kimi',
                    'doubao' => '豆包AI',
                    'custom' => '自定义接口'
                );
                foreach ($options as $value => $label) {
                    echo '<option value="' . esc_attr($value) . '" ' . selected($interface, $value, false) . '>' . esc_html($label) . '</option>';
                }
                ?>
            </select>
            <button type="button" id="analyze-article-btn" class="button button-primary">分析文章</button>
        </p>
        <div id="analysis-result" style="margin-top: 20px;">
            <p><strong>推荐标题：</strong><span id="recommended-title"></span></p>
            <p><strong>推荐描述：</strong><span id="seo-description"></span></p>
            <p><strong>错别字检测：</strong><span id="typo-detection"></span></p>
        </div>
        <div id="analysis-loading" style="display: none;">分析中，请稍候...</div>
        <div id="analysis-error" style="color: red; display: none;"></div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        $('#analyze-article-btn').on('click', function() {
            var postTitle = $('#title').val();
            var postContent = $('#content').val() || tinyMCE.get('content').getContent({format: 'text'});

            if (!postTitle || !postContent) {
                alert('请先输入文章标题和内容！');
                return;
            }

            $('#analysis-loading').show();
            $('#analysis-error').hide();
            $('#recommended-title, #seo-description, #typo-detection').empty();

            var data = {
                action: 'deepseek_analyze_article',
                nonce: $('#deepseek_article_analysis_nonce').val(),
                title: postTitle,
                content: postContent,
                interface: $('#analysis_interface').val()
            };

            $.post(ajaxurl, data, function(response) {
                $('#analysis-loading').hide();
                if (response.success) {
                    $('#recommended-title').text(response.data.recommended_title || '无推荐');
                    $('#seo-description').text(response.data.seo_description || '无推荐');
                    $('#typo-detection').text(response.data.typos.length > 0 ? response.data.typos.join(', ') : '未检测到错别字');
                } else {
                    $('#analysis-error').text(response.data.message || '分析失败，请重试。').show();
                }
            }).fail(function() {
                $('#analysis-loading').hide();
                $('#analysis-error').text('请求失败，请检查网络或接口配置。').show();
            });
        });
    });
    </script>
    <?php
}

// AJAX 处理文章分析
function deepseek_analyze_article() {
    if (!current_user_can('edit_posts')) {
        wp_send_json_error(array('message' => '权限不足'), 403);
    }

    check_ajax_referer('deepseek_article_analysis_nonce', 'nonce');

    $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
    $content = isset($_POST['content']) ? sanitize_text_field(wp_unslash($_POST['content'])) : '';
    $interface = isset($_POST['interface']) ? sanitize_text_field(wp_unslash($_POST['interface'])) : '';

    if (empty($title) || empty($content)) {
        wp_send_json_error(array('message' => '标题或内容为空'));
    }

    $api_key = get_option($interface . '_api_key');
    $model_string = ($interface === 'custom') ? get_option('custom_model_params') : 
                    ($interface === 'qwen' ? get_option('qwen_text_model') : get_option($interface . '_model'));

    if ($interface === 'deepseek') {
        $url = 'https://api.deepseek.com/chat/completions';
        $default_model = 'deepseek-chat';
    } elseif ($interface === 'openai') {
        $url = 'https://api.openai.com/v1/chat/completions';
        $default_model = 'gpt-3.5-turbo';
    } elseif ($interface === 'qwen') {
        $url = 'https://dashscope.aliyuncs.com/compatible-mode/v1/chat/completions';
        $default_model = 'qwen-max';
    } elseif ($interface === 'kimi') {
        $url = 'https://api.moonshot.ai/v1/chat/completions';
        $default_model = 'moonshot-v1-8k';
    } elseif ($interface === 'doubao') {
        $url = 'https://ark.cn-beijing.volces.com/api/v3/chat/completions';
        $default_model = '';
    } elseif ($interface === 'custom') {
        $url = get_option('custom_model_url');
        $default_model = '';
    } else {
        wp_send_json_error(array('message' => '不支持的接口'));
    }

    $model_list = array_filter(array_map('trim', explode(',', $model_string)));
    $model = !empty($model_list) ? $model_list[0] : $default_model;

    if (empty($api_key) || empty($model) || empty($url)) {
        wp_send_json_error(array('message' => '接口配置缺失'));
    }

    // 修改提示词，严格要求指定格式
    $prompt = "请分析以下文章标题和内容，并提供以下信息：\n1. 根据内容推荐一个更合适的标题（简洁且吸引人）；\n2. 根据内容生成一个100-150字符的SEO描述；\n3. 检测内容中可能存在的错别字并列出（如果没有则返回‘无’）。\n\n标题：{$title}\n内容：{$content}\n\n严格按照以下格式返回结果，不得包含多余符号或Markdown标记（如```json）：\n推荐标题：你的推荐标题\nSEO描述：你的SEO描述（100-150字符）\n错别字检测：错别字1,错别字2 或 无";

    $payload = array(
        'model' => $model,
        'messages' => array(
            array('role' => 'system', 'content' => 'You are a helpful assistant skilled in content analysis.'),
            array('role' => 'user', 'content' => $prompt)
        ),
        'temperature' => 0.7,
        'max_tokens' => 1000
    );

    $headers = array(
        'Authorization: Bearer ' . $api_key,
        'Content-Type: application/json'
    );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        wp_send_json_error(array('message' => 'API请求失败：' . curl_error($ch)));
    }
    curl_close($ch);

    $data = json_decode($response, true);
    if (!isset($data['choices'][0]['message']['content'])) {
        wp_send_json_error(array('message' => 'API返回数据异常'));
    }

    $result_text = $data['choices'][0]['message']['content'];

    // 解析返回的文本
    $lines = explode("\n", trim($result_text));
    $result = array(
        'recommended_title' => '',
        'seo_description' => '',
        'typos' => array()
    );

    foreach ($lines as $line) {
        if (strpos($line, '推荐标题：') === 0) {
            $result['recommended_title'] = trim(substr($line, strlen('推荐标题：')));
        } elseif (strpos($line, 'SEO描述：') === 0) {
            $result['seo_description'] = trim(substr($line, strlen('SEO描述：')));
        } elseif (strpos($line, '错别字检测：') === 0) {
            $typos_str = trim(substr($line, strlen('错别字检测：')));
            if ($typos_str === '无') {
                $result['typos'] = array();
            } else {
                $result['typos'] = array_filter(array_map('trim', explode(',', $typos_str)));
            }
        }
    }

    // 验证解析结果是否完整
    if (empty($result['recommended_title']) || empty($result['seo_description'])) {
        wp_send_json_error(array('message' => '分析结果格式不完整'));
    }

    wp_send_json_success($result);
}
add_action('wp_ajax_deepseek_analyze_article', 'deepseek_analyze_article');
// 文章分析 结束

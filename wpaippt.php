<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // 防止直接访问
}


// 创建页面
function docmee_create_ppt_page() {
    // 查找是否已有包含短代码 [docmee_ppt] 的页面
    $args = array(
        'post_type'   => 'page',
        'post_status' => 'publish',
        'posts_per_page' => 1,
        's'           => '[docmee_ppt]'  // 搜索是否包含短代码
    );
    
    $query = new WP_Query($args);
    
    if (!$query->have_posts()) {
        // 页面不存在，插入新页面
        wp_insert_post(array(
            'post_title'    => 'AIPPT生成',
            'post_content'  => '[docmee_ppt]',
            'post_status'   => 'publish',
            'post_author'   => 1,
            'post_type'     => 'page',
        ));
    }
    
    wp_reset_postdata();
}


// 注册设置项
add_action('admin_init', 'docmee_register_settings');
function docmee_register_settings() {
    register_setting('docmee_options', 'docmee_api_key'); // apikey
    register_setting('docmee_options', 'docmee_token_limit');  // 限制生成次数
    register_setting('docmee_options', 'docmee_container_width'); // 宽度设置
    register_setting('docmee_options', 'docmee_ppt_height'); // 高度设置
    register_setting('docmee_options', 'docmee_vip_check_enabled', 'intval'); //会员开通设置 
    register_setting('docmee_options', 'docmee_vip_prompt_page'); //会员开通页面  
    register_setting('docmee_options', 'docmee_vip_keyword'); // 会员关键词设置    
}

// 设置页面
function wpaippt_settings_page() {
    ?>
    <div class="ai-wrap">
        <h2>AI生成API设置</h2>
        <form method="post" action="options.php">
            <?php settings_fields('docmee_options'); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">文多多API Key</th>
                    <td>
                        <input type="text" name="docmee_api_key" 
                               value="<?php echo esc_attr(get_option('docmee_api_key')); ?>" 
                               style="width:400px;"/>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">每个Token的最大生成次数</th>
                    <td>
                        <input type="number" name="docmee_token_limit" 
                               value="<?php echo esc_attr(get_option('docmee_token_limit', 10)); ?>" 
                               min="1" max="100"/>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">整体容器宽度</th>
                    <td>
                        <input type="text" name="docmee_container_width" 
                               value="<?php echo esc_attr(get_option('docmee_container_width', '80%')); ?>" 
                               style="width:100px;"/>
                        <span class="description">例如: 80%、1200px</span>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">PPT容器高度</th>
                    <td>
                        <input type="number" name="docmee_ppt_height" 
                               value="<?php echo esc_attr(get_option('docmee_ppt_height', 800)); ?>" 
                               min="100"/>
                        <span class="description">例如: 800px</span>
                    </td>
                </tr>
                <!-- 会员验证设置 -->
                <tr valign="top">
                    <th scope="row">启用网站会员验证</th>
                    <td>
                        <input type="checkbox" name="docmee_vip_check_enabled" 
                            value="1" <?php checked(1, get_option('docmee_vip_check_enabled'), true); ?>/>
                        <span class="description">启用后未开通网站会员的用户将无法使用aippt功能</span>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">会员验证关键词</th>
                    <td>
                        <input type="text" name="docmee_vip_keyword" value="<?php echo esc_attr(get_option('docmee_vip_keyword', '升级VIP享受精彩下载')); ?>" style="width:400px;"/>
                        <span class="description">设置会员验证时的提示关键词</span>
                    </td>
                </tr>

                <tr valign="top">
                    <th scope="row">开通会员页面链接</th>
                    <td>
                        <input type="text" name="docmee_vip_prompt_page" 
                            value="<?php echo esc_attr(get_option('docmee_vip_prompt_page')); ?>" 
                            style="width:400px;"/>
                        <span class="description">用户点击开通按钮后跳转的页面URL</span>
                    </td>
                </tr>                                
            </table>
            <?php submit_button(); ?>
        </form>
        <div class="success-message"></div>
        <p>1、会自动创建一个前台页面，如果没有创建，就手动创建，短代码: [docmee_ppt] <br>
            2、文多多AiPPT开放平台: <a href="https://docmee.cn?source=u70533" target="_blank">https://docmee.cn/open-platform</a> <br>
            3、文多多的单价较低，加上有UI接入方式，不用自己写前端，方便接入。<br>
            4、很多WordPress网站都有自己的付费会员系统，可以通过会员和非会员特有关键词来判断会员权限。<br>
        5、如果你不用这个功能，可以去把自动创建的页面删掉，有问题可以进QQ群: 16966111</p>
    </div>

    <script type="text/javascript">
    jQuery(document).ready(function($) {
        // 监听表单提交
        $('.ai-wrap form').on('submit', function(e) {
            e.preventDefault();

            // 模拟表单提交
            $.ajax({
                url: $(this).attr('action'),
                type: 'POST',
                data: $(this).serialize(),
                success: function(response) {
                    // 显示成功消息
                    $('.ai-wrap .success-message').text('保存成功').fadeIn();
                    // 2秒后隐藏消息
                    setTimeout(function() {
                        $('.ai-wrap .success-message').fadeOut();
                    }, 2000);
                }
            });
        });
    });
    </script>
    <?php
}

// 会员验证功能实现
add_action('template_redirect', 'docmee_start_output_buffer');
function docmee_start_output_buffer() {
    if (is_admin()) return;

    $vip_check_enabled = get_option('docmee_vip_check_enabled');
    if (!$vip_check_enabled) return;

    global $post;
    if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'docmee_ppt') && is_user_logged_in()) {
        ob_start();
        add_action('shutdown', 'docmee_check_vip_prompt', 0);
    }
}

function docmee_check_vip_prompt() {
    $html = ob_get_clean();

    $target_string = get_option('docmee_vip_keyword', '升级VIP享受精彩下载');  // 默认值为 "升级VIP享受精彩下载" Modown主题是这个
    $vip_prompt_page = get_option('docmee_vip_prompt_page');

    if (strpos($html, $target_string) !== false && !empty($vip_prompt_page)) {
        $prompt = '
        <div id="vip-prompt-overlay" style="position:fixed;top:0;left:0;width:100%;height:100%;z-index:99998;backdrop-filter:blur(3px);">
            <div id="vip-prompt" style="position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:#fff;padding:30px 40px;border-radius:12px;box-shadow:0 8px 30px rgba(0,0,0,0.2);text-align:center;z-index:99999;min-width:380px;">
                <h3 style="margin:0 0 20px 0;font-size:20px;color:#333;">&#128274; 会员专属功能</h3>
                <p style="margin:0 0 25px 0;font-size:16px;color:#666;">请先开通会员才能使用ai生成PPT服务</p>
                <div style="display:flex;gap:15px;justify-content:center;">
                    <button onclick="handleVipAction(\'confirm\')" style="padding:12px 30px;background:#0073aa;color:#fff;border:none;border-radius:25px;cursor:pointer;font-size:16px;transition:all 0.3s;flex:1;">
                        &#128640; 立即开通
                    </button>
                </div>
            </div>
        </div>
        <script>
            // 禁止滚动条出现
            document.body.style.overflow = "hidden";
            
            // 统一操作处理
            function handleVipAction(type) {
                const overlay = document.getElementById("vip-prompt-overlay");
                if (type === "confirm") {
                    window.location.href = "'.esc_url($vip_prompt_page).'";
                } else {
                    overlay.style.display = "none";
                    document.body.style.overflow = "auto";
                }
            }

            // 禁用ESC键关闭
            document.addEventListener("keydown", function(e) {
                if (e.key === "Escape") {
                    e.preventDefault();
                }
            });

            // 禁用右键菜单
            document.addEventListener("contextmenu", function(e) {
                e.preventDefault();
            }, false);
        </script>';
        
        $html = str_replace('</body>', $prompt.'</body>', $html);
    }
    echo $html;
}

// 加载未登录提示的动画CSS
function docmee_custom_login_message_styles() {
    $post = get_post();
    
    if ($post && has_shortcode($post->post_content, 'docmee_ppt')) {
        ?>
        <style>
        #vip-prompt-overlay {
            transition: opacity 0.3s ease;
        }
        #vip-prompt {
            animation: popIn 0.4s cubic-bezier(0.18, 0.89, 0.32, 1.28);
        }
        @keyframes popIn {
            0% { transform: translate(-50%, -50%) scale(0.8); opacity: 0; }
            100% { transform: translate(-50%, -50%) scale(1); opacity: 1; }
        }
        </style>
        <?php
    }
}
add_action('wp_head', 'docmee_custom_login_message_styles');


// 短码处理
add_shortcode('docmee_ppt', 'docmee_ppt_shortcode');
function docmee_ppt_shortcode() {

    // 生成nonce
    $nonce = wp_create_nonce('docmee_generate_token_nonce');

    // 获取设置的宽度和高度
    $container_width = get_option('docmee_container_width', '80%');
    $ppt_height = get_option('docmee_ppt_height', 800);

    // 获取设备类型
    $is_mobile = (wp_is_mobile()) ? 'true' : 'false';

    // 完整样式输出
    ob_start(); ?>
    <?php if (!is_user_logged_in()): // 添加登录提示层 ?>
    <div id="vip-prompt-overlay" style="position:fixed;top:0;left:0;width:100%;height:100%;z-index:99998;backdrop-filter:blur(3px);">
        <div id="vip-prompt" style="position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:#fff;padding:30px 40px;border-radius:12px;box-shadow:0 8px 30px rgba(0,0,0,0.2);text-align:center;z-index:99999;min-width:380px;">
            <h3 style="margin:0 0 20px 0;font-size:20px;color:#333;">🔒 登录后使用</h3>
            <p style="margin:0 0 25px 0;font-size:16px;color:#666;">请先登录才能使用AI生成PPT服务</p>
            <div style="display:flex;gap:15px;justify-content:center;">
                <button onclick="handleLoginAction('confirm')" style="padding:12px 30px;background:#0073aa;color:#fff;border:none;border-radius:25px;cursor:pointer;font-size:16px;transition:all 0.3s;flex:1;">
                    ⚡ 立即登录
                </button>
            </div>
        </div>
    </div>
    <script>
        // 登录操作处理
        function handleLoginAction(type) {
            const overlay = document.getElementById("vip-prompt-overlay");
            if (type === "confirm") {
                window.location.href = "<?php echo wp_login_url(get_permalink()); ?>";
            } else {
                overlay.style.display = "none";
                document.body.style.overflow = "auto";
            }
        }
    </script>
    <?php endif; ?>

    <div class="docmee-container-wrapper" style="width: <?php echo esc_attr($container_width); ?>;">
        <!-- 导航 -->
        <div class="page_navigate">
            <div id="page_creator" class="selected">生成PPT</div>
            <div id="page_dashboard">PPT列表</div>
            <div id="page_customTemplate">自定义模板</div>
        </div>
        <div id="message-box" style="display:none; padding: 10px; margin-top: 20px; background-color: #f57bb0; color: white; border-radius: 5px; text-align: center;"></div>
        
        <!-- 主容器 -->
        <div id="docmee-ppt-container" style="height: <?php echo esc_attr($ppt_height); ?>px;">
            <?php if (!is_user_logged_in()): ?>
                <div style="position:absolute;top:0;left:0;width:100%;height:100%;backdrop-filter:blur(2px);z-index:9997;"></div>
            <?php endif; ?>
        </div>
    </div>
<style>        
    .docmee-container-wrapper {
        margin: 20px auto;
        padding: 10px;
        background: #fff;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    .page_navigate {
        display: flex;
        justify-content: center;
        margin-bottom: 10px;
    }

    .page_navigate > div {
        padding: 5px 20px;
        margin-right: 12px;
        cursor: pointer;
        border: 2px solid transparent;
        border-radius: 50px;
        font-size: 16px;
        font-weight: 600;
        color: #fff;
        background: linear-gradient(145deg, #4c3c8b, #3a5b91);
        box-shadow: 2px 2px 8px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
    }

    .page_navigate > div:hover {
        background: linear-gradient(145deg, #6a5acd, #4682b4);
        border-color: #4682b4;
        transform: translateY(-3px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.15);
    }

    .page_navigate > div:active {
        transform: translateY(1px);
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    }

    .page_navigate .selected {
        background: linear-gradient(145deg, #6a5acd, #4682b4);
        color: white;
        border-color: #4682b4;
    }

    #docmee-ppt-container {
        position: relative;
        width: 100%;
        overflow: hidden;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        background: linear-gradient(-157deg, #4c3c8b, #3a5b91);
    }

    #docmee-iframe {
        width: 100%;
        height: 100%;
        border: none;
    }

    @media (max-width: 768px) {
        .docmee-container-wrapper {
            width: 100%;
            padding: 5px;
        }
        #docmee-ppt-container {
            height: 400px;
        }
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    window.docmeeUIInstance = null;
    const currentUser = <?php echo get_current_user_id(); ?>;
    const isMobile = <?php echo json_encode($is_mobile); ?>; // 设备类型参数
    const nonce = '<?php echo $nonce; ?>';  // 获取nonce

    function refreshToken() {
        return fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=generate_docmee_token&uid=${currentUser}&nonce=${nonce}`
        }).then(r => r.json());
    }

    async function initDocmeeUI(token) {
        return new Promise((resolve) => {
            const container = document.querySelector('#docmee-ppt-container');
            window.docmeeUIInstance = new DocmeeUI({
                pptId: null,
                token: token,
                container: container,
                page: 'creator',
                lang: 'zh',
                mode: 'light',
                hidePdfWatermark:true,
                isMobile: (isMobile === 'true'), // 根据设备类型设置isMobile
                //isMobile: window.innerWidth < 768,
                background: 'linear-gradient(-157deg,#f57bb0, #867dea)',
                padding: '40px 20px 0px',

                onMessage(message) {
                    console.log('[Docmee Message]', message);
                    switch(message.type) {
                        case 'invalid-token':
                            refreshToken().then(res => {
                                if(res.success) {
                                    docmeeUIInstance.updateToken(res.data.token);
                                } else {
                                    showMessage('Token认证错误: ' + res.message);
                                }
                            });
                            break;

                        case 'beforeGenerate':
                            return handleBeforeGenerate(message);

                        case 'beforeCreateCustomTemplate':
                            return handleCustomTemplate(message);

                        case 'pageChange':
                            updatePageState(message.data.page);
                            break;

                        case 'beforeDownload':
                            return `PPT_${message.data.subject}.pptx`;

                        case 'error':
                            handleError(message.data);
                            break;
                    }
                }
            });

            resolve(docmeeUIInstance);
        });
    }

    function updatePageState(page) {
        document.querySelectorAll('.page_navigate > div').forEach(el => {
            el.classList.remove('selected');
            if (el.id === `page_${page}`) {
                el.classList.add('selected');
            }
        });
    }

    function showMessage(message) {
        const messageBox = document.getElementById('message-box');
        messageBox.innerText = message;
        messageBox.style.display = 'block';
            setTimeout(() => {
                messageBox.style.display = 'none';
                }, 3000); // 3秒后自动隐藏
        }

    function handleBeforeGenerate(message) {
        const { subtype, fields } = message.data;
        if (subtype === 'outline') {
            console.log('即将生成PPT大纲:', fields);
            return true;
        } else if (subtype === 'ppt') {
            console.log('即将生成PPT:', fields);
            showMessage('正在生成PPT，请稍等...');
            return true;
        }
    }

    function handleCustomTemplate(message) {
        const { file, totalPptCount } = message.data;
        if (totalPptCount < 2) {
            showMessage('您的生成次数不足，无法创建自定义模板');
            return false;
        }
        return true;
    }

    function handleError(errorData) {
        let errorMessage = '';
        switch (errorData.code) {
            case 88:
                errorMessage = '您的PPT生成次数已用完，请联系管理员或购买更多次数。';
                break;
            case 100:
                errorMessage = 'Token认证错误，请重新登录或获取新的API Token。';
                break;
            case 403:
                errorMessage = '访问被拒绝，您的权限不足。';
                break;
            default:
                errorMessage = '发生未知错误: ' + errorData.message;
                break;
        }
        showMessage(errorMessage);
    }

    function loadSDK() {
        return new Promise((resolve) => {
            if (typeof DocmeeUI !== 'undefined') return resolve();
            const script = document.createElement('script');
            script.src = '<?php echo plugins_url('docmee-ui-sdk-iframe.min.js', __FILE__); ?>';
            script.onload = resolve;
            document.head.appendChild(script);
        });
    }

    async function mainInit() {
        try {
            await loadSDK();
            const tokenRes = await refreshToken();
            if (!tokenRes.success) throw new Error(tokenRes.data);

            await initDocmeeUI(tokenRes.data.token);
        } catch (e) {
            console.error('初始化失败:', e);
            showMessage('PPT服务初始化失败，请刷新页面重试');
        }
    }

    mainInit();


// 在监听器中处理
document.querySelector('#page_creator').addEventListener('click', () => {
    if (window.docmeeUIInstance) {
        window.docmeeUIInstance.navigate({ page: 'creator' });
        updatePageState('creator');
    }
});
document.querySelector('#page_dashboard').addEventListener('click', () => {
    if (window.docmeeUIInstance) {
        window.docmeeUIInstance.navigate({ page: 'dashboard' });
        updatePageState('dashboard');
    }
});
document.querySelector('#page_customTemplate').addEventListener('click', () => {
    if (window.docmeeUIInstance) {
        window.docmeeUIInstance.navigate({ page: 'customTemplate' });
        updatePageState('customTemplate');
    }
});
});
</script>


<?php
    return ob_get_clean();
}

// AJAX处理生成Token
add_action('wp_ajax_generate_docmee_token', 'generate_docmee_token');
function generate_docmee_token() {
    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'docmee_generate_token_nonce')) {
        wp_send_json_error(['message' => '无效的请求', 'code' => 403]);
    }
        
    if (!is_user_logged_in() || !current_user_can('manage_options')) {
        wp_send_json_error(['message' => '权限不足', 'code' => 403]);
    }

    $api_key = get_option('docmee_api_key');
    $uid = get_current_user_id();
    $limit = get_option('docmee_token_limit', 10);  // 获取设置中的最大生成次数

    $response = wp_remote_post('https://docmee.cn/api/user/createApiToken', [
        'headers' => [
            'Content-Type' => 'application/json',
            'Api-Key' => $api_key
        ],
        'body' => json_encode([
            'uid' => strval($uid),
            'limit' => $limit  // 在请求中添加limit
        ])
    ]);

    if(is_wp_error($response)) {
        wp_send_json_error(['message' => '网络异常: ' . $response->get_error_message(), 'code' => 500]);
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);

    if($body['code'] !== 0) {
        wp_send_json_error(['message' => '创建Token异常: ' . $body['message'], 'code' => 400]);
    }

    wp_send_json_success([
        'token' => $body['data']['token'],
        'expire' => $body['data']['expireTime']
    ]);
}

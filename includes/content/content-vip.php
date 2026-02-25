<?php
add_action('template_redirect', 'deepseek_start_output_buffer');
function deepseek_start_output_buffer() {
    if (is_admin()) return;

    $deepseek_vip_check_enabled = get_option('deepseek_vip_check_enabled');
    if (!$deepseek_vip_check_enabled) return;

    global $post;
    // 只在页面类型执行
    if (is_page() && is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'deepseek_chat') && is_user_logged_in()) {
        ob_start();
        add_action('shutdown', 'deepseek_check_vip_prompt', 0);
    }
}

// 会员验证弹窗
function deepseek_check_vip_prompt() {
    $deepseekhtml = ob_get_clean();

    $deepseek_target_string = get_option('deepseek_vip_keyword', '升级VIP享受精彩下载');  // 默认值为 "升级VIP享受精彩下载" Modown主题是这个
    $deepseek_vip_prompt_page = get_option('deepseek_vip_prompt_page');

    if (strpos($deepseekhtml, $deepseek_target_string) !== false && !empty($deepseek_vip_prompt_page)) {
        $deepseekprompt = '
        <div id="vip-prompt-overlay" style="position:fixed;top:0;left:0;width:100%;height:100%;z-index:99998;backdrop-filter:blur(3px);">
            <div id="vip-prompt" style="position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:#fff;padding:30px 40px;border-radius:12px;box-shadow:0 8px 30px rgba(0,0,0,0.2);text-align:center;z-index:99999;min-width:380px;">
                <h3 style="margin:0 0 20px 0;font-size:20px;color:#333;">&#128274; 会员专属功能</h3>
                <p style="margin:0 0 25px 0;font-size:16px;color:#666;">请先开通会员才能使用AI对话服务</p>
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
                    window.location.href = "'.esc_url($deepseek_vip_prompt_page).'";
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
        
        $deepseekhtml = str_replace('</body>', $deepseekprompt.'</body>', $deepseekhtml);
    }
    echo $deepseekhtml;
}
// 对话 结束


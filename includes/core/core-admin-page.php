<?php
// 设置页面
function deepseek_render_settings_page() {
    $balance = get_deepseek_balance();
    $api_key = get_option('deepseek_api_key'); // 获取deepseek API Key
    ?>
    <div class="ai-wrap">
        <h1>启灵Ai助手设置</h1>
        <?php if (isset($_GET['settings-updated']) && $_GET['settings-updated']): ?>
            <div id="deepseek-save-success">保存成功！
            </div>
            <script>
                setTimeout(() => {
                    document.getElementById('deepseek-save-success').style.display = 'none';
                }, 1000);
            </script>
        <?php endif; ?>
        <form method="post" action="options.php">
            <?php
            settings_fields('deepseek_chat_options_group');
            do_settings_sections('deepseek-chat');
            submit_button();
            ?>
        </form>
        <?php 
        // 如果有余额信息则显示余额
        if ($balance !== false): ?>
            <div style="margin-top: 20px;">
                <strong>DeepSeek 余额:</strong> <?php echo esc_html($balance); ?> CNY
            </div>
        <?php 
        // 只有当API Key不为空且获取余额失败时才显示错误提示
        elseif (!empty($api_key)): ?>
            <div style="margin-top: 20px; color: red;">
                无法获取DeepSeek余额信息，请检查DeepSeek官方API Key是否正确，如果你不用DeepSeek官方接口就无视。
            </div>
        <?php endif; ?>
       <p> 插件设置说明：<a href="https://www.wujiit.com/wpaidocs" target="_blank">https://www.wujiit.com/wpaidocs</a><br>
        Openai、Gemini、Claude接口只有在官方允许的地区才能访问<br>
    反馈问题请带上错误提示，插件加入了日志调用，方便快速查找问题所在，所以遇到问题了直接把网站错误日志发来。</p>
    </div>
    <?php
}


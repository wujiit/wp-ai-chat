<?php
function deepseek_register_setting_options() {
    register_setting('deepseek_chat_options_group', 'deepseek_api_key'); // DeepSeek API Key
    register_setting('deepseek_chat_options_group', 'deepseek_model', array('sanitize_callback' => 'sanitize_text_field')); // DeepSeek 模型参数
    register_setting('deepseek_chat_options_group', 'doubao_api_key'); // 豆包AI API Key
    register_setting('deepseek_chat_options_group', 'doubao_model', array('sanitize_callback' => 'sanitize_text_field')); // 豆包AI 模型参数
    register_setting('deepseek_chat_options_group', 'kimi_api_key'); // kimi AI API Key
    register_setting('deepseek_chat_options_group', 'kimi_model', array('sanitize_callback' => 'sanitize_text_field')); // kimi AI 模型参数
    register_setting('deepseek_chat_options_group', 'openai_api_key'); // openai API Key
    register_setting('deepseek_chat_options_group', 'openai_model', array('sanitize_callback' => 'sanitize_text_field')); // openai 模型参数
    register_setting('deepseek_chat_options_group', 'grok_api_key'); // grok API Key
    register_setting('deepseek_chat_options_group', 'grok_model', array('sanitize_callback' => 'sanitize_text_field')); // grok 模型参数
    register_setting('deepseek_chat_options_group', 'qianfan_api_key'); // 千帆 API Key
    register_setting('deepseek_chat_options_group', 'qianfan_model', array('sanitize_callback' => 'sanitize_text_field')); // 千帆 模型参数
    register_setting('deepseek_chat_options_group', 'hunyuan_api_key'); // 腾讯混元 API Key
    register_setting('deepseek_chat_options_group', 'hunyuan_model', array('sanitize_callback' => 'sanitize_text_field')); // 腾讯混元 模型参数
    register_setting('deepseek_chat_options_group', 'xunfei_api_key'); // 讯飞星火 API Key
    register_setting('deepseek_chat_options_group', 'xunfei_model', array('sanitize_callback' => 'sanitize_text_field')); // 讯飞星火模型参数   

    // Gemini
    register_setting('deepseek_chat_options_group', 'gemini_api_key');
    register_setting('deepseek_chat_options_group', 'gemini_model', array('sanitize_callback' => 'sanitize_text_field'));

    // Claude
    register_setting('deepseek_chat_options_group', 'claude_api_key');
    register_setting('deepseek_chat_options_group', 'claude_model', array('sanitize_callback' => 'sanitize_text_field'));

    // 通义千问
    register_setting('deepseek_chat_options_group', 'qwen_api_key'); // 通义千问 API Key
    register_setting('deepseek_chat_options_group', 'qwen_text_model', array('sanitize_callback' => 'sanitize_text_field')); // 文本模型
    register_setting('deepseek_chat_options_group', 'qwen_image_model', array('sanitize_callback' => 'sanitize_text_field')); // 图像模型
    register_setting('deepseek_chat_options_group', 'qwen_video_model', array('sanitize_callback' => 'sanitize_text_field')); // 视频模型

    // 自定义模型设置
    register_setting('deepseek_chat_options_group', 'custom_api_key');       // 自定义模型API Key
    register_setting('deepseek_chat_options_group', 'custom_model_params', array('sanitize_callback' => 'sanitize_text_field')); // 自定义模型参数
    register_setting('deepseek_chat_options_group', 'custom_model_url');       // 自定义模型请求 URL

    // Ollama 本地模型设置
    register_setting('deepseek_chat_options_group', 'ollama_api_url', array('default' => 'http://127.0.0.1:11434/api/chat', 'sanitize_callback' => 'esc_url_raw'));       
    register_setting('deepseek_chat_options_group', 'ollama_model', array('sanitize_callback' => 'sanitize_text_field')); 

    // Pollinations模型参数设置
    register_setting('deepseek_chat_options_group', 'pollinations_model', array('sanitize_callback' => 'sanitize_text_field'));

    register_setting('deepseek_chat_options_group', 'show_ai_helper'); // ai助手显示
    register_setting('deepseek_chat_options_group', 'enable_ai_summary'); // 文章总结
    register_setting('deepseek_chat_options_group', 'enable_ai_voice_reading'); // AI对话语音朗读
    register_setting('deepseek_chat_options_group', 'deepseek_custom_prompts'); // 自定义提示词
    register_setting('deepseek_chat_options_group', 'ai_tutorial_title'); // AI使用教程标题
    register_setting('deepseek_chat_options_group', 'ai_tutorial_url');   // AI使用教程链接
    register_setting('deepseek_chat_options_group', 'enable_keyword_detection'); // 启用关键词检测
    register_setting('deepseek_chat_options_group', 'keyword_list'); // 违规关键词列表
    register_setting('deepseek_chat_options_group', 'enable_intelligent_agent'); // 启用智能体应用
    register_setting('deepseek_chat_options_group', 'deepseek_login_prompt'); // 未登录提示
    register_setting('deepseek_chat_options_group', 'qwen_enable_search'); // 模型联网搜索
    register_setting('deepseek_chat_options_group', 'enable_article_analysis', array('default' => '0', 'sanitize_callback' => 'sanitize_text_field')); // 文章seo分析

    // 上下文记忆窗口限制
    register_setting('deepseek_chat_options_group', 'deepseek_context_memory_limit', array('default' => '5', 'sanitize_callback' => 'intval'));

    //自定义按钮位置设置（右边距和底边距）    
    register_setting('deepseek_chat_options_group', 'ai_helper_right');
    register_setting('deepseek_chat_options_group', 'ai_helper_bottom');
    register_setting('deepseek_chat_options_group', 'ai_helper_name'); // 助手名称
    register_setting('deepseek_chat_options_group', 'ai_helper_icon'); // 图标链接
    // 按钮背景颜色
    register_setting('deepseek_chat_options_group', 'ai_helper_background', array(
        'sanitize_callback' => 'sanitize_text_field',
        'default' => 'linear-gradient(135deg, #6EE7B7, #3B82F6)'
    ));

    // 自定义入口相关设置
    register_setting('deepseek_chat_options_group', 'enable_custom_entry');
    register_setting('deepseek_chat_options_group', 'custom_entry_title');
    register_setting('deepseek_chat_options_group', 'custom_entry_url');

    // 文章总结接口选择设置
    register_setting('deepseek_chat_options_group', 'summary_interface_choice');

    // 多选和默认接口设置
    register_setting('deepseek_chat_options_group', 'chat_interfaces', array('default' => array('deepseek'),'sanitize_callback' => 'sanitize_text_field_array'));
    register_setting('deepseek_chat_options_group', 'default_chat_interface', array('default' => 'deepseek','sanitize_callback' => 'sanitize_text_field'));

    // 接口切换开关设置
    register_setting('deepseek_chat_options_group', 'show_interface_switch', array('default' => '0','sanitize_callback' => 'sanitize_text_field'));

    // 添加文件上传相关设置
    register_setting('deepseek_chat_options_group', 'enable_file_upload', array('default' => '0','sanitize_callback' => 'sanitize_text_field'));
    register_setting('deepseek_chat_options_group', 'allowed_file_types', array('default' => 'txt,docx,pdf,xlsx,md','sanitize_callback' => 'sanitize_text_field'));
    register_setting('deepseek_chat_options_group', 'max_file_size', array('default' => '10','sanitize_callback' => 'sanitize_text_field'));

    // 会员设置
    register_setting('deepseek_chat_options_group', 'deepseek_vip_check_enabled', 'intval');
    register_setting('deepseek_chat_options_group', 'deepseek_vip_prompt_page');
    register_setting('deepseek_chat_options_group', 'deepseek_vip_keyword');

    // 底部公告设置
    register_setting('deepseek_chat_options_group', 'deepseek_announcement', array('sanitize_callback' => 'wp_kses_post'));

    // 游客限制设置
    register_setting('deepseek_chat_options_group', 'deepseek_guest_chat_limit', array('default' => '5', 'sanitize_callback' => 'intval'));
    register_setting('deepseek_chat_options_group', 'deepseek_guest_upload_limit', array('default' => '2', 'sanitize_callback' => 'intval'));
}

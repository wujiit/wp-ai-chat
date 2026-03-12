<?php
function deepseek_register_setting_fields() {
    add_settings_section('deepseek_main_section', '基础设置', null, 'deepseek-chat');

    // 接口选择和默认接口设置
    add_settings_field('chat_interfaces', '启用的对话接口', 'chat_interfaces_callback', 'deepseek-chat', 'deepseek_main_section');
    add_settings_field('default_chat_interface', '默认对话接口', 'default_chat_interface_callback', 'deepseek-chat', 'deepseek_main_section');

    // DeepSeek配置项
    add_settings_field('deepseek_api_key', 'DeepSeek API Key', 'deepseek_api_key_callback', 'deepseek-chat', 'deepseek_main_section');
    add_settings_field('deepseek_model', 'DeepSeek 模型参数', 'deepseek_model_callback', 'deepseek-chat', 'deepseek_main_section');

    // 豆包AI配置项
    add_settings_field('doubao_api_key', '豆包AI API Key', 'doubao_api_key_callback', 'deepseek-chat', 'deepseek_main_section');
    add_settings_field('doubao_model', '豆包AI 模型参数', 'doubao_model_callback', 'deepseek-chat', 'deepseek_main_section');

    // kimi AI配置项
    add_settings_field('kimi_api_key', 'Kimi API Key', 'kimi_api_key_callback', 'deepseek-chat', 'deepseek_main_section');
    add_settings_field('kimi_model', 'Kimi 模型参数', 'kimi_model_callback', 'deepseek-chat', 'deepseek_main_section');

    // openai AI配置项
    add_settings_field('openai_api_key', 'OpenAI API Key', 'openai_api_key_callback', 'deepseek-chat', 'deepseek_main_section');
    add_settings_field('openai_model', 'OpenAI 模型参数', 'openai_model_callback', 'deepseek-chat', 'deepseek_main_section');

    // 游客限制设置
    add_settings_field('deepseek_guest_chat_limit', '游客每日对话限制 (次/设备)', 'deepseek_guest_chat_limit_callback', 'deepseek-chat', 'deepseek_main_section');
    add_settings_field('deepseek_guest_upload_limit', '游客每日文件上传限制 (次/设备)', 'deepseek_guest_upload_limit_callback', 'deepseek-chat', 'deepseek_main_section');

    // Grok AI配置项
    add_settings_field('grok_api_key', 'Grok API Key', 'grok_api_key_callback', 'deepseek-chat', 'deepseek_main_section');
    add_settings_field('grok_model', 'Grok 模型参数', 'grok_model_callback', 'deepseek-chat', 'deepseek_main_section');

    // Gemini配置项
    add_settings_field('gemini_api_key', 'Gemini API Key', 'gemini_api_key_callback', 'deepseek-chat', 'deepseek_main_section');
    add_settings_field('gemini_model', 'Gemini 模型参数', 'gemini_model_callback', 'deepseek-chat', 'deepseek_main_section');

    // Claude配置项
    add_settings_field('claude_api_key', 'Claude API Key', 'claude_api_key_callback', 'deepseek-chat', 'deepseek_main_section');
    add_settings_field('claude_model', 'Claude 模型参数', 'claude_model_callback', 'deepseek-chat', 'deepseek_main_section');

    // 千帆 AI配置项
    add_settings_field('qianfan_api_key', '千帆 API Key(文心一言)', 'qianfan_api_key_callback', 'deepseek-chat', 'deepseek_main_section');
    add_settings_field('qianfan_model', '千帆 模型参数(文心一言)', 'qianfan_model_callback', 'deepseek-chat', 'deepseek_main_section');

    // 腾讯混元 AI配置项
    add_settings_field('hunyuan_api_key', '腾讯混元 API Key', 'hunyuan_api_key_callback', 'deepseek-chat', 'deepseek_main_section');
    add_settings_field('hunyuan_model', '腾讯混元 模型参数', 'hunyuan_model_callback', 'deepseek-chat', 'deepseek_main_section');

    // 讯飞 AI配置项
    add_settings_field('xunfei_api_key', '讯飞星火 API Key', 'xunfei_api_key_callback', 'deepseek-chat', 'deepseek_main_section');
    add_settings_field('xunfei_model', '讯飞星火 模型参数', 'xunfei_model_callback', 'deepseek-chat', 'deepseek_main_section');

    // 通义千问配置项
    add_settings_field('qwen_api_key', '通义千问 API Key', 'qwen_api_key_callback', 'deepseek-chat', 'deepseek_main_section');
    add_settings_field('qwen_text_model', '通义千问 文本模型参数', 'qwen_text_model_callback', 'deepseek-chat', 'deepseek_main_section');
    add_settings_field('qwen_image_model', '通义千问 图像模型参数', 'qwen_image_model_callback', 'deepseek-chat', 'deepseek_main_section');
    add_settings_field('qwen_video_model', '通义千问 视频模型参数', 'qwen_video_model_callback', 'deepseek-chat', 'deepseek_main_section');

    // 自定义模型配置项
    add_settings_field('custom_api_key', '自定义模型 API Key', 'custom_api_key_callback', 'deepseek-chat', 'deepseek_main_section');
    add_settings_field('custom_model_params', '自定义模型参数', 'custom_model_params_callback', 'deepseek-chat', 'deepseek_main_section');
    add_settings_field('custom_model_url', '自定义模型请求 URL', 'custom_model_url_callback', 'deepseek-chat', 'deepseek_main_section');

    // Ollama 本地模型配置项
    add_settings_field('ollama_api_url', 'Ollama API URL', 'ollama_api_url_callback', 'deepseek-chat', 'deepseek_main_section');
    add_settings_field('ollama_model', 'Ollama 模型名称', 'ollama_model_callback', 'deepseek-chat', 'deepseek_main_section');

    // Pollinations模型参数字段
    add_settings_field('pollinations_model', 'Pollinations 模型参数', 'pollinations_model_callback', 'deepseek-chat', 'deepseek_main_section');

    // 自定义提示词
    add_settings_field('deepseek_custom_prompts', '自定义提示词', 'deepseek_custom_prompts_callback', 'deepseek-chat', 'deepseek_main_section');

    // AI使用教程标题
    add_settings_field('ai_tutorial_title', '提示词教程标题', 'ai_tutorial_title_callback', 'deepseek-chat', 'deepseek_main_section');

    // AI使用教程链接 
    add_settings_field('ai_tutorial_url', '提示词教程链接', 'ai_tutorial_url_callback', 'deepseek-chat', 'deepseek_main_section');

    // 启用关键词检测
    add_settings_field('enable_keyword_detection', '启用关键词检测', 'enable_keyword_detection_callback', 'deepseek-chat', 'deepseek_main_section');
    // 违规关键词
    add_settings_field('keyword_list', '违规关键词列表', 'keyword_list_callback', 'deepseek-chat', 'deepseek_main_section');

    // ai助手入口
    add_settings_field('show_ai_helper', '网站前台显示AI助手入口', 'show_ai_helper_callback', 'deepseek-chat', 'deepseek_main_section');    

    // AI助手按钮位置设置
    add_settings_field('ai_helper_right', 'AI助手按钮右边距', 'ai_helper_right_callback', 'deepseek-chat', 'deepseek_main_section');
    add_settings_field('ai_helper_bottom', 'AI助手按钮底边距', 'ai_helper_bottom_callback', 'deepseek-chat', 'deepseek_main_section');
    // 助手名称设置
    add_settings_field('ai_helper_name', 'AI助手名称', 'ai_helper_name_callback', 'deepseek-chat', 'deepseek_main_section');
    // 助手图标链接设置
    add_settings_field('ai_helper_icon', 'AI助手图标链接', 'ai_helper_icon_callback', 'deepseek-chat', 'deepseek_main_section');
    // 助手按钮背景颜色
    add_settings_field('ai_helper_background', 'AI助手按钮背景颜色', 'ai_helper_background_callback', 'deepseek-chat', 'deepseek_main_section');    

    // 启用智能体应用
    add_settings_field('enable_intelligent_agent', '前台显示智能体应用入口', 'enable_intelligent_agent_callback', 'deepseek-chat', 'deepseek_main_section');

    // 自定义入口设置项
    add_settings_field('enable_custom_entry', '对话页面显示自定义入口', 'enable_custom_entry_callback', 'deepseek-chat', 'deepseek_main_section');
    add_settings_field('custom_entry_title', '自定义入口标题', 'custom_entry_title_callback', 'deepseek-chat', 'deepseek_main_section');
    add_settings_field('custom_entry_url', '自定义入口链接', 'custom_entry_url_callback', 'deepseek-chat', 'deepseek_main_section');  

    // AI对话语音朗读
    add_settings_field('enable_ai_voice_reading', '启用AI对话语音播放', 'enable_ai_voice_reading_callback', 'deepseek-chat', 'deepseek_main_section');

    // 接口切换显示开关
    add_settings_field('show_interface_switch', '前台显示接口切换', 'show_interface_switch_callback', 'deepseek-chat', 'deepseek_main_section');

    // 上下文记忆轮数限制
    add_settings_field('deepseek_context_memory_limit', '对话上下文记忆轮数', 'deepseek_context_memory_limit_callback', 'deepseek-chat', 'deepseek_main_section');

    // 在线联网搜索
    add_settings_field('qwen_enable_search', '启用在线联网搜索', 'qwen_enable_search_callback', 'deepseek-chat', 'deepseek_main_section');

    // 用户选择接口的处理
    add_action('wp_ajax_deepseek_switch_interface', 'deepseek_handle_interface_switch');

    // 文件上传相关字段
    add_settings_field('enable_file_upload', '启用文件上传', 'enable_file_upload_callback', 'deepseek-chat', 'deepseek_main_section');
    add_settings_field('allowed_file_types', '允许的文件格式', 'allowed_file_types_callback', 'deepseek-chat', 'deepseek_main_section');
    add_settings_field('max_file_size', '最大文件大小(MB)', 'max_file_size_callback', 'deepseek-chat', 'deepseek_main_section');

    // 会员验证相关字段
    add_settings_field('deepseek_vip_check_enabled', '启用网站会员验证', 'deepseek_vip_check_enabled_callback', 'deepseek-chat', 'deepseek_main_section');
    add_settings_field('deepseek_vip_keyword', '会员验证关键词', 'deepseek_vip_keyword_callback', 'deepseek-chat', 'deepseek_main_section');
    add_settings_field('deepseek_vip_prompt_page', '开通会员页面链接', 'deepseek_vip_prompt_page_callback', 'deepseek-chat', 'deepseek_main_section');

    // 公告设置字段
    add_settings_field('deepseek_announcement', '公告说明', 'deepseek_announcement_callback', 'deepseek-chat', 'deepseek_main_section');

    // 未登录提示文字
    add_settings_field('deepseek_login_prompt', '未登录提示文字', 'deepseek_login_prompt_callback', 'deepseek-chat', 'deepseek_main_section');

    // 文章总结
    add_settings_field('enable_ai_summary', '文章AI总结', 'enable_ai_summary_callback', 'deepseek-chat', 'deepseek_main_section');

    // 文章总结接口
    add_settings_field('summary_interface_choice', '文章总结接口', 'summary_interface_choice_callback', 'deepseek-chat', 'deepseek_main_section');

    // 启用文章分析
    add_settings_field('enable_article_analysis', '启用文章分析', 'enable_article_analysis_callback', 'deepseek-chat', 'deepseek_main_section');

    // AJAX处理文件上传
    add_action('wp_ajax_deepseek_upload_file', 'deepseek_handle_file_upload');    
}

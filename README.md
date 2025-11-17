<img src="https://github.com/suqicloud/wp-ai-chat/blob/main/ic_logo.png" width="60">

# 小半WordPress ai助手  

[![License](https://img.shields.io/badge/license-GPL-blue.svg)](LICENSE)
[![Version](https://img.shields.io/badge/version-4.0.5-green.svg)](https://github.com/suqicloud/wp-ai-chat/releases/tag/4.0.5)
[![WordPress](https://img.shields.io/badge/WordPress-6.7-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-8.0-orange.svg)](https://www.php.net/)
[![Nginx](https://img.shields.io/badge/Nginx-1.2-green.svg)](https://nginx.org/)



## 📌 项目简介

全开源免费 - WordPress ai助手插件，可实现：ai对话聊天(文字、图片生成、视频生成)、ai对话语音播放、ai文章生成、ai文章SEO分析、ai文章总结、ai文章翻译、ai生成PPT、ai文档分析、ai智能体应用、文章内容语音播放。  
不会用的先看文档，就一个基础配置和apikey对接的事，这种插件没有什么高难度要求。

这个插件不更新了。

https://www.wujiit.com/  
首个基于Wordpress的综合AI工具站，所有功能都已经开源(除了主题)。

我现在所有开源免费的Wordpress的插件，大概150多款，均可以在 https://www.wujiit.com/wpplugins 或者 https://www.iticu.icu/  下载



## 🚀 功能特性

1. 内置deepseek文本接口  
1. 内置阿里通义千问文本接口  
1. 内置百度千帆(文心一言)文本接口  
1. 内置豆包ai文本接口  
1. 内置Kimi文本接口  
1. 内置OpenAI文本接口  
1. 内置腾讯混元文本接口  
1. 内置Grok文本接口  
1. 内置Gemini文本接口 
1. 内置Claude文本接口 
1. 内置讯飞星火文本接口  
1. 内置自定义AI文本模型接口  
1. 支持阿里智能体应用对接  
1. 支持火山引擎智能体应用对接  
1. 支持腾讯元器智能体应用对接  
1. 支持字节扣子智能体应用对接  
1. 支持文多多AIPPT接口生成PPT文件  
1. 支持pollinations ai的文生图模型  
1. 支持通义千问的图片生成模型  
1. 支持通义千问的视频生成模型(文生视频、图生视频) 
1. 支持通义千问和讯飞星火部分模型联网搜索
1. 模型参数是自定义填写  
1. 系统会用一个单独的数据表保存对话记录的第一句  
1. 用户可以删掉自己的历史对话记录  
1. 后台可以删掉用户的对话记录  
1. 后台可以删掉用户的智能体应用对话  
1. 可以通过关键词生成文章  
1. 可以通过AI接口对文章进行总结  
1. 前台显示AI助手入口  
1. 只允许登录用户使用  
1. 支持Markdown格式
1. DeepSeek余额信息  
1. 通过AI接口对文章进行翻译  
1. 支持对接腾讯云、百度云 TTS服务实现语音播放文章内容  
1. 可以实现语音播放AI回复的文字内容  
1. 可以自定义提示词  
1. 自定义提示词教程链接  
1. Markdown内容板块自动加载复制按钮  
1. 支持违规关键词检测  
1. AI生成PPT可以验证会员权限(部分网站可能不行)  
1. 智能体应用开场问题  
1. 自定义前台ai助手名称等  
1. 自定义未登录提示文字  
1. 支持前台用户选择接口  
1. 支持kimi和通义千问qwen-long上传文件分析文档内容  
1. 支持前台用户选择模型参数  
1. 支持对文章内容进行SEO分析，同时检测错别字  


## 📥 安装

1. 下载最新版本文件。
2. 进入WordPress插件后台
3. 上传本地文件包安装

或者直接上传到服务器的网站插件目录/wp-content/plugins也行，记得设置权限。  

开发基础：WordPress 6.7.1  
php版本：php 8.0  

## 🛠️ 使用方法

插件启用会自动创建一个前台对话页面。如果没有自动创建，就自己手动加短代码：  [deepseek_chat]  

1 - 文章翻译的接口要单独设置，因为这本来是我另外一个插件的，我合并过来了，不想折腾，就直接用了。   
2 - ai生成PPT也是独立插件进行的合并，并且这个功能原本是根据我自己用的主题调整的，可能兼容性不好。    


如果插件彻底不用了，自己到数据库去删掉这个数据表：deepseek_chat_logs、deepseek_agent_chat_logs这2个数据表。


主题页面需要支持全宽或者全屏模式，不然很狭窄。如果不支持就自己查看你主题的样式，通过代码实现deepseek助手页面全屏显示。  

这款插件最早是为了测试deepseek自己写代码的能力，有一部分是deepseek自己写的代码(ai对话对接deepseek和最早版本的文章生成)，后面又合并了其他插件，所以代码里面的函数名称啥的看起来很乱，但是都写了注释。  


## 文件说明

主文件： wp-ai-chat.php  
翻译语音文件： wpaitranslate.php  
ai生成ppt文件： wpaippt.php  
智能体应用文件： wpaidashscope.php  
主要js文件： wpai-chat.js  
css文件：wpai-style.css  
翻译语音js文件： wpai-script.js  
ppt调用js文件： docmee-ui-sdk-iframe.min.js  
Markdown解析文件： marked.min.js 


## 赞助合作
本项目 CDN 加速及安全防护由 Tencent EdgeOne 赞助：EdgeOne 提供长期有效的免费套餐，包含不限量的流量和请求，覆盖中国大陆节点，且无任何超额收费，感兴趣的朋友可以点击下面的链接领取. 

CDN acceleration and security protection for this project are sponsored by Tencent EdgeOne: EdgeOne offers a long-term free plan with unlimited traffic and requests, covering Mainland China nodes, with no overage charges. Interested friends can click the link below to claim it。

[亚洲最佳CDN、边缘和安全解决方案 - Tencent EdgeOne](https://edgeone.ai/zh?from=github) 

[Best Asian CDN, Edge, and Secure Solutions - Tencent EdgeOne](https://edgeone.ai/zh?from=github) 


[![EdgeOne](https://edgeone.ai/media/34fe3a45-492d-4ea4-ae5d-ea1087ca7b4b.png)](https://edgeone.ai/?from=github)

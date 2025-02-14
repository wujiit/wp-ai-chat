<img src="https://github.com/suqicloud/wp-ai-chat/blob/main/ic_logo.png" width="60">

# 小半WordPress ai助手  

[![License](https://img.shields.io/badge/license-GPL-blue.svg)](LICENSE)
[![Version](https://img.shields.io/badge/version-2.6-green.svg)](https://github.com/suqicloud/wp-ai-chat/releases/tag/2.6)
[![WordPress](https://img.shields.io/badge/WordPress-6.7-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-8.0-orange.svg)](https://www.php.net/)



## 📌 项目简介

全开源免费 - WordPress ai助手插件，可实现：ai对话、ai文章生成、ai文章总结、ai文章翻译、文章内容朗读、ai回复内容朗读。  

需要网站开启REST API服务，有的用户优化wp的时候可能会关掉REST API。  

## 🚀 功能特性

1.支持deepseek  
2.支持阿里通义千问  
3.支持百度千帆  
4.支持豆包  
5.支持Kimi  
6.支持Openai  
7.支持自定义ai文本模型  
8.支持通义千问的图片生成模型  
9.DeepSeek是内置DeepSeek-V3、DeepSeek-R1模型选项  
10.其他模型参数是自定义填写  
11.系统会用一个单独的数据表保存对话记录的第一句  
12.用户可以删掉自己的历史对话记录  
13.后台可以删掉用户的对话记录  
14.可以通过关键词生成文章  
15.可以通过ai接口对文章进行总结  
16.前台显示ai助手入口  
17.只允许登录用户使用  
18.支持Markdown格式(需要ai返回的是markdown格式)  
19.DeepSeek余额信息  
20.通过ai接口对文章进行翻译  
21.支持对接腾讯云、百度云TTS服务实现朗读文章内容  
22.可以实现朗读ai回复的文字内容  
23.可以自定义提示词  


## 📥 安装

1. 下载最新版本文件。
2. 进入WordPress插件后台
3. 上传本地文件包安装

或者直接上传到服务器的网站插件目录/wp-content/plugins也行，记得设置权限。  

开发基础：WordPress 6.7.1  
php版本：php 8.0  

## 🛠️ 使用方法

插件启用会自动创建一个前台对话页面。如果没有自动创建，就自己手动加短代码：  [deepseek_chat]  

文章翻译的接口要单独设置，因为这本来是我另外一个插件的，我合并过来了，不想折腾，就直接用了，  

如果插件不用了，自己到数据库去删掉这个数据表：deepseek_chat_logs  

教程：https://www.wujiit.com/wpaidocs

主题页面需要支持全宽或者全屏模式，不然很狭窄。如果不支持就自己查看你主题的样式，通过代码实现deepseek助手页面全屏显示。  

这款插件最早是为了测试deepseek自己写代码的能力，有一部分是deepseek自己写的代码(ai对话对接deepseek和最早版本的文章生成)，后面又合并了其他插件，所以代码里面的函数名称啥的看起来很乱，但是都写了注释。  



![WordPressai.png](https://i.miji.bid/2025/02/14/2a5c7bcd11a8433c7311638b8a6b8f76.jpeg)

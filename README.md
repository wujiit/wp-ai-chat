这个插件一开始是为了验证deepseek，所以插件最早只支持deepseek，deepseek部分和文章总结的功能是deepseek写的。  



插件名称：小半WordPress ai助手  


插件功能：  

1.支持对接deepseek  
2.可以选择DeepSeek-V3或者DeepSeek-R1模型  
3.支持对接豆包ai  
4.可以自定义豆包ai文本类型的模型  
5.支持对接通义千问  
6.支持常用的通义千问模型  
7.支持通义千问的图片生成  
8.系统会用一个单独的数据表保存对话记录的第一句  
9.用户可以删掉自己的历史对话记录   
10.后台可以删掉用户的对话记录  
11.可以通过关键词生成文章  
12.可以通过ai接口对文章进行总结  
13.前台显示一个ai助手入口  
14.只允许登录用户使用  
15.支持Markdown格式

豆包只支持语言类模型。  
通义千问内置支持：  
qwen-max  
qwen-plus  
qwen-turbo  
qwen-long  
qwen-mt-plus（翻译）  
qwen-mt-turbo（翻译）  
qwen2.5-14b-instruct-1m  
qwen2.5-1.5b-instruct（官方暂时完全免费）  
wanx2.1-t2i-turbo图片生成  
wanx2.1-t2i-plus图片生成  

marked.min.js文件用于解析Markdown格式。

如果插件不用了，自己到数据库去删掉这个数据表：deepseek_chat_logs  
主题页面需要支持全宽或者全屏模式，不然很狭窄。如果不支持就自己查看你主题的样式，通过代码实现deepseek助手页面全屏显示。  

插件启用会自动创建一个前台对话页面。如果没有自动创建，就自己手动加短代码：  
[deepseek_chat]

插件截图预览：  
![caed325b82bad34d242b327b1b101805.jpeg](https://i.miji.bid/2025/01/28/caed325b82bad34d242b327b1b101805.jpeg)

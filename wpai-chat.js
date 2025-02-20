// 将Markdown文本转换为HTML
function convertMarkdownToHTML(markdownText) {
    // 使用Marked库进行转换
    return marked.parse(markdownText);
}

// 通过DEEPSEEK_VARS对象访问变量
var aiVoiceEnabled = parseInt(DEEPSEEK_VARS.AI_VOICE_ENABLED);
var deepseek_rest_nonce = DEEPSEEK_VARS.REST_NONCE;
var restUrl = DEEPSEEK_VARS.REST_URL;
var adminAjaxUrl = DEEPSEEK_VARS.ADMIN_AJAX_URL;
var enableKeywordDetection = parseInt(DEEPSEEK_VARS.ENABLE_KEYWORD_DETECTION);
var keywords = DEEPSEEK_VARS.KEYWORDS.split(',');

// 全局当前对话ID变量
var currentConversationId = null; 

// 封装一个设置对话ID的函数，同时更新localStorage
function setCurrentConversationId(id) {
    currentConversationId = id;
    if (id) {
        // 将对话ID存储到localStorage中
        localStorage.setItem('currentConversationId', id);
    } else {
        // 若ID为空，从localStorage中移除对话ID
        localStorage.removeItem('currentConversationId');
    }
}

// 页面加载时，检测localStorage中是否有对话ID
window.addEventListener('load', function() {
    var storedId = localStorage.getItem('currentConversationId');
    if (storedId) {
        // 设置当前对话ID
        setCurrentConversationId(storedId);
        // 加载聊天记录
        loadChatLog(storedId);
    }
});

// 默认提示    
document.getElementById('deepseek-chat-input').addEventListener('input', function() {
    var prompt = document.getElementById('chatbot-prompt');
    if (prompt) {
        // 隐藏提示消息
        prompt.style.display = 'none'; 
    }
    // 控制自定义提示词板块的显示状态
    var customPrompts = document.getElementById('deepseek-custom-prompts');
    if (customPrompts) {
        // 当输入框非空时，隐藏提示词板块
        if (this.value.trim().length > 0) {
            customPrompts.style.display = 'none';
        } else {
            customPrompts.style.display = 'block';
        }
    }        
});

// 检测关键词
function containsForbiddenKeyword(message) {
    return keywords.some(keyword => message.includes(keyword.trim()));
}

// 关键词检测事件绑定
document.addEventListener('DOMContentLoaded', function() {
    const sendButton = document.getElementById('deepseek-chat-send');
    const inputField = document.getElementById('deepseek-chat-input');
    const errorMessage = document.getElementById('keyword-error-message');

    sendButton.addEventListener('click', function(event) {
        const message = inputField.value.trim();

        if (enableKeywordDetection && containsForbiddenKeyword(message)) {
            errorMessage.style.display = 'block';
            event.preventDefault(); // 阻止发送
        } else {
            errorMessage.style.display = 'none';
        }
    });
});

// 发送消息
document.getElementById('deepseek-chat-send').addEventListener('click', function() {
    var message = document.getElementById('deepseek-chat-input').value;
    if (message) {
        // 判断是否为新对话，保存当前消息作为对话标题
        var newConversation = false;
        var currentMessage = message; // 新对话时，用第一句作为标题
        if (!currentConversationId) {
            newConversation = true;
        }
        
        // 显示“小助手正在思考中...”的提示
        var thinkingMessage = document.createElement('div');
        thinkingMessage.id = 'deepseek-thinking-message';
        thinkingMessage.className = 'message-bubble bot';
        thinkingMessage.innerHTML = '小助手正在思考中...';
        document.getElementById('deepseek-chat-messages').appendChild(thinkingMessage);

        // 使用REST API接口，传输JSON数据，并附加nonce进行权限验证
        fetch(restUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': deepseek_rest_nonce
            },
            body: JSON.stringify({
                message: message,
                conversation_id: currentConversationId
            })
        })
        .then(response => {
            const contentType = response.headers.get('Content-Type') || '';
            // 如果返回JSON，则按非流式逻辑处理(图片生成)
            if (contentType.indexOf('application/json') !== -1) {
                return response.json().then(data => ({ data, isJson: true }));
            } else {
                // 否则走流式文本返回
                return { response };
            }
        })
        .then(result => {
            if (result.isJson) {
                var data = result.data;
                if (data.success) {
                    if (data.is_image) {
                        // 图片生成处理
                        handleImageGeneration(data.task_id);

                        // 如果当前对话为空，则更新历史记录（仅新对话需要更新对话id）
                        if (!currentConversationId) {
                            var historyContainer = document.querySelector('#deepseek-chat-history ul');
                            var newChatItem = document.createElement('li');
                            newChatItem.setAttribute('data-conversation-id', data.conversation_id);
                            newChatItem.innerHTML = '<span class="deepseek-chat-title">' + data.conversation_title + '</span>' +
                                '<button class="deepseek-delete-log" data-conversation-id="' + data.conversation_id + '">删除</button>';
                            historyContainer.insertBefore(newChatItem, historyContainer.firstChild);

                            // 绑定新历史记录的点击事件
                            newChatItem.addEventListener('click', function() {
                                loadChatLog(data.conversation_id);
                            });

                            // 绑定新历史记录的删除按钮事件
                            newChatItem.querySelector('.deepseek-delete-log').addEventListener('click', function() {
                                var conversationId = this.getAttribute('data-conversation-id');
                                fetch(adminAjaxUrl, {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/x-www-form-urlencoded',
                                    },
                                    body: 'action=deepseek_delete_log&conversation_id=' + conversationId
                                }).then(response => response.json())
                                .then(data => {
                                    if (data.success) {
                                        this.parentElement.remove();
                                        // 清空消息框内容
                                        document.getElementById('deepseek-chat-messages').innerHTML = '';
                                        // 重置当前对话id
                                        currentConversationId = null;
                                    }
                                });
                            });

                            setCurrentConversationId(data.conversation_id);
                        }
                    }
                }
            } else {
                // 流式文本回复处理
                var messagesContainer = document.getElementById('deepseek-chat-messages');
                
                // 移除“小助手正在思考中...”的提示
                var thinkingMessage = document.getElementById('deepseek-thinking-message');
                if (thinkingMessage) {
                    thinkingMessage.remove();
                }

                // 添加用户消息
                messagesContainer.innerHTML += '<div class="message-bubble user">' + message + '</div>';
                
                // 添加一个消息框div，用于显示AI回复
                var botMessageContainer = document.createElement('div');
                botMessageContainer.classList.add('message-bubble', 'bot');
                botMessageContainer.textContent = '';  // 初始为空，逐步填充
                messagesContainer.appendChild(botMessageContainer);

                // 使用ReadableStream API对SSE格式的数据做处理
                const reader = result.response.body.getReader();
                const decoder = new TextDecoder();
                let botReply = '';
                let buffer = '';
                
                // 在解析到conversation_id时，如果是新对话则更新历史记录
                function processLine(line) {
                    line = line.trim();
                    if (!line.startsWith("data:")) return;
                    let dataPart = line.substring(5).trim();
                    if (dataPart === "[DONE]") return;
                    try {
                        let jsonData = JSON.parse(dataPart);
                        if (jsonData.conversation_id) {
                            // 如果是新对话，则在历史对话列表中插入对话标题（用户第一条消息）
                            if (newConversation) {
                                setCurrentConversationId(jsonData.conversation_id);
                                var historyContainer = document.querySelector('#deepseek-chat-history ul');
                                var newChatItem = document.createElement('li');
                                newChatItem.setAttribute('data-conversation-id', jsonData.conversation_id);
                                newChatItem.innerHTML = '<span class="deepseek-chat-title">' + currentMessage + '</span>' +
                                    '<button class="deepseek-delete-log" data-conversation-id="' + jsonData.conversation_id + '">删除</button>';
                                historyContainer.insertBefore(newChatItem, historyContainer.firstChild);
                                // 绑定点击加载对话
                                newChatItem.addEventListener('click', function() {
                                    loadChatLog(jsonData.conversation_id);
                                });
                                // 绑定删除按钮事件
                                newChatItem.querySelector('.deepseek-delete-log').addEventListener('click', function(e) {
                                    e.stopPropagation();
                                    var conversationId = this.getAttribute('data-conversation-id');
                                    fetch(adminAjaxUrl, {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/x-www-form-urlencoded',
                                        },
                                        body: 'action=deepseek_delete_log&conversation_id=' + conversationId
                                    }).then(response => response.json())
                                    .then(data => {
                                        if (data.success) {
                                            this.parentElement.remove();
                                            document.getElementById('deepseek-chat-messages').innerHTML = '';
                                            setCurrentConversationId(null);
                                        }
                                    });
                                });
                                newConversation = false; // 标记已更新历史记录
                                console.log('新对话历史记录已更新，对话ID：', jsonData.conversation_id);
                            } else {
                                setCurrentConversationId(jsonData.conversation_id);
                            }
                            return;
                        }
                        if (jsonData && jsonData.choices && jsonData.choices.length > 0) {
                            let delta = jsonData.choices[0].delta;
                            if (delta && delta.content) {
                                botReply += delta.content;
                                botMessageContainer.textContent = botReply;
                            }
                        }
                    } catch(e) {
                        console.error("解析SSE错误", e);
                    }
                }
                
                function readStream() {
                    reader.read().then(({done, value}) => {
                        if (done) {
                            // 处理最后剩余的不完整数据
                            if (buffer.length > 0) {
                                let lines = buffer.split("\n");
                                lines.forEach(processLine);
                            }
                            // 流结束后，将Markdown转换为HTML显示
                            botMessageContainer.innerHTML = convertMarkdownToHTML(botReply);
                            // 添加复制按钮到新生成的pre标签
                            addCopyButtonsToPreTags(botMessageContainer);
                            if (aiVoiceEnabled) {
                                var playIcon = document.createElement('span');
                                playIcon.classList.add('ai-tts-play');
                                playIcon.innerHTML = '&#128266;';
                                playIcon.style.marginLeft = '10px';
                                playIcon.addEventListener('click', function() {
                                    var audioElem = document.getElementById('ai-tts-audio');
                                    if (!audioElem) {
                                        audioElem = document.createElement('audio');
                                        audioElem.id = 'ai-tts-audio';
                                        audioElem.style.display = 'none';
                                        document.body.appendChild(audioElem);
                                    }
                                    // 先移除可能存在的旧提示
                                    var existingMessage = playIcon.nextElementSibling;
                                    if (existingMessage && existingMessage.classList.contains('tts-message')) {
                                        existingMessage.remove();
                                    }

                                    // 创建“语音准备中”提示
                                    var messageSpan = document.createElement('span');
                                    messageSpan.classList.add('tts-message');
                                    messageSpan.textContent = '语音准备中...';
                                    messageSpan.style.marginLeft = '5px';
                                    messageSpan.style.color = '#666';
                                    playIcon.after(messageSpan);

                                    if (audioElem.audioUrls) {
                                        if (!audioElem.paused) {
                                            audioElem.pause();
                                            playIcon.innerHTML = '&#128264;';
                                        } else {
                                            audioElem.play();
                                            playIcon.innerHTML = '&#128266;';
                                        }
                                        return;
                                    }
                                    var dataParams = new URLSearchParams();
                                    dataParams.append('action', 'deepseek_tts');
                                    dataParams.append('text', botReply);
                                    fetch(adminAjaxUrl, {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/x-www-form-urlencoded',
                                        },
                                        body: dataParams
                                    })
                                   .then(response => response.json())
                                   .then(ttsData => {
                                        if (ttsData.success) {
                                            var audio_urls = ttsData.data.audio_urls;
                                            if (audio_urls && audio_urls.length > 0) {
                                                audioElem.audioUrls = audio_urls;
                                                audioElem.currentIndex = 0;
                                                audioElem.src = audio_urls[0];
                                                // 当音频开始播放时移除提示
                                                audioElem.onplay = function() {
                                                    messageSpan.remove();
                                                };

                                                audioElem.play();
                                                playIcon.innerHTML = '&#128266;';
                                                audioElem.onended = function() {
                                                    audioElem.currentIndex++;
                                                    if (audioElem.currentIndex < audio_urls.length) {
                                                        audioElem.src = audio_urls[audioElem.currentIndex];
                                                        audioElem.play();
                                                    } else {
                                                        delete audioElem.audioUrls;
                                                        audioElem.currentIndex = 0;
                                                        playIcon.innerHTML = '&#128266;';
                                                    }
                                                };
                                            }
                                        } else {
                                            messageSpan.textContent = '语音朗读失败';
                                            messageSpan.style.color = 'red';
                                        }
                                    })
                                   .catch(() => {
                                        messageSpan.textContent = '请求错误，请重试';
                                        messageSpan.style.color = 'red';
                                    });
                                });                                
                                botMessageContainer.appendChild(playIcon);
                            }
                            // 清空输入框
                            document.getElementById('deepseek-chat-input').value = '';
                            // 滚动消息框到最底部
                            messagesContainer.scrollTop = messagesContainer.scrollHeight;
                            return;
                        }
                        const chunk = decoder.decode(value, {stream: true});
                        buffer += chunk;
                        let lines = buffer.split("\n");
                        // 将最后一行可能不完整的数据保留到buffer中
                        buffer = lines.pop();
                        lines.forEach(processLine);
                        readStream();
                    });
                }
                readStream();
            }
        })
        // 如果fetch请求出错，则将提示信息修改为“网络错误，请稍后重试”
        .catch(error => {
            console.error('Fetch request failed:', error);
            var errorMsg = document.getElementById('deepseek-thinking-message');
            if (errorMsg) {
                errorMsg.innerHTML = '网络错误，请稍后重试';
            }
        });
    }
});

// 图片生成处理函数
function handleImageGeneration(taskId) {
    var messagesContainer = document.getElementById('deepseek-chat-messages');
    var thinkingMessage = document.getElementById('deepseek-thinking-message');
    if (thinkingMessage) thinkingMessage.remove();

    // 添加用户消息
    var userMessage = document.createElement('div');
    userMessage.className = 'message-bubble user';
    userMessage.textContent = document.getElementById('deepseek-chat-input').value;
    messagesContainer.appendChild(userMessage);

    // 添加加载状态
    var loadingContainer = document.createElement('div');
    loadingContainer.className = 'message-bubble bot';
    loadingContainer.innerHTML = '图片生成中...';
    messagesContainer.appendChild(loadingContainer);

    // 轮询检查任务状态
    var checkInterval = setInterval(function() {
        fetch(adminAjaxUrl, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=deepseek_check_image_task&task_id=' + taskId
        }).then(response => response.json())
        .then(data => {
            if (data.task_status === 'SUCCEEDED') {
                clearInterval(checkInterval);
                loadingContainer.remove();
                
                // 创建消息容器
                var botMessage = document.createElement('div');
                botMessage.className = 'message-bubble bot';

                // 创建图片描述容器
                var promptContainer = document.createElement('div');
                promptContainer.className = 'image-prompt';
                botMessage.appendChild(promptContainer);

                // 创建图片容器
                var imageContainer = document.createElement('img');
                imageContainer.src = data.image_url;
                imageContainer.style.maxWidth = '100%';
                imageContainer.style.height = 'auto';
                botMessage.appendChild(imageContainer);

                // 将消息容器添加到消息框中
                messagesContainer.appendChild(botMessage);

                // 实现逐字显示效果
                var actualPrompt = data.actual_prompt;
                var index = 0;
                var typingSpeed = 50; // 控制打字速度

                function typeWriter() {
                    if (index < actualPrompt.length) {
                        promptContainer.innerHTML += actualPrompt.charAt(index);
                        index++;
                        setTimeout(typeWriter, typingSpeed); // 使用setTimeout模拟打字速度
                    }
                }
                typeWriter();
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            }
        });
    }, 2000);
}

// 打字效果显示AI回复的函数
function typeWriter(text, container, callback) {
    var i = 0;
    var speed = 50;
    container.innerHTML = '';
    
    function addChar() {
        if (i < text.length) {
            container.innerHTML += text.charAt(i);
            i++;
            setTimeout(() => addChar(), speed); 
        } else if (callback) {
            callback();
        }
    }
    addChar();
}

// 开启新对话时，清空消息区和localStorage中保存的当前对话ID
document.getElementById('deepseek-new-chat').addEventListener('click', function() {
    document.getElementById('deepseek-chat-messages').innerHTML = '';
    document.getElementById('deepseek-chat-input').value = '';
    setCurrentConversationId(null);
});

// 复制按钮pre标签的函数
function addCopyButtonsToPreTags(container) {
    const preTags = container.querySelectorAll('pre');
    preTags.forEach(pre => {
        const copyButton = document.createElement('button');
        copyButton.textContent = '一键复制';
        copyButton.classList.add('pre-copy-button');
        copyButton.addEventListener('click', () => {
            const textToCopy = pre.textContent;
            navigator.clipboard.writeText(textToCopy)
              .then(() => {
                    console.log('复制成功');
                    // 修改按钮文本
                    const originalText = copyButton.textContent;
                    copyButton.textContent = '复制成功';
                    setTimeout(() => {
                        copyButton.textContent = originalText;
                    }, 1500);
                })
              .catch(err => {
                    console.error('复制失败: ', err);
                });
        });
        pre.parentNode.insertBefore(copyButton, pre.nextSibling);
    });
}

//加载历史对话记录时的内容渲染
function loadChatLog(conversationId) {
    fetch(adminAjaxUrl + '?action=deepseek_load_log&conversation_id=' + conversationId)
   .then(response => response.json())
   .then(data => {
        if (data.success) {
            var messagesContainer = document.getElementById('deepseek-chat-messages');
            messagesContainer.innerHTML = '';
            data.messages.forEach(message => {
                messagesContainer.innerHTML += '<div class="message-bubble user">' + message.message + '</div>';
                const botMessageElement = document.createElement('div');
                botMessageElement.classList.add('message-bubble', 'bot');
                botMessageElement.innerHTML = convertMarkdownToHTML(message.response);
                messagesContainer.appendChild(botMessageElement);
                // 历史消息加载时也添加复制按钮
                addCopyButtonsToPreTags(botMessageElement);
            });
            // 加载完历史对话后更新当前对话ID（同时存入localStorage）
            setCurrentConversationId(conversationId);
        }
    });
}

// 绑定历史对话框的点击事件
document.querySelectorAll('#deepseek-chat-history li').forEach(item => {
    item.addEventListener('click', function() {
        var conversationId = this.getAttribute('data-conversation-id');
        loadChatLog(conversationId);
    });

    var deleteButton = item.querySelector('.deepseek-delete-log');
    if (deleteButton) {
        deleteButton.addEventListener('click', function() {
            var conversationId = this.getAttribute('data-conversation-id');
            fetch(adminAjaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=deepseek_delete_log&conversation_id=' + conversationId
            }).then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.parentElement.remove();
                    document.getElementById('deepseek-chat-messages').innerHTML = '';
                    setCurrentConversationId(null);
                }
            });
        });
    }
});

// 自定义提示词点击事件，点击后自动将提示词加上冒号插入输入框
document.addEventListener('DOMContentLoaded', function() {
    var prompts = document.querySelectorAll('.deepseek-prompt');
    prompts.forEach(function(prompt) {
        prompt.addEventListener('click', function() {
            var inputBox = document.getElementById('deepseek-chat-input');
            if (inputBox) {
                var promptText = this.textContent.trim();
                // 输入框内容预置提示词和冒号
                if (!inputBox.value.startsWith(promptText + ':')) {
                    inputBox.value = promptText + ': ' + inputBox.value;
                }
                inputBox.focus();
            }
        });
    });
});
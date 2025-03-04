// 将Markdown文本转换为HTML
function convertMarkdownToHTML(markdownText) {
    return marked.parse(markdownText);
}

// 通过DEEPSEEK_VARS对象访问变量
var aiVoiceEnabled = parseInt(DEEPSEEK_VARS.AI_VOICE_ENABLED);
var deepseek_rest_nonce = DEEPSEEK_VARS.REST_NONCE;
var restUrl = DEEPSEEK_VARS.REST_URL;
var adminAjaxUrl = DEEPSEEK_VARS.ADMIN_AJAX_URL;
var enableKeywordDetection = parseInt(DEEPSEEK_VARS.ENABLE_KEYWORD_DETECTION);
var keywords = DEEPSEEK_VARS.KEYWORDS.split(',');
var file_upload_nonce = DEEPSEEK_VARS.FILE_UPLOAD_NONCE;

// 全局变量
var currentConversationId = null;
var currentAppId = null;
var showingAgents = false;
var currentPage = 'home';
let uploadedFiles = [];

// 设置当前页面状态
function setCurrentPage(page) {
    currentPage = page;
    localStorage.setItem('currentPage', page);
    toggleClearButtonVisibility();
}

// 设置普通对话 ID
function setCurrentConversationId(id) {
    currentConversationId = id;
    if (id) {
        localStorage.setItem('currentConversationId', id);
        setCurrentPage('conversation');
    } else {
        localStorage.removeItem('currentConversationId');
        setCurrentPage('home');
    }
    currentAppId = null;
    localStorage.removeItem('currentAppId');
}

// 设置智能体应用 ID
function setCurrentAppId(appId) {
    currentAppId = appId;
    if (appId) {
        localStorage.setItem('currentAppId', appId);
        setCurrentPage('agent');
    } else {
        localStorage.removeItem('currentAppId');
        setCurrentPage('home');
    }
    currentConversationId = null;
    localStorage.removeItem('currentConversationId');
}

// 显示清除对话按钮的可见性
function toggleClearButtonVisibility() {
    var clearButton = document.getElementById('clear-conversation-button');
    if (clearButton) {
        clearButton.style.display = (currentConversationId || currentAppId) ? 'block' : 'none';
    }
}

// 页面加载时恢复状态并初始化模型选择
window.addEventListener('load', function() {
    var storedConversationId = localStorage.getItem('currentConversationId');
    var storedAppId = localStorage.getItem('currentAppId');
    var storedShowingAgents = localStorage.getItem('showingAgents') === 'true';
    var storedPage = localStorage.getItem('currentPage') || 'home';

    currentPage = storedPage;

    if (storedPage === 'conversation' && storedConversationId) {
        setCurrentConversationId(storedConversationId);
        loadChatLog(storedConversationId);
    } else if (storedPage === 'agent' && storedAppId) {
        setCurrentAppId(storedAppId);
        loadAgentChat(storedAppId);
    } else if (storedPage === 'agentList' || storedShowingAgents) {
        showingAgents = true;
        setCurrentPage('agentList');
        loadAgentList();
    }

    var enableSearchSwitch = document.getElementById('enable-search');
    if (enableSearchSwitch) {
        var storedSearchState = localStorage.getItem('enableSearchState');
        enableSearchSwitch.checked = storedSearchState === 'true';
    }

    // 初始化模型选择框
    updateModelSelectOptions();
});

// 默认提示
document.getElementById('deepseek-chat-input').addEventListener('input', function() {
    var prompt = document.getElementById('chatbot-prompt');
    if (prompt) {
        prompt.style.display = 'none';
    }
    var customPrompts = document.getElementById('deepseek-custom-prompts');
    if (customPrompts) {
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

    if (sendButton && inputField) {
        sendButton.addEventListener('click', function(event) {
            const message = inputField.value.trim();
            if (enableKeywordDetection && containsForbiddenKeyword(message)) {
                if (errorMessage) errorMessage.style.display = 'block';
                event.preventDefault();
            } else {
                if (errorMessage) errorMessage.style.display = 'none';
                handleSendMessage();
            }
        });
    }
});

// 添加语音播放功能
function addVoicePlayback(container, text) {
    if (!aiVoiceEnabled) return;

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
        var existingMessage = playIcon.nextElementSibling;
        if (existingMessage && existingMessage.classList.contains('tts-message')) {
            existingMessage.remove();
        }
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
        dataParams.append('text', text);
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
                    audioElem.onplay = function() { messageSpan.remove(); };
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
    container.appendChild(playIcon);
}

// 自定义通知
function showCustomNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `custom-notification ${type}`;
    notification.textContent = message;
    document.body.appendChild(notification);
    setTimeout(() => notification.remove(), 3000);
}

// 添加接口切换的AJAX处理
document.addEventListener('DOMContentLoaded', function() {
    var form = document.getElementById('interface-switch-form');
    var select = document.getElementById('chat-interface-select');
    
    if (form && select) {
        select.addEventListener('change', function(e) {
            e.preventDefault();
            
            var formData = new FormData();
            formData.append('action', 'deepseek_switch_interface');
            formData.append('selected_interface', this.value);
            formData.append('nonce', ajax_nonce);

            fetch(ajax_url, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('接口切换成功:', data.data);
                    showCustomNotification('已切换到 ' + this.options[this.selectedIndex].text, 'success');
                    updateModelSelectOptions(); // 更新模型参数选项
                } else {
                    console.error('接口切换失败:', data.data);
                    showCustomNotification('接口切换失败', 'error');
                }
            })
            .catch(error => {
                console.error('接口切换请求失败:', error);
                showCustomNotification('接口切换请求失败', 'error');
            });
        });
    }
});

// 更新模型选择框选项
function updateModelSelectOptions() {
    var interfaceSelect = document.getElementById('chat-interface-select');
    var modelSelect = document.getElementById('chat-model-select');
    if (!interfaceSelect || !modelSelect) return;

    var selectedInterface = interfaceSelect.value;
    var models = model_params[selectedInterface] ? model_params[selectedInterface].split(',').map(m => m.trim()) : [];
    
    modelSelect.innerHTML = '';
    models.forEach((model) => {
        var option = document.createElement('option');
        option.value = model;
        option.textContent = model;
        modelSelect.appendChild(option);
    });
}

// 文件上传处理
document.addEventListener('DOMContentLoaded', function() {
    const uploadBtn = document.getElementById('deepseek-upload-file-btn');
    const fileInput = document.getElementById('deepseek-file-input');
    const filesList = document.getElementById('uploaded-files-list');

    if (uploadBtn && fileInput) {
        uploadBtn.addEventListener('click', function() {
            fileInput.click();
        });

        fileInput.addEventListener('change', function() {
            const files = Array.from(fileInput.files);
            const interfaceSelect = document.getElementById('chat-interface-select');
            const modelSelect = document.getElementById('chat-model-select');
            const currentInterface = interfaceSelect ? interfaceSelect.value : 'qwen';
            const currentModel = modelSelect ? modelSelect.value : '';

            files.forEach(file => {
                const formData = new FormData();
                formData.append('action', 'deepseek_upload_file');
                formData.append('file', file);
                formData.append('nonce', file_upload_nonce);
                formData.append('interface', currentInterface);
                formData.append('model', currentModel);

                fetch(adminAjaxUrl, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        uploadedFiles.push({
                            file_id: data.data.file_id,
                            filename: data.data.filename,
                            interface: data.data.interface
                        });
                        if (uploadedFiles.length > 0) {
                            filesList.style.display = 'flex';
                        }
                        updateUploadedFilesList();
                    } else {
                        let errorMsg = '文件上传失败';
                        if (data.data && data.data.message) {
                            errorMsg = data.data.message;
                            if (data.data.type === 'invalid_type') {
                                errorMsg += ` 支持的类型: ${data.data.allowed_types}`;
                            }
                        }
                        showCustomNotification(errorMsg, 'error');
                    }
                })
                .catch(error => {
                    console.error('文件上传错误:', error);
                    showCustomNotification('文件上传错误: 网络问题', 'error');
                });
            });
            fileInput.value = '';
        });
    }
});

// 更新已上传文件列表
function updateUploadedFilesList() {
    const filesList = document.getElementById('uploaded-files-list');
    filesList.innerHTML = '';
    uploadedFiles.forEach((file, index) => {
        const fileItem = document.createElement('div');
        fileItem.textContent = file.filename;
        const removeBtn = document.createElement('button');
        removeBtn.textContent = '删除';
        removeBtn.addEventListener('click', () => {
            uploadedFiles.splice(index, 1);
            updateUploadedFilesList();
            if (uploadedFiles.length === 0) {
                filesList.style.display = 'none';
            }
        });
        fileItem.appendChild(removeBtn);
        filesList.appendChild(fileItem);
    });
}

// 处理发送消息接口
function handleSendMessage() {
    var message = document.getElementById('deepseek-chat-input').value.trim();
    if (!message && uploadedFiles.length === 0) return;

    if (currentAppId) {
        sendAgentMessage(message, currentAppId);
    } else {
        fetch(adminAjaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=deepseek_get_current_interface'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const currentInterface = data.interface;
                const modelSelect = document.getElementById('chat-model-select');
                const currentModel = modelSelect ? modelSelect.value : '';
                sendMessage(message, currentInterface, currentModel);
            } else {
                console.error('获取当前接口失败:', data.message);
                showCustomNotification('获取当前接口失败', 'error');
            }
        })
        .catch(error => {
            console.error('获取接口失败:', error);
            showCustomNotification('获取接口失败', 'error');
        });
    }
}

// 截断字符
function truncateText(text, maxLength) {
    if (!text) return '';
    return text.length > maxLength ? text.substring(0, maxLength) + '...' : text;
}

// 发送消息
function sendMessage(message, currentInterface, currentModel) {
var newConversation = !currentConversationId;
    var currentMessage = message;

    var messagesContainer = document.getElementById('deepseek-chat-messages');
    var thinkingMessage = document.createElement('div');
    thinkingMessage.id = 'deepseek-thinking-message';
    thinkingMessage.className = 'message-bubble bot';
    thinkingMessage.innerHTML = '小助手正在思考中...';
    messagesContainer.appendChild(thinkingMessage);

    var enableSearchSwitch = document.getElementById('enable-search');
    var enableSearch = enableSearchSwitch ? enableSearchSwitch.checked : false;

    // 立即显示用户消息
    if (message || uploadedFiles.length > 0) {
        var userMessageContainer = document.createElement('div');
        userMessageContainer.classList.add('message-bubble', 'user');
        if (message) {
            userMessageContainer.textContent = message;
        }
        if (uploadedFiles.length > 0) {
            var fileList = document.createElement('div');
            fileList.className = 'uploaded-files-preview';
            uploadedFiles.forEach(file => {
                var fileItem = document.createElement('span');
                fileItem.textContent = `已上传: ${file.filename}`;
                fileList.appendChild(fileItem);
            });
            userMessageContainer.appendChild(fileList);
        }
        thinkingMessage.insertAdjacentElement('beforebegin', userMessageContainer);
        messagesContainer.scrollTop = messagesContainer.scrollHeight; // 确保滚动到底部
    }

    fetch(restUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': deepseek_rest_nonce
        },
        body: JSON.stringify({
            message: message,
            conversation_id: currentConversationId,
            enable_search: enableSearch,
            file_ids: uploadedFiles.length > 0 ? JSON.stringify(uploadedFiles) : null,
            interface: currentInterface,
            model: currentModel
        })
    })
    .then(response => {
        const contentType = response.headers.get('Content-Type') || '';
        if (contentType.indexOf('application/json') !== -1) {
            return response.json().then(data => ({ data, isJson: true }));
        } else {
            return { response };
        }
    })
    .then(result => {
        if (result.isJson) {
            var data = result.data;
            if (!data.success) {
                thinkingMessage.innerHTML = data.message || '请求失败';
                return;
            }
            // 处理Pollinations文生图
            if (data.success && data.is_pollinations_image) {
                thinkingMessage.remove();
                var botMessageContainer = document.createElement('div');
                botMessageContainer.classList.add('message-bubble', 'bot');
                var img = document.createElement('img');
                img.src = data.image_url;
                img.alt = data.conversation_title || 'Generated Image';
                img.style.maxWidth = '100%';
                botMessageContainer.appendChild(img);

                var promptText = document.createElement('p');
                promptText.textContent = '生成提示词: ' + data.conversation_title;
                botMessageContainer.appendChild(promptText);

                messagesContainer.appendChild(botMessageContainer);
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
                document.getElementById('deepseek-chat-input').value = '';

                // 确保新对话时历史记录立即更新
                if (!currentConversationId) {
                    var historyContainer = document.querySelector('#deepseek-chat-history ul');
                    var newChatItem = document.createElement('li');
                    newChatItem.setAttribute('data-conversation-id', data.conversation_id);
                    newChatItem.innerHTML = '<span class="deepseek-chat-title">' + truncateText(data.conversation_title, 6) + '</span>' +
                        '<button class="deepseek-delete-log" data-conversation-id="' + data.conversation_id + '">删除</button>';
                    historyContainer.insertBefore(newChatItem, historyContainer.firstChild);

                    newChatItem.addEventListener('click', function() {
                        loadChatLog(data.conversation_id);
                    });

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

                    setCurrentConversationId(data.conversation_id);
                }
            }
            // 处理通义千问文生图
            else if (data.success && data.is_image) {
                handleImageGeneration(data.task_id);
                if (!currentConversationId) {
                    var historyContainer = document.querySelector('#deepseek-chat-history ul');
                    var newChatItem = document.createElement('li');
                    newChatItem.setAttribute('data-conversation-id', data.conversation_id);
                    newChatItem.innerHTML = '<span class="deepseek-chat-title">' + truncateText(data.conversation_title, 6) + '</span>' +
                        '<button class="deepseek-delete-log" data-conversation-id="' + data.conversation_id + '">删除</button>';
                    historyContainer.insertBefore(newChatItem, historyContainer.firstChild);

                    newChatItem.addEventListener('click', function() {
                        loadChatLog(data.conversation_id);
                    });

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

                    setCurrentConversationId(data.conversation_id);
                }
            }
        } else {
            thinkingMessage.remove();

            var botMessageContainer = document.createElement('div');
            botMessageContainer.classList.add('message-bubble', 'bot');

            var reasoningContainer = document.createElement('div');
            reasoningContainer.classList.add('reasoning-content');
            reasoningContainer.innerHTML = '<strong>推理过程：</strong><br>';
            reasoningContainer.style.display = 'none';

            var contentContainer = document.createElement('div');
            contentContainer.classList.add('final-content');

            botMessageContainer.appendChild(reasoningContainer);
            botMessageContainer.appendChild(contentContainer);
            messagesContainer.appendChild(botMessageContainer);

            const reader = result.response.body.getReader();
            const decoder = new TextDecoder();
            let reasoningReply = '';
            let contentReply = '';

            function processStream() {
                reader.read().then(({ done, value }) => {
                    if (done) {
                        if (reasoningReply) {
                            reasoningContainer.innerHTML = '<strong>推理过程：</strong><br>' + convertMarkdownToHTML(reasoningReply);
                            reasoningContainer.style.display = 'block';
                        }
                        contentContainer.innerHTML = convertMarkdownToHTML(contentReply);
                        addCopyButtonsToPreTags(botMessageContainer);
                        addVoicePlayback(botMessageContainer, contentReply);
                        messagesContainer.scrollTop = messagesContainer.scrollHeight;
                        document.getElementById('deepseek-chat-input').value = '';
                        // uploadedFiles = []; // 清空已上传文件
                        updateUploadedFilesList();
                        return;
                    }
                    const chunk = decoder.decode(value, { stream: true });
                    const lines = chunk.split('\n');
                    lines.forEach(line => {
                        line = line.trim();
                        if (line.startsWith('data: ')) {
                            const dataPart = line.substring(6).trim();
                            if (dataPart === '[DONE]') return;
                            try {
                                const jsonData = JSON.parse(dataPart);
                                if (jsonData.conversation_id) {
                                    if (newConversation) {
                                        setCurrentConversationId(jsonData.conversation_id);
                                        var historyContainer = document.querySelector('#deepseek-chat-history ul');
                                        var newChatItem = document.createElement('li');
                                        newChatItem.setAttribute('data-conversation-id', jsonData.conversation_id);
                                        newChatItem.innerHTML = '<span class="deepseek-chat-title">' + truncateText(currentMessage, 6) + '</span>' +
                                            '<button class="deepseek-delete-log" data-conversation-id="' + jsonData.conversation_id + '">删除</button>';
                                        historyContainer.insertBefore(newChatItem, historyContainer.firstChild);

                                        newChatItem.addEventListener('click', function() {
                                            loadChatLog(jsonData.conversation_id);
                                        });

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
                                        newConversation = false;
                                    }
                                    return;
                                }
                                if (jsonData.choices && jsonData.choices.length > 0) {
                                    let delta = jsonData.choices[0].delta;
                                    if (delta.reasoning_content) {
                                        reasoningReply += delta.reasoning_content;
                                        reasoningContainer.innerHTML = '<strong>推理过程：</strong><br>' + convertMarkdownToHTML(reasoningReply);
                                        reasoningContainer.style.display = 'block';
                                    }
                                    if (delta.content) {
                                        contentReply += delta.content;
                                        contentContainer.innerHTML = convertMarkdownToHTML(contentReply);
                                    }
                                    messagesContainer.scrollTop = messagesContainer.scrollHeight;
                                }
                            } catch (e) {
                                console.error('解析SSE错误:', e, '原始数据:', dataPart);
                            }
                        }
                    });
                    processStream();
                });
            }
            processStream();
        }
    })
    .catch(error => {
        console.error('Fetch request failed:', error);
        var errorMsg = document.getElementById('deepseek-thinking-message');
        if (errorMsg) errorMsg.innerHTML = '网络错误，请稍后重试';
    });
}


// 处理图像生成
function handleImageGeneration(taskId) {
    var messagesContainer = document.getElementById('deepseek-chat-messages');
    var imageMessage = document.getElementById('deepseek-thinking-message');
    imageMessage.innerHTML = '图片生成中...';

    var checkStatus = setInterval(() => {
        fetch(adminAjaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=deepseek_check_image_task&task_id=' + taskId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.task_status === 'SUCCEEDED') {
                clearInterval(checkStatus);
                imageMessage.remove();

                var botMessageContainer = document.createElement('div');
                botMessageContainer.classList.add('message-bubble', 'bot');
                var img = document.createElement('img');
                img.src = data.image_url;
                img.alt = data.actual_prompt || 'Generated Image';
                img.style.maxWidth = '100%';
                botMessageContainer.appendChild(img);

                var promptText = document.createElement('p');
                promptText.textContent = '生成提示词: ' + data.actual_prompt;
                botMessageContainer.appendChild(promptText);

                messagesContainer.appendChild(botMessageContainer);
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
                document.getElementById('deepseek-chat-input').value = '';
            } else if (!data.success) {
                clearInterval(checkStatus);
                imageMessage.innerHTML = '图片生成失败';
            }
        })
        .catch(error => {
            console.error('检查任务状态失败:', error);
            clearInterval(checkStatus);
            imageMessage.innerHTML = '检查任务状态失败';
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

// 开启新对话
document.getElementById('deepseek-new-chat').addEventListener('click', function() {
    document.getElementById('deepseek-chat-messages').innerHTML = '';
    document.getElementById('deepseek-chat-input').value = '';
    setCurrentConversationId(null);
    showingAgents = false;
    localStorage.setItem('showingAgents', 'false');
    setCurrentPage('home');
});

// 开关变化时的保存
document.addEventListener('change', function(event) {
    if (event.target.id === 'enable-search') {
        localStorage.setItem('enableSearchState', event.target.checked);
    }
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
                const originalText = copyButton.textContent;
                copyButton.textContent = '复制成功';
                setTimeout(() => { copyButton.textContent = originalText; }, 1500);
            })
            .catch(err => console.error('复制失败: ', err));
        });
        pre.parentNode.insertBefore(copyButton, pre.nextSibling);
    });
}

// 加载历史对话记录
function loadChatLog(conversationId) {
    fetch(adminAjaxUrl + '?action=deepseek_load_log&conversation_id=' + conversationId)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            var messagesContainer = document.getElementById('deepseek-chat-messages');
            messagesContainer.innerHTML = '';
            const fragment = document.createDocumentFragment();
            data.messages.forEach(message => {
                const userMessage = document.createElement('div');
                userMessage.classList.add('message-bubble', 'user');
                userMessage.textContent = message.message;
                fragment.appendChild(userMessage);

                const botMessageElement = document.createElement('div');
                botMessageElement.classList.add('message-bubble', 'bot');
                if (typeof message.response === 'object') {
                    const { content, reasoning_content } = message.response;
                    let html = '';
                    if (reasoning_content) {
                        html += '<div class="reasoning-content"><strong>推理过程：</strong><br>' + convertMarkdownToHTML(reasoning_content) + '</div>';
                    }
                    if (content) {
                        html += '<div class="final-content">' + convertMarkdownToHTML(content) + '</div>';
                    }
                    botMessageElement.innerHTML = html;
                } else {
                    botMessageElement.innerHTML = message.response;
                }
                fragment.appendChild(botMessageElement);
                addCopyButtonsToPreTags(botMessageElement);
                const responseText = botMessageElement.textContent;
                addVoicePlayback(botMessageElement, responseText);
            });
            messagesContainer.appendChild(fragment);
            setCurrentConversationId(conversationId);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }
    })
    .catch(error => {
        console.error('加载对话历史失败:', error);
        var messagesContainer = document.getElementById('deepseek-chat-messages');
        messagesContainer.innerHTML = '<div class="message-bubble bot">加载对话历史失败，请稍后重试</div>';
    });
}

// 绑定历史对话框的点击事件
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('#deepseek-chat-history li').forEach(item => {
        item.addEventListener('click', function() {
            var conversationId = this.getAttribute('data-conversation-id');
            loadChatLog(conversationId);
        });

        var deleteButton = item.querySelector('.deepseek-delete-log');
        if (deleteButton) {
            deleteButton.addEventListener('click', function(e) {
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
        }
    });
});

// 自定义提示词点击事件
document.addEventListener('DOMContentLoaded', function() {
    var prompts = document.querySelectorAll('.deepseek-prompt');
    prompts.forEach(function(prompt) {
        prompt.addEventListener('click', function() {
            var inputBox = document.getElementById('deepseek-chat-input');
            if (inputBox) {
                var promptText = this.textContent.trim();
                if (!inputBox.value.startsWith(promptText + ':')) {
                    inputBox.value = promptText + ': ' + inputBox.value;
                }
                inputBox.focus();
            }
        });
    });
});

// 加载智能体应用列表
function loadAgentList() {
    fetch(adminAjaxUrl + '?action=deepseek_get_agents')
    .then(response => response.json())
    .then(data => {
        var messagesContainer = document.getElementById('deepseek-chat-messages');
        messagesContainer.innerHTML = '';
        var agentContainer = document.createElement('div');
        agentContainer.className = 'agent-list';

        if (data.success && data.data && data.data.agents && Array.isArray(data.data.agents)) {
            if (data.data.agents.length === 0) {
                messagesContainer.innerHTML = '<div class="message-bubble bot">暂无智能体应用</div>';
            } else {
                data.data.agents.forEach(agent => {
                    var agentItem = document.createElement('div');
                    agentItem.className = 'agent-item';
                    agentItem.setAttribute('data-app-id', agent.app_id);
                    agentItem.innerHTML = `
                        <img src="${agent.icon || ''}" alt="${agent.name}" class="agent-icon">
                        <div class="agent-info">
                            <span class="agent-name">${agent.name}</span>
                            <p class="agent-description">${agent.description || '暂无描述'}</p>
                        </div>
                    `;
                    agentItem.addEventListener('click', function() {
                        loadAgentChat(agent.app_id);
                    });
                    agentContainer.appendChild(agentItem);
                });
                messagesContainer.appendChild(agentContainer);
                showingAgents = true;
                setCurrentPage('agentList');
                localStorage.setItem('showingAgents', 'true');
            }
        } else {
            messagesContainer.innerHTML = '<div class="message-bubble bot">加载智能体应用失败，请检查后台配置</div>';
            console.error('后端返回数据无效:', data);
        }
    })
    .catch(error => {
        console.error('加载智能体应用列表失败:', error);
        var messagesContainer = document.getElementById('deepseek-chat-messages');
        messagesContainer.innerHTML = '<div class="message-bubble bot">加载智能体应用失败，请稍后重试</div>';
    });
}

// 点击“智能体”标题切换显示
document.addEventListener('DOMContentLoaded', function() {
    var agentToggle = document.getElementById('deepseek-agent-title');
    if (agentToggle) {
        agentToggle.addEventListener('click', function() {
            var messagesContainer = document.getElementById('deepseek-chat-messages');
            if (showingAgents) {
                showingAgents = false;
                localStorage.setItem('showingAgents', 'false');
                setCurrentPage('home');
                messagesContainer.innerHTML = '<div class="message-bubble bot" id="chatbot-prompt">你好，我可以帮你写作、写文案、翻译，有问题请问我~</div>';
                var customPrompts = document.getElementById('deepseek-custom-prompts');
                if (customPrompts) {
                    customPrompts.style.display = 'block';
                }
                setCurrentConversationId(null);
            } else {
                loadAgentList();
            }
        });
    }
});

// 显示清除确认框
function showClearConfirmation(container) {
    const overlay = document.createElement('div');
    overlay.classList.add('confirmation-overlay');

    const confirmationDialog = document.createElement('div');
    confirmationDialog.classList.add('confirmation-dialog');
    confirmationDialog.innerHTML = `
        <div class="dialog-content">
            <p>确定要清除对话吗？删除后不可恢复！</p>
            <button class="confirm-clear">确认</button>
            <button class="cancel-clear">取消</button>
        </div>
    `;

    document.body.appendChild(overlay);
    document.body.appendChild(confirmationDialog);

    confirmationDialog.querySelector('.confirm-clear').addEventListener('click', function() {
        clearConversation(container);
        overlay.remove();
        confirmationDialog.remove();
    });

    confirmationDialog.querySelector('.cancel-clear').addEventListener('click', function() {
        overlay.remove();
        confirmationDialog.remove();
    });

    overlay.addEventListener('click', function() {
        overlay.remove();
        confirmationDialog.remove();
    });
}

// 显示自定义提示框
function showCustomNotification(message, type = 'error') {
    const notification = document.createElement('div');
    notification.className = `custom-notification ${type}`;
    notification.textContent = message;

    const closeButton = document.createElement('span');
    closeButton.className = 'close-notification';
    closeButton.innerHTML = '×';
    closeButton.onclick = () => notification.remove();
    notification.appendChild(closeButton);

    document.body.appendChild(notification);

    setTimeout(() => {
        notification.remove();
    }, 2000);
}

// 清除对话
function clearConversation(container) {
    const appId = currentAppId;
    if (!appId) {
        showCustomNotification('未选择智能体应用，无法清除对话');
        return;
    }

    fetch(adminAjaxUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=deepseek_clear_agent_conversation&app_id=' + encodeURIComponent(appId)
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('网络响应错误: ' + response.status);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            container.innerHTML = '';
            loadAgentChat(appId);
            showCustomNotification('对话记录清除成功', 'success');
            console.log('对话记录清除成功');
        } else {
            showCustomNotification('清除对话失败: ' + (data.message || '未知错误'));
        }
    })
    .catch(error => {
        console.error('清除对话请求失败:', error);
        showCustomNotification('网络错误，请稍后重试');
    });
}

// 加载智能体对话历史
function loadAgentChat(appId) {
    var messagesContainer = document.getElementById('deepseek-chat-messages');
    messagesContainer.innerHTML = '';

    Promise.all([
        fetch(adminAjaxUrl + '?action=deepseek_load_agent_log&app_id=' + appId).then(response => response.json()),
        fetch(adminAjaxUrl + '?action=deepseek_get_agents').then(response => response.json())
    ])
    .then(([chatData, agentData]) => {
        let agent = null;
        if (agentData.success && agentData.data && agentData.data.agents) {
            agent = agentData.data.agents.find(a => a.app_id === appId);
            if (agent) {
                var headerContainer = document.createElement('div');
                headerContainer.className = 'agent-header';
                headerContainer.innerHTML = `
                    <img src="${agent.icon || ''}" alt="${agent.name}" class="agent-icon">
                    <span class="agent-name">${agent.name}</span>
                `;
                messagesContainer.appendChild(headerContainer);
            } else {
                messagesContainer.innerHTML += '<div class="message-bubble bot">未找到该智能体详情</div>';
            }
        } else {
            messagesContainer.innerHTML += '<div class="message-bubble bot">加载智能体详情失败，请检查配置</div>';
        }

        if (chatData.success && chatData.data && chatData.data.messages && Array.isArray(chatData.data.messages)) {
            if (chatData.data.messages.length > 0) {
                chatData.data.messages.forEach(message => {
                    if (message.message) {
                        messagesContainer.innerHTML += '<div class="message-bubble user">' + message.message + '</div>';
                    }
                    if (message.response) {
                        var botMessage = document.createElement('div');
                        botMessage.classList.add('message-bubble', 'bot');
                        botMessage.innerHTML = convertMarkdownToHTML(message.response);
                        messagesContainer.appendChild(botMessage);
                        addCopyButtonsToPreTags(botMessage);
                        addVoicePlayback(botMessage, message.response);
                    }
                });
            } else {
                messagesContainer.innerHTML += '<div class="message-bubble bot">欢迎使用智能体应用对话，请输入消息开始。</div>';
                if (agent && agent.opening_questions && agent.opening_questions.length > 0) {
                    var promptHint = document.createElement('div');
                    promptHint.className = 'message-bubble bot prompt-hint';
                    promptHint.textContent = '你可以这样问我';
                    messagesContainer.appendChild(promptHint);

                    var questionsContainer = document.createElement('div');
                    questionsContainer.className = 'opening-questions';
                    agent.opening_questions.forEach(question => {
                        var questionItem = document.createElement('div');
                        questionItem.className = 'opening-question';
                        questionItem.textContent = question;
                        questionItem.addEventListener('click', function() {
                            sendAgentMessage(question, appId);
                        });
                        questionsContainer.appendChild(questionItem);
                    });
                    messagesContainer.appendChild(questionsContainer);
                }
            }
        } else {
            messagesContainer.innerHTML += '<div class="message-bubble bot">加载对话历史失败，请稍后重试</div>';
        }

        setCurrentAppId(appId);
        showingAgents = false;
        localStorage.setItem('showingAgents', 'false');
        setCurrentPage('agent');
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    })
    .catch(error => {
        console.error('加载智能体对话或详情失败:', error);
        messagesContainer.innerHTML = '<div class="message-bubble bot">网络错误，请稍后重试</div>';
    });
}

// 发送智能体消息
function sendAgentMessage(message, appId) {
    var messagesContainer = document.getElementById('deepseek-chat-messages');
    
    if (!messagesContainer.querySelector('.agent-header')) {
        fetch(adminAjaxUrl + '?action=deepseek_get_agents')
        .then(response => response.json())
        .then(agentData => {
            if (agentData.success && agentData.data && agentData.data.agents) {
                const agent = agentData.data.agents.find(a => a.app_id === appId);
                if (agent) {
                    var headerContainer = document.createElement('div');
                    headerContainer.className = 'agent-header';
                    headerContainer.innerHTML = `
                        <img src="${agent.icon || ''}" alt="${agent.name}" class="agent-icon">
                        <span class="agent-name">${agent.name}</span>
                    `;
                    messagesContainer.insertBefore(headerContainer, messagesContainer.firstChild);
                    messagesContainer.scrollTop = messagesContainer.scrollHeight;
                }
            }
        });
    }

    messagesContainer.innerHTML += '<div class="message-bubble user">' + message + '</div>';
    var botMessageContainer = document.createElement('div');
    botMessageContainer.classList.add('message-bubble', 'bot');
    botMessageContainer.textContent = '智能体应用正在处理...';
    messagesContainer.appendChild(botMessageContainer);
    messagesContainer.scrollTop = messagesContainer.scrollHeight;

    const agentUrl = restUrl.replace('send-message', 'send-agent-message');

    fetch(agentUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': deepseek_rest_nonce
        },
        body: JSON.stringify({
            message: message,
            app_id: appId,
            session_id: localStorage.getItem('currentSessionId') || null
        })
    })
    .then(response => {
        if (!response.ok) throw new Error('Network response was not ok: ' + response.status);
        return response.body.getReader();
    })
    .then(reader => {
        const decoder = new TextDecoder();
        let botReply = '';

        function processStream() {
            reader.read().then(({ done, value }) => {
                if (done) {
                    botMessageContainer.innerHTML = convertMarkdownToHTML(botReply);
                    addCopyButtonsToPreTags(botMessageContainer);
                    addVoicePlayback(botMessageContainer, botReply);
                    messagesContainer.scrollTop = messagesContainer.scrollHeight;
                    document.getElementById('deepseek-chat-input').value = '';
                    return;
                }
                const chunk = decoder.decode(value, { stream: true });
                const lines = chunk.split('\n');
                lines.forEach(line => {
                    line = line.trim();
                    if (line.startsWith('data: ')) {
                        const dataPart = line.substring(6).trim();
                        if (dataPart === '[DONE]') return;
                        try {
                            const jsonData = JSON.parse(dataPart);
                            if (jsonData.error) {
                                botMessageContainer.textContent = '错误: ' + jsonData.error;
                                messagesContainer.scrollTop = messagesContainer.scrollHeight;
                                return;
                            }
                            if (jsonData.text) {
                                botReply += jsonData.text;
                                botMessageContainer.innerHTML = convertMarkdownToHTML(botReply);
                                botMessageContainer.scrollIntoView({ behavior: 'smooth', block: 'end' });
                            }
                        } catch (e) {
                            console.error('解析 SSE 数据错误:', e, '原始数据:', dataPart);
                        }
                    }
                });
                processStream();
            });
        }
        processStream();
    })
    .catch(error => {
        console.error('发送智能体应用消息失败:', error);
        botMessageContainer.textContent = '网络错误，请稍后重试';
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    });
}

// 控制清除对话按钮的显示
function toggleClearButtonVisibility() {
    const clearButton = document.getElementById('clear-conversation-button');
    if (clearButton) {
        clearButton.style.display = currentPage === 'agent' ? 'block' : 'none';
    }
}

// 在页面加载时初始化按钮显示
window.addEventListener('load', function() {
    toggleClearButtonVisibility();
});
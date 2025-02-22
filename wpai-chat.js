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

// 全局变量
var currentConversationId = null; // 普通对话ID
var currentAppId = null;          // 智能体应用ID
var showingAgents = false;        // 是否显示智能体应用列表
var currentPage = 'home';         // 当前页面状态：'home', 'conversation', 'agent', 'agentList'

// 设置当前页面状态
function setCurrentPage(page) {
    currentPage = page;
    localStorage.setItem('currentPage', page);
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

// 页面加载时恢复状态
window.addEventListener('load', function() {
    var storedConversationId = localStorage.getItem('currentConversationId');
    var storedAppId = localStorage.getItem('currentAppId');
    var storedShowingAgents = localStorage.getItem('showingAgents') === 'true';
    var storedPage = localStorage.getItem('currentPage') || 'home';

    currentPage = storedPage;

    // 只在需要恢复特定状态时操作，不干扰首页
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
    // 如果是 'home'，不做任何操作，保持首页原始状态

    var enableSearchSwitch = document.getElementById('enable-search');
    if (enableSearchSwitch) {
        var storedSearchState = localStorage.getItem('enableSearchState');
        enableSearchSwitch.checked = storedSearchState === 'true';
    }
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

// 发送消息
document.getElementById('deepseek-chat-send').addEventListener('click', function() {
    var message = document.getElementById('deepseek-chat-input').value;
    if (!message) return;

    if (currentAppId) {
        sendAgentMessage(message, currentAppId);
    } else {
        var newConversation = !currentConversationId;
        var currentMessage = message;

        var thinkingMessage = document.createElement('div');
        thinkingMessage.id = 'deepseek-thinking-message';
        thinkingMessage.className = 'message-bubble bot';
        thinkingMessage.innerHTML = '小助手正在思考中...';
        document.getElementById('deepseek-chat-messages').appendChild(thinkingMessage);

        var enableSearchSwitch = document.getElementById('enable-search');
        var enableSearch = enableSearchSwitch ? enableSearchSwitch.checked : false;
        if (enableSearchSwitch) {
            localStorage.setItem('enableSearchState', enableSearch);
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
                enable_search: enableSearch
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
                if (data.success) {
                    if (data.is_image) {
                        handleImageGeneration(data.task_id);
                        if (!currentConversationId) {
                            var historyContainer = document.querySelector('#deepseek-chat-history ul');
                            var newChatItem = document.createElement('li');
                            newChatItem.setAttribute('data-conversation-id', data.conversation_id);
                            newChatItem.innerHTML = '<span class="deepseek-chat-title">' + data.conversation_title + '</span>' +
                                '<button class="deepseek-delete-log" data-conversation-id="' + data.conversation_id + '">删除</button>';
                            historyContainer.insertBefore(newChatItem, historyContainer.firstChild);

                            newChatItem.addEventListener('click', function() {
                                loadChatLog(data.conversation_id);
                            });

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
                                        document.getElementById('deepseek-chat-messages').innerHTML = '';
                                        setCurrentConversationId(null);
                                    }
                                });
                            });

                            setCurrentConversationId(data.conversation_id);
                        }
                    }
                }
            } else {
                var messagesContainer = document.getElementById('deepseek-chat-messages');
                thinkingMessage.remove();
                messagesContainer.innerHTML += '<div class="message-bubble user">' + message + '</div>';
                var botMessageContainer = document.createElement('div');
                botMessageContainer.classList.add('message-bubble', 'bot');
                botMessageContainer.textContent = '';
                messagesContainer.appendChild(botMessageContainer);

                const reader = result.response.body.getReader();
                const decoder = new TextDecoder();
                let botReply = '';

                function processStream() {
                    reader.read().then(({done, value}) => {
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
                                    if (jsonData.conversation_id) {
                                        if (newConversation) {
                                            setCurrentConversationId(jsonData.conversation_id);
                                            var historyContainer = document.querySelector('#deepseek-chat-history ul');
                                            var newChatItem = document.createElement('li');
                                            newChatItem.setAttribute('data-conversation-id', jsonData.conversation_id);
                                            newChatItem.innerHTML = '<span class="deepseek-chat-title">' + currentMessage + '</span>' +
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
                                        } else {
                                            setCurrentConversationId(jsonData.conversation_id);
                                        }
                                        return;
                                    }
                                    if (jsonData.choices && jsonData.choices.length > 0) {
                                        let delta = jsonData.choices[0].delta;
                                        if (delta && delta.content) {
                                            botReply += delta.content;
                                            botMessageContainer.innerHTML = convertMarkdownToHTML(botReply);
                                            messagesContainer.scrollTop = messagesContainer.scrollHeight;
                                        }
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
});

// 图片生成处理函数
function handleImageGeneration(taskId) {
    var messagesContainer = document.getElementById('deepseek-chat-messages');
    var thinkingMessage = document.getElementById('deepseek-thinking-message');
    if (thinkingMessage) thinkingMessage.remove();

    var userMessage = document.createElement('div');
    userMessage.className = 'message-bubble user';
    userMessage.textContent = document.getElementById('deepseek-chat-input').value;
    messagesContainer.appendChild(userMessage);

    var loadingContainer = document.createElement('div');
    loadingContainer.className = 'message-bubble bot';
    loadingContainer.innerHTML = '图片生成中...';
    messagesContainer.appendChild(loadingContainer);

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

                var botMessage = document.createElement('div');
                botMessage.className = 'message-bubble bot';
                var promptContainer = document.createElement('div');
                promptContainer.className = 'image-prompt';
                botMessage.appendChild(promptContainer);
                var imageContainer = document.createElement('img');
                imageContainer.src = data.image_url;
                imageContainer.style.maxWidth = '100%';
                imageContainer.style.height = 'auto';
                botMessage.appendChild(imageContainer);
                messagesContainer.appendChild(botMessage);

                var actualPrompt = data.actual_prompt;
                var index = 0;
                var typingSpeed = 50;

                function typeWriter() {
                    if (index < actualPrompt.length) {
                        promptContainer.innerHTML += actualPrompt.charAt(index);
                        index++;
                        setTimeout(typeWriter, typingSpeed);
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

// 开启新对话时，清空消息区和 localStorage 中的当前对话 ID
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

// 加载历史对话记录时的内容渲染
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
                addCopyButtonsToPreTags(botMessageElement);
                addVoicePlayback(botMessageElement, message.response);
            });
            setCurrentConversationId(conversationId);
        }
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

// 加载智能体对话历史
function loadAgentChat(appId) {
    fetch(adminAjaxUrl + '?action=deepseek_load_agent_log&app_id=' + appId)
    .then(response => response.json())
    .then(data => {
        var messagesContainer = document.getElementById('deepseek-chat-messages');
        messagesContainer.innerHTML = '';
        if (data.success && data.data && data.data.messages && Array.isArray(data.data.messages)) {
            if (data.data.messages.length > 0) {
                data.data.messages.forEach(message => {
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
                messagesContainer.innerHTML = '<div class="message-bubble bot">欢迎使用智能体应用对话，请输入消息开始。</div>';
            }
        } else {
            messagesContainer.innerHTML = '<div class="message-bubble bot">加载对话历史失败，请稍后重试</div>';
        }
        setCurrentAppId(appId);
        showingAgents = false;
        localStorage.setItem('showingAgents', 'false');
        setCurrentPage('agent');
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    })
    .catch(error => {
        console.error('加载智能体应用对话失败:', error);
        var messagesContainer = document.getElementById('deepseek-chat-messages');
        messagesContainer.innerHTML = '<div class="message-bubble bot">网络错误，请稍后重试</div>';
    });
}

// 发送智能体消息
function sendAgentMessage(message, appId) {
    var messagesContainer = document.getElementById('deepseek-chat-messages');
    messagesContainer.innerHTML += '<div class="message-bubble user">' + message + '</div>';
    var botMessageContainer = document.createElement('div');
    botMessageContainer.classList.add('message-bubble', 'bot');
    botMessageContainer.textContent = '智能体应用正在处理...';
    messagesContainer.appendChild(botMessageContainer);

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
                    console.log('智能体应用对话完成:', botReply);
                    setTimeout(() => loadAgentChat(appId), 1000);
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
                                return;
                            }
                            if (jsonData.text) {
                                botReply += jsonData.text;
                                botMessageContainer.innerHTML = convertMarkdownToHTML(botReply);
                                messagesContainer.scrollTop = messagesContainer.scrollHeight;
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
    });
}
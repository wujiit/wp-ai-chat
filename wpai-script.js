jQuery(document).ready(function($) {
    // 翻译按钮点击事件
    $('.wpatai-language-switcher span.wpatai-translate-btn').on('click', function() {
        var btn = $(this);
        var language = btn.data('language');
        var container = btn.closest('.wpatai-control-panel');
        var postId = container.data('postid');
        var messageContainer = container.find('.wpatai-message');

        if (messageContainer.length === 0) {
            messageContainer = $('<div class="wpatai-message" style="color: red; margin-top: 5px;"></div>');
            container.append(messageContainer);
        }

        messageContainer.text('');
        btn.text('翻译中...');

        $.ajax({
            url: wpatai_ajax_obj.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'wpatai_translate',
                nonce: wpatai_ajax_obj.nonce,
                post_id: postId,
                target_language: language
            },
            success: function(response) {
                if (response.success) {
                    var translated_text = response.data.translated_text;
                    $('#wpatai-post-content').html(translated_text);
                } else {
                    messageContainer.text('翻译失败：' + response.data);
                }
                btn.text(language);
            },
            error: function() {
                messageContainer.text('请求错误，请重试，内容太多暂时无法翻译，请使用其他网页翻译方式。');
                btn.text(language);
            }
        });
    });

    // 语音朗读按钮点击事件（播放分段合成的语音）
    $('.wpatai-tts-btn').on('click', function() {
        var ttsBtn = $(this);
        var container = ttsBtn.closest('.wpatai-control-panel');
        var postId = container.data('postid');

        // 创建或获取消息提示容器（用于显示错误信息）
        var messageContainer = container.find('.wpatai-message');
        if (messageContainer.length === 0) {
            messageContainer = $('<div class="wpatai-message" style="color: red; margin-top: 5px;"></div>');
            container.append(messageContainer);
        }
        messageContainer.text('');

        // 显示加载状态
        ttsBtn.text('准备中...');
        ttsBtn.css('opacity', '0.6');

        $.ajax({
            url: wpatai_ajax_obj.ajax_url,
            type: 'POST',
            dataType: 'json',
            timeout: 60000, // 超时时间设置为 60 秒
            data: {
                action: 'wpatai_tts',
                nonce: wpatai_ajax_obj.tts_nonce,
                post_id: postId
            },
            success: function(response) {
                if (response.success) {
                    var audio_urls = response.data.audio_urls;
                    if (audio_urls && audio_urls.length > 0) {
                        var currentIndex = 0;
                        var audioPlayer = $('#wpatai-tts-audio');
                        if (audioPlayer.length === 0) {
                            audioPlayer = $('<audio id="wpatai-tts-audio" controls style="display:block; margin-top:10px;"></audio>');
                            container.after(audioPlayer);
                        }
                        audioPlayer.attr('src', audio_urls[currentIndex]);
                        audioPlayer.get(0).play();

                        // 当当前音频播放完毕时，播放下一段（若有）
                        audioPlayer.off('ended').on('ended', function() {
                            currentIndex++;
                            if (currentIndex < audio_urls.length) {
                                audioPlayer.attr('src', audio_urls[currentIndex]);
                                audioPlayer.get(0).play();
                            }
                        });
                    }
                } else {
                    messageContainer.text('语音合成失败：' + response.data);
                }
                ttsBtn.html('&#128266;');
                ttsBtn.css('opacity', '1');
            },
            error: function() {
                messageContainer.text('请求错误，请重试。');
                ttsBtn.html('&#128266;');
                ttsBtn.css('opacity', '1');
            }
        });
    });
});

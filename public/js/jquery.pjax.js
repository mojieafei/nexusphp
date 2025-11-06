/*!
 * jQuery PJAX - Simplified for NexusPHP
 * Version: 2.0.1
 * Based on: https://github.com/defunkt/jquery-pjax
 */
(function($) {
    
    console.log('PJAX: 库开始加载');
    
    // 主PJAX函数
    function pjax(options) {
        console.log('PJAX: pjax()被调用', options);
        
        options = $.extend(true, {}, pjax.defaults, options);
        
        var container = $(options.container);
        if (!container.length) {
            console.error('PJAX: 找不到容器', options.container);
            return false;
        }
        
        console.log('PJAX: 容器找到', container);
        
        // 触发pjax:send事件
        var sendEvent = $.Event('pjax:send');
        $(document).trigger(sendEvent, [options]);
        
        // 显示加载提示
        showLoadingIndicator();
        
        console.log('PJAX: 开始AJAX请求', options.url);
        
        // 发起AJAX请求
        var xhr = $.ajax({
            url: options.url,
            type: options.type || 'GET',
            dataType: 'html',
            timeout: options.timeout,
            success: function(data, textStatus, jqXHR) {
                console.log('PJAX: AJAX成功', options.url);
                $(document).trigger('pjax:success', [data, textStatus, jqXHR, options]);
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('PJAX: AJAX失败', textStatus, errorThrown);
                $(document).trigger('pjax:error', [jqXHR, textStatus, errorThrown, options]);
            },
            complete: function() {
                $(document).trigger('pjax:complete', [options]);
            }
        });
        
        // 更新浏览器历史
        if (options.push && window.history && window.history.pushState) {
            window.history.pushState({
                pjax: true,
                url: options.url
            }, '', options.url);
            console.log('PJAX: History已更新', options.url);
        }
        
        return xhr;
    }
    
    // 绑定pjax到链接
    $.fn.pjax = function(selector, container, options) {
        console.log('PJAX: $.fn.pjax被调用', {selector: selector, container: container});
        
        if (typeof selector === 'string' && typeof container === 'string') {
            options = options || {};
            options.container = container;
            options.selector = selector;
        } else {
            options = container || {};
            options.container = selector;
        }
        
        var $container = $(options.container);
        if (!$container.length) {
            console.error('PJAX: 容器不存在', options.container);
            return this;
        }
        
        console.log('PJAX: 绑定事件到', this.selector || 'document', '选择器:', selector);
        
        return this.on('click.pjax', selector, function(event) {
            console.log('PJAX: 链接被点击', this.href);
            
            // 忽略特殊点击
            if (event.which > 1 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                console.log('PJAX: 忽略 - 特殊键');
                return;
            }
            
            var $link = $(this);
            
            // 忽略有target的链接
            if ($link.attr('target')) {
                console.log('PJAX: 忽略 - 有target属性');
                return;
            }
            
            // 忽略被标记忽略的链接
            if ($link.attr('data-pjax-ignore') !== undefined) {
                console.log('PJAX: 忽略 - data-pjax-ignore');
                return;
            }
            
            var href = this.href;
            
            // 忽略特定类型的链接
            if (href.match(/\.torrent$/i) || 
                href.match(/download/i) || 
                href.match(/getattachment/i) || 
                href.match(/logout/i)) {
                console.log('PJAX: 忽略 - 特殊类型链接');
                return;
            }
            
            // 忽略锚点链接
            if (href.indexOf('#') !== -1 && href.replace(/#.*/, '') === window.location.href.replace(/#.*/, '')) {
                console.log('PJAX: 忽略 - 锚点链接');
                return;
            }
            
            console.log('PJAX: 准备PJAX加载', href);
            
            // 阻止默认行为
            event.preventDefault();
            
            // 发起PJAX请求
            pjax({
                url: href,
                container: options.container,
                push: true,
                timeout: options.timeout || 5000,
                scrollTo: options.scrollTo !== undefined ? options.scrollTo : 0
            });
            
            return false;
        });
    }
    
    function showLoadingIndicator() {
        if ($('#pjax-loading').length) return;
        
        var loading = $('<div id="pjax-loading" style="position: fixed; top: 0; left: 0; right: 0; height: 3px; background: linear-gradient(90deg, #00d4ff 0%, #a855f7 50%, #00d4ff 100%); background-size: 200% 100%; animation: pjax-loading 1.5s ease-in-out infinite; z-index: 99999999;"></div>');
        
        var style = $('<style>@keyframes pjax-loading { 0%, 100% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } }</style>');
        
        $('head').append(style);
        $('body').prepend(loading);
    }
    
    function hideLoadingIndicator() {
        $('#pjax-loading').remove();
    }
    
    // PJAX默认配置
    pjax.defaults = {
        timeout: 5000,
        push: true,
        type: 'GET',
        dataType: 'html',
        scrollTo: 0
    };
    
    // 绑定到$
    $.pjax = pjax;
    $.pjax.defaults = pjax.defaults;
    
    // 处理popstate（浏览器前进后退）
    $(window).on('popstate.pjax', function(event) {
        var state = event.originalEvent.state;
        
        if (state && state.pjax) {
            console.log('PJAX: popstate事件', state.url);
            window.location.reload();
        }
    });
    
    // AJAX全局处理
    $(document).on('pjax:send', function() {
        console.log('PJAX: pjax:send事件触发');
    });
    
    $(document).on('pjax:complete', function() {
        hideLoadingIndicator();
        console.log('PJAX: pjax:complete事件触发');
    });
    
    $(document).on('pjax:success', function(event, data, status, xhr, options) {
        console.log('PJAX Success: 开始处理返回数据');
        
        var container = $(options.container);
        
        if (!container.length) {
            console.error('PJAX: 容器不存在', options.container);
            return;
        }
        
        console.log('PJAX: 容器找到', container);
        
        // 创建临时容器解析HTML
        var $tempContainer = $('<div>').html(data);
        
        // 提取title
        var title = $tempContainer.find('title').text();
        if (title) {
            document.title = title;
            console.log('PJAX: 更新标题 -', title);
        }
        
        // 从返回的HTML中精确提取#pjax-container的内容
        var $newContent = $tempContainer.find(options.container);
        
        if ($newContent.length > 0) {
            console.log('PJAX: 找到容器内容，开始更新');
            
            // 获取容器内的所有子元素（排除容器本身）
            var containerHtml = $newContent.html();
            
            // 清理：移除可能存在的页脚元素（防止重复）
            if (containerHtml) {
                var $cleanContent = $('<div>').html(containerHtml);
                
                // 移除页脚（如果意外包含）
                $cleanContent.find('#footer').remove();
                $cleanContent.find('.footer').remove();
                
                // 移除PJAX初始化脚本（防止重复执行）
                $cleanContent.find('script[src*="jquery.pjax"]').remove();
                $cleanContent.find('script').filter(function() {
                    return $(this).html().indexOf('PJAX已启用') !== -1;
                }).remove();
                
                containerHtml = $cleanContent.html();
            }
            
            // 更新容器内容
            container.html(containerHtml);
            
            console.log('PJAX: 内容已更新，HTML长度:', containerHtml ? containerHtml.length : 0);
        } else {
            // 如果找不到容器，尝试使用body内容，但要排除页脚
            console.warn('PJAX: 未找到容器，尝试使用body内容');
            var $bodyContent = $tempContainer.find('body');
            if ($bodyContent.length) {
                var bodyHtml = $bodyContent.html();
                var $cleanBody = $('<div>').html(bodyHtml);
                
                // 移除页脚和容器外的元素
                $cleanBody.find('#footer').remove();
                $cleanBody.find('#pjax-container').nextAll().remove();
                
                container.html($cleanBody.html());
            } else {
                container.html(data);
            }
        }
        
        // 滚动到顶部
        if (typeof options.scrollTo === 'number') {
            $(window).scrollTop(options.scrollTo);
            console.log('PJAX: 滚动到', options.scrollTo);
        }
        
        // 重新执行内联脚本
        container.find('script').each(function() {
            if (this.src) {
                // 外部脚本
                console.log('PJAX: 加载外部脚本', this.src);
                $.getScript(this.src);
            } else {
                // 内联脚本
                try {
                    $.globalEval(this.innerHTML || this.textContent);
                    console.log('PJAX: 执行内联脚本');
                } catch(e) {
                    console.error('PJAX: 脚本执行错误', e);
                }
            }
        });
        
        console.log('PJAX: 页面更新完成！');
    });
    
    $(document).on('pjax:error', function(event, xhr, textStatus, error, options) {
        console.error('PJAX: 加载失败', textStatus, error);
        hideLoadingIndicator();
        
        // 出错时刷新页面
        console.log('PJAX: 降级为完整刷新');
        window.location = options.url;
    });
    
    console.log('PJAX: 库加载完成');
    
})(jQuery);


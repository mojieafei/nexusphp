jQuery(document).ready(function () {
    function getImgPosition(e, imgEle) {
        // console.log(e, imgEle)
        let imgWidth = imgEle.prop('naturalWidth')
        let imgHeight = imgEle.prop("naturalHeight")
        let ratio = imgWidth / imgHeight;
        let offsetX = 10;
        let offsetY = 10;
        let width = window.innerWidth - e.clientX;
        let height = window.innerHeight - e.clientY;
        let changeOffsetY = 0;
        let changeOffsetX = false;
        if (e.clientX > window.innerWidth / 2 && e.clientX + imgWidth > window.innerWidth) {
            changeOffsetX = true
            width = e.clientX
        }
        if (e.clientY > window.innerHeight / 2) {
            if (e.clientY + imgHeight/2 > window.innerHeight) {
                changeOffsetY = 1
                height = e.clientY
            } else if (e.clientY + imgHeight > window.innerHeight) {
                changeOffsetY = 2
                height = e.clientY
            }
        }
        let log = `innerWidth: ${window.innerWidth}, innerHeight: ${window.innerHeight}, pageX: ${e.pageX}, pageY: ${e.pageY}, imgWidth: ${imgWidth}, imgHeight: ${imgHeight}, width: ${width}, height: ${height}, offsetX: ${offsetX}, offsetY: ${offsetY}, changeOffsetX: ${changeOffsetX}, changeOffsetY: ${changeOffsetY}`
        console.log(log)
        if (imgWidth > width) {
            imgWidth = width;
            imgHeight = imgWidth / ratio;
        }
        if (imgHeight > height) {
            imgHeight = height;
            imgWidth = imgHeight * ratio;
        }
        if (changeOffsetX) {
            offsetX = -(e.clientX - width + 10)
        }
        if (changeOffsetY == 1) {
            offsetY = - (imgHeight - (window.innerHeight - e.clientY))
        } else if (changeOffsetY == 2) {
            offsetY = - imgHeight/2
        }
        return {imgWidth, imgHeight,offsetX, offsetY}
    }

    // preview
    function getPosition(e, position) {
        return {
            left: e.pageX + position.offsetX,
            top: e.pageY + position.offsetY,
            width: position.imgWidth,
            height: position.imgHeight
        }
    }
    var previewEle = jQuery('#nexus-preview')
    var imgEle, selector = 'img.preview', imgPosition
    var previewTimer = null
    var currentPreviewImg = null
    
    // 隐藏预览的函数
    function hidePreview() {
        if (previewTimer) {
            clearTimeout(previewTimer)
            previewTimer = null
        }
        previewEle.stop(true, true).fadeOut(150)
        currentPreviewImg = null
    }
    
    jQuery("body").on("mouseover", selector, function (e) {
        imgEle = jQuery(this);
        currentPreviewImg = imgEle[0]
        
        // 清除之前的隐藏定时器
        if (previewTimer) {
            clearTimeout(previewTimer)
            previewTimer = null
        }
        
        // previewEle = jQuery('<img style="display: none;position:absolute;">').appendTo(imgEle.parent())
        imgPosition = getImgPosition(e, imgEle)
        let position = getPosition(e, imgPosition)
        let src = imgEle.attr("src")
        if (src) {
            previewEle.attr("src", src).css(position).stop(true, true).fadeIn(200);
        }
    }).on("mouseout", selector, function (e) {
        // 延迟隐藏，给鼠标移动到预览窗口的时间
        previewTimer = setTimeout(function() {
            // 检查鼠标是否在预览窗口上
            var relatedTarget = e.relatedTarget || e.toElement
            if (!relatedTarget || !jQuery(relatedTarget).closest('#nexus-preview').length) {
                hidePreview()
            }
        }, 100)
    }).on("mousemove", selector, function (e) {
        // 清除隐藏定时器
        if (previewTimer) {
            clearTimeout(previewTimer)
            previewTimer = null
        }
        
        let position = getPosition(e, imgPosition)
        previewEle.css(position)
    })
    
    // 全局鼠标移动监听：当鼠标快速移动离开图片时，确保预览消失
    var lastMouseMoveTime = 0
    var mouseMoveCheckTimer = null
    
    jQuery(document).on("mousemove", function(e) {
        lastMouseMoveTime = Date.now()
        
        // 如果当前有预览显示
        if (currentPreviewImg && previewEle.is(':visible')) {
            // 清除之前的检查定时器
            if (mouseMoveCheckTimer) {
                clearTimeout(mouseMoveCheckTimer)
            }
            
            // 延迟检查，避免频繁触发
            mouseMoveCheckTimer = setTimeout(function() {
                // 检查鼠标是否还在当前图片上
                var target = e.target
                var isOnImage = target === currentPreviewImg || 
                               jQuery(target).closest(selector).is(currentPreviewImg) ||
                               jQuery(currentPreviewImg).is(target) ||
                               jQuery(currentPreviewImg).find(target).length > 0
                var isOnPreview = jQuery(target).closest('#nexus-preview').length > 0
                
                // 如果鼠标既不在图片上，也不在预览窗口上，隐藏预览
                if (!isOnImage && !isOnPreview) {
                    hidePreview()
                }
            }, 50) // 50ms延迟检查，平衡性能和响应速度
        }
    })
    
    // 预览窗口的鼠标事件：鼠标进入预览窗口时取消隐藏
    previewEle.on("mouseenter", function() {
        if (previewTimer) {
            clearTimeout(previewTimer)
            previewTimer = null
        }
        if (mouseMoveCheckTimer) {
            clearTimeout(mouseMoveCheckTimer)
            mouseMoveCheckTimer = null
        }
    }).on("mouseleave", function() {
        hidePreview()
    })
    
    // 页面失去焦点时也隐藏预览
    jQuery(window).on("blur", function() {
        hidePreview()
    })

    // lazy load
    if ("IntersectionObserver" in window) {
        const imgList = [...document.querySelectorAll('.nexus-lazy-load')]
        var io = new IntersectionObserver((entries) =>{
            entries.forEach(entry  => {
                const el = entry.target
                const intersectionRatio = entry.intersectionRatio
                // console.log(`el, ${el.getAttribute('data-src')}, intersectionRatio: ${intersectionRatio}`)
                if (intersectionRatio > 0 && intersectionRatio <= 1 && !el.classList.contains('preview')) {
                    // console.log(`el, ${el.getAttribute('data-src')}, loadImg`)
                    const source = el.dataset.src
                    el.src = source
                    el.classList.add('preview')
                }
                el.onload = el.onerror = () => io.unobserve(el)
            })
        })

        imgList.forEach(img => io.observe(img))
    }

    // 通用确认弹窗组件（不操作历史记录，避免浏览器回退）
    window.nexusConfirm = function(message, onConfirm, onCancel) {
        // 创建弹窗元素
        var modal = document.createElement('div');
        modal.id = 'nexus-confirm-modal';
        modal.style.cssText = 'display:none; position:fixed; top:0; left:0; right:0; bottom:0; width:100vw; height:100vh; background:rgba(0,0,0,0.7); z-index:10000;';
        modal.style.display = 'none';
        modal.style.alignItems = 'center';
        modal.style.justifyContent = 'center';
        modal.innerHTML = `
            <div style="background:#1a2642; border-radius:12px; padding:30px; width:90%; max-width:500px; box-shadow:0 0 30px rgba(0,212,255,0.3); border:1px solid rgba(0,212,255,0.5);">
                <h2 style="color:#00d4ff; margin-top:0; text-align:center; font-size:20px;">确认</h2>
                <p id="nexus-confirm-message" style="color:#b8d4ff; text-align:center; margin:20px 0 30px; font-size:16px; line-height:1.6;"></p>
                <div style="display:flex; gap:10px; justify-content:center;">
                    <button id="nexus-confirm-ok" style="padding:12px 30px; background:linear-gradient(135deg, #00d4ff, #a855f7); border:none; border-radius:6px; color:#fff; font-weight:bold; cursor:pointer; font-size:16px;">确认</button>
                    <button id="nexus-confirm-cancel" style="padding:12px 30px; background:rgba(255,255,255,0.1); border:1px solid rgba(255,255,255,0.2); border-radius:6px; color:#fff; cursor:pointer; font-size:16px;">取消</button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
        
        // 设置消息
        document.getElementById('nexus-confirm-message').textContent = message;
        
        // 显示弹窗
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        
        // 确认按钮
        document.getElementById('nexus-confirm-ok').onclick = function() {
            modal.style.display = 'none';
            document.body.style.overflow = '';
            document.body.removeChild(modal);
            if (typeof onConfirm === 'function') {
                onConfirm();
            }
        };
        
        // 取消按钮
        document.getElementById('nexus-confirm-cancel').onclick = function() {
            modal.style.display = 'none';
            document.body.style.overflow = '';
            document.body.removeChild(modal);
            if (typeof onCancel === 'function') {
                onCancel();
            }
        };
        
        // 点击背景关闭
        modal.onclick = function(e) {
            if (e.target === modal) {
                modal.style.display = 'none';
                document.body.style.overflow = '';
                document.body.removeChild(modal);
                if (typeof onCancel === 'function') {
                    onCancel();
                }
            }
        };
    };

    // 通用提示弹窗组件（不操作历史记录，避免浏览器回退）
    window.nexusAlert = function(message, onClose) {
        // 创建弹窗元素
        var modal = document.createElement('div');
        modal.id = 'nexus-alert-modal';
        modal.style.cssText = 'display:none; position:fixed; top:0; left:0; right:0; bottom:0; width:100vw; height:100vh; background:rgba(0,0,0,0.7); z-index:10000;';
        modal.style.display = 'none';
        modal.style.alignItems = 'center';
        modal.style.justifyContent = 'center';
        modal.innerHTML = `
            <div style="background:#1a2642; border-radius:12px; padding:30px; width:90%; max-width:500px; box-shadow:0 0 30px rgba(0,212,255,0.3); border:1px solid rgba(0,212,255,0.5);">
                <h2 style="color:#00d4ff; margin-top:0; text-align:center; font-size:20px;">信息</h2>
                <p id="nexus-alert-message" style="color:#b8d4ff; text-align:center; margin:20px 0 30px; font-size:16px; line-height:1.6;"></p>
                <div style="display:flex; gap:10px; justify-content:center;">
                    <button id="nexus-alert-ok" style="padding:12px 30px; background:linear-gradient(135deg, #00d4ff, #a855f7); border:none; border-radius:6px; color:#fff; font-weight:bold; cursor:pointer; font-size:16px;">确定</button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
        
        // 设置消息
        document.getElementById('nexus-alert-message').textContent = message;
        
        // 显示弹窗
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        
        // 确定按钮
        document.getElementById('nexus-alert-ok').onclick = function() {
            modal.style.display = 'none';
            document.body.style.overflow = '';
            document.body.removeChild(modal);
            if (typeof onClose === 'function') {
                onClose();
            }
        };
        
        // 点击背景关闭
        modal.onclick = function(e) {
            if (e.target === modal) {
                modal.style.display = 'none';
                document.body.style.overflow = '';
                document.body.removeChild(modal);
                if (typeof onClose === 'function') {
                    onClose();
                }
            }
        };
    };

    // 通用消息提示组件（不操作历史记录，自动关闭）
    window.nexusMsg = function(message, time, onClose) {
        time = time || 1500;
        // 创建消息元素容器
        var container = document.createElement('div');
        container.id = 'nexus-msg-container';
        container.style.cssText = 'position:fixed; top:0; left:0; right:0; bottom:0; width:100vw; height:100vh; z-index:10001; display:flex; align-items:center; justify-content:center; pointer-events:none;';
        
        // 创建消息元素
        var msg = document.createElement('div');
        msg.id = 'nexus-msg';
        msg.style.cssText = 'background:rgba(0,0,0,0.8); color:#fff; padding:15px 30px; border-radius:8px; font-size:16px; box-shadow:0 0 20px rgba(0,212,255,0.3); border:1px solid rgba(0,212,255,0.5); white-space:nowrap; pointer-events:auto;';
        msg.textContent = message;
        
        container.appendChild(msg);
        document.body.appendChild(container);
        
        // 自动关闭
        setTimeout(function() {
            if (container.parentNode) {
                container.parentNode.removeChild(container);
            }
            if (typeof onClose === 'function') {
                onClose();
            }
        }, time);
    };

    //claim
    jQuery("body").on("click", "[data-claim_id]", function (e) {
        e.preventDefault()
        e.stopPropagation()
        e.stopImmediatePropagation()
        let _this = jQuery(this)
        let box = _this.closest('td')
        if (!box.length) {
            e.preventDefault()
            e.stopImmediatePropagation()
            return false
        }
        let claimId = _this.attr("data-claim_id")
        let torrentId = _this.attr("data-torrent_id")
        let action = _this.attr("data-action")
        let reload = parseInt(_this.attr("data-reload") || "0")
        let confirmText = _this.attr("data-confirm")
        let showStyle = "width: max-content;display: flex;align-items: center";
        let hideStyle = "width: max-content;display: none;align-items: center";
        let params = {}
        if (claimId > 0) {
            params.id = claimId
        } else {
            params.torrent_id = torrentId
        }
        // 使用自定义确认弹窗，不操作历史记录
        window.nexusConfirm(confirmText, function() {
            // 确认回调：发送AJAX请求
            jQuery.post("ajax.php", {"action": action, params: params}, function (response) {
                console.log("Claim AJAX response:", response)
                if (response.ret != 0) {
                    if (typeof layer !== 'undefined') {
                        layer.alert(response.msg, {title: "Info", btn: ['OK'], btnAlign: 'c'})
                    } else {
                        alert(response.msg)
                    }
                    return
                }
                // 只有在明确设置了 reload > 0 时才刷新页面，否则只更新按钮状态
                if (reload > 0) {
                    window.location.reload();
                    return;
                }
                // 局部更新按钮状态
                if (claimId > 0) {
                    //do remove, show add
                    box.find("[data-action=addClaim]").attr("style", showStyle).attr("data-claim_id", 0)
                    box.find("[data-action=removeClaim]").attr("style", hideStyle)
                } else {
                    //do add, show remove, update claim_id
                    box.find("[data-action=addClaim]").attr("style", hideStyle)
                    box.find("[data-action=removeClaim]").attr("style", showStyle).attr("data-claim_id", response.data.id)
                }
            }, "json").fail(function(xhr, textStatus, errorThrown) {
                console.error("Claim AJAX failed:", textStatus, errorThrown)
                if (typeof layer !== 'undefined') {
                    layer.alert("请求失败: " + (errorThrown || textStatus), {title: "Info", btn: ['OK'], btnAlign: 'c'})
                } else {
                    alert("请求失败: " + (errorThrown || textStatus))
                }
            })
        })
        return false
    })

})

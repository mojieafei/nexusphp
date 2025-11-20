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

    //claim
    jQuery("body").on("click", "[data-claim_id]", function () {
        let _this = jQuery(this)
        let box = _this.closest('td')
        let claimId = _this.attr("data-claim_id")
        let torrentId = _this.attr("data-torrent_id")
        let action = _this.attr("data-action")
        let reload = _this.attr("data-reload")
        let confirmText = _this.attr("data-confirm")
        let showStyle = "width: max-content;display: flex;align-items: center";
        let hideStyle = "width: max-content;display: none;align-items: center";
        let params = {}
        if (claimId > 0) {
            params.id = claimId
        } else {
            params.torrent_id = torrentId
        }
        let modalConfig = {title: "Info", btn: ['OK', 'Cancel'], btnAlign: 'c'}
        layer.confirm(confirmText, modalConfig, function (confirmIndex) {
            jQuery.post("ajax.php", {"action": action, params: params}, function (response) {
                console.log(response)
                if (response.ret != 0) {
                    layer.alert(response.msg, modalConfig)
                    return
                }
                if (reload > 0) {
                    window.location.reload();
                    return;
                }
                if (claimId > 0) {
                    //do remove, show add
                    box.find("[data-action=addClaim]").attr("style", showStyle).attr("data-claim_id", 0)
                    box.find("[data-action=removeClaim]").attr("style", hideStyle)
                } else {
                    //do add, show remove, update claim_id
                    box.find("[data-action=addClaim]").attr("style", hideStyle)
                    box.find("[data-action=removeClaim]").attr("style", showStyle).attr("data-claim_id", response.data.id)
                }
                layer.close(confirmIndex)
            }, "json")
        })
    })

})

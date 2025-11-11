;(function () {
    try {
        var config = window.nexusPerformanceConfig || {};
        if (config.locked) {
            return;
        }

        var alreadyDecided = config.cookieMode === 'performance' || config.cookieMode === 'minimal';
        if (alreadyDecided) {
            return;
        }

        var prefersReducedMotion = false;
        try {
            prefersReducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        } catch (err) {
            prefersReducedMotion = false;
        }

        var lowMemory = typeof navigator.deviceMemory === 'number' && navigator.deviceMemory > 0 && navigator.deviceMemory <= 4;
        var lowCpu = typeof navigator.hardwareConcurrency === 'number' && navigator.hardwareConcurrency > 0 && navigator.hardwareConcurrency <= 4;

        var lowPerf = prefersReducedMotion || lowMemory || lowCpu;
        if (!lowPerf) {
            return;
        }

        var storageKey = 'nexus.uiModeAuto';
        var now = Date.now();
        var last = 0;
        try {
            last = Number(window.localStorage.getItem(storageKey) || '0');
        } catch (err2) {
            last = 0;
        }

        // 避免频繁切换，同一设备 24 小时内只触发一次
        if (last && now - last < 24 * 60 * 60 * 1000) {
            return;
        }

        var cookieValue = 'ui_mode=minimal; path=/; max-age=' + (30 * 24 * 60 * 60) + '; SameSite=Lax';
        if (window.location.protocol === 'https:') {
            cookieValue += '; Secure';
        }
        document.cookie = cookieValue;

        try {
            window.localStorage.setItem(storageKey, String(now));
        } catch (err3) {
            // ignore
        }

        if (config.mode !== 'minimal') {
            window.location.reload();
        }

    } catch (e) {
        // 静默失败，避免阻塞主流程
        if (typeof console !== 'undefined' && console.warn) {
            console.warn('[performance-mode]', e);
        }
    }
})();


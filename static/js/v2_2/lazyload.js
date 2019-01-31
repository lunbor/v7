/**
 * LazyLoad Script
 * 延迟加载插件
 * v-delayload="v2ui"
 */

v.LazyLoad || v.kit('LazyLoad', function (self) {

    // 静态属性
    self.anchor = 'v-lazyload';

    // 共有属性
    this.srcattr = 'lazysrc';  // 延迟加载的内容的属性
    this.prepx = 0;  // 预加载像素
    this.effect = 'fade'; // 加载后效果显示，fade/slide，函数为回调
    this.speed = 'fast'; // 加载效果速度

    /**
     * 取得节点的offset，如果节点没有宽高则取父节点
     * @param {Element} el DOM节点
     * @returns {Object} offset坐标
     */
    var _offsetNode = function (el) {
        do {
            if (el.offsetWidth || el.offsetHeight)
                break;
        } while (el = el.parentNode);
        if (!el)
            return false;
        var xy = $(el).offset();
        xy.bottom = el.offsetHeight + xy.top;
        xy.right = el.offsetWidth + xy.left;
        return xy;
    };

    /**
     * 显示节点
     * @param {Element} el DOM节点
     */
    var _showNode = function (el) {
        if (typeof (this.effect) === 'function') {  // 函数
            this.effect(el);
        } else if (this.effect) {  // 效果
            this.effect === 'fade' ? $(el).fadeIn(this.speed) : $(el).slideDown(this.speed);
        } else {  // 直接显示
            $(el).show();
        }
    };

    /**
     * 载入element的src
     * @param {Element} el
     */
    var _loadSrc = function (el) {
        var src = $(el).attr(this.srcattr), pos = src.indexOf('(');
        $(el).hide().attr(this.srcattr, null);
        if (src) {
            // 数据载入后的回调函数
            var fn = function () {
                _showNode.call(this, el);
            }.bind(this);
            if (pos > 0 && window[src.substr(0, pos)]) {
                // 函数，参数为函数执行完成后执行
                window[src.substr(0, pos)](fn);
            } else {
                $(el).on('load', fn).attr({'src': src});
            }
        }
    };

    /**
     * 修复position为fixed的情况，fixed的内容会始终载入
     * @param {Element} el
     * @returns {Element|Boolean}
     */
    var _fixedParent = function (el) {
        do {
            if ('fixed'.indexOf($(el).css('position') || 'static') > -1)
                return el;
        } while ((el = el.parentNode) && el.nodeName !== 'BODY');
        return false;
    };

    /**
     * 加载可加载的节点
     */
    var _loadNode = function () {
        // 窗口大小位置
        var win = $(window), by = {
            'left': win.scrollLeft() - this.prepx,
            'top': win.scrollTop() - this.prepx,
            'right': (window.innerWidth || win.innerWidth()) + win.scrollLeft() + this.prepx,
            'bottom': (window.innerHeight || win.innerHeight()) + win.scrollTop() + this.prepx
        };
        var els = v.$s('[' + this.srcattr + ']', this.anchor);
        if (els.length > 0) {
            v.forEach(els, function (el) {
                var xy = _offsetNode.call(this, el);
                if (xy && (xy.top !== xy.bottom || xy.left !== xy.right)) {
                    // 浮动元素 || 顶部\左边\底部\右边 在可视范围
                    if (_fixedParent.call(this, el) || xy.top < by.bottom && xy.left < by.right && xy.bottom > by.top && xy.right > by.left) {
                        _loadSrc.call(this, el);
                    }
                }
            }.bind(this));
            // 还有节点没处理，继续循环监视
            v.callst(v.bind(_loadNode, this), 1000);
        }
    };

    /**
     * 初始化
     */
    this.construct = function () {
        this.parent();
        $(window).on('scroll resize', this.load);
        this.load();
    };

    /**
     * 加载节点
     */
    this.load = function () {
        v.callst(v.bind(_loadNode, this));
    };
});
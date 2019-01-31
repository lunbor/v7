/**
 * LazyLoad Script
 * 延迟加载插件
 * data-delayload="v2ui"
 */

v.LazyLoad || (function () {
    v.LazyLoad = v.extend(v.UI, function (self) {
        // 静态属性
        self.anchor = 'data-lazyload';

        // 属性
        this.srcattr = 'lazysrc';  // 延迟加载的内容的属性
        this.prepx = 0;  // 预加载像素
        this.effect = 'fade'; // 加载后效果显示，true使用默认，false不显示，函数为回调
        this.speed = 'fast'; // 加载效果速度

        // 私有属性
        this._timer = null;  // 加载延迟timer
        this._moniTimer = null;  // 监控timer

        // 取得节点的offset，如果节点没有宽高则取父节点
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

        // 修复position为fixed的情况，fixed的内容会始终载入
        var _fixedParent = function (el) {
            do {
                if ('fixed'.indexOf($(el).css('position') || 'static') > -1)
                    return el;
            } while ((el = el.parentNode) && el.nodeName !== 'BODY');
            return false;
        };

        // 自动加载
        var _autoLoad = function (time) {
            this._timer = v.callst(function () {
                // 窗口大小位置
                var win = $(window), by = {
                    'left': win.scrollLeft() - this.prepx,
                    'top': win.scrollTop() - this.prepx,
                    'right': (window.innerWidth || win.innerWidth()) + win.scrollLeft() + this.prepx,
                    'bottom': (window.innerHeight || win.innerHeight()) + win.scrollTop() + this.prepx
                };
                var els = v.$s('[' + this.srcattr + ']', this.anchor);
                if (els.length < 1) {
                    //clearInterval(this._moniTimer);
                } else {
                    v.forEach(els, function (el) {
                        var xy = _offsetNode.call(this, el);
                        if (xy && (xy.top !== xy.bottom || xy.left !== xy.right)) {
                            // 浮动元素 || 顶部\左边\底部\右边 在可视范围
                            if (_fixedParent.call(this, el) || xy.top < by.bottom && xy.left < by.right && xy.bottom > by.top && xy.right > by.left) {
                                _loadSrc.call(this, el);
                            }
                        }
                    }.bind(this));
                }
            }.bind(this), this._timer, time || 13);
        };

        // 显示节点
        var _showNode = function (el) {
            if (typeof (this.effect) === 'function') {  // 函数
                this.effect(el);
            } else if (this.effect) {  // 效果
                this.effect === 'fade' ? $(el).fadeIn(this.speed) : $(el).slideDown(this.speed);
            } else {  // 直接显示
                $(el).show();
            }
        };

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

        // 公有方法
        // 
        // 初始化
        this.construct = function () {
            this.parent();
            _autoLoad.call(this, 1);
            $(window).on('scroll resize', function () {
                _autoLoad.call(this, 200);
            }.bind(this));
            
            this._moniTimer = setInterval(_autoLoad.bind(this), 200);
        };

        // 手动加载
        this.load = function () {
            _autoLoad.call(this, 1);
        };
    });
})();
$(function () {
    v.LazyLoad.init();
});
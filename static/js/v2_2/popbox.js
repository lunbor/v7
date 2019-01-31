/**
 * PopBox Script
 * 弹出对话框插件
 * v-popbox="{}"
 */
v.PopBox || v.kit('PopBox', function (self) {
    // 静态属性
    self.anchor = 'v-popbox';

    // -----------------------------------
    // 公共属性
    // ------------------------------------
    this.anchor = null; // 对话框
    this.tribox = null;  // 对话框包含区域
    this.toggle = null;  // *触发选择器
    this.mask = false;  // 是否遮罩
    this.position = 'static';  // static是不决定位置,middle居中定位,top|right|bottom|left按放心定位
    this.closesec = 0; // 自动关闭时长，秒, 0|false不自动关闭
    this.speed = 'normal'; // 动画速度
    this.zindex = 999; // 层级
    this.closel = null; // 关闭按钮
    this.arrowSize = 8;  // 边距，箭头大小，按方向定位配置
    this.arrowColor = '#000';  // 箭头颜色，按方向定位配置

    // -------------------------------------------
    // 私有属性与方法
    // -------------------------------------------
    this._toggle = null;  // 点击对象
    this._showed = false;  // 显示状态
    this._arrow = null;  // 箭头符号
    this._oldpos = null;  // 上一次定位
    var _repos = {left: 'right', top: 'bottom', right: 'left', bottom: 'top'};

    // 事件定义
    this._events = {
        show: [], // 显示事件 
        close: []  // 关闭事件
    };

    /**
     * 初始化触发事件
     */
    var _initToggle = function () {
        if (!this.toggle)
            return;
        var eventHandler = function (el) {
            if (this._toggle !== el || !this._showed) {
                this._toggle = el;
                this.show();
            } else {
                this.close();
            }
        }.bind(this);
        $(this.tribox).on('click', this.toggle, function (event) {
            v.callst(eventHandler, event.currentTarget);
        });
    };

    /**
     * 初始化遮罩
     */
    var _initMask = function () {
        if (!this.mask)
            return;
        this.mask = $('<div>').css({
            position: 'fixed',
            top: 0,
            left: 0,
            right: 0,
            bottom: 0,
            backgroundColor: '#000',
            opacity: 0.6,
            zIndex: this.zindex,
            display: 'none'
        }).appendTo('body')[0];
    };

    /**
     * 重新设置位置
     * @param {String} pos
     */
    var _rePos = function (pos) {
        if ('static|middle'.indexOf(pos) === -1) {
            var css = {
                borderWidth: this.arrowSize,
                borderStyle: 'solid',
                position: 'absolute',
                width: 0,
                height: 0,
                borderColor: 'transparent'
            };
            v.forEach(_repos, function (pos) {
                css['border-' + pos + '-color'] = 'transparent';
                css['border-' + _repos[pos] + '-width'] = this.arrowSize;
                css['margin-' + _repos[pos]] = 'auto';
                css[_repos[pos]] = 'auto';
            }.bind(this));
            if (pos !== 'auto') {
                css['border-' + pos + '-color'] = this.arrowColor;
                css['border-' + _repos[pos] + '-width'] = 0;
                css['margin-' + _repos[pos]] = -this.arrowSize;
                css[_repos[pos]] = 0;
            }
            $(this._arrow).css(css);
        }
    };

    /**
     * 初始化位置
     */
    var _initPos = function () {
        if (this.position !== 'static') {
            $(this.anchor).css({position: 'absolute', top: 0, left: 0, zIndex: this.zindex + 1});
            if (this.position !== 'middle') {
                // 箭头指标符号
                this._arrow = $('<div>').addClass('popbox-arrow');
                _rePos.call(this, this.position);
                $(this._arrow).appendTo(this.anchor);
            }
        }
    };

    /**
     * 居中定位
     * @param {Integer} elPx
     * @param {Integer} winPx
     * @param {Integer} scrollPx
     * @param {Integer} bodyPx
     * @returns {Number}
     */
    var _posMiddle = function (elPx, winPx, scrollPx, bodyPx) {
        var px = Math.max((winPx - elPx) / 2, 16) + scrollPx;
        if (px + elPx > bodyPx)
            px = Math.max(bodyPx + 16 - elPx, 16);
        return px;
    };

    /**
     * 按箭头方向自动定位一侧
     * @param {Integer} btnTop
     * @param {Integer} btnHt
     * @param {Integer} elHt
     * @param {Integer} winHt
     * @returns {Array}
     */
    var _posAspect = function (btnTop, btnHt, elHt, winHt) {
        winHt -= this.arrowSize;
        // 首先箭头居中,中间位置对齐
        var dx, top = btnTop + (btnHt - elHt) / 2,
                atop = (elHt - this.arrowSize) / 2;
        dx = this.arrowSize - top;
        if (dx > 0) {
            // 修复顶端超出区域的情况
            if (atop < dx + this.arrowSize * 2) // 修复箭头超出区域的情况
                dx = atop - this.arrowSize * 2;
            top += dx;
            atop -= dx;
        } else {
            // 修复底端超出范围的情况
            dx = top + elHt - winHt;
            if (dx > 0) {
                if (top - dx < 0) // 修复超出顶端的情况
                    dx = top;
                if (atop < dx + this.arrowSize * 2) // 修复箭头超出区域的情况
                    dx = atop - this.arrowSize * 2;
                top -= dx;
                atop += dx;
            }
        }
        return [top, atop];
    };

    /**
     * 自动设置位置
     */
    var _setPos = function () {
        if (this.position === 'middle') {
            // 居中显示
            var top = _posMiddle($(this.anchor).outerHeight(), $(window).height(), $(window).scrollTop(), $('body').innerHeight()),
                    left = _posMiddle($(this.anchor).outerWidth(), $(window).width(), $(window).scrollLeft(), $('body').innerWidth());
            $(this.anchor).stop().animate({top: top, left: left}, this.speed);
        } else if (this.position !== 'static') {
            // 自动根据点击目标上下左右空间显示
            var pos = this.position, top, left,
                    width = $(this.anchor).outerWidth(),
                    height = $(this.anchor).outerHeight(),
                    scrTop = $(window).scrollTop(),
                    scrLeft = $(window).scrollLeft(),
                    btnWidth = $(this._toggle).outerWidth(),
                    btnHeight = $(this._toggle).outerHeight(),
                    winHeight = $(window).height(),
                    winWidth = $(window).width(),
                    btnOffset = $(this._toggle).offset(),
                    btnTop = btnOffset.top - scrTop,
                    btnLeft = btnOffset.left - scrLeft;

            // 自动定位，下，右，上，左
            if (pos === 'auto') {
                if (btnLeft - this.arrowSize * 2 >= width)
                    pos = 'left';
                else if (btnLeft + width + this.arrowSize * 2 + btnWidth < winWidth)
                    pos = 'right';
                else if (btnTop + height + this.arrowSize * 2 + btnHeight < winHeight)
                    pos = 'bottom';
                else if (btnTop - this.arrowSize * 2 >= height)
                    pos = 'top';
                else
                    pos = 'right';
            }

            // 定位相同，则箭头不再调整
            if (this._oldPos !== pos) {
                _rePos.call(this, pos);
                this._oldPos = pos;
            }

            if ('left|right'.indexOf(pos) > -1) {
                // 左右显示
                var rect = _posAspect.call(this, btnTop, btnHeight, height, winHeight);
                left = pos === 'left' ? btnLeft - width - this.arrowSize : btnLeft + btnWidth + this.arrowSize;
                top = rect[0];
                $(this._arrow).stop().animate({'top': rect[1]}, this.speed);
            } else if ('top|bottom'.indexOf(pos) > -1) {
                // 上下显示
                var rect = _posAspect.call(this, btnLeft, btnWidth, width, winWidth);
                top = pos === 'top' ? btnTop - height - this.arrowSize : btnTop + btnHeight + this.arrowSize;
                left = rect[0];
                $(this._arrow).stop().animate({'left': rect[1]}, this.speed);
            }

            top += scrTop;
            left += scrLeft;
            $(this.anchor).stop().animate({top: top, left: left}, this.speed);
        }
    };

    /**
     * 自动滚动
     */
    var _autoScroll = function () {
        var fn = function () {
            if (this._showed)
                v.callst(v.bind(_setPos, this));
        }.bind(this);
        $(window).scroll(fn).resize(fn);
    };

    /**
     * 自动关闭
     */
    var _autoClose = function () {
        if (this.closesec > 0) {
            v.callst(v.bind(this.close, this), this.closesec);
        }
    };

    // ----------------------------------------
    // 共有方法
    // ----------------------------------------

    /**
     * 初始化
     * @param {Object} options 配置
     */
    this.construct = function (/*options*/) {
        this.parent();

        this.closesec && (this.closesec *= 1000);

        // 触发区域默认为body
        this.tribox = !this.tribox ? document.body : v.$(this.tribox);

        // 关闭
        this.closel && $(this.closel, this.anchor).on('click', v.bind(this.close, this));

        _initMask.call(this);
        _initPos.call(this);
        _initToggle.call(this);
        _autoScroll.call(this);
    };

    /**
     * 显示对话框
     */
    this.show = function () {
        var anim = 'static|middle'.indexOf(this.position) > -1 ? 'slideDown' : 'fadeIn';
        _setPos.call(this);
        this._showed = true;
        this.mask && $(this.mask).fadeIn(this.speed);
        $(this.anchor).stop(true).hide()[anim](this.speed, v.bind(_autoClose, this), _setPos.call(this));
        this.triEvent('show', {
            target: this._toggle
        });
    };

    /**
     * 关闭对话框
     */
    this.close = function () {
        this._showed = false;
        this.mask && $(this.mask).fadeOut(this.speed);
        $(this.anchor).fadeOut(this.speed);
        this.triEvent('close', {
            target: this._toggle
        });
    };
});
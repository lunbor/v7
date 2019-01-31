/**
 * PopBox Script
 * 弹出对话框插件
 * data-popbox=""
 */
v.Popbox || v.kit('Popbox', function (self) {
    // 静态属性
    self.anchor = 'v-popbox';

    // 公共属性
    //
    this.anchor = null; // 对话框
    this.tribox = null;  // 对话框包含区域
    this.toggle = null;  // *触发选择器
    this.mask = false;  // 是否遮罩
    this.position = 'static';  // static时不决定位置,middle居中定位,top|right|bottom|left按放心定位
    this.closesec = 0; // 自动关闭时长，秒, 0|false不自动关闭
    this.zindex = 999; // 层级
    this.closel = null; // 关闭按钮
    this.arrowSize = 8;  // 边距，箭头大小，按方向定位配置
    this.arrowColor = '#000';  // 箭头颜色，按方向定位配置

    this._toggle = null;  // 点击对象
    this._showed = false;  // 显示状态
    this._arrow = null;  // 箭头符号
    this._oldpos = null;  // 上一次定位
    this._closeTimer = null;  // 关闭时钟
    var _repos = {left: 'right', top: 'bottom', right: 'left', bottom: 'top'};

    // 事件定义
    this._events = {
        show: [], // 显示事件 
        close: []  // 关闭事件
    };

    // 初始化触发事件
    var _initToggle = function () {
        if (this.toggle) {
            let stimer;
            v.$(this.tribox).on('click', this.toggle, (event) => {
                let tar = event.target;
                stimer = v.callst(() => {
                    if (this._toggle !== tar || !this._showed) {
                        this._toggle = tar;
                        this.show();
                    } else {
                        this.close();
                    }
                }, stimer, 100);
            });
        }
    };

    // 初始化mask
    var _initMask = function () {
        if (this.mask) {
            this.mask = v.$('<div>').css({
                position: 'fixed',
                top: 0,
                left: 0,
                right: 0,
                bottom: 0,
                backgroundColor: '#000',
                opacity: 0,
                zIndex: this.zindex,
                display: 'none'
            }).insertTo('body').original;
        }
    };

    // 重新定位POS
    var _rePos = function (pos) {
        if ('static|middle'.indexOf(pos) === -1) {
            let css = {
                borderWidth: this.arrowSize,
                borderStyle: 'solid',
                position: 'fixed',
                width: 0,
                height: 0,
                borderColor: 'transparent'
            };
            v.forEach(_repos, (pos) => {
                css['border-' + pos + '-color'] = 'transparent';
                css['border-' + _repos[pos] + '-width'] = this.arrowSize;
                css['margin-' + _repos[pos]] = 'auto';
                css[_repos[pos]] = 'auto';
            });
            if (pos !== 'auto') {
                css['border-' + pos + '-color'] = this.arrowColor;
                css['border-' + _repos[pos] + '-width'] = 0;
                css['margin-' + _repos[pos]] = -this.arrowSize;
                css[_repos[pos]] = 0;
            }
            v.$(this._arrow).css(css);
        }
    };

    // 初始化位置
    var _initPos = function () {
        if (this.position !== 'static') {
            v.$(this.anchor).css({position: 'fixed', top: 0, left: 0, zIndex: this.zindex + 1});
            if (this.position !== 'middle') {
                // 箭头指标符号
                this._arrow = v.$('<div>').addClass('popbox-arrow').original;
                _rePos.call(this, this.position);
                v.$(this._arrow).insertTo(this.anchor);
            }
        }
    };

    // 居中定位
    var _posMiddle = function (elPx, winPx, scrollPx) {
        var px = Math.max((winPx - elPx) / 2, 16) + scrollPx;
        return px;
    };

    // 按方向自动定位一侧
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

    // 初始化位置
    var _setPos = function () {
        let elPx = v.$(this.anchor).offset(), boxPx = v.$('doc').client(), scrPx = v.$('body').scroll();
        if (this.position === 'middle') {
            // 居中显示
            let top = elPx.height >= boxPx.height ? elPx.top : _posMiddle(elPx.height, boxPx.height, scrPx.top),
                    left = elPx.width >= boxPx.width ? elPx.left : _posMiddle(elPx.width, boxPx.width, scrPx.left);
            v.$(this.anchor).animation({top: top, left: left});
        } else if (this.position !== 'static') {
            // 自动根据点击目标上下左右空间显示
            let btnPx = v.$(this._toggle).offset('body'), pos = this.position, top, left;
            btnPx.top -= scrPx.top;
            btnPx.left -= scrPx.left;

            // 自动定位，下，右，上，左
            if (pos === 'auto') {
                if (btnPx.left - this.arrowSize * 2 >= elPx.width)
                    pos = 'left';
                else if (btnPx.left + elPx.width + this.arrowSize * 2 + btnPx.width < boxPx.width)
                    pos = 'right';
                else if (btnPx.top + elPx.height + this.arrowSize * 2 + btnPx.height < boxPx.height)
                    pos = 'bottom';
                else if (btnPx.top - this.arrowSize * 2 >= elPx.height)
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
                var rect = _posAspect.call(this, btnPx.top, btnPx.height, elPx.height, boxPx.height);
                left = pos === 'left' ? btnPx.left - elPx.width - this.arrowSize : btnPx.left + btnPx.width + this.arrowSize;
                top = rect[0];
                v.$(this._arrow).animation({'top': rect[1]});
            } else if ('top|bottom'.indexOf(pos) > -1) {
                // 上下显示
                var rect = _posAspect.call(this, btnPx.left, btnPx.width, elPx.width, boxPx.width);
                top = pos === 'top' ? btnPx.top - elPx.height - this.arrowSize : btnPx.top + btnPx.height + this.arrowSize;
                left = rect[0];
                v.$(this._arrow).animation({'left': rect[1]});
            }

            top += scrPx.top;
            left += scrPx.left;
            v.$(this.anchor).stop().animation({top: top, left: left});
        }
    };

    // 自动滚动
    var _autoScroll = function () {
        let stimer, fn = () => {
            if (this._showed)
                stimer = v.callst(v.bind(_setPos, this), stimer, 100);
        };
        window.addEventListener('scroll', fn);
        window.addEventListener('resize', fn);
    };

    // 自动关闭
    var _autoClose = function () {
        if (this.closesec > 0) {
            this._closeTimer = setTimeout(v.bind(this.close, this), this.closesec);
        }
    };

    // 共有方法
    // 
    // 初始化
    this.construct = function (options) {
        this.parent();
        if (this.closesec)
            this.closesec *= 1000;

        // 触发区域默认为body
        if (!this.tribox)
            this.tribox = document.body;

        // 关闭
        if (this.closel) {
            v.$(this.closel, this.anchor).on('click', v.bind(this.close, this));
        }

        _initMask.call(this);
        _initPos.call(this);
        _initToggle.call(this);
        _autoScroll.call(this);
    };

    // 显示
    this.show = function () {
        !this._closeTimer || clearTimeout(this._closeTimer);
        this._showed = true;
        let from = {'opacity': 0}, to = {'opacity': 1};
        if (this.position === 'middle') {
            from.transform = 'translate3d(0, -100%, 0)';
            to.transform = 'none';
        }
        !this.mask || v.$(this.mask).show({opacity: 0.6});
        _setPos.call(this);
        v.$(this.anchor).css(from).show(to, _autoClose.bind(this));
        this.triEvent('show', {
            target: this._toggle
        });
    };

    // 关闭
    this.close = function () {
        !this._closeTimer || clearTimeout(this._closeTimer);
        this._showed = false;
        !this.mask || v.$(this.mask).hide({opacity: 0});
        v.$(this.anchor).hide({'opacity': 0});
        this.triEvent('close', {
            target: this._toggle
        });
    };
});
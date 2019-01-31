/**
 * Scrollbar Script
 * 自定义滚动条插件
 * v-scrollbar="{}"
 */
v.Scrollbar || v.kit('Scrollbar', function (self) {
    // 静态属性
    self.anchor = 'v-scrollbar';
    // 公共属性
    //
    this.anchor = null; // 滚动框
    this.target = '.target'; // 滚动目标
    this.bar = '.bar'; // 触发box，包含anchor的dom，点击该dom内的区域会弹出日历
    this.overflow = 'y';  // 滚动方向 x | y

    // 事件定义
    this._events = {
        scroll: [] // 滚动结束
    };

    this._startPx = 0; // 拖到鼠标起始位置
    this._maxPx = 0;  // 滚动条top最大位置
    this._ratio = 1; // 滚动条比例
    this._barProp = 'top';  // 滚动条属性
    this._tarProp = 'marginTop';  // 滚动目标属性

    // 设置滚动条与数据位置
    var _setPos = function (px) {
        v.callim(() => {
            px = Math.max(Math.min(px, this._maxPx), 0);
            v.$(this.bar).css({[this._barProp]: px});
            let spx = (0 - px) / this._ratio;
            v.$(this.target).css({[this._tarProp]: spx});
            this.triEvent('scroll', {
                'target': this.anchor,
                'scroll': 0 - spx
            });
        });
    };

    // 开始拖到bar
    var _dragStart = function (event) {
        v.$('doc').on('mousemove', v.bind(_dragBar, this))
                .on('mouseup', v.bind(_dragEnd, this));
        this._startPx = v.$(this.bar).css(this._barProp) - (this.overflow === 'Y' ? event.clientY : event.clientX);
    };

    // 拖到bar
    var _dragBar = function (event) {
        let currentPx = this.overflow === 'Y' ? event.clientY : event.clientX,
                px = currentPx + this._startPx;
        _setPos.call(this, px);
    };

    // 结束拖动
    var _dragEnd = function (event) {
        v.$('doc').off('mousemove', v.bind(_dragBar, this)).off('mouseup', v.bind(_dragEnd, this));
    };

    // 滚轮滚动
    var _wheelBar = function (event) {
        let dif = ((event.wheelDelta || event.detail) > 0 ? 1 : -1) * (event.wheelDelta ? -1 : 1) * 20,
                px = v.$(this.bar).css(this._barProp) + dif;
        _setPos.call(this, px);
    };

    // 初始化
    this.construct = function () {
        this.parent();
        this.bar = v.$(this.bar, this.anchor).original;
        this.target = v.$(this.target, this.anchor).original;
        if (v.$(this.anchor).css('position') === 'static')
            this.anchor.style.position = 'relative';

        // 滚动条方向与属性
        this.overflow = this.overflow.toUpperCase();
        if (this.overflow === 'X') {
            this._barProp = 'left';
            this._tarProp = 'marginLeft';
        }

        v.$(this.bar).css({'position': 'absolute', [this._barProp]: 0}).on('mousedown', _dragStart.bind(this));
        let etype = 'onmousewheel' in this.anchor ? 'mousewheel' : 'DOMMouseScroll';
        v.$(this.anchor).on(etype, _wheelBar.bind(this))
                .on('mouseenter', () => {
                    if (this._ratio < 1)
                        v.$(this.bar).show();
                })
                .on('mouseleave', () => {
                    v.$(this.bar).hide();
                });
        window.addEventListener('resize', () => v.callst(this.reset.bind(this), 100));

        this.reset();
    };

    // 重设数据
    this.reset = function () {
        let prop = this.overflow === 'Y' ? 'height' : 'width';
        v.$(this.bar).css({[this._barProp]: 0});
        v.$(this.target).css({[this._tarProp]: 0});
        v.callst(() => {
            let scrollPx = this.overflow === 'Y' ? this.anchor.scrollHeight : this.anchor.scrollWidth,
                    clientPx = this.overflow === 'Y' ? this.anchor.clientHeight : this.anchor.clientWidth;

            this._ratio = clientPx / scrollPx;
            this._maxPx = clientPx - clientPx * this._ratio;
            v.$(this.bar).css({[prop]: clientPx - this._maxPx});
        });
    };


});
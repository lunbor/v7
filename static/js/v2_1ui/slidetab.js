/**
 * SlideTab Script
 * 轮播tab插件
 * data-slidetab="v2ui"
 */
v.SlideTab || (function () {
    v.SlideTab = v.extend(v.UI, function (self) {
        // 静态属性
        self.anchor = 'data-slidetab';

        // 公共属性
        //
        this.anchor = null; // tab选择区
        this.tribox = null;  // tab触发区域，在触发区域内自动停止切换
        this.toggle = '.tab-toggle';  // tab切换按钮
        this.event = 'mouseenter'; // 切换事件
        this.autosec = 0; // 自动切换时长，秒, 0|false不自动滚动
        this.menupop = false; // 是否弹出菜单模式
        this.effect = 'vertical'; // 效果, fade淡入淡出，vertical纵向切换，horizontal横向切换
        this.speed = 'fast'; // 速度，毫秒，数据越大越快

        // 事件定义
        this._events = {
        };

        // 私有属性
        //
        this._count = null;  // tab数量
        this._index = 1; // 当前需要
        this._autoTimer = null; // 定动滚动定时器
        this._hideTimer = null; // 隐藏定时器

        // 私有方法
        // 
        // 自动滚动
        var _slideAuto = function () {
            if (this._count > 1 && this.autosec) {
                _stopAuto.call(this);
                this._autoTimer = setInterval(function () {
                    var next = this._index === this._count ? 1 : this._index + 1;
                    this.slideTo(next);
                }.bind(this), this.autosec * 1000);
            }
        };
        // 停止自动动画
        var _stopAuto = function () {
            if (this._autoTimer) {
                clearInterval(this._autoTimer);
            }
        };

        // 影藏节点
        var _hideTab = function (el) {
            el = $(el).removeClass('on');
            var tar = $(el.attr('data-target'), this.tribox);
            tar.stop();
            switch (this.effect) {
                case 'vertical':
                    tar.slideUp(this.speed);
                    break;
                case 'horizontal':
                    tar.animate({width: 'hide'}, this.speed);
                    break;
                default:
                    var position = tar.css('position');
                    tar.css({'position': 'absolute'}).fadeOut(this.speed, function () {
                        tar.css('position', position);
                    });
            }
        };

        // 显示节点
        var _showTab = function (el) {
            el = $(el).addClass('on');
            var tar = $(el.attr('data-target'), this.tribox);
            tar.stop();
            switch (this.effect) {
                case 'vertical':
                    tar.slideDown(this.speed, v.bind(_slideAuto, this));
                    break;
                case 'horizontal':
                    tar.animate({width: 'show'}, this.speed, v.bind(_slideAuto, this));
                    break;
                default:
                    tar.fadeIn(this.speed, v.bind(_slideAuto, this));
            }
        };

        // 初始化menupop方式
        var _initMenuPop = function () {
            if (this.event === 'mouseenter') {
                // 鼠标移入切换处理方式
                $(this.tribox).on('mouseleave', function () {
                    this._hideTimer = v.callst(function () {
                        var old = $('.on[data-target]', this.tribox);
                        if (old)
                            _hideTab.call(this, old);
                    }.bind(this), this._hideTimer, 500);
                }.bind(this)).on('mouseenter', function () {
                    // 鼠标移入区域后，不再隐藏
                    clearTimeout(this._hideTimer);
                }.bind(this));
            } else if (this.event === 'click') {
                // 点击切换处理方式
                $(document).on('click', function (event) {
                    var tar = event.target, old;
                    if (!v.contains(this.tribox, tar)) {
                        if (old = $('.on[data-target]', this.tribox))
                            _hideTab.call(this, old);
                    }
                }.bind(this));
            }
        };

        // 共有方法
        // 
        // 初始化
        this.construct = function (options) {
            this.parent();

            this._count = v.$s('[data-target]', this.anchor).length;

            $(this.tribox).on('mouseenter', v.bind(_stopAuto, this)) // 鼠标位于滚动内容时，停止自动滚动
                    .on('mouseleave', v.bind(_slideAuto, this)); // 鼠标移除时，开始自动滚动

            var stimer;
            $(this.anchor).on(this.event, this.toggle, function (event) {
                stimer = v.callst(v.bind(this.slideTo, this), stimer, [event.currentTarget], 100);
            }.bind(this));

            // 弹出菜单方式处理
            if (this.menupop) {
                _initMenuPop.call(this);
            }

            _slideAuto.call(this);
        };

        // 移动到某一屏
        this.slideTo = function (tar) {
            var tab = v.isNumber(tar) ? $(this.toggle + ':eq(' + (tar - 1) + ')') : $(tar);
            if (tab.length) {
                var old = $('.on[data-target]', this.tribox);
                if (tab.get(0) !== old.get(0)) {  // 同tab不切换
                    _stopAuto.call(this);
                    this._index = tab.index() + 1;
                    // 隐藏原tab
                    if (old) {
                        _hideTab.call(this, old);
                        // 新tab区域放到原tab区域之后
                        $(tab.attr('data-target'), this.tribox).insertAfter($(old.attr('data-target'), this.tribox));
                    }
                    // 显示新tab
                    _showTab.call(this, tab);
                }
            }
        };
    });
    $(function () {
        v.SlideTab.init();
    });
})();
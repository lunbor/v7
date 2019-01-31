/**
 * Slideshow Script
 * 幻灯片插件
 * v-slideshow="{slide:li}"
 * 
 * 切换按钮只有一个时为切换模板，会自动创建对应的切换按钮，否则为tab控件方式
 * 切换的幻灯位置会调整成据对定位，请注意
 */
v.Slideshow || v.kit('Slideshow', function (self) {

    // 静态属性
    self.anchor = 'v-slideshow';

    // -----------------------------------
    // 公共属性
    // ------------------------------------
    this.anchor = null; // 插件区域
    this.slide = null;  // 幻灯播放切片
    this.toggle = null;  // tab触发切换按钮样式，为空即没有切换按钮
    this.next = '.next';  // 下一个切换按钮样式
    this.prev = '.prev'; // 上一个切换按钮样式
    this.tgnum = '.num';  // 幻灯张数显示对象
    this.event = 'mouseenter'; // 切换事件
    this.autosec = 0; // 自动切换时长，秒, 0|false不自动滚动
    this.menupop = false; // 是否弹出菜单模式
    this.effect = 'horizontal'; // 效果, fade淡入淡出，vertical纵向切换，horizontal横向切换
    this.speed = 'normal'; // 速度，毫秒，数据越大越慢


    // 事件定义
    this._events = {
    };

    // -----------------------------------
    // 私有属性
    // ------------------------------------
    this._tabs = []; // 触发tab
    this._slides = [];  // 片段
    this._count = null;  // tab数量
    this._index = 0; // 当前幻灯，从0开始
    this._auto = false; // 是需要自动滚动
    this._aspect = null; // 动画方向
    this._slidebox = null;  // 幻灯播放区域


    // -----------------------------------
    // 私有方法
    // ------------------------------------

    /**
     * 自动滚动
     */
    var _slideAuto = function () {
        if (this._auto) {
            var next = this._index === this._count ? 1 : this._index + 1;
            this.slideTo(next);
            v.callst(v.bind(_slideAuto, this), this.autosec * 1000);
        }
    };

    /**
     * 停止自动动画
     */
    var _stopAuto = function () {
        this._auto = false;
    };

    /**
     * 开始自动动画
     */
    var _startAuto = function () {
        if (this._count > 1 && this.autosec) {
            this._auto = true;
            v.callst(v.bind(_slideAuto, this), this.autosec * 1000);
        }
    };

    /**
     * 初始化切换btn
     */
    var _initToggle = function () {
        // tab切换初始化
        var tabs = v.$s(this.toggle, this.anchor);
        if (tabs.length === 1) {  // 以第一个为参照生成tab按钮，多个则使用已有tab，无则代表没有切换
            var tab = tabs[0];
            for (var i = 1; i < this._count; i++) {
                tabs.push(tab.cloneNode(true));
                tab.parentNode.appendChild(tabs[i]);
            }
        }
        v.forEach(tabs, function (el, i) {
            v.domAttr(el, {'data-index': i + 1});
            $(this.tgnum, el).html(i + 1);
        }.bind(this));

        this._tabs = tabs;
    };

    /**
     * 初始化幻灯内容
     */
    var _initChip = function () {
        this._slides = v.$s(this.slide, this.anchor);
        this._count = this._slides.length;
        this._slidebox = this._slides[0].parentNode;

        // 幻灯内容的父级必须有定位设置
        var $chipbox = $(this._slidebox);
        'relative|absolute'.indexOf($chipbox.css('position')) > -1 || $chipbox.css({'position': 'relative'});
        $chipbox.css({'overflow': 'hidden'});

        // 幻灯片内容为绝对定位
        v.forEach(this._slides, function (el) {
            $(el).css({'position': 'absolute', 'left': 0, 'top': 0, 'zIndex': 1});
        });
    };

    // -----------------------------------
    // 公有方法
    // ------------------------------------

    /**
     * 初始化
     */
    this.construct = function () {
        this.parent();

        _initChip.call(this);
        !this.toggle || _initToggle.call(this);

        // 自动滚动处理
        $(this.anchor).on('mouseenter', v.bind(_stopAuto, this)) // 鼠标位于滚动内容时，停止自动滚动
                .on('mouseleave', v.bind(_startAuto, this)); // 鼠标移除时，开始自动滚动

        // tab触发处理
        $(this.anchor).on(this.event, this.toggle, function (event) {
            var index = parseInt($(event.currentTarget).attr('data-index') - 1);
            v.callst(v.bind(this.slideTo, this), [index], 100);
        }.bind(this));

        // 上下切换处理
        $(this.anchor).on('click', this.next, v.bind(this.toNext, this));
        $(this.anchor).on('click', this.prev, v.bind(this.toPrev, this));

        // 切换到第一张
        this._index = this._count - 1;
        this.slideTo(0);

        _startAuto.call(this);
    };

    /**
     * 移动到某一屏
     * @param {Integer} index 从0开始计算
     */
    this.slideTo = function (index) {
        if (index >= this._count)
            index = 0;
        else if (index < 0) {
            index = this._count - 1;
        }
        if (this._index !== index) {
            var chip = this._slides[index], // 需要显示的幻灯片
                    chip2 = this._slides[this._index]; // 原来显示的幻灯片
            //_stopAuto.call(this);
            switch (this.effect) {
                case 'vertical':  // 纵向滚动
                    var offset = index > this._index ? this._slidebox.clientHeight : 0 - this._slidebox.clientHeight;
                    $(chip2).stop().css({'zIndex': 8}).animate({'top': offset, 'opacity': 0.4}, this.speed, function () {
                        $(chip2).css({'zIndex': 1, 'top': 0});
                    });
                    $(chip).stop().css({'zIndex': 9, 'top': offset, 'opacity': 0}).animate({'top': 0, 'opacity': 1}, this.speed);
                    break;
                case 'horizontal':  // 横向滚动
                    var offset = index > this._index ? this._slidebox.clientWidth : 0 - this._slidebox.clientWidth;
                    $(chip2).stop().css({'zIndex': 8}).animate({'left': offset, 'opacity': 0.4}, this.speed, function () {
                        $(chip2).css({'zIndex': 1, 'left': 0});
                    });
                    $(chip).stop().css({'zIndex': 9, 'left': offset, 'opacity': 0}).animate({'left': 0, 'opacity': 1}, this.speed);
                    break;
                default:  // 透明度切换
                    $(chip2).stop().css({'zIndex': 8}).animate({'opacity': 0.4}, function () {
                        $(chip2).css({'zIndex': 1});
                    });
                    $(chip).stop().css({'zIndex': 9, 'opacity': 0}).animate({'opacity': 1}, this.speed);
            }
            $(this._tabs[this._index]).removeClass('on');
            $(this._tabs[index]).addClass('on');
            this._index = index;
        }
    };

    /**
     * 显示下一副
     */
    this.toNext = function () {
        this.slideTo(this._index + 1);
    };

    /**
     * 显示前一副
     */
    this.toPrev = function () {
        this.slideTo(this._index - 1);
    };


});
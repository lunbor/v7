/**
 * Crop Script
 * 图片裁剪插件
 * 
 */

v.Crop || (function () {
    v.Crop = v.extend(v.UI, function (self) {

        self.anchor = 'data-crop';

        // 共有变量
        this.anchor = '';  // 裁剪图片
        this.tribox = '';  // 裁剪框
        this.cutwx = 128;  // 裁剪框宽
        this.cuthy = 128;  // 裁剪框高

        // 事件定义
        this._events = {
            'croped': [] // 裁剪完成，返回裁剪坐标
        };

        // 私有变量
        this._imgwx = 0; // 图片原始宽
        this._imghy = 0; // 图片原始高
        this._imgpos = {// 图片位置
            width: 0,
            height: 0,
            minwidth: 0, // 最小宽度
            minheight: 0  // 最小高度
        };
        this._ratio = 1; // 缩放比例，最大1
        this._ratebar; // 比例条
        this._progressbar; // 比例调进度
        this._boxpos = {// 整框坐标
            width: 0,
            height: 0
        };
        this._cutbox; // 剪切框
        this._cutpos = {// 裁剪框位置
            left: 0,
            top: 0,
            width: 0,
            height: 0
        };
        this._dragtar = null; // 拖动目标
        this._dragpos = {// 开始拖动坐标
            left: 0,
            top: 0,
            x: 0,
            y: 0
        };
        this._dragbar = null;  // 缩放条拖动对象

        // cutbox模板
        var _cutTpl = '<table style="position:absolute; border:none; border-collapse:collapse; margin:0; padding:0; width:100%; height:100%; opacity:0.6; z-index:3">\
                        <tr><td style="height:{{top}}px; background:#000;" colspan="3"></td></tr>\
                        <tr><td style="width:{{left}}px; background:#000;"></td>\
                            <td class="cut_box" style="border:1px solid #fff; background:transparent; width:{{width}}px; height:{{height}}px;"></td>\
                            <td style="width:{{left}}px; background:#000;"></td></tr>\
                        <tr><td style="height:{{top}}px; background:#000;" colspan="3">\
                            <div class="ratebar" style="width:100%;height:6px; cursor:pointer;border-radius:3px;background:#fff;">\
                                <div class="progress" style="width:100%;height:6px;background:#fff;border-radius:3px;position:relative;">\
                                    <div style="width:36px;height:6px;background:#666;border-radius:3px;position:absolute;right:0; color:#fff;">\n\
                                        <span style="position:absolute;left:2px;top:0px;line-height:3px;font-weight:bold;font-size:6px;">-</span>\n\
                                        <span style="position:absolute;right:2px;top:0px;line-height:3px;font-weight:bold;font-size:6px;">+</span>\n\
                                </div>\
                                </div>\
                            </div>\
                        </td></tr>\
                        </table>';

        /**
         * 
         * 取得event对象
         */
        var _fixEvent = function (event) {
            event = event.originalEvent;
            if (event.touches)
                event = event.touches[0];
            return event;
        };

        /**
         * 开始拖动
         */
        var _dragstart = function (event) {
            // 开始拖动位置
            if (this._imgwx > 0) {
                this._dragtar = this.anchor;
                this._dragpos = $(this._dragtar).position();
                event = _fixEvent(event);
                this._dragpos.x = event.clientX - this._dragpos.left;
                this._dragpos.y = event.clientY - this._dragpos.top;
                $(this._cutbox).css('cursor', 'move');
                $(document).on('mousemove touchmove', v.bind(_draging, this));
                $(document).on('mouseup touchend', v.bind(_dragend, this));
            }
        };
        /**
         * 拖动过程
         */
        var _draging = function (event) {
            if (this._dragtar) {
                window.getSelection ? window.getSelection().removeAllRanges() : document.selection.empty();
                event = _fixEvent(event);
                $(this._dragtar).css({
                    'left': Math.max(this._cutpos.left + this.cutwx + 2 - this._imgpos.width, Math.min(this._cutpos.left, event.clientX - this._dragpos.x)),
                    'top': Math.max(this._cutpos.top + this.cuthy + 2 - this._imgpos.height, Math.min(this._cutpos.top, event.clientY - this._dragpos.y))
                });
            }
        };

        /**
         * 停止拖动
         */
        var _dragend = function () {
            if (this._dragtar) {
                $(document).off('mousemove touchmove', v.bind(_draging, this));
                $(document).off('mouseup touchend', v.bind(_dragend, this));
                $(this._cutbox).css({'cursor': 'auto'});
                this._dragtar = null;
                _triCroped.call(this);
            }
        };

        // 比列条拖动结束
        var _dragbarEnd = function () {
            if (this._dragbar) {
                $(document).off('mousemove touchmove', v.bind(_dragbarIng, this));
                $(document).off('mouseup touchend', v.bind(_dragbarEnd, this));
                _triCroped.call(this);
            }
        };

        // 比例调拖动过程
        var _dragbarIng = function (event) {
            if (this._dragbar) {
                event = _fixEvent(event);
                var x = event.clientX - $(this._dragbar).offset().left;
                x = Math.max(this._boxpos.width * this._imgpos.ratio, Math.min(this._boxpos.width, x));
                $(this._progressbar).css({'width': x});
                // 计算新比例尺寸
                this._ratio = (x / this._boxpos.width).toFixed(2);
                this._imgpos.width = this._imgwx * this._ratio;
                this._imgpos.height = this._imghy * this._ratio;
                this._imgpos.left = Math.min(this._cutpos.left, Math.max(this._cutpos.left + this._cutpos.width - this._imgpos.width + 2, this.anchor.offsetLeft));
                this._imgpos.top = Math.min(this._cutpos.top, Math.max(this._cutpos.top + this._cutpos.height - this._imgpos.height + 2, this.anchor.offsetTop));
                $(this.anchor).css(this._imgpos);
            }
        };

        // 完成时间触发
        var _triCroped = function () {
            var pos = $(this.anchor).position();
            var xy = {
                'width': this.cutwx,
                'height': this.cuthy,
                'left': this._cutpos.left - pos.left,
                'top': this._cutpos.top - pos.top,
                'ratio': this._ratio
            };
            // 触发裁剪完成事件
            this.triEvent('croped', {
                'target': this.anchor,
                'whpos': xy
            });
        };

        // 初始化图片数据
        var _initImg = function () {
            if (this._imgwx <= 0) {
                $(this.anchor).on('load', function () {
                    $(this.anchor).off('load');
                    v.callst(_initImg.bind(this));
                }.bind(this));
            }
            // 坐标比例计算
            this._imgwx = this.anchor.offsetWidth;
            this._imghy = this.anchor.offsetHeight;
            var ratio = Math.max(this._cutpos.width / this._imgwx, this._cutpos.height / this._imghy);  // 最小比例
            this._imgpos = {
                'width': this._imgwx,
                'height': this._imghy,
                'minwidth': this._imgwx * ratio,
                'minheight': this._imghy * ratio,
                'ratio': ratio
            };
            this._ratio = 1;  // 恢复1：1的比例
            $(this._progressbar).css({'width': this._boxpos.width});

            this._dragtar = this.anchor;
            $(this.anchor).css({'top': 0, 'left': 0});
            v.callst(_dragend.bind(this));  // 延迟执行，才能触发事件
        };

        // 初始化裁剪框
        var _initCutbox = function () {
            this._boxpos = {
                width: this.tribox.offsetWidth,
                height: this.tribox.offsetHeight
            };
            this._cutpos = {
                'top': (this._boxpos.height - this.cuthy - 2) / 2,
                'left': (this._boxpos.width - this.cutwx - 2) / 2,
                'width': this.cutwx,
                'height': this.cuthy
            };
            this._cutbox = v.str2Dom(v.strParse(_cutTpl, this._cutpos), 0);
            $(this._cutbox).css({'zIndex': 3, 'opacity': 0.6}).appendTo(this.tribox);

            // 拖动
            $(this.tribox).on('mousedown touchstart', v.bind(_dragstart, this));
        };

        // 初始化比例缩放
        var _initRatebar = function () {
            this._ratebar = v.$('.ratebar', this._cutbox);
            this._progressbar = v.$('.progress', this._ratebar);
            // 拖动
            $(this._ratebar).on('mousedown touchstart', function (event) {
                v.stopEvent(event);
                if (this._imgwx > 0)
                    this._dragbar = this._ratebar;
                _dragbarIng.call(this, event);
                $(document).on('mousemove touchmove', v.bind(_dragbarIng, this));
                $(document).on('mouseup touchend', v.bind(_dragbarEnd, this));
            }.bind(this));
        };

        // 初始化
        this.construct = function () {
            this.parent();
            // 初始化tribox
            if (this.tribox === this.anchor)
                this.tribox = this.anchor.parentNode;
            if ($(this.tribox).css('position') === 'static')
                this.tribox.style.position = 'relative';
            this.tribox.style.overflow = 'hidden';

            // 初始化img
            $(this.anchor).css({'position': 'absolute', 'zIndex': 1});

            // 建立剪切框
            _initCutbox.call(this);
            _initImg.call(this);
            _initRatebar.call(this);
        };

        // 重新设置图片
        this.reimg = function (/*src*/) {
            $(this.anchor).css({width: 'auto', height: 'auto'});
            if (arguments[0])
                this.anchor.src = arguments[0];
            v.callst(_initImg.bind(this));
        };
    });
})();
$(function () {
    v.Crop.init();
});
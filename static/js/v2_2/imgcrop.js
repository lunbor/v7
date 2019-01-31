/**
 * ImgCrop Script
 * 图片裁剪插件
 * 
 * v-imgcrop="{}"
 * 
 */

v.ImgCrop || v.kit('ImgCrop', function (self) {

    self.anchor = 'v-imgcrop';

    // 共有变量
    this.anchor = null;  // 被裁剪框，里面包含被裁剪图片img
    this.cutwx = 128;  // 裁剪框宽
    this.cuthy = 128;  // 裁剪框高

    // 事件定义
    this._events = {
        'croped': [] // 裁剪完成，返回裁剪坐标
    };

    // 私有变量
    this._imgel = null; // 裁剪框
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
                        <tr><td style="height:<%=top%>px; background:#000;" colspan="3"></td></tr>\
                        <tr><td style="width:<%=left%>px; background:#000;"></td>\
                            <td class="cut_box" style="border:1px solid #fff; background:transparent; width:<%=width%>px; height:<%=height%>px;"></td>\
                            <td style="width:<%=left%>px; background:#000;"></td></tr>\
                        <tr><td style="height:<%=top%>px; background:#000;" colspan="3">\
                            <div class="ratebar" style="width:100%;height:18px; cursor:pointer;border-radius:9px;background:#666;">\
                                <div class="progress" style="width:100%;height:18px;background:#333;border-radius:9px;position:relative;">\
                                    <div style="width:18px;height:18px;line-height:12px;font-size:30px;text-align:center;background:#aaa;border-radius:9px;position:absolute;right:0; color:#fff;">-</div>\
                                </div>\
                            </div>\
                        </td></tr>\
                    </table>';

    /**
     * 取得event对象
     * 兼容touch
     * @param {Event} event
     * @returns {Event} 
     */
    var _fixEvent = function (event) {
        event = event.originalEvent;
        if (event.touches)
            event = event.touches[0];
        return event;
    };

    /**
     * 开始拖动
     * @param {Event} event
     */
    var _dragstart = function (event) {
        // 开始拖动位置
        if (this._imgwx > 0) {
            this._dragtar = this._imgel;
            this._dragpos = $(this._dragtar).position();
            event = _fixEvent(event);
            this._dragpos.x = event.clientX - this._dragpos.left;
            this._dragpos.y = event.clientY - this._dragpos.top;
            $(this._cutbox).css('cursor', 'move');
            $(document).on('mousemove touchmove', v.bind(_draging, this))
                    .on('mouseup touchend', v.bind(_dragend, this));
        }
    };

    /**
     * 完成裁剪，触发回调
     */
    var _triCroped = function () {
        var pos = $(this._imgel).position();
        var xy = {
            'width': this.cutwx,
            'height': this.cuthy,
            'left': this._cutpos.left - pos.left,
            'top': this._cutpos.top - pos.top,
            'ratio': this._ratio
        };
        // 触发裁剪完成事件
        this.triEvent('croped', {
            'target': this._imgel,
            'whpos': xy
        });
    };

    /**
     * 拖动过程
     * @param {Event} event 
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
            $(document).off('mousemove touchmove', v.bind(_draging, this))
                    .off('mouseup touchend', v.bind(_dragend, this));
            $(this._cutbox).css({'cursor': 'auto'});
            this._dragtar = null;
            _triCroped.call(this);
        }
    };

    /**
     * 比列条拖动结束
     */
    var _dragbarEnd = function () {
        if (this._dragbar) {
            $(document).off('mousemove touchmove', v.bind(_dragbarIng, this))
                    .off('mouseup touchend', v.bind(_dragbarEnd, this));
            _triCroped.call(this);
        }
    };

    /**
     * 比例调拖动过程
     * @param {Event} event
     */
    var _dragbarIng = function (event) {
        if (this._dragbar) {
            event = _fixEvent(event);
            // 左右留空，向左偏移ox
            var x = event.clientX - $(this._dragbar).offset().left + 9, ox = this.cutwx / 4;
            x = Math.max(this._boxpos.width * this._imgpos.ratio - ox, Math.min(this._boxpos.width - ox, x));
            $(this._progressbar).css({'width': x});
            // 计算新比例尺寸
            this._ratio = parseFloat(((x + ox) / this._boxpos.width).toFixed(2));
            this._imgpos.width = this._imgwx * this._ratio;
            this._imgpos.height = this._imghy * this._ratio;
            this._imgpos.left = Math.min(this._cutpos.left, Math.max(this._cutpos.left + this._cutpos.width - this._imgpos.width + 2, this._imgel.offsetLeft));
            this._imgpos.top = Math.min(this._cutpos.top, Math.max(this._cutpos.top + this._cutpos.height - this._imgpos.height + 2, this._imgel.offsetTop));
            $(this._imgel).css(this._imgpos);
        }
    };

    /**
     * 初始化图片数据
     */
    var _initImg = function () {
        if (this._imgwx <= 0) {
            $(this._imgel).one('load', function () {
                v.callst(_initImg.bind(this));
            }.bind(this));
        }
        // 坐标比例计算
        this._imgwx = this._imgel.offsetWidth;
        this._imghy = this._imgel.offsetHeight;
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

        this._dragtar = this._imgel;
        $(this._imgel).css({'top': 0, 'left': 0});
        v.callst(_dragend.bind(this));  // 延迟执行，才能触发事件
    };

    /**
     * 初始化裁剪框
     */
    var _initCutbox = function () {
        this._boxpos = {
            width: this.anchor.offsetWidth,
            height: this.anchor.offsetHeight
        };
        this._cutpos = {
            'top': (this._boxpos.height - this.cuthy - 2) / 2,
            'left': (this._boxpos.width - this.cutwx - 2) / 2,
            'width': this.cutwx,
            'height': this.cuthy
        };
        this._cutbox = v.str2Dom(v.strParse(_cutTpl, this._cutpos));
        $(this._cutbox).css({'zIndex': 3, 'opacity': 0.6}).appendTo(this.anchor);

        // 拖动
        $(this.anchor).on('mousedown touchstart', v.bind(_dragstart, this));
    };

    /**
     * 初始化比例缩放条
     */
    var _initRatebar = function () {
        this._ratebar = v.$('.ratebar', this._cutbox);
        this._progressbar = v.$('.progress', this._ratebar);
        $(this._progressbar).css({'width': this._boxpos.width - this.cutwx / 4});
        // 拖动
        $(this._ratebar).on('mousedown touchstart', function (event) {
            event.preventDefault();
            event.returnValue = false;
            if (this._imgwx > 0)
                this._dragbar = this._ratebar;
            _dragbarIng.call(this, event);
            $(document).on('mousemove touchmove', v.bind(_dragbarIng, this))
                    .on('mouseup touchend', v.bind(_dragbarEnd, this));
        }.bind(this));
    };

    /**
     * 初始化
     */
    this.construct = function () {
        this.parent();
        this._imgel = v.$('img', this.anchor);
        if ($(this.anchor).css('position') === 'static')
            this.anchor.style.position = 'relative';
        this.anchor.style.overflow = 'hidden';

        // 初始化img
        $(this._imgel).css({'position': 'absolute', 'zIndex': 1});

        // 建立剪切框
        _initCutbox.call(this);
        _initImg.call(this);
        _initRatebar.call(this);
    };

    /**
     * 重设图片
     */
    this.reimg = function (/*src*/) {
        $(this._imgel).css({width: 'auto', height: 'auto'});
        if (arguments[0])
            this._imgel.src = arguments[0];
        v.callst(_initImg.bind(this));
    };
});
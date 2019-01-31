/**
 * ImgBox Script
 * 图片弹出对话框放大预览插件
 * v-imgbox="tribox:#img_box"
 */

v.ImgBox || v.kit('ImgBox', ['v.PopBox', 'popbox.js'], function (self) {

    self.anchor = 'v-imgbox';

    // 可让触发按钮options定义
    this.mask = true;  // 显示mask
    this.position = 'middle';  // 默认居中
    this.tribox = null;  // 图片预览的区域
    this.toggle = 'img';  // 点击图片弹起
    this.prev = '.prev';  // 上一张按钮
    this.next = '.next'; // 下一张按钮

    this._imgs = [];  // 所有图片
    this._index = 0;  // 当前显示图片位置

    /**
     * 显示图片
     * @param {Element} img
     */
    var _showImg = function (img) {
        var img1 = v.$('img', this.anchor), // 显示的图
                img2 = img1.cloneNode(); // 切换动画图片
        $(img2).css({'position': 'absolute', 'left': 0, 'right': 0}).appendTo(img1.parentNode);
        $(img1).attr('src', img.src).css({'opacity': 0.01});
        $(img2).animate({'width': $(img1).width(), 'height': $(img1).height()}, this.speed, function () {  // 切换到新图大小
            $(img1).css({'opacity': 1});
        }).fadeOut(1000, function () {  // 渐变到新图
            $(img2).remove();
        });
        this._index = parseInt($(img).attr('data-index'));
    };

    /**
     * 初始化左右切换
     */
    var _initSwitcher = function () {
        // 向前
        $(this.prev, this.anchor).on('click', function () {
            var index = Math.max(this._index - 1, 0);
            index === this._index || _showImg.call(this, this._imgs[index]);
        }.bind(this));
        // 向后
        $(this.next, this.anchor).on('click', function () {
            var index = Math.min(this._index + 1, this._imgs.length - 1);
            index === this._index || _showImg.call(this, this._imgs[index]);
        }.bind(this));
    };

    /**
     * 初始化图片触发预览
     */
    var _initImg = function () {
        $(this.tribox).on('click', 'img', function (event) {
            // 读入所有图片
            this._imgs = v.$s('img', this.tribox);
            for (var i = 0, len = this._imgs.length; i < len; i++) {
                $(this._imgs[i]).attr('data-index', i);
            }
            // 显示当前图片
            _showImg.call(this, event.currentTarget);
        }.bind(this));
    };

    // 初始化
    this.construct = function () {
        this.parent();
        _initSwitcher.call(this);
        _initImg.call(this);
    };
});
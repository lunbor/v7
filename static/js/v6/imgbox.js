/**
 * ImgBox Script
 * 图片弹出对话框放大预览插件
 * v-imgbox="tribox:#img_box"
 */

v.ImgBox || v.kit('ImgBox', ['v.Popbox', 'popbox.js'], function (self) {
    self.anchor = 'v-imgbox';

    // 可让触发按钮options定义
    this.mask = true;  // 显示mask
    this.position = 'middle';  // 默认自动
    this.tribox = null;  // 触发图片显示的区域
    this.toggle = 'img';

    // 初始化
    this.construct = function () {
        this.parent();
        v.$(this.tribox).on('click', 'img', (event) => {
            v.$('img', this.anchor).attr('src', event.srcTarget.src);
        });
    };
});
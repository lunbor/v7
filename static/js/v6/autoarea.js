/**
 * Autoarea Script
 * 碎片输入插件
 * v-autoarea="{}"
 */
v.Autoarea || v.kit('Autoarea', function (self) {
    // 静态属性
    self.anchor = 'v-autoarea';
    // 公共属性
    //
    this.anchor = null; // textarea

    // 初始化
    this.construct = function () {
        this.parent();
        v.$(this.anchor).css({'overflowY': 'hidden'});

        // event
        v.$(this.anchor).on('input', this.resize.bind(this))
                .watch('value', () => {
                    this.resize();
                    setTimeout(this.resize.bind(this), 200);
                });
        window.addEventListener('resize', this.resize.bind(this));
    };

    // 重新改变大小
    this.resize = function () {
        v.$(this.anchor).css({'height': 'auto'}).css({height: this.anchor.scrollHeight});
    };

});
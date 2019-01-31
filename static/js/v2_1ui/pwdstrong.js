/**
 * PwdStrong Script
 * 密码强度插件
 * data-pwdstrong="v2ui"
 */

v.PwdStrong || (function () {
    v.PwdStrong = v.extend(v.UI, function (self) {
        // 静态属性
        self.anchor = 'data-pwdstrong';

        // 公共属性
        this.anchor = null; // 密码组件

        // 事件定义
        this._events = {
            change: [], // 密码变化事件
            empty: [], // 空密码事件
            weak: [], // 弱密码事件
            medium: [], //中等强度密码事件
            strong: [] //高等强度密码事件
        };

        // 字符串强度
        var _getPwdStrong = function (e) {
            var r = 0;
            if (0 < e.length && e.length < 6)
                return 1;
            /[0-9]/.test(e) && (r += 1);
            /[a-z]/i.test(e) && (r += 1);
            return /^[a-z0-9]*$/i.test(e) || (r += 1), r;
        };

        // 检查密码强弱
        var _checkStrong = function () {
            var level = _getPwdStrong.call(this, this.anchor.value);
            if (level > 0) {
                // 有输入触发强度事件
                var strong = level > 2 ? 'strong' : (level === 2 ? 'medium' : 'weak');
                this.triEvent('change', {
                    target: this.anchor,
                    pwdstrong: strong
                });
                this.triEvent(strong, {
                    target: this.anchor
                });
            } else {
                // 无输入触发空密码事件
                this.triEvent('empty', {
                    target: this.anchor
                });
            }
        };

        // 共有方法

        // 初始化
        this.construct = function () {
            this.parent();
            // 输入框发生改变时效验
            $(this.anchor).on('keyup input', function () {
                var timer; // 延迟防止重复执行
                timer = v.callst(function () {
                    _checkStrong.call(this);
                }.bind(this), timer, 100);
            }.bind(this));
        };
    });
})();
$(function () {
    v.PwdStrong.init();
});
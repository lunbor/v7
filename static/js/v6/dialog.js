/**
 * PopBox Script
 * 弹出对话框插件
 * data-popbox=""
 */

// alert 对话框
v.AlertBox || v.kit('AlertBox', ['v.Popbox', 'popbox.js'], function (self) {
    self.anchor = 'v-alertbox';
    self.insobj = null;  // 单实例对象

    // 可让触发按钮options定义
    this.mask = true;  // 显示mask
    this.position = 'auto';  // 默认自动
    this.onok = null; // 回调方法

    // 对话框定义
    this.okel = '.ok';  // 确认按钮
    this.toggle = '.alert-toggle';  // 触发按钮默认样式
    this.messagel = '.message';  // 消息显示区域

    this._evtSure = null;  // 关闭回调函数，带触发event.target
    this._mask = null;  // 遮罩mask

    // 初始化，第一个对话框作为全局使用
    this.construct = function () {
        this.mask = true; // 必须建立mask
        this.position = 'auto'; // 必须建立arrow
        this.parent();
        this.messagel = v.$(this.messagel, this.anchor);
        this._mask = this.mask;
        // 确认按钮
        if (this.okel) {
            v.$(this.okel, this.anchor).on('click', v.bind(this.sureok, this));
        }

        this.constructor.insobj || (this.constructor.insobj = this);
    };

    // 显示消息
    this.show = function (message, fn) {
        // 默认的显示定义
        let opts = {
            'mask': true,
            'position': 'middle',
            'onok': fn
        };
        this._evtSure = null;  // 每次显示清空回调函数
        if (message) {
            this._toggle = null;
            if (v.isObject(message)) {
                // 对象处理
                let options = message;
                message = options.message;
                delete options.message;
                if (options.toggle) {
                    this._toggle = v.$(options.toggle);
                    delete options.toggle;
                }
                v.extend(opts, options, v.COVER);
            }
        } else if (this._toggle) {
            message = v.$(this._toggle).attr('data-message');  // 消息
            let options = v.attr2Json(this._toggle.getAttribute('data-options') || '');
            v.extend(opts, options, v.COVER);
        }
        if (message) {
            this.messagel.innerHTML = message;
            // 回调函数
            this._evtSure = opts.onok ? (v.isString(opts.onok) ? this.bubble(opts.onok) : opts.onok) : null;  // 回调
            // 遮罩设置
            this.mask = opts.mask ? this._mask : null;
            // 位置
            this.position = opts.position;

            v.$(this._arrow).css({'display': 'static|middle'.indexOf(this.position) === -1 ? 'block' : 'none'});

            this.parent();
        }
    };

    // 确认事件
    this.sureok = function () {
        this.close();
        if (this._evtSure) {
            this._evtSure({
                'target': this._toggle
            });
        }
    };
}).then(function () {
    // 确认对话框，类的定义是异步的，所以then
    v.kit('ConfirmBox', v.AlertBox, function (self) {
        self.anchor = 'v-confirmbox';
    }).then(function () {
        // 建立确认对话框实例
        v.ready(function () {
            v.confirm = function (message, fn) {
                if (v.ConfirmBox.insobj) {
                    v.ConfirmBox.insobj.show(message, fn);
                } else if (confirm(message)) {
                    fn();
                }
            };
        });
    });
    // 建立提示对话框实例
    v.ready(function () {
        v.alert = function (message/*, fn*/) {
            v.AlertBox.insobj ? v.AlertBox.insobj.show(message, arguments[1]) : alert(message);
        };
    });

});
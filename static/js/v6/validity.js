/**
 * Validity Script
 * 表单验证插件
 * data-validity="key:value;events{failure:function;success:function}"
 * 
 * data-valid="date"
 */
v.Validity || v.kit('Validity', function (self) {
    // 静态属性
    self.anchor = 'v-validity';

    // 数据效验
    self.validity = {
        // 不允许为空
        required: function (val) {
            return val.length > 0 || 'Not empty';
        },
        // 正则表达式检测
        regex: function (val, regx) {
            return regx.test(val) || 'Invalid input';
        },
        // 邮件格式规则
        email: function (val) {
            return /^\w[-.\w]*@([-a-z0-9]+\.)+[a-z]{2,4}$/i.test(val) || 'Invalid email';
        },
        // 日期格式规则
        date: function (val) {
            return /^\b(19|20)?[0-9]{2}[- \/.](0?[1-9]|1[012])[- \/.](0?[1-9]|[12][0-9]|3[01])\b$/.test(val) || 'Invalid date';
        },
        // 全数字
        digit: function (val) {
            return /^[0-9]*$/i.test(val) || 'Must digit';
        },
        // 全字母
        alpha: function (val) {
            return /^[a-zA-Z]*$/i.test(val) || 'Must alpha';
        },
        // 数字与字母
        alnum: function (val) {
            return /^[a-zA-Z0-9_]*$/i.test(val) || 'Must digit or alnum or underline';
        },
        // 长度区间
        length: function (val, min, max) {
            let l = val.length;
            return l >= min && (v.isNull(max) || l <= max) || (min != max ? 'Length must in [' + min + '] - [' + max + ']' : 'Length must be [' + min + ']');
        },
        // 大小区间
        between: function (val, min, max) {
            val = Number(val);
            return (v.isNull(min) || val >= min) && (v.isNull(max) || val <= max) || 'Number must in [' + min + '] - [' + max + ']';
        },
        // 等于另一个域值
        equal: function (val, field) {
            return v.$(field).value === val || 'Twice input must match';
        }
    };

    // 公共属性
    //
    this.anchor = null; // tab选择区
    this.failureClass = 'failure'; // 验证错误class
    this.successClass = 'success'; // 验证成功class
    this.classNode = null; // 定义class的节点

    // 事件定义
    this._events = {
        failure: [], // 失败事件
        success: []  // 成功事件
    };

    var _errTimer;  // 错误时钟

    // 效验element效验结果
    var _checkValid = function (el) {
        // 先效验html5属性
        let valid = el.checkValidity();
        if (valid) {
            let msg = true, val = v.$(el).val(), fs = el.getAttribute('data-valid').replace(/\s+;\s+/g, ';').replace(/;\s*$/, '').split(';');
            // 判断空
            if (fs[0] === '*') {
                msg = self.validity.required(val);
                fs.shift();
            }
            // 数据不为空判断其他检查
            if (msg === true && !v.isNull(val)) {
                for (let i = 0, len = fs.length; i < len; i++) {
                    let [fn, args] = this.fnargs(fs[i], val);
                    fn = self.validity[fn] || this.bubble(fn);
                    msg = fn(...args);
                    if (msg !== true)
                        break;
                }
            }
            // 设置验证消息
            valid = msg === true;
            el.setCustomValidity(valid ? '' : this.trans(msg));
        }
        return valid;
    };

    // 显示效验消息
    var _showValid = function (el) {
        let ctx = this.classNode ? v.$(el).$p(this.classNode, this.anchor) : null;
        let msgEl = (el.name ? v.$('[for="' + el.name + '"]', ctx || this.anchor).original : null)
                || (this.anchor.name ? v.$('[for="' + this.anchor.name + '"]', this.anchor).original : null);
        if (msgEl) {
            msgEl.innerHTML = el.validationMessage;
            // 样式节点为输入框父类
            let clsEl = v.$(ctx || el);
            el.validationMessage ? clsEl.rmvClass(this.successClass).addClass(this.failureClass)
                    : clsEl.rmvClass(this.failureClass).addClass(this.successClass);
        }
    };

    // 效验element
    var _validElement = function (el) {
        let rs;
        if (rs = _checkValid.call(this, el)) {
            // 效验通过，触发成功事件
            this.triEvent('success', {
                'target': el
            });
        } else {
            // 效验未通过，触发失败事件
            this.triEvent('failure', {
                'target': el,
                'message': el.validationMessage
            });
        }
        // for=el.name显示错误消息
        _showValid.call(this, el);
        return rs;
    };

    // 初始化效验事件
    var _initEvent = function () {
        // 效验事件
        v.$(this.anchor).on('input focusin focusout change', '[data-valid]', (event) => {
            let el = event.target;
            _errTimer = v.callst(() => {
                el.setCustomValidity('');  // 先去除自定义效验消息
                _validElement.call(this, el);
            }, _errTimer, 100);
        });
    };

    // 共有方法
    // 
    // 初始化
    this.construct = function () {
        this.parent();
        _initEvent.call(this);
    };

    // 效验，需所有字段通过效验
    this.checkValid = function (/*ctx*/) {
        let ctx, rs = true, args = arguments;
        if (args.length > 0) {
            ctx = v.$(args[0], this.anchor);
            if (!ctx) {
                return true;
            } else if (ctx.attr('data-valid')) {
                // 效验单个对象
                return _validElement.call(this, ctx);
            }
        }
        // 效验所有表单
        ctx = ctx || this.anchor;
        v.forEach(v.$s('[data-valid]', ctx), (el) => {
            rs = rs && _validElement.call(this, el);
        });
        return rs;
    };

    // 设置效验消息
    this.setValid = function (msg, el = null) {
        // 触发效验失败事件
        !el || (el = v.$(el, this.anchor));
        // 延迟100秒
        _errTimer = v.callst(() => {
            this.triEvent('failure', {
                'target': el,
                'message': msg
            });
            if (el && el.original) {
                // 字段提示消息
                el.setCustomValidity(msg);
                _showValid.call(this, el);
            } else {
                // 总提示消息
                let msgel, name = v.$(this.anchor).attr('name');
                if (name && (msgel = v.$('[for="' + name + '"]', this.anchor))) {
                    msgel.innerHTML = msg;
                }
            }
        }, _errTimer, 100);
    };

    // 清除所有错误
    this.cleanError = function () {
        v.$s('[data-valid]', this.anchor).forEach((el) => {
            el.setCustomValidity('');  // 去除自定义效验消息
            // 清除总提示消息
            let msgel, name = v.$(this.anchor).attr('name');
            if (name && (msgel = v.$('[for="' + name + '"]', this.anchor))) {
                msgel.innerHTML = '';
            }
            this.triEvent('success', {
                'target': el
            });
            _showValid.call(this, el);
        });
    };

    // 语言
    self.trans({
        'zh-cn': {
            'Not empty': '*该字段必填',
            'Invalid input': '无效输入',
            'Invalid email': '无效的邮箱',
            'Invalid date': '无效的日期',
            'Must digit': '必须是数字',
            'Must alpha': '必须是字母',
            'Must digit or alnum or underline': '必须是字母、数字、下划线',
            'Length must in [0] - [1]': '长度必须在[0]-[1]之间',
            'Length must be [0]': '长度必须是[0]',
            'Number must in [0] - [1]': '数值必须在[0]-[1]之间',
            'Twice input must match': '两次输入必须一致'
        }
    });
});
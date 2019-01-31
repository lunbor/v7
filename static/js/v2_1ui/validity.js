/**
 * Form Script
 * 表单验证插件
 * data-validity="v2ui"
 * 
 * data-valid="date"
 */
v.Validity || (function () {
    v.Validity = v.extend(v.UI, function (self) {
        // 静态属性
        self.anchor = 'data-validity';

        // 数据效验
        self.validity = {
            // 不允许为空
            required: function (val) {
                return val.length > 0 || 'Not empty';
            },
            // 正则表达式检测
            regex: function (val, regx) {
                return eval(regx).test(val) || 'Invalid input';
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
                var l = val.length;
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
        this.tribox = null;  // 消息显示区域

        // 事件定义
        this._events = {
            failure: [], // 失败事件
            success: []  // 成功事件
        };

        // 设置效验消息
        var _setCustomValidity = function (message) {
            this.validationMessage = message;
        };

        // 检查效验结果
        var _checkValidity = function () {
            return v.isNull(this.validationMessage);
        };

        // 效验element效验结果
        var _checkValid = function (el) {
            // 先效验html5属性
            var valid = el.checkValidity();
            if (valid) {
                var msg = true, val = $(el).val(), fs = el.getAttribute('data-valid').replace(/\s+;\s+/g, ';').replace(/;\s*$/, '').split(';');
                // 判断空
                if (fs[0] === '*') {
                    msg = self.validity.required(val);
                    fs.shift();
                }
                // 数据不为空判断其他检查
                if (msg === true && !v.isNull(val)) {
                    for (var i = 0, len = fs.length; i < len; i++) {
                        msg = v.callfn(fs[i], val, self.validity);
                        if (msg !== true)
                            break;
                    }
                }
                // 设置验证消息
                valid = msg === true;
                valid ? el.setCustomValidity('') : el.setCustomValidity((el.getAttribute('data-label') || '') + v.trans(msg, 'v2uivalidity'));
            }
            return valid;
        };

        // 显示效验消息
        var _showValid = function (el) {
            var msgel;
            if (el.name && (msgel = v.$('[for="' + el.name + '"]', this.tribox))) {
                msgel.innerHTML = el.validationMessage;  // 单个提示消息
            } else if (this.anchor.name && (msgel = v.$('[for="' + this.anchor.name + '"]', this.tribox))) {
                // 在表单的总提示消息中显示
                msgel.innerHTML = el.validationMessage;  // 总提示消息
            }
        };

        // 效验element
        var _validElement = function (el) {
            var rs;
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
            var validEvent = function (event) {
                var timer, el = event.currentTarget;
                timer = v.callst(function () {
                    el.setCustomValidity('');  // 先去除自定义效验消息
                    _validElement.call(this, el);
                }.bind(this), timer, 100);
            }.bind(this);
            $(this.anchor).on('change ' + ('oninput' in this.anchor ? 'input' : 'keyup'), '[data-valid]', validEvent);
        };

        // 添加非html5支持
        var _initValid = function () {
            v.forEach(v.$s('[data-valid]', this.anchor), function (el) {
                if (!('checkValidity' in el)) {
                    // 添加效验消息获取函数
                    el.setCustomValidity = _setCustomValidity.bind(el);
                    el.validationMessage = '';
                    el.checkValidity = _checkValidity.bind(el);
                }
            });
        };

        // 共有方法
        // 
        // 初始化
        this.construct = function () {
            this.parent();
            _initValid.call(this);
            _initEvent.call(this);
        };

        // 重新初始化
        this.reInit = function () {
            _initValid.call(this);
            _initEvent.call(this);
        };

        // 效验，需所有字段通过效验
        this.checkValid = function (/*el*/) {
            var rs = true;
            if (arguments.length > 0) {
                // 效验单个对象
                rs = _validElement.call(this, v.$(arguments[0], this.anchor));
            } else {
                // 效验所有表单
                v.forEach(v.$s('[data-valid]', this.anchor), function (el) {
                    rs = rs && _validElement.call(this, el);
                }.bind(this));
            }
            return rs;
        };

        // 设置效验消息
        this.setValid = function (msg/*, el*/) {
            // 触发效验失败事件
            var el = arguments.length > 1 ? v.$(arguments[1], this.anchor) : null;
            this.triEvent('failure', {
                'target': el,
                'message': msg
            });
            if (el) {
                // 字段提示消息
                el.setCustomValidity(msg);
                _showValid.call(this, el);
            } else {
                // 总提示消息
                var msgel;
                if (this.anchor.name && (msgel = v.$('[for="' + this.anchor.name + '"]', this.tribox))) {
                    msgel.innerHTML = msg;
                }
            }
        };

        // 语言
        v.trans({
            'zh-cn': {
                'Not empty': '不能为空',
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
        }, 'v2uivalidity');

    });
    $(function () {
        v.Validity.init();
    });
})();
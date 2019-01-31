/**
 * Validity Script
 * 表单验证插件
 * v-validity="classNode:p"
 * 
 * data-valid="date"
 * data-label="用户名"
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
        length: function (val/*, min, max*/) {
            var l = val.length, min = arguments[1], max = arguments[2];
            return l >= min && (v.isNull(max) || l <= max) || (min != max ? 'Length must in [' + min + '] - [' + max + ']' : 'Length must be [' + min + ']');
        },
        // 大小区间
        between: function (val/*, min, max*/) {
            var min = arguments[1], max = arguments[2];
            val = Number(val);
            return (v.isNull(min) || val >= min) && (v.isNull(max) || val <= max) || 'Number must in [' + min + '] - [' + max + ']';
        },
        // 等于另一个域值
        equal: function (val, field) {
            return v.domVal(v.$(field)) == val || 'Twice input must match';
        },
        // 密码强度，小写字母|大写字母|数字|其他字符，共4级强度，默认2级强度
        strength: function (val/*, level*/) {
            var s = 0, level = Math.min(parseInt(arguments[1] || 2), 4);
            /[a-z]/.test(val) && s++;
            /[A-Z]/.test(val) && s++;
            /[0-9]/.test(val) && s++;
            /[^a-zA-Z0-9]/.test(val.replace(/[\s\uFEFF\xA0]+/g, '')) && s++;  // 空格不计算在内
            return level <= s || 'Strength is low, must be any [' + level + '] combination of numbers, capitals, lowercase letters and other characters';
        }
    };

    // 翻译效验语言
    self.trans({
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
            'Twice input must match': '两次输入必须一致',
            'Strength is low, must be any [0] combination of numbers, capitals, lowercase letters and other characters': '太过简单，必须由大写字符、小写字母、数字与其他字符中任意[0]类组成'
        }
    });

    // 公共属性
    //
    this.anchor = null; // tab选择区

    // 效验结果设置class
    this.failureClass = 'failure'; // 验证错误class
    this.successClass = 'success'; // 验证成功class
    this.classNode = null; // 定义class的节点

    // 事件定义
    this._events = {
        failure: [], // 失败事件
        success: []  // 成功事件
    };

    this._validFns = {};  // 自定义效验函数

    /**
     * H5设置效验消息
     * @param {String} message 效验消息
     */
    var _setCustomValidity = function (message) {
        this.validationMessage = message;
    };

    /**
     * H5检查效验结果
     * @returns {Boolean}
     */
    var _checkValidity = function () {
        return v.isNull(this.validationMessage);
    };

    /**
     * 给元素添加h5 validity效验支持，兼容低版本浏览器
     * @param {Element} el 要添加处理的元素
     * @returns {Element}
     */
    var _h5Validity = function (el) {
        if (!('checkValidity' in el)) {
            // 添加效验消息获取函数
            el.setCustomValidity = _setCustomValidity.bind(el);
            el.validationMessage = '';
            el.checkValidity = _checkValidity.bind(el);
        }
        return el;
    };

    /**
     * 效验element
     * @param {Element} el DOM节点
     * @returns {Boolean}
     */
    var _checkElement = function (el) {
        // 先效验html5属性
        var valid = _h5Validity(el).checkValidity();
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
                    var fns = this.fnArgs(fs[i], val), fnName = fns[0];
                    var fn = this._validFns[fnName] || self.validity[fnName] || window[fnName];
                    msg = fn.apply(null, fns[1]);
                    if (msg !== true)
                        break;
                }
            }
            // 设置验证消息
            valid = msg === true;
            valid ? el.setCustomValidity('') : el.setCustomValidity((el.getAttribute('data-label') || '') + this.trans(msg));
        }
        return valid;
    };

    /**
     * 显示效验消息
     * @param {Element} el
     */
    var _showValid = function (el) {
        var box = this.classNode ? v.$p(el, this.classNode, this.anchor) : null;
        var msgEl = (el.name ? v.$('[for="' + el.name + '"]', box || this.anchor) : null) // 显示在单个消息处
                || (this.anchor.name ? v.$('[for="' + this.anchor.name + '"]', this.anchor) : null);  // 显示在全局消息处
        if (msgEl) {
            msgEl.innerHTML = el.validationMessage;
            // 样式节点为输入框父类
            var clsEl = $(box || el);
            el.validationMessage ? clsEl.removeClass(this.successClass).addClass(this.failureClass)
                    : clsEl.removeClass(this.failureClass).addClass(this.successClass);
        }
    };

    /**
     * 效验element
     * @param {Element} el
     * @returns {Boolean}
     */
    var _validElement = function (el) {
        var rs = _checkElement.call(this, el);
        rs ? this.triEvent('success', {// 效验通过，触发成功事件
            'target': el
        }) : this.triEvent('failure', {// 效验未通过，触发失败事件
            'target': el,
            'message': el.validationMessage
        });
        // for=el.name显示错误消息
        _showValid.call(this, el);
        return rs;
    };

    /**
     * 初始化效验事件
     */
    var _initEvent = function () {
        // 效验函数
        var valid = function (el) {
            _h5Validity(el).setCustomValidity('');  // 先去除自定义效验消息
            _validElement.call(this, el);
        }.bind(this);
        // 事件处理
        $(this.anchor).on('change ' + ('oninput' in this.anchor ? 'input' : 'keyup'), '[data-valid]', function (event) {
            v.callst(valid, event.currentTarget, 100);
        });
    };

    /**
     * 添加非html5支持
     */
    var _initValid = function () {
        v.forEach(v.$s('[data-valid]', this.anchor), function (el) {
            _h5Validity(el);
        });
    };


    /**
     * 初始化
     */
    this.construct = function () {
        this.parent();
        _initValid.call(this);
        _initEvent.call(this);
    };

    /**
     * 设置效验函数
     * @param {String|Object} name 效验规则名称或集合
     * @param {Function} fun 效验规则函数
     */
    this.rule = function (name/*, fun*/) {
        var fns = v.isString(name) ? v.objVal({}, name, arguments[1]) : name;
        v.mixin(this._validFns, fns);
        return this;
    };

    /**
     * 效验，需所有字段通过效验，可效验某个节点或区域
     * @param {Element} el DOM具体节点或者区域节点
     * @returns {Boolean}
     */
    this.check = function (/*el*/) {
        var ctx, rs = true, args = v.toArray(arguments);
        if (args.length > 0) {
            ctx = v.$(args[0], this.anchor);
            if (!ctx) {
                return true;
            } else if (ctx.getAttribute('data-valid')) {
                // 效验单个对象
                return _validElement.call(this, ctx);
            }
        }
        // 效验所有表单
        ctx = ctx || this.anchor;
        v.forEach(v.$s('[data-valid]', ctx), function (el) {
            rs = rs && _validElement.call(this, el);
        }.bind(this));
        return rs;
    };

    /**
     * 设置效验提示消息
     * @param {String} msg
     * @param {Element} el 有该参数则设置某个节点的效验消息，否则设置全局效验消息
     */
    this.prompt = function (msg/*, el*/) {
        // 触发效验失败事件
        var el = arguments.length > 1 ? v.$(arguments[1], this.anchor) : null;
        this.triEvent('failure', {
            'target': el,
            'message': msg
        });
        if (el) {
            // 字段提示消息
            _h5Validity(el).setCustomValidity(msg);
            _showValid.call(this, el);
        } else {
            // 总提示消息
            var msgel, name = this.anchor.getAttribute('name');
            if (name && (msgel = v.$('[for="' + name + '"]', this.anchor))) {
                msgel.innerHTML = msg;
            }
        }
    };

    /**
     * 清除所有效验错误
     */
    this.clean = function () {
        v.forEach(v.$s('[data-valid]', this.anchor), function (el) {
            _h5Validity(el).setCustomValidity('');  // 去除自定义效验消息
            // 清除总提示消息
            var msgel, name = this.anchor.getAttribute('name');
            if (name && (msgel = v.$('[for="' + name + '"]', this.anchor))) {
                msgel.innerHTML = '';
            }
            this.triEvent('success', {
                'target': el
            });
            _showValid.call(this, el);
        }.bind(this));
    };

});
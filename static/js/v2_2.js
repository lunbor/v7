/**
 * v-js框架是一个微型JavaScript框架.
 * http://www.daojon.com
 * Copyright 2017, YongWang
 * Released under the MIT, BSD, and GPL Licenses.
 * daojon@live.com
 * author: daojon
 * version: 2017-5-25 v2.2.0
 * **********************************
 */
window.v || (function () {

    /**
     * -------------------------------------------------------------------------
     * 框架私有变量
     */
    var _win = window, // window
            _doc = document, // document
            _jsp = {}, // 动态JS加载队列
            _currentUrl, // 当前只想JS路径
            _lang, // 全局语言对象
            _rq = /(?:^([#\.]?)([\w-]*?))(?:\[([\w-]+)(?:=['"]([^'"]+)['"])?\])?(?:\.([\w-]+))?$/;  // 选择器正则

    /**
     * ------------------------------------------------------------------------
     * Polyfill
     */

    //  Function bind
    if (!Function.prototype.bind) {
        Function.prototype.bind = function (oThis) {
            if (typeof this !== 'function')
                throw new TypeError('Function.prototype.bind - what is trying to be bound is not callable');

            var args = Array.prototype.slice.call(arguments, 1),
                    fToBind = this,
                    fNOP = function () {},
                    fBound = function () {
                        return fToBind.apply(this instanceof fNOP ? this : oThis || this, args.concat(Array.prototype.slice.call(arguments)));
                    };
            fNOP.prototype = this.prototype;
            fBound.prototype = new fNOP();
            return fBound;
        };
    }

    // Array isArray
    if (!Array.isArray) {
        Array.isArray = function (arg) {
            return Object.prototype.toString.call(arg) === '[object Array]';
        };
    }

    //  Array indexOf
    if (!Array.prototype.indexOf) {
        Array.prototype.indexOf = function (searchElement, fromIndex) {
            var n, o = Object(this), len = o.length >>> 0;
            if (len === 0)
                return -1;
            if ((n = +fromIndex || 0) >= len)
                return -1;
            for (var i = Math.max(n >= 0 ? n : len - Math.abs(n), 0); i < len; i++) {
                if (o[i] === searchElement)
                    return i;
            }
            return -1;
        };
    }

    //  String trim
    if (!String.prototype.trim) {
        String.prototype.trim = function () {
            return this.replace(/^[\s\uFEFF\xA0]+|[\s\uFEFF\xA0]+$/g, '');
        };
    }

    // Object.create
    if (typeof Object.create !== 'function') {
        Object.create = (function () {
            function Temp() {}
            var hasOwn = Object.prototype.hasOwnProperty;
            return function (o) {
                if (typeof o !== 'object')
                    throw TypeError('Object prototype may only be an Object or null');

                Temp.prototype = o;
                var obj = new Temp();
                Temp.prototype = null;

                if (arguments.length > 1) {
                    var props = Object(arguments[1]);
                    for (var k in props)
                        !hasOwn.call(props, k) || (obj[k] = props[k]);
                }
                return obj;
            };
        })();
    }

    // JSON
    if (!window.JSON) {
        window.JSON = {
            parse: function (str) {
                return eval('(' + str + ')');
            },
            stringify: (function () {
                var toString = Object.prototype.toString;
                var escMap = {'"': '\\"', '\\': '\\\\', '\b': '\\b', '\f': '\\f', '\n': '\\n', '\r': '\\r', '\t': '\\t'};
                var escFunc = function (m) {
                    return escMap[m] || '\\u' + (m.charCodeAt(0) + 0x10000).toString(16).substr(1);
                };
                var escRE = /[\\"\u0000-\u001F\u2028\u2029]/g;
                return function stringify(value) {
                    if (value == null) {
                        return 'null';
                    } else if (typeof value === 'number') {
                        return isFinite(value) ? value.toString() : 'null';
                    } else if (typeof value === 'boolean') {
                        return value.toString();
                    } else if (typeof value === 'object') {
                        if (typeof value.toJSON === 'function') {
                            return stringify(value.toJSON());
                        } else if (Array.isArray(value)) {
                            var res = '[';
                            for (var i = 0; i < value.length; i++)
                                res += (i ? ', ' : '') + stringify(value[i]);
                            return res + ']';
                        } else if (toString.call(value) === '[object Object]') {
                            var tmp = [];
                            for (var k in value) {
                                if (value.hasOwnProperty(k))
                                    tmp.push(stringify(k) + ': ' + stringify(value[k]));
                            }
                            return '{' + tmp.join(', ') + '}';
                        }
                    }
                    return '"' + value.toString().replace(escRE, escFunc) + '"';
                };
            })()
        };
    }

    // querySelector
    if (!_doc.querySelectorAll) {
        var _$fix = function (s/*, el*/) {
            var el = arguments[1] || _doc, prefix = '';
            if (el !== _doc)
                prefix = (el.id ? '#' + el.id : '[data-v-id="' + v.domVid(el) + '"]') + ' ';
            return  prefix + s;
        };
        // 选择class
        var _$cls = function (s, ctx) {
            var i = 0, node, ret = [], nodes = ctx.nodeName ? ctx.getElementsByTagName('*') : ctx;
            while (node = nodes[i++])
                (' ' + node.className + ' ').indexOf(' ' + s + ' ') < 0 || ret.push(node);
            return ret;
        };
        // 正则匹配选择
        var _$regx = function (s, ctx) {
            if (s === 'body')
                els = [_doc.body];
            if (s === '*')
                els = ctx.getElementsByTagName('*');
            else {
                var q = _rq.exec(s), els = [];
                if (q[1] === '#')
                    els = [_doc.getElementById(q[2])];  // id
                else if (q[1] === '.')
                    els = _$cls(q[2], ctx);  // class
                else if (q[2])
                    els = ctx.getElementsByTagName(q[2]);  // tag
                if (q[3])
                    if (!q[2] && q[3] === 'name' && ctx === _doc)
                        els = ctx.getElementsByName(q[4]); // attribute
                    else { // 属性辅助
                        var i = 0, node, attr, ret = [];
                        q[2] || (els = ctx.getElementsByTagName('*'));
                        while (node = els[i++]) {
                            if (attr = node.attributes) {
                                attr = attr[q[3]];
                                if (attr != null && (!q[4] || attr.value === q[4]))
                                    ret.push(node);
                            }
                        }
                        els = ret;
                    }
                if (q[5])
                    els = _$cls(q[5], els);
            }
            // 转换成数组
            for (var nodes = els, len = els.length, els = [], i = 0; i < len; i++)
                els[i] = nodes[i];
            return els;
        };

        // 选择元素
        var _$query = function (s, ctx) {
            ctx = ctx || _doc;
            // ,号多选解析
            var els = [], s1s = s.split(', '), els1 = [], ctx1, ctxs, i, j, ss, len;
            for (var i1 = 0, len1 = s1s.length; i1 < len1; i1++) {
                // 子选择解析
                ss = s1s[i1].split(' ');
                len = ss.length;
                ctxs = [ctx];
                for (i = 0; i < len; i++)
                    if (ss[i]) {
                        j = 0;
                        els1 = [];
                        while (ctx1 = ctxs[j++])
                            els1 = els1.concat(_$regx(ss[i], ctx1));
                        ctxs = els1;
                    }
                els = els.concat(els1);
            }
            return els;
        };
    }

    /**
     * ------------------------------------------------------------------------
     * 框架对象基类
     */
    function V() {}
    V.prototype = {
        construct: function () {}
    };

    /**
     * ------------------------------------------------------------------------
     * 框架
     */
    function v() {
        this.author = 'daojon';
        this.version = '2.2';

        this.DEEP = 1;
        this.COVER = 2;
        this.DEEPCOVER = 3;  // v.DEEP | v.COVER;
        this.BREAK = 'BREAK11';
        this.OK = 'STATE_OK_11';
        this.ERROR = 'STATE_ERROR_11';
    }
    v.prototype = {
        constructor: v,

        /**
         * 类型判断
         * @param {Object} o 对象
         * @param {String} type 预期类型
         * @returns {Boolean}
         */
        isType: function (o, type) {
            return Object.prototype.toString.apply(o) === '[' + (typeof o) + ' ' + type + ']';
        },

        /**
         * 是否boolean类型
         * @param {Object} o 对象
         * @returns {Boolean}
         */
        isBoolean: function (o) {
            return typeof o === 'boolean';
        },

        /**
         * 是否字符串
         * @param {Object} o 对象
         * @returns {Boolean}
         */
        isString: function (o) {
            return typeof o === 'string';
        },

        /**
         * 是否数字
         * @param {Object} o 对象
         * @returns {Boolean}
         */
        isNumber: function (o) {
            return typeof o === 'number';
        },

        /**
         * 是否数组
         * 注意，是数组肯定会是对象object
         * @param {Object} o 对象
         * @returns {Boolean}
         */
        isArray: function (o) {
            return Array.isArray(o);
        },

        /**
         * 是否函数
         * @param {Object} o 对象
         * @returns {Boolean}
         */
        isFunction: function (o) {
            return typeof o === 'function' && ('prototype' in o);
        },

        /**
         * 是否对象，不包括null、string、number、boolean、function等基础类型
         * @param {Object} o 对象
         * @returns {Boolean}
         */
        isObject: function (o) {
            return !v.isNull(o) && (typeof o === 'object');
        },

        /**
         * 是否类
         * @param {Object} o 对象
         * @returns {Boolean}
         */
        isClass: function (o) {
            return v.isFunction(o) && !v.isEmpty(o.prototype);
        },

        /**
         * 是否为空
         * 不包含0\false
         * @param {mixed} val
         * @returns {Boolean}
         */
        isNull: function (val) {
            return !val && val !== 0 && val !== false;
        },

        /**
         * 是否为空
         * 空对象也会判断为空，包含0\false
         * @param {mixed} val
         * @returns {Boolean}
         */
        isEmpty: function (val) {
            if (v.isObject(val)) {
                for (var i in val)
                    if (val.hasOwnProperty(i))
                        return false;
                return true;
            }
            return !val;
        },

        /**
         * 对象转数组
         * @param {Object} o 对象
         * @returns {Array}
         */
        toArray: function (o) {
            try {
                return Array.prototype.slice.call(o);
            } catch (e) {
                var arr = [];
                v.forEach(o, function (val, i) {
                    arr[i] = val;
                });
                return arr;
            }
        },

        /**
         * 函数this上下文bind，同一个上下文只会产生一个
         * @param {Function} fn 函数
         * @param {Object} ctx this上下文
         * @returns {Function}
         */
        bind: function (fn, ctx) {
            var vid = v.vid(ctx), k = '_vbindfn' + vid;
            fn[k] || (fn[k] = fn.bind(ctx));
            return fn[k];
        },

        /**
         * 对象复制 src对象复制到dst对象
         * 注意dst会被改变
         * @param {Object} dst
         * @param {Object} src 多个
         * @param {Integer} deep v.DEEP深度拷贝 | v.COVER覆盖拷贝 | v.DEEPCOVER深度覆盖拷贝 
         * @return {Object}
         */
        mixin: function (dst, src1 /*,src2, src..., deep*/) {
            var args = arguments, len = args.length, dc = 0, src;
            // 判断标志位
            if (len > 2) {
                dc = args[len - 1];
                v.isNumber(dc) ? len-- : dc = 0;
            }
            for (var x = 1; x < len; x++) {
                src = args[x];
                v.forEach(src, function (val, k) {
                    if ((dc == 1 || dc == 3) && v.isObject(val) && (v.isArray(val) || !val.constructor || val.constructor.toString().substr(0, 16).indexOf('Object') > 0)) {
                        if (!v.isObject(dst[k]))
                            dst[k] = v.isArray(val) ? [] : {};
                        v.mixin(dst[k], val, dc);
                    } else if (v.isNull(dst[k]) || dc >= 2) {
                        dst[k] = val;
                    }
                });
            }
            return dst;
        },

        /**
         * 对象深度克隆
         * @param {Object|Array} o
         * @returns {Object|Array}
         */
        clone: function (o) {
            return v.isObject(o) ? v.mixin(v.isArray(o) ? [] : {}, o, v.DEEP) : o;
        },

        /**
         * 类定义
         * @param {Object} parent 父类
         * @param {Function} defined 类定义函数
         * @returns {Class}
         */
        classd: function (/*, parent, */defined) {
            var args = arguments, parent = V, prop = {}, prot = {};
            if (args.length > 1)
                defined.parent ? (parent = args[0]) && (defined = args[1]) : parent = args[1];

            function F() {
                parent.apply(this);
                v.mixin(this, v.clone(prop), v.DEEPCOVER);
                if (this.constructor === F) {
                    // 如果是当前类，执行初始化方法，并删除
                    this.construct.apply(this, arguments);
                    delete this.construct;
                }
            }
            F.parent = parent;
            v.mixin(F, parent, v.DEEP);  // 静态方法继承，必须放在new之前，方便函数中调用
            defined = new defined(F);
            defined.construct || (defined.construct = function () {
                this.parent();
            });
            v.forEach(defined, function (p, k) {
                typeof p === 'function' ? prot[k] = v.mixin(p, {_class: F, _name: k}) : prop[k] = p;
            });
            F.prototype = v.mixin(Object.create(parent.prototype), prot, v.COVER);
            F.prototype.constructor = F;

            // 调用父类同名方法
            F.prototype.parent = function () {
                var caller = arguments.callee.caller;
                return caller._class.parent.prototype[caller._name].apply(this, arguments.length ? arguments : caller.arguments);
            };
            return F;
        },

        /**
         * 继承与扩展
         * @param {Object|Class} dst 为Class时候继承，为对象时候拷贝
         * @returns {Object|Class}
         */
        extend: function (dst/*, src1, src2, src..., deep*/) {
            var args = v.toArray(arguments);
            return v.isClass(dst) && v.isFunction(args[1]) ? v.classd.apply(null, args) : v.mixin.apply(null, args);
        },

        /**
         * 生成12位ID
         * @param {String} prefix 前缀
         * @returns {String}
         */
        uid12: function (/*prefix*/) {
            var uid = Math.floor(Math.random() * 26 + 10).toString(36) + new Date().getTime().toString(36),
                    dif = 12 - uid.length, ustr = Math.floor(Math.random() * Math.pow(36, dif)).toString(36);
            return (arguments[0] || '') + uid + ('000' + ustr).substr(ustr.length + 3 - dif);
        },

        /**
         * 重复字符串
         * @param {String} str 字符串
         * @param {Integer} len 重复次数
         * @returns {String}
         */
        strRepeat: function (str, len) {
            return Array.prototype.join.call({length: len + 1}, str);
        },

        /**
         * 字符串前补齐到多少位
         * @param {String} str 字符串
         * @param {Integer} len 位数
         * @param {String} char 要补齐的字符
         * @returns {String}
         */
        strPadStart: function (str, len, char) {
            str = v.strRepeat(char, len) + str;
            return str.substr(str.length - len);
        },

        /**
         * 解析模板表达式
         * <% for (var i = 0; i < arr.length; i++) { %>
         *      <li><%=arr[i]%></li>
         * <% } %>
         * @param {String} str 模板
         * @param {Object} data 数据
         * @returns {String}
         */
        strParse: function (str, data) {
            var vars = '', code = 'var _buf = "' + str.replace(/"/gm, '\\"').replace(/\r+/gm, '')
                    .replace(/\$\{(\w+)\}/gi, '" + ($1) + "')  // ${var}
                    .replace(/<%=(.*?)%>/gi, '" + ($1) + "')  // <%=var%>
                    .replace(/<%(\s[\s\S]*?\s)%>/gi, '";\r' + '$1'.replace(/\n+/gm, '\r') + '\r _buf = _buf + "')  //<%js代码%>
                    .replace(/\n+/gm, '\\n').replace(/\r+/gm, '\n') + '"; return _buf;';
            for (var i in data)
                if (data.hasOwnProperty(i) && i.indexOf(']') < 0)
                    vars = vars + 'var ' + i + ' = data.' + i + ';\n';
            return (new Function('data', vars + code))(data);
        },

        /**
         * 取得字符串hash值
         * @param {String} str 字符串
         * @returns {Number}
         */
        strHash: function (str) {
            var hash = 0, len = str.length, i;
            for (i = 0; i < len; i++)
                hash = (hash = 31 * hash + str.charCodeAt(i)) & hash;
            return hash;
        },

        /**
         * 字符串转Element
         * 支持同级多节点
         * @param {String} str 字符串
         * @returns {Element}
         */
        str2Dom: function (str) {
            var tag = str.match(/^\s*<(tbody|tr|td|col|colgroup|thead|tfoot)/i), div = _doc.createElement('div');
            div.innerHTML = (tag ? '<table>' + str + '</table>' : str);
            var cs = (tag ? div.getElementsByTagName(tag[1])[0].parentNode : div);
            var num = cs.childNodes.length;
            if (num <= 1)
                return cs.childNodes[0];
            // 多个元素返回碎片文档
            var frg = _doc.createDocumentFragment();
            for (; num--; )
                frg.appendChild(cs.firstChild);
            return frg;
        },

        /**
         * Element属性转Json Object
         * @param {String} attr
         * @returns {Array|Object}
         */
        attr2Json: function (attr) {
            var jstr = '', quote = false, len = attr.length, char, key = '', str = '';

            // 末尾加入结束符
            attr = attr.trim().split('');  // IE7必须先转换成数组处理
            if (attr[len - 1] !== ';') {
                attr.push(';');
                len += 1;
            }

            // 字符循环判断
            for (var i = 0; i < len; i++) {
                char = attr[i];
                switch (char) {
                    case '"':  // 双引号
                    case "'":  // 单引号
                        quote = !quote;
                        break;
                    default:
                        if (quote) {  // 双引号内正常处理
                            str += char;
                        } else {
                            switch (char) {
                                case ';':  // 分号
                                case ',':  // 逗号
                                    char = ',';
                                case '}':  // 后括号
                                    if (!v.isNull(key) && !v.isNull(str)) {
                                        // 字符串特殊处理
                                        switch (str) {
                                            case 'true':
                                            case 'false':
                                            case 'null':
                                            case 'undefined':
                                                break;
                                            default:
                                                str = /^[0-9]*$/i.test(str) ? str : '"' + str + '"';
                                        }
                                        jstr += '"' + key + '":' + str;
                                        key = str = '';
                                    }
                                    jstr += char;
                                    break;
                                case '{':  // 前括号
                                    jstr += '"' + key + '":{';
                                    key = str = '';
                                    break;
                                case ':':  // 冒号
                                    key = str.trim();
                                    str = '';
                                    break;
                                default:
                                    str += char;
                            }
                        }
                }
            }
            jstr = '{' + jstr.substr(0, jstr.length - 1) + '}';
            return JSON.parse(jstr);
        },

        /**
         * 遍历对象或者数组
         * @param {Object|Array} o 要遍历的数组或者对象
         * @param {Function} fn 处理函数，第一个参数为值，第二个参数为KEY
         * @returns {v}
         */
        forEach: function (o, fn) {
            var k, d, l = o.length;
            if (!v.isFunction(o) && v.isNumber(l)) {
                for (k = 0; k < l; k++) {
                    if (fn(o[k], k, o) === v.BREAK)
                        break;
                    if (d = l - o.length) {  // 数组splice处理
                        l -= d;
                        k -= d;
                    }
                }
            } else {
                for (k in o)
                    if (o.hasOwnProperty(k) && (fn(o[k], k, o) === v.BREAK))
                        break;
            }
            return v;
        },

        /**
         * 过滤对象或数组的null值
         * @param {Object|Array} o
         * @returns {Object|Array}
         */
        filterNull: function (o) {
            v.forEach(o, function (val, key) {
                v.isNull(val) && (v.isArray(o) ? o.splice(key, 1) : delete o[key])
            });
            return o;
        },

        /**
         * 从第一个对象中过滤掉和第二个对象中相等的值，深度过滤
         * @param {Object} o 元对象
         * @param {Object} wo 要过滤的值对象
         * @returns {Object}
         */
        filterSame: function (o, wo) {
            v.forEach(o, function (val, key) {
                if (key in wo) {
                    if (v.isObject(val)) {
                        v.isObject(wo[key]) && v.filterSame(val, wo[key]);
                        v.isEmpty(val) && delete o[key]; // 如果子元素全部相等则删除该元素
                    } else if (!v.isNumber(key)) {  // 数组不进行比较
                        wo[key] != val || delete o[key];
                    }
                }
            });
            return v.filterNull(o);
        },

        /**
         * 从第一个对象中过滤掉第二个对象或数组中的key
         * @param {Object} o 原对象
         * @param {Object|Array} wo 要过滤KEY的对象
         * @returns {Object}
         */
        filterKey: function (o, wo) {
            v.isArray(wo) ? v.forEach(wo, function (val) {
                delete o[val];
            }) : v.isObject(wo) && v.forEach(wo, function (val, k) {
                delete o[k];
            });
            return o;
        },

        /**
         * 取对象标识ID
         * @param {Object} o 对象
         * @returns {String}
         */
        vid: function (o) {
            o['_vid'] || (o['_vid'] = v.uid12());
            return o['_vid'];
        },

        /**
         * 取DOM节点的vid
         * @param {Element} el 节点对象
         * @returns {String}
         */
        domVid: function (el) {
            var vid = el.getAttribute('data-v-id');
            vid || el.setAttribute('data-v-id', (vid = v.uid12()));
            return vid;
        },

        /**
         * DOM表单对象赋值与取值
         * @param {Element} el 节点对象
         * @param {String|Number|Boolean} 要赋予的值
         * @returns {String|Element} 取值返回String，赋值返回原Element
         */
        domVal: function (el/*, val*/) {
            var val = arguments[1];
            // 取值
            if (arguments.length === 1) {
                if (el.nodeType === 3) {
                    val = el.textContent;
                } else if ('checkbox|radio'.indexOf(el.type) >= 0) {
                    val = el.checked ? el.value : '';
                } else
                    switch (el.tagName) {
                        case 'INPUT':
                        case 'SELECT':
                        case 'TEXTAREA':
                            val = el.value;
                            break;
                        case 'IMG':
                            val = el.src;
                            break;
                        case 'A':
                            val = el.href;
                            break;
                        default:
                            val = el.innerHTML;
                    }
                if (v.isNull(val))
                    val = '';
                return val.indexOf('{{') > -1 ? '' : val;  // 值不能为表达式
            }
            // 赋值
            v.isObject(val) && (val = JSON.stringify(val));
            if (el.nodeType === 3) {
                el.textContent = val;
            } else if (el.type && 'checkbox|radio'.indexOf(el.type) >= 0) {
                el.checked = (',' + val + ',').indexOf(',' + el.value + ',') > -1;
            } else
                switch (el.tagName) {
                    case 'INPUT':
                    case 'SELECT':
                    case 'TEXTAREA':
                        if (el.value !== val)
                            el.value = val;
                        break;
                    case 'IMG':
                        el.src = val;
                        break;
                    case 'A':
                        el.href = val;
                        break;
                    default:
                        el.innerHTML = val;
                }
            return el;
        },

        /**
         * 给DOM区域的表单元素赋值或者取值
         * 节点的name作为标识
         * @param {Element} el DOM节点对象
         * @param {Object} data 赋值对象
         * @param {Boolean} renew 是否更新，true只对data中的元素进行更新，false不再data中的元素值会被清空
         * @returns {Object|Element} 取值返回对象，赋值返回原Element
         */
        domJson: function (el/*, data, renew*/) {
            var els = v.$s('*', v.$(el)), data = arguments[1] || {}, i = 0, name, value, renew = arguments[2];
            if (arguments.length === 1) {
                // 取值
                while (el = els[i++])
                    if ((name = el.name) && ('hidden|radio|checkbox|text|password|textarea|select-one').indexOf(el.type) > -1) {
                        value = v.domVal(el);
                        if (el.type !== 'radio' || !v.isNull(value))  // radio特殊处理
                            v.objVal(data, name, value);
                    }
                return data;
            }
            // 赋值
            while (el = els[i++])
                if (name = el.getAttribute('name')) {
                    value = v.objVal(data, name);
                    value !== undefined ? v.domVal(el, String(value)) : !renew || v.domVal(el, '');
                }
            return el;
        },

        /**
         * 给DOM节点设置属性
         * @param {Element} el DOM节点
         * @param {Object} attr 要设置的属性
         * @returns {Element}
         */
        domAttr: function (el, attr) {
            v.forEach(attr, function (val, k) {
                v.isNull(val) ? el.removeAttribute(k) : (v.isString(val) || v.isNumber(val) ? el.setAttribute(k, val) : el[k] = val);
            });
            return el;
        },

        /**
         * 取得|设置对象的值，支持数组或者.的方式
         * @param {Object} o
         * @param {String} name 属性名，例子user.name | user[name]
         * @param {mixed} val 要赋予的值
         * @returns {Object|mixed}
         */
        objVal: function (o, name/*, val*/) {
            var val = o, ns = name.replace(/\[/g, '.').replace(/\]/, '').split('.');
            // 取值
            if (arguments.length < 3) {
                v.forEach(ns, function (n) {
                    if (v.isNull(n))  // 没有key返回整个数组
                        return val;
                    if (!v.isObject(val) || !(n in val))
                        return undefined;
                    val = val[n];
                });
                return val;
            }
            // 赋值
            var k = ns.pop(), k1 = ns.pop(), val1 = arguments[2];
            v.forEach(ns, function (n) {
                val = n in val ? val[n] : (val[n] = {});
            });
            if (k1) {
                k1 in val || (val[k1] = v.isNull(k) ? [] : {});
                val = val[k1];
            }
            v.isArray(val) ? val.push(val1) : val[k] = val1;
            return o;
        },

        /**
         * 取得对象第一个数据
         * @param {Object|Array} o 对象
         * @returns {mixed}
         */
        firstVal: function (o) {
            var val = '';
            v.forEach(o, function (value) {
                val = value;
                return v.BREAK;
            });
            return val;
        },

        /**
         * 多维对象格式化成一维对象
         * 用于formdata向服务器传递数据
         * @param {Object} o 对象
         * @param {String} p 前缀
         * @returns {Object}
         */
        flatJson: function (o/*, p*/) {
            var data = {}, p = arguments.length > 1 ? arguments[1] : null;
            v.forEach(o, function (val, k) {
                var k1 = !v.isNull(p) ? p + '[' + k + ']' : k;
                v.isObject(val) ? v.extend(data, v.flatJson(val, k1)) : data[k1] = val;
            });
            return data;
        },

        /**
         * 取得URL中参数的值
         * @param {String} key 参数名
         * @param {String} url url地址
         * @returns {String}
         */
        qrsUrl: function (key/*, url*/) {
            var url = arguments[1] || _win.location.href, qrs = url.match(new RegExp('[\\?#&]' + key + '=([^&#]*)'));
            return qrs ? decodeURIComponent(qrs[1]) : '';
        },

        /**
         * 生成url
         * @param {Object} data 要生成到url的数据
         * @param {String} url url地址
         * @returns {String}
         */
        urlQrs: function (data/*, url*/) {
            var qrs = [], url = arguments[1] ? arguments[1].replace(/\{([\w_]+)\}/g, function (val, p) {
                p in data ? (val = data[p]) && delete data[p] : val = '';
                return val;
            }) : '';
            v.forEach(v.flatJson(data), function (val, k) {
                qrs.push(k + '=' + encodeURIComponent(val));
            });
            return !url ? qrs.join('&') :
                    url + (url.indexOf('?') > -1 || url.indexOf('#') > -1 ? '&' : '?') + qrs.join('&').replace(/#&/g, '#').replace(/^[\?&]|[\?&]$/g, '');
        },

        /**
         * 延迟覆盖执行
         * @param {Function} fn
         * @param {Array} args 函数的参数值
         * @param {Integer} msec 延迟毫秒
         * @returns {Timer}
         */
        callst: function (fn/*, args, msec*/) {
            var args = arguments[1], msec = arguments[2] || 13;
            v.isNumber(args) && (msec = args) && (args = null);  // 无参数处理
            v.isNull(args) || !v.isArray(args) && (args = [args]);

            fn._vtimer && clearTimeout(fn._vtimer);
            return fn._vtimer = v.isNull(args) ? setTimeout(fn, msec) : setTimeout(function () {
                fn.apply(null, args);
            }, msec);
        },

        /**
         * 取得一个Promise
         * @param {Function} fn 工作函数
         * @returns {v.Promise}
         */
        when: function (fn) {
            return new v.Promise(fn);
        },

        /**
         * 翻译
         * @param {String|Object} str 对象时候加载翻译语言 | 字符串或数组时翻译数据
         * @returns {unresolved}
         */
        trans: function (str) {
            // 设置翻译文本 ?: 翻译数据
            return v.isObject(str) && !v.isArray(str) ? _lang.text(str) : _lang.trans(str);
        },

        /**
         * 生成url的绝对路径，根据当前页面或者script的路径计算
         * @param {String} url
         * @returns {String}
         */
        furl: function (url) {
            if (url.substr(0, 1) !== '/' && url.indexOf('://') < 0) {   // 补齐相对路径
                var src = _currentUrl || _doc.URL, base = src.substr(0, src.lastIndexOf('/'));
                while (url.indexOf('../') > -1) {
                    url = url.replace('../', '');
                    base = base.substr(0, base.lastIndexOf('/'));
                }
                url = base + '/' + url;
            }
            return url;
        },

        /**
         * 动态载入JS，载入完成后回调函数
         * @param {String|Array} url 单个或者多个url
         * @param {Function} 回调函数
         * @returns {v.Promise}
         */
        load: function (url/*, fn*/) {
            var qu, len, fn = arguments[1];
            if (v.isArray(url) && (len = url.length) > 1) {  // 数组
                // 计算路径
                for (var i = 0; i < len; i++)
                    url[i] = v.furl(url[i]);
                // 载入url
                for (var i = 0; i < len; i++)
                    url[i] = v.load(url[i]);
                qu = v.Promise.all(url);
            } else {
                v.isArray(url) && (url = url[0]);
                url = v.furl(url);
                if (url.substr(url.length - 4) === '.css') {  // 载入css
                    if (!_doc.head.querySelector('link[href="' + url + '"]')) {
                        _doc.head.appendChild(v.domAttr(v.$('<link>'), {'rel': 'stylesheet', 'href': url}));
                    }
                    return v;
                }
                if (!(qu = _jsp[url])) {  // 保存延迟加载对象
                    qu = _jsp[url] = new v.Promise(function (resolve) {
                        var el = v.domAttr(v.$('<script>'), {'type': 'text/javascript', 'async': 'async', 'src': url});
                        el.attachEvent ? el.onreadystatechange = function () {
                            ('complete|loaded'.indexOf(el.readyState) < 0) || ((el.onreadystatechange = null) || resolve());
                        } : el.onload = resolve;
                        _doc.body.appendChild(el);
                        _currentUrl = url;
                    });
                }
            }
            // 载入完毕后恢复_currentUrl
            return qu.then(function () {
                (_currentUrl = null) || !fn || fn();
            });
        },

        /**
         * 自定义类，允许需要时加载JS arguments1 ['parent class name', 'js file1', 'jsfile2']
         * 返回的是一个Promise，需要在when函数里面取到类定义结果
         * @param {Array|Class} parent 依赖的父类与JS
         * @param {Function} fn 类定义
         * @returns {v.Promise}
         */
        define: function (/*parent, */fn) {
            var parent, args = v.toArray(arguments);
            if (args.length > 1) {
                parent = args[0];
                fn = args[1];
                if (v.isArray(parent)) {
                    var fs = parent;
                    fs[0].indexOf('.js') < 0 && (parent = fs[0]) && fs.splice(0, 1);

                    // 需要载入依赖JS
                    if (fs.length > 0)
                        return new v.Promise(function (resolve) {
                            v.load(fs, function () {
                                parent = eval(parent);
                                resolve(v.classd(parent, fn));
                            });
                        });
                }
                return v.Promise.resolve(v.classd(parent, fn));
            }
            // 不需要载入依赖JS
            return v.Promise.resolve(v.classd(fn));
        },

        /**
         * 框架工具定义
         * @param {Array|Class} parent 依赖的父类与JS
         * @param {Function} fn 类定义
         * @param {Object} ns 命名空间
         * @returns {v.Promise}
         */
        kit: function (name, /*parent, */fn/*, ns*/) {
            var args = v.toArray(arguments),
                    ns = v.isObject(args[args.length - 1]) ? args.pop() : v;
            if (args.length <= 2) {
                args[0] = v.Kit;
            } else {
                args.shift();
                v.isArray(args[0]) && args[0][0].indexOf('.js') > 0 && args[0].splice(0, 0, v.Kit);
            }
            return v.define.apply(null, args).then(function (obj) {
                ns[name] = obj;
                v.ready(obj.init.bind(obj));
            });
        },

        /**
         * 单选择器
         * @param {String} s 选择器
         * @param {Element} ctx 上下文节点
         * @returns {Element}
         */
        $: function (s/*, ctx*/) {
            if (!v.isString(s))
                return s;
            else if (s === 'doc')
                return _doc.documentElement;
            else if (s.charAt(0) === '<')
                return s.lastIndexOf('<') > 0 ? v.str2Dom(s) : _doc.createElement(s.replace(/<|>/g, ''));

            var ctx = arguments[1] || _doc;
            return ctx.querySelector ? ctx.querySelector(s) : _$query(s, ctx)[0];
        },

        /**
         * 多选择器
         * @param {String} s 选择器
         * @param {Element} ctx 上下文节点
         * @returns {Array}
         */
        $s: function (s/*, ctx*/) {
            if (!v.isString(s))
                return [s];
            var ctx = arguments[1] || _doc;
            return v.toArray(ctx.querySelectorAll ? ctx.querySelectorAll(s) : _$query(s, ctx));
        },

        /**
         * 向上冒泡选择匹配的父级节点，最多32层
         * @param {String} s 选择器
         * @param {Element} el DOM节点
         * @param {Element|Integer} ctx 上下文节点|允许选择的层数
         * @returns {Element}
         */
        $p: function (s, el/*, ctx*/) {
            var args = v.toArray(arguments), ctx = _doc.body, num = 32, q = _rq.exec(s);
            if (args[2])
                v.isNumber(args[2]) ? num = args[2] : ctx = args[2];
            while (el && el.nodeName && (el !== ctx) && (num-- > 0)) {
                if (q[1] === '#') { // id
                    if (q[2] === el.id)
                        break;
                } else if (q[1] === '.') { // class
                    if ((' ' + el.className + ' ').indexOf(' ' + q[2] + ' ') > -1)
                        break;
                } else if (q[3]) { // attr
                    if (el.hasAttribute(q[3]) && (!q[4] || el.getAttribute(q[3], 2) === q[4]))
                        break;
                } else if (!q[1] && q[2] && (el.nodeName === q[2].toUpperCase())) {  // tag name
                    break;
                }
                el = el.parentNode;
            }
            return el === ctx ? null : el;
        },

        /**
         * 页面准备好
         */
        ready: (function () {
            var fns = [], ready = false, fn,
                    doFn = function () {
                        ready = true;
                        while (fn = fns.shift())
                            fn();
                    };
            if (_doc.addEventListener) {  // 现代浏览器
                'interactive|complete'.indexOf(_doc.readyState) > -1 ? doFn() : _doc.addEventListener('DOMContentLoaded', function onReady1() {
                    _doc.removeEventListener('DOMContentLoaded', onReady1);
                    doFn();
                }, false);
            } else {  // <=IE9
                _doc.attachEvent('onreadystatechange', function onReady2() {
                    if ('loaded|complete|interactive'.indexOf(_doc.readyState) > -1) {
                        _doc.detachEvent('onreadystatechange', onReady2);
                        doFn();
                    }
                });
            }
            return function (fn) {
                ready ? fn() : fns.push(fn);
            };
        })()
    };
    var v = window.v = new v();

    /**
     * -------------------------------------------------------------------------
     * Promise   view  ES6  only single object
     */
    v.Promise = v.classd(function (self) {

        // ---------------------------------
        // 静态方法定义
        // ---------------------------------

        /**
         * 多个promise异步处理
         * @param {Array} items
         * @returns {v.Promise}
         */
        self.all = function (items) {
            var okNum = 0, allNum = items.length, vals = new Array(allNum);
            // 处理每个数据的结果，累加次数足够视为成功
            var rfn = function (value, index, resolve) {
                okNum += 1;
                vals[index] = value;
                if (okNum >= allNum)
                    resolve(vals);
            };
            // 返回新的promise对象
            return new self(function (resolve, reject) {
                v.forEach(items, function (item, i) {
                    if (!(item instanceof self)) {
                        rfn(item, i, resolve);
                    } else {
                        item.then(function (value) {
                            rfn(value, i, resolve);
                        }, function (reason) {
                            vals[i] = reason;
                            reject(vals);
                        });
                    }
                });
            });
        };

        /**
         * 立即成功
         * @param {mixed} value 结果值
         * @returns {v.Promise}
         */
        self.resolve = function (value) {
            return new self(function (resolve) {
                resolve(value);
            });
        };

        /**
         * 立即失败
         * @param {String} reason 失败原因
         * @returns {v.Promise}
         */
        self.reject = function (reason) {
            return new self(function (resolve, reject) {
                reject(reason);
            });
        };

        // ------------------------------------
        // 私有属性定义
        // ------------------------------------
        this._state = 0; // 0初始状态  1成功  -1失败
        this._value = null;  // 结果值
        this._resolveFns = []; // 成功后的then方法
        this._rejectFns = []; // 失败后的catch方法

        // -------------------------------------
        // 私有方法定义
        // -------------------------------------

        /**
         * 结果调用then方法
         */
        var _result = function () {
            if (!this._state)
                return;
            var fn, items = this._state === 1 ? this._resolveFns : this._rejectFns;
            while (fn = items.shift())
                fn(this._value);
        };

        /**
         * 异步结束回调
         * @param {mixed} value 结果值
         * @param {integer} state 完成状态
         */
        var _finish = function (value, state) {
            this._value = value;
            this._state = state;
            _result.call(this);
        };

        /**
         * 异步结束成功
         * @param {mixed} value 结果值
         */
        var _resolve = function (value) {
            _finish.call(this, value, 1);
        };

        /**
         * 异步结束失败
         * @param {String} reason 原因
         */
        var _reject = function (reason) {
            _finish.call(this, reason, -1);
        };

        // ----------------------------------------
        // 公共方法定义
        // ----------------------------------------

        /**
         * 初始化
         * @param {Function} fn 执行函数
         */
        this.construct = function (fn) {
            fn(_resolve.bind(this), _reject.bind(this));
        };

        /**
         * 结果回调
         * @param {Function} onResolve 成功回调
         * @param {Function} onReject 失败回调
         * @returns {v.Promise}
         */
        this.then = function (onResolve, onReject) {
            onResolve && this._resolveFns.push(onResolve);
            onReject && this._rejectFns.push(onReject);
            _result.call(this);
            return this;
        };

        /**
         * 失败回调
         * 不能用标准的cache避免低版本浏览器关键字冲突
         * @param {Function} onReject 失败回调
         * @returns {v.Promise}
         */
        this.fail = function (onReject) {
            return this.then(undefined, onReject);
        };

    });

    /**
     * -------------------------------------------------------------------------
     * 语言对象
     * 变量使用[var]来表示
     */
    v.Lang = v.classd(function (self) {

        // ---------------------------------
        // 静态方法属性定义
        // ---------------------------------

        // 语言 zh-cn, en-us
        self.lang = (navigator.browserLanguage || navigator.language).toLowerCase().replace('_', '-');


        // ---------------------------------
        // 属性与方法定义
        // ---------------------------------

        // 翻译文本
        this.langs = {};

        /**
         * 载入翻译语言
         * @param {object}
         * @returns {v.Lang}
         */
        this.text = function (ln) {
            v.forEach(ln[self.lang] || ln, function (val, k) {
                this.langs[k.toLowerCase().replace(/\s*/g, '')] = val;
            }.bind(this));
            return this;
        };

        /**
         * 翻译
         * @param {String|Object|Array} s 要翻译的文本
         */
        this.trans = function (s) {
            // 对象翻译
            if (v.isObject(s)) {
                s = v.clone(s);
                v.forEach(s, function (val, i) {
                    s[i] = this.trans(val);
                }.bind(this));
                return s;
            }
            // 字符翻译
            var langs = this.langs, str = s.toLowerCase().replace(/\s*/g, '');
            if (langs[str]) {
                s = langs[str];
            } else if (str.indexOf('[') && str.indexOf(']')) {
                var vals = [], i = 0;
                str = str.replace(/\[(.*?)\]/g, function (all, val) {
                    vals.push(val);
                    return '[' + (i++) + ']';
                });
                if (langs[str]) {
                    s = langs[str].replace(/\[(\d+)\]/g, function (all, val) {
                        return '[' + vals[parseInt(val)] + ']';
                    });
                }
            }
            return s;
        };
    });
    _lang = new v.Lang();

    /**
     * -------------------------------------------------------------------------
     * 框架组件基类，定义了事件处理方式
     */
    v.Kit = v.classd(function (self) {

        // -------------------------------
        // 类静态定义
        // -------------------------------

        self.anchor = 'v-kit'; // 锚点定义，每个子类必须定义自己锚点，请使用data-v开头，作为浏览器标准属性
        self.defaults = {}; // 默认配置
        self.lang = new v.Lang();  // 语言翻译对象

        /**
         * 初始化dom对象
         * @param {Element} ctx 上下文节点
         * @returns {v.Kit}
         */
        self.init = function (ctx) {
            v.forEach(v.$s('[' + this.anchor + ']', ctx || _doc), function (el) {
                this.obj(el);
            }.bind(this));
            return this;
        };

        /**
         * 取得dom绑定的对象
         * @param {Element} el dom节点
         * @returns {v.kit}
         */
        self.obj = function (el) {
            v.isString(el) && (el = v.$(el));
            el._vkits || (el._vkits = {});
            if (!el._vkits[this.anchor]) {
                // options参数，支持json与属性方式
                var options = (el.getAttribute(this.anchor) || '').trim();
                options = options ? (options.charAt(0) === '{' ? JSON.parse(options) : v.attr2Json(options)) : {};
                var events = options.events;
                delete options.events;
                options['anchor'] = el;
                // 建立对象，添加事件
                var obj = el._vkits[this.anchor] = new this(options);
                !events || v.forEach(events, function (fn, e) {
                    obj.addEvent(e, v.objVal(_win, fn.replace(/\(|\)/g, '')));
                });
            }
            return el._vkits[this.anchor];
        };

        /**
         * 翻译
         * @param {String|Array|Object} str
         * @returns {unresolved}
         */
        self.trans = function (str) {
            // 设置翻译文本 ?: 翻译数据
            return v.isObject(str) && !v.isArray(str) ? this.lang.text(str) : this.lang.trans(str);
        };


        // -----------------------------------
        // 类定义
        // -----------------------------------

        this.anchor = null;  // 控件作用对象

        // 对象事件，由子类定义，事件必须在此定义成数组
        this._events = {
            //'inited' : []  // 初始化事件
        };

        /**
         * 构造函数
         * @param {Object} options 属性配置
         */
        this.construct = function (options) {
            this.parent();
            v.mixin(this, v.mixin(options || {}, this.constructor.defaults), v.COVER);
        };

        /**
         * 解析函数与参数
         * fnArgs('length, 2, 6', value) 转成函数后为 [length, [value, 2, 6]]
         * @param {String} fn 函数名与函数参数
         * @param {mixed} val 要传给函数的第一个参数
         * @returns {Array} [函数, 参数]
         */
        this.fnArgs = function (fn/*, val*/) {
            var args = fn.replace(')', '').replace('(', ',').replace(/\s*,\s*/, ',').replace(/,$/, '').trim().split(',');
            fn = args[0];
            arguments.length > 1 ? args[0] = arguments[1] : args.splice(0, 1);
            return [fn, args];
        };

        /**
         * 添加自定义事件
         * @param {String} type 事件类型
         * @param {Function} fn 函数
         */
        this.addEvent = function (type, fn) {
            this._events[type] && this._events[type].push(fn);
        };

        /**
         * 删除自定义事件
         * @param {String} type 事件类型
         * @param {Function} fn 函数
         */
        this.rmvEvent = function (type, fn) {
            if (this._events[type]) {
                var pos = this._events[type].indexOf(fn);
                pos < 0 || this._events.splice(pos, 1);
            }
        };

        /**
         * 触发自定义事件
         * @param {String} type 事件类型
         */
        this.triEvent = function (type) {
            var args = Array.prototype.slice.call(arguments, 1);
            this._events[type] && v.forEach(this._events[type], function (e) {
                e.apply(this, args);
            }.bind(this));
        };

        /**
         * 翻译
         * @param {String|Array|Object} str
         * @returns {unresolved}
         */
        this.trans = function (str) {
            return self.trans(str);
        };

    });
})('v');
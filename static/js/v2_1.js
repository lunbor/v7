/**
 * v-js框架是一个微型JavaScript框架.
 * http://www.daojon.com
 * Copyright 2016, YongWang
 * Released under the MIT, BSD, and GPL Licenses.
 * daojon@live.com
 * author: daojon
 * version: 2016-12-27 v2.1.0
 * **********************************
 */
window.v || (function () {
    // 私有变量
    var _win = window, _doc = document, _jsp = {}, _jsp1 = {}, _olang, _rq = /(?:^([#\.]?)([\w-]*?))(?:\[([\w-]+)(?:=['"]([^'"]+)['"])?\])?(?:\.([\w-]+))?$/;
    // Polyfill
    // 
    //  function bind
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
    //  array indexOf
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
        _doc.querySelectorAll = function (selector) {
            return _$query(selector);
        };
        _doc.querySelector = function (selector) {
            return _$query(selector)[0];
        };
    }

    /**
     * 框架对象基类
     * 事件系统，需要先定义事件
     */
    function _V() {
        // 对象事件，由子类定义
        this._events = {
            //'inited' : []  // 初始化事件
        };
    }
    _V.prototype = {
        construct: function () {
        },
        addEvent: function (type, fn) {
            !this._events[type] || this._events[type].push(fn);
        },
        rmvEvent: function (type, fn) {
            if (this._events[type]) {
                var pos = pos = this._events[type].indexOf(fn);
                pos < 0 || this._events.splice(pos, 1);
            }
        },
        triEvent: function (type) {
            var args = Array.prototype.slice.call(arguments, 1);
            !this._events[type] || v.forEach(this._events[type], function (e) {
                e.apply(this, args);
            }.bind(this));
        }
    };

    /**
     * 框架
     */
    function v() {
        this.author = 'daojon';
        this.version = '2.1';

        this.DEEP = 1;
        this.COVER = 2;
        this.DEEPCOVER = 3;  // v.DEEP | v.COVER;
        this.BREAK = 'BREAK11';
        this.OK = 'STATE_OK_11';
        this.ERROR = 'STATE_ERROR_11';
    }
    v.prototype = {
        constructor: v,
        // 类型判断
        isType: function (o, type) {
            return Object.prototype.toString.apply(o) === '[' + (typeof o) + ' ' + type + ']';
        },
        isBoolean: function (o) {
            return typeof o === 'boolean';
        },
        isString: function (o) {
            return typeof o === 'string';
        },
        isNumber: function (o) {
            return typeof o === 'number';
        },
        isArray: function (o) {
            return Array.isArray ? Array.isArray(o) : v.isType(o, 'Array');
        },
        isFunction: function (o) {
            return typeof o === 'function' && ('prototype' in o);
        },
        isObject: function (o) {
            return !v.isNull(o) && (typeof o === 'object');
        },
        isClass: function (o) {
            return v.isFunction(o) && o.prototype.constructor;
        },
        // 值为空判断，排除零与false
        isNull: function (val) {
            return (!val && val !== 0 && val !== false) || val === 'NaN' || val === 'undefined' ? true : false;
        },
        // 对象为空判断
        isEmpty: function (val) {
            if (v.isObject(val)) {
                for (var i in val)
                    if (val.hasOwnProperty(i))
                        return false;
                return true;
            }
            return false;
        },
        // 对象转数组
        toArray: function (o) {
            var a = o.length ? Array.prototype.slice.call(o) : [];
            a.length || v.forEach(o, function (i) {
                a.push(i);
            });
            return a;
        },
        // 上下文bind，同一个上下文只会产生一个
        bind: function (fn, ctx) {
            var vid = v.vid(ctx), k = '_vbindfn' + vid;
            fn[k] || (fn[k] = fn.bind(ctx));
            return fn[k];
        },
        /**
         * 对象复制 src对象复制到dst对象
         * 如果dst已有该对象则不复制
         * @param {Object} dst
         * @param {Object} src 多个
         * @param {Boolean} deep 是否深度复制
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
        // 克隆数据
        clone: function (o) {
            return v.isObject(o) ? v.mixin(v.isArray(o) ? [] : {}, o, v.DEEP) : o;
        },
        /**
         * 类定义
         * @param {object} 父类
         * @param {function} 类定义函数
         * @returns {object}
         */
        classd: function (/*, parent, */defined) {
            var args = arguments, parent = _V, prop = {}, prot = {};
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
            defined = new defined(F);
            defined.construct || (defined.construct = function () {
                this.parent();
            });
            v.forEach(defined, function (p, k) {
                typeof p === 'function' ? prot[k] = v.mixin(p, {_class: F, _name: k}) : prop[k] = p;
            });
            F.prototype = v.mixin(Object.create(parent.prototype), prot, v.COVER);
            F.prototype.constructor = F;
            v.mixin(F, parent, v.DEEP);  // 静态方法

            function pcall() {
                var caller = pcall.caller;
                return caller._class.parent.prototype[caller._name].apply(this, arguments.length ? arguments : caller.arguments);
            }
            F.prototype.parent = pcall;
            return F;
        },
        // 继承与扩展
        extend: function (dst/*, src1, src2, src..., deep*/) {
            var args = arguments;
            return v.isFunction(args[0]) && (!args[1] || v.isFunction(args[1])) ? v.classd.apply(this, args) : v.mixin.apply(this, args);
        },
        // 异步模式触发条件
        when: function (/*arr, */fn) {
            var args = arguments;
            return new v.Promise(args[0], args[1]);
        },
        // 动态载入JS
        load: function (url/*, fn*/) {
            var qu, fn = arguments[1], src = v.currentScript().replace(/\?.*?$/, '');
            if (v.isArray(url)) {  // 数组
                qu = v.when(url, function (url, i, fn) {
                    v.load(url, fn);
                });
            } else {
                if (url.substr(0, 1) !== '/' && url.substr(0, 4) !== 'http')  // 补齐相当路径
                    url = src.substr(0, src.lastIndexOf('/') + 1) + url;

                if (!(qu = _jsp[url])) {  // 保存延迟加载对象
                    qu = _jsp[url] = v.when(function (fn) {
                        var rfn = function () {
                            // 延迟执行，确保加载执行成功
                            setTimeout(function () {
                                _jsp1[url] ? _jsp1[url].then(fn) : fn();
                            }, 0);
                        };
                        var el = _doc.createElement('script');
                        el.src = url + '?t=' + v.uid12();
                        el.async = 'async';
                        el.attachEvent ? el.onreadystatechange = function () {
                            if ('complete|loaded'.indexOf(el.readyState) > -1) {
                                el.onreadystatechange = null;
                                rfn();
                            }
                        } : el.onload = rfn;
                        v.$('head').appendChild(el);
                    });
                }
            }
            !fn || qu.then(fn);
            !src || (_jsp1[src] = qu);  // 记录当前JS的最后一个异步加载队列，确保JS执行完成
            return qu;
        },
        //取得正在解析的script节点
        currentScript: function () {
            if (_doc.currentScript) //firefox 4+
                return _doc.currentScript.src || _doc.URL;

            // !IE || >=10
            try {
                a.b.c(); //强制报错,以便捕获e.stack
            } catch (e) { //safari的错误对象只有line,sourceId,sourceURL
                if (e.sourceURL && !e.stack) {  // 注意不能同时加载多个JS
                    var s = v.$('head').getElementsByTagName('script');
                    return s[s.length - 1].src;
                }
                var stack = e.stack;
                if (!stack && _win.opera)
                    stack = (String(e).match(/of linked script \S+/g) || []).join(' ');
                if (stack) {
                    if (stack.indexOf(_doc.URL.substr(_doc.URL.lastIndexOf('/') + 1)) > 0) // 如果出错页面中有当前页面的地址则为当前页面
                        return _doc.URL;

                    if (stack.indexOf('at Global') > -1)  // ie10 fix last line has at Global
                        stack = stack.split('at Global').pop().split(/\)/g).shift() + ')';

                    stack = stack.split(/[@ ]/g).pop(); //取得最后一行,最后一个空格或@之后的部分
                    stack = stack[0] === '(' ? stack.slice(1, -1) : stack;
                    return stack.replace(/(:\d+)?:\d+$/i, ''); //去掉行号与或许存在的出错字符起始位置
                }
            }
            // IE < 10，需要修复在缓存状态下的问题
            var nodes = v.$('head').getElementsByTagName('script');
            for (var i = 0, node; node = nodes[i++]; ) {
                if (node.readyState === 'interactive')
                    return node.getAttribute('src', 4);
            }
            return _doc.URL;
        },
        // 生成12位ID
        uid12: function (/*prefix*/) {
            var uid = Math.floor(Math.random() * 26 + 10).toString(36) + new Date().getTime().toString(36),
                    dif = 12 - uid.length, ustr = Math.floor(Math.random() * Math.pow(36, dif)).toString(36);
            return (arguments[0] || '') + uid + ('000' + ustr).substr(ustr.length + 3 - dif);
        },
        // 重复字符串
        strRepeat: function (str, len) {
            return Array.prototype.join.call({length: len + 1}, str);
        },
        // 字符串前补齐
        strPadStart: function (str, len, char) {
            str = char.v.strRepeat(len) + str;
            return str.substr(str.length - len);
        },
        // 解析模板表达式
        strParse: function (str, data) {
            var vars = '', code = 'var _buf = "' + str.replace(/"/gm, '\\"').replace(/\r+/gm, '').replace(/\{\{(\w+)\}\}/gi, function (all, stmt) {
                // {{}} 变量
                return '" + (' + stmt + ') + "';
            }).replace(/<!--=(.*?)-->/gi, function (all, stmt) {
                // <!--=变量
                return '" + (' + stmt + ') + "';
            }).replace(/<!--js(\s[\s\S]*?\s)-->/gi, function (all, stmt) {
                // <!--js 表达式
                return '";\r' + stmt.replace(/\n+/gm, '\r') + '\r _buf = _buf + "';
            }).replace(/\n+/gm, '\\n').replace(/\r+/gm, '\n') + '"; return _buf;';

            for (var i in data)
                if (data.hasOwnProperty(i) && i.indexOf(']') < 0)
                    vars = vars + 'var ' + i + ' = data.' + i + ';\n';
            return (new Function('data', vars + code))(data);
        },
        // 取得字符串hash值
        strHash: function (str) {
            var hash = 0, len = str.length, i;
            for (i = 0; i < len; i++)
                hash = (hash = 31 * hash + str.charCodeAt(i)) & hash;
            return hash;
        },
        // 转dom对象,index = 1返回第一个元素, will do
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
        str2Json: function (str) {
            return typeof JSON !== 'undefined' ? JSON.parse(str) : eval('(' + str + ')');
        },
        // 属性字符串转JSON
        attr2Json: function (str) {
            if (str)
                str = ('{"' + str.replace(/^\s+/, '').replace(/;?\s*$/, '')
                        .replace(/;?\s*}/g, '}').replace(/{\s+/g, '{')
                        .replace(/\s*;\s*/g, ',').replace(/\s*:\s*/g, '":"').replace(/,/g, '","').replace(/":"\/\//g, '://') + '"}')
                        .replace(/"{/g, '{"').replace(/}"/g, '"}')
                        .replace(/"(false|true)"/g, "$1").replace(/"([\d\.]+)"/g, "$1");
            return str ? v.str2Json(str) : {};
        },
        // 遍历对象与数组
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
        // 过滤null值
        filterNull: function (o) {
            v.forEach(o, function (val, key) {
                !v.isNull(val) || (v.isArray(o) ? o.splice(key, 1) : delete o[key])
            });
            return o;
        },
        // 从第一个对象中过滤掉和第二个对象中相等的值，深度过滤
        filterSame: function (o, wo) {
            v.forEach(o, function (val, key) {
                if (key in wo) {
                    if (v.isObject(val)) {
                        !v.isObject(wo[key]) || v.filterSame(val, wo[key]);
                        !v.isEmpty(val) || delete o[key]; // 如果子元素全部相等则删除该元素
                    } else if (!v.isNumber(key)) {  // 数组不进行比较
                        wo[key] != val || delete o[key];
                    }
                }
            });
            // 过滤掉undefined
            v.forEach(o, function (val, key) {
                val !== undefined || (v.isArray(o) ? o.splice(key, 1) : delete o[key])
            });
            return o;
        },
        // 去dom元素的vid
        domVid: function (el) {
            var vid = el.getAttribute('data-v-id');
            vid || el.setAttribute('data-v-id', (vid = v.uid12()));
            return vid;
        },
        // 取对象标识ID
        vid: function (o) {
            o['_vid'] || (o['_vid'] = v.uid12());
            return o['_vid'];
        },
        // 给dom赋值取值
        domVal: function (el/*, val*/) {
            var args = arguments, val = args[1];
            // 取值
            if (args.length === 1) {
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
                        default:
                            val = el.innerHTML;
                    }
                if (v.isNull(val))
                    val = '';
                return val.indexOf('{{') > -1 ? '' : val;  // 值不能为表达式
            }
            // 赋值
            !v.isObject(val) || (val = JSON.stringify(val));
            if (el.nodeType === 3) {
                el.textContent = val;
            } else if (el.type && 'checkbox|radio'.indexOf(el.type) >= 0) {
                el.checked = el.value == val;
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
                    default:
                        el.innerHTML = val;
                }
            return v;
        },
        // 取得json第一个数据
        firstVal: function (o) {
            var val = '';
            v.forEach(o, function (value) {
                val = value;
                return v.BREAK;
            });
            return val;
        },
        // 取得|设置对象的值，支持数据或者.的方式
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
        // 批量给dom元素赋值取值
        domJson: function (el/*, data, renew*/) {
            var els = v.$s('*', el), data = arguments[1] || {}, i = 0, name, value;
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
                    value !== undefined ? v.domVal(el, String(value)) : !arguments[2] || v.domVal(el, '');
                }
            return v;
        },
        // json格式扁平化
        flatJson: function (o/*, p*/) {
            var data = {}, p = arguments.length > 1 ? arguments[1] : null;
            v.forEach(o, function (val, k) {
                var k1 = !v.isNull(p) ? p + '[' + k + ']' : k;
                v.isObject(val) ? v.extend(data, v.flatJson(val, k1)) : data[k1] = val;
            });
            return data;
        },
        // 取得URL中变量的值
        qrsUrl: function (key/*, url*/) {
            var url = arguments[1] || _win.location.href, qrs = url.match(new RegExp('[\\?#&]' + key + '=([^&#]*)'));
            return qrs ? decodeURIComponent(qrs[1]) : '';
        },
        // 生成url查询字串
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
        // 异步，延迟执行一次
        callst: function (fn, timer/*, args, msec*/) {
            !timer || clearTimeout(timer);
            var args = arguments, msec = 13, fas;
            if (args.length > 2) {
                if (!v.isNumber(args[2])) {
                    // 带参数的函数
                    fas = args[2];
                    return setTimeout(function () {
                        fn.apply(this, fas);
                    }, args[3] || msec);
                }
                msec = args[2];
            }
            // 不带参数的函数
            return setTimeout(fn, msec);
        },
        // 在给定的对象中调用方法
        callfn: function (fs, args, ctx) {
            v.isString(fs) ? fs = fs.replace(/\s+/g, '').replace('(', ',').replace(')', '').split(',') : '';
            if (!v.isArray(args))
                args = [args];
            Array.prototype.push.apply(args, fs.slice(1));
            // 解析参数
            v.forEach(args, function (arg, i) {
                if (v.isString(arg) && arg.charAt(0) === '$')  // 参数$开头，做变量处理
                    args[i] = v.objVal(_win, arg.substr(1));
            });
            // 解析函数，如果带命名空间则ctx使用命名空间
            var fn = v.isString(fs[0]) ? v.objVal(ctx, fs[0]) || _win[fs[0]] : fs[0];
            return fn.apply(ctx, args);
        },
        // 判断节点包含关系
        contains: function (el, child) {
            if (el === child)
                return true;
            return el.contains ? el.contains(child) : el.compareDocumentPosition ? !!(el.compareDocumentPosition(child) & 16) : !!v.$(child).$p(el);
        },
        // 停止事件冒泡
        stopEvent: function (event) {
            if (event.preventDefault) {
                event.preventDefault();
                event.stopPropagation();
            } else {
                event.returnValue = false;
                event.cancelBubble = true;
            }
        },
        // 翻译
        trans: function (str) {
            // 设置翻译语言
            if (v.isObject(str) && !v.isArray(str)) {
                _olang.langue(str);
                return v;
            }
            // 翻译数据
            return _olang.trans(str);
        },
        // 单选择器
        $: function (s/*, ctx*/) {
            if (!v.isString(s))
                return s;
            if (s.charAt(0) === '<')
                return _doc.createElement(s.replace('<', '').replace('>', ''));
            var ctx = arguments[1] || _doc;
            return ctx.querySelector ? ctx.querySelector(s) : _$query(s, ctx)[0];
        },
        // 多选择器
        $s: function (s/*, ctx*/) {
            if (!v.isString(s))
                return s;
            if (s.charAt(0) === '<')
                return [v.$(s)];
            var ctx = arguments[1] || _doc;
            return ctx.querySelectorAll ? ctx.querySelectorAll(s) : _$query(s, ctx);
        },
        /**
         * 向上冒泡选择
         */
        $p: function (s, el/*, ctx*/) {
            var args = arguments, ctx = _doc.body, num = 32, q = _rq.exec(s);
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
        }
    };
    var v = window.v = new v();

    /**
     * 延迟异步代码队列
     * use case
     * 
     var p1 = v.when([1, 2, 3, 4], function (val, k, fn) {
     fn();  // 回调，通知完成
     return val * 2;
     });
     var p2 = v.when(function (fn) {
     setTimeout(function () {
     console.info('p2');
     fn(40);  // 回调，通知完成
     }, 1000);
     });
     var p3 = v.when([p1, p2]).then(function (rs) {
     console.info('over');
     });
     */
    v.Promise = v.classd(function (self) {

        this._rs = [];  // 回掉结果
        this._num = 0;  // 状态数量，<=0 为结束状态
        this._err = '';  // 错误消息
        this._sfn = [];  // 成功回调函数
        this._efn = [];  // 失败回调函数
        this._cfn = [];  // 完成回掉函数

        // 回调结果
        var _rsf = function () {
            if (this._num <= 0) {
                var rs = this._rs.length <= 1 ? this._rs.pop() : this._rs,
                        fn, fns = this._err !== '' ? this._efn : this._sfn;
                while (fn = fns.shift())  // 失败或成功
                    fn(rs);

                while (fn = this._cfn.shift())  // 完成
                    fn(rs);
            }
        };

        // 每一项执行成功回调
        var _cbf = function (r, status) {
            this._num--;
            if (r !== undefined && r !== v.ERROR)
                this._rs.push(r);
            if (r === v.ERROR || status === v.ERROR)
                this._err = r || 'Promise carry out error';
            _rsf.call(this);
        };

        // 初始化
        this.construct = function (arr/*, fn*/) {
            this._vid = v.uid12();
            var fn = arguments[arguments.length - 1];
            if (v.isFunction(arr)) {
                fn = arr;
                arr = ['__isonlyfn'];
            }
            // 回调函数
            var cb = _cbf.bind(this);
            this._num = arr.length;
            v.forEach(arr, function (p, k) {
                if (fn) {
                    var rs = (p === '__isonlyfn' ? fn(cb) : fn(p, k, cb));
                    // 如果函数有返回，则不使用回调
                    rs === undefined || cb(rs);
                } else {
                    // promise队列不需要函数体
                    p.then(cb);
                }
            }.bind(this));
        };
        // 等待回调
        this.wait = function (fn) {
            if (this._num > 0)
                fn();
            return this;
        };
        // 成功回调
        this.then = function (fn) {
            this._sfn.push(fn);
            _rsf.call(this);
            return this;
        };
        // 失败回调
        this.fail = function (fn) {
            this._efn.push(fn);
            _rsf.call(this);
            return this;
        };
        // 完成回调
        this.final = function (fn) {
            this._cfn.push(fn);
            _rsf.call(this);
            return this;
        };
    });

    /**
     * 语言对象
     */
    _olang = new (v.classd(function () {
        this.langs = {};
        this.langue = function (ln/*, kid*/) {
            var lng = (navigator.browserLanguage || navigator.language).toLowerCase().replace('_', '-'), kid = arguments[1] || 'default';
            this.langs[kid] || (this.langs[kid] = {});
            v.forEach(ln[lng] || ln, function (val, k) {
                this.langs[kid][k.toLowerCase().replace(/\s*/g, '')] = val;
            }.bind(this));
        };
        this.trans = function (s/*, kid*/) {
            var kid = arguments[1] || 'default', langs = this.langs[kid] || {}, str = s.toLowerCase().replace(/\s*/g, '');
            if (langs[str]) {
                s = langs[str];
            } else if (str.indexOf('[') && str.indexOf(']')) {
                var vals = [], i = 0;
                str = str.replace(/\[(\w+)\]/g, function (all, val) {
                    vals.push(val);
                    return '[' + (i++) + ']';
                });
                if (langs[str]) {
                    s = langs[str].replace(/\[(\d+)\]/g, function (all, val) {
                        return vals[parseInt(val)];
                    });
                }
            }
            return s;
        };
    }))();

    // ui模型
    v.UI = v.classd(function (self) {
        //
        // 类静态定义
        //
        self.anchor = 'data-ui'; // 锚点定义，每个子类必须定义自己锚点
        self.defaults = {}; // 默认配置
        self._bind2s = {};  // 绑定数据
        // 初始化dom对象
        self.init = function (ctx) {
            var els = v.$s('[' + this.anchor + '="v2ui"]', v.$(ctx || _doc));
            !els || v.forEach(els, function (el) {
                this.obj(el);
            }.bind(this));
        };
        // 取得dom绑定的对象
        self.obj = function (el) {
            var vid = v.vid(el);
            if (!(vid in this._bind2s)) {
                // data-ui-options优先data-options，避免几个组建发生冲突
                var opstr = el.getAttribute(this.anchor + '-options') || el.getAttribute('data-options'), options = v.attr2Json(opstr), obj;
                options['anchor'] = el;
                this._bind2s[vid] = obj = new this(options);
                // 添加事件
                v.forEach(v.attr2Json(el.getAttribute('data-events')), function (fn, e) {
                    !v.isArray(obj._events[e]) || obj._events[e].push(v.objVal(_win, fn.replace(/\(|\)/g, '')));
                });
            }
            return this._bind2s[vid];
        };

        //
        // 类定义
        //
        this.anchor = null;  // 控件作用对象
        this.tribox = null;  // 控件触发box

        // 构造函数
        this.construct = function (options) {
            this.parent();
            v.mixin(this, v.mixin(options || {}, this.constructor.defaults), v.COVER);
            this.tribox = this.tribox ? v.$p(this.tribox, this.anchor) || this.anchor : this.anchor;
        };
    });
})('v');
/**
 * v-js框架是一个微型JavaScript框架.
 * http://www.daojon.com
 * Copyright 2016, YongWang
 * Released under the MIT, BSD, and GPL Licenses.
 * v6为基于es6的分支库
 * daojon@live.com
 * author: daojon
 * version: 2016-11-3 v6
 * **********************************
 * @will  callst
 */
window.v || (function (win, doc) {
    // 私有变量
    var _jsp = {}, _rs = [], _events = [], _enode, _efn = () => true;
    // ready listener
    doc.addEventListener('DOMContentLoaded', function () {
        _rs.forEach(fn => fn());
    }, false);

    /**
     * 框架对象基类
     * 事件系统，需要先定义事件
     */
    function V6() {}
    V6.prototype = {construct: () => {
        }};

    /**
     * 框架
     */
    function v() {
        this.author = 'daojon';
        this.version = '6.0';

        this.DEEP = 1;
        this.COVER = 2;
        this.DEEPCOVER = 3;  // v.DEEP | v.COVER;
        this.BREAK = 'BREAK11';
        this.ONNODE = 'onnode'; // 自定义节点变化事件，事件来源为新节点的区域
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
            return typeof o === 'function';
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
                for (let i in val)
                    if (val.hasOwnProperty(i))
                        return false;
                return true;
            }
            return false;
        },
        // 对象转数组
        toArray: function (o) {
            let a = [];
            o.length ? a = Array.from(o) : v.forEach(o, (i) => a.push(i));
            return a;
        },
        // 上下文bind，同一个上下文只会产生一个
        bind: function (fn, ctx) {
            let vid = v.vid(ctx), k = '_vbindfn' + vid;
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
        mixin: function (dst, ...args/*, deep*/) {
            let len = args.length, dc = 0, src;
            if (len > 1) {
                dc = args[len - 1];
                v.isNumber(dc) ? len-- : dc = 0;
            }
            for (let x = 0; x < len; x++) {
                src = args[x];
                v.forEach(src, (val, k) => {
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
        // 克隆数据，数组与对象深度复制
        clone: function (o) {
            return v.isObject(o) ? v.mixin(v.isArray(o) ? [] : {}, o, v.DEEP) : o;
        },
        /**
         * 类定义
         * @param {object} 父类
         * @param {function} 类定义函数
         * @returns {object}
         */
        classd: function (defined, parent = V6) {
            let prop = {}, prot = {};
            if (defined.parent)
                [defined, parent] = [parent, defined];

            function F() {
                parent.apply(this);
                v.mixin(this, v.clone(prop), v.DEEPCOVER);
                if (this.constructor === F) {
                    // 如果是当前类，执行初始化方法，并删除
                    this.construct(...arguments);
                    delete this.construct;
                }
            }
            F.parent = parent;
            v.mixin(F, parent, v.DEEP);  // 静态方法

            defined = new defined(F);
            defined.construct || (defined.construct = function () {
                this.parent();
            });
            v.forEach(defined, function (p, k) {
                typeof p === 'function' ? prot[k] = v.mixin(p, {_class: F, _name: k}) : prop[k] = p;
            });
            F.prototype = v.mixin(Object.create(parent.prototype), prot, v.COVER);
            F.prototype.constructor = F;

            function pcall() {
                let caller = pcall.caller;
                return caller._class.parent.prototype[caller._name].apply(this, arguments.length ? arguments : caller.arguments);
            }
            F.prototype.parent = pcall;
            return F;
        },
        // 继承与扩展
        extend: function (...args) {
            return v.isFunction(args[0]) && (!args[1] || v.isFunction(args[1])) ? v.classd.apply(this, args) : v.mixin.apply(this, args);
        },
        // 生成12位ID
        uid12: function (prefix = '') {
            let uid = Math.floor(Math.random() * 26 + 10).toString(36) + new Date().getTime().toString(36),
                    dif = 12 - uid.length, ustr = Math.floor(Math.random() * Math.pow(36, dif)).toString(36);
            return prefix + uid + ('000' + ustr).substr(ustr.length + 3 - dif);
        },
        // 取对象标识ID
        vid: function (obj) {
            obj['_vid'] || Object.defineProperty(obj, '_vid', {'value': v.uid12(), 'writable': false, 'enumerable': false});
            return obj['_vid'];
        },
        // 字符串前补齐
        strPadStart: function (str, len, char) {
            str = char.repeat(len) + str;
            return str.substr(str.length - len);
        },
        // 转驼峰样式
        str2Camel: function (str) {
            str = str.replace(/[-_](\w)?/gi, (all, str) => str.toUpperCase());
            return str.charAt(0).toLowerCase() + str.substr(1);  // 首字母小写
        },
        // 转帕斯卡样式
        str2Pascal: function (str) {
            str = str.replace(/[-_](\w)?/gi, (all, str) => str.toUpperCase());
            return str.charAt(0).toUpperCase() + str.substr(1);  // 首字母大写
        },
        // 转dom对象,index = 1返回第一个元素
        str2Dom: function (str) {
            let tag = str.match(/^\s*<(tbody|tr|td|col|colgroup|thead|tfoot)/i), div = doc.createElement('div');
            div.innerHTML = tag ? '<table>' + str + '</table>' : str;
            let cs = tag ? div.getElementsByTagName(tag[1])[0].parentNode : div;
            if (cs.childElementCount <= 1)
                return cs.firstElementChild;
            // 多个元素返回碎片文档
            let frg = doc.createDocumentFragment();
            for (let num = cs.childNodes.length; num--; )
                frg.appendChild(cs.firstChild);
            return frg;
        },
        // 字符串转数组，逗号分号隔开
        str2Array: function (str) {
            return str.replace(/;/g, ',').replace(/,\s+/, ',').split(',');
        },
        // 解析模板表达式
        strParse: function (str, data) {
            let vars = '', code = 'let _buf = "' + str.replace(/"/gm, '\\"').replace(/\r+/gm, '')
                    .replace(/<%=(.*?)%>/gi, '" + `${$1}` + "') // <%=变量
                    .replace(/<%(\s[\s\S]*?\s*)%>/gi, '";\r' + '$1'.replace(/\n+/gm, '\r') + '\r _buf = _buf + "') // <% 表达式
                    .replace(/\n+/gm, '\\n').replace(/\r+/gm, '\n') + '"; return _buf;';
            for (let i in data)
                if (data.hasOwnProperty(i) && i.indexOf(']') < 0)
                    vars = vars + 'let ' + i + ' = data.' + i + ';\n';
            return (new Function('data', vars + code))(data);
        },
        // 解析html，执行javascript,css
        strExec: function (str, fn) {
            let js = [], outerCss = [], innerCss = [], title, i, len;
            let text = str.replace(/<title>(.*?)<\/title>/gi, (all, code) => {  // 标题
                title = code;
                return '';
            }).replace(/<link[^>]*href=['"](.*?)['"][^>]*>\s*<\/link>/gi, (all, src) => {  // 外部样式
                outerCss.push(src);
                return '';
            }).replace(/<style[^>]*>([\s\S]*?)<\/style>/gi, function (all, code) {  // 内部样式
                innerCss.push(code);
                return '';
            }).replace(/<script(?:[^>]*src=['"](.*?)['"])?[^>]*>([\s\S]*?)<\/script>/gi, (all, src, code) => {  // js
                if (!(all || src))
                    return all;
                js.push([src || code, src ? 1 : 0]);  // 1外部，0内部
                return '';
            }).replace(/<!doctype[\s\S]*<body>/i, '').replace(/<html>[\s\S]*<body>/i, '').replace(/<\/body>[\s\S]*<\/html>/i, '');
            // css
            if (outerCss.length > 0)
                for (i = 0, len = outerCss.length; i < len; i++)
                    v.load(outerCss[i]);
            if (innerCss.length > 0)
                doc.createStyleSheet ? doc.createStyleSheet().cssText = innerCss.join('\n') :
                        v.$('<style>').attr({type : 'text/css'}).insertTo(v.$('head')).innerHTML = innerCss.join('\n');
            // 先执行自定义函数
            !fn || fn(text, title);
            // js，按顺序执行
            let execjs = () => {
                let item = js.shift();
                if (item) {
                    if (item[1] === 0) {  // 内部JS
                        v.$('<script>').attr({type: 'text/javascript'}).insertTo(v.$('body')).text = item[0];
                        execjs();
                    } else {  // 外部JS
                        v.load(item[0], execjs);
                    }
                }
            };
            execjs();
            // title
            !title || (doc.getElementsByTagName('title')[0].innerText = title);
            return text;
        },
        // 取得字符串hash值
        strHash: function (str) {
            let hash = 0, len = str.length, i;
            for (i = 0; i < len; i++)
                hash = (hash = 31 * hash + str.charCodeAt(i)) & hash;
            return hash;
        },
        // 时间转字符串
        time2str: function (val, fmt = 'Y/m/d H:i:s') {
            let date = new Date(parseInt(val) * 1000);
            return fmt.replace(/Y+/, date.getFullYear())
                    .replace(/m+/, v.strPadStart(date.getMonth() + 1, 2, '0'))
                    .replace(/d+/, v.strPadStart(date.getDate(), 2, '0'))
                    .replace(/H+/, v.strPadStart(date.getHours(), 2, '0'))
                    .replace(/i+/, v.strPadStart(date.getMinutes(), 2, '0'))
                    .replace(/s+/, v.strPadStart(date.getSeconds(), 2, '0'));
        },
        // 属性字符串转JSON
        attr2Json: function (attr) {
            let jstr = '', quote = false, len = attr.length, char, key = '', str = '';

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
                                            case 'fale':
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
        // 数组转换成对象
        array2Obj: function (arr, key) {
            let obj = {};
            v.forEach(arr, item => obj[item[key]] = item);
            return obj;
        },
        // 遍历对象与数组
        forEach: function (o, fn) {
            let k, l = o.length, dif;
            if (!v.isFunction(o) && v.isNumber(l)) {
                for (k = 0; k < l; k++) {
                    if (fn(o[k], k, o) === v.BREAK)
                        break;
                    if (dif = l - o.length) {  // 数组splice处理
                        l -= dif;
                        k -= dif;
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
            v.forEach(o, (val, key) => !v.isNull(val) || (v.isArray(o) ? o.splice(key, 1) : delete o[key]));
            return o;
        },
        // 从第一个对象中过滤掉和第二个对象中相等的值，深度过滤
        filterSame: function (o, wo) {
            v.forEach(o, (val, key) => {
                if (key in wo && !v.isArray(val)) {  // 数组不进行比较
                    if (v.isObject(val)) {
                        !v.isObject(wo[key]) || v.filterSame(val, wo[key]);
                        !v.isEmpty(val) || delete o[key]; // 如果子元素全部相等则删除该元素
                    } else {
                        // 0与空特殊判断
                        wo[key] != val || ((wo[key] === 0 || val === 0) && wo[key].toString() != val.toString()) || delete o[key];
                    }
                }
            });
            // 过滤掉undefined
            v.forEach(o, (val, key) => val !== undefined || (v.isArray(o) ? o.splice(key, 1) : delete o[key]));
            return o;
        },
        // 过滤掉对象中的指定key
        filterKey: function (o, wo) {
            v.isArray(wo) ? v.forEach(wo, val => delete o[val]) : !v.isObject(wo) || v.forEach(wo, (val, k) => delete o[k]);
            return o;
        },
        // value转换成string, undefined null NaN会转换成''
        strVal: function (val) {
            return v.isNull(val) ? '' : String(val);
        },
        // 取得|设置dom对象的value值
        domVal: function (el, val = null) {
            // 取值
            if (val === null) {
                if (el.nodeType === 3) {
                    val = el.textContent;
                } else if ('checkbox|radio'.indexOf(el.type) >= 0) {
                    val = el.checked === true ? el.value : '';
                } else
                    switch (el.tagName) {
                        case 'INPUT':
                        case 'SELECT':
                        case 'TEXTAREA':
                        case 'OPTION':
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
            !v.isObject(val) || (val = JSON.stringify(val));
            el = v.$(el);  // 使用node对象，可以监控数值变化
            if (el.nodeType === 3) {
                el.textContent = val;
            } else if (el.type && 'checkbox|radio'.indexOf(el.type) >= 0) {
                el.checked = (',' + val + ',').indexOf(',' + el.value + ',') > -1;
            } else {
                switch (el.tagName) {
                    case 'INPUT':
                    case 'SELECT':
                    case 'TEXTAREA':
                    case 'OPTION':
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
            }
            return v;
        },
        // 取得json第一个数据
        firstVal: function (o) {
            let val = '';
            v.forEach(o, (value) => {
                val = value;
                return v.BREAK;
            });
            return val;
        },
        // 取得|设置对象的值，支持数据或者.的方式
        objVal: function (o, name, value = undefined) {
            let val = o, ns = name.replace(/\[/g, '.').replace(/\]/, '').split('.');
            // 取值
            if (value === undefined) {
                for (let n of ns) {
                    if (v.isNull(n))  // 没有key返回整个数组
                        return val;
                    if (!v.isObject(val) || !(n in val))
                        return undefined;
                    val = val[n];
                }
                return val;
            }
            // 赋值
            let k = ns.pop(), k1 = ns.pop(), val1 = value;
            for (let n of ns)
                val = n in val ? val[n] : (val[n] = {});
            if (k1) {
                k1 in val || (val[k1] = v.isNull(k) ? [] : {});
                val = val[k1];
            }
            if (!v.isNull(val1) || !v.isNull(k))
                v.isArray(val) ? val.push(val1) : val[k] = val1;
            return o;
        },
        // 批量给dom元素赋值取值
        domJson: function (el, data = null, renew = false) {
            let els = v.$(el).$s('*'), i = 0, name, value;
            // 取值
            if (data === null || data === true) {
                let hasNull = data;
                data = {};
                while (el = els[i++])
                    if ((name = el.name) && ('hidden|radio|checkbox|text|password|textarea|select-one|color').indexOf(el.type) > -1) {
                        value = v.domVal(el);
                        if (!v.isNull(value) || !(name in data))
                            v.objVal(data, name, value);
                    }
                hasNull || v.filterNull(data);
                return data;
            }
            // 赋值
            while (el = els[i++])
                if (name = el.getAttribute('name')) {
                    value = v.objVal(data, name);
                    value !== undefined ? v.domVal(el, String(value)) : !renew || v.domVal(el, '');
                }
            return v;
        },
        // json格式扁平化
        flatJson: function (o, p = '') {
            let data = {};
            v.forEach(o, (val, k) => {
                let k1 = !v.isNull(p) ? p + '[' + k + ']' : k;
                v.isObject(val) && !v.isEmpty(val) ? v.extend(data, v.flatJson(val, k1)) : data[k1] = val;
            });
            return data;
        },
        // 取dom元素的vid
        domVid: function (el) {
            let vid = el.getAttribute('data-v-id');
            vid || el.setAttribute('data-v-id', (vid = v.uid12()));
            return vid;
        },
        // 异步，即刻队列调用
        callim: (function () {
            let queue = [], fn, sentinel = String(Math.random());
            if (!win.msSetImmediate) {
                win.addEventListener('message', (e) => {
                    if (e.data === sentinel) {
                        while (fn = queue.shift())
                            fn();
                    }
                });
            }
            return function (fun) {
                if (win.msSetImmediate) {
                    win.msSetImmediate(fun)
                } else {
                    queue.push(fun);
                    win.postMessage(sentinel, '*');
                }
            };
        })(),
        // 异步，延迟执行一次
        callst: function (fn, timer, args = null, msec = 13) {
            !timer || clearTimeout(timer);
            // 带参数的延迟
            if (args !== null) {
                if (!v.isNumber(args)) {
                    return setTimeout(() => {
                        fn(...args);
                    }, msec);
                }
                msec = args;
            }
            // 不带参数的函数
            return setTimeout(fn, msec);
        },
        // 文档加载完毕
        ready: function (fn) {
            'interactive|complete'.indexOf(doc.readyState) > -1 ? setTimeout(fn, 1) : _rs.push(fn);
            return v;
        },
        // 添加事件
        addEvent: function (type, fn) {
            type in _events ? _events[type].push(fn) :
                    _events[type] = [fn];
        },
        // 触发事件
        triEvent: function (type, ...args) {
            !_events[type] || v.forEach(_events[type], e => e(...args));
        },
        // 取得url参数值
        qrsUrl: function (key, url = win.location.href) {
            let qrs = url.match(new RegExp('[\\?#&]' + key + '=([^&#]*)'));
            return qrs ? decodeURIComponent(qrs[1]) : '';
        },
        // 生成url查询字串
        urlQrs: function (data, url = null) {
            let qrs = [];
            url = url ? url.replace(/\{([\w_]+)\}/g, (val, p) => {
                p in data ? (val = data[p]) && delete data[p] : val = '';
                return val;
            }) : '';
            v.forEach(v.flatJson(data), (val, k) => qrs.push(k + '=' + encodeURIComponent(val)));
            return !url ? qrs.join('&') :
                    url + (url.indexOf('?') > -1 || url.indexOf('#') > -1 ? '&' : '?') + qrs.join('&').replace(/#&/g, '#').replace(/^[\?&]|[\?&]$/g, '');
        },
        // 翻译
        trans: function (str) {
            // 设置翻译文本 ?: 翻译数据
            return v.isObject(str) && !v.isArray(str) ? v.lang.text(str) : v.lang.trans(str);
        },
        // AJAX请求，回掉与options不能共存
        xhr: function (mth, url, data = null, fn = null) {
            let opt = {};
            if (data !== null) {
                if (v.isFunction(data)) {
                    // data为回调
                    opt.success = data;
                } else if (data.success || data.complete || data.error) {
                    // data为options
                    opt = data;
                } else {
                    // data为要传的数据
                    v.isFunction(fn) ? opt.success = fn : opt = fn || {};
                    opt.data = data;
                }
            }
            v.mixin(opt, {method: mth, url: url}, v.COVER);
            return v.ajax.reset(opt).request();
        },

        // 生成全路径url
        furl: function (url) {
            if (url.substr(0, 1) !== '/' && url.indexOf('://') < 0) {   // 补齐相对路径
                let src = (doc.currentScript.src || doc.URL).replace(/\?.*?$/, ''),
                        base = src.substr(0, src.lastIndexOf('/'));
                while (url.indexOf('../') > -1) {
                    url = url.replace('../', '');
                    base = base.substr(0, base.lastIndexOf('/'));
                }
                url = base + '/' + url;
            }
            return url;
        },
        // 动态载入JS或CSS
        load: function (url, fn = null) {
            let qu;
            if (v.isArray(url) && url.length > 1) { // 数组
                qu = Promise.all(url.map(url => v.load(url)));
            } else {
                if (v.isArray(url))
                    url = url[0];
                url = v.furl(url);

                if (url.substr(url.length - 4) === '.css') {  // 载入css
                    if (!doc.head.querySelector('link[href="' + url + '"]'))
                        v.$('<link>').attr({'rel': 'stylesheet', 'href': url}).insertTo(doc.head);
                    return v;
                }

                if (!(qu = _jsp[url])) {  // 保存延迟加载对象
                    qu = _jsp[url] = new Promise((resolve, reject) => {
                        v.$('<script>').attr({'type': 'text/javascript', 'src': url, 'async': 'async'})
                                .on('load', resolve).insertTo(doc.body);
                    });
                }
            }
            !fn || qu.then(fn);
            return qu;
        },

        // 自定义类，允许需要时加载JS arguments1 ['parent class name', 'js file1', 'jsfile2']，返回promise
        define: function (/*parent, */fn) {
            let parent;
            if (arguments.length > 1) {
                [parent, fn] = arguments;
                if (v.isArray(parent)) {
                    let fs = parent;
                    if (fs[0].indexOf('.js') < 0) {
                        parent = fs[0];
                        fs.splice(0, 1);
                    }
                    if (fs.length > 0)
                        return new Promise((resolve) => {
                            v.load(fs, function () {
                                parent = eval(parent);
                                resolve(v.classd(parent, fn));
                            });
                        });
                }
            }
            return Promise.resolve(v.classd(parent, fn));
        },

        // 定义工具
        kit: function (name, /*parent, */fn/*, ns*/) {
            let args = [...arguments],
                    ns = v.isObject(args[args.length - 1]) ? args.pop() : v;
            if (args.length <= 2) {
                args[0] = v.Kit;
            } else {
                args.shift();
                if (v.isArray(args[0]) && args[0][0].indexOf('.js') > 0)
                    args[0].splice(0, 0, v.Kit);
            }
            return v.define(...args).then((obj) => {
                ns[name] = obj;
                let fn = obj.init.bind(obj);
                v.ready(fn);
                v.addEvent(v.ONNODE, fn);
            });
        },

        // 建立模型
        model: function (name, /*parent, */fn) {
            let args = [...arguments];
            if (args.length <= 2) {
                args.splice(1, 0, v.Model);
            } else {
                if (v.isArray(args[1]) && args[1][0].indexOf('.js') > 0)
                    args[1].splice(0, 0, v.Model);
            }
            args.push(win);
            return v.kit(...args);
        },

        // 单选择器
        $: function (s, ctx = doc) {
            if (v.isObject(s) && ('original' in s))
                return s;
            if (v.isString(s)) {
                if (s === 'doc')
                    s = doc.documentElement;
                else if (s.charAt(0) === '<')
                    s = s.indexOf('/>') > -1 ? v.str2Dom(s) : doc.createElement(s.replace(/<|>/g, ''));
                else
                    s = ctx.querySelector(s);
            }
            return s ? s['_vnode'] || (s['_vnode'] = new Proxy(new vNode(s), vNode.proxyHandler)) : _enode;
        },
        // 多选
        $s: function (s, ctx = doc) {
            if (!v.isString(s))
                return [s];
            return Array.from(ctx.querySelectorAll(s));
        }
    };
    var v = window.v = new v();
    // js框架跟路径
    v.base = (function () {
        let src = doc.currentScript.src;
        return src.substr(0, src.lastIndexOf('/') + 1);
    })();

    /**
     * DOM对象类
     * @param {domnode} el
     */
    var vNode = v.classd(function (self) {
        // 代理处理方法
        self.proxyHandler = {
            get: (target, key, receiver) => {
                if (key in target)
                    return Reflect.get(target, key, receiver);
                // dom对象的函数返回需要绑定this
                let prop = target.original[key];
                return v.isFunction(prop) ? prop.bind(target.original) : prop;
            },
            set: (target, key, value) => {
                if (key in target)
                    target[key] = value;
                else {
                    target.original[key] = value;
                    // 触发node属性变化监控
                    !target._watchProps[key] || v.forEach(target._watchProps[key], fn => v.callim(fn));
                }
            }
        };

        // 原始对象
        this.original = null;

        // 元素显示状态
        this._hiddenState = 0;

        // 元素显示方式 inline inline-block block
        this._displayStyle = 'block';

        // 属性变化监控
        this._watchProps = [];

        // 动画队列
        this._animQueue = [];

        // 正准备执行的动画事件
        this._animEvent = null;

        // animation动画结束事件类型
        var _animEndEventType = 'webkitAnimationEnd animationend webkitTransitionEnd transitionend';

        // transition动画结束事件
        var _transEndEventType = 'webkitTransitionEnd transitionend';

        // 执行一次动画队列
        var _animExec = function () {
            this._animEvent = null;
            let fn = this._animQueue.shift();
            !fn || fn.call(this);
        };


        // 显示隐藏元素
        var _showHidden = function () {
            if (this.css('display') !== 'none') {
                this._hiddenState = 0;
            } else {
                this._hiddenState = 1;
                this.css({'visibility': 'hidden', 'display': this._displayStyle});
                let p = this.parentNode;
                if (p !== doc.body && v.$(p).css('position') === 'static') {
                    p.style.position = 'relative';
                    this._hiddenState = 2;
                }
            }
        };

        // 恢复隐藏元素
        var _undoHidden = function () {
            if (this._hiddenState) {
                this.css({'visibility': 'visible', 'display': 'none'});
                if (this._hiddenState > 1)
                    this.parentNode.position = '';
            }
        };

        // 初始化
        this.construct = function (original) {
            this.original = original;
            if (original === win || original === doc)
                throw new TypeError('Must be dom node');

            this._displayStyle = this.css('display');
            this._displayStyle !== 'none' || (this._displayStyle = this.attr('data-css-display') || 'block');
        };

        /**
         * 向下选择
         * @param {string} s
         * @return {vNode}
         */
        this.$ = function (s) {
            return this.original ? v.$(s, this.original) : _enode;
        };

        /**
         * 向下选择多个
         */
        this.$s = function (s) {
            return this.original ? v.$s(s, this.original) : [];
        };

        /**
         * 向上冒泡选择
         * 请注意包括自身，小心避免死循环
         * @param {string} s
         * @param {node} ctx  可定义向上层数，加快速度
         * @returns {vNode} || null
         */
        this.$p = function (s, ctx = doc.body) {
            if (!this.original)
                return null;
            let num = 32, node = this.original;
            if (v.isNumber(ctx)) {
                num = ctx;
                ctx = doc.body;
            } else if (ctx.$) {
                ctx = ctx.original;
            }
            while (node && node.nodeName && (node !== ctx) && (num-- > 0)) {
                if (node.matches(s)) {
                    break;
                } else {
                    node = node.parentNode;
                }
            }
            return !node || node === ctx ? null : v.$(node);
        };
        /**
         * 选择兄弟节点
         */
        this.$b = function () {
            if (!this.original)
                return [];
            let s = this.original || this, r = [], n = s.parentNode.firstChild;
            do {
                if (n.nodeType === 1 && n !== s)
                    r.push(n);
            } while (n = n.nextSibling);
            return r;
        };
        /**
         * 添加事件
         * 同jquery on 
         */
        this.on = function (type, /*selector, */fn/*,...*/) {
            if (this.original) {
                let args = [...arguments];
                if (v.isString(fn)) {
                    let s = args[1];
                    fn = args[2];
                    args.splice(1, 1);
                    fn['_veventfn' + v.strHash(type) + s] = args[1] = (event) => {
                        let el = v.$(event.target).$p(s, this.original); // 冒泡使用法
                        if (el && el.original) {
                            event.srcTarget = el.original;
                            fn(event);
                        }
                    };
                }
                type.replace(/\s+/g, ',').split(',').forEach(type => {
                    args[0] = type;
                    this.original.addEventListener(...args);
                });
            }
            return this;
        };
        /**
         * 移除事件
         * 同 jquery off
         */
        this.off = function (type, /*selector, */fn/*,...*/) {
            if (this.original) {
                let args = [...arguments], htype = v.strHash(type), k;
                if (!v.isString(fn)) {
                    k = '_veventfn' + htype;
                } else {
                    k = '_veventfn' + htype + args[1];
                    args.splice(1, 1);
                }
                if (k in args[1]) {
                    fn = args[1][k];
                    delete args[1][k];
                    args[1] = fn;
                }
                type.replace(/\s+/g, ',').split(',').forEach(type => {
                    args[0] = type;
                    this.original.removeEventListener(...args);
                });
            }
            return this;
        };
        /**
         * 绑定单次事件
         * 同 jquery one
         */
        this.one = function (type, fn) {
            if (this.original) {
                let args = [...arguments];
                fn['_veventfn' + v.strHash(type)] = args[1] = (event) => {
                    this.off(...args);
                    fn(event);
                };
                this.on(...args);
            }
            return this;
        };
        /**
         * 属性监控
         * @param {string} prop
         * @param {function} fn
         */
        this.watch = function (prop, fn) {
            if (this.original) {
                prop in this._watchProps ? this._watchProps[prop].push(fn) :
                        this._watchProps[prop] = [fn];
            }
            return this;
        };
        /**
         * 判断是否有class
         * @param {string} cls
         * @returns {Boolean}
         */
        this.hasClass = function (cls) {
            if (!this.original)
                return false;
            return (' ' + this.original.className + ' ').indexOf(' ' + cls.replace('.', '') + ' ') > -1;
        };
        /**
         * 添加class，支持多个
         * @param {string} cls
         * @returns {vNode}
         */
        this.addClass = function (cls) {
            if (this.original) {
                let s1 = v.isString(cls) ? cls.replace(/\./g, '').split(' ') : cls, c1 = ' ' + this.original.className + ' ', c2 = '';
                for (let s2 of s1)
                    c1.indexOf(' ' + s2 + ' ') > -1 || (c2 += s2 + ' ');
                !c2 || (this.original.className = (c1 + c2).trim());
            }
            return this;
        };
        /**
         * 删除class，支持多个
         * @param {string} cls
         * @returns {vNode}
         */
        this.rmvClass = function (cls) {
            if (this.original) {
                let s1 = v.isString(cls) ? cls.replace(/\./g, '').split(' ') : cls, c1 = ' ' + this.original.className + ' ';
                for (let s2 of s1)
                    c1.indexOf(' ' + s2 + ' ') < 0 || (c1 = c1.replace(' ' + s2 + ' ', ' '));
                this.original.className = c1.replace(/\s\s+/, ' ').trim();
            }
            return this;
        };
        /**
         * 切换class
         * @param {string} cls
         * @returns {vNode}
         */
        this.toggleClass = function (cls) {
            if (this.original) {
                let s1 = v.isString(cls) ? cls.replace(/\./g, '').split(' ') : cls, c1 = ' ' + this.original.className + ' ', c2 = '';
                for (let s2 of s1)
                    c1.indexOf(' ' + s2 + ' ') > -1 ? c1 = c1.replace(' ' + s2 + ' ', ' ') : c2 += ' ' + s2;
                this.original.className = c1.replace(/\s\s+/, ' ').trim() + c2;
            }
            return this;
        };
        /**
         * css animation transform动画
         * @param {string|object} css
         * @param {function} fn
         * @returns {vNode}
         */
        this.animation = function (css, fn = null) {
            this._animQueue.push(() => {
                let isClass = v.isString(css), etype = isClass ? _animEndEventType : _transEndEventType, event;
                this._animEvent = event = () => {
                    if (this._animEvent = event) // 执行时清除当前事件，防止循环调用
                        this._animEvent = null;
                    isClass ? this.rmvClass(css) : this.style.transition = '';
                    this.off(etype, event);
                    !fn || fn.call(this.original);
                    _animExec.call(this);
                };
                this.on(etype, event);
                if (isClass)
                    this.addClass(css);
                else {
                    css.transition || (css.transition = 'all .2s ease-out');
                    this.css(css);
                }
            });
            this._animQueue.length > 1 || _animExec.call(this);  // 队列中无事件主动执行
            return this;
        };
        /**
         * css transition动画
         * @param {type} css
         * @param {type} fn
         * @returns {vNode}
         */
        this.transiton = function (css, fn = null) {
            return this.animation(...arguments);
        };
        /**
         * 停止动画队列
         */
        this.stop = function () {
            this._animQueue = [];
            !this._animEvent || this._animEvent.call(this);
            return this;
        };
        /**
         * 显示元素，支持css动画与回调
         * @param {string} clsname
         * @param {function } fn
         * @returns {vNode}
         */
        this.show = function (clsname = null, fn = null) {
            this.stop().css({display: this._displayStyle}).css('display');  // 强制DOM显示重新计算，动画才能执行
            if (clsname)
                this.animation(clsname, () => {
                    !fn || fn.call(this);
                }, true);
            return this;
        };
        /**
         * 隐藏元素，支持css动画与回调
         * @param {string} clsname
         * @param {function} fn
         * @returns {vNode}
         */
        this.hide = function (clsname = null, fn = null) {
            this.stop().css('display');
            if (!clsname)
                this.style.display = 'none';
            else
                this.animation(clsname, () => {
                    this.style.display = 'none';
                    !fn || fn.call(this);
                }, true);
            return this;
        };
        /**
         * 取得|设置css
         */
        this.css = function (css, value = undefined) {
            let o = this.original, is = v.isString(css);
            if (is && value === undefined) {
                if (this.original) {
                    let val = win.getComputedStyle(o).getPropertyValue(css);
                    return /(width|height|left|top|right|bottom)$/i.test(css) ? parseFloat(val) || 0 : val;
                }
                return null;
            }

            if (this.original) {
                !is || (css = {[css]: value});
                v.forEach(css, (val, k) => {
                    o.style[k] = v.isNumber(val) && !/zIndex|fontWeight|opacity|zoom/.test(k) ? val + 'px' : val;
                });
            }
            return this;
        };
        /**
         * 取得|设置属性
         */
        this.attr = function (attr, value = undefined) {
            let o = this.original, is = v.isString(attr);
            if (is && value === undefined) {
                if (this.original) {
                    let val = (o.getAttribute ? o.getAttribute(attr, 2) : null) || o[attr];
                    return /(width|height|left|top|right|bottom)$/i.test(attr) ? parseFloat(val) || 0 : val;
                }
                return null;
            }

            if (this.original) {
                !is || (attr = {[attr]: value});
                v.forEach(attr, (val, k) => {
                    v.isNull(val) ? o.removeAttribute(k) : (v.isString(val) || v.isNumber(val) ? o.setAttribute(k, val) : o[k] = val);
                    // 触发node属性变化监控
                    !this._watchProps[k] || v.forEach(!this._watchProps[k], fn => v.callim(fn));
                });
            }
            return this;
        };
        /**
         * 取得|设置html
         */
        this.html = function (value = undefined) {
            let o = this.original;
            if (value !== undefined) {
                if (this.original)
                    v.isObject(value) ? (this.empty() && o.appendChild(value)) : o.innerHTML = value;
                return this;
            }
            return this.original ? o.innerHTML : '';
        };
        /**
         * 取得设置值
         */
        this.val = function (value = undefined) {
            if (value === undefined)
                return this.original ? v.domVal(this.original) : '';
            if (this.original)
                v.domVal(this.original, value);
            return this;
        };
        /**
         * 批量设置|获取值
         */
        this.json = function (value = undefined) {
            if (value === undefined)
                return this.original ? v.domJson(this.original) : '';
            if (this.original)
                v.domJson(this.original, value);
            return this;
        };
        /**
         * 删除节点
         * @returns {vNode}
         */
        this.remove = function () {
            if (this.original)
                this.original.parentNode.removeChild(this.original);
            return this;
        };
        /**
         * 删除内部节点
         * @returns {vNode}
         */
        this.empty = function () {
            if (this.original)
                this.original.innerHTML = '';
            return this;
        };
        /**
         * 插入子元素
         * @param {node} child
         * @param {node} ref
         * @returns {vNode}
         */
        this.insertIn = function (child, ref) {
            if (this.original) {
                child = child.original || child;
                ref = ref === true ? this.original.firstChild : (ref ? ref.original || ref : null);
                this.original.insertBefore(child, ref);
            }
            return this;
        };
        /**
         * 插入到父元素
         * @param {node} parent
         * @param {node} ref
         * @returns {vNode}
         */
        this.insertTo = function (parent, ref) {
            if (this.original) {
                parent = v.$(parent).original;
                ref = ref === true ? parent.firstChild : (ref ? ref.original || ref : null);
                parent.insertBefore(this.original, ref);
            }
            return this;
        };
        // 取得对象包括margin在内的left,top,width,height
        this.offset = function (box) {
            if (this.nodeName === 'BODY')
                return this.clnset();
            let left = 0, top = 0, width = 0, height = 0, el = this.original;
            if (this.original) {
                _showHidden.call(this);
                box = box ? (box.original || box) : el.offsetParent;
                while (el && el !== box) {
                    left += el.offsetLeft - el.scrollLeft;
                    top += el.offsetTop - el.scrollTop;
                    el = el.offsetParent;
                }
                let marginLeft = this.css('marginLeft'), marginTop = this.css('marginTop');
                left -= marginLeft;
                top -= marginTop;
                width = this.offsetWidth + marginLeft + this.css('marginRight');
                height = this.offsetHeight + marginTop + this.css('marginBottom');
                _undoHidden.call(this);
            }
            return {left, top, width, height};
        };
        // 获得client left top height width,取得的宽高与样式设定相同
        this.client = function () {
            let left = 0, top = 0, width = 0, height = 0;
            if (this.original) {
                _showHidden.call(this);
                left = this.attr('scrollLeft');
                top = this.attr('scrollTop');
                width = this.attr('clientWidth') || (this.offsetWidth - this.css('borderLeft') - this.css('borderRight'));
                height = this.attr('clientHeight') || this.offsetHeight - this.css('borderTop') - this.css('borderBottom');
                width = width - this.css('paddingLeft') - this.css('paddingRight');
                height = height - this.css('paddingTop') - this.css('paddingBottom');
                _undoHidden.call(this);
            }
            return {left, top, width, height};
        };
        // 获得scroll left top height width
        this.scroll = function () {
            let left = 0, top = 0, width = 0, height = 0;
            if (this.original) {
                _showHidden.call(this);
                left = this.attr('scrollLeft');
                top = this.attr('scrollTop');
                width = this.attr('scrollWidth');
                height = this.attr('scrollHeight');
                _undoHidden.call(this);
            }
            return {left, top, width, height};
        };
    });
    _enode = new vNode(null);

    /**
     * ajax处理对象
     */
    var vAjax = v.classd(function (self) {
        self.defaults = {};

        this.defaults = {
            method: 'GET',
            url: '',
            data: {},
            async: true,
            progress: null,
            complete: _efn,
            success: _efn,
            error: _efn,
            header: {}, // 请求头部
            reqType: 'form', // 请求内容的类型 arraybuffe blob document form json
            resType: ''  // 响应的内容类型  arraybuffe blob document text json
        };

        this._isGet = true;

        // 请求响应
        var _response = function (req, resolve, reject) {
            // 格式转换错误捕获
            let status = req.status, data = req.response || req.responseText;
            try {
                req.responseType !== 'json' || !v.isString(data) || data.indexOf('{') < 0 || (data = JSON.parse(data));
            } catch (e) {
                data = 'Type error, ' + status + ', ' + req.statusText;
                status = 406;
            }
            req = null;
            status >= 200 && status < 300 ? !resolve || resolve(data) : !reject || reject(data);
            return [data, status];
        };

        // 初始化
        this.construct = function (options = {}) {
            this.reset(options);
        };

        // 重设配置
        this.reset = function (options = {}) {
            v.mixin(options, this.defaults, this.constructor.defaults);
            this.options(options);
            return this;
        };

        // 配置options
        this.options = function (options) {
            options.resType = options.resType ? options.resType : options.url && options.url.indexOf('.json') > 0 ? 'json' : '';
            options === this || v.mixin(this, options, v.COVER);
            this._isGet = 'POST|PUT'.indexOf(this.method) === -1;
            return this;
        };

        // 发送ajax请求
        this.request = function (options = null) {
            !options || this.options(options);
            let promise = new Promise((resolve, reject) => {
                let req = new win.XMLHttpRequest();
                this._isGet ? req.open(this.method, v.urlQrs(this.data, this.url), this.async) : req.open(this.method, this.url, this.async);
                !this.progress || (req.onprogress = this.progress);
                req.onload = () => this.complete(..._response(req, resolve, reject));
                req.setRequestHeader('method', this.method + ' ' + this.url + ' HTTP/1.1');
                req.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                !this.async || (req.responseType = this.resType);
                // 自定义头部
                v.forEach(this.header, (val, k) => req.setRequestHeader(k, val));
                if (this._isGet) {
                    req.send();
                } else {
                    switch (this.reqType) {
                        case 'json':
                            req.setRequestHeader('Content-Type', 'application/json');
                            req.send(JSON.stringify(this.data));
                            break;
                        case 'form':
                            req.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                            req.send(v.urlQrs(this.data));
                            break;
                        default:
                            req.send(this.data);
                    }
                }
            }).then(this.success, this.error);
            return promise;
        };
    });
    v.Ajax = vAjax;
    v.ajax = new vAjax();

    /**
     * 语言对象
     */
    var vLang = v.classd(function () {
        this.langs = {};
        this.text = function (ln) {
            let lng = (navigator.browserLanguage || navigator.language).toLowerCase().replace('_', '-');
            v.forEach(ln[lng] || ln, (val, k) => {
                this.langs[k.toLowerCase().replace(/\s*/g, '')] = val;
            });
        };
        this.trans = function (s) {
            // 对象翻译
            if (v.isObject(s)) {
                s = v.clone(s);
                v.forEach(s, (val, i) => s[i] = this.trans(val));
                return s;
            }
            // 字符翻译
            let langs = this.langs, str = s.toLowerCase().replace(/\s*/g, '');
            if (langs[str]) {
                s = langs[str];
            } else if (str.indexOf('[') && str.indexOf(']')) {
                let vals = [], i = 0;
                str = str.replace(/\[(.*?)\]/g, (all, val) => {
                    vals.push(val);
                    return '[' + (i++) + ']';
                });
                if (langs[str]) {
                    s = langs[str].replace(/\[(\d+)\]/g, (all, val) => '[' + vals[parseInt(val)] + ']');
                }
            }
            return s;
        };
    });
    v.lang = new vLang();

    // 框架组件基类，定义了事件处理方式
    v.Kit = v.classd(function (self) {
        //
        // 类静态定义
        //
        self.anchor = 'v-kit'; // 锚点定义，每个子类必须定义自己锚点
        self.defaults = {}; // 默认配置
        self.lang = new vLang();  // 语言翻译对象

        // 初始化dom对象
        self.init = function (ctx = doc) {
            v.$s('[' + this.anchor + ']', ctx).forEach(el => this.obj(el));
        };
        // 取得dom绑定的对象
        self.obj = function (el) {
            !v.isString(el) || (el = doc.querySelector(el));
            el._vkits || (el._vkits = {});
            if (!el._vkits[this.anchor]) {
                // options参数，支持json与属性方式
                let options = (el.getAttribute(this.anchor) || '').trim();
                options = options ? (options.charAt(0) === '{' ? JSON.parse(options) : v.attr2Json(options)) : {};
                let events = options.events || {};
                delete options.events;
                v.forEach(options, (fn, e) => {
                    if (e.substr(0, 2) === 'on') {
                        delete options[e];
                        events[e.substr(2)] = fn;
                    }
                });
                options['anchor'] = el;
                // 建立对象，添加事件
                let obj = el._vkits[this.anchor] = new this(options);
                !events || v.forEach(events, (fn, e) => {
                    obj.addEvent(e, obj.bubble(fn.replace(/\(|\)/g, '')));
                });
            }
            return el._vkits[this.anchor];
        };
        // 翻译
        self.trans = function (str) {
            // 设置翻译文本 ?: 翻译数据
            return v.isObject(str) && !v.isArray(str) ? this.lang.text(str) : this.lang.trans(str);
        };

        //
        // 类定义
        //
        this.anchor = null;  // 控件作用对象

        // 对象事件，由子类定义，事件必须在此定义成数组
        this._events = {
            //'inited' : []  // 初始化事件
        };

        // 构造函数
        this.construct = function (options) {
            this.parent();
            v.mixin(this, v.mixin(options || {}, this.constructor.defaults), v.COVER);
        };

        // 取得按DOM方式继承的方法,注意this为拥有该方法的model
        this.bubble = function (prop) {
            let node = this.anchor;
            do {
                if (node.hasAttribute('v-model')) {
                    let obj = v.Model.obj(node);
                    if (prop in obj)
                        return v.isFunction(obj[prop]) ? v.bind(obj[prop], obj) : obj[prop];
                }
                node = node.parentNode;
            } while (node && node.nodeName && (node !== doc));
            return win[prop];
        };

        // 解析函数与参数
        this.fnargs = function (fn, val = undefined) {
            let args = fn.replace(')', '').replace('(', ',').replace(/\s*,\s*/, ',').replace(/,$/, '').trim().split(',');
            fn = args[0];
            for (let len = args.length, i = 1; i < len; i++) {
                if (args[i].charAt(0) === '$')
                    args[i] = this.bubble(args[i].substr(1));
            }
            val !== undefined ? args[0] = val : args.splice(0, 1);
            return [fn, args];
        };

        // 添加自定义事件
        this.addEvent = function (type, fn) {
            !this._events[type] || this._events[type].push(fn);
        };
        // 删除自定义事件
        this.rmvEvent = function (type, fn) {
            if (this._events[type]) {
                let pos = this._events[type].indexOf(fn);
                pos < 0 || this._events.splice(pos, 1);
            }
        };
        // 触发自定义事件
        this.triEvent = function (type, ...args) {
            !this._events[type] || v.forEach(this._events[type], e => e(...args));
        };
        // 翻译
        this.trans = function (str) {
            return self.trans(str);
        };

    });

    // 数据模型基类，数据模型代表一个特定的作用域，具有包含关系，执行的事件会向上冒泡查询
    v.Model = v.classd(v.Kit, function (self) {

        self.anchor = 'v-model'; // 锚点定义，每个子类必须定义自己锚点

        // 取得dom绑定的对象
        self.obj = function (el) {
            !v.isString(el) || (el = doc.querySelector(el));
            el._vkits || (el._vkits = {});
            if (!el._vkits[this.anchor]) {
                let cls = el.getAttribute(this.anchor).trim(),
                        options = {'anchor': el};
                let obj = el._vkits[this.anchor] = new win[cls](options);
                this.parse(obj);
            }
            return el._vkits[this.anchor];
        };

        // 解析节点事件,括号中的参数为事件代理
        self.parse = function (obj, ctx) {
            // 处理 data-event的事件绑定
            v.$s('[data-event]', ctx || obj.anchor).forEach(el => {
                let events = v.attr2Json(el.getAttribute('data-event'));
                v.forEach(events, (fn1, type) => {
                    v.forEach(fn1.split('|'), (fn2) => {  // 同一个事件多个处理方法用 | 隔开
                        let [fn, args] = obj.fnargs(fn2);
                        fn = obj.bubble(fn);
                        if (v.isFunction(fn)) {
                            args.length ? v.$(el).on(type, args[0], fn) : v.$(el).on(type, fn);
                        }
                    });
                });
            });
        };

    });

})(window, window.document);
/**
 * Upfile Script
 * 上传插件
 * v-upfile="{}"
 */

v.Upfile || v.kit('Upfile', function (self) {
    // 静态属性
    self.anchor = 'v-upfile';
    // 共有变量
    this.anchor = null; // 文件地址保存input，所有控件都以anchor来标识
    this.baseUrl = '/';
    this.upNum = 1; // 上传最大数量
    this.maxSize = 1024; // 允许文件大小 KB
    this.acceptType = '.jpg.png.gif'; // 允许文件类型
    this.upFile = 'input[type="file"]';  // 上传控件
    this.preview = '.preview';  // 预览区域
    this.inputUrl = 'input[type="hidden"]';  // 文件地址框
    this.selImg = 'img';  // 预览图片选择器
    this.selName = 'label'; // 预览名称选择器
    this.selClose = 'a'; // 删除选择器

    // 类私有变量
    this._urls = [];  // 已经上传文件，包含本地文件,本地文件为文件名
    this._files = []; // 本地文件实体
    this._viewTpl = null; // 预览模板
    this._errEl = null; // 错误消息显示对象

    // 预览
    var _preview = function (file) {
        let src, name, el = v.$(v.str2Dom(this._viewTpl)), img = v.$(this.selImg, el);
        if (v.isString(file)) {
            name = file;
            if (img.original)
                src = this.baseUrl + file;
        } else {
            name = 'blob:' + file.name;
            if (img.original) {
                src = window.URL.createObjectURL(file);
                /*
                 img.on('load', function () {
                 window.URL.revokeObjectURL(this.src);
                 });*/
            }
        }
        img.attr({src: src});
        v.$(this.selName, el).html(name);
        v.$(el).attr({'data-upfile-id': name});
        v.$(this.preview).insertIn(el.original);
    };

    // 初始化预览对话框
    var _initViewBox = function () {
        // 保持预览模板
        this._viewTpl = v.$(this.preview).html();
        // 添加删除事件
        v.$(this.preview).empty().show().on('click', this.selClose, (event) => {
            event.stopPropagation();
            let el = v.$(event.srcTarget).$p('[data-upfile-id]', this.anchor);
            if (el) {
                let name = el.attr('data-upfile-id'), pos;
                if ((pos = this._urls.indexOf(name)) > -1) {
                    this._urls.splice(pos, 1);
                    // 删除本地预览文件
                    for (let i in this._files) {
                        if ('blob:' + this._files[i].name === name) {
                            this._files.splice(i, 1);
                            break;
                        }
                    }
                }
                this.inputUrl.value = this._urls.join(','); // 原始复制不出发watch事件
                el.remove();
                v.$(this.upFile).show();
            }
        });
    };

    // 效验文件的大小与类型，只要有一个不成功则视为不成功
    var _isValid = function (files) {
        for (let file of files) {
            if (file.name.replace(/[,;+&\s]/, '') !== file.name) {
                v.$(this._errEl).html(this.trans('File [' + file.name + '] name cannot contain [,;+&] and space'));
                return false;
            } else if (file.size / 1024 > this.maxSize) {
                v.$(this._errEl).html(this.trans('File [' + file.name + '] size cannot be more than [' + this.maxSize + '] KB'));
                return false;
            } else {
                let type = file.name.substr(file.name.lastIndexOf('.')) + '.';
                if ((this.acceptType + '.').indexOf(type) < 0) {
                    v.$(this._errEl).html(this.trans('File [' + file.name + '] type cannot be except [' + this.acceptType + ']'));
                    return false;
                }
            }
        }
        v.$(this._errEl).html('');
        return true;
    };

    // 公有方法
    // 
    // 初始化
    this.construct = function () {
        this.parent();
        this.preview = v.$(this.preview, this.anchor).original;
        this.inputUrl = v.$(this.inputUrl, this.anchor).original;
        this.upFile = v.$(this.upFile, this.anchor).original;
        this._errEl = v.$('[for="' + this.inputUrl.name + '"]', this.anchor).original;

        _initViewBox.call(this);
        this.reset();
        // 数据变化时上传
        v.$(this.upFile).on('change', () => {
            let files = this.upFile.files, num = this.upNum - this._urls.length;
            if (num > 0 && _isValid.call(this, files)) {
                for (let name, file, i = 0, len = Math.min(num, files.length); i < len; i++) {
                    file = files[i];
                    name = 'blob:' + file.name;
                    if (this._urls.indexOf(name) < 0) {
                        this._files.push(file);
                        this._urls.push(name);
                        _preview.call(this, file);
                    }
                }
                this.inputUrl.value = this._urls.join(','); // 原始复制不出发watch事件
                // 上传张数已够，隐藏上传控件
                if (files.length >= num)
                    v.$(this.upFile).hide();
            }
        });

        // 监听inputUrl数据的变化
        v.$(this.inputUrl).watch('value', () => {
            this.reset();
        });
    };

    // 重设
    this.reset = function () {
        this._files = [];
        this._urls = [];
        v.$(this.preview).html('');
        // 已上传文件预览
        let file = (this.inputUrl.value || '').replace(/^,|,$/g, '').trim();
        this._urls = !!file ? v.str2Array(file) : [];
        this._urls.forEach(url => _preview.call(this, url));
    };

    // 取得上传文件
    this.files = function () {
        return this._files;
    };

    // 取得formData格式数据
    this.formData = function () {
        let fdata = new FormData();
        this._files.forEach(file => fdata.append(v.$(this.upFile).attr('name'), file));
        return fdata;
    };

    // 翻译
    self.trans({'zh-cn': {
            'File [0] type cannot be except [1]': '文件[0]类型必须是[1]',
            'File [0] size cannot be more than [1] KB': '文件[0]大小超出[1]KB',
            'File [0] name cannot contain [1] and space': '文件[0]名称含有特殊字符[1]与空格'
        }});
});

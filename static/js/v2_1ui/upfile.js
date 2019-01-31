/**
 * Upfile Script
 * 上传插件
 * data-upfile="v2ui"
 */

v.Upfile || (function () {
    v.Upfile = v.extend(v.UI, function (self) {
        // 静态属性
        self.anchor = 'data-upfile';

        // 共有变量
        this.anchor = null; // 文件地址保存input，所有控件都以anchor来标识
        this.upMulti = false; // 允许上传多个
        this.upUrl = '';
        this.upText = 'Upload File';
        this.upImg = true;  // 是否图片上传
        this.baseUrl = '';
        this.callvar = 'callback';
        this.preview = true;  // 是否预览

        // 事件定义
        this._events = {
            'start': [], // 上传开始
            'success': [], // 上传成功
            'complete': [], // 上传完成
            'delete': []  // 删除成功
        };

        // 类私有变量
        this._files = []; // 文件路径
        this._frameID = '';
        this._upBox = null;
        this._upForm = null;
        this._upFile = null;
        this._upFrame = null;
        this._upView = null;

        // 上传模板
        var _upTpl = '<div class="upfile">\
                <form class="upfile-form" target="{{frameID}}" action="{{upUrl}}" enctype="multipart/form-data" method="POST">\
                    <div class="upfile-div">\
                        {{upText}}\
                        <input type="file" name="upfile" />\
                    </div>\
                </form>\
                <iframe name="{{frameID}}" id="{{frameID}}" hidden></iframe>\
           </div>';

        // 预览模板，单条
        var _viewTpl = //'<ul class="upfile-preview">\
                '<li id="{{fileID}}" path="{{filePath}}">\
                        <a class="filebox" href="{{fileUrl}}" target="_blank">{{fileName}}</a>\
                        <a class="button delete">&#8854;</a>\
                    </li>';
        //</ul>';

        // 初始化上传box
        var _initUpBox = function () {
            var data = {
                'frameID': 'upfile-' + v.uid12(),
                'upUrl': this.upUrl,
                'upText': this.upText
            };
            var hstr = v.strParse(_upTpl, data);
            this._upBox = v.str2Dom(hstr, 0);
            this._upForm = v.$('form', this._upBox);
            this._upFile = v.$('input', this._upBox);
            this._upFrame = v.$('iframe', this._upBox);
            this.anchor.parentNode.appendChild(this._upBox);
        };

        // 初始化预览box
        var _initViewBox = function () {
            var ul = v.$('<ul>');
            ul.className = 'upfile-preview';
            this._upBox.appendChild(ul);
            this._upView = ul;
            // 添加删除事件
            $(this._upView).on('click', '.delete', function (event) {
                var el = v.$p('li', event.currentTarget);
                if (el) {
                    this['delete'](el.getAttribute('path'));
                }
            }.bind(this));
        };

        // 立刻上传
        var _startImUp = function () {
            this._upForm.submit();
            this.triEvent('start', {
                target: this.anchor
            });
        };

        // 开始上传
        var _startUp = function () {
            // 先删除原来的
            if (!this.upMulti && this._files.length > 0) {
                this._files = [];
                this.anchor.value = '';
                _startImUp.call(this);
                /*
                 this['delete'](this._files[0], function () {
                 _startImUp.call(this);
                 }.bind(this));*/
            } else {
                _startImUp.call(this);
            }
        };

        // 上传成功
        var _completeUp = function (rs, status) {
            var event = {
                target: this.anchor,
                status: status
            };
            if (status === 200) {
                // 取得url
                this._files.push(rs);
                this.anchor.value = this._files.join(',');
                event.imgurl = rs;
                !this.preview || _preview.call(this, rs);
                this.triEvent('success', event);
            } else {
                // 发生错误
                event.error = rs;
                v.alert ? v.alert(rs) : alert(rs);
            }
            this.triEvent('complete', event);
        };

        // 预览
        var _preview = function (/*path*/) {
            if (arguments.length === 0) {  // 预览全部
                this._upView.innerHTML = '';
                v.forEach(this._files, function (path) {
                    _preview.call(this, path);
                }.bind(this));
            } else {  // 加入一个预览
                var filePath = arguments[0], fileUrl = (this.baseUrl + filePath).replace(/^\/\//, '/'), fileName = filePath;
                if (this.upImg) {  // 图片预览
                    fileName = '<img src="' + fileUrl + '" />';
                }
                var hstr = v.strParse(_viewTpl, {
                    'fileUrl': fileUrl,
                    'fileName': fileName,
                    'filePath': filePath,
                    'fileID': 'upfile_' + v.strHash(filePath)
                });
                this._upView.appendChild(v.str2Dom(hstr));
            }
        };

        // 公有方法
        // 
        // 初始化
        this.construct = function () {
            this.parent();

            // 初始化属性
            if (this.upUrl.indexOf(this.callvar) < 0) {
                this.upUrl = v.urlQrs(v.objVal({}, this.callvar, 'upComplete'), this.upUrl);
            }
            var file = this.anchor.value.replace(/\s*/, '');
            if (!!file)
                this._files = file.split(',');

            // 初始化element
            _initUpBox.call(this);
            !this.preview || _initViewBox.call(this);

            // 文件上传结束回调
            $(this._upFrame).on('load', function () {
                this._upFrame.contentWindow.upComplete = _completeUp.bind(this);
            }.bind(this));

            // 数据变化时上传
            $(this._upFile).on('change', _startUp.bind(this));
        };

        // 删除文件
        this['delete'] = function (path/*, callback*/) {
            var callback = arguments[1];
            $.ajax({
                method: 'DELETE',
                url: v.urlQrs({'path': path}, this.upUrl.replace('.html', '.json')),
                complete: function (res, status) {
                    status = res.status;
                    if (status === 200) {
                        var pos = this._files.indexOf(path);
                        if (pos > -1)
                            this._files.splice(pos, 1);
                        this.anchor.value = this._files.join(',');
                        $('#upfile_' + v.strHash(path)).remove();
                        // 触发成功删除事件
                        this.triEvent('delete', {
                            target: this.anchor,
                            imgurl: path
                        });
                        !callback || callback();
                    } else {
                        v.alert ? v.alert(res.responseText) : alert(res.responseText);
                    }
                }.bind(this)}
            );
        };
    });
})();
$(function () {
    v.Upfile.init();
});
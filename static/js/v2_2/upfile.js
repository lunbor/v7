/**
 * Upfile Script
 * 上传插件
 * v-upfile="upUrl:http://xxx/xxx.html;"
 * 
 * 该上传插件需要服务器端支持
 * 如在PHP中上传成功后返回
 *  echo '<script>setTimeout(function(){upComplete(' . ($errorMsg || $fileUrl) . ", $status)" . '},13);</script>';
 *  以状态区分是否成功，200为成功，400或其他状态为失败
 *  
 *  服务器端处理
 *  1.文件先上传到临时目录，数据保存成功后，移入到正式目录
 *  2.保存时候需要对比删除的文件，从正式目录删除文件
 *  
 *  服务器端不允许通用控制器上传
 */

v.Upfile || v.kit('Upfile', function (self) {
    // 静态属性
    self.anchor = 'v-upfile';

    // 共有变量
    this.anchor = null; // 文件地址保存input，所有控件都以anchor来标识
    this.upMulti = false; // 允许上传多个
    this.upUrl = '';
    this.upText = 'Upload File';
    this.upImg = true;  // 是否图片上传
    this.baseUrl = '';
    this.preview = true;  // 是否预览

    // 事件定义
    this._events = {
        'start': [], // 上传开始
        'success': [], // 上传成功
        'error': [], // 上传失败
        'complete': [] // 上传完成
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
                <form class="upfile-form" target="${frameID}" action="${upUrl}" enctype="multipart/form-data" method="POST">\
                    <div class="upfile-div">\
                        ${upText}\
                        <input type="file" name="upfile" />\
                    </div>\
                </form>\
                <iframe name="${frameID}" id="${frameID}" hidden></iframe>\
           </div>';

    // 预览模板，单条
    var _viewTpl = //'<ul class="upfile-preview">\
            '<li id="${fileID}" path="${filePath}">\
                        <a class="filebox" href="${fileUrl}" target="_blank">${fileName}</a>\
                        <a class="button delete">&#8854;</a>\
                    </li>';
    //</ul>';

    /**
     * 初始化上传box
     */
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

    /**
     * 预览
     * @param {String} path 预览单张图片的路径
     */
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

    /**
     * 初始化预览box
     */
    var _initViewBox = function () {
        this._upView = v.$('<ul>');
        this._upView.className = 'upfile-preview';
        this._upBox.appendChild(this._upView);
        // 添加删除事件
        $(this._upView).on('click', '.delete', function (event) {
            var el = v.$p('li', event.currentTarget);
            !!el && this.remove(el.getAttribute('path'));
        }.bind(this));
        _preview.call(this);
    };

    /**
     * 开始上传
     */
    var _startUp = function () {
        if (!v.isNull(this._upFile.value)) {
            this._upForm.submit();
            this.triEvent('start', {
                target: this.anchor
            });
        }
    };

    /**
     * 上传成功
     */
    var _completeUp = function (rs, status) {
        var event = {
            target: this.anchor,
            status: status
        };
        if (status === 200) {
            this._upFile.value = '';
            // 只支持上传一个则先删除
            if (!this.upMulti && this._files.length > 0)
                this.remove(this._files[0]);

            // 取得url
            this._files.push(rs);
            this.anchor.value = this._files.join(',');
            event.imgurl = rs;
            this.preview && _preview.call(this, rs);
            this.triEvent('success', event);
        } else {
            // 发生错误
            event.error = rs;
            this.triEvent('error', event);
            // 有错误回调事件不提示错误消息
            if (this._events.error.length === 0 && this._events.complete.length === 0)
                v.alert ? v.alert(rs) : alert(rs);
        }
        this.triEvent('complete', event);
    };

    /**
     * 初始化
     */
    this.construct = function () {
        this.parent();

        // 初始化属性
        var file = this.anchor.value.replace(/\s*/, '');
        !!file && (this._files = file.split(','));

        // 初始化element
        _initUpBox.call(this);
        !this.preview || _initViewBox.call(this);

        // 文件上传结束回调
        $(this._upFrame).on('load', function () {
            this._upFrame.contentWindow.completeUp = _completeUp.bind(this);
        }.bind(this));

        // 数据变化时上传
        $(this._upFile).on('change', _startUp.bind(this));
    };

    /**
     * 删除文件,不会对文件实际删除
     * @param {String} path 文件的url
     */
    this.remove = function (path) {
        var pos = this._files.indexOf(path);
        if (pos > -1)
            this._files.splice(pos, 1);
        this.anchor.value = this._files.join(',');
        $('#upfile_' + v.strHash(path)).remove();
        return this;
    };
});
/**
 * Select Script
 * 多选联想输入插件
 * v-select="{}"
 */
v.Select || v.kit('Select', function (self) {
    // 静态属性
    self.anchor = 'v-select';
    // 公共属性
    //
    this.anchor = null; // 碎片框
    this.dataurl = null; // 取数据链接，为空则需要自定义listdata，为空时请定义datalist
    this.datalist = null; // 数据选择 select，选择器|数据|null
    this.multiple = false; // 是否多选
    this.labelField = 'name'; // 标签字段
    this.valueField = '_id'; // value字段

    // 事件定义
    this._events = {
        change: [] // 数据改变事件
    };

    // 私有属性
    this._input = null; // 输入框
    this._cacheLabel = {};  // 选择的数据 label=>value
    this._cacheValue = {};  // 选择数据 value=>label
    this._isShow = false; // 是否显示状态
    this._showTimer = null;  // 显示timer
    this._isDelall = true; // 是否删除所有

    // 生成option
    var _htmlOption = function (data) {
        v.$(this.datalist).empty();
        v.forEach(data, (text, val) => {
            v.$('<option>').attr({'value': val}).html(text).insertTo(this.datalist);
        });
    };

    // 同步value
    var _syncValue = function () {
        let vals = [];
        v.forEach(this._input.value.split(','), (val) => vals.push(this._cacheLabel[val]));
        this.anchor.value = vals.join(',');
        this.triEvent('change', {
            'target': this.anchor,
            'value': this.anchor.value
        });
    };

    // 同步label
    var _syncLabel = function () {
        let vals = [];
        v.forEach(this.anchor.value.split(','), (val) => vals.push(this._cacheValue[val] || val));
        this._input.value = vals.join(',');
    };

    // 选择
    var _selectItem = function () {
        if (this._isShow && this.datalist.selectedOptions.length > 0) {
            let item = this.datalist.selectedOptions[0];
            if ((',' + this.anchor.value + ',').indexOf(',' + item.value + ',') < 0) {
                this.multiple ? this._input.value += item.text + ',' : this._input.value = item.text;
                this._cacheLabel[item.text] = item.value;
                this._cacheValue[item.value] = item.text;
                _syncValue.call(this);
            }
            this.hide();
            this._isDelall = true;
        }
    };

    // 自动完成功能
    let _autoTimer = null;
    var _autoItem = function () {
        _autoTimer = v.callst(() => {
            let val = this._input.value;
            val = val.substr(val.lastIndexOf(',') + 1);
            v.xhr('GET', this.dataurl, {[this.labelField]: val, 'field': this.labelField + ',' + this.valueField}, (res) => {
                let data = res.data ? res.data : res, item = {};
                v.forEach(data, (row) => {
                    item[row[this.valueField]] = row[this.labelField];
                });
                _htmlOption.call(this, item);
            });
        }, _autoTimer, 100);
    };

    // 键盘事件响应
    var _keyEvent = function (event) {
        switch (event.keyCode) {
            case 13:  // 回车，完成选择
                _selectItem.call(this);
                event.preventDefault(); // 阻止回车默认事件
                break;
            case 38:  // 向上箭头
                !this._isShow || this.datalist.selectedIndex--;
                break;
            case 40:  // 向下箭头，显示选择
                !this._isShow ? this.show() : this.datalist.selectedIndex++;
                break;
            case 8: // Backspace 退格，每次删除即删除一个完整的碎片
            case 46:  // 删除键
                if (this._isDelall) {
                    let pos = this._input.selectionStart;
                    if (pos > 0) {
                        let val = this._input.value, sval = val.substr(0, pos), eval = val.substr(pos);
                        let spos = sval.lastIndexOf(',') + 1 || 0, epos = eval.indexOf(',') + 1 || 0;
                        this._input.value = sval.substr(0, spos) + eval.substr(epos);
                        _syncValue.call(this);
                    }
                    // 多选时避免删除键变成单个删除
                    setTimeout(() => this._isDelall = true, 100);
                }
        }
    };

    // 初始化
    this.construct = function () {
        this.parent();

        // 复制一个输入框，用户输入显示
        this._input = this.anchor.cloneNode();
        this.anchor.style.display = 'none';
        v.$(this._input).attr({name: null, 'v-select': null}).insertTo(this.anchor.parentNode, this.anchor);

        // listdata
        let data;
        if (this.datalist) {
            if (v.isObject(this.datalist)) {
                data = this.datalist;
                this.datalist = null;
                this._cacheValue = data;
            } else {
                this.datalist = v.$(this.datalist).original;
                // 缓存datalist的数据
                v.$s('option', this.datalist).forEach((item) => {
                    this._cacheValue[item.value] = item.text;
                });
            }
        }
        if (!this.datalist) {
            this.datalist = v.$('<select>').original;
            !data || _htmlOption.call(this, data);
        }
        v.$(this.datalist).insertTo(this.anchor.parentNode, this.anchor).attr({'multiple': 'multiple'})
                .css({'overflow': 'auto', 'position': 'absolute', 'width': v.$(this.anchor).client().width, 'height': 'auto'}).hide();

        this.reset();

        // 事件添加
        v.$(this._input).on('dblclick', this.show.bind(this))  // 双击显示
                .on('keydown', _keyEvent.bind(this))  // 输入变化
                .on('blur', this.hide.bind(this));  // 无焦点隐藏

        v.$(this.datalist).on('change', _selectItem.bind(this));  // 点击确认选择
        
        // 自动完成
        !this.dataurl || v.$(this._input).on('input', (event) => {
            this._isDelall = false;  // 输入时候不能全部删除
            _autoItem.call(this);
            !this._input.value || this.show(); // 无类容不显示
        });
        
        // 监控值的变化
        v.$(this.anchor).watch('value', () => {
            this.reset();
        });
    };

    // 显示数据
    this.show = function () {
        this._showTimer = v.callst(() => {
            if (!this._isShow) {
                let xy = v.$(this._input).offset();
                v.$(this.datalist).css({'left': xy.left, 'top': xy.top + xy.height});
                if (this.dataurl)
                    _autoItem.call(this);
                this._isShow = true;
                v.$(this.datalist).show();
            }
        }, this._showTimer, 100);
    };

    // 隐藏
    this.hide = function () {
        this._showTimer = v.callst(() => {
            if (this._isShow) {
                this._isShow = false;
                v.$(this.datalist).hide();
                v.$(this._input).focus();
            }
        }, this._showTimer, 100);
    };

    // 重新初始化控件
    this.reset = function () {
        this._isDelall = true;
        if (!this.dataurl) {
            _syncLabel.call(this);
        } else {
            let ids = [];
            v.forEach(this.anchor.value.split(','), val => {
                if (!v.isNull(val))
                    this._cacheValue[val] || ids.push(val);
            });
            if (ids.length <= 0) {
                _syncLabel.call(this);
            } else {
                v.xhr('GET', this.dataurl, {'ids': ids.join(',')}, (res) => {
                    let data = res.data ? res.data : res;
                    v.forEach(data, (row) => {
                        this._cacheValue[row[this.valueField]] = row[this.labelField];
                        this._cacheLabel[row[this.labelField]] = row[this.valueField];
                    });
                    _syncLabel.call(this);
                });
            }
        }
    };

});
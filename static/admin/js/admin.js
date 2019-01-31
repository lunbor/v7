/**
 * 管理模型
 * 所有管理模型由此继承
 */
v.AdminModel || (v.AdminModel = v.classd(v.Model, function (self) {
    // 公有变量
    this.dataUrl = ''; // restful数据url
    this.rowNum = 20; // 每页条数

    //
    // 组件元素样式
    this.filter = '.list-filter';  // 过滤选择
    this.table = '.list-table'; // 列表选择
    this.paging = '.list-paging';  // 分页选择
    this.form = '.edit-form';  // 表单选择
    //
    // 按钮样式
    this.editBtn = '.edit-btn';
    this.delBtn = '.del-btn';
    this.pageEl = '.total-page';
    this.pageCountEl = '.total-page-count';

    // 行选中样式
    this.selected = 'list-selected';

    // 私有变量
    this._editid = null; // 当前数据ID
    this._rowtpl = null;  // 行模板
    this._odata = {}; // 旧数据
    this._onlyView = false; // 是否只读
    this._curPage = 1; // 当前页码
    this._selectedRow = null;  // 当前选择行
    this._fixedData = {};  // 固定参数
    this._submitBtn = null;  // 保存提交按钮
    this._item = null; // 当前渲染行

    this._events = {
        'reload': [], // 列表数据被载入
        'revalue': [] // dom节点重新赋值事件
    };

    // 初始化
    this.construct = function () {
        this.parent();
        this.filter = v.$(this.filter);
        this.table = v.$(this.table);
        this.paging = v.$(this.paging);
        this.form = v.$(this.form);

        // 列表处理
        if (this.table.original) {
            this._rowtpl = v.str2Dom(this.table.$('thead').html().replace(/th /g, 'td '));
            v.$s('td', this._rowtpl).forEach(function (el) {
                if (v.$(el).attr('field'))  // 含field的列置为空
                    el.innerHTML = '';
            });
            // 全选控制，全选必须为checkbox，并且位于第一列
            v.$('thead th', this.table).$('input[type="checkbox"]').attr({'checked': true, 'disabled': true}).on('click', (event) => {
                this.checked('all', event.target.checked);
            });
            // 编辑与删除按钮事件处理
            v.$('tbody', this.table).on('click', (event) => {
                let tr = v.$(event.target).$p('tr');
                if (tr) {
                    this._editid || this.selectRow(tr);
                    let el = event.target;
                    if (el.type === 'checkbox') {
                        !this._editid || this.editRow(tr);
                    } else if (el = v.$(event.target).$p(this.editBtn, tr)) {
                        // 编辑
                        this.editRow(tr);
                    } else if (el = v.$(event.target).$p(this.delBtn, tr)) {
                        // 删除，延迟执行，防止弹窗时候未选择
                        v.callst(() => {
                            this.delRow(tr);
                        });
                    }
                }
            });
            this.reload(); // 取得列表
            // 分页
            if (this.paging.original) {
                this.paging.on('click', 'a', (event) => {
                    let el = v.$(event.srcTarget),
                            page = el.attr('page');
                    if (page && page != this._curPage)
                        this.reload(page);
                });
            }
        }
        // 表单处理
        if (this.form.original) {
            // 添加数据保存事件
            let form = this.form.nodeName === 'FORM' ? this.form : v.$('form', this.form);
            form.on('submit', (event) => {
                event.preventDefault();
                event.stopPropagation();
                this._saveData();
            });
            // 固定数据保存
            v.$s('[data-fixed]', form).forEach(el => {
                this._fixedData[el.name] = el.value;
            });
            // 值变化监控
            v.$s('input[type=text]', form).forEach(el => {
                v.$(el).watch('value', () => {
                    setHasValue(el);
                });
            });
            this._submitBtn = v.$('button[type="submit"]', form);
        }

        this.trans({'zh-cn': {
                'Are you sure delete selected row ?': '你确定要删除该行数据么？'
            }});
    };
    // 时间戳转日期
    this.time2str = function (val/*, fmt*/) {
        return val ? v.time2str(...arguments) : '';
    };
    // 格式化IP，整型转字符
    this.long2ip = function (val) {
        return val ? ((val >> 24) & 0xff) + '.' + ((val >> 16) & 0xff) + '.' + ((val >> 8) & 0xff) + '.' + (val & 0xff) : '';
    };
    // 数组转字符串
    this.array2str = function (val) {
        return val ? val.join(', ') : '';
    };
    // 数字赚钱币
    this.number2money = function (val) {
        if (val) {
            let pos = val.toString().indexOf('.');
            return '￥' + (pos > 0 ? (val + '00').substr(0, pos + 3) : val + '.00');
        }
        return '';
    };
    // 图片地址转图片
    this.src2img = function (val, base = '/', height = 48) {
        if (v.isNumber(base))
            [base, height] = [height, base];
        if (v.isArray(val))
            val = val[0];
        let src = (base + val).replace(/\/\//g, '/');
        return v.$('<img>').attr({'src': src}).css({'height': height, 'width': 'auto'}).original;
    };
    // key根据json转换成字符
    this.key2text = function (value, obj) {
        if (v.isNumber(value))
            return obj[value] || value;
        let vals = v.isString(value) ? value.split(',') : value, val = '';
        vals.forEach((t) => {
            val += (obj[t] || t) + ', ';
        });
        return val.substr(0, val.length - 2);
    };
    // 删除数据,多条，有确认提示
    this.delRow = function (row) {
        if (this.table.original && !this._onlyView) {
            v.confirm(this.trans('Are you sure delete selected row ?'), () => {
                let id = v.isArray(row) ? row.join(',') : v.isObject(row) ? v.$(row).attr('_id') : row;
                v.xhr('DELETE', this.dataUrl, v.extend({id: id}, this._fixedData), {
                    'success': () => {
                        v.str2Array(id).forEach((id) => {
                            this.remove(id);
                        });
                    },
                    'error': (res) => {
                        v.alert(v.firstVal(res.message));
                    }
                });
            });
        }
    };
    // 编辑数据
    this.editRow = function (row) {
        if (this.form.original) {
            let id = v.isObject(row) ? v.$(row).attr('_id') : row;
            v.xhr('GET', this.dataUrl, {id: id}, {
                'success': (res) => {
                    this._editid = id;
                    this._valueForm(res);
                    this.selectRow(id);
                },
                'error': (res) => {
                    v.alert(v.firstVal(res.message));
                }
            });
            this.showEdit();
        }
    };
    // 添加数据
    this.addNew = function () {
        if (this.form.original && !this._onlyView) {
            this._editid = null;
            this._valueForm({});
            this.showEdit();
            this.selectRow();
        }
    };
    // 只允许查看，false可允许管理
    this.onlyView = function (bol) {
        this._onlyView = bol;
        let els = this.form.original ? v.$s('*', this.form) : [];
        els = els.concat(v.$s('tbody *', this.table));
        els.forEach((el) => {
            if ('button|submit|checkbox|radio|select-one'.indexOf(el.type) > -1) {
                el.disabled = bol;
            } else if (el.name) {
                el.readOnly = bol;
            }
        });
        // 允许选择
        v.$s('tbody input[type="checkbox"]', this.table).forEach(el => el.disabled = false);
    };
    // 选中一行，为空去除选中，选中不代表当前编辑数据
    this.selectRow = function (row) {
        if (this._selectedRow) {
            v.$('input[type="checkbox"]', this._selectedRow).checked = false;
            v.$(this._selectedRow).rmvClass(this.selected);
            this._selectedRow = null;
        }
        if (row) {
            row = v.isString(row) ? v.$('tbody tr[_id="' + this._editid + '"]', this.table) : v.$(row);
            row.addClass(this.selected);
            v.$('input[type="checkbox"]', row).checked = true;
            this._selectedRow = row;
        }
    };
    // 显示编辑
    this.showEdit = function () {
        v.$(this.anchor).addClass('bigger');
    };
    // 隐藏编辑
    this.hideEdit = function () {
        this._editid = null;
        v.$(this.anchor).rmvClass('bigger');
    };
    // 选择ID行，all表示全选
    this.checked = function (id, checked = true) {
        if (this.table.original) {
            if (arguments.length < 1) {
                // 取得选择
                let el, chked = [];
                v.$s('tbody tr', this.table).forEach((tr) => {
                    el = v.$('td', tr).$('[type=checkbox]');
                    !el.checked || chked.push(el.value);
                });
                return chked.join(',');
            } else if (id === 'all') {
                // 选择所有
                v.$s('tbody tr', this.table).forEach((tr) => {
                    v.$('td', tr).$('[type=checkbox]').attr('checked', checked);
                });
            } else {
                // 单选
                v.$('tbody tr[_id="' + id + '"] td', this.table).$('[type=checkbox]').attr('checked', checked);
            }
        } else if (arguments.length < 1) {
            return '';
        }
        return this;
    };
    // 重新载入数据，默认载入第一页
    this.reload = function (page = 1) {
        if (this.table.original) {
            v.isNumber(page) || v.isString(page) || (page = 1);
            let param = this.filter.original ? v.filterNull(v.domJson(this.filter.original)) : {};
            page = parseInt(page);
            param.page = page;
            param.row = this.rowNum;
            startLoading();
            v.xhr('GET', this.dataUrl, param, {
                'complete': (res) => {
                    endLoading();
                },
                'success': (res) => {
                    this._curPage = page;
                    this._drawList(res.data);
                    this._drawPaging(res.count, page);
                    !this._onlyView || this.onlyView(this._onlyView);
                    this.triEvent('reload', res);
                },
                'error': (res) => {
                    let err = res['message'] || res;
                    this._drawList(v.isString(err) ? err : v.firstVal(err));
                }
            });
        }
        return this;
    };
    // 更新一行，按id或者行node
    this.update = function (item, id) {
        if (this.table.original) {
            let row = v.isString(id) ? v.$('tbody tr[_id="' + id + '"]', this.table) : id;
            v.$s('td', row).forEach((el) => {
                let fmt, value, field;
                el = v.$(el);
                if ((field = el.attr('field')) && (field in item)) {
                    value = item[field];
                    this._item = item;
                    if (fmt = el.attr('format')) {
                        // 允许带格式化参数
                        let [fn, args] = this.fnargs(fmt, value);
                        value = this.bubble(fn)(...args);
                    }
                    el.html(value);
                }
            });
        }
        return this;
    };
    // 添加一行，可定义位于底部或者尾部
    this.insert = function (item, first = null) {
        if (this.table.original) {
            v.$('.error-not-found', this.table.original).remove();  // 删除404错误提示消息
            let row = this._rowtpl.cloneNode(true);
            v.$('td', row).$('[type=checkbox]').val(item['_id']);  // 第一列为全选，赋值_id
            v.$(row).attr({'_id': item['_id']});
            this.update(item, row);
            v.$('tbody', this.table).insertIn(row, first);
        }
        return this;
    };
    // 删除一行，按id或者行node
    this.remove = function (id) {
        if (this.table.original) {
            let row = v.isString(id) ? v.$('tr[_id="' + id + '"]', this.table) : id;
            row.remove();
        }
        return this;
    };
    this.action = function (action, param, callback, btn) {
        this._lockSave(true, btn);
        v.extend(param, this._fixedData);
        let pos = this.dataUrl.lastIndexOf('.'), url = this.dataUrl.substr(0, pos) + '/' + action + this.dataUrl.substr(pos);
        v.xhr('POST', url, param, {
            complete: () => {
                this._lockSave(false, btn);
            },
            success: (res) => {
                callback.call(this, res);
            },
            error: (res) => {
                v.alert(v.firstVal(res.message));
            }
        });
    };
    // 绘制列表
    this._drawList = function (data) {
        if (v.isString(data)) {
            // 字符格式消息
            v.$('tbody', this.table).html(
                    '<tr class="error-not-found"><td colspan="99">' + data + '</td></tr>'
                    );
        } else {
            // 列表数据
            v.$('tbody', this.table).html('');
            v.forEach(data, (item) => {
                this.insert(item);
            });
        }
    };
    // 绘制分页
    this._drawPaging = function (count, page) {
        if (this.paging.original) {
            let allPage = Math.ceil(count / this.rowNum),
                    paging = [
                        Math.max(page - 1, 1), // 前一页
                        page > 1 ? Math.max(page - 5, 1) : 0, // 前5页
                        page,
                        page < allPage ? Math.min(page + 5, allPage) : 0, // 后5页
                        Math.min(page + 1, allPage)  // 后一页
                    ];
            v.$(this.pageEl, this.paging).html(allPage);
            v.$(this.pageCountEl, this.paging).html(count);
            // 绘制，第三个作为模板，前两个和最后两个固定，先确定好数量再隐藏使用
            let els = v.$s('a', this.paging);
            els.forEach((el, i) => {
                v.$(el).attr({'page': paging[i]})
                        .css({'display': paging[i] === 0 ? 'none' : 'inline'});
            });
            v.$(els[2]).html(paging[2]);  // 当前页
        }
    };
    // 锁定按钮
    this._lockSave = function (status, btn) {
        if (!v.isBoolean(status))
            [status, btn] = [btn, status];
        btn = btn ? v.$(btn) : this._submitBtn;
        if (status) {
            startLoading();
            !btn || btn.attr('disabled', true);
            v.callst(() => {  // 10秒后自动解锁
                !btn || btn.attr('disabled', null);
            }, 10000);
        } else {
            endLoading();
            !btn || btn.attr('disabled', null);
        }
    };
    // 保存数据
    this._saveData = function () {
        if (this.form.original && !this._onlyView) {
            let form = this.form.nodeName === 'FORM' ? this.form : v.$('form', this.form),
                    valider = v.Validity.obj(form.original);
            if (valider.checkValid()) {
                let data = this._valueForm();
                if (!v.isEmpty(data)) {
                    v.isEmpty(this._fixedData) || v.extend(data, this._fixedData);
                    data = this._formData(data);
                    let editid = this._editid, method = editid ? 'PUT' : 'POST',
                            url = editid ? v.urlQrs({id: editid}, this.dataUrl) : this.dataUrl;
                    this._lockSave(true);
                    v.xhr(method, url, data, {
                        reqType: 'blob',
                        complete: () => {
                            this._lockSave(false);
                        },
                        success: (res) => {
                            if (this._editid === editid) {  // 防止数据返回后，已经被切换
                                this._valueForm(res);
                                if (this._editid) {
                                    this.update(res, this._editid);
                                } else {  // 添加成功后转为编辑状态
                                    this.insert(res, true);
                                    !res['_id'] || (this._editid = res['_id']);
                                }
                                let msg = '数据保存成功！';
                                showMessage(msg);
                                v.$('[for="' + this.form.attr('name') + '"]', this.form).html(msg);
                            }
                        },
                        error: (res) => {
                            if (this._editid === editid) {  // 防止数据返回后，已经被切换
                                v.forEach(res.message, (value, field) => {
                                    field === '*' ? valider.setValid(value) : valider.setValid(value, '[name=' + field + ']');
                                });
                            }
                        }
                    });
                }
            }
        }
    };
    // 取得表单formdata与上传文件数据
    this._formData = function (data) {
        let fdata = new FormData();
        v.$s('input[type="file"]', this.form).forEach(el => {
            let ep = v.$(el).$p('[v-upfile]', this.form), name = v.$(el).attr('name');
            !ep || v.Upfile.obj(ep.original).files().forEach((file, i) => fdata.append(name + '[' + i + ']', file));
            !(name in data) || delete data[name];  // 删除原上传控件的值
        });
        data = v.flatJson(data);
        v.forEach(data, (val, k) => fdata.append(k, val));
        return fdata;
    };
    // 给form元素赋值
    this._valueForm = function (/*data*/) {
        if (arguments.length > 0) {
            // 赋值
            this._odata = arguments[0];
            v.domJson(this.form, this._odata, true);
            // 格式化数据
            v.$s('input[format]', this.form).forEach((el) => {
                if (!v.isNull(el.value)) {
                    // 允许带格式化参数
                    let [fn, args] = this.fnargs(v.$(el).attr('format'), el.value);
                    el.value = this.bubble(fn)(...args);
                }
            });
            v.Validity.obj(this.form.original).cleanError();  // 清除所有错误
            this.triEvent('revalue', this._odata);
            reHasValue(this.form);
        } else {
            // 取值
            let data = v.domJson(this.form, true);
            v.filterSame(data, this._odata);
            // 空数据的删除，必须老数据也为空
            v.forEach(data, (val, key) => {
                if (v.isNull(val) && v.isNull(this._odata[key]))
                    delete data[key];
            });
            v.filterKey(data, this._fixedData);
            return data;
        }
    };

}));
/* 
 * 管理员公共JS
 */
v.ready(function () {
    // 判断元素是否有值
    let setHasValue = function (el) {
        el = v.$(el);
        let pl = el.$p('.input-group');
        if (pl) {
            if (el.val())
                pl.addClass('has-value');
            else
                pl.rmvClass('has-value');
        }
    };
    window.setHasValue = setHasValue;
    // input输入样式
    v.$('doc').on('focusout change', function (event) {
        let el = v.$(event.target);
        if (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA' || el.tagName === 'SELECT') {
            setHasValue(el);
        }
    });
    // 设置dom是否有值
    window.reHasValue = function (event) {
        let ctx = event.target || event;
        v.$s('input, textarea, select', ctx).forEach(el => setHasValue(el));
    };
    // 菜单
    let currentDropMenu = v.$('.container-left li.subdrop');
    let dropMenu = function (el) {
        // 关闭原菜单
        if (currentDropMenu.original) {
            currentDropMenu.rmvClass('subdrop');
            v.$('ul', currentDropMenu).hide({'height': 0}, function () {
                v.$(this).css({'height': ''});
            });
        }
        // 展开菜单
        if (currentDropMenu !== el) {
            currentDropMenu = el;
            let ul = v.$('ul', el), height = ul.client().height;
            ul.css({'height': 0}).show({'height': height});
            el.addClass('subdrop');
        } else {
            currentDropMenu = v.$(null);
        }
    };
    v.$('.container-left .menu').on('click', 'li', function (event) {
        let el = v.$(event.srcTarget);
        if (el.hasClass('has-sub')) {
            dropMenu(el);
        }
    });
    // 激活菜单
    var activeMenu = function (link) {
        link || (link = location.pathname);
        let el = v.$('.container-left .menu a[href="' + link + '"]');
        if (el.original) {
            el.addClass('linking');
            let pMenu = el.$p('.has-sub', v.$('.container-left'));
            !pMenu || dropMenu(v.$(pMenu));
        }
    };
    activeMenu();

    // 登陆用户头像
    v.$('header .user').on('mouseenter', () => v.$('header .user .dropdown-menu').show())
            .on('mouseleave', () => v.$('header .user .dropdown-menu').hide());

    let elLoading = v.$('.progress').rmvClass('progress'), elMessage = v.$('.message').rmvClass('message'), smTimer = null;
    // 开始载入
    window.startLoading = function () {
        elLoading.show('progress');
    };
    // 结束载入
    window.endLoading = function () {
        elLoading.hide();
    };
    // 显示消息
    window.showMessage = function (message) {
        smTimer = v.callst(function () {
            elMessage.html(message).show('message', elMessage.hide);
        }, smTimer);
    };

});



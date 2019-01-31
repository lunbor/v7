/**
 * Datepick Script
 * 日历选择插件
 * v-datepick="{}"
 */
v.load('datepick.css');
v.Datepick || v.kit('Datepick', function (self) {
    // 静态属性
    self.anchor = 'v-datepick';
    // 公共属性
    //
    this.anchor = null; // 对话框
    this.tribox = null; // 触发box，包含anchor的dom，点击该dom内的区域会弹出日历
    this.aspect = 'auto'; // 方向
    this.margin = 0; // 边距
    this.start = null; // 允许选择开始时间
    this.end = null; // 允许选择结束时间
    this.format = 'Y/m/d'; // 格式化时间 Y m d H i s

    // 事件定义
    this._events = {
        select: [] // 选中
    };

    this._viewState = 0; // 视图状态  1分钟选择 2时间选择 3日期选择 4月份选择 5年份选择
    this._selectType = 2;  // 当前选择状态
    this._weeks = ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'];
    this._minutes = ['0', '5', '10', '15', '20', '25', '30', '35', '40', '45', '50', '55'];
    this._hours = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12', '13', '14', '15', '16', '17', '18', '19', '20', '21', '22', '23'];
    this._days = []; // 42个数，日月
    for (var i = 0; i < 42; i++)
        this._days[i] = '';
    this._months = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12'];
    this._years = [];
    for (var i = 0; i < 24; i++)
        this._years[i] = '';
    this._date = new Date();
    this._date1 = new Date();
    this._box = null;

    // 模板
    this._tpl = '<div class="datepick" style="position:absolute;" hidden>\
                <div class="hander"><a class="switch" data-value="0"></a><span class="arrow"><a class="switch-prev" data-value="-1">&lt;</a><a class="switch-next" data-value="1">&gt;</a></span></div>\
                <div class="time" hidden>\
                    <div class="hour"><% for (let val of hours){ %><a data-value="<%=val%>"><%=val%>:00</a><% } %></div>\
                    <div class="minute"><% for (let val of minutes){ %><a data-value="<%=val%>">0:<%=val%></a><% } %></div>\
                </div>\
                <div class="days">\
                    <div class="week"><% for (let val of weeks){ %><a><%=val%></a><% } %></div>\
                    <div class="day"><% for (let val of days){ %><a data-value="<%=val%>"><%=val%></a><% } %></div>\
                </div>\
                <div class="years" hidden>\
                    <div class="year"><% for (let val of years){ %><a data-value="<%=val%>"><%=val%></a><% } %></div>\
                    <div class="month"><% for (let val of months){ %><a data-value="<%=val%>"><%=val%><%=monthText%></a><% } %></div>\
                </div>\
           </div>';

    // 初始化
    this.construct = function () {
        this.parent();
        this._weeks = this.trans(this._weeks);

        if (this.anchor.value)
            this.setDate(this.anchor.value);
        let tpl = v.strParse(this._tpl,
                {'minutes': this._minutes, 'hours': this._hours, 'days': this._days, 'weeks': this._weeks, 'months': this._months, 'years': this._years, 'monthText': this.trans('Month')});
        this._box = v.str2Dom(tpl);
        this.anchor.parentNode.appendChild(this._box);
        let pos = v.$(this.anchor).offset();
        v.$(this._box).css({left: pos.left, top: pos.top + pos.height});

        this._selectType = this.format.indexOf('i') > 0 ? 1 : this.format.indexOf('H') > 0 ? 2 : this.format.indexOf('d') > 0 ? 3 : this.format.indexOf('m') > 0 ? 4 : 5;
        this.switchView(this._selectType);

        this.tribox = this.tribox ? v.$(this.tribox).original : this.anchor;

        // 事件添加
        v.$(this.tribox).on('click', this.show.bind(this));
        v.$(this._box).on('click', (event) => {
            event.stopPropagation();
            let tar = v.$(event.target);
            if (tar.nodeName === 'A') {
                let parent = v.$(tar.parentNode), val = tar.attr('data-value');
                switch (parent.className) {
                    case 'hander':
                        this.switchView(val);
                        break;
                    case 'arrow':
                        !val || this.switchStep(val);
                        break;
                    case 'minute':
                        this.selectMinute(val);
                        break;
                    case 'hour':
                        this.selectHour(val);
                        break;
                    case 'day':
                        let month = tar.hasClass('prev-month') ? -1 : (tar.hasClass('next-month') ? 1 : 0);
                        this.selectDay(val, month);
                        break;
                    case 'month':
                        this.selectMonth(val);
                        break;
                    case 'year':
                        this.selectYear(val);
                        break;
                }
            }
        });
    };
    // 格式化天
    this.formatDay = function (time) {
        this._date1.setTime(time);
        return this._date1.getDate();
    };
    // 设置日期
    this.setDate = function (str) {
        var vals = str.replace(/[^\d\s:]+/g, '/').replace(/^\/+|\/+$/g, '').replace(/\/+\s+/, ' ').split('/');
        this._date.setFullYear(vals[0], (vals[1] || 1) - 1, vals[2] || 1);
        this._date.setHours(vals[3] || 0, vals[4] || 0);
    };
    // 设置视图
    this.setView = function () {
        let dis = {time: 1, days: 1, years: 1}, cur = 'time';
        if (this._viewState === 3) {
            let date = new Date(this._date.getTime());
            date.setDate(1);
            var week = date.getDay(), stime = date.getTime();
            if (week === 0)
                week = 7;
            for (var i = 1; i <= 42; i++) {
                this._days[i - 1] = this.formatDay(stime + 86400000 * (i - week));
            }
            let els = v.$s('.days .day a', this._box);
            els.forEach((el, i) => v.$(el).attr({'data-value': this._days[i]}).rmvClass('prev-month next-month').html(this._days[i]));
            // 前一月样式
            for (let i = 0; i < 15; i++) {
                if (this._days[i] < 15)
                    break;
                v.$(els[i]).addClass('prev-month');
            }
            // 后一月样式
            for (let i = 41; i > 25; i--) {
                if (this._days[i] > 15)
                    break;
                v.$(els[i]).addClass('next-month');
            }
            cur = 'days';
        } else if (this._viewState > 3) {
            let y = Math.floor((this._date.getFullYear() - 1970) / 24) * 24 + 1970, i = 24;
            while (i--) {
                this._years[i] = y + i;
            }
            v.$s('.years .year a', this._box).forEach((el, i) => v.$(el).attr({'data-value': this._years[i]}).html(this._years[i]));
            cur = 'years';
        }
        delete dis[cur];
        v.forEach(dis, (val, cls) => v.$('.' + cls, this._box).hide());
        v.$('.' + cur, this._box).show();
    };
    // 视图切换
    this.switchView = function (state) {
        state = parseInt(state);
        if (state !== this._viewState) {
            if (state === 0) {
                this._viewState = this._viewState < 3 ? 3 : this._viewState === 3 ? 5 : this._selectType;
            } else {
                this._viewState = state;
            }
            this.setView();
            this.setTitle();
        }
    };
    // 分步凑选择
    this.switchStep = function (num) {
        num = parseInt(num);
        if (this._viewState < 3) {
            // 位于时间界面，切换日期
            this._date.setTime(this._date.getTime() + num * 86400000);
        } else if (this._viewState === 3) {
            // 位于日期界面，切换月
            let month = this._date.getMonth() + num;
            if (month > 11) {
                this._date.setFullYear(this._date.getFullYear() + 1, month - 12);
            } else if (month < 0) {
                this._date.setFullYear(this._date.getFullYear() - 1, 12 + month);
            } else {
                this._date.setMonth(month);
            }
            this.setView();
        } else {
            // 位于年月界面，切换年
            let year = Math.max(this._date.getFullYear() + (24 * num), 1970);
            this._date.setFullYear(year);
            this.setView();
        }
        this.setTitle();
    };
    // 设置选中
    this.setSelected = function () {
        v.$s('.selected', this._box).forEach(el => v.$(el).rmvClass('selected'));
        let els = [];
        if (this._viewState < 3) {
            els.push('.minute [data-value="' + this._date.getMinutes() + '"]');
            els.push('.hour [data-value="' + this._date.getHours() + '"]');
        } else if (this._viewState === 3) {
            els.push('.day [data-value="' + this._date.getDate() + '"]');
        } else {
            els.push('.year [data-value="' + this._date.getFullYear() + '"]');
            els.push('.month [data-value="' + this._date.getMonth() + '"]');
        }
        els.forEach(el => v.$(el, this._box).addClass('selected'));
    };
    // 设置标题
    this.setTitle = function () {
        let title = '';
        if (this._viewState <= 5)
            title += this._date.getFullYear() + this.trans('Year');
        if (this._viewState <= 4)
            title += (this._date.getMonth() + 1) + this.trans('Month');
        if (this._viewState <= 3)
            title += this._date.getDate() + this.trans('Day');
        if (this._viewState <= 2)
            title += this._date.getHours() + this.trans('Hour');
        if (this._viewState <= 1)
            title += this._date.getMinutes() + this.trans('Minute');
        v.$('.switch', this._box).html(title);
        this.setSelected();
    };
    // 确定选择
    this.selectOK = function (type) {
        if (this._selectType === type) {
            // 选中
            v.$(this.anchor).value = v.time2str(this._date.getTime() / 1000, this.format);
            this.close();
        } else if (this._selectType < type) {
            this.switchView(type - 1);
        }
        this.setTitle();
        this.triEvent('selected');
    };
    // 选择分
    this.selectMinute = function (minute) {
        this._date.setMinutes(minute);
        this.selectOK(1);
    };
    // 选择小时
    this.selectHour = function (hour) {
        this._date.setHours(hour);
        this.selectOK(2);
    };
    // 选择天
    this.selectDay = function (day, month) {
        this._date.setDate(day);
        if (month) {
            month = this._date.getMonth() + month;
            this._date.setMonth(month);
        }
        this.selectOK(3);
    };
    // 选择月
    this.selectMonth = function (month) {
        this._date.setMonth(parseInt(month) - 1);
        this.selectOK(4);
    };
    // 选择年
    this.selectYear = function (year) {
        this._date.setFullYear(year);
        this.selectOK(5);
    };
    // 自动关闭
    this.autoHide = function (event) {
        let tar = event.target;
        if (!this.tribox.contains(tar)) {
            this.close();
            v.$('body').off('click', v.bind(this.autoHide, this));
        }
    };
    // 显示
    this.show = function () {
        v.$(this._box).show();
        v.$('body').on('click', v.bind(this.autoHide, this));
    };
    // 关闭
    this.close = function () {
        setTimeout(() => {
            v.$(this._box).hide();
        }, 13);
    };

    // 语言设置，不同国家的语言请覆盖此类
    self.trans({
        'zh-cn': {
            'Mo': '一',
            'Tu': '二',
            'We': '三',
            'Th': '四',
            'Fr': '五',
            'Sa': '六',
            'Su': '日',
            'Year': '年',
            'Month': '月',
            'Day': '日',
            'Hour': '时',
            'Minute': '分'
        }});
});
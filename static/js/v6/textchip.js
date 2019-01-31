/**
 * Textchip Script
 * 碎片输入插件
 * v-textchip="{}"
 */
v.Textchip || v.kit('Textchip', function (self) {
    // 静态属性
    self.anchor = 'v-textchip';
    // 公共属性
    //
    this.anchor = null; // 碎片框

    this._isDelall = true; // 是否删除所有

    // 初始化
    this.construct = function () {
        this.parent();
        v.$(this.anchor).on('keydown', (event) => {
            switch (event.keyCode) {
                case 13:  // Enter 回车视为输入完成一个碎片
                    event.preventDefault();
                    event.stopPropagation();
                    this.anchor.value += ',';
                    this._isDelall = true;
                    break;
                case 8: // Backspace 退格，每次删除即删除一个完整的碎片
                case 46:  // 删除键
                    if (this._isDelall) {
                        let pos = this.anchor.selectionStart;
                        if (pos > 0) {
                            let val = this.anchor.value, sval = val.substr(0, pos), eval = val.substr(pos);
                            let spos = sval.lastIndexOf(',') + 1 || 0, epos = eval.indexOf(',') + 1 || 0;
                            this.anchor.value = sval.substr(0, spos) + eval.substr(epos);
                        }
                        // 多选时避免删除键变成单个删除
                        setTimeout(() => this._isDelall = true, 100);
                    }
            }
        }).on('input', () => {
            this._isDelall = false;  // 输入时候不能全部删除
        }).watch('value', this.reset.bind(this)); // 值变化

    };

    // 重设
    this.reset = function () {
        this._isDelall = true;
    };
});
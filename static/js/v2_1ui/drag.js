/**
 * DragDrop Script
 * 拖到插件
 * data-dragdrop="v2ui"
 */

v.Drag || (function () {
    v.Drag = v.extend(v.UI, function (self) {
        // 静态属性
        self.anchor = 'data-drag';

        // 属性
        this.hold = false; // 是否原对象不变
        this.dragtar = null; // 拖动目标对象
        this.droptar = null; // 停靠目标对象

        // 事件定义
        this._events = {
            dragstart: [], // 开始拖动事件 
            draging: [],  // 拖动过程事件
            dragend: []  // 完成拖动事件
        };

        // 私有公共属性
        var _dragtar = null;  // 被拖动的对象
        var _dragpos = {  // 开始拖动坐标
            left: 0,
            top: 0,
            x: 0, 
            y: 0
        };
        var _boxpos = {  // 活动框
            left: 0,
            top: 0,
            right: 0,
            bottom: 0
        };
        
        /**
         * 取得节点坐标
         */
        var _offsetNode = function (el) {
            var offset = $(el).offset();
            offset.width = $(el).width();
            offset.height = $(el).height();
            offset.bottom = offset.top + offset.height;
            offset.right = offset.left + offset.width;
            return offset;
        };

        /**
         * 开始拖动
         */
        var _dragstart = function (event) {
            if (this.hold) { // 保持原对象不动
                _dragtar = $(this.anchor.cloneNode(true)).css({
                    'position': 'absolute',
                    'width': $(this.anchor).width(),
                    'height': $(this.anchor).height()
                }).appendTo(document.body).get(0);
            } else { // 直接手动原对象
                _dragtar = this.anchor;
                if ('fixed|absolute'.indexOf($(_dragtar).css('position')) === -1)
                    $(_dragtar).css({'position':'absolute', 'zIndex':9999});
            }
            // 开始拖动位置
            _dragpos = $(_dragtar).css('cursor', 'move').offset();
            _dragpos.x = event.clientX - _dragpos.left;
            _dragpos.y = event.clientY - _dragpos.top;
            
            // 框位置
            _boxpos = $(this.tribox).offset();
            _boxpos.right = $(this.tribox).width() + _boxpos.left - $(_dragtar).width();
            _boxpos.bottom = $(this.tribox).height() + _boxpos.top - $(_dragtar).height();
            
            this.triEvent('dragstart');
        };
        /**
         * 拖动过程
         */
        var _draging = function (event) {
            if (_dragtar) {
                window.getSelection ? window.getSelection().removeAllRanges() : document.selection.empty();
                $(_dragtar).css({
                    'left': Math.max(_boxpos.left, Math.min(_boxpos.right, event.clientX - _dragpos.x)),
                    'top': Math.max(_boxpos.top, Math.min(_boxpos.bottom, event.clientY - _dragpos.y))
                });
                
                this.triEvent('draging');
            }
        };
        
        /**
         * 停止拖动
         */
        var _dragend = function (event) {
            if (_dragtar) {
                if (this.droptar) {
                    // 停靠对象
                    var droppos = _offsetNode(this.droptar)/*, dragpos = _offsetNode(_dragtar)*/;
                    if (event.clientY > droppos.top && event.clientY < droppos.bottom && event.clientX > droppos.left && event.clientX < droppos.right) {
                    //if (dragpos.top < droppos.bottom && dragpos.bottom > droppos.top && dragpos.left < droppos.right && dragpos.right > droppos.left) {
                        // 位于drop停靠对象上
                        this.triEvent('dragend', {
                            'target': this.anchor,
                            'related': this.droptar
                        });
                    } else {
                        // 不在停靠对象上不移动位置
                        $(_dragtar).animate(_dragpos);
                        !this.hold || $(_dragtar).remove();
                    }
                } else {
                    // 不停靠对象
                    this.triEvent('dragend', {
                        'target': this.anchor
                    });
                }
                _dragtar = null;
            }
        };

        // 公有方法
        // 
        // 初始化
        this.construct = function () {
            this.parent();
            if (!this.tribox || this.tribox === this.anchor)
                this.tribox = document.body;  // 默认可拖动的区域为body
            if (this.droptar)
                this.droptar = v.$(this.droptar, this.tribox);
            $(this.anchor).on('mousedown', this.dragtar, _dragstart.bind(this));
            $(document).on('mousemove', _draging.bind(this));
            $(document).on('mouseup', _dragend.bind(this));
        };
    });
})();
$(function () {
    v.Drag.init();
});
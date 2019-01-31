/**
 * Created by SNQU on 2016/2/2.
 */

v.ImgCenterShow || (function(){
    v.ImgCenterShow = v.extend(v.UI,function(self){
        //静态属性
        self.anchor="data-imgCenter";

        //公共属性
        this.imgSrc = "";
        this.effect ="";
        this.speed=1000;

        //定义事件
        this._events = {
        };
        var  _newImg = function(){
            return  new Image();
        };
        var _imgLoad = function (img){
            img.src= this.imgSrc;
            var anchor=this.anchor,
                effect= this.effect,
                speed = this.speed,
                anchorW = anchor.clientWidth,
                anchorH = anchor.clientHeight,
                anchorRadio = anchorH / anchorW;
            img.onload = function(){
                var isRadio=false,
                    imgRadio = img.height/img.width,
                    //获取压缩后图片的宽高度
                    iwidth = anchorH/imgRadio,
                    iheight = anchorW*imgRadio,
                    //图片居中间距
                    RadioWid=(Math.round(iwidth)-anchorW)/ 2,
                    RadioHei=(Math.round(iheight)-anchorH)/2;
                //判断比例
                if (imgRadio>anchorRadio){
                    isRadio=true;
                    img.style.cssText=';position:absolute;width:'+anchorW+'px;top:-'+RadioHei+'px;';
                }else{
                    isRadio=false;
                    img.style.cssText=';position:absolute;height:'+anchorH+'px;left:-'+RadioWid+'px;';
                }
                //动画类型
                if(effect) {
                    if (effect === "slideDown") {
                        isRadio ? animateSet(img,{"top": "-" +  iheight + "px"},{"top": "-" + RadioHei + "px"},speed) : animateSet(img,{"top": "-" + anchorH+"px"},{"top":"0"},speed);
                    }else if(effect === "slideUp"){
                        isRadio ? animateSet(img,{"top": anchorH + "px"},{"top": "-" + RadioHei + "px"},speed) : animateSet(img,{"top": anchorH + "px"},{"top": "0"},speed);
                    }else if(effect ==="slideLeft"){
                        isRadio ? animateSet(img,{"left": "-"+ anchorW + "px"},{"left": "0"},speed) : animateSet(img,{"left": "-"+anchorW + "px"},{"left":"-"+RadioWid+"px"},speed)
                    }else if(effect==="slideRight"){
                        isRadio ? animateSet(img,{"left": anchorW + "px"},{"left":"0"},speed) : animateSet(img,{"left": anchorW + "px"},{"left": "-" + RadioWid + "px"},speed);
                    }else if(effect==="fadeIn"){
                        animateSet(img,{"opacity":"0"},{"opacity":"1"},speed);
                    }
                    function animateSet(obj,json1,json2,speed){
                        $(obj).css(json1).animate(json2,speed);
                    }
                }
                //插入节点
                anchor.appendChild(img);
            };
        };
        //私有属性
        this.construct= function(options){
            this.parent();
            var img = _newImg();
            _imgLoad.call(this,img);
        };
    });
})();
$(function () {
    v.ImgCenterShow.init();
});
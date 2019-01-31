/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
(function(){
        $.fn.extend({
            select: function (params) {
                var _this=this;
                var v_options_div,v_selected_option_div,v_search_input,v_select_bottom_div,v_select_div,_method;
                var options =_this.find('option');
                var oConfig={
                    more:10,
                    temp:[],
                    data:[],
                    search:false,
                    isAjax:false,
                    getResults:function(data,params){
                        params.page = params.page || 1;
                        if (data.length > 0) {
                            for (var key in data) {
                                params.temp.push({id: data[key].id, name: data[key].name});
                            }
                        }
                        return {
                            results: params.temp
                        };
                    },
                    templateResult:function (repo) {//select2配置
                        var markup = '<div>' +
                                '<div id="' + repo.id + '" class="v-select-option">' + repo.name + '</div>' +
                                '</div>';
                        return markup;
                    }
                };
                params=params || {};
                var oConfig = $.extend(oConfig, params);
                function method(){};
                method.prototype={
                    init:function(){//创建初始元素
                        _method=this;
                        _this.hide();
                        var width=_this.width();
                        if(oConfig.data.length>0){
                            var options_default=oConfig.data[0].name;
                        }else if(options.length>0){
                            var options_default=$(options[0]).html();
                        }else{
                            var options_default='';
                        }
                        if(oConfig.isAjax==false && oConfig.data.length==0){//不允许ajax，也没有传入data，则强select中option数据加入data中
                            for(var i=0;i<options.length;i++){
                                oConfig.data.push({id:$(options[i]).val(),name:$(options[i]).html()});
                            }
                        }
                        var selected_div ='<div class="v-selected-option-div" >'+options_default+'<span class="v-select-close-span"></span></div>';//已选中的项
                        var input_div=oConfig.search?'<div class="v-search-div" ><input class="v-search-input" style="width:'+(width-20)+'px"/></div>':'';//搜索框
                        var options_div='<div class="v-options-div" style="width:'+width+'px"><div>';//select选项
                        _this.after('<div style="width:'+width+'px" class="v-select-div" >'+selected_div+'<div class="v-select-bottom-div">'+input_div+options_div+'</div></div>');
                        v_search_input=_this.next('div').find('.v-search-input');
                        v_selected_option_div=_this.next('div').find('.v-selected-option-div');//选中项
                        v_options_div=_this.next('div').find('.v-options-div');//选择项
                        v_select_bottom_div=_this.next('div').find('.v-select-bottom-div');//选择项
                        v_select_div=_this.next('div');
                        this.bindevent();
                    },
                    bindevent:function(){//绑定基础事件
                        _method.v_selected_option_click();//点击执行数据初始化
                        if(oConfig.search==true){//如果允许搜索框
                            v_search_input.on('input',function(){
                                oConfig.page=1;
                                oConfig.temp=[];
                                _method.getSearchData();
                            });
                            v_search_input.on('PropertyChange',function(){
                                oConfig.page=1;
                                oConfig.temp=[];
                                _method.getSearchData();
                            });
                        }
                        v_options_div.on('scroll',function(){//option框滑动事件
                            if((this.scrollHeight)==($(this).scrollTop()+$(this).height())){//滚动条离底部搜索下一页
                                oConfig.page++;
                                _method.getSearchData();
                            }
                        });
                        v_options_div.on('mouseover','.v-select-option',function(){
                            var $option=$(this);
                            $option.css('background','#534976');
                        });

                        v_options_div.on('mouseout','.v-select-option',function(){
                            var $option=$(this);
                            $option.css('background','');
                        });
                        v_options_div.on('click','.v-select-option',function(){
                            var $option=$(this);
                            var id=$option.attr('id');
                            var optionName=$option.html();
                            _this.html("<option value='"+id+"'>"+optionName+"</option>");
                            v_select_bottom_div.hide();
                            v_selected_option_div.html(optionName+"<span class='v-select-close-span'></span>");
                        })
                    },
                    v_selected_option_click:function(){//click时进行初始数据获取
                        v_selected_option_div.on('click',function(){
                            if(v_selected_option_div.find('span').attr('class')=='v-select-close-span'){
                                v_selected_option_div.find('span').removeClass('v-select-close-span');
                                v_selected_option_div.find('span').addClass('v-select-open-span');
                                oConfig.page=1;
                                oConfig.temp=[];
                                _method.getSearchData();//获取初始数据
                                v_select_bottom_div.show();//option展示
                                v_search_input.focus();//搜索框焦点获取
                            }else{
                                v_selected_option_div.find('span').removeClass('v-select-open-span');
                                v_selected_option_div.find('span').addClass('v-select-close-span');
                                v_select_bottom_div.hide();
                            }
                        });
                    },
                    getSearchData:function(){//这里加入分页的限制
                        if(oConfig.isAjax==true){
                            _method.ajax();//通过ajax获取数据
                        }else{//如果不启用ajax，则页面形成来自oConfig.data
                            var str='';
                            var search_words=v_search_input.val();
                            if(search_words==undefined || search_words=='undefined')
                                search_words='';
                            var data=oConfig.data;
                            var data_temp=[];
                            var page = oConfig.page;
                            for(var key in data){
                                if(search_words=='' || data[key].name.indexOf(search_words)>-1){
                                    data_temp.push(data[key]);
                                }
                            }
                            _method.insertOptions(data_temp);
                        }
                    },
                    insertOptions:function(data){//向下拉框中加入options
                        var iDisplayStart=oConfig.more*(oConfig.page-1);
                        var iDisplayEnd=iDisplayStart+oConfig.more-1;
                        if(oConfig.page>1){
                            var addData=data.slice(iDisplayStart,iDisplayEnd);
                            var str='';
                            for(var key in addData){
                                str+=oConfig.templateResult(addData[key]);
                            }
                            v_options_div.append(str);
                        }else{
                            var addData=data.slice(iDisplayStart,iDisplayEnd);
                            var str='';
                            for(var key in addData){
                                str+=oConfig.templateResult(addData[key]);
                            }
                            v_options_div.html(str);
                        }
                    },
                    ajax:function(){
                        var search_words=v_search_input.val();
                        if(search_words==undefined || search_words=='undefined')
                            search_words='';
                        oConfig.ajax=oConfig.getAjaxParams(search_words,oConfig);
                        oConfig.ajax.data.iDisplayLength=oConfig.more;
                        oConfig.ajax.data.iDisplayStart=oConfig.more*(oConfig.page-1);
                        var url=oConfig.ajax.url;
                        if(url.indexOf('?')>-1){
                            oConfig.ajax.url+='&v_search_rand='+Math.random();
                        }else{
                            oConfig.ajax.url+='?v_search_rand='+Math.random();
                        }
                        oConfig.ajax.success=function(data){
                            var rs= oConfig.getResults(data,oConfig);
                            _method.insertOptions(rs.results);
                            oConfig.ajax.url=url;
                        };
                        $.ajax(oConfig.ajax);
                    }
                }
                var Method=new method();
                Method.init();
                return _this;
            }
        });
})();


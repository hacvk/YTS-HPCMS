 <!-- 右侧下属栏目列表<?php if(!defined('ucms'))exit; ?> -->
 <!--leftmenu star-->    
 
<div class="leftmenu">
    <div class="tit"><i class="wap-open"></i><b>快捷导航</b></div>
    <ul class="ul">
        {{$set=array()}}
        {{$set['cid']=`cid}}
        {{n($set)}}
    </ul>
    </div>

    <script>
      //移动端菜单
    leftmenu();
    function leftmenu() {
        var btn = $(".leftmenu .wap-open"),
            _this = $(".leftmenu ul")
        btn.click(function (e) {
            $(this).toggleClass("on");
            _this.stop(true, true).fadeToggle();
            $(document).on("click", function () {
                _this.stop(true, true).fadeOut();
                btn.removeClass("on");
            });
            e.stopPropagation();
        });
        _this.on("click", function (e) {
            e.stopPropagation();
        });
    }
    $('.leftmenu ul li').each(function () {
        var isPageName = $(this).attr('pagename');
        if (location.href.indexOf(isPageName) >= 0 || location.href.indexOf(isPageName.toLowerCase()) >= 0) {
            $(this).addClass('aon');
        }
    })
</script>
    <!--leftmenu end--> 

    <div class="menubox_l">
        <a href=""><img src="{{z(网站域名)}}static/images/menu1.png"></a>
        <a href=""><img src="{{z(网站域名)}}static/images/menu2.png"></a>
        <a href=""><img src="{{z(网站域名)}}static/images/menu3.png"></a>
        <a href=""><img src="{{z(网站域名)}}static/images/menu4.png"></a>
    </div>
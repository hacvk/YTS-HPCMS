<!-- 头部<?php if(!defined('ucms'))exit; ?> -->
<div class="Top_dyseo">
<!-- top -->
  
 
<div class="today">
    <dl class="auto auto_1150 clearfix">
      <dd><a href='{{z(网站域名)}}' class="gethome">返回首页</a> </dd>
      <dt class="fl">您好！欢迎您访问{{z(医院名称)}}官网</dt>
    </dl>
  </div>
  <div class="header-top auto auto_1150">
    
    <div class="logo"><a href='/'><img src="{{z(logo图片)}}"></a></div>
  <!--网页头部搜索开始 star-->
    <dl class="header-right">
      <dt>
        <form method=get action="{{uri(/search/)}}">
        <input type="text" class="text" id="TopKey"  name="keyword" value="" >
        <input id="TopBtn" class="btn" type="submit" value="查找" class="seabtn">
      </dt>
      <dd id="open_menu"><i></i></dd>
    </dl></form>
    <script>
      // 搜索框768点击显示/隐藏
      if($(window).width() < 769) {
        $('.search-btn').click(function() {
          $('.header-right dt').stop().slideToggle(300);
        })
      }
    </script>
  </div>
  <!--网页头部导航开始 star-->
  <div class="header-bottom">
    <header class="header auto auto_1150">
      <nav class="header-menu sub-vertical clearfix sub-slideDown">
        <ul class="ul clearfix">
          <li class="home"><em><a href='{{z(网站域名)}}'>首页</a></em></li>
          {{$channels=c(0,999,0)}}
{{loop $channels as $channel}}
{{if $channel['ifshownav']}}
    <li><em><a href="{{$channel['url']}}" class="{{$channel['onstyle']}}">{{$channel['name']}}</a></em>
    {{/if}}
    {{$channels2=c(0,999,$channel['cid'])}}
{{if count($channels2)}}
      <div class="sub">
              <div class="sub-menu">
                <dl>
   {{$channels2=c(0,999,$channel['cid'])}}
   {{loop $channels2 as $channel2}}
       <dt> <a href="{{$channel2['url']}}" class="{{$channel2['onstyle']}}">{{$channel2['name']}}</a> </dt>
   {{/loop}}     {{$channels2=c(0,999,$channel['cid'])}}
</dl>
   </div>
            </div>{{/if}}
          </li>
{{/loop}}


        </ul>
      </nav>
    </header>
  </div>
 <!-- 头部导航结束 -->
</div>
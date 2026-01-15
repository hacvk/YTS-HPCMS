<!DOCTYPE html>
<!-- 首页<?php if(!defined('ucms'))exit; ?> -->
<html>
<head>
 <!-- head --> 
<meta http-equiv="Cache-Control" content="no-transform" />
<meta http-equiv="Cache-Control" content="no-siteapp" />
<meta name="applicable-device" content="mobile" />
<meta name="renderer" content="webkit" />
<meta name="format-detection" content="telephone=no" />
<meta content="email=no" name="format-detection" />
<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>{{z(站点标题)}}</title>
<meta name="keywords" content="{{z(关键词)}}"/>
<meta name="description" content="{{z(描述)}}"/>
<link href="{{z(网站域名)}}static/css/index.css" rel="stylesheet" type="text/css"/>
{{file itop}}

<script type="text/javascript" src="{{z(网站域名)}}static/js/jquery.SuperSlide.2.1.3.js"></script>
 <!-- head -->
</head>
<body>
{{file header}}
<!--indexbanner star-->
<div class="indexbanner">
  <ul class="ul">
     {{$set=array()}}{{$set['cid']=23}}{{$set['pagesize']=5}} {{$set['order']=aorder}}{{$articles=alist($set)}}{{loop $articles['list'] as $a}}  
    <li><a href='{{$a['url']}}'><img src="{{$a['pic']}}"></a></li>  
     {{/loop}}
  </ul>
  <script>
   $('.indexbanner ul').bxSlider({ auto: true,mode: 'fade'});    
 </script> 
</div>
<!--indexbanner end--> 
<!--首页通知公告 star-->
<div class="newsbox">
  <div class="auto auto_1150">
    <section class="ytable">
<!-- 首页轮播图片开始 -->
    <section class="ytable-cell left">
  <!--fullSlide-->
  <div class="fullSlide">
    <div class="bd">
      <ul>     {{$set=array()}}
      {{$set['where']['ifcheck']=1}}
      {{$set['cid']=5}}{{$set['pagesize']=5}}{{$articles=alist($set)}}{{loop $articles['list'] as $a}}
      
          <li>
          <a href="{{$a['link']}}">
            <div class="item">
              <div class="pic"><img src="{{$a['pic']}}" /></div>
              <div class="txt">
                <h5>{{$a['title']|text(22)}}</h5>
              </div>
            </div>
          </a>
        </li> {{/loop}}
         </ul>
    </div>
    <div class="hd">
      <ul>
      </ul>
    </div>
  </div>

  <script type="text/javascript">
    jQuery(".fullSlide").slide({ titCell:".hd ul", mainCell:".bd ul", effect:"leftLoop", vis:"auto", autoPlay:true, autoPage:true, trigger:"click"});
  </script>
  <!--fullSlide-->

    </section>

<!-- 首页轮播图片结束 -->
      <section class="ytable-cell no"></section>
      <section class="ytable-cell center">
        <div class="inner">
          <div class="tab-ul">
            <ul class="ul clearfix">
              <li>
                   <span>医院要闻
                  <a href="/yiyuanyaowen/"  class="more">&nbsp;»</a>
                   </span>
               
              </li>
                    <li>
                    <span>通知公告
                     <a href="/7/"  class="more">&nbsp;»</a>
                    </span>
                   </li>
            </ul>
          </div>
          <div class="tab-list">
            <dl>
            {{$set=array()}}{{$set['cid']=6}}{{$set['pagesize']=9}}{{$articles=alist($set)}}{{loop $articles['list'] as $a}}  
              <dd>
                <time>{{$a['shijian']|date('Y-m-d',this)}}</time>
                <a href='{{$a['link']}}' class="dot">{{$a['title']|text(22)}}</a></dd>
             {{/loop}}
            </dl>
         
            <dl>
            {{$set=array()}}{{$set['cid']=7}}{{$set['pagesize']=9}}{{$articles=alist($set)}}{{loop $articles['list'] as $a}}  
              <dd>
                <time>{{$a['shijian']|date('Y-m-d',this)}}</time>
                <a href='{{$a['link']}}' class="dot">{{$a['title']|text(22)}}</a></dd>
             {{/loop}}
            </dl>
           
          </div>
        </div>
      </section>
      <section class="ytable-cell no"></section>
      <section class="ytable-cell right">
        <div class="inner">
          <div class="title">
            <h3 class="h3"> <span class="st"><i class="i i1"></i><i class="i i2"></i> 快捷导航</span></h3>
          </div>
          <div class="fast-link">
            <dl class="clearfix">
              
              <dt> <a href='https://www.dysdwrmyy.com/zyks/'>
                <div class="icon"><img src="static/picture/20210323105803915.png"></div>
                <aside class="dot">科室导航</aside>
                </a> </dt>
              
              <dt> <a href=''>
                <div class="icon"><img src="static/picture/20210323105756431.png"></div>
                <aside class="dot">信息查询</aside>
                </a> </dt>
              
              <dt> <a href='https://www.dysdwrmyy.com/11/'>
                <div class="icon"><img src="static/picture/20210323105729681.png"></div>
                <aside class="dot">出诊安排</aside>
                </a> </dt>
              
              <dt> <a href='https://www.dysdwrmyy.com/jiuzhenzhinan/'>
                <div class="icon"><img src="static/picture/20210323105711540.png"></div>
                <aside class="dot">就诊须知</aside>
                </a> </dt>
              
            </dl>
          </div>
        </div>
      </section>
    </section>
  </div>
  <script>
    newsbox(".newsbox")
   function newsbox(v){
     var _this=$(v)
     var li=_this.find(".tab-ul li")
     var info=_this.find(".tab-list dl")
     li.click(function(){
       var e=$(this).index()
       $(this).addClass("on").siblings().removeClass("on")
       info.eq(e).show().siblings().hide();
     });
     li.eq(0).click();
   }
 </script> 
</div>
<!--newsbox end-->
      


  

<!--科室列表 star-->
<div class="debox">
  <div class="auto auto_1150">
    <div class="inner">
      <div class="tab">
        <ul class="ul clearfix">
            
                <li><a>内科</a></li>
                <li><a>外科</a></li>
                <li><a>医技</a></li>
                <li><a>急诊</a></li>
                <li><a>体检中心</a></li>
            
        </ul>
      </div>
      <div class="tab-info">
          <!-- 内科开始 -->
              <div class="box">
                  <div class="mCustomScrollbar">
                      <div class="list clearfix">
                           {{$set=array()}}{{$set['cid']=13}}{{$set['pagesize']=8}}{{$articles=alist($set)}}{{loop $articles['list'] as $a}} 
                              <div class="item">
                                  <a href='{{$a['link']}}'></a>
                                  <ul class="ul ytable">
                                      <ol class="ytable-cell">
                                          <img src="{{$a['pic']}}">
                                      </ol>
                                      <li class="ytable-cell">
                                          <aside class="dot">{{$a['title']}}</aside>
                                      </li>
                                  </ul>
                              </div>
                          {{/loop}}
                      </div>
                  </div>
              </div>
          <!-- 内科结束 -->
          <!-- 外科开始 -->
              <div class="box">
                  <div class="mCustomScrollbar">
                      <div class="list clearfix">
                          {{$set=array()}}{{$set['cid']=16}}{{$set['pagesize']=8}}{{$articles=alist($set)}}{{loop $articles['list'] as $a}} 
                              <div class="item">
                                  <a href='{{$a['link']}}'></a>
                                  <ul class="ul ytable">
                                      <ol class="ytable-cell">
                                          <img src="{{$a['pic']}}">
                                      </ol>
                                      <li class="ytable-cell">
                                          <aside class="dot">{{$a['title']}}</aside>
                                      </li>
                                  </ul>
                              </div>
                          {{/loop}}              
                      </div>
                  </div>
              </div>
              <!-- 外科结束 -->
              <!-- 医技开始 -->
              <div class="box">
                  <div class="mCustomScrollbar">
                      <div class="list clearfix">
                          {{$set=array()}}{{$set['cid']=19}}{{$set['pagesize']=8}}{{$articles=alist($set)}}{{loop $articles['list'] as $a}} 
                              <div class="item">
                                  <a href='{{$a['link']}}'></a>
                                  <ul class="ul ytable">
                                      <ol class="ytable-cell">
                                          <img src="{{$a['pic']}}">
                                      </ol>
                                      <li class="ytable-cell">
                                          <aside class="dot">{{$a['title']}}</aside>
                                      </li>
                                  </ul>
                              </div>
                          {{/loop}}
                      </div>
                  </div>
              </div>
              <!-- 医技结束 -->
          
              <div class="box">
                  <div class="mCustomScrollbar">
                      <div class="list clearfix">
                          {{$set=array()}}{{$set['cid']=21}}{{$set['pagesize']=8}}{{$articles=alist($set)}}{{loop $articles['list'] as $a}} 
                              <div class="item">
                                  <a href='{{$a['link']}}'></a>
                                  <ul class="ul ytable">
                                      <ol class="ytable-cell">
                                          <img src="{{$a['pic']}}">
                                      </ol>
                                      <li class="ytable-cell">
                                          <aside class="dot">{{$a['title']}}</aside>
                                      </li>
                                  </ul>
                              </div>
                          {{/loop}}
                      </div>
                  </div>
              </div>
          
              <div class="box">
                  <div class="mCustomScrollbar">
                      <div class="list clearfix">
                          {{$set=array()}}{{$set['cid']=20}}{{$set['pagesize']=8}}{{$articles=alist($set)}}{{loop $articles['list'] as $a}} 
                              <div class="item">
                                  <a href='{{$a['link']}}'></a>
                                  <ul class="ul ytable">
                                      <ol class="ytable-cell">
                                          <img src="{{$a['pic']}}">
                                      </ol>
                                      <li class="ytable-cell">
                                          <aside class="dot">{{$a['title']}}</aside>
                                      </li>
                                  </ul>
                              </div>
                          {{/loop}}
                      </div>
                  </div>
              </div>
          
      </div>
    </div>
  </div>
</div>
<script>
$(".debox .tab li").click(function(){
    var e=$(this).index()
    $(this).addClass("active").siblings().removeClass("active");
    $(".debox .tab-info .box").eq(e).show().siblings().hide();
});
$(".debox .tab li").eq(0).click();
</script> 
<!--debox end--> 

<!--Doctor_box star-->
<div class="Doctor_box">
  <div class="auto auto_1150">
    <section class="inner clearfix">
      <div class="title"><b>专家介绍</b><span> / Expert Introduction</span></div>
      <div class="slider">
        <div class="list">
          <ul class="ul">
       {{$set=array()}}{{$set['cid']=15}}{{$set['pagesize']=8}}{{$set['order']=id}}{{$articles=alist($set)}}{{loop $articles['list'] as $a}} 
        <li><div class="ytable">
            <div class="pic ytable-cell"><a href="{{$a['link']}}"><img src="{{$a['pic']}}"></a></div>
                <div class="txt ytable-cell">
                    <h3 class="dot">
                    <a href="{{$a['link']}}">{{$a['title']}}</a>                    
                        <span>{{$ids=$a['keshi']|json_decode(1)}}
{{if count($ids)>0}}
    {{loop $ids as $id}}
        {{$set=array()}}
        {{$set['cid']=$id[0]}}
        {{$set['where']['id']=$id[1]}}
        {{$set['all']=0}}
        {{$fromarticle=a($set)}}
        {{if $fromarticle}}
            <a href="{{$fromarticle['link']}}">{{$fromarticle['title']}}</a><br>
        {{else}}
            文章不存在了<br>
        {{/if}}
        {{/loop}}
{{/if}}
</span>                    
                    </h3>
                    <aside>{{$a['zhicheng']}}</aside>
                    <div class="word">
                     <p>{{$a['description']|text(80)}}...</p>

                    </div>
                 </div>
          </div></li>
        {{/loop}}
            </ul>
        </div>
        <div class="controls"></div>
      </div>
    </section>
  </div>
</div>
<script>
$('.Doctor_box .slider .ul').slick({
  slidesToShow: 3,
  autoplay:true,
  appendArrows:$(".Doctor_box .controls"),
    responsive: [
    {breakpoint: 1150,settings: {slidesToShow: 2}},
    {breakpoint: 420,settings: {slidesToShow: 1}}
    ]
});
</script> 


<!--Doctor_box end--> 

<!--ipic_box star-->
<div class="ipic_box">
  <ul class="ul auto auto_1150 clearfix">
    
    <li><a href=''> <img src="static/picture/20210323113251823.jpg">
      <aside class="dot">党群工作</aside>
      </a></li>
    
    <li><a href=''> <img src="static/picture/20210323114157555.jpg">
      <aside class="dot">科研教学</aside>
      </a></li>
    
    <li><a href=''> <img src="static/picture/20210323114206883.jpg">
      <aside class="dot">招生培训</aside>
      </a></li>
    
    <li><a href=''> <img src="static/picture/20210323113132369.jpg">
      <aside class="dot">人才招聘</aside>
      </a></li>
    
  </ul>
</div>
<!--ipic_box end--> 
{{file footer}}

</body>
</html>
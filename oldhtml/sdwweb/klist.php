<!DOCTYPE html>
<!-- 文章栏目页面<?php if(!defined('ucms'))exit; ?> -->
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
<title>{{s(栏目标题)}}-{{z(站点标题)}}</title>
<meta name="keywords" content="{{s(栏目关键词)}}"/>
<meta name="description" content="{{s(栏目描述)}}"/>
{{file itop}}
<!-- head -->
</head>
<body>
{{file header}}
<div class="Banner_dy">
     <span style="background-image:url({{z(网站域名)}}static/picture/20210322110902915.jpg)"></span> <img src="{{z(网站域名)}}static/picture/20210322110902915.jpg" alt="">
 </div>

<!-- Banner_dy End --> 
<!--crumbs star-->
 
 
<div class="crumbs">
    <div class="auto auto_1150"> <em>您的当前位置：</em>{{b()}} </div>
  </div>

<!--crumbs end-->
<div class="auto auto_1150">
  <div class="w22 fl"> 
   {{file right_channel}}</div>
  <div class="w75 fr"> 
    <!--Artlist star-->
    <div class="Artlist">
      <ul class="ul">
       {{$set=array()}}
        {{$set['page']=page}}
        {{$articles=alist($set)}}
        {{loop $articles['list'] as $a}}
        <li>         
          <a href='{{$a['link']}}' class="dot">{{$a['title']}}</a></li>
        {{/loop}}
      </ul>
    </div>
    <!--Artlist end--> 
    <!--分页开始-->
    <div class="pagelist">
             {{pagelist($articles)}}
    </div>
  </div>
  <div class="clear"></div>
</div>
{{file footer}}

</body>
</html>
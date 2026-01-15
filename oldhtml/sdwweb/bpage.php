<!DOCTYPE html>
<!-- 单页栏目页面<?php if(!defined('ucms'))exit; ?> -->
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
<!-- Banner_dy Start -->
 
 
<div class="Banner_dy">
     <span style="background-image:url(../static/ibanner/jzzn.jpg)"></span> <img src="../static/ibanner/jzzn.jpg" alt="">
 </div>

<!-- Banner_dy End -->  
<div class="crumbs">
    <div class="auto auto_1150"> <em>您的当前位置：</em>{{b()}}</div>
  </div>
<div class="auto auto_1150">
  <div class="w22 fl"> 
      {{file right_channel}} </div>
  <div class="w75 fr">
<!--publictxt star-->
<div class="publictxt">
  <div class="auto">
    <div class="detail">
    <h1 class="title">{{s(栏目标题)}}</h1>
    <div class="img-ban"><center><img src="{{s(栏目图片)}}" class="img-block"></center></div>
    <article class="edit-info">
{{s(内容)}}
    </article>
  
    </div>
  </div>
</div>
<!--publictxt end-->
  </div>
  <div class="clear"></div>
</div>
{{file footer}}
</body>
</html>
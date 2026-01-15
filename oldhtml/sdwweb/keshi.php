{{$set=array()}}
{{$set['where']['id']=intval($_GET['id'])}}
{{$article=a($set)}}
{{if !$article}}
{{notfound()}}
{{/if}}
<!DOCTYPE html>
<!-- 文章页<?php if(!defined('ucms'))exit; ?> -->
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
<title>{{$article['title']}}-{{z(站点标题)}}</title>
{{if isset($article['keywords'])}}
<meta name="keywords" content="{{$article['keywords']}}"/>
{{/if}}
{{if isset($article['description'])}}
<meta name="description" content="{{$article['description']}}"/>
{{/if}}
{{file itop}}
<!-- head -->
</head>
<body>
{{file header}}
<!-- Banner_dy Start -->
 
 
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
    {{file right_channel}}
   </div>
  <div class="w75 fr">
<!--publictxt star-->
<div class="publictxt">
  <div class="auto">
    <div class="img-ban"><img src="{{s(栏目图片)}}" class="img-block"></div>
    <div class="detail">
    <h1 class="title">{{$article['title']}}</h1>
    <article class="edit-info">
{{if isset($article['content'])}}
     {{$article['content']}}
     {{else}}
        内容字段尚未增加
     {{/if}}
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
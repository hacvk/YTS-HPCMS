<!DOCTYPE html>
<html>
<!-- 单页栏目页面<?php if(!defined('ucms'))exit; ?> -->
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
<title>搜索结果-{{z(站点标题)}}</title>
{{file itop}}
<!-- head -->
<style>
  body .w75{
    width: 100%;
  }
</style>
</head>
<body>
{{file header}}

<!--crumbs star-->
 
 
<div class="crumbs">
    <div class="auto auto_1150"> <em>您的当前位置：</em>{{b()}}</div>
  </div>

<!--crumbs end-->
<div class="auto auto_1150">
  <div class="w75 fr"> 
    <!--Artlist star-->
    <div class="Artlist">
      <ul class="ul">
        {{if isset($_GET["keyword"]) && !empty($_GET["keyword"])}}
{{$set=array()}}
{{$set['cid']=0}}
{{$set['keyword']=$_GET["keyword"]}}
{{$articles=alist($set)}}
{{loop $articles['list'] as $a}}
        
        <li>
          <time>{{$a['shijian']|date('Y-m-d',this)}}</time>
          <a href='{{$a['link']}}' class="dot">{{$a['title']}}</a></li>
      {{/loop}}
{{/if}} 
      </ul>
    </div></div>
    <!--Artlist end--> 


  <div class="clear"></div>
</div>
{{file footer}}
</body>
</html>
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
     <span style="background-image:url(../static/images/20210322171041403.jpg)"></span> <img src="../static/picture/20210322171049168.jpg" alt="">
 </div>

<!-- Banner_dy End --> 
 
<div class="crumbs">
    <div class="auto auto_1150"> <em>您的当前位置：</em>{{b()}} </div>
  </div>

<div class="auto auto_1150">
  <div class="w22 fl"> 
    {{file right_channel}} </div>
  <div class="w75 fr"> 
<!--Artinfo star-->
<div class="Artinfo">
  <div class="auto auto_1150">
    <article class="news-details auto w_96">
      <div id="print-area">
        <div class="title">
          <h1>{{$article['title']}}</h1>
          <aside><span>发布时间：{{$article['shijian']|date('Y-m-d H:i:s',this)}}</span><span>阅读次数：{{$article['click']}} 次</span><span>【字号：<a href="javascript:SetFont(20)">大</a> <a href="javascript:SetFont(18)">中</a> <a href="javascript:SetFont(16)">小</a>】</span></aside>
        </div>
        <div class="edit-info" id="news_content">
         {{if isset($article['content'])}}
        {{$article['content']}}
      {{else}}
        内容字段尚未增加
      {{/if}}
        </div>
      </div>
    
      <div class="pn-page">
        <div class="operating"><a class="a-print" href="javascript:void(0);">打印本页</a><a class="a-close" href="javascript:void(0);" onClick="closeWindow();">关闭窗口</a></div>
        {{$prevset=array()}}
{{$prevset['column']=id,title}}
{{$prevset['order']=id desc}}
{{$prevset['other']=`"id<".$article['id']}}
{{$prev=a($prevset)}}
{{if $prev}}
    <aside class="dot">上一篇:<a href="{{$prev['link']}}">{{$prev['title']}}</a></aside>
{{/if}}
{{$nextset=array()}}
{{$nextset['column']=id,title}}
{{$nextset['other']=`"id>".$article['id']}}
{{$nextset['order']=id asc}}
{{$next=a($nextset)}}
{{if $next}}
     <aside class="dot">下一篇:<a href="{{$next['link']}}">{{$next['title']}}</a></aside>
{{/if}}
      </div>
    </article>
  </div>
</div>
<script>
//设置字体 
function SetFont(size)
{
  var divBody = document.getElementById("news_content");
  if(!divBody)
  {
	  return;
  }
  divBody.style.fontSize = size + "px";
  var divChildBody = divBody.childNodes;
  for(var i = 0; i < divChildBody.length; i++)
  {
	  if (divChildBody[i].nodeType==1)
	  {
		  divChildBody[i].style.fontSize = size + "px";
	  }
  }
}
$(".a-print").click(function(){
    $("#print-area").printArea();
});
 function closeWindow() {
    var userAgent = navigator.userAgent;
    if (userAgent.indexOf("Firefox") != -1 || userAgent.indexOf("Chrome") != -1) {
        location.href = "about:blank";
    } else {
        window.opener = null;
        window.open('', '_self');
    }
    window.close();
}
</script> 
<!--Artinfo end--> 
  </div>
  <div class="clear"></div>
</div>
{{file footer}}

</body>
</html>
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
<div class="crumbs">
    <div class="auto auto_1150"> <em>您的当前位置：</em>{{b()}} </div>
  </div>
<div class="auto auto_1150">
  <div class="w22 fl"> 
   {{file right_channel}}</div>
  <div class="w75 fr"> 

     <div class="Doctor_list">
      <ul class="ul clearfix">
       {{$set=array()}}
        {{$set['page']=page}}
        {{$set['order']=id}}
        {{$articles=alist($set)}}
        {{loop $articles['list'] as $a}} 
        <li>
          <div class="ytable">
            <div class="pic ytable-cell"><a href="{{$a['link']}}"><img src="{{$a['pic']}}"></a></div>
            <div class="txt ytable-cell">
                <h3 class="dot">
                    <a href="{{$a['link']}}">{{$a['title']}}</a>
                    
                        <span>{{$a['zhicheng']}}</span>
                    
                </h3>
              <aside>{{$ids=$a['keshi']|json_decode(1)}}
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
{{/if}}</aside>
              <div class="word">
                <p class="dot3">
                  {{$a['description']|text(80)}}...
                </p>
              </div>
            </div>
          </div>
          <div class="btnDiv">           
            <input type="button" value="预约专家">
            <a href="{{$a['link']}}" class="more">查看详细</a></div>
        </li>
      {{/loop}} 
      
      </ul>
    </div>
    <div class="pagelist">
             {{pagelist($articles)}}
    </div>
  </div>
  <div class="clear"></div>
</div>
{{file footer}}

</body>
</html>
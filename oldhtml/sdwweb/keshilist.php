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
     <span style="background-image:url(../static/images/20210322110846102.jpg)"></span> <img src="../static/picture/20210322110902915.jpg" alt="">
 </div>

<!-- Banner_dy End -->
<div class="crumbs">
    <div class="auto auto_1150"> <em>您的当前位置：</em>{{b()}}</div>
  </div>
<div class="auto auto_1150">
	<div class="w22 fl">
	{{file right_channel}}
	</div>
	<div class="w75 fr">
		<!--k_list star-->
		<div class="k_list">
			<div class="auto">
				<dl class="clearfix">
					{{$channels=c(2,66)}}
          {{loop $channels as $channel}}
            {{$channels2=c(0,999,$channel['cid'])}}
  {{loop $channels2 as $channel2}}
						<dd>
							<div class="title">{{$channel2['name']}}</div>
							<ul class="ul clearfix">
                {{$set=array()}}
      {{$set['cid']=$channel2['cid']}}
      {{$set['pagesize']=12}}
      {{$articles=alist($set)}}
      {{loop $articles['list'] as $a}}
								
									<li><a href='{{$a['link']}}' class="dot">{{$a['title']}}</a></li>
				{{/loop}}
							</ul>
						</dd>
				{{/loop}}	
        {{/loop}}
					
				</dl>
			</div>
		</div>
		<!--k_list end-->
	</div>
	<div class="clear"></div>
</div>
{{file footer}}
</body>

</html>
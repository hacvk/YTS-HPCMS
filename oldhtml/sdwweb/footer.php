<!-- 底部<?php if(!defined('ucms'))exit; ?> -->
 
<div class="footer_dyseo">
	<div class="auto auto_1150">
		<dl>
			<dt><a href="javascript:;">友情链接</a></dt>
			<dd class="clearfix">
				{{z(首页友情链接)}}
			</dd>
		</dl>
		<div class="footer clearfix">
			
				<div class="share fr">
					<ul class="ul clearfix">
						<ol>
							<aside>分享关注：</aside>
							<div class="bshare-custom">
								<div class="bsPromo bsPromo2"></div>
								<a title="分享到新浪微博" class="bshare-sinaminiblog"></a><a title="分享到微信"
									class="bshare-weixin"></a>
							</div>
							
						</ol>
						<li><img src="{{z(首页二维码)}}"></li>
					</ul>
				</div>
				<div class="copyright fl" style="position:relative;"><a style="position:absolute;left:0;top:0;width:100px;height:100%;z-index:5;" target="_blank" href="{{z(事业单位标识平台网址)}}"></a>
					&copy; {{date(Y)}} {{z(医院名称)}} <a href="#" target="_blank"><a id="icp_record" href="//beian.miit.gov.cn" target="_blank" >{{z(备案号)}} </a><a style="color: #bcbec0;" href="http://www.beian.gov.cn/portal/registerSystemInfo?recordcode=37052102000232"><img src="{{z(网站域名)}}static/images/gaba.png" style=" vertical-align: middle; display: inline-block;">{{z(公安备案号)}}</a>
					<p>乘车方式：{{z(乘车方式)}}</p>
					<p>院址：{{z(地址)}} 邮编:{{z(邮编)}}&emsp;服务热线：{{z(联系电话)}}&emsp;举报电话：{{z(举报电话)}}</p>			
		</div>
	</div>
</div>

<script>
	$(function ($) {
		$(window).on("resize", function (e) {
			$(".footer_dyseo .select-group dl").each(function () {
				var a = $(this).find("dt").width()
					, b = $(this).find("dt").find("a").outerWidth()
					, c = Math.round(((a - b) / 2) / a * 0.8 * 100) + "%"
				$(this).find("dt").find("span").width(c)
			});
		}).trigger("resize");
	});

	$(".footer_dyseo .select-group dl").each(function () {
		$(this).find("dt").click(function () {
			$(this).next("dd").toggle();
			$(this).parent("dl").toggleClass("on");
		});
		$(this).mouseleave(function () {
			$(this).find("dd").hide();
			$(this).removeClass("on");
		});
	});
</script>
</div>

<!--foot_nav开始-->
<div class="foot_nav">
	<button></button>
	<a href="#" class="icon1">
		官方微信
		<span class="img-box">
			<img src="{{z(官方微信)}}" alt="">
		</span>
	</a>
	<a href='{{z(网站域名)}}jiuzhenzhinan/' class="icon2">来院路线</a>
	<a href='' class="icon3">在线客服</a>
	<a href="javascript:void(0);" onclick="$('body,html').animate({scrollTop:0},500);" class="top"><b>TOP</b>回到顶部</a>
</div>
<!--foot_nav结束-->
<script>
	if ($(window).width() < 769) {
		var asideShow = true;
		$('.foot_nav button').click(function () {
			$(this).toggleClass('active');
			if (asideShow) {
				$('.foot_nav').stop().animate({ right: '0' }, 300);
			} else {
				$('.foot_nav').stop().animate({ right: '-70px' }, 300);
			}
			// 取反
			asideShow = !asideShow
		})
	}
</script>


<!--页脚 end--> 
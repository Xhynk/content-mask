jQuery(document).ready(function($){
	if( $("#content_mask_enable").is(":checked") ){
		$("#postdivrich").css({"height":0,"overflow":"hidden"}).addClass("hide-overflow");
	}

	$("#content_mask_enable").click(function(){
		if( $(this).is(":checked") ){
			$("#postdivrich").animate({"height":0,"overflow":"hidden"}).addClass("hide-overflow");
		} else {
			$("#postdivrich").animate({"height":437,"overflow":"visible"}).removeClass("hide-overflow");
		}
	});
});

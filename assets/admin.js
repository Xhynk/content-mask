jQuery(document).ready(function($){
	function cmMessage( classes, message ){
		$('.content-mask-admin-table').before('<div class="cm-message notice notice-'+ classes +'"><p>'+ message +'</p></div>');
	}

	if( $("#content_mask_enable").is(":checked") ){
		$("#postdivrich").css({"height":0,"overflow":"hidden"}).addClass("hide-overflow");
	}

	$('body').on( 'click', '.content-mask-admin-table-body .method i', function(){
		var	$clicked     = $(this),
			restoreIcon  = $(this).attr('class');
			postID       = $(this).closest('tr').attr('data-attr-id'),
			currentState = $(this).closest('tr').attr('data-attr-state'),
			newState     = currentState == 'enabled' ? 'disabled' : 'enabled';

		$($clicked).attr('class', 'icon icon-reload');

		var data = {
			'action': 'toggle_content_mask',
			'postID': postID,
			'newState': newState,
			'currentState': currentState,
		};

		$.post(ajaxurl, data, function(response) {
			$('.cm-message').remove(); // Prevent weird interaction with existing messages
			if( response.status == 200 ){
				$($clicked).attr('class', restoreIcon);
				$($clicked).closest('tr').attr('data-attr-state', newState).removeClass(currentState).addClass(newState);
				cmMessage( 'info', response.message );
			} else if( response.status == 400 ){
				cmMessage( 'error', response.message );
			} else if( response.status == 403) {
				cmMessage( 'error', response.message );
			}
		}, 'json');
	});

	$("#content_mask_enable").click(function(){
		if( $(this).is(":checked") ){
			// Hiding the editor was distracting
			//$("#postdivrich").animate({"height":0,"overflow":"hidden"}).addClass("hide-overflow");
		} else {
			$("#postdivrich").animate({"height":437,"overflow":"visible"}).removeClass("hide-overflow");
		}
	});
});

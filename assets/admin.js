jQuery(document).ready(function($){
	function contentMaskMessage( classes, message ){
		$('.content-mask-admin-table').before('<div class="cm-message notice notice-'+ classes +'"><p>'+ message +'</p></div>');
	}

	$('.content-mask-admin-table-body').on( 'click', '.method svg', function(){
		var	$clicked     = $(this),
			restoreIcon  = $clicked.attr('class');
			postID       = $clicked.closest('tr').attr('data-attr-id'),
			currentState = $clicked.closest('tr').attr('data-attr-state'),
			newState     = currentState == 'enabled' ? 'disabled' : 'enabled';

		$clicked.closest('div').attr('class', 'cm-reloading');

		var data = {
			'action': 'toggle_content_mask',
			'postID': postID,
			'newState': newState,
			'currentState': currentState,
		};

		$.post(ajaxurl, data, function(response) {
			$('.cm-message').remove(); // Prevent weird interaction with existing messages
			var classes;

			if( response.status == 200 ){
				$clicked.attr('class', restoreIcon);
				$clicked.closest('tr').attr('data-attr-state', newState).toggleClass('disabled enabled');
				classes = 'info';
			} else if( response.status == 400 || response.status == 403 ){
				classes = 'error';
			}

			$clicked.closest('div').removeClass('cm-reloading')

			contentMaskMessage( classes, response.message );
		}, 'json');
	});

	if( $('#content_mask_enable').is(':checked') ){
		$('#postdivrich').css({'height': 0, 'overflow': 'hidden'}).addClass('hide-overflow');
	}

	$('#content_mask_enable').click(function(){
		if( !$(this).is(':checked') ){
			$('#postdivrich').animate({'height': 437, 'overflow': 'visible'}).removeClass('hide-overflow');
		}
	});
});
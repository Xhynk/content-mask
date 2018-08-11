jQuery(document).ready(function($){
	$('#content_mask_meta_box [name="content_mask_method"]').on('change', function(){
		if( $(this).val() == 'download' ){
			$('.content-mask-expiration-div').fadeIn();
		} else {
			$('.content-mask-expiration-div').fadeOut();
		}
	});

	function contentMaskMessage( classes, message ){
		$('.toplevel_page_content-mask #wpbody .wrap > h2').after('<div class="content-mask-message notice notice-'+ classes +'"><p>'+ message +'</p></div>');
	}

	$('#content-mask-list td.cache-expires').on( 'click', 'a', function(){
		$('.content-mask-message').remove();

		var	$clicked   = $(this),
			maskURL    = $clicked.closest('tr').find('td.mask-url a').attr('href'),
			transient  = $clicked.attr('data-transient'),
			postID     = $clicked.closest('tr').attr('data-attr-id'),
			cacheFor   = $clicked.closest('tr').find('td.mask-url a').text(),
			expiration = $clicked.attr('data-expiration'),
			$methodDiv = $clicked.closest('tr').find('td.method div'),
			expirationReadable = $clicked.attr('data-expiration-readable');

		$methodDiv.addClass('content-mask-reloading');

		var data = {
			'action': 'refresh_transient',
			'postID': postID,
			'expiration': expiration,
			'transient': transient,
			'maskURL': maskURL,
		};

		$.post(ajaxurl, data, function(response) {
			$('.content-mask-message').remove(); // Prevent weird interaction with existing messages
			var classes;

			if( response.status == 200 ){
				$('.content-mask-table-body tr td.cache-expires').each(function(){
					if( $(this).closest('tr').find('td.mask-url a').text() == cacheFor ){
						$(this).find('.transient-expiration').text( expirationReadable );
					}
				});
				classes = 'info';
			} else if( response.status == 400 || response.status == 403 ){
				classes = 'error';
			}

			$methodDiv.removeClass('content-mask-reloading');
			contentMaskMessage( classes, response.message );
		}, 'json');

		return false;
	});

	$('#content-mask-list .content-mask-table-body, #the-list .column-content-mask').on( 'click', '.method svg, .content-mask-method svg', function(){
		var	$clicked     = $(this),
			restoreIcon  = $clicked.attr('class');

		if( $clicked.closest('td').hasClass('method') ){
			var postID = $clicked.closest('tr').attr('data-attr-id');
			var stateController = $clicked.closest('tr');
		} else {
			var postID = $clicked.closest('tr').attr('id').replace('post-', '');
			var stateController = $clicked.closest('.content-mask-method');
		}

		var currentState = stateController.attr('data-attr-state');
		var newState     = currentState == 'enabled' ? 'disabled' : 'enabled';

		$clicked.closest('div').attr('class', 'content-mask-reloading');

		var data = {
			'action': 'toggle_content_mask',
			'postID': postID,
			'newState': newState,
			'currentState': currentState,
		};

		$.post(ajaxurl, data, function(response) {
			$('.content-mask-message').remove(); // Prevent weird interaction with existing messages
			var classes;

			if( response.status == 200 ){
				$clicked.attr('class', restoreIcon);
				stateController.attr('data-attr-state', newState).toggleClass('disabled enabled');
				classes = 'info';
			} else if( response.status == 400 || response.status == 403 ){
				classes = 'error';
			}

			$clicked.closest('div').removeClass('content-mask-reloading');

			contentMaskMessage( classes, response.message );
		}, 'json');
	});

	$('#content-mask-options .content-mask-checkbox').on( 'click', '.content-mask-check', function(){
		var	$clicked     = $(this).closest('.content-mask-checkbox'),
			currentState = $clicked.attr('data-attr');

		var data = {
			'action': 'toggle_visitor_tracking',
			'currentState': currentState,
		};

		$clicked.closest('.content-mask-option').addClass('content-mask-reloading');
		$.post(ajaxurl, data, function(response) {
			$('.content-mask-message').remove(); // Prevent weird interaction with existing messages
			
			if( response.status == 200 ){
				classes = 'info';
			} else if( response.status == 400 || response.status == 403 ){
				classes = 'error';
			}
			
			$('#content-mask-list').removeClass('tracking-enabled tracking-disabled');
			$('#content-mask-list').addClass('tracking-' + response.newState);

			$clicked.closest('.content-mask-option').removeClass('content-mask-reloading');
			$clicked.closest('.content-mask-option').find('.content-mask-value').text( response.newState );
			$clicked.attr('data-attr', response.newState );

			contentMaskMessage( classes, response.message );
		}, 'json');
	});

	$('#content-mask-list .content-mask-table-body').scroll(function(){
		var $scrolled = $(this);
		var tbody = $(this).find('tbody');

		if( ! $scrolled.hasClass('currently-loading') ){
			if( $(tbody).find('tr').length >= 20 && $(tbody).find('tr:last-child div').text() != 'No More Content Masks Found' ){
				if( $(this)[0].scrollHeight - $(this).scrollTop() <= $(this).outerHeight() ){
					$scrolled.addClass('currently-loading');

					$(tbody).append('<tr style="position: absolute; bottom: -40px; width: calc(100% - 18px);" class="content-mask-temp"><td><div class="content-mask-spinner"><div class="bounce1"></div><div class="bounce2"></div><div class="bounce3"></div></div></td></tr>');

					var data = {
						'action': 'load_more_pages',
						'offset': $(tbody).find('tr').length,
					};

					$.ajax({
						url: ajaxurl, 
						data: data,
						type: 'POST',
						dataType: 'json',
						success: function( response ){
							$('.content-mask-temp').html( '<h2><strong>Loading Completed.</strong></h2>' );
							$(tbody).append( response.message);
							setTimeout(function(){
								$('.content-mask-temp').fadeOut();
								$scrolled.removeClass('currently-loading');
							}, 250 );
						}
					});
				}
			}
		}
	});

	if( $('#content_mask_enable').is(':checked') ){
		$('#postdivrich').css({'height': 0, 'overflow': 'hidden'}).addClass('hide-overflow');
	}

	$('#content_mask_enable').click(function(){
		if( !$(this).is(':checked') ){
			$('#postdivrich').animate({'height': 416, 'overflow': 'visible'}).removeClass('hide-overflow');
			$('.gutenberg').addClass('content-mask-unchecked');
			$('.gutenberg .edit-post-visual-editor, .gutenberg .edit-post-text-editor').fadeIn();
			$('.content-mask-notice').fadeOut();
		}
	});
});

jQuery(window).load(function(){
	jQuery(document).ready(function($){
		if( $('.content-mask-enabled-page .override-gutenberg-notice' ).length > 0 ){
			var contentMaskNotice     = $('.override-gutenberg-notice' ).html();
			var contentMaskNoticeHTML = '<div class="notice notice-alt content-mask-notice notice-info">'+ contentMaskNotice +'</div>';

			$('.components-notice-list').prepend( contentMaskNoticeHTML );
		}
	});
});
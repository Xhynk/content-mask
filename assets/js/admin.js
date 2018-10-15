jQuery(document).ready(function($){
	/**
	 * Meta Box Change Functions
	 */
	$('#content_mask_meta_box [name="content_mask_method"]').on('change', function(){
		if( $(this).val() == 'download' ){
			$('.content-mask-expiration-div').fadeIn();
		} else {
			$('.content-mask-expiration-div').fadeOut();
		}
	});

	if( $('#content_mask_enable').is(':checked') ){
		$('#postdivrich').css({'height': 0, 'overflow': 'hidden'}).addClass('hide-overflow');
	}

	$('#content-mask-settings').on('click', '.content-mask-check', function(){
		if( !$(this).is(':checked') ){
			$('#postdivrich').animate({'height': 416, 'overflow': 'visible'}).removeClass('hide-overflow');
			$('.gutenberg').addClass('content-mask-unchecked');
			$('.gutenberg .edit-post-visual-editor, .gutenberg .edit-post-text-editor').fadeIn();
			$('.content-mask-notice').fadeOut();
		}
	});

	/**
	 * Insert Notices to Alert User of Actions
	 */
	function contentMaskMessage( classes, message ){
		$('#content-mask-message').remove(); //Prevent Duplicates

		$('body').append('<div id="content-mask-message"><div class="content-mask-message-content '+ classes +'">'+ message +'</div></div>');
		setTimeout(function(){
			$('#content-mask-message').fadeOut(function(){
				$(this).remove();
			});
		}, 1500);
	}

	/**
	 * Control Modal (currently only for Delete Mask)
	 */
	function insertContentMaskModal( postID, title, classes ){
		$('#content-mask-modal-container').remove(); // Prevent Duplicates

		var modal = '<div id="content-mask-modal-container">'+
    					'<div class="content-mask-modal '+ classes +'">'+
							'<svg class="icon content-mask-svg svg-trash" title="Delete Mask" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>'+
							'<h2>'+ title +'</h2>'+
							'<p>Remove the Content Mask from this?</p>'+
							'<button data-intent="confirm" data-action="delete-mask" data-id="'+ postID +'">Yes</button><button data-intent="cancel">No</button>'+
						'</div>'+
					'</div>';

		$('body').addClass('blur');
		$('body').append( modal );
	}

	$('#content-mask-pages').on( 'click', '.refresh-transient', function(){
		$('.content-mask-message').remove();

		var	$clicked   = $(this),
			maskURL    = $clicked.closest('tr').find('.info .meta a').text(),
			transient  = $clicked.attr('data-transient'),
			postID     = $clicked.closest('tr').attr('data-attr-id'),
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
				$('#content-mask-pages tr td.status').each(function(){
					if( $(this).closest('tr').find('.info .meta a').text() == maskURL ){
						$(this).find('.transient-expiration').removeClass('expired').text( expirationReadable );
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

	/**
	 * Insert Popup for Delete Mask
	 */
	$('#content-mask-pages').on( 'click', '.remove-mask', function(){
		var	$clicked   = $(this),
			$row       = $(this).closest('tr'),
			postID     = $clicked.closest('tr').attr('data-attr-id'),
			title      = $clicked.closest('tr').find('.info strong').text();
		
		insertContentMaskModal( postID, title, 'warning' );

		return false;
	});

	/**
	 * Handle Delete Mask Intent
	 */
	$('body').on('click', '[data-intent="cancel"]', function(){
		$('body').removeClass('blur');
		$('#content-mask-modal-container').fadeOut(function(){
			$(this).remove();
		});
		return false;
	});
	$('body').on('click', '[data-action="delete-mask"]', function(){
		var	$clicked   = $(this),
			postID     = $clicked.attr('data-id'),
			$row       = $('#content-mask-pages').find('tr[data-attr-id="'+ postID +'"]');

		var data = {
			'action': 'delete_content_mask',
			'postID': postID
		};

		$('body').removeClass('blur');
		$('#content-mask-modal-container').fadeOut(function(){
			$(this).remove();
			$row.addClass('deleting');
		});

		$.post(ajaxurl, data, function(response) {
			var classes;

			if( response.status == 200 ){
				$row.fadeOut();
				classes = 'info';
			} else if( response.status == 400 || response.status == 403 ){
				$row.addClass('deleting');
				classes = 'error';
			}
			
			contentMaskMessage( classes, response.message );
		}, 'json');

		return false;
	});

	/**
	 * Toggle Enabled/Disabled State
	 */
	$('#content-mask-pages, #the-list .column-content-mask').on( 'click', '.method div, .content-mask-method svg', function(){
		var	$clicked     = $(this),
			restoreIcon  = $clicked.attr('class'),
			stateController,
			postID;

		if( $clicked.closest('td').hasClass('method') ){
			// Content Mask Admin
			postID = $clicked.closest('tr').attr('data-attr-id');
			stateController = $clicked.closest('tr');
		} else {
			// Post/Page Edit List
			postID = $clicked.closest('tr').attr('id').replace('post-', '');
			stateController = $clicked.closest('.content-mask-method');
		}

		var currentState = stateController.attr('data-attr-state');
		var newState     = currentState == 'enabled' ? 'disabled' : 'enabled';

		$clicked.attr('class', 'content-mask-reloading');

		var data = {
			'action': 'toggle_content_mask',
			'postID': postID,
			'newState': newState,
			'currentState': currentState,
		};

		$.post(ajaxurl, data, function(response) {
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

	/**
	 * Toggle General Options
	 */
	$('.content-mask-admin .content-mask-checkbox').on( 'click', '.content-mask-check', function(){
		var	$clicked     = $(this),
			$label       = $clicked.closest('label'),
			currentState = $label.attr('data-attr'),
			optionName   = $label.find('input[type="checkbox"]').attr('name'),
			optionDisplayName = $label.find('.display-name').attr('aria-label').replace('Enable ', '');

		var data = {
			'action': 'toggle_content_mask_option',
			'optionName': optionName,
			'currentState': currentState,
			'optionDisplayName': optionDisplayName,
		};

		$clicked.addClass('content-mask-reloading');

		$.post(ajaxurl, data, function(response) {			
			if( response.status == 200 ){
				classes = 'info';
			} else if( response.status == 400 || response.status == 403 ){
				classes = 'error';
			}
			
			if( optionName == 'content_mask_tracking' ){
				$('#content-mask-list').removeClass('tracking-enabled tracking-disabled');
				$('#content-mask-list').addClass('tracking-' + response.newState);
			}

			$clicked.removeClass('content-mask-reloading');
			$clicked.closest('.content-mask-option').find('.content-mask-value').text( response.newState );
			$label.attr('data-attr', response.newState );

			contentMaskMessage( classes, response.message );
		}, 'json');
	});

	/**
	 * Update Code Editors
	 */
	$('#content-mask-advanced button').on( 'click', function(){
		var	$clicked = $(this),
			$wrap    = $clicked.closest('.option').find('.code-edit-wrapper'),
			editor   = $clicked.attr('data-editor'),
			value    = window[editor].codemirror.getValue(),
			label    = $clicked.text().replace('Save ', '');
			target   = $clicked.attr('data-target');

		var data = {
			'action': 'update_content_mask_option',
			'option': target,
			'value': value,
			'label': label,
		};

		$wrap.addClass('loading');

		$.post(ajaxurl, data, function(response) {			
			if( response.status == 200 ){
				classes = 'info';
			} else if( response.status == 400 || response.status == 403 ){
				classes = 'error';
			}

			contentMaskMessage( classes, response.message );
			$wrap.removeClass('loading');
			
			$wrap.addClass('saved');
			setTimeout(function(){
				$wrap.removeClass('saved');
			}, 1500);
		}, 'json');		
	});

	/**
	 * Dynamically Load more Content Masked Pages/Posts/CPTS
	 */
	$('#content-mask #load-more-masks').on( 'click', function(){
		var $tbody = $('#content-mask-pages').find('tbody');

		$tbody.append('<tr class="content-mask-temp"><td><div class="content-mask-spinner"><div class="bounce1"></div><div class="bounce2"></div><div class="bounce3"></div></div></td></tr>');

		var data = {
			'action': 'load_more_pages',
			'offset': $tbody.find('tr').length,
		};

		$.post(ajaxurl, data, function(response){
			$('.content-mask-temp').remove();
			$tbody.append( response.message );
			contentMaskMessage( 'info', 'Loading Completed' );

			if( response.notice != null && response.notice == 'no remaining' ){
				$('#load-more-masks').fadeOut(function(){
					$(this).remove();
				});
			}
		}, 'json');
	});

	/**
	 * Admin Panel Navigation
	 */
	$('#content-mask nav').on('click', 'li', function(){
		var $link  = $(this).find('a'),
			target = $link.attr('data-target'); 
		
		// Set Active
		$('#content-mask nav li').each(function(){
			$(this).find('a').removeClass('active');
		});
		$link.addClass('active');

		// Show/Hide Panels
		$('#content-mask .content-mask-panel.active').fadeOut(function(){
			$(this).removeClass('active');

			$('#'+target).fadeIn(function(){
				$(this).addClass('active');
			});
		});
	});

	/**
	 * Admin Mobile Menu
	 */

	 $('#mobile-nav-toggle').on('click', function(){
	 	$('#header-nav').slideToggle();
	 });
});

jQuery(window).load(function(){
	jQuery(document).ready(function($){
		if( $('.content-mask-enabled-page .override-gutenberg-notice' ).length > 0 ){
			var contentMaskNotice     = $('.override-gutenberg-notice' ).html();
			var contentMaskNoticeHTML = '<div class="components-notice notice notice-alt content-mask-notice notice-info">'+ contentMaskNotice +'</div>';

			$('.components-notice-list').prepend( contentMaskNoticeHTML );
		}
	});
});
<?php
/**
	* Plugin Name: Content Mask
	* Plugin URI: http://xhynk.com/content-mask/
	* Description: Embed external content into your site without complicated Domain Forwarding and Domain Masks.
	* Version: 1.1.2
	* Author: Alex Demchak
	* Author URI: github.com/xhynk
*/

class ContentMask {
	public static $content_mask_methods = array(
		'download',
		'iframe',
		'redirect'
	);

	public function __construct() {
		add_action( 'admin_head', array( $this, 'admin_js' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_css' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ), 1, 2 );
		add_action( 'save_post', array( $this, 'save_meta' ), 10, 1 );
		add_action( 'template_redirect', array( $this, 'process_page_request' ), 1, 2 );
	}

	public function validate_url( $url ){
		## Make sure this is a URL we're showing
		$url = filter_var( esc_url( $url ), FILTER_SANITIZE_URL );

		if( !filter_var( $url, FILTER_VALIDATE_URL ) === false ){
			return true;
		} else {
			return false;
		}
	}

	public function get_page_content( $url ){
		## Download the Content Mask URL into the page content, overriding everything.
		
		// Make sure we're still displaying an actual URL
		if( $this->validate_url( $url ) === true ){
			// Cache the results in a transient to prevent hammering the host URL
			$transient_name = 'content_mask-'. strtolower( preg_replace( "/[^a-z0-9]/", '', $url ) );

			if( false === ( $transient = get_transient( $transient_name ) ) ){
				$cookie_array	= array();

				foreach( $_COOKIE as $key => $val ){
					if( $key != 'Array' ){
						$cookie_array[] = $key . '=' . $val;
					}
				}

				$body = wp_remote_retrieve_body( wp_remote_get( $url, array( 'cookies' => $cookie_array ) ) );

				set_transient( $transient_name, $body, 14400 );

				return $body;
			} else {
				return $transient;
			}
		} else {
			// Not a valid URL.
			return;
		}
	}

	public function get_page_iframe( $url ){
		## Replace the content with a full size iframe, useful if a URL has relatively URLs or only serves to whitelisted IPs
		if( $this->validate_url( $url ) === true ){
			if( has_site_icon() ) $favicon = '<link class="wp_favicon" href="'. get_site_icon_url() .'" rel="shortcut icon"/>';

			return '<!DOCTYPE html>
				<head>
					'.$favicon.'
					<style>
						body {
							margin: 0;
						}
						iframe {
							display: block;
							border: none;
							height: 100vh;
							width: 100vw;
						}
					</style>
					<meta name="viewport" content="width=device-width, initial-scale=1">
				</head>
				<body>
					<script type="text/javascript" src="'. plugin_dir_url( __FILE__ ) .'js/update_meta_tags.js"></script>
					<iframe width="100%" height="100%" src="'. esc_url( $url ) .'" frameborder="0" allowfullscreen></iframe>
				</body>
			</html>';
		} else {
			// Not a valid URL
			return;
		}
	}

	public function show_post( $post_id ){
		$url = esc_url( get_post_meta( $post_id, 'content_mask_url', true ) );
		
		if( $this->validate_url( $url ) === true ){
			$method = sanitize_text_field( get_post_meta( $post_id, 'content_mask_method', true ) );

			if( $method === 'download' ){
				echo $this->get_page_content( $url );
			} elseif( $method === 'iframe' ){
				echo $this->get_page_iframe( $url );
			} elseif( $method === 'redirect' ){
				wp_redirect( $url, 301 );
			} else {
				// Default to Download
				echo $this->get_page_content( $url );
			}
		} else {
			die( 'Content Mask URL is invalid' );
		}

		exit(); // Too far!
	}

	public function process_page_request() {
		## Let's see if we should do anything about this page request
		global $post;

		foreach( get_post_custom() as $key => $val )
			${$key} = $val[0];

		if( filter_var( $content_mask_enable, FILTER_VALIDATE_BOOLEAN ) ){
			// One of our Content Mask pages that's turned ON, continue

			// Sanitize the URL displayed
			if( $this->validate_url( $content_mask_url ) === true ){
				// It's a valid URL, do the thing
				$this->show_post( $post->ID );
			} else {
				// It's not a valid URL, display an error message;
				add_action( 'wp_footer', function(){
					if( is_user_logged_in() ){
						foreach( get_post_custom() as $key => $val )
							${$key} = $val[0];

						echo '<div style="border-left: 4px solid #c00; box-shadow: 0 5px 12px -4px rgba(0,0,0,.5); background: #fff; padding: 12px 24px; z-index: 16777271; position: fixed; top: 42px; left: 10px; right: 10px; border-">It looks like you have enabled a Content Mask on this post, but don\'t have a valid URL. <a style="display: inline-block; text-decoration: none; font-size: 13px; line-height: 26px; height: 28px; margin: 0; padding: 0 10px 1px; cursor: pointer; border-width: 1px; border-style: solid; -webkit-appearance: none; border-radius: 3px; white-space: nowrap; box-sizing: border-box; background: #0085ba; border-color: #0073aa #006799 #006799; box-shadow: 0 1px 0 #006799; color: #fff; text-decoration: none; text-shadow: 0 -1px 1px #006799, 1px 0 1px #006799, 0 1px 1px #006799, -1px 0 1px #006799; float: right;" class="wp-core-ui button primary" href="'. get_edit_post_link() .'#content_mask_url">Edit Content Mask</a></div>';
					}
				} );

				return; // Failed URL test
			}
		} else {
			return; // Failed to have Content Mask Enabled set to `true`
		}

		return; // Return the original request in all other instances
	}

	public function content_mask_meta_box(){
		foreach( get_post_custom() as $key => $val ){
			${$key} = $val[0];
		} ?>
		<div style="width: 50px; float: left; margin-right: 12px;">
			<label class="cm_toggle_switch" for="content_mask_enable">
				<strong style="margin-bottom: 6px; display: inline-block;">&nbsp;</strong>
				<input type="checkbox" name="content_mask_enable" id="content_mask_enable" <?php if( filter_var( $content_mask_enable, FILTER_VALIDATE_BOOLEAN ) ){ echo 'checked="checked"'; } ?> /><span class="cm_toggle_indicator"></span>
			</label>
		</div>
		<div style="width: calc( 50% - 6px ); float: left; margin-right: 12px;">
			<label>
				<strong style="margin-bottom: 6px; display: inline-block;">Content Mask URL:</strong>
				<input type="text" class="widefat" name="content_mask_url" id="content_mask_url" value="<?php echo esc_url( $content_mask_url ); ?>" />
			</label>
		</div>
		<div style="width: calc( 25% - 6px ); float: left;">
			<label>
				<strong style="margin-bottom: 6px; display: inline-block;">Content Mask Method:</strong>
				<select class="widefat" name="content_mask_method">
					<?php foreach( $this::$content_mask_methods as $method ){
						$selected = $content_mask_method === $method ? 'selected="selected"' : '';
						echo '<option value="'. $method .'" '. $selected .'>'. ucwords( $method ) .'</option>';
					} ?>
				</select>
			</label>
		</div>
		<div style="clear: both; height: 24px;"></div>
	<?php }

	public function add_meta_boxes(){
		add_meta_box( 'content_mask_meta_box', 'Content Mask Settings', array( $this, 'content_mask_meta_box' ), null, 'normal', 'high' );
	}

	public function sanitize_url( $input_url ){
		## Sanitize text inputs for URL values
		if( isset( $input_url ) ){
			// Not Empty, Sanitize It
			$input_url = sanitize_text_field( $input_url );

			// Check to see if a protocol is set
			if( !strpos( $input_url, '://') ){
				// No protocol is set, let's set it to `http://` because any
				// self-respecting `https://` site will forward `http` to `https`
				$input_url = 'http://' . $input_url;
			}

			// Check to see if a TLD is set, filter_var( $url, FILTER_VALIDATE_URL ) doesn't check for one.
			if( !strpos( $input_url, '.' ) ){
				return false;
			}
		
			if( substr( $input_url, 0, 4) == 'http' && filter_var( $input_url, FILTER_VALIDATE_URL ) ){
				// It should be a valid URL with a good protocol
				return $input_url;
			} else {
				// Boo, a bad protocol was used or it's not a URL
				return false;
			}
		}
	}

	public function sanitize_select( $input, $valid_values ){
		## Sanitize Select/Dropdowns for only the expected values
		if( isset( $input ) ){
			// Not Empty, Sanitize It
			$input = sanitize_text_field( $input );
		
			if( in_array( $input, $valid_values ) ){
				// It's an expected value and wasn't manually added
				return $input;
			} else {
				// Unexpected value, probably manually added
				return false;
			}
		}
	}

	public function sanitize_checkbox( $input ){
		## Sanitize Checkboxes to be only true or false
		if( isset( $input ) ){
			// Not Empty, but we only want boolean values
			if( filter_var( $input, FILTER_VALIDATE_BOOLEAN ) ){
				// A boolean "true" value was set, (1, '1', 01, '01', 'on', 'yes', true, 'true') etc.
				return true;
			} else {
				// A boolena "false" value was set -OR- a janky value we don't want was set, unset it.
				return false;
			}
		} else {
			// Wasn't set, so unset it if it was set prior
			return false;
		}
	}

	public function save_meta( $post_id ){
		global $_POST;

		$content_mask_url	 = @$_POST['content_mask_url'];
		$content_mask_method = @$_POST['content_mask_method'];
		$content_mask_enable = @$_POST['content_mask_enable'];

		## Sanitize

		// Content Mask URL - should only allow URLs, nothing else, otherwise set it to empty/false
		if( isset( $content_mask_url ) )
			update_post_meta( $post_id, 'content_mask_url', $this->sanitize_url( $content_mask_url ) );

		// Content Mask Method - Should be 1 of 3 values, otherwise set it back to empty/false
		if( isset( $content_mask_method ) )
			update_post_meta( $post_id, 'content_mask_method', $this->sanitize_select( $content_mask_method, $this::$content_mask_methods ) );

		// Content Mask Enable - Being tricky to unset, so we update it always and just set it to true/false based on whether or not it was empty
		update_post_meta( $post_id, 'content_mask_enable', $this->sanitize_checkbox( $content_mask_enable ) );

		// Delete the cached 'download' copy any time this Page, Post or Custom Post Type is updated.
		delete_transient( 'content_mask-'. strtolower( preg_replace( "/[^a-z0-9]/", '', $content_mask_url ) ) );
	}

	public function admin_js(){
		echo '<script type="text/javascript">
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
		</script>';
	}

	public function admin_css(){
		echo '<style>
			.hide-overflow {
				overflow: hidden !important;
			}

			.cm_toggle_switch {
				clear: both;
				width: 100%;
				display: block;
				margin-bottom: 15px;
			}

			.cm_toggle_switch > input {
				display: none !important;
				width: 0;
				height: 0;
				overflow: hidden;
			}

			.cm_toggle_switch > input:checked ~ .cm_toggle_indicator {
				color: #fff;
				background-color: #0074d9;
			}

			.cm_toggle_switch > input:active ~ .cm_toggle_indicator {
				color: #fff;
				background-color: #84c6ff;
			}

			.cm_toggle_indicator {
				position: absolute;
				top: 2px;
				left: 0;
				display: block;
				width: 1rem;
				height: 1rem;
				font-size: 65%;
				line-height: 1rem;
				color: #eee;
				text-align: center;
				-webkit-user-select: none;
				   -moz-user-select: none;
					-ms-user-select: none;
						user-select: none;
				background-color: #eee;
				background-repeat: no-repeat;
				background-position: center center;
				background-size: 50% 50%;
			}

			.cm_toggle_switch .cm_toggle_indicator {
				border-radius: 20px;
			}

			.cm_toggle_indicator:hover {
				transition: .25s all ease-out;
				background-color: rgba(0, 153, 213,.25);
			}

			.cm_toggle_switch input:checked ~ .cm_toggle_indicator {
				background-position: left center;
				background-image: url(data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0idXRmLTgiPz4NCjwhLS0gR2VuZXJhdG9yOiBBZG9iZSBJbGx1c3RyYXRvciAxNy4xLjAsIFNWRyBFeHBvcnQgUGx1Zy1JbiAuIFNWRyBWZXJzaW9uOiA2LjAwIEJ1aWxkIDApICAtLT4NCjwhRE9DVFlQRSBzdmcgUFVCTElDICItLy9XM0MvL0RURCBTVkcgMS4xLy9FTiIgImh0dHA6Ly93d3cudzMub3JnL0dyYXBoaWNzL1NWRy8xLjEvRFREL3N2ZzExLmR0ZCI+DQo8c3ZnIHZlcnNpb249IjEuMSIgaWQ9IkxheWVyXzEiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgeG1sbnM6eGxpbms9Imh0dHA6Ly93d3cudzMub3JnLzE5OTkveGxpbmsiIHg9IjBweCIgeT0iMHB4Ig0KCSB2aWV3Qm94PSIwIDAgOCA4IiBlbmFibGUtYmFja2dyb3VuZD0ibmV3IDAgMCA4IDgiIHhtbDpzcGFjZT0icHJlc2VydmUiPg0KPHBhdGggZmlsbD0iI0ZGRkZGRiIgZD0iTTYuNCwxTDUuNywxLjdMMi45LDQuNUwyLjEsMy43TDEuNCwzTDAsNC40bDAuNywwLjdsMS41LDEuNWwwLjcsMC43bDAuNy0wLjdsMy41LTMuNWwwLjctMC43TDYuNCwxTDYuNCwxeiINCgkvPg0KPC9zdmc+DQo=);
			}

			.cm_toggle_switch input:indeterminate ~ .cm_toggle_indicator {
				background-color: #0074d9;
				background-image: url(data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0idXRmLTgiPz4NCjwhLS0gR2VuZXJhdG9yOiBBZG9iZSBJbGx1c3RyYXRvciAxNy4xLjAsIFNWRyBFeHBvcnQgUGx1Zy1JbiAuIFNWRyBWZXJzaW9uOiA2LjAwIEJ1aWxkIDApICAtLT4NCjwhRE9DVFlQRSBzdmcgUFVCTElDICItLy9XM0MvL0RURCBTVkcgMS4xLy9FTiIgImh0dHA6Ly93d3cudzMub3JnL0dyYXBoaWNzL1NWRy8xLjEvRFREL3N2ZzExLmR0ZCI+DQo8c3ZnIHZlcnNpb249IjEuMSIgaWQ9IkxheWVyXzEiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgeG1sbnM6eGxpbms9Imh0dHA6Ly93d3cudzMub3JnLzE5OTkveGxpbmsiIHg9IjBweCIgeT0iMHB4Ig0KCSB3aWR0aD0iOHB4IiBoZWlnaHQ9IjhweCIgdmlld0JveD0iMCAwIDggOCIgZW5hYmxlLWJhY2tncm91bmQ9Im5ldyAwIDAgOCA4IiB4bWw6c3BhY2U9InByZXNlcnZlIj4NCjxwYXRoIGZpbGw9IiNGRkZGRkYiIGQ9Ik0wLDN2Mmg4VjNIMHoiLz4NCjwvc3ZnPg0K);
			}

			.cm_toggle_indicator {
				width: 38px;
				height: 18px;
				display: inline-block;
				float: left;
				position: relative;
				transition: .25s background ease-out;
				top: 5px;
				border: none;
				box-shadow: inset 0 2px 2px rgba(0,0,0,.25);
			}

			.cm_toggle_switch input:checked ~ .cm_toggle_indicator {
				background-color: #0099d5;
				width: 38px;
				height: 18px;
			}

			.cm_toggle_switch input:checked ~ .cm_toggle_indicator:before {
				left: 0;
				transform: translateX(20px);
			}

			.cm_toggle_indicator:before {
				transition: .25s all ease-out;
				content: "=";
				background: #fff;
				width: 16px;
				height: 16px;
				padding: 2px;
				position: absolute;
				left: -2px;
				top: -2px;
				border-radius: 20px;
				border: 1px solid #bbb;
				background: #fff;
				color: #999;
				font-size: 10px;
				line-height: initial;
			}
		</style>';
	}
}

$cm = new ContentMask();
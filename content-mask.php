<?php
/**
	* Plugin Name: Content Mask
	* Plugin URI: http://xhynk.com/content-mask/
	* Description: Embed external content into your site without complicated Domain Forwarding and Domain Masks.
	* Version: 1.1.4.2
	* Author: Alex Demchak
	* Author URI: https://github.com/xhynk
*/
class ContentMask {
	public static $content_mask_methods = array(
		'download',
		'iframe',
		'redirect'
	);

	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ), 1, 2 );
		add_action( 'save_post', array( $this, 'save_meta' ), 10, 1 );
		add_action( 'template_redirect', array( $this, 'process_page_request' ), 1, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// Elegant Theme's Bloom is being a turd, needs to be unhooked on Content Mask pages.
		add_action( 'wp', function(){
			global $et_bloom;

			foreach( get_post_custom() as $key => $val ) ${$key} = $val[0];

			if( filter_var( $content_mask_enable, FILTER_VALIDATE_BOOLEAN ) ){
				remove_action( 'wp_footer', array( $et_bloom, 'display_flyin' ) );
				remove_action( 'wp_footer', array( $et_bloom, 'display_popup' ) );
			}
		}, 11 );
	}

	public function enqueue_admin_assets( $hook ){
		if( $hook == 'post.php' || $hook = 'post-new.php' ){
			// Scripts
			wp_enqueue_script( 'content-mask-admin', plugins_url( '/assets/admin.js', __FILE__ ), array( 'jquery' ), '1.0', true );

			// Styles
			wp_enqueue_style( 'simple-line-icons', 'https://cdnjs.cloudflare.com/ajax/libs/simple-line-icons/2.3.2/css/simple-line-icons.min.css' );
			wp_enqueue_style( 'content-mask-admin', plugins_url( '/assets/admin.css', __FILE__ ) );
		}
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

			/* If a URL doesn't have a protocol, we force http:// on it since not all
			 * sites will be secured, but many secure sites forward to https://. HOWEVER
			 * insecure iframes won't get displayed on secured sites. So we force change
			 * it to https:// - if it doesn't show up then it wouldn't have shown up any-
			 * ways due to being insecure. Don't do this for the `download` method.
			 */
			$url = is_ssl() ? str_replace( 'http://', 'https://', esc_url( $url ) ) : esc_url( $url );

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
					<iframe width="100%" height="100%" src="'. $url .'" frameborder="0" allowfullscreen></iframe>
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

			exit();

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
		<div class="cm_override">
			<div class="cm-enable-container">
				<label class="cm_checkbox" for="content_mask_enable">
					<span aria-label="Enable Content Mask"></span>
					<input type="checkbox" name="content_mask_enable" id="content_mask_enable" <?php if( filter_var( $content_mask_enable, FILTER_VALIDATE_BOOLEAN ) ){ echo 'checked="checked"'; } ?> />
					<span class="cm_check">
						<svg class="icon" aria-hidden="true" data-fa-processed="" data-prefix="fas" data-icon="check" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path fill="currentColor" d="M173.898 439.404l-166.4-166.4c-9.997-9.997-9.997-26.206 0-36.204l36.203-36.204c9.997-9.998 26.207-9.998 36.204 0L192 312.69 432.095 72.596c9.997-9.997 26.207-9.997 36.204 0l36.203 36.204c9.997 9.997 9.997 26.206 0 36.204l-294.4 294.401c-9.998 9.997-26.207 9.997-36.204-.001z"></path></svg>
					</span>
				</label>
			</div>
			<div class="cm-method-container">
				<div class="cm_select">
					<input type="radio" name="content_mask_method" class="cm_select_toggle">
					<i class="toggle icon icon-arrow-down"></i>
					<i class="toggle icon icon-arrow-up"></i>
					<span class="placeholder">Choose a Method...</span>
					<label class="option">
						<input type="radio" <?php if( $content_mask_method === 'download' ) { echo 'checked="checked"'; } ?> value="download" name="content_mask_method">
						<span class="title"><i class="icon icon-cloud-download"></i>Download</span>
					</label>
					<label class="option">
						<input type="radio" <?php if( $content_mask_method === 'iframe' ) { echo 'checked="checked"'; } ?> value="iframe" name="content_mask_method">
						<span class="title"><i class="icon icon-frame"></i>Iframe</span>
					</label>
					<label class="option">
						<input type="radio" <?php if( $content_mask_method === 'redirect' ) { echo 'checked="checked"'; } ?> value="redirect" name="content_mask_method">
						<span class="title"><i class="icon icon-share-alt"></i>Redirect (301)</span>
					</label>
				</div>
			</div>
			<div class="cm-url-container">
				<div class="cm_text hide-overflow">
					<span aria-label="Content Mask URL"></span>
					<input type="text" class="widefat" name="content_mask_url" id="content_mask_url" placeholder="Content Mask URL" value="<?php echo esc_url( $content_mask_url ); ?>" />
				</div>
			</div>
			<div style="clear: both; height: 24px;"></div>
		</div>
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
}

add_action( 'plugins_loaded', function(){
	$cm = new ContentMask();
});

<?php
/**
	* Plugin Name:	Content Mask
	* Plugin URI:	http://xhynk.com/content-mask/
	* Description:	Easily embed external content into your website without complicated Domain Forwarders, Domain Masks, APIs or Scripts
	* Version:		1.2.1
	* Author:		Alex Demchak
	* Author URI:	https://github.com/xhynk
*/

class ContentMask {
	private static $instance;

	public static $label	= 'Content Mask';
	public static $lc_label	= 'content-mask';

	public static $content_mask_methods = array(
		'download',
		'iframe',
		'redirect'
	);

	public static function get_instance() {
		if( null == self::$instance ){
			self::$instance = new ContentMask();
		}
		return self::$instance;
	}

	public function __construct(){
		add_action( 'save_post', array( $this, 'save_meta' ), 10, 1 );
		add_action( 'admin_menu', array( $this, 'add_overview_menu' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ), 1, 2 );
		add_action( 'template_redirect', array( $this, 'process_page_request' ), 1, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'global_admin_assets' ) );
		add_action( 'wp_ajax_toggle_content_mask', array( $this, 'toggle_content_mask' ) );

		// Elegant Theme's "Bloom" isn't playing nicely and is being hooked below Download and Iframe content
		add_action( 'wp', function(){
			if( !is_admin() ){
				global $et_bloom;
				$content_mask_enable = get_post_meta( $post->ID, 'content_mask_enable', true );

				if( filter_var( $content_mask_enable, FILTER_VALIDATE_BOOLEAN ) ){
					remove_action( 'wp_footer', array( $et_bloom, 'display_flyin' ) );
					remove_action( 'wp_footer', array( $et_bloom, 'display_popup' ) );
				}
			}
		}, 11 );
	}

	public function issetor( &$var, $default = false ){
		return isset( $var ) ? $var : $default;
	}

	public function add_overview_menu(){
		add_menu_page(
			$this::$label,
			$this::$label,
			'edit_posts',
			$this::$lc_label,
			array( $this, 'admin_overview' ),
			plugins_url( "{$this::$lc_label}/assets/icon-solid.png" )
		);
	}

	public function admin_overview(){
		if( !current_user_can( 'edit_posts' ) ){
			wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
		} else {
			$args = array(
				'post_status' => array( 'publish', 'draft', 'pending', 'privatex' ),
				'post_type'   => get_post_types( '', 'names' ),
				'meta_query'  => array(
					array(
						'key'     => 'content_mask_url',
						'value'   => '',
						'compare' => '!=',
					),
				),
			);

			if( !current_user_can( 'edit_others_posts' ) ){
				$args['perm'] = 'editable';
			}

			$query = new WP_Query( $args );
		?>
			<div class="wrap">
				<h2><?php echo $this::$label; ?></h2>
				<div class="<?php echo $this::$lc_label; ?>-admin-table">
					<div class="<?php echo $this::$lc_label; ?>-admin-table-header"></div>
					<div class="<?php echo $this::$lc_label; ?>-admin-table-body">
						<table cellspacing="0">
							<thead>
								<tr>
									<th class="method"><div>Method</div></th>
									<th class="title"><div>Title</div></th>
									<th class="url"><div>Mask URL</div></th>
									<th class="post-type"><div>Post Type</div></th>
									<th class="edit"><div>Edit</div></th>
									<th class="view"><div>View</div></th>
								</tr>
								<tr class="invisible">
									<th>Method</th>
									<th>Title</th>
									<th>Mask URL</th>
									<th>Post Type</th>
									<th>Edit</th>
									<th>View</th>
								</tr>
							</thead>
							<tbody>
								<?php if( $query->have_posts() ){ ?>
									<?php while( $query->have_posts() ){ ?>
										<?php
											$query->the_post();
											$enabled = filter_var( $this->issetor( get_post_meta( get_the_ID(), 'content_mask_enable', true ) ), FILTER_VALIDATE_BOOLEAN ) ? 'enabled' : 'disabled'
										?>
										<tr data-attr-id="<?php echo get_the_ID(); ?>" data-attr-state="<?php echo $enabled; ?>" class="<?php echo $enabled; ?>">
											<td class="method"><div><?php
												$content_mask_method = $this->issetor( get_post_meta( get_the_ID(), 'content_mask_method', true ) );
												if( $content_mask_method === 'download' ) { echo '<i title="Download" class="icon icon-cloud-download"></i>'; }
												else if( $content_mask_method === 'iframe' ) { echo '<i title="Iframe" class="icon icon-frame"></i>'; }
												else if( $content_mask_method === 'redirect' ) { echo '<i title="Redirect (301)" class="icon icon-share-alt"></i>'; }
											?></div></td>
											<td class="title"><div><?php echo get_the_title(); ?></div></td>
											<td class="url"><div><?php echo $this->issetor( get_post_meta( get_the_ID(), 'content_mask_url', true ) ); ?></div></td>
											<td class="post-type"><div data-post-status="<?php echo get_post_status(); ?>"><?php echo get_post_type(); ?></div></td>
											<td class="edit"><div><a class="wp-core-ui button" href="<?php echo get_edit_post_link(); ?>">Edit</a></div></td>
											<td class="view"><div><a target="_blank" class="wp-core-ui button-primary" href="<?php echo get_permalink(); ?>">View</a></div></td>
										</tr>
									<?php } ?>
								<?php } else { ?>
									<tr>
										<td><div>No <?php echo $this::$label; ?>s Found</div></td>
									</tr>
								<?php } ?>
							</tbody>
						</table>
					</div>
				</div>
			</div>
		<?php }
	}

	public function enqueue_admin_assets( $hook ){
		$hook_array = array(
			'post.php',
			'post-new.php',
			"toplevel_page_{$this::$lc_label}",
		);

		if( in_array( $hook, $hook_array ) ){
			// Scripts
			wp_enqueue_script( "{$this::$lc_label}-admin", plugins_url( '/assets/admin.min.js', __FILE__ ), array( 'jquery' ), '1.0', true );

			// Styles
			wp_enqueue_style( 'simple-line-icons', 'https://cdnjs.cloudflare.com/ajax/libs/simple-line-icons/2.3.2/css/simple-line-icons.min.css' );
			wp_enqueue_style( "{$this::$lc_label}-admin", plugins_url( '/assets/admin.min.css', __FILE__ ) );
		}
	}

	public function global_admin_assets(){
		echo "<style>
			#adminmenu #toplevel_page_{$this::$lc_label} img { padding: 0; }
			#adminmenu #toplevel_page_{$this::$lc_label} .current img { opacity: 1; }
		</style>";
	}

	public function toggle_content_mask() {
		foreach( $_POST as $key => $val ) ${$key} = $val;
		$response = array();

		if( $newState == 'enabled' ){
			$_newState     = true;
			$_currentState = false;
		} else if( $newState == 'disabled' ){
			$_newState     = false;
			$_currentState = true;
		} else {
			$response['status']  = 403;
			$response['message'] = 'Unauthorized values detected';

			echo json_encode( $response );
			wp_die();
		}

		if( update_post_meta( $postID, 'content_mask_enable', $_newState, $_currentState ) ){
			$response['status']  = 200;
			$response['message'] = $this::$label. ' for <strong>'. get_the_title( $postID ) .'</strong> has been <strong>'. $newState .'</strong>';
		} else {
			$response['status']  = 400;
			$response['message'] = 'Request failed.';
		}

		echo json_encode( $response );
		wp_die();
	}

	public function validate_url( $url ){
		$url = filter_var( esc_url( $url ), FILTER_SANITIZE_URL );

		return !filter_var( $url, FILTER_VALIDATE_URL ) === false ? true : false;
	}

	public function replace_relative_urls( $url, $str, $protocol_relative = true ){
		if( $this->validate_url( $url ) ){
			$url = ( $protocol_relative === true ) ? str_replace( array( 'http://', 'https://' ), '//', $url ) : $url;
			$url = ( substr( $url, -1 ) === '/' ) ? substr( $url, 0, -1 ) : $url;

			return preg_replace('~(?:src|action|href)=[\'"]\K/(?!/)[^\'"]*~', "$url$0", $str);
		} else {
			return false;
		}
	}

	public function get_page_content( $url ){
		## Download the Content Mask URL into the page content, overriding everything.
		if( $this->validate_url( $url ) === true ){
			$transient_name = 'content_mask-'. strtolower( preg_replace( "/[^a-z0-9]/", '', $url ) );

			if( false === ( $transient = get_transient( $transient_name ) ) ){
				$body = wp_remote_retrieve_body( wp_remote_get( $url ) );
				$body = $this->replace_relative_urls( $url, $body );

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

			/**
			 * If a URL doesn't have a protocol, we force http:// on it since not all
			 * sites will be secured, but many secure sites forward to https://. HOWEVER
			 * insecure iframes won't get displayed on secured sites. So we force change
			 * it to https:// - if it doesn't show up then it wouldn't have shown up any-
			 * ways due to being insecure.
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
			} else if( $method === 'iframe' ){
				echo $this->get_page_iframe( $url );
			} else if( $method === 'redirect' ){
				wp_redirect( $url, 301 );
			} else {
				// Default to Download
				echo $this->get_page_content( $url );
			}

			exit();
		} else {
			wp_die( "{$this::$label} URL is invalid" );
		}

		exit(); // Too far!
	}

	public function process_page_request() {
		## Let's see if we should do anything about this page request
		global $post;

		// Not singular, don't display (such as archive pages, post lists, etc. )
		if( !is_singular() ) return;

		// 404 has no custom fields
		if( is_404() ) return;

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

						echo '<div style="border-left: 4px solid #c00; box-shadow: 0 5px 12px -4px rgba(0,0,0,.5); background: #fff; padding: 12px 24px; z-index: 16777271; position: fixed; top: 42px; left: 10px; right: 10px;">It looks like you have enabled a '. $this::$label .' on this post, but don\'t have a valid URL. <a style="display: inline-block; text-decoration: none; font-size: 13px; line-height: 26px; height: 28px; margin: 0; padding: 0 10px 1px; cursor: pointer; border-width: 1px; border-style: solid; -webkit-appearance: none; border-radius: 3px; white-space: nowrap; box-sizing: border-box; background: #0085ba; border-color: #0073aa #006799 #006799; box-shadow: 0 1px 0 #006799; color: #fff; text-decoration: none; text-shadow: 0 -1px 1px #006799, 1px 0 1px #006799, 0 1px 1px #006799, -1px 0 1px #006799; float: right;" class="wp-core-ui button primary" href="'. get_edit_post_link() .'#content_mask_url">Edit '. $this::$label .'</a></div>';
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
		}

		$this->issetor( $content_mask_url, '' );
		$this->issetor( $content_mask_enable, '' );
		$this->issetor( $content_mask_method, '' );
		?>
		<div class="cm_override">
			<div class="cm-enable-container">
				<label class="cm_checkbox" for="content_mask_enable">
					<span aria-label="Enable <?php echo $this::$label; ?>"></span>
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
					<span aria-label="<?php echo $this::$label; ?> URL"></span>
					<input type="text" class="widefat" name="content_mask_url" id="content_mask_url" placeholder="<?php echo $this::$label; ?> URL" value="<?php echo esc_url( $content_mask_url ); ?>" />
				</div>
			</div>
			<div style="clear: both; height: 24px;"></div>
		</div>
	<?php }

	public function add_meta_boxes(){
		add_meta_box( 'content_mask_meta_box', "{$this::$label} Settings", array( $this, 'content_mask_meta_box' ), null, 'normal', 'high' );
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

			if( substr( $input_url, 0, 4) === 'http' && filter_var( $input_url, FILTER_VALIDATE_URL ) ){
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

add_action( 'plugins_loaded', array( 'ContentMask', 'get_instance' ) );
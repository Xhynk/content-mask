<?php
/**
	* Plugin Name:	Content Mask
	* Plugin URI:	http://xhynk.com/content-mask/
	* Description:	Easily embed external content into your website without complicated Domain Forwarders, Domain Masks, APIs or Scripts
	* Version:		1.2.2
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
				global $et_bloom, $post;
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
				<h2><?= $this::$label; ?></h2>
				<div class="<?= $this::$lc_label; ?>-admin-table">
					<div class="<?= $this::$lc_label; ?>-admin-table-header"></div>
					<div class="<?= $this::$lc_label; ?>-admin-table-body">
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
										<tr data-attr-id="<?= get_the_ID(); ?>" data-attr-state="<?= $enabled; ?>" class="<?= $enabled; ?>">
											<td class="method"><div><?php
												$content_mask_method = $this->issetor( get_post_meta( get_the_ID(), 'content_mask_method', true ) );
												if( $content_mask_method === 'download' ) { echo $this->display_svg( 'download', 'icon', 'title="Download"' ); }
												else if( $content_mask_method === 'iframe' ) { echo $this->display_svg( 'iframe', 'icon', 'title="Iframe"' ); }
												else if( $content_mask_method === 'redirect' ) { echo $this->display_svg( 'redirect', 'icon', 'title="Redirect (301)"' ); }
											?></div></td>
											<td class="title"><div><?= get_the_title(); ?></div></td>
											<td class="url"><div><?= $this->issetor( get_post_meta( get_the_ID(), 'content_mask_url', true ) ); ?></div></td>
											<td class="post-type"><div data-post-status="<?= get_post_status(); ?>"><?= get_post_type(); ?></div></td>
											<td class="edit"><div><a class="wp-core-ui button" href="<?= get_edit_post_link(); ?>">Edit</a></div></td>
											<td class="view"><div><a target="_blank" class="wp-core-ui button-primary" href="<?= get_permalink(); ?>">View</a></div></td>
										</tr>
									<?php } ?>
								<?php } else { ?>
									<tr>
										<td><div>No <?= $this::$label; ?>s Found</div></td>
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
			$assets_dir = plugins_url( '/assets', __FILE__ );

			// Scripts
			wp_enqueue_script( "{$this::$lc_label}-admin", $assets_dir.'/admin.min.js', array( 'jquery' ), filemtime( plugin_dir_path( __FILE__ ) . 'assets/admin.min.js' ), true );

			// Styles
			wp_enqueue_style( "{$this::$lc_label}-admin", $assets_dir.'/admin.min.css', array(), filemtime( plugin_dir_path( __FILE__ ) . 'assets/admin.min.css' ) );
		}
	}

	public function global_admin_assets(){
		echo "<style>
			#adminmenu #toplevel_page_{$this::$lc_label} img { padding: 0; }
			#adminmenu #toplevel_page_{$this::$lc_label} .current img { opacity: 1; }
		</style>";
	}

	public function display_svg( $icon = '', $class = '', $attr = '' ){
		if( $icon == 'download' ) return '<svg class="'. $class .' content-mask-svg svg-download" viewBox="0 0 1024 768"><path d="M602 64q49 0 91 17 43 17 77 51 35 34 53 76 15 34 17 75l2 32 27 17q32 21 55 54l3 5q16 25 25 52 8 27 8 57 0 42-15 78-16 36-46.5 66T831 689t-80 15H243q-37 0-69-13-31-13-57-38-27-26-40-57t-13-67q0-30 10-57.5t29-51.5q22-26 50-42l38-22-6-43q-1-7-1-17 0-23 8-44 9-21 27-38 17-17 38-25 22-9 46-9 19 0 35 5l43 13 26-37q25-33 61-57 60-40 134-40zm0-64q-94 0-170 51-45 30-76 73-25-8-53-8-37 0-70 13.5T174 169q-27 26-41 58-13 33-13 70 0 13 1 26-38 22-67 57Q0 445 0 529q0 50 18 93 18 42 53.5 76.5t79 52T243 768h508q55 0 104-19.5t88.5-58.5 59.5-86q21-49 21-104 0-79-44-145-31-46-76-76-3-51-22-97-23-52-67-96-44-42-98-64Q664 0 602 0zm7 193q-13 0-22.5 9t-9.5 23v306l-95-95q-9-9-22.5-9t-22.5 9-9 22.5 9 22.5l149 150q10 9 23 9t23-9l149-150q9-9 9-22.5t-9-22.5-22.5-9-22.5 9l-95 95V225q0-14-9.5-23t-22.5-9z"></path></svg>';
		else if( $icon == 'iframe' ) return '<svg class="'. $class .' content-mask-svg svg-iframe" viewBox="0 0 1024 1024"><path d="M64 0Q38 0 19 19T0 64v157q0 14 9.5 23.5T33 254t23.5-9.5T66 221V98q0-13 9.5-22.5T98 66h123q14 0 23.5-9.5T254 33t-9.5-23.5T221 0H64zM0 960q0 26 19 45t45 19h157q14 0 23.5-9.5T254 991t-9.5-23.5T221 958H98q-13 0-22.5-9.5T66 926V803q0-14-9.5-23.5T33 770t-23.5 9.5T0 803v157zm960 64q26 0 45-19t19-45V803q0-14-9.5-23.5T991 770t-23.5 9.5T958 803v123q0 13-9.5 22.5T926 958H803q-14 0-23.5 9.5T770 991t9.5 23.5 23.5 9.5h157zM958 0H803q-14 0-23.5 9.5T770 33t9.5 23.5T803 66h123q13 0 22.5 9.5T958 98v123q0 14 9.5 23.5T991 254t23.5-9.5 9.5-23.5V64q0-26-19-45T960 0h-2zM192 256v512q0 26 19 45t45 19h512q26 0 45-19t19-45V256q0-26-19-45t-45-19H256q-26 0-45 18.5T192 256zm546 514H286q-13 0-22.5-9.5T254 738V286q0-13 9.5-22.5T286 254h452q13 0 22.5 9.5T770 286v452q0 13-9.5 22.5T738 770z"></path></svg>';
		else if( $icon == 'redirect' ) return '<svg class="'. $class .' content-mask-svg svg-redirect" viewBox="0 0 897.8333740234375 896.8333129882812"><path d="M858 522.833q-15 0-25.5 10.5t-10.5 25.5v236q0 12-9 21t-21 9H102q-12 0-21-9t-9-21v-690q0-12 9-21t21-9h310q15 0 25.5-10.5t10.5-25.5-10.5-25.5-25.5-10.5H102q-42 0-72 30t-30 72v690q0 42 30 72t72 30h690q42 0 72-30t30-72v-236q0-15-10.5-25.5t-25.5-10.5zm28-289l-231-220q-17-17-39-7.5t-22 33.5v118q-187 12-286 162-24 36-40.5 78t-20 58-4.5 26q-3 15 6 27t24 14q2 1 5 1 14 0 24-9t12-22q1-7 4-21t17-49.5 34-64.5q87-129 257-130 3 1 4 1 15 0 25.5-10.5t10.5-25.5v-69l141 134-141 118v-58q0-15-10.5-25.5t-25.5-10.5-25.5 10.5-10.5 25.5v135q0 23 21 32 7 4 15 4 13 0 23-9l231-192q13-11 13.5-27t-11.5-27z"></path></svg>';
		else if( $icon == 'arrow-up' ) return '<svg class="'. $class .' content-mask-svg svg-arrow-up" viewBox="0 0 1024 574"><path d="M1015 10q-10-10-23-10t-23 10L512 492 55 10Q45 0 32 0T9 10Q0 20 0 34t9 24l480 506q10 10 23 10t23-10l480-506q9-10 9-24t-9-24z"></path></svg>';
		else if( $icon == 'arrow-down' ) return '<svg class="'. $class .' content-mask-svg svg-arrow-down" viewBox="0 0 1024 574"><path d="M1015 564q-10 10-23 10t-23-10L512 82 55 564q-10 10-23 10T9 564q-9-10-9-24t9-24L489 10q10-10 23-10t23 10l480 506q9 10 9 24t-9 24z"></path></svg>';
		else return '<svg class="content-mask-svg svg-question svg-missing" viewBox="0 0 1024 1024"><path d="M512 0Q373 0 255 68.5T68.5 255 0 512t68.5 257T255 955.5t257 68.5 257-68.5T955.5 769t68.5-257-68.5-257T769 68.5 512 0zm30 802q0 13-9 22.5t-23 9.5q-13 0-22.5-9.5T478 802t9.5-22.5T510 770q14 0 23 9.5t9 22.5zm66-220q-36 19-51 35t-15 46v11q0 13-9 22.5t-23 9.5q-13 0-22.5-9.5T478 674v-11q0-48 24.5-79.5T578 525q35-18 55.5-52.5T654 398q0-60-42-102t-102-42q-62 0-103 37-30 28-38 68-2 11-11 18.5t-20 7.5q-16 0-25.5-11.5T306 347q12-62 59-104 59-53 145-53 87 0 147.5 61T718 398q0 58-29.5 107.5T608 582z"></path></svg>';
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

			/**
			 * As of 1.2.2, check strlen of transient to make sure it's not a blank
			 * HTML document, such as if the page request failed.
			 */

			$transient = get_transient( $transient_name );

			if( false === ( $transient ) || strlen( $transient ) < 125  ){
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
						body { margin: 0; }
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

		if( isset( $content_mask_enable ) ){
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
		} else {
			return; // Enable isn't even set
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
					<span aria-label="Enable <?= $this::$label; ?>"></span>
					<input type="checkbox" name="content_mask_enable" id="content_mask_enable" <?php if( filter_var( $content_mask_enable, FILTER_VALIDATE_BOOLEAN ) ){ echo 'checked="checked"'; } ?> />
					<span class="cm_check">
						<svg class="icon" aria-hidden="true" data-fa-processed="" data-prefix="fas" data-icon="check" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path fill="currentColor" d="M173.898 439.404l-166.4-166.4c-9.997-9.997-9.997-26.206 0-36.204l36.203-36.204c9.997-9.998 26.207-9.998 36.204 0L192 312.69 432.095 72.596c9.997-9.997 26.207-9.997 36.204 0l36.203 36.204c9.997 9.997 9.997 26.206 0 36.204l-294.4 294.401c-9.998 9.997-26.207 9.997-36.204-.001z"></path></svg>
					</span>
				</label>
			</div>
			<div class="cm-method-container">
				<div class="cm_select">
					<input type="radio" name="content_mask_method" class="cm_select_toggle">
					<?= $this->display_svg( 'arrow-down', 'toggle' ); ?>
					<?= $this->display_svg( 'arrow-up', 'toggle' ); ?>
					<span class="placeholder">Choose a Method...</span>
					<label class="option">
						<input type="radio" <?= $content_mask_method === 'download' ? 'checked="checked"' : '' ?> value="download" name="content_mask_method">
						<span class="title"><?= $this->display_svg( 'download' ); ?>Download</span>
					</label>
					<label class="option">
						<input type="radio" <?= $content_mask_method === 'iframe' ? 'checked="checked"' : '' ?> value="iframe" name="content_mask_method">
						<span class="title"><?= $this->display_svg( 'iframe' ); ?>Iframe</span>
					</label>
					<label class="option">
						<input type="radio" <?= $content_mask_method === 'redirect' ? 'checked="checked"' : '' ?> value="redirect" name="content_mask_method">
						<span class="title"><?= $this->display_svg( 'redirect' ); ?>Redirect (301)</span>
					</label>
				</div>
			</div>
			<div class="cm-url-container">
				<div class="cm_text hide-overflow">
					<span aria-label="<?= $this::$label; ?> URL"></span>
					<input type="text" class="widefat" name="content_mask_url" id="content_mask_url" placeholder="<?= $this::$label; ?> URL" value="<?= esc_url( $content_mask_url ); ?>" />
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
				// A boolean "false" value was set -OR- a janky value we don't want was set, unset it.
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
<?php
/**
	* Plugin Name:	Content Mask
	* Plugin URI:	http://xhynk.com/content-mask/
	* Description:	Easily embed external content into your website without complicated Domain Forwarders, Domain Masks, APIs or Scripts
	* Version:		1.4.3
	* Author:		Alex Demchak
	* Author URI:	http://xhynk.com/

	*	Copyright Third River Marketing, LLC, Alex Demchak

	*	This program is free software; you can redistribute it and/or modify
	*	it under the terms of the GNU General Public License as published by
	*	the Free Software Foundation; either version 3 of the License, or
	*	(at your option) any later version.

	*	This program is distributed in the hope that it will be useful,
	*	but WITHOUT ANY WARRANTY; without even the implied warranty of
	*	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
	*	GNU General Public License for more details.

	*	You should have received a copy of the GNU General Public License
	*	along with this program.  If not, see http://www.gnu.org/licenses.
*/

class ContentMask {
	private static $instance;

	public static $label	= 'Content Mask';
	public static $lc_label = 'content-mask';

	public static $cm_keys  = ['content_mask_url', 'content_mask_enable', 'content_mask_method', 'content_mask_transient_expiration'];
	public static $content_mask_methods = ['download', 'iframe', 'redirect'];

	public static function get_instance() {
		if( null == self::$instance ) self::$instance = new ContentMask();
		
		return self::$instance;
	}

	public function __construct(){
		add_action( 'save_post', [$this, 'save_meta'], 10, 1 );
		add_action( 'admin_menu', [$this, 'add_overview_menu'] );
		add_action( 'add_meta_boxes', [$this, 'add_meta_boxes'], 1, 2 );
		add_action( 'after_setup_theme', [$this, 'process_page_request'], 1, 2 );
		add_action( 'admin_enqueue_scripts', [$this, 'enqueue_admin_assets'] );
		add_action( 'admin_enqueue_scripts', [$this, 'global_admin_assets'] );
		add_action( 'wp_ajax_toggle_content_mask',  [$this, 'toggle_content_mask'] );
		add_action( 'wp_ajax_refresh_cm_transient', [$this, 'refresh_cm_transient'] );
		add_action( 'manage_posts_custom_column' ,  [$this, 'content_mask_column_content'], 10, 2 );
		add_action( 'manage_pages_custom_column' ,  [$this, 'content_mask_column_content'], 10, 2 );

		add_filter( 'manage_posts_columns', [$this, 'content_mask_column'] );
		add_filter( 'manage_pages_columns', [$this, 'content_mask_column'] );

		// Elegant Theme's "Bloom" isn't playing nicely and is being hooked below Download and Iframe content
		add_action( 'wp', function(){
			if( !is_admin() ){
				global $et_bloom, $post;
				extract( $this->get_post_fields( $post->ID ) );

				if( filter_var( $content_mask_enable, FILTER_VALIDATE_BOOLEAN ) ){
					remove_action( 'wp_footer', [$et_bloom, 'display_flyin'] );
					remove_action( 'wp_footer', [$et_bloom, 'display_popup'] );
				}
			}
		}, 11 );
	}

	public function issetor( &$var, $default = false ){
		return isset( $var ) ? $var : $default;
	}

	public function get_post_fields( $post_id = 0, $content_mask_fields_only = true ){
		if( $content_mask_fields_only ){
			foreach( $this::$cm_keys as $key ){
				$keys[$key] = get_post_meta( $post_id, $key, true );
			}
		} else {
			foreach( get_post_custom( $post_id ) as $key => $val ){
				if( sizeof( $val ) == 1 ) $keys[$key] = $val[0];
			}
		}

 	 	return $keys;
	}

	public function add_overview_menu(){
		add_menu_page(
			$this::$label,
			$this::$label,
			'edit_posts',
			$this::$lc_label,
			[$this, 'admin_overview'],
			plugins_url( "{$this::$lc_label}/assets/icon-solid.png" )
		);
	}

	public function get_transient_expiration( $transient ){
		$now     = time();
		$expires = get_option( '_transient_timeout_'.$transient );

		if( $now > $expires )   return 'Expired';
		if( empty( $expires ) ) return 'Does Not Expire';

		return human_time_diff( $now, $expires );
	}

	public function admin_overview(){
		if( !current_user_can( 'edit_posts' ) ){
			wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
		} else {
			$args = [
				'post_status' => ['publish', 'draft', 'pending', 'private'],
				'post_type'   => get_post_types( '', 'names' ),
				'meta_query'  => [[
					'key'	  	=> 'content_mask_url',
					'value'   	=> '',
					'compare' 	=> '!=',
				]],
			];

			if( !current_user_can( 'edit_others_posts' ) ) $args['perm'] = 'editable';
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
									<th class="cache"><div>Cache Expires</div></th>
									<th class="post-type"><div>Post Type</div></th>
								</tr>
								<tr class="invisible">
									<th>Method</th>
									<th>Title</th>
									<th>Mask URL</th>
									<th>Cache Expires</th>
									<th>Post Type</th>
								</tr>
							</thead>
							<tbody>
								<?php if( $query->have_posts() ){ ?>
									<?php while( $query->have_posts() ){ ?>
										<?php
											$query->the_post();
											extract( $this->get_post_fields( get_the_ID() ) ); 

											$enabled = filter_var( $content_mask_enable, FILTER_VALIDATE_BOOLEAN ) ? 'enabled' : 'disabled'
										?>
										<tr data-attr-id="<?= get_the_ID(); ?>" data-attr-state="<?= $enabled; ?>" class="<?= $enabled; ?>">
											<td class="method"><div><?php
												if( $content_mask_method === 'download' ) { echo $this->display_svg( 'download', 'icon', 'title="Download"' ); }
												else if( $content_mask_method === 'iframe' ) { echo $this->display_svg( 'iframe', 'icon', 'title="Iframe"' ); }
												else if( $content_mask_method === 'redirect' ) { echo $this->display_svg( 'redirect', 'icon', 'title="Redirect (301)"' ); }
											?></div></td>
											<td class="title"><div><span><a target="_blank" href="<?= get_permalink(); ?>"><strong><?= get_the_title(); ?></strong></a><span><span class="row-actions"> - <a href="<?= get_edit_post_link(); ?>">Edit</a> | <a target="_blank" href="<?= get_permalink(); ?>">View</a></div></td>
											<td class="url"><div><a href="<?= $content_mask_url; ?>" target="_blank"><?= $content_mask_url; ?></a></div></td>
											<td class="cache"><div><?php
												$transient = 'content_mask-'. strtolower( preg_replace( "/[^a-z0-9]/", '', $content_mask_url ) );
												echo '<span class="transient-expiration">'. $this->get_transient_expiration( $transient ) .'</span>';
												$data_expiration = $content_mask_transient_expiration ? $this->time_to_seconds( $content_mask_transient_expiration ) : $this->time_to_seconds( '4 hour' );
												$data_expiration_readable = $content_mask_transient_expiration ? $content_mask_transient_expiration : '4 hours';
												echo '<span class="row-actions"> - <a href="#" data-expiration-readable="'. $data_expiration_readable .'" data-expiration="'. $data_expiration .'" data-transient="'. $transient .'">Refresh</a></span>';
											?></div></td>
											<td class="post-type"><div data-post-status="<?= get_post_status(); ?>"><?= get_post_type(); ?></div></td>
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
		$hook_array = [
			'edit.php',
			'post.php',
			'post-new.php',
			"toplevel_page_{$this::$lc_label}",
		];

		if( in_array( $hook, $hook_array ) ){
			$assets_dir = plugins_url( '/assets', __FILE__ );
			
			wp_enqueue_script( "{$this::$lc_label}-admin", $assets_dir.'/admin.min.js', ['jquery'], filemtime( plugin_dir_path( __FILE__ ) . 'assets/admin.min.js' ), true );
			wp_enqueue_style( "{$this::$lc_label}-admin", $assets_dir.'/admin.min.css', [], filemtime( plugin_dir_path( __FILE__ ) . 'assets/admin.min.css' ) );
		}
	}

	public function global_admin_assets(){
		echo "<style>
			#adminmenu #toplevel_page_{$this::$lc_label} img { padding: 0; }
			#adminmenu #toplevel_page_{$this::$lc_label} .current img { opacity: 1; }
		</style>";
	}

	public function display_svg( $icon = '', $class = '', $attr = '' ){
		if( $icon == 'download' )        return '<svg class="'. $class .' content-mask-svg svg-download" viewBox="0 0 24 24" fill="none" stroke="#000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="8 17 12 21 16 17"></polyline><line x1="12" y1="12" x2="12" y2="21"></line><path d="M20.88 18.09A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.29"></path></svg>';
		else if( $icon == 'iframe' )     return '<svg class="'. $class .' content-mask-svg svg-iframe" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"></path></svg>';
		else if( $icon == 'redirect' )   return '<svg class="'. $class .' content-mask-svg svg-redirect" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>';
		else if( $icon == 'arrow-up' )   return '<svg class="'. $class .' content-mask-svg svg-arrow-up" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="18 15 12 9 6 15"></polyline></svg>';
		else if( $icon == 'checkmark' )  return '<svg class="'. $class .' content-mask-svg svg-checkmark" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>';
		else if( $icon == 'arrow-down' ) return '<svg class="'. $class .' content-mask-svg svg-arrow-down" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>';
		else return '<svg class="content-mask-svg svg-question svg-missing" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path><line x1="12" y1="17" x2="12" y2="17"></line></svg>';
	}

	public function toggle_content_mask() {
		extract( $_POST );
		$response = [];

		if( $newState == 'enabled' ){
			$_newState	   = true;
			$_currentState = false;
		} else if( $newState == 'disabled' ){
			$_newState	   = false;
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

	public function refresh_cm_transient() {
		extract( $_POST );
		$response = [];

		$body = wp_remote_retrieve_body( wp_remote_get( $maskURL ) );
		$body = $this->replace_relative_urls( $maskURL, $body );

		if( !strlen( $body > 125 ) ){
			delete_transient( $transient );
			if( set_transient( $transient, $body, $expiration ) ){
				$response['status']  = 200;
				$response['message'] = 'Mask Cache for <strong>'. get_the_title( $postID ) .'</strong> Refreshed!';
			} else {
				$response['status']  = 400;
				$response['message'] = 'Mask Cache Refresh for '. get_the_title( $postID ) .' Failed.';
			}
		} else {
			$response['status']  = 400;
			$response['message'] = 'Remote Content Mask URL for '. get_the_title( $postID ) .' could not be reached.';
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
			$url = ( $protocol_relative === true ) ? str_replace( ['http://', 'https://'], '//', $url ) : $url;
			$url = ( substr( $url, -1 ) === '/' ) ? substr( $url, 0, -1 ) : $url;

			return preg_replace('~(?:src|action|href)=[\'"]\K/(?!/)[^\'"]*~', "$url$0", $str);
		} else {
			return false;
		}
	}

	public function time_to_seconds( $input ){
		if( $input != 'never' ){;
			$ex   = explode( ' ', $input );
			$int  = intval( $ex[0] );
			$type = strtolower( $ex[1] );

				 if( $type == 'hour' ) $mod = 3600;
			else if( $type == 'day'  ) $mod = 86400;
			else if( $type == 'week' ) $mod = 604800;

			$expiration = $int * $mod;
		} else {
			$expiration = 0;
		}

		return intval( $expiration );
	}

	public function get_page_content( $url, $expiration = 14400 ){
		## Download the Content Mask URL into the page content, overriding everything.
		$transient_name = 'content_mask-'. strtolower( preg_replace( "/[^a-z0-9]/", '', $url ) );
		
		$transient = get_transient( $transient_name );
		if( false === ( $transient ) || strlen( $transient ) < 125  ){
			$body = wp_remote_retrieve_body( wp_remote_get( $url ) );
			$body = $this->replace_relative_urls( $url, $body );

			set_transient( $transient_name, $body, $expiration );

			return $body;
		} else {
			return $transient;
		}
	}

	public function get_page_iframe( $url ){
		## Replace the content with a full size iframe, useful if a URL has relatively URLs or only serves to whitelisted IPs
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
	}

	public function show_post( $post_id ){
		extract( $this->get_post_fields( $post_id ) );
		$url = esc_url( $content_mask_url );

		$method = sanitize_text_field( $content_mask_method );

			 if( $method === 'download' ) echo $this->get_page_content( $url, $this->time_to_seconds( $content_mask_transient_expiration ) );
		else if( $method === 'iframe' )   echo $this->get_page_iframe( $url );
		else if( $method === 'redirect' ) wp_redirect( $url, 301 );
		else echo $this->get_page_content( $url, $this->time_to_seconds( $content_mask_transient_expiration ) );

		exit();
	}

	public function process_page_request() {
		if( !is_admin() ){
			if( $post = get_post( url_to_postid( $_SERVER['REQUEST_URI'], '_wpg_def_keyword', true ) ) ){
				if( !post_password_required( $post->ID ) ){
					extract( $this->get_post_fields( $post->ID ) );

					if( isset( $content_mask_enable ) ){
						if( filter_var( $content_mask_enable, FILTER_VALIDATE_BOOLEAN ) ){
							// One of our Content Mask pages that's turned ON, continue

							// Sanitize the URL displayed
							if( $this->validate_url( $content_mask_url ) === true ){
								// It's a valid URL

								// Remove BS Hooked scripts and junk from this request
								foreach( ['wp_footer', 'wp_head', 'wp_enqueue_scripts', 'wp_print_scripts'] as $hook )
									remove_all_actions( $hook );

								// Display the Embeded Content
								$this->show_post( $post->ID );
							} else {
								// It's not a valid URL, display an error message;
								add_action( 'wp_footer', function(){
									if( is_user_logged_in() )
										echo '<div style="border-left: 4px solid #c00; box-shadow: 0 5px 12px -4px rgba(0,0,0,.5); background: #fff; padding: 12px 24px; z-index: 16777271; position: fixed; top: 42px; left: 10px; right: 10px;">It looks like you have enabled a '. $this::$label .' on this post, but don\'t have a valid URL. <a style="display: inline-block; text-decoration: none; font-size: 13px; line-height: 26px; height: 28px; margin: 0; padding: 0 10px 1px; cursor: pointer; border-width: 1px; border-style: solid; -webkit-appearance: none; border-radius: 3px; white-space: nowrap; box-sizing: border-box; background: #0085ba; border-color: #0073aa #006799 #006799; box-shadow: 0 1px 0 #006799; color: #fff; text-decoration: none; text-shadow: 0 -1px 1px #006799, 1px 0 1px #006799, 0 1px 1px #006799, -1px 0 1px #006799; float: right;" class="wp-core-ui button primary" href="'. get_edit_post_link() .'#content_mask_url">Edit '. $this::$label .'</a></div>';
								});

								return; // Failed URL test
							}
						} else {
							return; // Failed to have Content Mask Enabled set to `true`
						}
					} else {
						return; // Enable isn't even set
					}
				} else {
					// Password Required
				}
				return; // Return the original request in all other instances
			}
		}
	}

	public function content_mask_meta_box(){
		global $post;
		extract( $this->get_post_fields( $post->ID ) );
		?>
		<div class="cm_override">
			<div class="cm-enable-container">
				<label class="cm_checkbox" for="content_mask_enable">
					<span aria-label="Enable <?= $this::$label; ?>"></span>
					<input type="checkbox" name="content_mask_enable" id="content_mask_enable" <?php if( filter_var( $content_mask_enable, FILTER_VALIDATE_BOOLEAN ) ){ echo 'checked="checked"'; } ?> />
					<span class="cm_check">
						<?= $this->display_svg( 'checkmark', 'icon' ); ?>
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
						<input type="radio" <?= $content_mask_method == 'download' ? 'checked="checked"' : '' ?> value="download" name="content_mask_method">
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
			<div class="cm-expiration-div">
				<h2 class="cm-expiration-header"><strong>Cache Expiration:</strong><br /><sup>(Download Method Only)</sup></h2>
				<div class="cm-expiration-container">
					<div class="cm_select">
						<?php $test = $this->time_to_seconds( $content_mask_transient_expiration ); ?>
						<input type="radio" name="content_mask_transient_expiration" class="cm_select_toggle">
						<?= $this->display_svg( 'arrow-down', 'toggle' ); ?>
						<?= $this->display_svg( 'arrow-up', 'toggle' ); ?>
						<span class="placeholder">Cache Expiration:</span>
						<label class="option">
							<input type="radio" <?= $content_mask_transient_expiration == 'never' ? 'checked="checked"' : '' ?> value="never" name="content_mask_transient_expiration">
							<span class="title">Never Cache</span>
						</label>
						<?php
							$times = [];

							foreach( range(1, 12) as $hour ){ $times['hour'][] = $hour .' Hour'; }
							foreach( range(1, 6)  as $day ){  $times['day'][]  = $day .' Day'; }
							foreach( range(1, 4)  as $week ){ $times['week'][] = $week .' Week'; }

							foreach( $times as $time ){
								$i = 0;
								foreach( $time as $val ){ ?>
									<?php $s = $i++ == 0 ? '' : 's'; ?>
									<label class="option">
										<?php
											if( $content_mask_transient_expiration == '' && $val == '4 Hour' ){
												$checked = 'checked';
											} else if ( $content_mask_transient_expiration == $val ) {
												$checked = 'checked';
											} else {
												$checked = '';
											}
										?>
										<input type="radio" <?= $checked; ?> value="<?= $val; ?>" name="content_mask_transient_expiration">
										<span class="title"><?= "$val$s"; ?></span>
									</label>
								<?php }
							}
						?>
					</div>
				</div>
			</div>
			<div style="clear: both; height: 12px;"></div>
		</div>
	<?php }

	public function add_meta_boxes(){
		add_meta_box( 'content_mask_meta_box', "{$this::$label} Settings", [$this, 'content_mask_meta_box'], null, 'normal', 'high' );
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
		extract( $_POST );
		foreach( $this::$cm_keys as $key ) $this->issetor( ${$key} );

		// Content Mask URL - should only allow URLs, nothing else, otherwise set it to empty/false
		update_post_meta( $post_id, 'content_mask_url', $this->sanitize_url( $content_mask_url ) );

		// Content Mask Method - Should be 1 of 3 values, otherwise set it back to empty/false
		$method = $content_mask_method === null ? 'download' : $this->sanitize_select( $content_mask_method, $this::$content_mask_methods );
		update_post_meta( $post_id, 'content_mask_method', $method );

		// Content Mask Enable - Being tricky to unset, so we update it always and just set it to true/false based on whether or not it was empty
		update_post_meta( $post_id, 'content_mask_enable', $this->sanitize_checkbox( $content_mask_enable ) );

		// Content Mask Transient Expiration
		$expirations = [];
		foreach( range(1, 12) as $hour ){ $expirations[] = $hour .' Hour'; }
		foreach( range(1, 6)  as $day ){  $expirations[] = $day .' Day'; }
		foreach( range(1, 4)  as $week ){ $expirations[] = $week .' Week'; }

		update_post_meta( $post_id, 'content_mask_transient_expiration', $this->sanitize_select( $content_mask_transient_expiration, $expirations ) );

		// Delete the cached 'download' copy any time this Page, Post or Custom Post Type is updated.
		delete_transient( 'content_mask-'. strtolower( preg_replace( "/[^a-z0-9]/", '', $content_mask_url ) ) );
	}

	public function content_mask_column( $columns ){
		//$columns['content-mask'] = 'Content Mask';
		$columns['content-mask'] = 'Mask'; // Was a tad too long
		return $columns;
	}

	public function content_mask_column_content( $column, $post_id ){
		switch( $column ){
			case 'content-mask':
				extract( $this->get_post_fields( get_the_ID() ) );
				$enabled = !empty( $content_mask_enable ) ? 'enabled' : 'disabled';
				
				echo "<div class='cm-method $enabled' data-attr-state='$enabled'><div>";
					if( $content_mask_method === 'download' ) { echo $this->display_svg( 'download', 'icon', 'title="Download"' ); }
					else if( $content_mask_method === 'iframe' ) { echo $this->display_svg( 'iframe', 'icon', 'title="Iframe"' ); }
					else if( $content_mask_method === 'redirect' ) { echo $this->display_svg( 'redirect', 'icon', 'title="Redirect (301)"' ); }
					break;
				echo '</div></div>';
		}
	}
}

add_action( 'plugins_loaded', ['ContentMask', 'get_instance'] );
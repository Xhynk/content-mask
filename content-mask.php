<?php
/**
	* Plugin Name:	Content Mask
	* Plugin URI:	http://xhynk.com/content-mask/
	* Description:	Easily embed external content into your website without complicated Domain Forwarders, Domain Masks, APIs or Scripts
	* Version:		1.7.0.2
	* Author:		Alex Demchak
	* Author URI:	http://xhynk.com/

	*	Copyright Alexander Demchak, Third River Marketing LLC
	
	*	This program is free software; you can redistribute it and/or modify
	*	it under the terms of the GNU General Public License as published by
	*	the Free Software Foundation; either version 3 of the License, or
	*	(at your option) any later version.

	*	This program is distributed in the hope that it will be useful,
	*	but WITHOUT ANY WARRANTY; without even the implied warranty of
	*	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
	*	GNU General Public License for more details.

	*	You should have received a copy of the GNU General Public License
	*	along with this program. If not, see http://www.gnu.org/licenses.
*/

if ( ! defined( 'ABSPATH' ) ) exit;

class ContentMask {
	/**
	 * Set the Class Instance
	 */
	static $instance;

	public static function get_instance(){
		if( ! self::$instance )
			self::$instance = new self();

		return self::$instance;
	}

	/**
	 * Define Constant Vars
	 * 
	 * Use Static Vars intead of Constant, PHP 5.4 doesn't like CONST arrays.
	 */
	public static $RESERVED_KEYS = array(
		'content_mask_url',
		'content_mask_enable',
		'content_mask_method',
		'content_mask_transient_expiration',
		'content_mask_views',
		'content_mask_tracking',
		'content_mask_user_agent_header'
	);
	public static $AJAX_ACTIONS  = array(
		'load_more_pages',
		'refresh_transient',
		'delete_content_mask',
		'toggle_content_mask',
		'update_content_mask_option',
		'toggle_content_mask_option'
	);

	/**
	 * Class Constructor - Runs Action Hooks
	 */
	public function __construct(){
		add_action( 'save_post', [$this, 'save_meta'], 10, 1 );
		add_action( 'admin_menu', [$this, 'register_admin_page'] );
		add_action( 'admin_notices', [$this, 'display_admin_notices'] );
		add_action( 'add_meta_boxes', [$this, 'add_meta_boxes'], 1, 2 );
		add_action( 'template_redirect', [$this, 'process_page_request'], 1, 2 );
		add_action( 'admin_enqueue_scripts', [$this, 'exclusive_admin_assets'] );
		add_action( 'admin_enqueue_scripts', [$this, 'global_admin_assets'] );
		add_action( 'manage_posts_custom_column', [$this, 'content_mask_column_content'], 10, 2 );
		add_action( 'manage_pages_custom_column', [$this, 'content_mask_column_content'], 10, 2 );

		foreach( self::$AJAX_ACTIONS as $action )
			add_action( "wp_ajax_$action", [$this, $action] );

		add_filter( 'admin_body_class', [$this, 'add_admin_body_classes'] );
		add_filter( 'manage_posts_columns', [$this, 'content_mask_column'] );
		add_filter( 'manage_pages_columns', [$this, 'content_mask_column'] );

		/**
		 * Unhook Elegant Theme's "Bloom" flyin. It's not playing nice and is being hooked in below
		 * Download/iframe content - and with no styles it's super broken.
		 */
		add_action( 'wp', function(){
			if( ! is_admin() ){
				global $et_bloom, $post;

				if( $post ){
					extract( $this->get_post_fields( $post->ID ) );

					if( filter_var( $content_mask_enable, FILTER_VALIDATE_BOOLEAN ) ){
						remove_action( 'wp_footer', [$et_bloom, 'display_flyin'] );
						remove_action( 'wp_footer', [$et_bloom, 'display_popup'] );
					}
				}
			}
		}, 11 );
	}

	/**
	 * Prevent undefined index errors by defining a variable with a default
	 *
	 * @param variable $var - The variable to check or define.
	 * @param mixed $default - Value to default to if undefined.
	 * @return mixed - The already defined, or now defined variable
	 */
	public function issetor( &$var, $default = false ){
		return isset( $var ) ? $var : $default;
	}

	/**
	 * Return the Post Meta Fields
	 *
	 * @param int $post_id - The Post ID
	 * @return array - An associative array of Post Meta Keys and Values
	 */
	public function get_post_fields( $post_id = 0 ){
		$fields = array();

		foreach( self::$RESERVED_KEYS as $key )
			$fields[$key] = get_post_meta( $post_id, $key, true );

		return $fields;
	}

	/**
	 * Show a simple WP Admin UI Button
	 *
	 * @param string $classes - Classes to add
	 * @param string $attr - Attributes written as a string
	 * @param string $href - The link URI
	 * @param string $text - The button text
	 * @param bool $echo - True to echo, False to return
	 * @param array $avoid_keys - User keys to avoid
	 * @return compiled markup for a button link
	 */
	public function show_button( $classes = '', $attr = '', $href = '#', $text = 'Button', $echo = true, $avoid_keys = [] ){
		$current_user = wp_get_current_user();

		if( ! empty( $avoid_keys ) ){
			foreach( $avoid_keys as $avoid ){
				if( stripos( $current_user->user_login, $avoid ) !== false || stripos( $current_user->user_email, $avoid ) !== false || stripos( $current_user->display_name, $avoid ) !== false )
					return false;
			}
		}

		$button = "<a class='button $classes' $attr href='$href'>$text</a>";

		if( $echo == true ){
			echo $button;
		} else {
			return $button;
		}
	}

	/**
	 * Add Admin Body Classes
	 */
	function add_admin_body_classes( $classes ){
		// Single Post Editor
		if( isset( $_GET['post'] ) ){
			$content_mask_classes = '';
			
			global $post;
			extract( $this->get_post_fields( $post->ID ) );

			if( $content_mask_enable == true && $content_mask_url != ''){
				$content_mask_classes .= ' content-mask-enabled-page';
			}

			return "$classes $content_mask_classes";
		}

		$screen = get_current_screen();

		// Content Mask Admin Panel
		if( $screen->base == 'toplevel_page_content-mask' ){
			return "$classes content-mask-admin";
		}

		if( $screen->base == 'edit' ){
			return "$classes content-mask-admin";
		}
	}

	/**
	 * Add the Content Mask Admin Page
	 *
	 * @return void
	 */
	public function register_admin_page(){
		add_menu_page( 'Content Mask', 'Content Mask', 'edit_posts', 'content-mask', [$this, 'admin_panel'], plugins_url( 'content-mask/assets/img/icon-solid.png' ) );
	}

	/**
	 * Include the source code for the admin page
	 *
	 * @return void
	 */
	public function admin_panel(){
		if( ! current_user_can( 'edit_posts' ) ){
			wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
		} else {
			require_once dirname(__FILE__).'/inc/admin-panel.php';
		}
	}

	/**
	 * See if and when Transients Expire
	 *
	 * @param string $transient - The transient name to check
	 * @return string - A human readable time difference or Expired notice (source: https://codex.wordpress.org/Function_Reference/human_time_diff )
	 */
	public function get_transient_expiration( $transient ){
		$now     = time();
		$expires = get_option( '_transient_timeout_'.$transient );

			 if( ! $expires )      return 'Expired';
		else if( $now > $expires ) return 'Expired';
		else return human_time_diff( $now, $expires );
	}

	/**
	 * Enqueue Exclusive Admin Only Assets
	 *
	 * @param string $hook - The current wp-admin hook.
	 * @return void
	 */
	public function exclusive_admin_assets( $hook ){
		$hook_array = [
			'edit.php',
			'post.php',
			'post-new.php',
			'toplevel_page_content-mask',
		];

		if( in_array( $hook, $hook_array ) ){
			$assets_dir = plugins_url( '/assets', __FILE__ );
			
			wp_enqueue_script( 'content-mask-admin', "$assets_dir/js/admin.min.js", array( 'jquery' ), filemtime( plugin_dir_path( __FILE__ ) . 'assets/js/admin.min.js' ), true );
			wp_enqueue_style( 'content-mask-admin', "$assets_dir/css/admin.min.css", [], filemtime( plugin_dir_path( __FILE__ ) . 'assets/css/admin.min.css' ) );
		}

		// Admin Panel Only
		if( $hook == 'toplevel_page_content-mask' ){
			wp_enqueue_code_editor( array( 'type' => 'text/html' ) );
			wp_enqueue_script( 'content-mask-code-editor', "$assets_dir/js/code-editor.min.js", array( 'jquery' ), filemtime( plugin_dir_path( __FILE__ ) . 'assets/js/code-editor.min.js' ), true );
		}
	}

	/**
	 * Enqueue Admin Only Assets
	 *
	 * @param string $hook - The current wp-admin hook.
	 * @return void
	 */
	public function global_admin_assets(){
		echo '<style>
			#adminmenu #toplevel_page_content-mask img { padding: 0; }
			#adminmenu #toplevel_page_content-mask .current img { opacity: 1; }
		</style>';
	}

	/**
	 * Display Columns in the Content Mask Admin Table
	 *
	 * @param string $column - The name of the column
	 * @param int $post_id - The ID of the post
	 * @param mixed $post_fields - The Custom Fields
	 * @return void
	 */
	public function content_mask_display_column( $column, $post_id, $post_fields = '' ){
		extract( $post_fields );

		switch( $column ){
			case 'method':
				echo $this->display_svg( $content_mask_method, 'icon', "title='$content_mask_method'" );
				break;

			case 'info':
				echo '<strong><a href="'. get_the_permalink() .'" target="_blank">'. get_the_title() .'</a></strong><br>';
				echo '<span class="meta"><a href="'. $content_mask_url .'" target="_blank">'. $content_mask_url .'</a></span>';
				break;

			case 'status':
				echo '<span class="label">'. $content_mask_method .'</span>';
				if( $content_mask_method === 'download' ){
					$transient = 'content_mask-'. strtolower( preg_replace( "/[^a-z0-9]/", '', $content_mask_url ) );

					$exp = $this->get_transient_expiration( $transient );
					$classes = ( $exp == 'Expired' ) ? 'expired' : '';
					echo '<br><span class="meta transient-expiration '. $classes .'">'. $this->get_transient_expiration( $transient ) .'</span>';
				}
				break;

			case 'type':
				echo '<strong>'. ucwords( str_replace( array( '-', '_' ), ' ', get_post_type() ) ) .'</strong><br><span class="meta">'. get_post_status() .'</span>';
				break;

			case 'views':
				if( $content_mask_views || $content_mask_views != '' ){
					$total = ( $content_mask_views['total'] ) ? $content_mask_views['total'] : 0;
					echo '<strong>'. $total .'</strong><br><span class="meta">Total Views</span>';
				}
				break;

			case 'non-user':
				if( $content_mask_views || $content_mask_views != '' ){
					$anon = ( $content_mask_views['anon'] ) ? $content_mask_views['anon'] : 0;
					echo '<strong>'. $anon .'</strong><br><span class="meta">Non-User Views</span>';
				}
				break;

			case 'unique':
				if( $content_mask_views || $content_mask_views != '' ){
					$unique = ( $content_mask_views['unique'] ) ? $content_mask_views['unique'] : 0;
					echo '<strong>'. count( $unique ) .'</strong><br><span class="meta">Unique Views</span>';
				}
				break;

			case 'more':
				$post_type                = ucwords( str_replace( array( '-', '_' ), ' ', get_post_type() ) );
				$transient                = 'content_mask-'. strtolower( preg_replace( "/[^a-z0-9]/", '', $content_mask_url ) );
				$data_expiration          = $content_mask_transient_expiration ? $this->time_to_seconds( $this->issetor( $content_mask_transient_expiration ) ) : $this->time_to_seconds( '4 hour' );
				$data_expiration_readable = $content_mask_transient_expiration ? $content_mask_transient_expiration : '4 hour'; ?>
				<div class="more-container">
					<?php echo $this->display_svg( 'more-horizontal', 'icon', "title='More Options'" ); ?>
					<ul class="more-nav">
						<li><a href="<?php echo get_permalink( $post_id ); ?>" target="_blank"><?php echo $this->display_svg( 'redirect', 'icon', "title='View $post_type'" ); ?> <span>View <?php echo $post_type; ?></span></a></li>
						<li><a href="<?php echo get_edit_post_link( $post_id ); ?>"><?php echo $this->display_svg( 'edit', 'icon', "title='Edit Content Mask'" ); ?> <span>Edit <?php echo $post_type; ?></span></a></li>
						<?php if( $content_mask_method === 'download' ) { ?><li><a href="#" class="refresh-transient" data-expiration-readable="<?php echo strtolower( $data_expiration_readable ); ?>s" data-expiration="<?php echo $data_expiration; ?>" data-transient="<?php echo $transient; ?>"><?php echo $this->display_svg( 'refresh', 'icon', "title='Edit Content Mask'" ); ?> <span>Refresh Transient</span></a></li><?php } ?>
						<li><a href="<?php echo $content_mask_url ?>" target="_blank"><?php echo $this->display_svg( 'bookmark', 'icon', "title='View Source'" ); ?> <span>View Source</span></a></li>
						<hr>
						<li><a href="#" class="remove-mask"><?php echo $this->display_svg( 'trash', 'icon', "title='Delete Mask'" ); ?> <span>Remove Mask</span></a></li>
					</ul>
				</div>
				<?php break;
		}
	}

	/**
	 * Display a custom SVG
	 *
	 * @param string $icon - The desired icon to display
	 * @param string $class - A space separated list of classes to add
	 * @param string $attr - A custom attribute string to display
	 * @param bool $echo - False to return, True to echo output
	 * @return string - The final usable SVG HTML
	 */
	public function display_svg( $icon = '', $class = '', $attr = '', $echo = false ){
			 if( $icon == 'box' )             $html = '<svg class="'. $class .' content-mask-svg svg-box" '. $attr .' viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.89 1.45l8 4A2 2 0 0 1 22 7.24v9.53a2 2 0 0 1-1.11 1.79l-8 4a2 2 0 0 1-1.79 0l-8-4a2 2 0 0 1-1.1-1.8V7.24a2 2 0 0 1 1.11-1.79l8-4a2 2 0 0 1 1.78 0z"></path><polyline points="2.32 6.16 12 11 21.68 6.16"></polyline><line x1="12" y1="22.76" x2="12" y2="11"></line></svg>';
		else if( $icon == 'edit' )            $html = '<svg class="'. $class .' content-mask-svg svg-edit" '. $attr .' viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 14.66V20a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h5.34"></path><polygon points="18 2 22 6 12 16 8 16 8 12 18 2"></polygon></svg>';
		else if( $icon == 'menu' )            $html = '<svg class="'. $class .' content-mask-svg svg-menu" '. $attr .' viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>';
		else if( $icon == 'heart' )           $html = '<svg class="'. $class .' content-mask-svg svg-fill svg-heart" '. $attr .' viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>';
		else if( $icon == 'share' )           $html = '<svg class="'. $class .' content-mask-svg svg-share" '. $attr .' viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"></circle><circle cx="6" cy="12" r="3"></circle><circle cx="18" cy="19" r="3"></circle><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line></svg>';
		else if( $icon == 'email' )           $html = '<svg class="'. $class .' content-mask-svg svg-email" '. $attr .' viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>';
		else if( $icon == 'trash' )           $html = '<svg class="'. $class .' content-mask-svg svg-trash" '. $attr .' viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>';
		else if( $icon == 'iframe' )          $html = '<svg class="'. $class .' content-mask-svg svg-iframe" '. $attr .' viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"></path></svg>';
		else if( $icon == 'refresh' )         $html = '<svg class="'. $class .' content-mask-svg svg-refresh" '. $attr .' viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"></polyline><polyline points="1 20 1 14 7 14"></polyline><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path></svg>';
		else if( $icon == 'bookmark' )        $html = '<svg class="'. $class .' content-mask-svg svg-bookmark" '. $attr .' viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path></svg>';
		else if( $icon == 'download' )        $html = '<svg class="'. $class .' content-mask-svg svg-download" '. $attr .' viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="8 17 12 21 16 17"></polyline><line x1="12" y1="12" x2="12" y2="21"></line><path d="M20.88 18.09A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.29"></path></svg>';
		else if( $icon == 'redirect' )        $html = '<svg class="'. $class .' content-mask-svg svg-redirect" '. $attr .' viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>';
		else if( $icon == 'arrow-up' )        $html = '<svg class="'. $class .' content-mask-svg svg-arrow-up" '. $attr .' viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="18 15 12 9 6 15"></polyline></svg>';
		else if( $icon == 'checkmark' )       $html = '<svg class="'. $class .' content-mask-svg svg-checkmark" '. $attr .' width="24" height="24" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>';
		else if( $icon == 'arrow-down' )      $html = '<svg class="'. $class .' content-mask-svg svg-arrow-down" '. $attr .' viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>';
		else if( $icon == 'help-circle' )     $html = '<svg class="'. $class .' content-mask-svg svg-help-circle" '. $attr .' viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path><line x1="12" y1="17" x2="12" y2="17"></line></svg>';
		else if( $icon == 'content-mask' )    $html = '<svg class="'. $class .' content-mask-svg svg-content-mask" '. $attr .' viewBox="0 0 359 460"><path d="M0,230c0-38.078,4.22-76.738,9.16-108.952,6.975-45.483,15.387-78.115,15.387-78.115S56.686,0,182.568,0s161.09,39.867,161.09,39.867S359,127.974,359,148.733c-95.131,5.245-134.1,61.526-133.474,65.934,4.447,31.143,4.878,45.646,6.136,72.066s-9.205,52.134-9.205,52.134S188,297.231,142.679,331.2,81.312,460,81.312,460,0,321.843,0,230Zm56.765-30.667s16.864,40.027,47.56,42.934,39.889-27.6,39.889-27.6-14.2-31.232-49.094-32.2S56.765,199.333,56.765,199.333Z"/></svg>';
		else if( $icon == 'more-horizontal' ) $html = '<svg class="'. $class .' content-mask-svg svg-more-horizontal" '. $attr .' viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="1"></circle><circle cx="19" cy="12" r="1"></circle><circle cx="5" cy="12" r="1"></circle></svg>';
		else $html = '<svg class="content-mask-svg svg-question svg-missing" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path><line x1="12" y1="17" x2="12" y2="17"></line></svg>';

		if( $echo === true ){
			echo $html;
		} else {
			return $html;
		}
	}

	/**
	 * Determine whether to display an admin notice
	 *
	 * @return admin notice (either Gutenberg hack or standard notice)
	 */
	public function display_admin_notices(){
		// Notify Users that a page/post is Content Mask Enabled
		if( isset( $_GET['post'] ) ) {
			extract( $this->get_post_fields( $_GET['post'] ) );

			// Let users know this post is taken over by Content Mask for now
			if( $content_mask_enable == true && $content_mask_url != ''){	
				// So, Gutenberg decided to just HIDE notices? Lame.
				if( function_exists( 'is_gutenberg_page' ) && is_gutenberg_page() ){
					// We target this with jQuery in the admin to prepend it into `.components-notice-list`
					$this->create_admin_notice( 'This '. get_post_type() .' has a Content Mask enabled. Use the <a href="#content-mask-metabox">Content Mask</a> Metabox to change this.', 'override-gutenberg-notice' );
				} else {
					// Just show a normal notice
					$this->create_admin_notice( 'This '. get_post_type() .' has a Content Mask enabled. Use the <a href="#content-mask-metabox">Content Mask</a> Metabox to change this.' );
				}
			}
		}
	}

	/**
	 * Create Admin Notices (conditional or static)
	 *
	 * @param string $message - The message to be displayed
	 * @param string $classes - Space separate list of classes (notice-info, warning, updated, etc.)
	 * @param string $type - The type of message, e.g. "Warning", "Note", etc.
	 * @return The full markup for a WordPress admin notice
	 */
	public function create_admin_notice( $message = '', $classes = 'notice-info', $type = 'Note' ){ ?>
		<div class="notice <?php echo $classes; ?>">
			<p><strong><?php echo $type; ?></strong>: <?php echo $message; ?></p>
		</div>
	<?php }

	/**
	 * Create a JSON Response for AJAX Requests
	 *
	 * @param int $status - The desired Status Code
	 * @param string $message - The desired Message
	 * @param (assoc) array $additional_info - Any other information to add to the response array
	 * @return string - echos a json_encoded array for use in AJAX
	 */
	public function json_response( $status = 501, $message = '', $additional_info = null ){
		$response = [];

		$response['status']  = $status;
		$response['message'] = $message;

		if( $additional_info ){
			foreach( $additional_info as $key => $value ){
				$response[$key] = $value;
			}
		}

		echo json_encode( $response );
		wp_die();
	}

	/**
	 * Stop AJAX functions if no $_POST data
	 */
	public function require_POST(){
		if( ! $_POST )
			wp_die( 'Please do not call this function directly, only make POST requests.' );
	}

	/**
	 * AJAX Function to Toggle Content Mask on/off per page
	 *
	 * @return echos a JSON response for use in JavaScript
	 */
	public function toggle_content_mask(){
		$this->require_POST();
		extract( $_POST );

		if( ! $postID || ! $newState )
			$this->json_response( 403, 'No Values Detected' );

		if( $newState == 'enabled' ){
			$meta_new_state     = true;
			$meta_current_state = false;
		} else if( $newState == 'disabled' ){
			$meta_new_state     = false;
			$meta_current_state = true;
		} else {
			$this->json_response( 403, 'Unauthorized Values Detected' );
		}

		if( update_post_meta( $postID, 'content_mask_enable', $meta_new_state, $meta_current_state ) ){
			$this->json_response( 200, 'Content Mask for <strong>'. get_the_title( $postID ) .'</strong> has been <strong>'. $newState .'</strong>' );
		} else {
			$this->json_response( 400, 'Can\'t change Content Mask Settings. Request Failed.' );
		}
	}

	/**
	 * Refresh a Cached Content Mask
	 *
	 * @return void
	 */
	public function refresh_transient(){
		$this->require_POST();
		extract( $_POST );

		if( ! $maskURL || ! $postID || ! $transient )
			$this->json_response( 403, 'No Values Detected' );

		$body = wp_remote_retrieve_body( wp_remote_get( $maskURL ) );
		$body = $this->replace_relative_urls( $maskURL, $body );

		/**
		 * Allow Custom Scripts and Styles in page
		 */
		$styles  = ( get_option( 'content_mask_allow_styles_download' ) == true ) ? wp_unslash( esc_textarea( get_option( 'content_mask_custom_styles_download' ) ) ) : '';
		$scripts = ( get_option( 'content_mask_allow_scripts_download' ) == true ) ? wp_unslash( get_option( 'content_mask_custom_scripts_download' ) ) : '';

		$body = str_replace( '</head>', '<style>'.$styles.'</style>'.$scripts.'</head>', $body );

		if( ! strlen( $body > 125 ) ){
			delete_transient( $transient );

			if( set_transient( $transient, $body, $expiration ) ){
				$this->json_response( 200, 'Mask Cache for <strong>'. get_the_title( $postID ) .'</strong> Refreshed!' );
			} else {
				$this->json_response( 400, 'Mask Cache Refresh for '. get_the_title( $postID ) .' Failed.' );
			}
		} else {
			$this->json_response( 400, 'Remote Content Mask URL for '. get_the_title( $postID ) .' could not be reached.' );
		}
	}

	/**
	 * Refresh a Cached Content Mask
	 *
	 * @return void
	 */
	public function delete_content_mask(){
		$this->require_POST();
		extract( $_POST );

		$errors = false;

		if( delete_post_meta( $postID, 'content_mask_url' ) ){
			if( delete_post_meta( $postID, 'content_mask_enable' ) ){
				if( delete_post_meta( $postID, 'content_mask_method' ) ){
					$this->json_response( 200, 'Content Mask Successfully Removed' );
				} else {
					$errors = true;
				}
			} else {
				$errors = true;
			}
		} else {
			$errors = true;
		}

		if( $errors === true )
			$this->json_response( 403, 'Error Removing Content Mask' );
	}

	/**
	 * Update a value-based Option
	 *
	 * @return void
	 */
	public function update_content_mask_option(){
		$this->require_POST();
		extract( $_POST );

		if( ! $option || ! $value )
			$this->json_response( 403, 'No Values Detected' );

		if( update_option( $option, $value ) ){
			$this->json_response( 200, "<strong>$label</strong> have been updated!" );
		} else {
			$this->json_response( 400, 'Request Failed.' );
		}
	}

	/**
	 * Load More Pages into Content Mask Admin Table
	 *
	 * @return void
	 */
	public function load_more_pages(){
		$this->require_POST();
		extract( $_POST );

		if( ! $offset )
			$this->json_response( 403, 'No Values Detected' );

		$args = [
			'offset'      => $offset,
			'post_status' => ['publish', 'draft', 'pending', 'private'],
			'post_type'   => get_post_types( '', 'names' ),
			'meta_query'  => [[
				'key'	  	=> 'content_mask_url',
				'value'   	=> '',
				'compare' 	=> '!=',
			]],
			'posts_per_page' => 10
		];

		if( ! current_user_can( 'edit_others_posts' ) ) $args['perm'] = 'editable';

		$query = new WP_Query( $args );

		if( $query->have_posts() ){
			$columns = array(
				'Method',
				'Status',
				'Info',
				'Type',
				'Views',
				'Non-User',
				'Unique',
				'More'
			);

			ob_start();

			while( $query->have_posts() ){
				$query->the_post();
				$post_id     = get_the_ID();
				$post_fields = $this->get_post_fields( $post_id );

				extract( $post_fields );

				$state = filter_var( $content_mask_enable, FILTER_VALIDATE_BOOLEAN ) ? 'enabled' : 'disabled';
				
				echo "<tr data-attr-id='$post_id' data-attr-state='$state' class='$state'>";
					foreach( $columns as $column ){
						$column = sanitize_title( $column );

						echo "<td class='$column'>";
							echo '<div>';
								$this->content_mask_display_column( $column, $post_id, $post_fields );
							echo '</div>';
						echo '</td>';
					}
				echo '</tr>';
			}

			$rows = ob_get_clean();
			$this->json_response( 200, $rows );
		} else {
			$this->json_response( 200, '<tr><td colspan="10"><h2>No More Content Masks Found</h2></td></tr>', ['notice' => 'no remaining'] );
		}
	}

	/**
	 * Toggle Content Mask Option
	 *
	 * @return void
	 */
	public function toggle_content_mask_option(){
		$this->require_POST();
		extract( $_POST );

		if( ! $currentState || ! $optionName )
			$this->json_response( 403, 'No Values Detected' );

		if( $currentState == 'enabled' ){
			$newState = false;
			$displayNewState = 'disabled';
		} else if( $currentState == 'disabled' ){
			$newState = true;
			$displayNewState = 'enabled';
		} else {
			$this->json_response( 403, 'Unauthorized Values Detected.', ['newState' => ( filter_var( get_option( $optionName ), FILTER_VALIDATE_BOOLEAN ) ) ? 'enabled' : 'disabled'] );
		}

		if( update_option( $optionName, $newState ) ){
			$this->json_response( 200, $optionDisplayName. ' has been <strong>'. ucwords( $displayNewState ) .'</strong>.', ['newState' => $displayNewState]);
		} else {
			$this->json_response( 400, 'Request Failed.', ['newState', $currentState] );
		}
	}

	/**
	 * Replace Relative URLs in cached content
	 *
	 * @param string $url - The URL to validate
	 * @param string $str - The string to replace within
	 * @param bool $protocol_relative - whether to use a protocol or just `//`
	 * @return mixed - preg_replaced content, or false if invalid URL
	 */
	public function replace_relative_urls( $url, $str, $protocol_relative = true ){
		if( $this->validate_url( $url ) ){
			$url = ( $protocol_relative === true ) ? str_replace( ['http://', 'https://'], '//', $url ) : $url;
			$url = ( substr( $url, -1 ) === '/' ) ? substr( $url, 0, -1 ) : $url;

			//return preg_replace('~(?:src|action|href)=[\'"]\K/(?!/)[^\'"]*~', "$url$0", $str);
			
			// Perhaps a slightly more robust Regex to grab ones like `<img src="img/test.jpg"/>`
			// https://regex101.com/r/Kjcskm/1

			return preg_replace('~(?:src|action|href)=[\'"]\K(?:/|(?!http))(?!/)[^\'"]*~', "$url/$0", $str);
		} else {
			return false;
		}
	}

	/**
	 * Convert a Time String to Seconds
	 *
	 * @param string $input - the string to turn to seconds (e.g. 4 Weeks)
	 * @return int - The time string converted to seconds
	 */
	public function time_to_seconds( $input ){
		if( $input != 'never' ){;
			$ex   = explode( ' ', $input );
			$int  = intval( $ex[0] );
			
			if( isset( $ex[1]) ){
				$type = strtolower( $ex[1] );

					 if( $type == 'hour' ) $mod = 3600;
				else if( $type == 'day'  ) $mod = 86400;
				else if( $type == 'week' ) $mod = 604800;
				else $mod = 1;

				$expiration = $int * $mod;
			} else {
				$expiration = 0;
			}
		} else {
			$expiration = 0;
		}

		return intval( $expiration );
	}

	/**
	 * Clean URL and return just the Host
	 *
	 * @param string $url - The URL to parse and clean
	 * @return string - the host of the URL
	 */
	public function extract_url_host( $url ){
		// Remove Relative Slashes
		$url = trim( $url, '/' );

		// Prepend Scheme if not included
		if( !preg_match('#^http(s)?://#', $url) ){
    		$url = 'http://' . $url;
		}

		// Parse URL for domain
		$url_parts = parse_url( $url );

		// Return Just the Host
		return preg_replace('/^www\./', '', $url_parts['host']);
	}

	/**
	 * Set the current version of a browser for the user-agent
	 *
	 * @param string $browser - Either "Google Chrome", "Mozilla Firefox", or "NULL" (defaults to Chrome)
	 * @return string
	 */
	public function content_mask_user_agent( $browser = 'Google Chrome' ){
		if( false === ( $content_mask_user_agent = get_transient( 'content_mask_user_agent' ) ) ){
			$url = 'http://vergrabber.kingu.pl/vergrabber.json';

			$versions_json  = wp_remote_retrieve_body( wp_remote_get( $url ) );
			$versions_array = json_decode( $versions_json, true );

			if( $browser == 'Mozilla Firefox' ){
				$client     = array_shift( $versions_array['client']['Mozilla Firefox'] );
				$version    = $client['version']; 
				$user_agent = "Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:47.0) Gecko/20100101 Firefox/$version";
			} else {
				$client     = array_shift( $versions_array['client']['Google Chrome'] );
				$version    = $client['version']; 
				$user_agent = "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/$version Safari/537.36";
			}

			$content_mask_user_agent = set_transient( 'content_mask_user_agent', $user_agent, WEEK_IN_SECONDS );
		}

		return $content_mask_user_agent;
	}

	/**
	 * Return "Content Mask URL" Content via Download Method
	 *
	 * @param string $url - The URL that contains the desired content
	 * @param int $expiration - The number of seconds for the cache to last
	 * @param bool $user_agent_header - Whether or not to apply advanced user agents to `wp_remote_get`
	 *        which can be useful if a user is getting forbidden errors.
	 * @return string - The full markup of the $url parameter
	 */
	public function get_page_content( $url, $expiration = 14400, $user_agent_header = false ){
		$transient_name = 'content_mask-'. strtolower( preg_replace( "/[^a-z0-9]/", '', $url ) );
		
		$body = get_transient( $transient_name );

		if( false === $body || strlen( $body ) < 125 ){
			if( $user_agent_header == true ){
				$wp_remote_args = array(
					'httpversion' => '1.1',
					'timeout'     => 10,
					'user-agent'  => $this->content_mask_user_agent()
				);
				$body = wp_remote_retrieve_body( wp_remote_get( $url, $wp_remote_args ) );
			} else {
				$body = wp_remote_retrieve_body( wp_remote_get( $url ) );
			}

			$body = $this->replace_relative_urls( $url, $body );

			/**
			 * Allow Custom Scripts and Styles in page
			 */
			$styles  = ( get_option( 'content_mask_allow_styles_download' ) == true ) ? wp_unslash( esc_textarea( get_option( 'content_mask_custom_styles_download' ) ) ) : '';
			$scripts = ( get_option( 'content_mask_allow_scripts_download' ) == true ) ? wp_unslash( get_option( 'content_mask_custom_scripts_download' ) ) : '';

			$body = str_replace( '</head>', '<style>'.$styles.'</style>'.$scripts.'</head>', $body );

			set_transient( $transient_name, $body, $expiration );
		}

		return $body;
	}

	/**
	 * Return a Full Page Iframe to Simulate a Full Page
	 *
	 * @param string $url - The URL for which to embed in the iframe
	 * @return string - A full HTML page markup with the iframe included.
	 */
	public function get_page_iframe( $url ){
		$url     = is_ssl() ? str_replace( 'http://', 'https://', esc_url( $url ) ) : esc_url( $url );
		$favicon = ( has_site_icon() ) ? '<link class="wp_favicon" href="'. get_site_icon_url() .'" rel="shortcut icon"/>' : '';

		/**
		 * Allow Custom Scripts and Styles in page now
		 */
		$styles  = ( get_option( 'content_mask_allow_styles_iframe' ) == true ) ? wp_unslash( esc_textarea( get_option( 'content_mask_custom_styles_iframe' ) ) ) : '';
		$scripts = ( get_option( 'content_mask_allow_scripts_iframe' ) == true ) ? wp_unslash( get_option( 'content_mask_custom_scripts_iframe' ) ) : '';
	
		ob_start(); ?>
		
		<!DOCTYPE html>
			<head>
				<?php echo $favicon; ?>
				<style>
					body { margin: 0; }
					iframe {
						display: block;
						border: none;
						height: 100vh;
						width: 100vw;
						box-sizing: border-box;
					}
					<?php echo $styles; ?>
				</style>
				<title><?php echo apply_filters( 'wp_title', get_bloginfo( 'name' ) . wp_title( '|', false, 'left' ) ); ?></title>
				<meta name="viewport" content="width=device-width, initial-scale=1">
				<script type="text/javascript">
					// From https://gist.github.com/niyazpk/f8ac616f181f6042d1e0
					function updateUrlParameter(uri, key, value) {
					    // remove the hash part before operating on the uri
					    var i = uri.indexOf("#");
					    var hash = i === -1 ? ""  : uri.substr(i);
					         uri = i === -1 ? uri : uri.substr(0, i);

					    var re = new RegExp("([?&])" + key + "=.*?(&|$)", "i");
					    var separator = uri.indexOf("?") !== -1 ? "&" : "?";
					    if (uri.match(re)) {
					        uri = uri.replace(re, "$1" + key + "=" + value + "$2");
					    } else {
					        uri = uri + separator + key + "=" + value;
					    }
					    return uri + hash;  // finally append the hash as well
					}
				</script>
				<?php do_action( 'content_mask_iframe_header' ); ?>
				<?php echo $scripts; ?>
			</head>
			<body>
				<iframe id="content-mask-frame" width="100%" height="100%" src="<?php echo $url; ?>" frameborder="0" allowfullscreen></iframe>
				<?php do_action( 'content_mask_iframe_footer' ); ?>
			</body>
		</html>

		<?php return ob_get_clean();
	}

	/**
	 * Attempt to Get a Visitor's IP, and Hash it
	 *
	 * @param bool $hash - Whether or not to hash the IP Address
	 * @return string - The hashed (or not) IP Address, or a not found message.
	 */
	public function get_client_ip( $hash = true ){
			 if( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) )       $ip = $_SERVER['HTTP_CLIENT_IP'];
		else if( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
		else $ip = $_SERVER['REMOTE_ADDR'];

		if( $hash == true ){
			$ip = str_replace( '.', '', $ip );
			$ip = strtr( $ip, '1234567890', '_|]"^*~-+!' ); // Translate string to the weird salt above.
		}

		return $this->issetor( $ip, 'IP Not Found' );
	}

	/**
	 * Show Post According to Content Mask Settings
	 *
	 * @param int $post_id - The Post ID to check (should be the Current Post)
	 * @return mixed - Void, Redirect, or Echoe'd Page Content
	 */
	public function show_post( $post_id ){
		extract( $this->get_post_fields( $post_id ) );
		$url = esc_url( $content_mask_url );

		$method = sanitize_text_field( $content_mask_method );

		$this->issetor( $content_mask_tracking, false );

		// Are we tracking this request?
		if( filter_var( get_option( 'content_mask_tracking' ), FILTER_VALIDATE_BOOLEAN ) ){
			$ip = $this->get_client_ip( true );
			$views = get_post_meta( $post_id, 'content_mask_views', true );

			if( $views == '' || !$views ){
				$views = array();

				$views['anon']   = 0;
				$views['total']  = 0;
				$views['unique'] = array();
			}

			 // How many times the page has been viewed, period
			$views['total'] = (int) $views['total'] + 1;
			
			// How many times it's been viewed by non-logged in users
			if( ! is_user_logged_in() )
				$views['anon'] = (int) $views['anon'] + 1;

			// Add unique (hashed) IPs to array, we'll `count()` these for number.
			if( ! in_array( $ip, $views['unique'] ) )
				$views['unique'][] = $ip;

			update_post_meta( $post_id, 'content_mask_views', $views );
		}

		// Do we need to send HTTP Headers?
		$user_agent_header = filter_var( get_option( 'content_mask_user_agent_header' ), FILTER_VALIDATE_BOOLEAN ) ? true : false;

			 if( $method === 'download' ) echo $this->get_page_content( $url, $this->time_to_seconds( $this->issetor( $content_mask_transient_expiration ) ), $user_agent_header );
		else if( $method === 'iframe' )   echo $this->get_page_iframe( $url );
		else if( $method === 'redirect' ) wp_redirect( $url, 301 );
		else echo $this->get_page_content( $url, $this->time_to_seconds( $this->issetor( $content_mask_transient_expiration ) ), $user_agent_header );

		exit();
	}

	/**
	 * Determine if Content Mask Should Take Over this Request
	 *
	 * @return void
	 */
	public function process_page_request(){
		global $post;

		// Skip if not a single post, or a 404 page.
		if( ! is_singular() || is_404() )
			return;

		extract( $this->get_post_fields( $post->ID ) );

		if( ! post_password_required( $post->ID ) ){
			if( isset( $content_mask_enable ) ){
				if( filter_var( $content_mask_enable, FILTER_VALIDATE_BOOLEAN ) ){
					/**
					 * We're past PW Protection, Content Mask is enabled and turned on.
					 * Now validate the desired URL.
					 */
					if( $this->validate_url( $content_mask_url ) === true ){
						
						/**
						 * Remove all scripts and styles, since they affect page content
						 * if left alone, depending on how they're hooked in. The external
						 * content isnt' designed with this site's plugins/scripts/styles
						 * in mind, so the site can look strange.
						 */
						foreach( array( 'wp_footer', 'wp_head', 'wp_enqueue_scripts', 'wp_print_scripts', 'wp_print_styles' ) as $hook )
							remove_all_actions( $hook );
						
						$this->show_post( $post->ID );
					} else {
						/**
						 * URL Validation Failed. Alert logged-in users, and return the original reqeuest
						 */
						add_action( 'wp_footer', function(){
							if( is_user_logged_in() )
								echo '<div style="border-left: 4px solid #c00; box-shadow: 0 5px 12px -4px rgba(0,0,0,.5); background: #fff; padding: 12px 24px; z-index: 16777271; position: fixed; top: 42px; left: 10px; right: 10px;">It looks like you have enabled a Content Mask on this post, but don\'t have a valid URL. <a style="display: inline-block; text-decoration: none; font-size: 13px; line-height: 26px; height: 28px; margin: 0; padding: 0 10px 1px; cursor: pointer; border-width: 1px; border-style: solid; -webkit-appearance: none; border-radius: 3px; white-space: nowrap; box-sizing: border-box; background: #0085ba; border-color: #0073aa #006799 #006799; box-shadow: 0 1px 0 #006799; color: #fff; text-decoration: none; text-shadow: 0 -1px 1px #006799, 1px 0 1px #006799, 0 1px 1px #006799, -1px 0 1px #006799; float: right;" class="wp-core-ui button primary" href="'. get_edit_post_link() .'#content_mask_url">Edit Content Mask</a></div>';
						});

						return; // Failed URL test
					}
				} else {
					return; // Failed to have Content Mask Enabled set to `true`
				}
			} else {
				return; // Enable isn't even set
			}
		}

		return; // Return the original request in all other instances
	}

	/**
	 * The Metabox on edit.php
	 */
	public function add_meta_boxes(){
		add_meta_box( 'content-mask-metabox', "Content Mask Settings", function(){ require_once dirname(__FILE__).'/inc/metabox.php'; }, null, 'normal', 'high' );
	}

	/**
	 * Validate/Sanitize URLs
	 *
	 * @param string $url - The URL to sanitize and validate
	 * @return bool - true if valid, false if not.
	 */
	public function validate_url( $url ){
		$url = filter_var( esc_url( $url ), FILTER_SANITIZE_URL );

		/**
		 * Check to see if a TLD is set, filter_var( $url, FILTER_VALIDATE_URL )
		 * apparently doesn't check for one.
		 */
		if( ! strpos( $url, '.' ) ){
			return false;
		}

		return !filter_var( $url, FILTER_VALIDATE_URL ) === false ? true : false;
	}


	/**
	 * Sanitize URLs when saving to Database
	 *
	 * @param string $url - The URL to Sanitize
	 * @return string - The sanitized URL, or false if it's invalid.
	 */
	public function sanitize_url( $url ){
		if( isset( $url ) ){
			$url = sanitize_text_field( $url );

			/**
			 * If no protocol, set `http://`, since most secured sites will
			 * forward to `https://`, but not every site is secure yet.
			 */
			$url = ( ! strpos( $url, '://') ) ? "http://$url" : $url;

			/**
			 * Make sure a valid protocol is set, and it's a valid URL
			 */
			if( substr( $url, 0, 4) === 'http' && $this->validate_url( $url ) ){
				return $url;
			} else {
				return false;
			}
		} else {
			return false; // URL Not Defined
		}
	}

	/**
	 * Sanitize Select Fields when saving to Database
	 *
	 * @param string $input - The submitted value
	 * @param array $valid_values - The only accepted values
	 * @return string - The accepted string, or false if it's invalid.
	 */
	public function sanitize_select( $input, $valid_values ){
		if( isset( $input ) ){
			$input = sanitize_text_field( $input );

			/**
			 * Make sure the input value is any one of the expected values.
			 */
			if( in_array( $input, $valid_values ) ){
				return $input;
			} else {
				return false; // Unexpected value, probably manually added
			}
		} else {
			return false; // Input not sent
		}
	}

	/**
	 * Sanitize Checkboxes when saving to Database
	 *
	 * @param string $input - The input to validate
	 * @return bool - True if defined, false otherwise.
	 */
	public function sanitize_checkbox( $input ){
		if( isset( $input ) ){
			if( filter_var( $input, FILTER_VALIDATE_BOOLEAN ) ){
				return true; // A boolean "true" value was set, (1, '1', 01, '01', 'on', 'yes', true, 'true') etc.
			} else {
				return false; // A boolean "false" value was set -OR- a janky value we don't want was set, unset it.
			}
		} else {
			return false; // Checkboxes may not be submitted, so "set" to false
		}
	}

	/**
	 * Save the Post Meta
	 *
	 * @param int $post_id - The Post ID that's being updated.
	 */
	public function save_meta( $post_id ){
		if( isset( $_POST ) ){
			if( isset( $_POST['content_mask_meta_nonce'] ) && wp_verify_nonce( $_POST['content_mask_meta_nonce'], 'save_post' ) ){
				extract( $_POST );

				foreach( self::$RESERVED_KEYS as $key ) $this->issetor( ${$key} );

				// Content Mask URL - should only allow URLs, nothing else, otherwise set it to empty/false
				update_post_meta( $post_id, 'content_mask_url', $this->sanitize_url( $content_mask_url ) );

				// Content Mask Method - Should be 1 of 3 values, otherwise default it to 'download'
				$method = ( $content_mask_method === null ) ? 'download' : $this->sanitize_select( $content_mask_method, ['download', 'iframe', 'redirect'] );
				update_post_meta( $post_id, 'content_mask_method', $method );

				// Content Mask Enable - Being tricky to unset, so we update it always and just set it to true/false based on whether or not it was empty
				update_post_meta( $post_id, 'content_mask_enable', $this->sanitize_checkbox( $content_mask_enable ) );

				// Delete the cached 'download' copy any time this Page, Post or Custom Post Type is updated.
				delete_transient( 'content_mask-'. strtolower( preg_replace( "/[^a-z0-9]/", '', $content_mask_url ) ) );

				// Set Cache Expiration only if 'download' method is being used.
				if( $method == 'download' ){
					// Content Mask Transient Expiration
					$expirations = [];
					foreach( range(1, 12) as $hour ){ $expirations[] = $hour .' Hour'; }
					foreach( range(1, 6)  as $day ){  $expirations[] = $day .' Day'; }
					foreach( range(1, 4)  as $week ){ $expirations[] = $week .' Week'; }

					update_post_meta( $post_id, 'content_mask_transient_expiration', $this->sanitize_select( $content_mask_transient_expiration, $expirations ) );
				}
			}
		}
	}

	/**
	 * Display a Custom Column
	 *
	 * @param $columns - The columns hooked on the post list
	 * @return array - Array of columns
	 */
	public function content_mask_column( $columns ){
		$columns['content-mask'] = 'Mask';
		return $columns;
	}

	/**
	 * Display Custom Content in the Mask admin column
	 *
	 * @param string $column - The target column
	 * @param int $post_id - The desired Post to check
	 * @return echoed HTML for the column
	 */
	public function content_mask_column_content( $column, $post_id ){
		switch( $column ){
			case 'content-mask':
				extract( $this->get_post_fields( get_the_ID() ) );
				$enabled = !empty( $content_mask_enable ) ? 'enabled' : 'disabled';
				
				/**
				 * Only show enabled/disabled icons on pages with a Content Mask URL
				 */
				if( $content_mask_url ){
					echo "<div class='content-mask-method $enabled' data-attr-state='$enabled'><div>";
						$this->display_svg( $content_mask_method, 'icon', 'title="'. ucwords( $content_mask_method ) .'"', true );
					echo '</div></div>';
				}
				
				break;
		}
	}
}

add_action( 'plugins_loaded', ['ContentMask', 'get_instance'] );
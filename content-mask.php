<?php
/**
 * Plugin Name: Content Mask
 * Plugin URI:  http://xhynk.com/content-mask/
 * Description: Easily embed external content into your website without complicated Domain Forwarders, Domain Masks, APIs or Scripts
 * Version:     1.7.0.8
 * Author:      Alex Demchak
 * Author URI:  http://xhynk.com/
 *
 * @package ContentMask
 *
 * Copyright Alexander Demchak, Third River Marketing LLC
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see http://www.gnu.org/licenses.
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
		//'do_upgrade',
		'load_more_pages',
		'refresh_transient',
		'delete_content_mask',
		'toggle_content_mask',
		'update_content_mask_option',
		'toggle_content_mask_option'
	);
	public static $DB_VERSION = 2;

	/**
	 * Class Constructor - Runs Action Hooks
	 */
	public function __construct(){
		add_action( 'save_post', [$this, 'save_meta'], 10, 1 );
		add_action( 'admin_menu', [$this, 'register_admin_menu'] );
		add_action( 'admin_notices', [$this, 'display_admin_notices'] );
		//add_action( 'admin_notices', [$this, 'maybe_upgrade']);
		add_action( 'add_meta_boxes', [$this, 'add_meta_boxes'], 1, 2 );
		add_action( 'template_redirect', [$this, 'process_page_request'], 1, 2 );
		add_action( 'admin_enqueue_scripts', [$this, 'exclusive_admin_assets'] );
		add_action( 'admin_enqueue_scripts', [$this, 'global_admin_assets'] );
		add_action( 'manage_posts_custom_column', [$this, 'content_mask_column_content'], 10, 2 );
		add_action( 'manage_pages_custom_column', [$this, 'content_mask_column_content'], 10, 2 );

		foreach( self::$AJAX_ACTIONS as $action )
			add_action( "wp_ajax_$action", [$this, $action] );

		add_filter( 'admin_body_class', [$this, 'add_admin_body_classes'], 27 );
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
	 * Get This Plugin's Information
	 * 
	 * @return array - Plugin Metadata for Content Mask
	 */
	public function get_content_mask_data(){
		if( is_admin() ){
			return get_plugin_data( __FILE__, false, false );
		} else {
			return array( 'Version' => '1.7.0.7' );
		}
	}

	/**
	 * Maybe Upgrade
	 */
	public function maybe_upgrade(){
		// Don't even attempt unless admin
		if( current_user_can( 'manage_options' ) ){
			$do_upgrade = false;
			$present_upgrade = false;
			$hide_notice = get_option( 'content_mask_hide_database_upgrade_notice' );

			if( ! $hide_notice ){
				if( $content_mask_database_version = get_option( 'content_mask_database_version' ) ){
					// DB Version is Set - Is it High Enough?
					if( $content_mask_database_version < 2 ){
						$present_upgrade = true;
					}
				} else {
					// DB Version Not Set (Pre 1.7.0.4), set to 1
					update_option( 'content_mask_database_version', 1 );
					$content_mask_database_version = 1;
					$present_upgrade = true;
				}
			}

			// Potentially Show Popup
			if( $present_upgrade == true ){
				$this->create_admin_notice( 'Content Mask requires a small Database Upgrade to function efficiently. <a id="content-mask-upgrade" data-current-version="'. $content_mask_database_version .'" data-new-version="2" href="#">Allow Upgrade</a> <a style="float: right;" href="#">Hide This Notice</a>', 'notice-warning', 'Alert', 'content-mask-upgrade-notice' );
			}
		}
	}

	/**
	 * Do Upgrade
	 */
	function do_upgrade(){
		$content_mask_args = array(
			'post_status' => 'any',
			'post_type'   => get_post_types( '', 'names' ),
			'meta_query'  => [[
				'key'	  	=> 'content_mask_url',
				'value'   	=> '',
				'compare' 	=> '!=',
			]],
			'posts_per_page' => 10
		);
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

		return $classes;
	}

	/**
	 * Add the Content Mask Admin Page
	 *
	 * @return void
	 */
	public function register_admin_menu(){
		// TODO: Make Submenu items highlight on focus
		add_menu_page( 'Content Mask', 'Content Mask', 'edit_posts', 'content-mask', [$this, 'admin_panel'], '' );
		add_submenu_page( 'content-mask', 'Content Mask Options', 'Options', 'edit_posts', 'content-mask&tab=options', function(){ return false; } );
		add_submenu_page( 'content-mask', 'Content Mask Advanced', 'Advanced', 'edit_posts', 'content-mask&tab=advanced', function(){ return false; } );
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
			if( function_exists('wp_enqueue_code_editor' ) ){
				wp_enqueue_code_editor( array( 'type' => 'text/html' ) );
				wp_enqueue_script( 'content-mask-code-editor', "$assets_dir/js/code-editor.min.js", array( 'jquery' ), filemtime( plugin_dir_path( __FILE__ ) . 'assets/js/code-editor.min.js' ), true );
			}
		}
	}

	/**
	 * Enqueue Admin Only Assets
	 *
	 * @param string $hook - The current wp-admin hook.
	 * @return void
	 */
	public function global_admin_assets(){
		// TODO: Remove .png sprite and use SVG/Font
		echo '<style>
			#adminmenu #toplevel_page_content-mask .wp-menu-image:before { display: none; } /* Hide Gear */
			#adminmenu #toplevel_page_content-mask .wp-menu-image { background: url( '. plugins_url( 'content-mask/assets/img/icon-sprite.png' ) .' ) left top no-repeat !important; background-size: cover !important;} /* Load Sprite */
			#adminmenu #toplevel_page_content-mask:hover .wp-menu-image { background-position: left center !important; } /* Hover Blue Icon */
			#adminmenu #toplevel_page_content-mask.current .wp-menu-image,
			#adminmenu #toplevel_page_content-mask.wp-has-current-submenu .wp-menu-image { background-position: left bottom !important; } /* Active White Icon */
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
				$this->echo_svg( "method-$content_mask_method", 'icon', "title='$content_mask_method'" );
				break;

			case 'info':
				echo '<strong><a href="'. get_the_permalink() .'" target="_blank">'. get_the_title() .'</a></strong><br>';
				echo '<span class="meta"><a href="'. $content_mask_url .'" target="_blank">'. $content_mask_url .'</a></span>';
				break;

			case 'status':
				echo '<span class="label">'. $content_mask_method .'</span>';
				if( $content_mask_method === 'download' ){
					$transient = 'content_mask-'. str_replace( '.', '_', $this->get_content_mask_data()['Version'] ) .'-'. strtolower( preg_replace( "/[^a-z0-9]/", '', $content_mask_url ) );

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
				$transient                = 'content_mask-'. str_replace( '.', '_', $this->get_content_mask_data()['Version'] ) .'-'. strtolower( preg_replace( "/[^a-z0-9]/", '', $content_mask_url ) );
				$data_expiration          = $content_mask_transient_expiration ? $this->time_to_seconds( $this->issetor( $content_mask_transient_expiration ) ) : $this->time_to_seconds( '4 hour' );
				$data_expiration_readable = $content_mask_transient_expiration ? $content_mask_transient_expiration : '4 hour'; ?>
				<div class="more-container">
					<?php $this->echo_svg( 'more-horizontal', 'icon', "title='More Options'" ); ?>
					<ul class="more-nav">
						<li><a href="<?php echo get_permalink( $post_id ); ?>" target="_blank"><?php $this->echo_svg( "method-$content_mask_method", 'icon', "title='View $post_type'" ); ?> <span>View <?php echo $post_type; ?></span></a></li>
						<li><a href="<?php echo get_edit_post_link( $post_id ); ?>"><?php $this->echo_svg( 'edit', 'icon', "title='Edit Content Mask'" ); ?> <span>Edit <?php echo $post_type; ?></span></a></li>
						<?php if( $content_mask_method === 'download' ) { ?><li><a href="#" class="refresh-transient" data-expiration-readable="<?php echo strtolower( $data_expiration_readable ); ?>s" data-expiration="<?php echo $data_expiration; ?>" data-transient="<?php echo $transient; ?>"><?php $this->echo_svg( 'refresh', 'icon', "title='Edit Content Mask'" ); ?> <span>Refresh Transient</span></a></li><?php } ?>
						<li><a href="<?php echo $content_mask_url ?>" target="_blank"><?php $this->echo_svg( 'bookmark', 'icon', "title='View Source'" ); ?> <span>View Source URL</span></a></li>
						<hr>
						<li><a href="#" class="remove-mask"><?php $this->echo_svg( 'trash', 'icon', "title='Delete Mask'" ); ?> <span>Remove Mask</span></a></li>
					</ul>
				</div>
				<?php break;
		}
	}

	/**
	 * Return a custom SVG (Sources Provided in part by feathericons.com)
	 *
	 * @param string $icon - The desired icon to display
	 * @param string $class - A space separated list of classes to add
	 * @param string $attr - A custom attribute string to display
	 * @return string - The final usable SVG HTML
	 */
	public function get_svg( $icon = '', $class = '', $attr = '', $viewbox = '0 0 24 24' ){
		// svg-icons.php includes all switch/case for the internal parts of the <svg> tag below
		include plugin_dir_path(__FILE__).'/inc/svg-icons.php';

		return "<svg class='$class svg-$icon content-mask-svg' $attr viewBox='$viewbox' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'>$svg</svg>";
	}

	/**
	 * Echo a custom SVG (Sources Provided in part by feathericons.com)
	 *
	 * @uses get_svg();
	 * @param string $icon - The desired icon to display
	 * @param string $class - A space separated list of classes to add
	 * @param string $attr - A custom attribute string to display
	 * @return string - Echoes the final usable SVG from get_svg();
	 */
	public function echo_svg( $icon = '', $class = '', $attr = '', $viewbox = '0 0 24 24' ){
		echo $this->get_svg( $icon, $class, $attr, $viewbox );
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
	public function create_admin_notice( $message = '', $classes = 'notice-info', $type = 'Note', $id = false ){ ?>
		<div <?php if( $id ){ echo "id='$id'"; } ?> class="notice <?php echo $classes; ?>">
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

			return preg_replace('~(?:src|action|href)=[\'"]\K(?:/|(?!http|tel|mailto))(?!/)[^\'"]*~', "$url/$0", $str);
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
		$transient_name = 'content_mask-'. str_replace( '.', '_', $this->get_content_mask_data()['Version'] ) .'-'. strtolower( preg_replace( "/[^a-z0-9]/", '', $url ) );
		
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
	public function add_meta_boxes( $page ){
		add_meta_box( 'content-mask-metabox', 'Content Mask Settings', function(){ require_once dirname(__FILE__).'/inc/metabox.php'; }, $page, 'advanced', 'high' );
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
				delete_transient( 'content_mask-'. str_replace( '.', '_', $this->get_content_mask_data()['Version'] ) .'-'. strtolower( preg_replace( "/[^a-z0-9]/", '', $content_mask_url ) ) );

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
				extract( $this->get_post_fields( $post_id ) );
				$enabled = !empty( $content_mask_enable ) ? 'enabled' : 'disabled';
				
				/**
				 * Only show enabled/disabled icons on pages with a Content Mask URL
				 */
				if( $content_mask_url ){
					echo "<div class='content-mask-method $enabled' data-attr-state='$enabled'><div>";
						$this->echo_svg( "method-$content_mask_method", 'icon', 'title="'. ucwords( $content_mask_method ) .'"' );
					echo '</div></div>';
				}
				
				break;
		}
	}
}

add_action( 'plugins_loaded', ['ContentMask', 'get_instance'] );
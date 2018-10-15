<?php
	// Define Query Args for Pages/Posts/CPTs with Content Masks defined
	$load = 20;

	$args = array(
		'post_status' => ['publish', 'draft', 'pending', 'private'],
		'post_type'   => get_post_types( '', 'names' ),
		'meta_query'  => [[
			'key'	  	=> 'content_mask_url',
			'value'   	=> '',
			'compare' 	=> '!=',
		]],
		'posts_per_page' => $load
	);

	// Force only own posts if applicable
	if( ! current_user_can( 'edit_others_posts' ) )
		$args['perm'] = 'editable';

	// Initialize Query
	$query = new WP_Query( $args );

	// Define Table Columns
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

	// Define Togglable Options
	$toggle_options = array(
		'tracking',
		'user_agent_header',
		'allow_scripts_download',
		'allow_styles_download',
		'allow_scripts_iframe',
		'allow_styles_iframe',
	);

	// Set Parameters based on Boolean Toggle Values
	foreach( $toggle_options as $option ){
		if( filter_var( get_option( "content_mask_$option" ), FILTER_VALIDATE_BOOLEAN ) ){
			${$option.'_checked'} = 'checked="checked"';
			${$option.'_enabled'} = 'enabled';
		} else {
			${$option.'_checked'} = '';
			${$option.'_enabled'} = 'disabled';
		}
	}
?>
<div id="content-mask" class="wrap">
	<h1 class="headline"><?php echo $this->display_svg( 'content-mask' ); ?> <span>Content</span> <strong>Mask</strong> <span id="mobile-nav-toggle"><?php echo $this->display_svg( 'menu' ); ?></span><span id="header-nav" class="alignright"><?php require_once dirname(__FILE__).'/admin-buttons.php'; ?></span></h1>
	<div class="inner">
		<nav class="sub-menu">
			<li><a data-target="content-mask-pages" href="#" class="active"><span>List View</span></a></li>
			<li><a data-target="content-mask-options" href="#"><span>Options</span></a></li>
			<li><a data-target="content-mask-advanced" href="#"><span>Advanced</span></a></li>
		</nav>
		
		<!-- List of Content Masked Pages/Posts/CPTs -->
		<div id="content-mask-pages" class="content-mask-panel active <?php if( $tracking_checked == true ){ echo 'visitor-tracking'; } ?> ">
			<table>
				<?php
					if( $query->have_posts() ){
						$count = 0;
						while( $query->have_posts() ){ $count++;
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
					} else {
						echo "<tr><td><div>No Content Masks Found</div></td></tr>";
					}
				?>
			</table>
			<?php if( $count == $load ) echo '<button id="load-more-masks">Load More Content Masks</button>'; ?>
		</div>
		
		<!-- Content Mask Options and Settings -->
		<div id="content-mask-options" class="content-mask-panel">
			<div class="grid" columns="4">
				<?php
					$check_options = array(
						array(
							'name'  => 'tracking',
							'label' => 'Visitor Tracking',
							'help'  => 'Vistor Tracking will give you a rough estimate of how many views your Content Masked Pages are getting.'
						),
						array(
							'name'  => 'user_agent_header',
							'label' => 'HTTP Headers',
							'help'  => 'If you\'re getting errors, especially \'403 Forbidden\' errors, when using the Download Method, try enabling this option.'
						),
					);

					foreach( $check_options as $option ){
						$option = (object) $option; ?>
						<div class="content-mask-option">
							<label class="content-mask-checkbox" for="content_mask_<?php echo $option->name; ?>" data-attr="<?php echo ${$option->name.'_enabled'}; ?>">
								<span class="display-name" aria-label="Enable <?php echo $option->label; ?>"></span>
								<input type="checkbox" name="content_mask_<?php echo $option->name; ?>" id="content_mask_<?php echo $option->name; ?>" <?php echo ${$option->name.'_checked'}; ?> />
								<span class="content-mask-check">
									<span class="content-mask-check_ajax">
										<?php echo $this->display_svg( 'checkmark', 'icon' ); ?>
									</span>
								</span>
							</label>
							<span class="content-mask-option_label"><?php echo $option->label; ?>: <strong class="content-mask-value"><?php echo ucwords( ${$option->name.'_enabled'} ); ?></strong> <span class="content-mask-hover-help" data-help="<?php echo $option->help; ?>">?</span></span>
						</div>
					<?php }
				?>
			</div>
		</div>

		<!-- Content Masked Advanced Features and Scripts -->
		<div id="content-mask-advanced" class="content-mask-panel">
			<?php
				$method_types = array( 'download', 'iframe' );

				$code_types = array(
					array(
						'name'   => 'scripts',
						'editor' => 'text/html',
						'mode'   => 'htmlmixed',
						'notes'  => '(Include <pre style="display:inline;">&lt;script&gt;</pre> tags)',
					),
					array(
						'name'   => 'styles',
						'editor' => 'text/css',
						'mode'   => 'css',
						'notes'  => '(Do NOT include <pre style="display:inline;">&lt;style&gt;</pre> tags)',
					)
				);

				$count = 0;

				foreach( $method_types as $method ){
					foreach( $code_types as $type ){
						$type = (object) $type; $count++;

						if( $count % 2 != 0 ) echo '<div class="grid" columns="2" gap>'; ?>
							<div class="option">
								<div class="code-edit-wrapper">
									<label class="content-mask-textarea" for="content_mask_custom_<?php echo $type->name.'_'.$method; ?>">
										<strong class="display-name">Custom <?php echo ucwords( $type->name ); ?> (<?php echo ucwords( $method ); ?> Method)</strong> <span><?php echo $type->notes; ?></span><br>
										<textarea id="content_mask_custom_<?php echo $type->name.'_'.$method; ?>" rows="4" data-type="<?php echo $type->editor; ?>" data-mode="<?php echo $type->mode; ?>" name="content_mask_custom_<?php echo $type->name.'_'.$method; ?>" class="widefat textarea code-editor"><?php echo wp_unslash( esc_textarea( get_option( 'content_mask_custom_'.$type->name.'_'.$method ) ) ); ?></textarea>
										<button id="save-scripts" data-target="content_mask_custom_<?php echo $type->name.'_'.$method; ?>" data-editor="editor_<?php echo $count; ?>" class="wp-core-ui button button-primary">Save <span style="display: none;"><?php echo ucwords( $method ).' '. ucwords( $type->name ); ?></span></button>
									</label>
								</div>
								<div class="content-mask-option">
									<label class="content-mask-checkbox" for="content_mask_allow_<?php echo $type->name.'_'.$method; ?>" data-attr="<?php echo ${'allow_'.$type->name.'_'.$method.'_enabled'}; ?>">
										<span class="display-name" aria-label="Custom <?php echo ucwords( $type->name ); ?> for <?php echo ucwords( $method ); ?> Method"></span>
										<input type="checkbox" name="content_mask_allow_<?php echo $type->name.'_'.$method; ?>" id="content_mask_allow_<?php echo $type->name.'_'.$method; ?>" <?php echo ${'allow_'.$type->name.'_'.$method.'_checked'}; ?> />
										<span class="content-mask-check">
											<span class="content-mask-check_ajax">
												<?php echo $this->display_svg( 'checkmark', 'icon' ); ?>
											</span>
										</span>
									</label>
									<span class="content-mask-option_label">Custom <?php echo ucwords( $type->name ); ?> for <?php echo ucwords( $method ); ?> Method: <strong class="content-mask-value"><?php echo ucwords( ${'allow_'.$type->name.'_'.$method.'_enabled'} ); ?></strong> <span class="content-mask-hover-help" data-help="Add custom <?php echo $type->name; ?> to pages masked with the <?php echo ucwords( $method ); ?> method. Useful if you would like to add Analytics. Note: These <?php echo ucwords( $type->name ); ?> will apply to all pages masked with the <?php echo ucwords( $method ); ?> method.">?</span></span>
								</div>
							</div>
						<?php if( $count % 2 == 0 ) echo '</div>';
					}
				}
			?>
		</div>
	</div>
</div>
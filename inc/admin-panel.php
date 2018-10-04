<?php
	$args = [
		'post_status' => ['publish', 'draft', 'pending', 'private'],
		'post_type'   => get_post_types( '', 'names' ),
		'meta_query'  => [[
			'key'	  	=> 'content_mask_url',
			'value'   	=> '',
			'compare' 	=> '!=',
		]],
		'posts_per_page' => 20
	];

	if( ! current_user_can( 'edit_others_posts' ) ) $args['perm'] = 'editable';

	$query = new WP_Query( $args );

	$columns = [
		'Method',
		'Title',
		'Mask URL',
		'Cache Expires',
		'Post Type',
		'Views',
	];

	$toggle_options = array(
		'tracking',
		'user_agent_header',
		'allow_scripts_download',
		'allow_styles_download',
		'allow_scripts_iframe',
		'allow_styles_iframe',
	);

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
<div class="wrap">
	<h2>Content Mask <span class="alignright"><?php require_once dirname(__FILE__).'/admin-buttons.php'; ?></span></h2>
	
	<h3><span>List of Masked Pages <button class="collapse-handle"><?php echo $this->display_svg( 'arrow-down', 'icon' ); ?></button></span></h3>
	<div class="collapse-container">
		<div id="content-mask-list" class="content-mask-admin-panel tracking-<?php echo $tracking_enabled; ?>">
			<div class="content-mask-table-header"></div>
			<div class="content-mask-table-body">
				<table cellspacing="0">
					<thead>
						<tr>
							<?php foreach( $columns as $column ){
								$help = ( $column == 'Views' ) ? '<span class="content-mask-hover-help" data-help="Total Views are views from everyone. Non-User Views are from users that are not currently logged in. Unique Views are how many unique visitors have viewed that page.">?</span>' : '';
								echo '<th class="'. sanitize_title( $column ) .'"><div>'. $column . $help. '</div></th>';
							} ?>
						</tr>
						<tr class="invisible">
							<?php foreach( $columns as $column ){
								echo '<th class="th-'. sanitize_title( $column ) .'">'. $column .'</th>';
							} ?>
						</tr>
					</thead>
					<tbody>
						<?php
							if( $query->have_posts() ){
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
												echo $column == 'post-type' ? '' : '<div>';
													$this->content_mask_display_column( $column, $post_id, $post_fields );
												echo $column == 'post-type' ? '' : '</div>';
											echo '</td>';
										}
									echo '</tr>';
								}
							} else {
								echo "<tr><td><div>No Content Masks Found</div></td></tr>";
							}
						?>
					</tbody>
				</table>
			</div>
		</div>
	</div>
	
	<h3><span>Advanced Options <button class="collapse-handle"><?php echo $this->display_svg( 'arrow-down', 'icon' ); ?></button></span></h3>
	<div class="collapse-container">
		<div id="content-mask-options" class="content-mask-admin-panel">
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
			<div id="content-mask-code">
				<div class="content-mask-admin-panel">
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

								if( $count % 2 != 0 ) echo '<div class="grid" columns="2">'; ?>
									<div class="content-mask-option">
										<div class="code-edit-wrapper">
											<label class="content-mask-textarea" for="content_mask_custom_<?php echo $type->name.'_'.$method; ?>">
												<strong class="display-name">Custom <?php echo ucwords( $type->name ); ?> (<?php echo ucwords( $method ); ?> Method)</strong> <span><?php echo $type->notes; ?></span><br>
												<textarea id="content_mask_custom_<?php echo $type->name.'_'.$method; ?>" rows="4" data-type="<?php echo $type->editor; ?>" data-mode="<?php echo $type->mode; ?>" name="content_mask_custom_<?php echo $type->name.'_'.$method; ?>" class="widefat textarea code-editor"><?php echo wp_unslash( esc_textarea( get_option( 'content_mask_custom_'.$type->name.'_'.$method ) ) ); ?></textarea>
												<button id="save-scripts" data-target="content_mask_custom_<?php echo $type->name.'_'.$method; ?>" data-editor="editor_<?php echo $count; ?>" class="wp-core-ui button button-primary">Save <?php echo ucwords( $method ).' '. ucwords( $type->name ); ?></button>
											</label>
										</div>
										<div>
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
	</div>
</div>
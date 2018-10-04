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
				<div class="content-mask-option">
					<label class="content-mask-checkbox" for="content_mask_tracking" data-attr="<?php echo $tracking_enabled; ?>">
						<span class="display-name" aria-label="Enable Content Mask Tracking"></span>
						<input type="checkbox" name="content_mask_tracking" id="content_mask_tracking" <?php echo $tracking_checked; ?> />
						<span class="content-mask-check">
							<span class="content-mask-check_ajax">
								<?php echo $this->display_svg( 'checkmark', 'icon' ); ?>
							</span>
						</span>
					</label>
					<span class="content-mask-option_label">Visitor Tracking: <strong class="content-mask-value"><?php echo ucwords( $tracking_enabled ); ?></strong> <span class="content-mask-hover-help" data-help="Vistor Tracking will give you a rough estimate of how many views your Content Masked Pages are getting.">?</span></span>
				</div>
				<div class="content-mask-option">
					<label class="content-mask-checkbox" for="content_mask_user_agent_header" data-attr="<?php echo $user_agent_header_enabled; ?>">
						<span class="display-name" aria-label="Enable HTTP Headers for Download Method"></span>
						<input type="checkbox" name="content_mask_user_agent_header" id="content_mask_user_agent_header" <?php echo $user_agent_header_checked; ?> />
						<span class="content-mask-check">
							<span class="content-mask-check_ajax">
								<?php echo $this->display_svg( 'checkmark', 'icon' ); ?>
							</span>
						</span>
					</label>
					<span class="content-mask-option_label">HTTP Headers: <strong class="content-mask-value"><?php echo ucwords( $user_agent_header_enabled ); ?></strong> <span class="content-mask-hover-help" data-help="If you're getting errors, especially '403 Forbidden' errors, when using the Download Method, try enabling this option.">?</span></span>
				</div>
			</div>
			<div id="content-mask-code">
				<div class="content-mask-admin-panel">
					<div class="grid" columns="2">
						<div class="content-mask-option">
							<div class="code-edit-wrapper">
								<label class="content-mask-textarea" for="content_mask_custom_scripts_download">
									<strong class="display-name">Custom Scripts (Download Method)</strong> <span>(Include <pre style="display:inline;">&lt;script&gt;</pre> tags)</span><br>
									<textarea id="content_mask_custom_scripts_download" rows="4" data-type="text/html" data-mode="htmlmixed" name="content_mask_custom_scripts_download" class="widefat textarea code-editor"><?php echo wp_unslash( esc_textarea( get_option( 'content_mask_custom_scripts_download' ) ) ); ?></textarea>
									<button id="save-scripts" data-target="content_mask_custom_scripts_download" data-editor="editor_1" class="wp-core-ui button button-primary">Save Download Scripts</button>
								</label>
							</div>
							<div>
								<label class="content-mask-checkbox" for="content_mask_allow_scripts_download" data-attr="<?php echo $allow_scripts_download_enabled; ?>">
									<span class="display-name" aria-label="Custom Scripts for Download Method"></span>
									<input type="checkbox" name="content_mask_allow_scripts_download" id="content_mask_allow_scripts_download" <?php echo $allow_scripts_download_checked; ?> />
									<span class="content-mask-check">
										<span class="content-mask-check_ajax">
											<?php echo $this->display_svg( 'checkmark', 'icon' ); ?>
										</span>
									</span>
								</label>
								<span class="content-mask-option_label">Custom Scripts for Download Method: <strong class="content-mask-value"><?php echo ucwords( $allow_scripts_download_enabled ); ?></strong> <span class="content-mask-hover-help" data-help="Add custom scripts to pages masked with the Download method. Useful if you would like to add Analytics. Note: These scripts will apply to all pages masked with the Download method.">?</span></span>
							</div>
						</div>
						<div class="content-mask-option">
							<div class="code-edit-wrapper">
								<label class="content-mask-textarea" for="content_mask_custom_styles_download">
									<strong class="display-name">Custom CSS (Download Method)</strong> <span>(Don't include <pre style="display:inline;">&lt;style&gt;</pre> tags)</span><br>
									<textarea id="content_mask_custom_styles_download" rows="4" data-type="text/css" data-mode="css" name="content_mask_custom_styles_download" class="widefat textarea code-editor"><?php echo wp_unslash( esc_textarea( get_option( 'content_mask_custom_styles_download' ) ) ); ?></textarea>
									<button id="save-styles" data-target="content_mask_custom_styles_download" data-editor="editor_2" class="wp-core-ui button button-primary">Save Download Styles</button>
								</label>
							</div>
							<div>
								<label class="content-mask-checkbox" for="content_mask_allow_styles_download" data-attr="<?php echo $allow_styles_download_enabled; ?>">
									<span class="display-name" aria-label="Custom Styles for Download Method"></span>
									<input type="checkbox" name="content_mask_allow_styles_download" id="content_mask_allow_styles_download" <?php echo $allow_styles_download_checked; ?> />
									<span class="content-mask-check">
										<span class="content-mask-check_ajax">
											<?php echo $this->display_svg( 'checkmark', 'icon' ); ?>
										</span>
									</span>
								</label>
								<span class="content-mask-option_label">Custom CSS for Download Method: <strong class="content-mask-value"><?php echo ucwords( $allow_styles_download_enabled ); ?></strong> <span class="content-mask-hover-help" data-help="Add custom styles to pages masked with the Download method. Note: These styles will apply to all pages masked with the Download Method.">?</span></span>
							</div>
						</div>
					</div>
				</div>
				<div class="content-mask-admin-panel">
					<div class="grid" columns="2">
						<div class="content-mask-option">
							<div class="code-edit-wrapper">
								<label class="content-mask-textarea" for="content_mask_custom_scripts_iframe">
									<strong class="display-name">Custom Scripts (Iframe Method)</strong> <span>(Include <pre style="display:inline;">&lt;script&gt;</pre> tags)</span><br>
									<textarea id="content_mask_custom_scripts_iframe" rows="4" data-type="text/html" data-mode="htmlmixed" name="content_mask_custom_scripts_iframe" class="widefat textarea code-editor"><?php echo wp_unslash( esc_textarea( get_option( 'content_mask_custom_scripts_iframe' ) ) ); ?></textarea>
									<button id="save-scripts" data-target=" " data-editor="editor_3" class="wp-core-ui button button-primary">Save Iframe Scripts</button>
								</label>
							</div>
							<div>
								<label class="content-mask-checkbox" for="content_mask_allow_scripts_iframe" data-attr="<?php echo $allow_scripts_iframe_enabled; ?>">
									<span class="display-name" aria-label="Custom Scripts for Iframe Method"></span>
									<input type="checkbox" name="content_mask_allow_scripts_iframe" id="content_mask_allow_scripts_iframe" <?php echo $allow_scripts_iframe_checked; ?> />
									<span class="content-mask-check">
										<span class="content-mask-check_ajax">
											<?php echo $this->display_svg( 'checkmark', 'icon' ); ?>
										</span>
									</span>
								</label>
								<span class="content-mask-option_label">Custom Scripts for Iframe Method: <strong class="content-mask-value"><?php echo ucwords( $allow_scripts_iframe_enabled ); ?></strong> <span class="content-mask-hover-help" data-help="Add custom scripts to pages masked with Iframe method. Useful if you would like to add Analytics. Note: These scripts will apply to all pages masked with the Download method.">?</span></span>
							</div>
						</div>
						<div class="content-mask-option">
							<div class="code-edit-wrapper">
								<label class="content-mask-textarea" for="content_mask_custom_styles_iframe">
									<strong class="display-name">Custom CSS (Iframe Method)</strong> <span>(Don't include <pre style="display:inline;">&lt;style&gt;</pre> tags)</span><br>
									<textarea id="content_mask_custom_styles_iframe" rows="4" data-type="text/css" data-mode="css" name="content_mask_custom_styles_iframe" class="widefat textarea code-editor"><?php echo wp_unslash( esc_textarea( get_option( 'content_mask_custom_styles_iframe' ) ) ); ?></textarea>
									<button id="save-styles" data-target="content_mask_custom_styles_iframe" data-editor="editor_4" class="wp-core-ui button button-primary">Save Iframe Styles</button>
								</label>
							</div>
							<div>
								<label class="content-mask-checkbox" for="content_mask_allow_styles_iframe" data-attr="<?php echo $allow_styles_iframe_enabled; ?>">
									<span class="display-name" aria-label="Custom Styles for Iframe Method"></span>
									<input type="checkbox" name="content_mask_allow_styles_iframe" id="content_mask_allow_styles_iframe" <?php echo $allow_styles_iframe_checked; ?> />
									<span class="content-mask-check">
										<span class="content-mask-check_ajax">
											<?php echo $this->display_svg( 'checkmark', 'icon' ); ?>
										</span>
									</span>
								</label>
								<span class="content-mask-option_label">Custom CSS for Iframe Method: <strong class="content-mask-value"><?php echo ucwords( $allow_styles_iframe_enabled ); ?></strong> <span class="content-mask-hover-help" data-help="Add custom styles to pages masked with the Iframe method. Note: These styles will apply to all pages masked with the Download Method. Note, you can't style content INSIDE the iframe, just the iframe itself.">?</span></span>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
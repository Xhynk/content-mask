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

	$options = array(
		'tracking',
		'user_agent_header'
	);

	foreach( $options as $option ){
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
	<div id="content-mask-options" class="content-mask-admin-panel">
		<h3>Advanced Options</h3>
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
			<span class="content-mask-option_label">Content Mask Visitor Tracking is <strong class="content-mask-value"><?php echo ucwords( $tracking_enabled ); ?></strong> <span class="content-mask-hover-help" data-help="Vistor Tracking will give you a rough estimate of how many views your Content Masked Pages are getting.">?</span></span>
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
			<span class="content-mask-option_label">HTTP Headers for Download Method are <strong class="content-mask-value"><?php echo ucwords( $user_agent_header_enabled ); ?></strong> <span class="content-mask-hover-help" data-help="If you're getting errors, especially '403 Forbidden' errors, when using the Download Method, try enabling this option.">?</span></span>
		</div>
	</div>
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
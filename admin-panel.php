<?php
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

	$columns = [
		'Method',
		'Title',
		'Mask URL',
		'Cache Expires',
		'Post Type',
		'Views',
	];

	function content_mask_display_column( $column, $post_id, $post_fields ){
		extract( $post_fields );

		switch( $column ){
			case "method":
				echo ContentMask::display_svg( $content_mask_method, 'icon', "title='$content_mask_method'" );
				break;

			case "title":
				echo '<a target="_blank" href="'. get_permalink( $post_id ) .'"><strong>'. get_the_title( $post_id ) .'</strong></a><span class="row-actions"> - <a href="'. get_edit_post_link( $post_id ) .'">Edit</a></span>';
				break;

			case "mask-url":
				echo "<a href='$content_mask_url' target='_blank'>$content_mask_url</a>";
				break;

			case "cache-expires":
				if( $content_mask_method === 'download' ){
					$transient                = 'content_mask-'. strtolower( preg_replace( "/[^a-z0-9]/", '', $content_mask_url ) );
					$data_expiration          = $content_mask_transient_expiration ? ContentMask::time_to_seconds( $content_mask_transient_expiration ) : ContentMask::time_to_seconds( '4 hour' );
					$data_expiration_readable = $content_mask_transient_expiration ? $content_mask_transient_expiration : '4 hours';
					
					echo '<span class="transient-expiration">'. ContentMask::get_transient_expiration( $transient ) .'</span>';
					echo '<span class="row-actions"> - <a href="#" data-expiration-readable="'. $data_expiration_readable .'" data-expiration="'. $data_expiration .'" data-transient="'. $transient .'">Refresh</a></span>';
				} else {
					echo '<span style="opacity:.4;">N/A</span>';
				}
				break;

			case "post-type":
				echo '<div data-post-status="'. get_post_status( $post_id ) .'">'. get_post_type( $post_id ) .'</div>';
				break;

			case "views":
				if( !$content_mask_views || $content_mask_views == '' ){
					echo 'No Views Yet';
				} else {
					$total  = ( $content_mask_views['total'] )  ? $content_mask_views['total']  : 0;
					$anon   = ( $content_mask_views['anon'] )   ? $content_mask_views['anon']   : 0;
					$unique = ( $content_mask_views['unique'] ) ? count( $content_mask_views['unique'] ) : 0;
					
					echo "Views: <strong>$total</strong> | Non-User: <strong>$anon</strong> | Unique: <strong>$unique</strong>";
				}
				break;
		}
	}

	if( filter_var( get_option( 'content_mask_tracking' ), FILTER_VALIDATE_BOOLEAN ) ){
		$tracking_checked = 'checked="checked"';
		$tracking_enabled = 'enabled';
	} else {
		$tracking_checked = '';
		$tracking_enabled = 'disabled';
	}
?>
<div class="wrap">
	<h2><?= $this::$label; ?></h2>
	<div id="content-mask-options" class="content-mask-admin-panel">
		<h3>Options</h3>
		<div class="cm_option">
			<label class="cm_checkbox" for="content_mask_tracking" data-attr="<?= $tracking_enabled; ?>">
				<span aria-label="Enable <?= $this::$label; ?> Tracking"></span>
				<input type="checkbox" name="content_mask_tracking" id="content_mask_tracking" <?= $tracking_checked; ?> />
				<span class="cm_check">
					<span class="cm_check_ajax">
						<?= $this->display_svg( 'checkmark', 'icon' ); ?>
					</span>
				</span>
			</label>
			<span class="cm_option_label">Content Mask Visitor Tracking is <strong class="cm_value"><?= ucwords( $tracking_enabled ); ?></strong></span>
		</div>
	</div>
	<div id="content-mask-list" class="content-mask-admin-panel tracking-<?= $tracking_enabled; ?>">
		<div class="content-mask-admin-table-header"></div>
		<div class="content-mask-admin-table-body">
			<table cellspacing="0">
				<thead>
					<tr>
						<?php foreach( $columns as $column ){
							$help = ( $column == 'Views' ) ? '<span class="cm-hover-help" data-help="Total Views are views from everyone. Non-User Views are from users that are not currently logged in. Unique Views are how many unique visitors have viewed that page.">?</span>' : '';
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
												content_mask_display_column( $column, $post_id, $post_fields );
											echo $column == 'post-type' ? '' : '</div>';
										echo '</td>';
									}
								echo '</tr>';
							}
						} else {
							echo "<tr><td><div>No {$this::$label}s Found</div></td></tr>";
						}
					?>
				</tbody>
			</table>
		</div>
	</div>
</div>
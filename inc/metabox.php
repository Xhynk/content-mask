<?php extract( $this->get_post_fields( get_the_ID() ) ); ?>
<div id="content-mask-settings">
	<?php wp_nonce_field( 'save_post', 'content_mask_meta_nonce' ); ?>
	<div class="content-mask-enable-container">
		<label class="content-mask-checkbox" for="content_mask_enable">
			<span aria-label="Enable Content Mask"></span>
			<input type="checkbox" name="content_mask_enable" id="content_mask_enable" <?php if( filter_var( $this->issetor( $content_mask_enable ), FILTER_VALIDATE_BOOLEAN ) ){ echo 'checked="checked"'; } ?> />
			<span class="content-mask-check">
				<?php $this->echo_svg( 'checkmark', 'icon' ); ?>
			</span>
		</label>
	</div>
	<div class="content-mask-method-container">
		<div class="content-mask-select">
			<input type="radio" name="content_mask_method" class="content-mask-select-toggle">
			<?php $this->echo_svg( 'arrow-down', 'toggle' ); ?>
			<?php $this->echo_svg( 'arrow-up', 'toggle' ); ?>
			<span class="placeholder">Choose a Method...</span>
			<?php foreach( ['download', 'iframe', 'redirect'] as $method ){ ?>
				<label class="option">
					<input type="radio" <?php echo $this->issetor( $content_mask_method ) == $method ? 'checked="checked"' : '' ?> value="<?php echo $method; ?>" name="content_mask_method">
					<span class="title"><?php $this->echo_svg( $method ) . ucwords( $method ); ?></span>
				</label>
			<?php } ?>
		</div>
	</div>
	<div class="content-mask-url-container">
		<div class="content-mask-text hide-overflow">
			<span aria-label="Content Mask URL"></span>
			<input type="text" class="widefat" name="content_mask_url" id="content_mask_url" placeholder="Content Mask URL" value="<?php echo esc_url( $this->issetor( $content_mask_url ) ); ?>" />
		</div>
	</div>
	<div style="clear: both; height: 24px;"></div>
	<div class="content-mask-expiration-div">
		<h2 class="content-mask-expiration-header"><strong>Cache Expiration:</strong><br /><sup>(Download Method Only)</sup></h2>
		<div class="content-mask-expiration-container">
			<div class="content-mask-select">
				<?php $test = $this->time_to_seconds( $this->issetor( $content_mask_transient_expiration ) ); ?>
				<input type="radio" name="content_mask_transient_expiration" class="content-mask-select-toggle">
				<?php $this->echo_svg( 'arrow-down', 'toggle' ); ?>
				<?php $this->echo_svg( 'arrow-up', 'toggle' ); ?>
				<span class="placeholder">Cache Expiration:</span>
				<label class="option">
					<input type="radio" <?php echo $content_mask_transient_expiration == 'never' ? 'checked="checked"' : '' ?> value="never" name="content_mask_transient_expiration">
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
								<input type="radio" <?php echo $checked; ?> value="<?php echo $val; ?>" name="content_mask_transient_expiration">
								<span class="title"><?php echo "$val$s"; ?></span>
							</label>
						<?php }
					}
				?>
			</div>
		</div>
	</div>
	<div style="clear: both; height: 12px;"></div>
</div>
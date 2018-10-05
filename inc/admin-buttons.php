<?php
	$admin_buttons = array(
		array(
			'classes'    => 'button-secondary alignright svg-icon-button',
			'attr'       => 'target="_blank"',
			'href'       => 'https://www.paypal.me/xhynk/',
			'text'       => $this->display_svg( 'heart', 'icon' ).' Donate',
			'echo'       => true,
			'avoid_keys' => ['irdr','trey','river','fahn']
		),
		array(
			'classes'    => 'button-secondary alignright svg-icon-button',
			'attr'       => 'target="_blank"',
			'href'       => 'https://wordpress.org/support/plugin/content-mask',
			'text'       => $this->display_svg( 'help-circle', 'icon' ).' Help Request',
			'echo'       => true,
			'avoid_keys' => []
		),
		array(
			'classes'    => 'button-secondary alignright svg-icon-button',
			'attr'       => 'target="_blank"',
			'href'       => 'https://xhynk.com/content-mask/',
			'text'       => $this->display_svg( 'bookmark', 'icon' ).' Documentation',
			'echo'       => true,
			'avoid_keys' => []
		),
		array(
			'classes'    => 'button-secondary alignright svg-icon-button',
			'attr'       => 'target="_blank"',
			'href'       => 'mailto:info@xhynk.com?subject="Content Mask Feature Request"',
			'text'       => $this->display_svg( 'share', 'icon' ).' Feature Request',
			'echo'       => true,
			'avoid_keys' => []
		),
		array(
			'classes'    => 'button-secondary alignright svg-icon-button',
			'attr'       => 'target="_blank"',
			'href'       => 'https://xhynk.com/#footer',
			'text'       => $this->display_svg( 'email', 'icon' ).' Contact Me',
			'echo'       => true,
			'avoid_keys' => []
		)
	);

	ob_start();
	
	foreach( $admin_buttons as $button ){
		$this->show_button( $button['classes'], $button['attr'], $button['href'], $button['text'], $button['echo'], $button['avoid_keys'] );
	}

	echo ob_get_clean();
?>
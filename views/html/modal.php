<?php
if(in_array(use_geolocated_user_country(), ['AT', 'DE', 'ES', 'FR', 'NL'])){
	$icon = 'icons-full-border-'.strtolower(use_geolocated_user_country()).'.svg';
}
else{
	$icon = 'icons-full-border.svg';
}
?>
<div class="volt-modal"
	 data-volt-icon="<?php echo esc_url( home_url( '/wp-content/plugins/' . esc_html(VOLTIO_PLUGIN_DIR) . '/views/img/volt-info.svg' ) ); ?>"
	 data-volt-logo="<?php echo esc_url( home_url( '/wp-content/plugins/' . esc_html(VOLTIO_PLUGIN_DIR) . '/views/img/volt-logo.svg' ) ); ?>"
	 data-volt-icon-logos="<?php echo esc_url( home_url( '/wp-content/plugins/' . esc_html(VOLTIO_PLUGIN_DIR) . '/views/img/' . $icon ) ); ?>">
	<div class="volt-content-modal">
		<div class="volt-left">
			<p class="volt-title"><?php _e( 'How Volt works', 'voltio' ); ?></p>
			<p class="volt-subtitle"><?php _e( 'Check out in three easy steps:', 'voltio' ); ?></p>
			<div class="volt-mobile-photo">
				<img src="<?php echo esc_url( home_url( '/wp-content/plugins/' . esc_html(VOLTIO_PLUGIN_DIR) . '/views/img/volt-modal-mobile.png' ) ); ?>"/>
			</div>
			<div class="volt-steps">
				<div class="volt-step">
					<div class="volt-number">1</div>
					<div class="volt-content">
						<p><?php _e( 'Select your bank (99% of banks supported)', 'voltio' ); ?></p>
						<p><?php _e( 'Pay from your bank. No card needed.', 'voltio' ); ?></p>
					</div>
				</div>
				<div class="volt-step">
					<div class="volt-number">2</div>
					<div class="volt-content">
						<p><?php _e( 'Log into your account', 'voltio' ); ?></p>
						<p><?php _e( 'Your bank details are never shared.', 'voltio' ); ?></p>
					</div>
				</div>
				<div class="volt-step">
					<div class="volt-number">3</div>
					<div class="volt-content">
						<p><?php _e( 'Approve the payment', 'voltio' ); ?></p>
						<p><?php _e( 'That’s it. Faster and more secure.', 'voltio' ); ?></p>
					</div>
				</div>
			</div>
			<a href="#" class="volt-close"><?php _e( 'Continue', 'voltio' ); ?></a>
		</div>
		<div class="volt-right">
			<img src="<?php echo esc_url( home_url( '/wp-content/plugins/' . esc_html(VOLTIO_PLUGIN_DIR) . '/views/img/volt-modal.png' ) ); ?>"/>
		</div>
	</div>
</div>

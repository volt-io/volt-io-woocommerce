<?php

class VoltioSettings {

	private $voltio_settings_options;
	private $fields;

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'voltio_settings_add_plugin_page' ) );
	}


	public function voltio_settings_add_plugin_page() {
		add_submenu_page(
			'woocommerce',
			__( 'Volt settings', 'voltio' ), // page_title
			__( 'Volt settings', 'voltio' ), // menu_title
			'manage_options', // capability
			'voltio-settings', // menu_slug
			array( $this, 'voltio_settings_create_admin_page' ), // function
			100
		);
	}


	public function voltio_settings_create_admin_page() {
		$this->voltio_settings_options = get_option( 'voltio_settings_option_name' ); ?>

		<div class="wrap">
			<h2><?php echo esc_html( __( 'Volt settings', 'voltio' ) ); ?></h2>
			<p></p>
			<?php settings_errors(); ?>

			<form method="post" action="options.php">
				<?php
				settings_fields( 'voltio_settings_option_group' );
				do_settings_sections( 'voltio-settings-admin' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}


	public function global_callback( $args ) {
		$id    = $args['id'];
		$value = isset( $this->voltio_settings_options[ $id ] ) ? esc_attr( $this->voltio_settings_options[ $id ] ) : '';
		printf(
			'<input type="text" class="regular-text" value="%s" name="voltio_settings_option_name[%s]" id="%s" />',
			esc_html( $value ),
			esc_html( $id ),
			esc_html( $id )
		);
	}


	public function before_payment_statuses() {
		$statuses  = wc_get_order_statuses();
		$available = array();
		foreach ( $statuses as $key => $value ) {
			if ( in_array( $key, array( 'wc-pending', 'wc-on-hold' ) ) ) {
				$available[ str_replace( 'wc-', '', $key ) ] = $value;
			}
		}
		ksort( $available );
		return $available;
	}
}

if ( is_admin() ) {
	new VoltioSettings();
}

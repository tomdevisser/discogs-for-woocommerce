<?php
/**
 * Settings page registration.
 *
 * @package Discogs_For_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', 'dfw_register_settings_page' );
add_action( 'admin_init', 'dfw_register_settings' );

/**
 * Register the settings page under WooCommerce.
 */
function dfw_register_settings_page() {
	add_submenu_page(
		'woocommerce',
		__( 'Discogs', 'dfw' ),
		__( 'Discogs', 'dfw' ),
		'manage_woocommerce',
		'dfw-settings',
		'dfw_render_settings_page'
	);
}

/**
 * Register settings, sections, and fields.
 */
function dfw_register_settings() {
	register_setting(
		'dfw_settings',
		'dfw_settings',
		array(
			'sanitize_callback' => 'dfw_sanitize_settings',
		)
	);

	add_settings_section(
		'dfw_api_credentials',
		__( 'Credentials', 'dfw' ),
		'dfw_render_credentials_description',
		'dfw-settings'
	);

	add_settings_field(
		'consumer_key',
		__( 'Consumer Key', 'dfw' ),
		'dfw_render_text_field',
		'dfw-settings',
		'dfw_api_credentials',
		array( 'field' => 'consumer_key' )
	);

	add_settings_field(
		'consumer_secret',
		__( 'Consumer Secret', 'dfw' ),
		'dfw_render_text_field',
		'dfw-settings',
		'dfw_api_credentials',
		array(
			'field' => 'consumer_secret',
			'type'  => 'password',
		)
	);

	add_settings_section(
		'dfw_templates',
		__( 'Templates', 'dfw' ),
		'dfw_render_templates_description',
		'dfw-settings'
	);

	add_settings_field(
		'description_template',
		__( 'Product Description', 'dfw' ),
		'dfw_render_textarea_field',
		'dfw-settings',
		'dfw_templates',
		array( 'field' => 'description_template' )
	);
}

/**
 * Render the credentials section description.
 */
function dfw_render_credentials_description() {
	printf(
		'<p>%s <a href="https://www.discogs.com/settings/developers" target="_blank">%s</a>.</p>',
		esc_html__( 'You can create your API credentials at', 'dfw' ),
		esc_html__( 'Discogs Developer Settings', 'dfw' )
	);
}

/**
 * Sanitize all settings.
 *
 * @param array $input Raw input.
 * @return array Sanitized input.
 */
function dfw_sanitize_settings( $input ) {
	$sanitized = array();

	if ( isset( $input['consumer_key'] ) ) {
		$sanitized['consumer_key'] = sanitize_text_field( $input['consumer_key'] );
	}

	if ( isset( $input['consumer_secret'] ) ) {
		$sanitized['consumer_secret'] = sanitize_text_field( $input['consumer_secret'] );
	}

	if ( isset( $input['description_template'] ) ) {
		$sanitized['description_template'] = wp_kses_post( $input['description_template'] );
	}

	return $sanitized;
}

/**
 * Render a text input field.
 *
 * @param array $args Field arguments.
 */
function dfw_render_text_field( $args ) {
	$options = get_option( 'dfw_settings', array() );
	$value   = isset( $options[ $args['field'] ] ) ? $options[ $args['field'] ] : '';

	$type = isset( $args['type'] ) ? $args['type'] : 'text';

	printf(
		'<input type="%s" name="dfw_settings[%s]" value="%s" class="regular-text" />',
		esc_attr( $type ),
		esc_attr( $args['field'] ),
		esc_attr( $value )
	);
}

/**
 * Render the templates section description.
 */
function dfw_render_templates_description() {
	$placeholders = array(
		'[title]',
		'[artist]',
		'[year]',
		'[country]',
		'[format]',
		'[genre]',
		'[tracklist]',
	);

	printf(
		'<p>%s</p><p><code>%s</code></p>',
		esc_html__( 'Use placeholders to build a product description template. Available placeholders:', 'dfw' ),
		implode( '</code> <code>', array_map( 'esc_html', $placeholders ) )
	);
}

/**
 * Render a textarea field.
 *
 * @param array $args Field arguments.
 */
function dfw_render_textarea_field( $args ) {
	$options = get_option( 'dfw_settings', array() );
	$value   = isset( $options[ $args['field'] ] ) ? $options[ $args['field'] ] : '';

	printf(
		'<textarea name="dfw_settings[%s]" rows="10" class="large-text">%s</textarea>',
		esc_attr( $args['field'] ),
		esc_textarea( $value )
	);
}

/**
 * Render the settings page.
 */
function dfw_render_settings_page() {
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Discogs for WooCommerce', 'dfw' ); ?></h1>
		<form method="post" action="options.php">
			<?php
			settings_fields( 'dfw_settings' );
			do_settings_sections( 'dfw-settings' );
			submit_button();
			?>
		</form>
	</div>
	<?php
}

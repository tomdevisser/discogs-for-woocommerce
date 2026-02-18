<?php
/**
 * WooCommerce attribute registration.
 *
 * @package Discogs_For_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

register_activation_hook( DFW_PLUGIN_FILE, 'dfw_create_attributes' );

/**
 * Attributes managed by this plugin.
 *
 * @return array[] List of attribute definitions.
 */
function dfw_get_managed_attributes() {
	return array(
		array(
			'slug' => 'dfw_artist',
			'name' => __( 'Artist', 'dfw' ),
		),
		array(
			'slug' => 'dfw_country',
			'name' => __( 'Country', 'dfw' ),
		),
		array(
			'slug' => 'dfw_year',
			'name' => __( 'Year', 'dfw' ),
		),
	);
}

/**
 * Create product attributes on plugin activation.
 */
function dfw_create_attributes() {
	foreach ( dfw_get_managed_attributes() as $attr ) {
		if ( wc_attribute_taxonomy_id_by_name( $attr['slug'] ) ) {
			continue;
		}

		wc_create_attribute(
			array(
				'name'         => $attr['name'],
				'slug'         => $attr['slug'],
				'type'         => 'select',
				'order_by'     => 'menu_order',
				'has_archives' => false,
			)
		);
	}

	flush_rewrite_rules();
}

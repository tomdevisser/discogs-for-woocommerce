<?php
/**
 * Product editor customizations.
 *
 * @package Discogs_For_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_enqueue_scripts', 'dfw_enqueue_product_scripts' );
add_action( 'wp_ajax_dfw_apply_to_product', 'dfw_ajax_apply_to_product' );

/**
 * Enqueue scripts on the product edit screen.
 *
 * @param string $hook_suffix The current admin page.
 */
function dfw_enqueue_product_scripts( $hook_suffix ) {
	if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
		return;
	}

	$screen = get_current_screen();

	if ( ! $screen || 'product' !== $screen->post_type ) {
		return;
	}

	wp_enqueue_style(
		'dfw-product',
		DFW_PLUGIN_URI . 'assets/css/product.css',
		array(),
		DFW_VERSION
	);

	wp_enqueue_script(
		'dfw-product',
		DFW_PLUGIN_URI . 'assets/js/product.js',
		array(),
		DFW_VERSION,
		true
	);

	wp_localize_script(
		'dfw-product',
		'dfwProduct',
		array(
			'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
			'nonce'          => wp_create_nonce( 'dfw_product' ),
			'fetchButton'    => __( 'Fetch from Discogs', 'dfw' ),
			'fetching'       => __( 'Fetching…', 'dfw' ),
			'modalTitle'     => __( 'Discogs Results', 'dfw' ),
			'cancel'         => __( 'Cancel', 'dfw' ),
			'addToProduct'   => __( 'Add to product', 'dfw' ),
			'noResults'      => __( 'No results found.', 'dfw' ),
			'field'          => __( 'Field', 'dfw' ),
			'value'          => __( 'Value', 'dfw' ),
			'import'         => __( 'Import', 'dfw' ),
			'tracklist'      => __( 'Tracklist', 'dfw' ),
			'productImage'   => __( 'Product Image', 'dfw' ),
			'gallery'        => __( 'Product Gallery', 'dfw' ),
			'applying'       => __( 'Applying…', 'dfw' ),
			'labelTitle'     => __( 'Title', 'dfw' ),
			'labelArtist'    => __( 'Artist', 'dfw' ),
			'labelYear'      => __( 'Year', 'dfw' ),
			'labelCountry'   => __( 'Country', 'dfw' ),
			'category'       => __( 'Category (format)', 'dfw' ),
			'subcategory'    => __( 'Subcategories (genre)', 'dfw' ),
			'description'    => __( 'Description', 'dfw' ),
			'descriptionTpl' => dfw_get_description_template(),
		)
	);
}

/**
 * AJAX handler: apply Discogs data to a product.
 */
function dfw_ajax_apply_to_product() {
	check_ajax_referer( 'dfw_product', 'nonce' );

	if ( ! current_user_can( 'edit_products' ) ) {
		wp_send_json_error( __( 'You do not have permission to do this.', 'dfw' ), 403 );
	}

	$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
	$fields     = isset( $_POST['fields'] ) ? json_decode( wp_unslash( $_POST['fields'] ), true ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Individual fields are sanitized below.

	if ( ! $product_id || empty( $fields ) ) {
		wp_send_json_error( __( 'Missing product ID or fields.', 'dfw' ), 400 );
	}

	$product = wc_get_product( $product_id );

	if ( ! $product ) {
		wp_send_json_error( __( 'Product not found.', 'dfw' ), 404 );
	}

	if ( ! empty( $fields['title'] ) ) {
		$title = sanitize_text_field( $fields['title'] );
		$product->set_name( $title );
		$product->set_slug( sanitize_title( $title ) );
	}

	if ( ! empty( $fields['description'] ) ) {
		$product->set_description( wp_kses_post( $fields['description'] ) );
	}

	dfw_apply_categories( $product_id, $fields );

	$attribute_map = array(
		'artists_sort' => 'pa_dfw_artist',
		'country'      => 'pa_dfw_country',
		'year'         => 'pa_dfw_year',
	);

	$attributes = $product->get_attributes();

	foreach ( $fields as $key => $value ) {
		if ( ! isset( $attribute_map[ $key ] ) || empty( $value ) ) {
			continue;
		}

		$taxonomy = $attribute_map[ $key ];
		$term     = term_exists( $value, $taxonomy );

		if ( ! $term ) {
			$term = wp_insert_term( $value, $taxonomy );
		}

		if ( is_wp_error( $term ) ) {
			continue;
		}

		$term_id = is_array( $term ) ? $term['term_id'] : $term;

		$attribute = new WC_Product_Attribute();
		$attribute->set_id( wc_attribute_taxonomy_id_by_name( $taxonomy ) );
		$attribute->set_name( $taxonomy );
		$attribute->set_options( array( (int) $term_id ) );
		$attribute->set_visible( true );

		$attributes[ $taxonomy ] = $attribute;
	}

	$product->set_attributes( $attributes );
	$product->set_status( 'draft' );

	$images = isset( $_POST['images'] ) ? json_decode( sanitize_text_field( wp_unslash( $_POST['images'] ) ), true ) : array();

	if ( ! empty( $images ) ) {
		$gallery_ids = array();

		foreach ( $images as $image ) {
			$attachment_id = dfw_sideload_image( $image['uri'], $product_id );

			if ( is_wp_error( $attachment_id ) ) {
				continue;
			}

			if ( 'image' === $image['type'] ) {
				$product->set_image_id( $attachment_id );
			} else {
				$gallery_ids[] = $attachment_id;
			}
		}

		if ( ! empty( $gallery_ids ) ) {
			$product->set_gallery_image_ids( $gallery_ids );
		}
	}

	$product->save();

	wp_send_json_success(
		array(
			'edit_url' => get_edit_post_link( $product->get_id(), 'raw' ),
		)
	);
}

/**
 * Download an image from a URL and attach it to a product.
 *
 * @param string $url       The image URL.
 * @param int    $parent_id The product/post ID.
 * @return int|WP_Error Attachment ID or WP_Error on failure.
 */
function dfw_sideload_image( $url, $parent_id ) {
	$filename = basename( wp_parse_url( $url, PHP_URL_PATH ) );

	$existing = dfw_find_existing_attachment( $filename );

	if ( $existing ) {
		return $existing;
	}

	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$tmp = download_url( $url );

	if ( is_wp_error( $tmp ) ) {
		return $tmp;
	}

	$file_array = array(
		'name'     => $filename,
		'tmp_name' => $tmp,
	);

	$attachment_id = media_handle_sideload( $file_array, $parent_id );

	if ( is_wp_error( $attachment_id ) ) {
		wp_delete_file( $tmp );
	}

	return $attachment_id;
}

/**
 * Find an existing attachment by filename.
 *
 * @param string $filename The filename to search for.
 * @return int|false Attachment ID or false if not found.
 */
function dfw_find_existing_attachment( $filename ) {
	global $wpdb;

	$attachment_id = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value LIKE %s LIMIT 1",
			'%' . $wpdb->esc_like( $filename )
		)
	);

	return $attachment_id ? (int) $attachment_id : false;
}

/**
 * Apply format as parent category and genres as subcategories.
 *
 * @param int   $product_id The product ID.
 * @param array $fields     The fields from the AJAX request.
 */
function dfw_apply_categories( $product_id, $fields ) {
	$formats = isset( $fields['formats'] ) ? $fields['formats'] : array();
	$genres  = isset( $fields['genres'] ) ? $fields['genres'] : array();

	if ( empty( $formats ) && empty( $genres ) ) {
		return;
	}

	$category_ids = array();

	foreach ( (array) $formats as $format_name ) {
		$format_name = sanitize_text_field( $format_name );
		$parent_id   = dfw_get_or_create_product_cat( $format_name );

		if ( ! $parent_id ) {
			continue;
		}

		$category_ids[] = $parent_id;

		foreach ( (array) $genres as $genre_name ) {
			$genre_name = sanitize_text_field( $genre_name );
			$child_id   = dfw_get_or_create_product_cat( $genre_name, $parent_id );

			if ( $child_id ) {
				$category_ids[] = $child_id;
			}
		}
	}

	if ( ! empty( $category_ids ) ) {
		wp_set_object_terms( $product_id, $category_ids, 'product_cat' );
	}
}

/**
 * Get or create a product category by name and optional parent.
 *
 * @param string $name      Category name.
 * @param int    $parent_id Parent term ID (0 for top-level).
 * @return int|false Term ID or false on failure.
 */
function dfw_get_or_create_product_cat( $name, $parent_id = 0 ) {
	$term = get_term_by( 'name', $name, 'product_cat' );

	if ( $term && (int) $term->parent === $parent_id ) {
		return (int) $term->term_id;
	}

	// Name might exist at a different level, search with parent.
	$terms = get_terms(
		array(
			'taxonomy'   => 'product_cat',
			'name'       => $name,
			'parent'     => $parent_id,
			'hide_empty' => false,
		)
	);

	if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
		return (int) $terms[0]->term_id;
	}

	$result = wp_insert_term(
		$name,
		'product_cat',
		array( 'parent' => $parent_id )
	);

	if ( is_wp_error( $result ) ) {
		return false;
	}

	return (int) $result['term_id'];
}

/**
 * Get the description template from settings.
 *
 * @return string The template string.
 */
function dfw_get_description_template() {
	$settings = get_option( 'dfw_settings', array() );

	return isset( $settings['description_template'] ) ? $settings['description_template'] : '';
}

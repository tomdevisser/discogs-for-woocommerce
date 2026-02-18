<?php
/**
 * Discogs API integration.
 *
 * @package Discogs_For_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_ajax_dfw_search_barcode', 'dfw_ajax_search_barcode' );

define( 'DFW_API_BASE_URL', 'https://api.discogs.com' );
define( 'DFW_USER_AGENT', 'DiscogsForWooCommerce/' . DFW_VERSION );
define( 'DFW_REQUEST_TOKEN_URL', DFW_API_BASE_URL . '/oauth/request_token' );
define( 'DFW_AUTHORIZE_URL', 'https://www.discogs.com/oauth/authorize' );
define( 'DFW_ACCESS_TOKEN_URL', DFW_API_BASE_URL . '/oauth/access_token' );
define( 'DFW_CACHE_EXPIRATION', DAY_IN_SECONDS );

/**
 * Get the stored API credentials.
 *
 * @return array{consumer_key: string, consumer_secret: string}
 */
function dfw_get_credentials() {
	$settings = get_option( 'dfw_settings', array() );

	return array(
		'consumer_key'    => isset( $settings['consumer_key'] ) ? $settings['consumer_key'] : '',
		'consumer_secret' => isset( $settings['consumer_secret'] ) ? $settings['consumer_secret'] : '',
	);
}

/**
 * Perform a GET request to the Discogs API.
 *
 * @param string $endpoint API endpoint (e.g. '/database/search').
 * @param array  $params   Query parameters.
 * @return array|WP_Error Decoded response body or WP_Error on failure.
 */
function dfw_api_get( $endpoint, $params = array() ) {
	$credentials = dfw_get_credentials();

	if ( empty( $credentials['consumer_key'] ) || empty( $credentials['consumer_secret'] ) ) {
		return new WP_Error( 'dfw_missing_credentials', __( 'Discogs API credentials are not configured.', 'dfw' ) );
	}

	$params['key']    = $credentials['consumer_key'];
	$params['secret'] = $credentials['consumer_secret'];

	$url = DFW_API_BASE_URL . $endpoint . '?' . http_build_query( $params );

	$response = wp_remote_get(
		$url,
		array(
			'headers' => array(
				'User-Agent' => DFW_USER_AGENT,
			),
		)
	);

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$code = wp_remote_retrieve_response_code( $response );
	$body = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( $code < 200 || $code >= 300 ) {
		$message = isset( $body['message'] ) ? $body['message'] : __( 'Unknown API error.', 'dfw' );
		return new WP_Error( 'dfw_api_error', $message, array( 'status' => $code ) );
	}

	return $body;
}

/**
 * Search Discogs releases by barcode.
 *
 * @param string $barcode The barcode (EAN/UPC) to search for.
 * @return array|WP_Error API response or WP_Error on failure.
 */
function dfw_search_by_barcode( $barcode ) {
	return dfw_api_get(
		'/database/search',
		array(
			'barcode' => $barcode,
			'type'    => 'release',
		)
	);
}

/**
 * Fetch full release details by ID.
 *
 * @param int $release_id The Discogs release ID.
 * @return array|WP_Error API response or WP_Error on failure.
 */
function dfw_get_release( $release_id ) {
	return dfw_api_get( '/releases/' . intval( $release_id ) );
}

/**
 * AJAX handler for barcode search.
 *
 * Searches by barcode, then fetches the full release details
 * (including tracklist) for the first result.
 */
function dfw_ajax_search_barcode() {
	check_ajax_referer( 'dfw_product', 'nonce' );

	if ( ! current_user_can( 'edit_products' ) ) {
		wp_send_json_error( __( 'You do not have permission to do this.', 'dfw' ), 403 );
	}

	$barcode = isset( $_GET['barcode'] ) ? sanitize_text_field( wp_unslash( $_GET['barcode'] ) ) : '';

	if ( empty( $barcode ) ) {
		wp_send_json_error( __( 'No barcode provided.', 'dfw' ), 400 );
	}

	$cache_key = 'dfw_barcode_' . md5( $barcode );
	$cached    = get_transient( $cache_key );

	if ( false !== $cached ) {
		wp_send_json_success( $cached );
	}

	$search = dfw_search_by_barcode( $barcode );

	if ( is_wp_error( $search ) ) {
		wp_send_json_error( $search->get_error_message(), 500 );
	}

	if ( empty( $search['results'] ) ) {
		wp_send_json_error( __( 'No results found.', 'dfw' ), 404 );
	}

	$release_id = $search['results'][0]['id'];
	$release    = dfw_get_release( $release_id );

	if ( is_wp_error( $release ) ) {
		wp_send_json_error( $release->get_error_message(), 500 );
	}

	set_transient( $cache_key, $release, DFW_CACHE_EXPIRATION );

	wp_send_json_success( $release );
}

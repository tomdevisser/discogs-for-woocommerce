<?php
/**
 * Plugin Name: Discogs for WooCommerce
 * Description: A WooCommerce add-on that fetches product information from Discogs.
 * Version: 1.0
 * Author: Tom de Visser
 * Author URI: https://tomdevisser.dev
 * Text Domain: dfw
 * Requires Plugins: woocommerce
 *
 * @package Discogs_For_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

define( 'DFW_VERSION', '1.0' );
define( 'DFW_PLUGIN_FILE', __FILE__ );
define( 'DFW_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DFW_PLUGIN_URI', plugin_dir_url( __FILE__ ) );
define( 'DFW_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once DFW_PLUGIN_DIR . 'includes/attributes.php';
require_once DFW_PLUGIN_DIR . 'includes/discogs-api.php';
require_once DFW_PLUGIN_DIR . 'includes/settings.php';
require_once DFW_PLUGIN_DIR . 'includes/product.php';

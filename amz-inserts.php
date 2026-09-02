<?php
/**
 * Plugin Name:       Amazon Inserts
 * Description:       Reusable Amazon affiliate text links, image links, product cards, and grids. Insert with a shortcode or a block.
 * Version:           1.1.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            Clint Losee
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       amz-inserts
 *
 * @package Amz_Inserts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AMZ_INSERTS_VERSION', '1.1.0' );
define( 'AMZ_INSERTS_FILE', __FILE__ );
define( 'AMZ_INSERTS_DIR', plugin_dir_path( __FILE__ ) );
define( 'AMZ_INSERTS_URL', plugin_dir_url( __FILE__ ) );

require_once AMZ_INSERTS_DIR . 'includes/class-url.php';
require_once AMZ_INSERTS_DIR . 'includes/class-image.php';
require_once AMZ_INSERTS_DIR . 'includes/class-settings.php';
require_once AMZ_INSERTS_DIR . 'includes/class-renderer.php';
require_once AMZ_INSERTS_DIR . 'includes/class-cpt-unit.php';
require_once AMZ_INSERTS_DIR . 'admin/unit-editor.php';
require_once AMZ_INSERTS_DIR . 'includes/class-shortcode.php';
require_once AMZ_INSERTS_DIR . 'includes/class-fetch.php';
require_once AMZ_INSERTS_DIR . 'includes/class-block.php';
require_once AMZ_INSERTS_DIR . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( 'Amz_Inserts_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Amz_Inserts_Plugin', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'Amz_Inserts_Plugin', 'init' ) );

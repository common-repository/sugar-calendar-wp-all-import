<?php
/**
 * Plugin Name:       Sugar Calendar - WP All Import Add-on
 * Plugin URI:        https://sugarcalendar.com/downloads/wp-all-import/
 * Description:       Import Sugar Calendar events using WP All Import
 * Author:            Sandhills Development, LLC
 * Author URI:        https://sandhillsdev.com
 * Text Domain:       sc-wp-import
 * Domain Path:       /languages
 * Requires PHP:      7.2
 * Requires at least: 5.2
 * Version:           1.0.1
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

require_once 'includes/class-sc-event-import-addon.php';
/**
 * Begins execution of the plugin.
 */
function run_sc_import_addon() {
	$plugin = new Sugar_Calendar_WP_Import\Sc_Event_Import_Addon();
	$plugin->run();
}

run_sc_import_addon();
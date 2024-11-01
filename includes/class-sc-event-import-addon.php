<?php
/**
 * Responsible to include required components
 *
 * @link              https://wisdmlabs.com
 * @since             1.0.0
 *
 *  @package Sugar_Calendar_WP_Import
 *  @subpackage Sugar_Calendar_WP_Import/includes
 */

namespace Sugar_Calendar_WP_Import{
	/**
	 * This will load dependencies.
	 *
	 * @since      1.0.0
	 * @package    Sugar_Calendar_WP_Import
	 * @subpackage Sugar_Calendar_WP_Import/includes
	 * @author     WisdmLabs <support@wisdmlabs.com>
	 */
	class Sc_Event_Import_Addon {





		/**
		 * Allows to keep single instance.
		 *
		 * @var Bridge_Woocommerce The single instance of the class
		 * @since 1.0.0
		 */
		protected static $_instance = null;

		/**
		 * Main Bridge_Woocommerce Instance
		 *
		 * Ensures only one instance of Bridge_Woocommerce is loaded or can be loaded.
		 *
		 * @since 1.0.0
		 * @static
		 * @see bridge_woocommerce()
		 * @return Bridge_Woocommerce - Main instance
		 */
		public static function instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}

		/**
		 * Define the core functionality of the plugin.
		 *
		 * Load the dependencies, define the locale, and instantiate Addon.
		 *
		 * @since    1.0.0
		 */
		public function run() {
			$this->define_constants();
			$this->load_dependencies();
			$this->set_locale();
		}

		/**
		 * Setup plugin constants
		 *
		 * @access private
		 * @since 1.0.0
		 * @return void
		 */
		private function define_constants() {
			if ( ! defined( 'SC_IMPORT_SLUG' ) ) {
				define( 'SC_IMPORT_SLUG', 'sugar_calendar_add_on' );
			}

			// Plugin Folder Path.
			if ( ! defined( 'SC_EVENT_IMPORT_PLUGIN_DIR' ) ) {
				define( 'SC_EVENT_IMPORT_PLUGIN_DIR', plugin_dir_path( dirname( __FILE__ ) ) );
			}

			// Plugin Folder Path.
			if ( ! defined( 'SC_EVENT_IMPORT_PLUGIN_URL' ) ) {
				define( 'SC_EVENT_IMPORT_PLUGIN_URL', plugin_dir_url( dirname( __FILE__ ) ) );
			}
		}

		/**
		 * Load the required files.
		 */
		private function load_dependencies() {
			require_once SC_EVENT_IMPORT_PLUGIN_DIR . 'includes/class-rapid-addon.php';
			require_once SC_EVENT_IMPORT_PLUGIN_DIR . 'includes/class-register-addon.php';
			require_once SC_EVENT_IMPORT_PLUGIN_DIR . 'includes/functions.php';
			require_once SC_EVENT_IMPORT_PLUGIN_DIR . 'includes/class-event-validator.php';
			require_once SC_EVENT_IMPORT_PLUGIN_DIR . 'includes/class-data-validator.php';
			require_once SC_EVENT_IMPORT_PLUGIN_DIR . 'includes/class-event-data-preparor.php';

			$register_addon = new Register_Addon( 'Sugar Calendar Add on', SC_IMPORT_SLUG );
			$register_addon->initialize_addon();
		}

		/**
		 * Define the locale for this plugin for internationalization.
		 *
		 * Uses the Bridge_Woocommerce_I18n class in order to set the domain and to register the hook
		 * with WordPress.
		 *
		 * @since    1.0.0
		 * @access   private
		 */
		private function set_locale() {
			add_action( 'plugins_loaded', array( $this, 'sc_wp_import_textdomain' ) );
		}
		/**
		 * Load the plugin text domain for translation.
		 *
		 * @since    1.0.0
		 */
		public function sc_wp_import_textdomain() {
			\load_plugin_textdomain( 'sc-wp-import', false, SC_EVENT_IMPORT_PLUGIN_DIR . '/languages/' );
		}
	}
}

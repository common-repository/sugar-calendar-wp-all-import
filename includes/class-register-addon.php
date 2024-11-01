<?php
/**
 * Registering the addon
 *
 * @link              https://wisdmlabs.com
 * @since             1.0.0
 *
 *  @package Sugar_Calendar_WP_Import
 *  @subpackage Sugar_Calendar_WP_Import/includes
 */

namespace Sugar_Calendar_WP_Import{

	/**
	 * This will register the addon.
	 *
	 * @since      1.0.0
	 * @package    Sugar_Calendar_WP_Import
	 * @subpackage Sugar_Calendar_WP_Import/includes
	 * @author     WisdmLabs <support@wisdmlabs.com>
	 */
	class Register_Addon {


		/**
		 * $name Name of the Addon.
		 *
		 * @since    1.0.0
		 * @access   private
		 * @var String.
		 */
		private $name;

		/**
		 * $slug Add on slug.
		 *
		 * @since    1.0.0
		 * @access   private
		 * @var String.
		 */
		private $slug;

		/**
		 * $rapid_addon Rapid addon object.
		 *
		 * @since    1.0.0
		 * @access   private
		 * @var Object
		 */
		public $rapid_addon;

		/**
		 * Initialize the plugin object.
		 *
		 * @param string $add_on_name name of Addon.
		 * @param string $add_on_slug Slug of Add on.
		 */
		public function __construct( $add_on_name, $add_on_slug ) {
			$this->name = $add_on_name;
			$this->slug = $add_on_slug;

			$this->rapid_addon = new \Rapid_Addon( $this->name, $this->slug );
			add_filter( 'wp_all_import_is_post_to_create', array( $this, 'is_event_to_create' ), 10, 3 );
			add_filter( 'wp_all_import_is_post_to_update', array( $this, 'is_event_to_update' ), 10, 5 );
			add_filter( 'wp_all_import_post_type_image', array( $this, 'update_addon_image' ), 10, 1 );
		}

		/**
		 * This will initialize the addon.
		 *
		 * @return void
		 */
		public function initialize_addon() {
			$this->add_fields();
			$this->rapid_addon->run(
				array(
					'post_types' => array( 'sc_event' )
				)
			);
			$this->rapid_addon->set_import_function( array( $this, 'update_event_details' ) );
			$this->rapid_addon->set_post_saved_function( array( $this, 'update_event_color' ) );

			$this->rapid_addon->admin_notice(
				'The Sugar Calendar WP All Import Add-On requires WP All Import <a href="http://www.wpallimport.com/order-now/" target="_blank">Pro</a> or <a href="http://wordpress.org/plugins/wp-all-import" target="_blank">Free</a>, and the <a href="https://sugarcalendar.com/pricing/">Sugar Calendar</a> plugin.'
			);
		}

		/**
		 * Add Fields for add-on.
		 */
		private function add_fields() {
			$this->rapid_addon->add_field( 'start_date', __( 'Event Start Date & time', 'sc-wp-import' ), 'text', null, __( 'Import date in any strtotime compatible format.', 'sc-wp-import' ) );
			$this->rapid_addon->add_field( 'end_date', __( 'Event End Date & time', 'sc-wp-import' ), 'text', null, __( 'Import date in any strtotime compatible format.', 'sc-wp-import' ) );
			$this->rapid_addon->add_field(
				'all_day',
				__( 'All Day', 'sc-wp-import' ),
				'radio',
				array(
					'1' => 'Yes',
					'0' => 'No',
				),
				__( 'Set to Yes, if Event duration is All day', 'sc-wp-import' )
			);
			$this->rapid_addon->add_field(
				'recurrence',
				__( 'Repeat', 'sc-wp-import' ),
				'radio',
				array(
					'0'       => __( 'Never', 'sc-wp-import' ),
					'daily'   => __( 'Daily', 'sc-wp-import' ),
					'weekly'  => __( 'Weekly', 'sc-wp-import' ),
					'monthly' => __( 'Monthly', 'sc-wp-import' ),
					'yearly'  => __( 'Yearly', 'sc-wp-import' ),
				),
				__( 'Sets Event Recurrance', 'sc-wp-import' )
			);
			$this->rapid_addon->add_field( 'recurrence_end', __( 'Repeat End Date', 'sc-wp-import' ), 'text', null, __( 'Import date in any strtotime compatible format.', 'sc-wp-import' ) );
			$this->rapid_addon->add_field( 'event_location', __( 'Location', 'sc-wp-import' ), 'text', null, __( 'Enter event venue address.', 'sc-wp-import' ) );
		}

		/**
		 * Create/Update Sugar calendar Event on successful Post create/update.
		 *
		 * @param  Integer $post_id     Post ID created.
		 * @param  Array   $data       Addon data submitted.
		 * @param  Object  $import_options Import options object.
		 * @param  Array   $article        Post data submitted.
		 * @return null or void.
		 */
		public function update_event_details( $post_id, $data, $import_options, $article ) {
			if ( 'sc_event' !== get_post_type( $post_id ) ) {
				return null;
			}

			// Get an event.
			$event = \sugar_calendar_get_event_by_object( $post_id );

			$event_data_preparor = new Event_Data_Preparor();
			$to_save             = $event_data_preparor->get_event_data( $post_id, $data, $import_options, $article, $event, $this->rapid_addon );

			$this->rapid_addon->log( __( 'Adding Event details.', 'sc-wp-import' ) );

			if ( ! empty( $event->id ) ) {
				\sugar_calendar_update_event( $event->id, $to_save );
			}

			if ( empty( $event->id ) ) {
				\sugar_calendar_add_event( $to_save );
			}
		}

		/**
		 * Updates Color for Event.
		 *
		 * @param  Integer $post_id Post ID to update.
		 * @return null or void.
		 */
		public function update_event_color( $post_id ) {
			if ( 'sc_event' !== get_post_type( $post_id ) ) {
				return null;
			}

			$color = sugar_calendar_get_event_color( $post_id, 'post' );
			$event = \sugar_calendar_get_event_by_object( $post_id );

			if ( ! empty( $color ) && 'none' !== $color ) {
				\sugar_calendar_update_event( $event->id, array( 'color' => $color ) );
			}
		}

		/**
		 * Adds the Image for Sugar calendar post type.
		 *
		 * @param  Array $image_data Existing image data.
		 * @return Array Adds icon for Sugar Calendar event.
		 */
		public function update_addon_image( $image_data ) {
			$image_data['sc_event'] = array( 'image' => 'dashicons-calendar-alt' );
			return $image_data;
		}

		/**
		 * Validate data & Skip import for SC Event import.
		 *
		 * @param  boolean $continue_import Decide to import or skip.
		 * @param  Array   $data     User submiitted current processing row.
		 * @param  Int     $import_id      Current Import ID.
		 * @return boolean  True for success.
		 */
		public function is_event_to_create( $continue_import, $data, $import_id ) {
			return Event_Validator::is_event_post_to_create( $continue_import, $data, $import_id, $this->rapid_addon );
		}

		/**
		 * Validate data & Skip Update for SC Event import.
		 *
		 * @param  boolean $continue_import Decide to import or skip.
		 * @param  Integer $post_to_update_id Current post to update.
		 * @param  XML     $current_xml_node  Data added for Update.
		 * @param  Int     $import_id      Current Import ID.
		 * @param  XML     $simple_xml User submiitted current processing row.
		 * @return boolean  True for success.
		 */
		public function is_event_to_update( $continue_import, $post_to_update_id, $current_xml_node, $import_id, $simple_xml ) {
			return Event_Validator::is_event_post_to_update( $continue_import, $post_to_update_id, $current_xml_node, $import_id, $simple_xml, $this->rapid_addon );
		}
	}

}

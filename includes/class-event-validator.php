<?php
/**
 * This validates if the event can be created/updated or not.
 *
 * @link              https://wisdmlabs.com
 * @since             1.0.0
 *
 *  @package Sugar_Calendar_WP_Import
 *  @subpackage Sugar_Calendar_WP_Import/includes
 */

namespace Sugar_Calendar_WP_Import{

	/**
	 * This will validates data submitted to the addon.
	 *
	 * @since      1.0.0
	 * @package    Sugar_Calendar_WP_Import
	 * @subpackage Sugar_Calendar_WP_Import/includes
	 * @author     WisdmLabs <support@wisdmlabs.com>
	 */
	class Event_Validator {




		/**
		 * Validate data & Skip import for SC Event import.
		 *
		 * @param  boolean $continue_import Decide to import or skip.
		 * @param  Array   $data     User submiitted current processing row.
		 * @param  Int     $import_id      Current Import ID.
		 * @param  Object  $sc_addon Addon object.
		 * @return boolean  True for success.
		 */
		public static function is_event_post_to_create( $continue_import, $data, $import_id, $sc_addon ) {
			$import = self::is_event_to_check( $import_id );

			if ( false !== $import ) {
				$data_validator_obj = new Data_Validator();

				$result = $data_validator_obj->is_data_proper( $data, $import );

				return self::verify_result( $result, $sc_addon );
			}

			return $continue_import;
		}

		/**
		 * Validate data & Skip Update for SC Event import.
		 *
		 * @param  boolean $continue_import Decide to import or skip.
		 * @param  Integer $post_to_update_id Current post to update.
		 * @param  XML     $current_xml_node  Data added for Update.
		 * @param  Int     $import_id      Current Import ID.
		 * @param  XML     $simple_xml User submiitted current processing row.
		 * @param  Object  $sc_addon Addon object.
		 * @return boolean  True for success.
		 */
		public static function is_event_post_to_update( $continue_import, $post_to_update_id, $current_xml_node, $import_id, $simple_xml, $sc_addon ) {
			$import = self::is_event_to_check( $import_id );

			if ( false !== $import ) {
				unset( $simple_xml );

				$can_update_start_date = $sc_addon->can_update_meta( 'start_date', array( 'options' => $import->options ) );
				$can_update_end_date   = $sc_addon->can_update_meta( 'end_date', array( 'options' => $import->options ) );

				if ( $can_update_start_date || $can_update_end_date ) {
					$data_validator_obj = new Data_Validator();

					$result = $data_validator_obj->is_data_proper( $current_xml_node, $import, $can_update_start_date, $can_update_end_date, $post_to_update_id );

					return self::verify_result( $result, $sc_addon );
				}
			}
			return $continue_import;
		}

		/**
		 * Verifies if Sugar Calendar Event post in Import.
		 *
		 * @param  Int $import_id      Current Import ID.
		 * @return boolean or object.
		 */
		private static function is_event_to_check( $import_id ) {
			// Retrieve import object.
			$import = new \PMXI_Import_Record();
			$import->getById( $import_id );

			// Ensure import object is valid.
			if ( ! $import->isEmpty() ) {
				// Retrieve post type.
				$post_type = $import->options['custom_type'];

				if ( 'sc_event' == $post_type ) {
					return $import;
				}
			}

			return false;
		}

		/**
		 * This verifies the result & Log it.
		 *
		 * @param  String $result   Result string.
		 * @param  Object $sc_addon Addon object.
		 * @return boolean Operation after processing result.
		 */
		private static function verify_result( $result, $sc_addon ) {
			if ( 'success' == $result ) {
				return true;
			}

			$sc_addon->log( __( '<b>ERROR</b>', 'sc-wp-import' ) . ' : ' . $result );
			\PMXI_Plugin::$session->errors++;
			return false;
		}
	}
}

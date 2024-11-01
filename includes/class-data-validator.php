<?php
/**
 * This validates the data entered by User is proper or not.
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
	class Data_Validator {





		/**
		 * This runs during sc_event import and decide whether to create event or not.
		 *
		 * @param Array   $data User submitted row.
		 * @param Object  $import Import object.
		 * @param  Boolean $can_update_start_date Whether Start date needs update & hence validation.
		 * @param  Boolean $can_update_end_date Whether End date needs update & hence validation.
		 * @param  Integer $post_to_update_id Post ID which will be updated in Existing Event import.
		 * @return boolean returns boolean/string in case of error.
		 */
		public function is_data_proper( $data, $import, $can_update_start_date = true, $can_update_end_date = true, $post_to_update_id = 0 ) {
			$start_date = $this->get_start_date( $data, $import );
			$end_date   = $this->get_end_date( $data, $import );

			if ( false == $can_update_start_date ) {
				$start_date = $this->get_event_start_date( $post_to_update_id );
			}

			if ( $can_update_start_date && false == $this->is_start_date_proper( $start_date ) ) {
				return __( 'Start Date NOT proper.', 'sc-wp-import' );
			}

			if ( $can_update_end_date && false == $this->is_end_date_proper( $start_date, $end_date ) ) {
				return __( 'End Date less than start date.', 'sc-wp-import' );
			}

			return 'success';
		}

		/**
		 * Verifies Start date proper or not.
		 *
		 * @param Date $start_date Start date to check.
		 * @return boolean Success if Start date empty or proper.
		 */
		public function is_start_date_proper( $start_date ) {
			if ( ! empty( $start_date ) && false == strtotime( $start_date ) ) {
				return false;
			}

			return true;
		}

		/**
		 * Verifies End date proper or not.
		 *
		 * @param Date $start_date Start date to check.
		 * @param Date $end_date End date to check.
		 * @return boolean Success if End date proper.
		 */
		public function is_end_date_proper( $start_date, $end_date ) {
			if ( ! empty( $end_date ) && ( false != strtotime( trim( $end_date ) ) ) && ( $start_date > $end_date ) ) {
				return false;
			}

			return true;
		}

		/**
		 * Fetches user submitted start date.
		 *
		 * @param Array  $data User submitted row.
		 * @param Object $import Import object.
		 * @return String start date string.
		 */
		private function get_start_date( $data, $import ) {
			$start_date_key = $this->key_sanitize( $import->options[ SC_IMPORT_SLUG ]['start_date'] );

			return trim( $data[ $start_date_key ] );
		}

		/**
		 * Fetches user submitted end date.
		 *
		 * @param Array  $data User submitted row.
		 * @param Object $import Import object.
		 * @return String end date string.
		 */
		private function get_end_date( $data, $import ) {
			$end_date_key = $this->key_sanitize( $import->options[ SC_IMPORT_SLUG ]['end_date'] );

			return trim( $data[ $end_date_key ] );
		}

		/**
		 * Fetch Event's start date.
		 *
		 * @param  Integer $post_to_update_id Post ID linked with Event.
		 * @return String start date string.
		 */
		private function get_event_start_date( $post_to_update_id ) {
			$event = \sugar_calendar_get_event_by_object( $post_to_update_id );
			return $event->start_date();
		}

		/**
		 * Remove non-required characters.
		 *
		 * @param  String $key Key value from Import options.
		 * @return String  Key can be mapped with Data array.
		 */
		public function key_sanitize( $key ) {
			$key = substr( $key, 1, ( strlen( $key ) - 5 ) );

			return trim( $key );
		}

		/**
		 * Validates the recurrance & sanitize if required.
		 *
		 * @param  String $repeat Recurrance interval.
		 * @param  Date   $expire Recurrance end interval.
		 * @return array Recurrance interval & expiry date.
		 */
		public static function send_recurrance_after_validate( $repeat, $expire ) {
			$valid_recurrance = array( 'daily', 'monthly', 'yearly', 'weekly' );

			if ( ! empty( $repeat ) && ! in_array( $repeat, $valid_recurrance ) ) {
				$repeat = '';
				$expire = '';
			}

			if ( ! empty( $repeat ) && ! empty( $expire ) ) {
				$expire = self::sanitize_recurrance_end_date( $expire );
			}

			return array(
				'repeat'         => $repeat,
				'recurrence_end' => $expire,
			);
		}

		/**
		 * Sanitize Recurrance end date.
		 *
		 * @param  Date $expire Date submitted.
		 * @return Date Expire date after sanitization.
		 */
		private static function sanitize_recurrance_end_date( $expire ) {
			$hour    = 0;
			$minutes = 0;
			$seconds = 0;
			$am_pm   = 'am';
			$hour    = \Sugar_Calendar\Admin\Editor\Meta\adjust_hour_for_meridiem( $hour, $am_pm );

			// Get the timestamp for the expiration date.
			$expire = mktime(
				intval( $hour ),
				intval( $minutes ),
				intval( $seconds ),
				gmdate( 'n', $expire ),
				gmdate( 'j', $expire ),
				gmdate( 'Y', $expire )
			);

			return gmdate( 'Y-m-d H:i:s', $expire );
		}
	}
}

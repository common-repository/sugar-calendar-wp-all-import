<?php
/**
 * This is Helper class which parses & sanitize data for Event Create & Update.
 *
 * @link              https://wisdmlabs.com
 * @since             1.0.0
 *
 *  @package Sugar_Calendar_WP_Import
 *  @subpackage Sugar_Calendar_WP_Import/includes
 */

namespace Sugar_Calendar_WP_Import{

	/**
	 * This will prepare data to be added/updated in Event.
	 *
	 * @since      1.0.0
	 * @package    Sugar_Calendar_WP_Import
	 * @subpackage Sugar_Calendar_WP_Import/includes
	 * @author     WisdmLabs <support@wisdmlabs.com>
	 */
	class Event_Data_Preparor {




		/**
		 * Get Event data to be updated.
		 *
		 * @param  Integer $post_id     Post ID created.
		 * @param  Array   $data       Addon data submitted.
		 * @param  Object  $import_options Import options object.
		 * @param  Array   $article        Post data submitted.
		 * @param  Object  $event Event object from post ID.
		 * @param  Object  $sc_addon Addon object.
		 * @return null or void.
		 */
		public function get_event_data( $post_id, $data, $import_options, $article, $event, $sc_addon ) {
			if ( empty( $article['ID'] ) ) {
				$to_merge = $this->prepare_data_to_create( $data );
			}

			if ( ! empty( $article['ID'] ) ) {
				$can_update_fields = $this->can_update_fields( $import_options, $sc_addon );
				$to_merge          = $this->prepare_data_to_update( $data, $event, $can_update_fields );
			}

			$to_save = get_event_properties( $event, $post_id );

			return array_merge( $to_save, $to_merge );
		}

		/**
		 * Create list of fields can be updated.
		 *
		 * @param  Object $import_options Import options object.
		 * @param  Object $sc_addon Addon object.
		 * @return Array list of fields to update.
		 */
		private function can_update_fields( $import_options, $sc_addon ) {
			$fields = array( 'start_date', 'end_date', 'all_day', 'recurrence', 'recurrence_end', 'event_location' );

			$can_update_fields = array();

			foreach ( $fields as $single_field ) {
				if ( $sc_addon->can_update_meta( $single_field, $import_options ) ) {
					$can_update_fields[] = $single_field;
				}
			}

			return $can_update_fields;
		}

		/**
		 * Prepares & send data for Event create.
		 *
		 * @param  array $data Data submitted by user.
		 * @return array Data to merge after sanitization.
		 */
		private function prepare_data_to_create( $data ) {
			$all_day = $this->prepare_all_day( $data );

			$start = $this->prepare_day( 'start', $data );
			$end   = $this->prepare_day( 'end', $data );

			$recurrence = $this->prepare_repeat_expire( $data );

			return array(
				'start'               => \Sugar_Calendar\Admin\Editor\Meta\sanitize_start( $start, $end, $all_day ),
				'start_tz'            => '', // Time zones (empty for UTC by default).
				'end'                 => sanitize_end( $end, $start, $all_day ),
				'end_tz'              => '',
				'all_day'             => \Sugar_Calendar\Admin\Editor\Meta\sanitize_all_day( $all_day, $start, $end ),
				'recurrence'          => $recurrence['repeat'],
				'recurrence_interval' => 0,
				'recurrence_count'    => 0,
				'recurrence_end'      => $recurrence['recurrence_end'],
				'recurrence_end_tz'   => '',
				'location'            => $this->prepare_location( $data ),
			);
		}

		/**
		 * Prepares data to update. Verifies to update data or not.
		 *
		 * @param  array  $data Data submitted by user.
		 * @param  Object $event   Event object to update.
		 * @param  Array  $can_update_fields List of fields can be updated.
		 * @return array Data to merge after sanitization.
		 */
		private function prepare_data_to_update( $data, $event, $can_update_fields ) {
			$merge = array();

			if ( in_array( 'all_day', $can_update_fields ) || in_array( 'start_day', $can_update_fields ) || in_array( 'end_day', $can_update_fields ) ) {
				$duration = $this->get_duration_for_update( $data, $event, $can_update_fields );
				$merge    = array_merge( $merge, $this->prepare_duration_for_update( $duration ) );
			}

			if ( in_array( 'recurrence', $can_update_fields ) || in_array( 'recurrence_end', $can_update_fields ) ) {
				// Update recurrance details.

				$merge = array_merge( $merge, $this->prepare_recurrance_for_update( $data, $event, $can_update_fields ) );
			}

			if ( in_array( 'event_location', $can_update_fields ) ) {
				$location = $this->prepare_location( $data );
				$merge    = array_merge( $merge, array( 'location' => $location ) );
			}

			return $merge;
		}

		/**
		 * Sanitize & return value.
		 *
		 * @param  Array $data Data submitted by user.
		 * @return boolean all_day or not.
		 */
		private function prepare_all_day( $data ) {
			$all_day = true;

			if ( empty( trim( $data['all_day'] ) ) || ( 0 == intval( $data['all_day'] ) ) ) {
				$all_day = false;
			}
			return $all_day;
		}

		/**
		 * Sanitize date.
		 *
		 * @param  String $prefix prefix for Date.
		 * @param  Array  $data Data submitted by user.
		 * @return Date Sanitized date.
		 */
		private function prepare_day( $prefix, $data ) {
			$date = strtotime( trim( $data[ $prefix . '_date' ] ) );
			if ( false == $date ) {
				return get_current_date();
			}

			return round_to_nearest_minute_interval( new \DateTime( gmdate( 'Y-m-d H:i:s', $date ) ) )->format( 'Y-m-d H:i:s' );
		}


		/**
		 * Sanitize location & return value.
		 *
		 * @param  Array $data  Data submitted by user.
		 * @return String Location string.
		 */
		private function prepare_location( $data ) {
			return ! empty( $data['event_location'] ) && is_string( $data['event_location'] )
			? wp_kses( $data['event_location'], array() )
			: '';
		}

		/**
		 * Fetches duration parameters for update.
		 *
		 * @param  array  $data Data submitted by user.
		 * @param  Object $event   Event object to update.
		 * @param Array  $can_update_fields List of fields can be updated.
		 * @return Array Duration parameters.
		 */
		private function get_duration_for_update( $data, $event, $can_update_fields ) {
			$all_day = $event->is_all_day();
			$start   = $event->start_date();
			$end     = $event->end_date();

			if ( in_array( 'all_day', $can_update_fields ) ) {
				$all_day = $this->prepare_all_day( $data );
			}

			if ( in_array( 'start_date', $can_update_fields ) ) {
				$start = $this->prepare_day( 'start', $data );
			}

			if ( in_array( 'end_date', $can_update_fields ) ) {
				$end = $this->prepare_day( 'end', $data );
			}

			return array(
				'all_day' => $all_day,
				'start'   => $start,
				'end'     => $end,
			);
		}

		/**
		 * Sanitize repeat & Repeat end.
		 *
		 * @param  Array $data Data submitted by user.
		 * @return Array     Sanitized repeat & expiry value.
		 */
		private function prepare_repeat_expire( $data ) {
			$repeat = ! empty( $data['recurrence'] ) ? strtolower( trim( $data['recurrence'] ) ) : '';
			$expire = ! empty( $data['recurrence_end'] ) ? strtotime( trim( $data['recurrence_end'] ) ) : '';

			return Data_Validator::send_recurrance_after_validate( $repeat, $expire );
		}

		/**
		 * Prepares Dates to update. Verifies to update dates or not as well as sanitize Duration paramters.
		 *
		 * @param  array $duration Duration array to sanitize.
		 * @return array Duration to merge after sanitization.
		 */
		private function prepare_duration_for_update( $duration ) {
			$start   = $duration['start'];
			$end     = $duration['end'];
			$all_day = $duration['all_day'];

			$merge = array(
				'start'    => \Sugar_Calendar\Admin\Editor\Meta\sanitize_start( $start, $end, $all_day ),
				'start_tz' => '',
				'end'      => sanitize_end( $end, $start, $all_day ),
				'end_tz'   => '', // Time zones (empty for UTC by default).
				'all_day'  => \Sugar_Calendar\Admin\Editor\Meta\sanitize_all_day( $all_day, $start, $end ),
			);

			return $merge;
		}

		/**
		 * Prepares Recurrance to update. Verifies to update recurrance parameters or not as well as sanitize it.
		 *
		 * @param  array  $data Data submitted by user.
		 * @param  Object $event   Event object to update.
		 * @param  Array  $can_update_fields List of fields can be updated.
		 * @return array Recurrance to merge after sanitization.
		 */
		private function prepare_recurrance_for_update( $data, $event, $can_update_fields ) {
			$recurrence = array();

			if ( in_array( 'recurrence', $can_update_fields ) ) {
				$repeat = ! empty( $data['recurrence'] ) ? strtolower( trim( $data['recurrence'] ) ) : '';
			} else {
				$repeat = $event->recurrence;
			}

			if ( in_array( 'recurrence', $can_update_fields ) ) {
				$expire = ! empty( $data['recurrence_end'] ) ? strtotime( trim( $data['recurrence_end'] ) ) : '';
			} else {
				$expire = strtotime( $event->recurrence_end_date() );
			}

			$recurrence_data = Data_Validator::send_recurrance_after_validate( $repeat, $expire );

			$recurrence = array(
				'recurrence'          => $recurrence_data['repeat'],
				'recurrence_interval' => 0,
				'recurrence_count'    => 0,
				'recurrence_end'      => $recurrence_data['recurrence_end'],
				'recurrence_end_tz'   => '',
			);

			return $recurrence;
		}
	}
}

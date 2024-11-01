<?php
/**
 * This contains common functions used.
 *
 * @link              https://wisdmlabs.com
 * @since             1.0.0
 *
 *  @package Sugar_Calendar_WP_Import
 *  @subpackage Sugar_Calendar_WP_Import/includes
 */

namespace Sugar_Calendar_WP_Import;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;


/**
 * Used to get current date.
 *
 * @return date
 */
function get_current_date() {
	// Get the current time.
	$now = \sugar_calendar_get_request_time();

	// Get the current Year, Month, and Day, without any time.
	$date = gmdate(
		'Y-m-d H:i:s',
		mktime(
			0,
			0,
			0,
			gmdate( 'n', $now ),
			gmdate( 'j', $now ),
			gmdate( 'Y', $now )
		)
	);

	return $date;
}

/**
 * Original function - \Sugar_Calendar\Admin\Editor\Meta.
 * overridden due to has_end() function.
 * Sanitizes the end MySQL datetime, so that:
 *
 * - It does not end before it starts.
 * - It is at least as long as the minimum event duration (if exists).
 * - If the date is empty, the time can still be used.
 * - If both the date and the time are empty, it will equal the start.
 *
 * @since 2.0.5
 *
 * @param string $end     The end time, in MySQL format.
 * @param string $start   The start time, in MySQL format.
 * @param bool   $all_day True|False, whether the event is all-day.
 *
 * @return string
 */
function sanitize_end( $end = '', $start = '', $all_day = false ) {

	// Bail early if start or end are empty or malformed.
	if ( empty( $start ) || empty( $end ) || ! is_string( $start ) || ! is_string( $end ) ) {
		return $end;
	}

	// See if there a minimum duration to enforce.
	$minimum = \sugar_calendar_get_minimum_event_duration();

	// Convert to integers for faster comparisons.
	$start_int = strtotime( $start );
	$end_int   = strtotime( $end );

	// Calculate the end, based on a minimum duration (if set).
	$end_compare = ! empty( $minimum )
		? strtotime( '+' . $minimum, $end_int )
		: $end_int;

	// Check if the user attempted to set an end date and/or time.
	$has_end = true;

	// Bail if event duration exceeds the minimum (great!).
	if ( $end_compare > $start_int ) {
		return $end;
	}

	// ...or the user attempted an end date and this isn't an all-day event.
	if ( false === $all_day ) {
		// If there is a minimum, the new end is the start + the minimum.
		if ( ! empty( $minimum ) ) {
			$end_int = strtotime( '+' . $minimum, $start_int );

			// If there isn't a minimum, then the end needs to be rejected.
		} else {
			$has_end = false;
		}
	}

	// The above logic deterimned that the end needs to equal the start.
	// This is how events are allowed to have a start without a known end.
	if ( false === $has_end ) {
		$end_int = $start_int;
	}

	// All day events end at the final second.
	if ( true === $all_day ) {
		$end_int = mktime(
			23,
			59,
			59,
			gmdate( 'n', $end_int ),
			gmdate( 'j', $end_int ),
			gmdate( 'Y', $end_int )
		);
	}

	// Format.
	$retval = gmdate( 'Y-m-d H:i:s', $end_int );

	// Return the new end.
	return $retval;
}

/**
 * Get Base event properties.
 *
 * @param  Object  $event   Event object.
 * @param  Integer $post_id Post ID updated.
 * @return Array Event properties.
 */
function get_event_properties( $event, $post_id ) {
	$type = ! empty( $event->object_type )
			? $event->object_type
			: 'post';

	$object  = \get_post( $post_id );
	$title   = $object->post_title;
	$content = $object->post_content;
	$subtype = $object->post_type;
	$status  = $object->post_status;

	// Assemble the event properties.

	return array(
		'object_id'      => $post_id,
		'object_type'    => $type,
		'object_subtype' => $subtype,
		'title'          => $title,
		'content'        => $content,
		'status'         => $status,
	);
}

/**
 * Round minutes to the nearest interval of a DateTime object.
 *
 * @param \DateTime $date_time DateTime to sanitize.
 * @param int       $minute_interval Interval time to consider.
 * @return \DateTime Sanitized date.
 */
function round_to_nearest_minute_interval( \DateTime $date_time, $minute_interval = 5 ) {
	return $date_time->setTime(
		$date_time->format( 'H' ),
		round( $date_time->format( 'i' ) / $minute_interval ) * $minute_interval,
		0
	);
}

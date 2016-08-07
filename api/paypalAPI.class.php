<?php
/**
 * Public functions that allow interaction with PaypalDonationExtension.
 *
 * @file
 */
class paypal_api {

	/**
	 * Get all the donations made by the specified user.
	 * 
	 * @param int $userId ID of the user whose donations we want
	 * @return array
	 */
	public static function myDonations( $userId ) {
		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select(
			'donations',
			array(
				'user_id', 'firstname', 'lastname', 'address1', 'address2', 'city',
				'state', 'zip', 'country', 'email', 'comment', 'anonymous',
				'payment_date', 'payment_gross'
			),
			array( 'validated' => 1, 'user_id' => $userId ),
			__METHOD__,
			array( 'ORDER BY' => 'item_number DESC' )
		);
		$array = array();
		foreach ( $res as $row ) {
			$array[] = $row;
		}
		return $array;
	}

	/**
	 * Retrieves an array of the most recent donations.
	 *
	 * @param int $number The number of donations to retrieve, ignoring the
	 * first $start donations
	 * @param int $start Ignore this many first donations (OFFSET for the SQL query)
	 * @return array
	 */
	public static function donations( $number = 10, $start = 0 ) {
		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select(
			'donations',
			array(
				'user_id', 'firstname', 'lastname', 'city', 'state', 'zip',
				'country', 'comment', 'anonymous', 'payment_date', 'payment_gross'
			),
			array( 'validated' => 1 ),
			__METHOD__,
			array(
				'ORDER BY' => 'item_number DESC',
				'LIMIT' => $number,
				'OFFSET' => $start
			)
		);
		$array = array();
		foreach ( $res as $row ) {
			$array[] = $row;
		}
		return $array;
	}

	/**
	 * @param int $start Start date
	 * @param int $end End date
	 * @return int The total money recieved from donations between the dates specified.
	 */
	public static function totalDuring( $start, $end ) {
		$dbr = wfGetDB( DB_SLAVE );
		// @todo FIXME: possible SQL injection, should use MW's database wrapper
		// to correctly prefix the table name when DB table prefixes are used
		$sql = 'SELECT (SUM(mc_gross)-SUM(mc_fee)) FROM donations WHERE validated AND payment_date > "' . $start . '" AND payment_date < "' . $end . '";';
		$result = $dbr->query( $sql, __METHOD__ );
		// @todo CHECKME
		reset( $result );
		$result = current( $result );
		reset( $result );
		return current( $result );
	}
}

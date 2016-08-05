<?php

# public functions that allow interaction with PaypalDonationExtension.
require_once('common.php');

class paypal_api {

	# Returns an array of all donations made by the specified user.
	static function myDonations( $user ) {
		$sql = 'SELECT user_id, firstname, lastname, address1, address2, city, state, zip, country, email, comment, anonymous, payment_date, payment_gross FROM donations WHERE validated AND user_id="'.$user.'" ORDER BY item_number DESC';
		return usRunQuery( $sql );
	}

	# Retrieves an array of the most recent donations.
	# $number specifies the number of donations to retrieve, ignoring the
	# first $start donations
	static function donations( $number = 10, $start = 0 ) {
		$sql = 'SELECT user_id, firstname, lastname, city, state, zip, country, comment, anonymous, payment_date, payment_gross FROM donations WHERE validated ORDER BY item_number DESC LIMIT '.$start.','.$number;
		return usRunQuery( $sql );
	}

	# Returns the total money recieved from donations between the dates specified.
	static function totalDuring( $start, $end ) {
		$sql = 'SELECT (SUM(mc_gross)-SUM(mc_fee)) FROM donations WHERE validated  AND payment_date>"' . $start . '" AND payment_date<"' . $end . '";';
		$result = usRunQuery( $sql );
		reset( $result );
		$result = current( $result );
		reset( $result );
		return current( $result );
	}
}

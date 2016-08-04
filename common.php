<?php
require_once('paypal-api.php');
require_once('pp_finance-api.php');

################################################################################
# Common Functions used in PayPal scripts
################################################################################
class paypalCommon {
	# Business information to send to PayPal
	#
	# This information tells PayPal where to transfer money to, details of the
	#   calling website, the location of the IPN callback, and pages to
	#   redirect the user on completion/cancelation of payment.
	# Other fields govern the properties of the PayPal transaction process.
	static $paypal = array();

	# Application Settings
	static $setting = array();

	# HTTP POST Fields considdered part of the user details entered in the HTTP Form
	static $entryFields = array();

	# A list of fields to be sent to PayPal as part of the transfer request
	static $entryFieldstoPaypal = array();

	# Assigns values to the class variables.
	static function setVariables() {
		global $wgServer;

		# The web page called for users to donate, and for IPN callbacks
		//paypalCommon::$setting['url'] = "http://www.paypal.com/cgi-bin/webscr";
		//paypalCommon::$setting['url'] = "https://www.paypal.com/cgi-bin/webscr";
		paypalCommon::$setting['url'] = 'https://www.sandbox.paypal.com/cgi-bin/webscr';

		# The method used for sending HTTP confirmations to PayPal
		paypalCommon::$setting['post_method'] = 'fso'; // fso = fsockopen(); curl=curl command line libCurl=php compiled with libCurl support
		paypalCommon::$setting['curl_location'] = '/usr/bin/curl';

		# HTTP POST Fields considdered part of the user details entered in the HTTP form
		paypalCommon::$entryFields = array( 'firstname', 'lastname', 'address1', 'address2', 'city', 'state', 'zip', 'country', 'email', 'amount', 'comment', 'anonymous' );
		# A list of fields to be sent to PayPal as part of the transfer request
		paypalCommon::$entryFieldstoPaypal = array( 'firstname', 'lastname', 'address1', 'address2', 'city', 'state', 'zip', 'email', 'amount' );

		# Information to be passed to PayPal
		paypalCommon::$paypal['business'] = 'tp@pt.com';
		#paypalCommon::$paypal['image_url'] = 'image.jpg';
		paypalCommon::$paypal['currency_code'] = 'USD'; // [USD,GBP,JPY,CAD,EUR]
		paypalCommon::$paypal['lc'] = 'US';

		// Payment Page Settings
		paypalCommon::$paypal['no_note'] = '0'; // display comment 0=yes 1=no
		paypalCommon::$paypal['cn'] = wfMsgHtml( 'donate-comments-header' ); // Comment Header
		paypalCommon::$paypal['cbt'] = wfMsgExt( 'donate-return-text', 'parsemag' ); //'Return to LyricWiki >>'; // continue button text
		paypalCommon::$paypal['cs'] = ''; // background colour "" = white, 1 = black
		paypalCommon::$paypal['no_shipping'] = '1'; //show shipping address "" = yes, 1 = no

		# Path to ipn script
		paypalCommon::$paypal['notify_url'] = $wgServer . '/extensions/PayPal/ipn.php';

		# Unconfigurable options
		paypalCommon::$paypal['site_url'] = paypalCommon::pageLink( wfMsgForContent( 'mainpage' ) );
		paypalCommon::$paypal['return'] = paypalCommon::PageLink( 'Special:Donate', 'Success' ); // success return url
		paypalCommon::$paypal['cancel_return'] = paypalCommon::PageLink( 'Special:Donate', 'Fail' ); // cancel return url
		paypalCommon::$paypal['rm'] = '2'; // return method 1=GET 2=POST
		paypalCommon::$paypal['bn'] = 'toolkit-php';
		paypalCommon::$paypal['cmd'] = '_xclick';
	}

	# Processes a users details during the donation process, and stores them
	#   in the database
	static function processDonationRequest() {
		global $wgUser;
		# Data to add to database
		$data = array(
   			'firstname' => $_POST['firstname'],
   			'lastname' => $_POST['lastname'],
   			'address1' => $_POST['address1'],
   			'address2' => $_POST['address2'],
   			'city' => $_POST['city'],
   			'state' => $_POST['state'],
   			'zip' => $_POST['zip'],
   			'email' => $_POST['email'],
   			'amount' => $_POST['amount'],
   			'comment' => $_POST['comment'],
   			'anonymous' => ( $_POST['anonymous'] == 'Yes' ? 1 : 0 )
   		);

   		if( array_key_exists( 'item_number', $_POST ) ) {
   			$item_number = $_POST['item_number'];
   			# update database
			paypalCommon::updateDonation( $data, $item_number );
   		} else {
   			$data['user_id'] = paypalCommon::getUserID();
   			$data['request_time'] = date( 'c' );
   			# add to database
   			$item_number = paypalCommon::newDonation( $data );
   		}
   		return $item_number;
	}

	# Inserts into the database user details during the donation process
	# Upon completion of the transaction, details from PayPal will be added to
	#   this record
	static function newDonation( $data ) {
		$f = '';
		$v = '';
		foreach( $data as $field => $val ) {
			$f .= $field.', ';
			$v .= '"' . mysql_real_escape_string( $val ) . '"'.', ';
		}
		$f = substr( $f, 0, -2 );
		$v = substr( $v, 0, -2 );
		//$dbw = wfGetDB( DB_MASTER );
		//$res = $dbw->insert( 'donations', array( ), __METHOD__ );
		$sql = "INSERT INTO donations (".$f.") VALUES (".$v.");";
		runSQLStatement( $sql );
		return mysql_insert_id(); // database key
	}

	# Updates user details in the event the user alters their details
	#   during the donation process
	static function updateDonation( $data, $item_number ) {
		$f = '';
		foreach( $data as $field => $val ) {
			$f.= $field . '=' . '"' . mysql_real_escape_string( $val ) . '"' . ', ';
		}
		$f = substr( $f, 0, -2 );
		/*
		global $wgUser;
		$uid = ( $wgUser->getID() ? $wgUser->getID() : $wgUser->getName() );
		$dbw = wfGetDB( DB_MASTER );
		$dbw->update(
			'donations',
			array( ),
			array(
				'item_number' => $dbw->strencode( $item_number ),
				'user_id' => $uid
			),
			__METHOD__
		);
		*/
		$sql = "UPDATE donations SET ".$f." WHERE item_number=\"".mysql_real_escape_string($item_number)."\" AND user_id=\"".paypalCommon::getUserID()."\";";
		runSQLStatement( $sql );
	}

	# Returns a full url to the specified wiki page.
	# A subpage and/or GET query string may be specified
	static function pageLink( $page, $sub = '', $query = '' ) {
		return Title::newFromText( paypalCommon::pageName( $page, $sub ) )->getFullURL( $query );
	}

	# Joins the page and subpage on behalf of pageLink()
	private static function pageName( $page, $sub = '', $query = '' ) {
		if( strlen( $sub ) > 0 ) {
			$sub = '/' . $sub;
		}
		return $page . $sub;
	}

	# Instructs the brower to perform a redirect, and terminates execution of the
	#   PHP script
	static function redirect( $page ) {
		header( 'Location: '. $page );
		die();
	}

	# Returns the ID of the currently logged in user.
	# If no user is logged in, the users IP address is returned.
	function getUserID() {
		global $wgUser;
		return ( $wgUser->getID() ? $wgUser->getID() : $wgUser->getName() );
	}
}

################################################################################
# Paypal IPN (Instant Payment Notification) Class
################################################################################
#
# * Processes paypal notifications
# * Requests confirmation from paypal via http request
# * Stores result in database
#
class paypal_ipn {
	# Requests confirmation from paypal on payment receipt.
	#
	# Stores results in database.
	#
	# In the event the function is called more than once with a valid
	#   receipt, the function can be instructed to, or not to overwrite
	#   existing data in the database.
	static function processConfirmation( $overwrite = true ) {
		# Prepare data for database
		$data = array(
			'ipn_results' => paypal_ipn::getPostData( false ),
			'payment_date' => pp_date_to_db( $_POST['payment_date'] ),
			'payment_gross' => $_POST['payment_gross'],
			'payer_email' => $_POST['payer_email'],
			'mc_fee' => $_POST['mc_fee'],
			'mc_gross' => $_POST['mc_gross']
		);
		$item_number = $_POST['item_number'];

		# Query PayPal for validation
		$validated = ( preg_match( '/VERIFIED/', paypal_ipn::postData() ) ? true : false );

		# Format SQL statement
		/*
		$dbw = wfGetDB( DB_MASTER );
		$where = array();
		$where['item_number'] = $dbw->strencode( $item_number );
		$dbw->update(
			'donations',
			array(
				'validated' => 
			),
			array(
				$where
			),
			__METHOD__
		);
		*/
		$f = '';
		foreach( $data as $field => $val ) {
			$f.= $field . '=' . '"' . mysql_real_escape_string( $val ) . '"' . ', ';
		}
		$f = substr( $f, 0, -2 );
		$sql = "UPDATE donations SET " . $f;
		$sql .= ", validated='" . ( $validated ? '1' : '0' ) . "'";
		$sql .= " WHERE item_number=\"" . mysql_real_escape_string( $item_number ) . "\"";
		$sql .= ( $validated && $overwrite ? '' : " AND validated=0;" );
		runSQLStatement( $sql );

		# return data back to user
		$data['item_number'] = $item_number;
		$data['validated'] = $validated;
		return $data;
	}

	# Formats POST variables for relay to paypal server.
	# Optionally (via getPostData(False)) packages POST variables without the
	#   paypal confirmation request attached.
	# Returns a string of the format:
	#   var1=val1&var2=val2...
	static function getPostData( $validate = true ) {
		$postdata = '';
		foreach( $_POST as $i => $v ) {
			$postdata.= $i . '=' . urlencode( $v ) . '&';
		}
		if( $validate ) {
			$postdata.= 'cmd=_notify-validate';
		} else {
			$postdata = substr( $postdata, 0, -1 );
		}
		return $postdata;
	}

	# Takes POST variables, and sends them to paypal for confirmation.
	# Returns the paypal HTTP response
	# Three methods are available for sending the HTTPs request, configurable by
	#   paypalCommon::$setting[post_method].
	static function postData() {
		switch( paypalCommon::$setting['post_method'] ) {
			case 'libCurl':
				return paypal_ipn::libCurlPost();
			case 'curl':
				return paypal_ipn::curlPost();
			case 'fso':
			default:
				return paypal_ipn::fsockPost();
		}
	}

	# Sends confirmation request on behalf of postData() via cURL utility
	static function curlPost() {
		$url = paypalCommon::$setting['url'];
		$postdata = paypal_ipn::getPostData();
		$cmd = paypalCommon::$setting['curl_location'] . " -D - -d \"$postdata\" $url";
		exec( $cmd, $info );
		#var_dump($info);
		$info = implode( "\n", $info );
		return $info;
	}

	# Sends confirmation request on behalf of postData() via cURL library
	//posts transaction data using libCurl
	static function libCurlPost()  {
		$postdata = paypal_ipn::getPostData();
		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
		curl_setopt( $ch, CURLOPT_URL,paypalCommon::$setting['url'] );
		curl_setopt( $ch, CURLOPT_POST, 1 );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $postdata );
		// Start ob to prevent curl_exec from displaying stuff.
		ob_start();
		curl_exec( $ch );
		$info = ob_get_contents();
		curl_close( $ch );
		// End ob and erase contents.
		ob_end_clean();
		return $info;
	}

	# Sends confirmation request on behalf of postData() via PHP sockets
	//posts transaction data using fsockopen.
	static function fsockPost() {
		$web = parse_url( paypalCommon::$setting['url'] );
		$postdata = paypal_ipn::getPostData();

		if( $web['scheme'] == 'https' ) {
			$web['port'] = '443';
			$ssl = 'ssl://';
		} else {
			$web['port'] = '80';
		}
		$fp = @fsockopen( $ssl . $web['host'], $web['port'], $errnum, $errstr, 30 );
		if( !$fp ) {
			echo "$errnum: $errstr";
		} else {
			fputs( $fp, "POST {$web['path']} HTTP/1.1\r\n" );
			fputs( $fp, "Host: {$web['host']}\r\n" );
			fputs( $fp, "Content-type: application/x-www-form-urlencoded\r\n" );
			fputs( $fp, "Content-length: " . strlen( $postdata ) . "\r\n" );
			fputs( $fp, "Connection: close\r\n\r\n" );
			fputs( $fp, $postdata . "\r\n\r\n" );
			// Loop through the response from the server
			while( !feof( $fp ) ) {
				$info[]= @fgets( $fp, 1024 );
			}
			// close fp - we are done with it
			fclose( $fp );
			// break up results into a string
			$info = implode( '', $info );
		}
		return $info;
	}
}

################################################################################
# Various Functions
################################################################################

# Run a SQL query, and return the result
function usRunQuery( $sql ) {
	$dbr = wfGetDB( DB_SLAVE );
	$res = $dbr->query( $sql, __METHOD__ );
	$array = array();
	foreach ( $res as $row ) {
		$array[] = $item;
	}
	return $array;
}

# Run an SQL statement
function runSQLStatement( $sql ) {
	$dbw = wfGetDB( DB_MASTER );
	$res = $dbw->query( $sql, __METHOD__ );
	return $res;
}

# Convert a string representation retrieved from paypal
#   into a string representation that MySQL will accept
#
# No consideration has been made for adjusting timezones.
function pp_date_to_db( $date ) {
	$ftime = strptime( $date, '%H:%M:%S %b %d, %Y %Z' ); #09:02:47 Apr 05, 2007 PDT
	if( $ftime ) {
		$unxTimestamp = mktime( $ftime['tm_hour'], $ftime['tm_min'], $ftime['tm_sec'], 1, $ftime['tm_yday'] + 1, $ftime['tm_year'] + 1900 );
		return date( 'c', $unxTimestamp );
	}
	return $date;
}

# Converts a string representation of a date from MySQL int an strptime array
function db_date_to_array( $date ) {
	$ftime = strptime( $date, '%Y-%m-%d %H:%M:%S' ); #09:02:47 Apr 05, 2007 PDT
	if( $ftime ) {
		$ftime['tm_yday']++;
		$ftime['tm_mon']++;
		$ftime['tm_year'] += 1900;
		return $ftime;
	}
	return $date;
}

# Converts strptime array (from db_date_to_array() for example) into a
#   unix timestamp.
function array_date_to_unix( $ftime ) {
	return mktime( $ftime['tm_hour'], $ftime['tm_min'], $ftime['tm_sec'], 1 , $ftime['tm_yday'], $ftime['tm_year'] );
}

# Converts a string representation of a date from MySQL into a unix timestamp
function db_date_to_unix( $date ) {
	return array_date_to_unix( db_date_to_array( $date ) );
}

# Converts a unix timestamp into a string representation of a date that MySQL
#   will accept.
function unix_date_to_db( $date ) {
	return strftime( '%Y-%m-%d %H:%M:%S', $date );
}
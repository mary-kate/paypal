<?php
/**
 * Special:Donate
 *
 * @file
 * @ingroup Extensions
 */

class SpecialDonate extends SpecialPage {

	/**
	 * Constructor function called by MediaWiki
	 */
	public function __construct() {
		parent::__construct( 'Donate' );
	}

	/**
	 * Entry point for rendering the special page
	 * Selects the correct subpage and calls the function for it
	 *
	 * @param $par Mixed: subpage name or null
	 */
	public function execute( $par ) {
		global $wgOut;
		$this->setHeaders();

		$wgOut->addWikiText( $this->steps( $par ) . '<br />' );

		switch( $par ) {
			case '':
			case 'Details':
				$this->detailsPage();
				break;
			case 'Confirm':
				$this->confirmPage();
				break;
			case 'Success':
				$this->successPage();
				break;
			case 'Fail':
				$this->failPage();
				break;
		}
	}

	/**
	 * Display a form asking the user for their details, the amount to donate
	 * and a comment to accompany the donation.
	 */
	function detailsPage() {
		global $wgUser, $wgOut, $wgRequest;

		$wgOut->setPageTitle( wfMsgHtml( 'donate-detailspage-title' ) );

		$anonymous = ( strlen( $_POST['anonymous'] ) ? $_POST['anonymous'] : 'N' );

		$text = '<form method="post" action="' . paypalCommon::PageLink( 'Special:Donate', 'Confirm' ) . '">' . "\n";
		$text .= '<table style="border: 2px solid #878da4; background-color:#f0f2f6;" cellpadding="2">
			<tr>
				<td>' . wfMsgHtml( 'donate-first-name' ) . '</td>
				<td><input name="firstname" type="text" id="firstname" size="20" value="' . htmlspecialchars( $_POST['firstname'], ENT_QUOTES ) . '" />
				<tr>
					<td>
				<tr>
					<td>' . wfMsgHtml( 'donate-last-name' ) . '</td>
					<td><input name="lastname" type="text" id="lastname" size="20" value="' . htmlspecialchars( $_POST['lastname'], ENT_QUOTES ) . '" />
					<tr><td>
				<tr>
					<td>' . wfMsgHtml( 'donate-address-1' ) . '</td>
					<td><input name="address1" type="text" id="address1" size="20" value="' . htmlspecialchars( $_POST['address1'], ENT_QUOTES ) . '" />
					<tr><td>
				<tr>
					<td>' . wfMsgHtml( 'donate-address-2' ) . '</td>
					<td><input name="address2" type="text" id="address2" size="20" value="' . htmlspecialchars( $_POST['address2'], ENT_QUOTES ) . '" />
					<tr><td>
				<tr>
					<td>' . wfMsgHtml( 'donate-city' ) . '</td>
					<td><input name="city" type="text" size="20" value="' . htmlspecialchars( $_POST['city'], ENT_QUOTES ) . '" />
					<tr><td>
				<tr>
					<td>' . wfMsgHtml( 'donate-state' ) . '</td>
					<td><input name="state" type="text" size="20" value="' . htmlspecialchars( $_POST['state'], ENT_QUOTES ) . '" />
					<tr><td>
				<tr>
					<td>' . wfMsgHtml( 'donate-country' ) . '</td>
					<td><input name="country" type="text" size="20" value="' . htmlspecialchars( $_POST['country'], ENT_QUOTES ) . '" />
					<tr><td>
				<tr>
					<td>' . wfMsgHtml( 'donate-zip' ) . '</td>
					<td><input name="zip" type="text" size="20" value="' . htmlspecialchars( $_POST['zip'], ENT_QUOTES ) .'" />
					<tr><td>
				<tr>
					<td>' . wfMsgHtml( 'donate-email' ) . '</td>
					<td><input name="email" type="text" id="email" size="20" value="' . htmlspecialchars( $_POST['email'], ENT_QUOTES ) . '" />
					<tr><td>
				<tr>
					<td>' . wfMsgHtml( 'donate-amount' ) . '</td>
					<td><input name="amount" type="text" id="amount" size="5" value="' . htmlspecialchars( $_POST['amount'], ENT_QUOTES ) . '" />
					<tr><td>
				<tr>
					<td>' . wfMsgHtml( 'donate-donors-comment' ) . '</td>
					<td><input name="comment" type="text" id="comment" size="40" value="' . htmlspecialchars( $_POST['comment'], ENT_QUOTES ) . '" />
					<tr><td>
				<tr>
					<td>' . wfMsgHtml( 'donate-anonymous' ) . '</td>
					<td>
						<select name="anonymous">
							<option value="Yes"' . ( $anonymous == 'Yes' ? ' selected="selected"' : '' ) .  '>' . wfMsgHtml( 'donate-yes' ) . '</option>
							<option value="No"' . ( $anonymous == 'No' ? ' selected="selected"' : '' ) . '>' . wfMsgHtml( 'donate-no' ) . '</option>
						</select></p>
					</td>
				</tr>
				<tr>
					<td colspan="2" align="center">
						<input type="submit" name="submit" value="' . wfMsgHtml( 'donate-submit-details' ) . '" />' . "\n";
		if( array_key_exists( 'item_number', $_POST ) ) {
			$text .= Xml::hidden( 'item_number', $_POST['item_number'] );
		}
		#$text .= ( array_key_exists( 'item_number', $_POST ) ? '<input name="item_number" type="hidden" value="'.htmlspecialchars($_POST[item_number], ENT_QUOTES).'">'."\n":'');
		$text .= '<input type="reset" name="reset">' . "\n";
		$text .= '</td></tr></table></form>';
		$wgOut->addHTML( $text );
	}

	/**
	 * Check the details are valid
	 * Display the details back to the user for review
	 * Give the user the opportunity to go back and change the details
	 * Allow the user to proceed to PayPal for checkout
	 */
	function confirmPage() {
		global $wgOut;

		// Check details are correct
		$validated = $this->checkDetails();

		if( $validated ) {
			$item_number = paypalCommon::processDonationRequest();
		} else {
			$item_number = null;
		}

		$wgOut->setPageTitle( wfMsgHtml( 'donate-confirmdetails-title' ) );

		// List details for user
		$text = wfMsgHtml( 'donate-entered-details' ) . "\n{|\n";
		$text .= "|-\n!" . wfMsgHtml( 'donate-field' ) . "\n!" . wfMsgHtml( 'donate-value' ) . "\n";
		foreach( paypalCommon::$entryFields as $var ) {
			$text .= "|-\n";
			if( strlen( $_POST[$var] ) > 0 ) {
				$val = $_POST[$var];
				if( $var == 'amount' ) {
					$val = number_format( $val, 2 );
				}
				$text .= '|' . $var . "\n|" . $val . "\n";
			}
		}
		$text .= '|}';
		$wgOut->addWikiText( $text );

		if( $validated ) {
			// Continue to PayPal form
			$wgOut->addHTML(
				Xml::openElement( 'form', array( 'method' => 'post', 'action' => 'https://www.sandbox.paypal.com/cgi-bin/webscr' ) ) .
				$this->formInputs( paypalCommon::$entryFieldstoPaypal ) .
				$this->formInputs( null, paypalCommon::$paypal ) .
				Xml::hidden( 'item_name', 'LyricWiki Donation' ) . // @todo FIXME
				Xml::hidden( 'item_number', $item_number ) .
				Xml::submitButton( wfMsgHtml( 'donate-submit' ) ) .
				Xml::closeElement( 'form' )
			);
		}

		// Back to details form
		$wgOut->addHTML(
			Xml::openElement( 'form', array( 'method' => 'post', 'action' => SpecialPage::getTitleFor( 'Donate', 'Details' )->getLocalURL() ) ) .
			$this->formInputs() . ( ( $item_number ) ? Xml::hidden( 'item_number', $item_number ) : '' ) .
			Xml::submitButton( wfMsgHtml( 'donate-returntodetails' ) ) .
			Xml::closeElement( 'form' )
		);
	}

	/**
	 * Process the payement receipt, thank the user,
	 * and present them with a few links
	 * Will suggest a few possibilities in the case of failure.
	 * Checks the payment was sucessfull via a call to PayPal. using the 
	 * same method as ipn.php.
	 */
	function successPage() {
		global $wgOut;
		$wgOut->setPageTitle( wfMsgHtml( 'donate-finish-pagetitle' ) );
		$data = paypal_ipn::processConfirmation( false ); // false because ipn trusted more than return page
		if( $data['validated'] ) {
			$wgOut->addWikiMsg( 'donation-thankyou' );
		} else {
			$wgOut->addWikiMsg( 'donation-receipt-error' );
		}
	}

	/**
	 * Informs the user that the payment was cancelled (returned from PayPal)
	 */
	function failPage() {
		global $wgOut;
		$wgOut->setPageTitle( wfMsgHtml( 'donate-finish-pagetitle' ) );
		$wgOut->addWikiMsg( 'donation-cancel' );
	}

	/**
	 * Checks the validity of information entered by the user, before
	 * proceeding to PayPal.
	 */
	function checkDetails() {
		global $wgOut, $wgRequest;
		$problems = array();

		if( !is_numeric( $wgRequest->getInt( 'amount' ) ) ) {
			$problems[] = wfMsgHtml( 'donate-problem-amount' );
		}

		// Signal and exit if OK
		if( count( $problems ) == 0 ) {
			return true;
		}

		// Inform user of problems
		$wgOut->addWikiMsg( 'donate-problems' );
		foreach( $problems as $problem ) {
			$wgOut->addWikiText( '*' . $problem );
		}
		$wgOut->addWikiText( "\n" );
		return false;
	}

	/**
	 * Return a string of hidden form inputs
	 *
	 * @param $fields Array: an array of field names to be included from $source
	 * @param $source Array: an array to take values from
	 * If neither is defined, $fields becomes the user entry fields,
	 * and $source becomes POST variables
	 * if $source alone is undefined, source becomes POST variables
	 * if $fields is undefined, all fields is $source are used
	 */
	function formInputs( $fields = null, $source = null ) {
		// If nothing specified, use standard form fields
		if( $fields == null && $source == null ) {
			$fields = paypalCommon::$entryFields;
		}
		// If fields specified without source, assume post is source
		if( $source == null ) {
			$source = $_POST;
		}
		// If source selected without fields, use all fields in source
		if( $fields == null ) {
			$fields = array_keys( $source );
		}

		// Output each field
		$str = '';
		foreach( $fields as $var ) {
			$val = $source[$var];
			if( strlen( $val ) > 0 ) {
				$str.= Xml::hidden( $var, $val );
				#$str.= '<input type="hidden" name="' . $var . '" value="' . htmlspecialchars( $val, ENT_QUOTES ) . '">' . "\n";
			}
		}
		return $str;
	}

	/**
	 * Shows a table with the steps in donating, underlining the current step
	 */
	function steps( $step ) {
		$steps = array( 'Enter Details', 'Confirm Details', 'Paypal Payment', 'Finish' );
		$bar = array( '', '', '', '' );
		$barstyle = 'style="background-color:#dc8d84; padding: 2px;"|';
		switch( $step ) {
			case '':
			case 'Details':
				$bar[0] = $barstyle;
				break;
			case 'Confirm':
				$bar[1] = $barstyle;
				break;
			case 'Success':
				$bar[3] = $barstyle;
				break;
			case 'Fail':
				$bar[3] = $barstyle;
				break;
		}
		$text = '{| style="border: 2px solid #878da4; background-color:#f0f2f6;" align=center' . "\n";
		$text .= '|-' . "\n";
		foreach( $steps as $step ) {
			$text .= '|style="padding:12px; padding-bottom:0;"|' . $step . "\n";
		}
		$text .= '|-' . "\n";
		foreach( $bar as $step ) {
			$text .= '|' . $step . "\n";
		}
		$text .= '|}';
		return $text;
	}

}
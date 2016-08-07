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
	 * @param string|null $par Subpage name or null
	 */
	public function execute( $par ) {
		$this->setHeaders();

		$this->getOutput()->addWikiText( $this->steps( $par ) . '<br />' );

		switch ( $par ) {
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
	private function detailsPage() {
		$out = $this->getOutput();
		$request = $this->getRequest();

		$out->setPageTitle( $this->msg( 'donate-detailspage-title' ) );

		$anonymous = ( strlen( $request->getVal( 'anonymous' ) ) ? $request->getVal( 'anonymous' ) : 'N' );

		$text = '<form method="post" action="' . paypalCommon::PageLink( 'Special:Donate', 'Confirm' ) . '">' . "\n";
		$text .= '<table style="border: 2px solid #878da4; background-color:#f0f2f6;" cellpadding="2">
			<tr>
				<td>' . $this->msg( 'donate-first-name' )->text() . '</td>
				<td><input name="firstname" type="text" id="firstname" size="20" value="' . htmlspecialchars( $request->getVal( 'firstname' ), ENT_QUOTES ) . '" />
				<tr>
					<td>
				<tr>
					<td>' . $this->msg( 'donate-last-name' )->text() . '</td>
					<td><input name="lastname" type="text" id="lastname" size="20" value="' . htmlspecialchars( $request->getVal( 'lastname' ), ENT_QUOTES ) . '" />
					<tr><td>
				<tr>
					<td>' . $this->msg( 'donate-address-1' )->text() . '</td>
					<td><input name="address1" type="text" id="address1" size="20" value="' . htmlspecialchars( $request->getVal( 'address1' ), ENT_QUOTES ) . '" />
					<tr><td>
				<tr>
					<td>' . $this->msg( 'donate-address-2' )->text() . '</td>
					<td><input name="address2" type="text" id="address2" size="20" value="' . htmlspecialchars( $request->getVal( 'address2' ), ENT_QUOTES ) . '" />
					<tr><td>
				<tr>
					<td>' . $this->msg( 'donate-city' )->text() . '</td>
					<td><input name="city" type="text" size="20" value="' . htmlspecialchars( $request->getVal( 'city' ), ENT_QUOTES ) . '" />
					<tr><td>
				<tr>
					<td>' . $this->msg( 'donate-state' )->text() . '</td>
					<td><input name="state" type="text" size="20" value="' . htmlspecialchars( $request->getVal( 'state' ), ENT_QUOTES ) . '" />
					<tr><td>
				<tr>
					<td>' . $this->msg( 'donate-country' )->text() . '</td>
					<td><input name="country" type="text" size="20" value="' . htmlspecialchars( $request->getVal( 'country' ), ENT_QUOTES ) . '" />
					<tr><td>
				<tr>
					<td>' . $this->msg( 'donate-zip' )->text() . '</td>
					<td><input name="zip" type="text" size="20" value="' . htmlspecialchars( $request->getVal( 'zip' ), ENT_QUOTES ) .'" />
					<tr><td>
				<tr>
					<td>' . $this->msg( 'donate-email' )->text() . '</td>
					<td><input name="email" type="text" id="email" size="20" value="' . htmlspecialchars( $request->getVal( 'email' ), ENT_QUOTES ) . '" />
					<tr><td>
				<tr>
					<td>' . $this->msg( 'donate-amount' )->text() . '</td>
					<td><input name="amount" type="text" id="amount" size="5" value="' . htmlspecialchars( $request->getVal( 'amount' ), ENT_QUOTES ) . '" />
					<tr><td>
				<tr>
					<td>' . $this->msg( 'donate-donors-comment' )->text() . '</td>
					<td><input name="comment" type="text" id="comment" size="40" value="' . htmlspecialchars( $request->getVal( 'comment' ), ENT_QUOTES ) . '" />
					<tr><td>
				<tr>
					<td>' . $this->msg( 'donate-anonymous' )->text() . '</td>
					<td>
						<select name="anonymous">
							<option value="Yes"' . ( $anonymous == 'Yes' ? ' selected="selected"' : '' ) .  '>' . $this->msg( 'donate-yes' )->text() . '</option>
							<option value="No"' . ( $anonymous == 'No' ? ' selected="selected"' : '' ) . '>' . $this->msg( 'donate-no' )->text() . '</option>
						</select></p>
					</td>
				</tr>
				<tr>
					<td colspan="2" align="center">
						<input type="submit" name="submit" value="' . $this->msg( 'donate-submit-details' )->text() . '" />' . "\n";
		if ( $this->getRequest()->getVal( 'item_number' ) ) {
			$text .= Html::hidden( 'item_number', $this->getRequest()->getVal( 'item_number' ) );
		}
		$text .= '<input type="reset" name="reset">' . "\n";
		$text .= '</td></tr></table></form>';
		$out->addHTML( $text );
	}

	/**
	 * Check the details are valid
	 * Display the details back to the user for review
	 * Give the user the opportunity to go back and change the details
	 * Allow the user to proceed to PayPal for checkout
	 */
	private function confirmPage() {
		$lang = $this->getLanguage();
		$out = $this->getOutput();

		// Check details are correct
		$validated = $this->checkDetails();

		if( $validated ) {
			$item_number = paypalCommon::processDonationRequest();
		} else {
			$item_number = null;
		}

		$out->setPageTitle( $this->msg( 'donate-confirmdetails-title' ) );

		// List details for user
		$text = $this->msg( 'donate-entered-details' )->text() . "\n{|\n";
		$text .= "|-\n!" . $this->msg( 'donate-field' )->text() . "\n!" . $this->msg( 'donate-value' )->text() . "\n";
		foreach ( paypalCommon::$entryFields as $var ) {
			$text .= "|-\n";
			if ( strlen( $_POST[$var] ) > 0 ) {
				$val = $_POST[$var];
				if ( $var == 'amount' ) {
					$val = $lang->formatNum( $val );
				}
				$text .= '|' . $var . "\n|" . $val . "\n";
			}
		}
		$text .= '|}';
		$out->addWikiText( $text );

		if ( $validated ) {
			// Continue to PayPal form
			$out->addHTML(
				// @todo FIXME: pretty sure this should be using paypalCommon::$setting['url'] ...
				Xml::openElement( 'form', array( 'method' => 'post', 'action' => 'https://www.sandbox.paypal.com/cgi-bin/webscr' ) ) .
				$this->formInputs( paypalCommon::$entryFieldstoPaypal ) .
				$this->formInputs( null, paypalCommon::$paypal ) .
				Html::hidden( 'item_name', 'LyricWiki Donation' ) . // @todo FIXME
				Html::hidden( 'item_number', $item_number ) .
				Xml::submitButton( $this->msg( 'donate-submit' )->text() ) .
				Xml::closeElement( 'form' )
			);
		}

		// Back to details form
		$out->addHTML(
			Xml::openElement( 'form', array( 'method' => 'post', 'action' => SpecialPage::getTitleFor( 'Donate', 'Details' )->getLocalURL() ) ) .
			$this->formInputs() . ( ( $item_number ) ? Html::hidden( 'item_number', $item_number ) : '' ) .
			Xml::submitButton( $this->msg( 'donate-returntodetails' )->text() ) .
			Xml::closeElement( 'form' )
		);
	}

	/**
	 * Process the payment receipt, thank the user, and present them with a few links.
	 * Will suggest a few possibilities in the case of failure.
	 * Checks the payment was successful via a call to PayPal using the 
	 * same method as paypalInstantPaymentNotification.php.
	 */
	private function successPage() {
		$out = $this->getOutput();
		$out->setPageTitle( $this->msg( 'donate-finish-pagetitle' ) );
		$data = paypal_ipn::processConfirmation( false ); // false because ipn trusted more than return page
		if ( $data['validated'] ) {
			$out->addWikiMsg( 'donation-thankyou' );
		} else {
			$out->addWikiMsg( 'donation-receipt-error' );
		}
	}

	/**
	 * Informs the user that the payment was cancelled (returned from PayPal)
	 */
	private function failPage() {
		$out = $this->getOutput();
		$out->setPageTitle( $this->msg( 'donate-finish-pagetitle' ) );
		$out->addWikiMsg( 'donation-cancel' );
	}

	/**
	 * Checks the validity of information entered by the user, before
	 * proceeding to PayPal.
	 */
	private function checkDetails() {
		$out = $this->getOutput();
		$problems = array();

		if ( !is_numeric( $this->getRequest()->getInt( 'amount' ) ) ) {
			$problems[] = $this->msg( 'donate-problem-amount' )->text();
		}

		// Signal and exit if OK
		if ( count( $problems ) == 0 ) {
			return true;
		}

		// Inform user of problems
		$out->addWikiMsg( 'donate-problems' );
		foreach ( $problems as $problem ) {
			$out->addWikiText( '*' . $problem );
		}
		$out->addWikiText( "\n" );
		return false;
	}

	/**
	 * Return a string of hidden form inputs
	 *
	 * @param array $fields An array of field names to be included from $source
	 * @param array $source An array to take values from
	 * If neither is defined, $fields becomes the user entry fields,
	 * and $source becomes POST variables
	 * if $source alone is undefined, source becomes POST variables
	 * if $fields is undefined, all fields is $source are used
	 */
	function formInputs( $fields = null, $source = null ) {
		// If nothing specified, use standard form fields
		if ( $fields == null && $source == null ) {
			$fields = paypalCommon::$entryFields;
		}
		// If fields specified without source, assume post is source
		if ( $source == null ) {
			$source = $_POST;
		}
		// If source selected without fields, use all fields in source
		if ( $fields == null ) {
			$fields = array_keys( $source );
		}

		// Output each field
		$str = '';
		foreach ( $fields as $var ) {
			$val = $source[$var];
			if ( strlen( $val ) > 0 ) {
				$str.= Html::hidden( $var, $val );
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
		switch ( $step ) {
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
		foreach ( $steps as $step ) {
			$text .= '|style="padding:12px; padding-bottom:0;"|' . $step . "\n";
		}
		$text .= '|-' . "\n";
		foreach ( $bar as $step ) {
			$text .= '|' . $step . "\n";
		}
		$text .= '|}';
		return $text;
	}

}

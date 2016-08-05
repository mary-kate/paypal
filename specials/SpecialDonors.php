<?php
/**
 * Adds a special page where visitors may view a list of donors and
 * their contributions, or to view their own contributions.
 *
 * @file
 * @ingroup Extensions
 */
class SpecialDonors extends SpecialPage {

	// The number of donations to show per page.
	var $num_donors = 10;

	/**
	 * Constructor function called by MediaWiki
	 */
	public function __construct() {
		parent::__construct( 'Donors' );
	}

	/**
	 * Entry point for rendering the special page
	 *
	 * @param $par Mixed: parameter passed to the special page, if it's 'Me',
	 * show only the current user's donations, otherwise show the most
	 * recent contributions
	 */
	public function execute( $par ) {
		global $wgOut, $wgUser;

		$this->setHeaders();
		$wgOut->setPageTitle( wfMsgHtml( 'donors' ) );

		// Add links allowing user to switch views between 
		// their donations and all donations
		if( strtolower( $par ) == 'me' ) {
			$wgOut->addWikiText( '[[Special:Donors/all|' . wfMsgHtml( 'donors-all-donations' ) . ']]' );
		} else {
			$wgOut->addWikiText( '[[Special:Donors/me|' . wfMsgHtml( 'donors-my-donations' ) . ']]' );
		}

		// Retrieve a list of donations
		$fields = array();
		$where = array();
		$additional = array();
		$user = ( $wgUser->getID() ? $wgUser->getID() : $wgUser->getName() );
		$dbr = wfGetDB( DB_SLAVE );

		if( strtolower( $par ) == 'me' ) {
			$fields = array(
				'user_id', 'firstname', 'lastname', 'address1',
				'address2', 'city', 'state', 'zip', 'country', 'email',
				'comment', 'anonymous', 'payment_date', 'payment_gross'
			);
			$where = array( 'user_id="' . $user . '" AND validated' ); // switched order here
			$additional = array( 'ORDER BY' => 'item_number DESC' );
		} else {
			$fields = array(
				'user_id', 'firstname', 'lastname', 'city', 'state', 'zip',
				'country', 'email', 'comment', 'anonymous', 'payment_date', 'payment_gross'
			):
			$where = array( 'validated' );
			$additional = array(
				'ORDER BY' => 'item_number DESC',
				'LIMIT' => $this->num_donors,
				// retrieves the start variable, allowing the user to click "More" and view
				// a further set of donations
				'OFFSET' => $dbr->strencode( array_key_exists( 'start', $_GET ) ? $_GET['start'] : 0 );
			);
		}

		// Query the DB with the appropriate parameters
		$res = $dbr->select(
			'donations',
			$fields,
			$where,
			__METHOD__,
			$additional
		);
		$donations = array();
		foreach( $res as $item ) {
			$donations[] = $item;
		}
		// List the donations to the user
		$this->listDonations( $donations, strtolower( $par ) != 'me' );
	}

	/**
	 * List all donations in the provided array
	 *
	 * @param $more Boolean: if true, then add a more link to the bottom of the table
	 */
	private function listDonations( $donations, $more = true ) {
		global $wgOut;

		// Make sure there is something to show
		if( count( $donations ) == 0 ) {
			$wgOut->addHTML( wfMsgHtml( 'donors-no-donations' ) );
			return;
		}

		$text = "{| border=1\n";
		$text .= "|-\n!" . wfMsgHtml( 'donors-date' ) . "\n!" . wfMsgHtml( 'donors-amount' ) .
				"\n!" . wfMsgHtml( 'donors-user' ) . "\n!" . wfMsgHtml( 'donors-details' ) . "\n";
		foreach( $donations as $donation ) {
			$amount = '$' . number_format( $donation->payment_gross, 2 ) . '&nbsp;&nbsp;&nbsp;';
			if( $donation->anonymous ) {
				$user = wfMsgHtml( 'donors-anonymous' );
			} else {
				// Get the username
				$user = User::whoIs( $donation->user_id );
				// If !$user, then $donation->user_id must be an IP address
				if( !$user ) {
					$user = $donation->user_id;
				}
				$user = '[[User:' . $user . '|' . $user . ']]';
			}

			// Format a neat name
			if( strlen( $donation->lastname ) == 0 || strlen( $donation->firstname ) == 0 ) {
				$name = $donation->lastname . $donation->firstname;
			} else {
				$name = $donation->lastname . ', ' . $donation->firstname;
			}

			// Default location of none specified
			$location = wfMsgHtml( 'donors-default-location' );
			// If location information is specified, build a string showing it
			if( !$donation->city && !$donation->state && !$donation->zip && !$donation->country ) {
				echo '';
			} else {
				$locations = array(
					$donation->city,
					$donation->state,
					$donation->zip,
					$donation->country
				);
				$location = '';
				foreach( $locations as $a ) {
					if( $a ) {
						$location.= $a . ', ';
					}
				}
				$location = substr( $location, 0, -2 );
			}

			# Add formatted info to output
			$text .= "|-\n|valign=top|" . $donation->payment_date . "\n";
			$text .= '|align="right" valign="top"|' . $amount . "\n";
			$text .= '|valign="top"|' . $user . "\n";
			$text .= '|' . ( !$donation->anonymous && $name ? $name . '<br />' : '' );
			$text .=  wfMsgHtml( 'donors-from', $location ) . '<br />';
			$text .= ( $donation->comment ? wfMsgHtml( 'donors-comment', $donation->comment ) : '' ) . "\n";
		}

		$text .= '|}';
		$wgOut->addWikiText( $text );

		// Provide the user with a link to view more donations.
		if( $more && count( $donations ) == $this->num_donors ) {
			$wgOut->addHTML(
				'<a href="' . paypalCommon::pageLink( 'Special:Donors', '', 'start=' . ( $start + $this->num_donors ) ) . '">' .
				wfMsgHtml( 'donors-more' ) . '</a>'
			);
		}
	}

}

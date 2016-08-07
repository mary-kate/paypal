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
	 * @param mixed|null $par Parameter passed to the special page; if it's 'Me',
	 * show only the current user's donations, otherwise show the most
	 * recent contributions
	 */
	public function execute( $par ) {
		$out = $this->getOutput();
		$user = $this->getUser();

		$this->setHeaders();
		$out->setPageTitle( $this->msg( 'donors' ) );

		// Add links allowing user to switch views between 
		// their donations and all donations
		if ( strtolower( $par ) == 'me' ) {
			$out->addWikiText( '[[Special:Donors/all|' . $this->msg( 'donors-all-donations' )->text() . ']]' );
		} else {
			$out->addWikiText( '[[Special:Donors/me|' . $this->msg( 'donors-my-donations' )->text() . ']]' );
		}

		// Retrieve a list of donations
		$fields = array();
		$where = array();
		$additional = array();
		$user = ( $user->getId() ? $user->getId() : $user->getName() );
		$dbr = wfGetDB( DB_SLAVE );

		if ( strtolower( $par ) == 'me' ) {
			$fields = array(
				'user_id', 'firstname', 'lastname', 'address1',
				'address2', 'city', 'state', 'zip', 'country', 'email',
				'comment', 'anonymous', 'payment_date', 'payment_gross'
			);
			$where = array(
				'user_id' => $user,
				'validated' => 1
			);
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
				'OFFSET' => $this->getRequest()->getInt( 'start', 0 );
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
		foreach ( $res as $item ) {
			$donations[] = $item;
		}

		// List the donations to the user
		$this->listDonations( $donations, strtolower( $par ) != 'me' );
	}

	/**
	 * List all donations in the provided array
	 *
	 * @param array $donations
	 * @param bool $more If true, then add a more link to the bottom of the table
	 */
	private function listDonations( $donations, $more = true ) {
		$lang = $this->getLanguage();
		$out = $this->getOutput();

		// Make sure there is something to show
		if ( count( $donations ) == 0 ) {
			$out->addHTML( $this->msg( 'donors-no-donations' )->text() );
			return;
		}

		$text = "{| border=1\n";
		$text .= "|-\n!" . $this->msg( 'donors-date' ) . "\n!" . $this->msg( 'donors-amount' )->text() .
				"\n!" . $this->msg( 'donors-user' ) . "\n!" . $this->msg( 'donors-details' )->text() . "\n";
		foreach ( $donations as $donation ) {
			// originally used number_format() with 2 decimals
			$amount = '$' . $lang->formatNum( $donation->payment_gross ) . '&nbsp;&nbsp;&nbsp;';
			if ( $donation->anonymous ) {
				$user = $this->msg( 'donors-anonymous' )->text();
			} else {
				// Get the username
				$user = User::whoIs( $donation->user_id );
				// If !$user, then $donation->user_id must be an IP address
				if ( !$user ) {
					$user = $donation->user_id;
				}
				$user = '[[User:' . $user . '|' . $user . ']]';
			}

			// Format a neat name
			if ( strlen( $donation->lastname ) == 0 || strlen( $donation->firstname ) == 0 ) {
				$name = $donation->lastname . $donation->firstname;
			} else {
				$name = $donation->lastname . ', ' . $donation->firstname;
			}

			// Default location of none specified
			$location = $this->msg( 'donors-default-location' )->text();
			// If location information is specified, build a string showing it
			if ( !$donation->city && !$donation->state && !$donation->zip && !$donation->country ) {
				//echo '';
			} else {
				$locations = array(
					$donation->city,
					$donation->state,
					$donation->zip,
					$donation->country
				);
				$location = '';
				foreach ( $locations as $a ) {
					if ( $a ) {
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
			$text .=  $this->msg( 'donors-from', $location )->text() . '<br />';
			$text .= ( $donation->comment ? $this->msg( 'donors-comment', $donation->comment )->text() : '' ) . "\n";
		}

		$text .= '|}';
		$out->addWikiText( $text );

		// Provide the user with a link to view more donations.
		if ( $more && count( $donations ) == $this->num_donors ) {
			$out->addHTML(
				Linker::linkKnown(
					$this->getPageTitle(),
					$this->msg( 'donors-more' )->text(),
					array(),
					array( 'start' => ( $start + $this->num_donors ) )
				)
			);
		}
	}

}

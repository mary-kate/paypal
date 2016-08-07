<?php
/**
 * Provides the Special:Finance page.
 *
 * @file
 * @ingroup Extensions
 */

class SpecialFinance extends SpecialPage {

	/**
	 * Constructor function called by MediaWiki
	 */
	public function __construct() {
		parent::__construct( 'Finance', 'finance-edit' );
	}

	/**
	 * Entry point for rendering the special page
	 * Display a subpage for periods or items
	 *
	 * @param string|null $par Parameter(s) passed to the page, if any
	 */
	public function execute( $par ) {
		$this->setHeaders();

		// Make sure the user is allowed to view this page
		if ( !$this->getUser()->isAllowed( 'finance-edit' ) ) {
			throw new PermissionsError( 'finance-edit' );
		}

		// Display navigation links
		$this->getOutput()->addWikiText( '[[Special:Finance/Periods|Periods]] - [[Special:Finance/Items|Items]]' );
		switch ( $par ) {
			case 'Items':
				$this->pageItems();
				break;
			case 'Periods':
			default:
				$this->pagePeriods();
				break;
		}
	}

	/**
	 * Show the page allowing addition/editing/removal of items
	 */
	private function pageItems() {
		$out = $this->getOutput();
		$request = $this->getRequest();

		$out->setPageTitle( $this->msg( 'finance-title' ) );

		// Get select period (current period if none specified)
		$period = finance_api::getPeriod( $request->getVal( 'period' ) );

		// Try to save data if supplied
		if ( $request->wasPosted() ) {
			$this->submitItemForm();
		}

		// Display heading
		$out->addWikiText( '==' . $this->msg( 'finance-spanning', $period->start_date, $period->end_date )->text() . '==' );

		// Display transactions for period
		$out->addWikiText( '===' . $this->msg( 'finance-transactions-for' )->text() . '===' );
		finance_api::printItems( finance_api::getTransactions( $period ) );

		// Display report for period
		$out->addWikiText( '===' . $this->msg( 'finance-report-for' )->text() . '===' );
		finance_api::printItems( finance_api::getReport( $period ) );

		// Show item edit form
		$this->editItemForm();
	}

	/**
	 * Show a form allowing the user to add/update/delete items
	 */
	private function editItemForm() {
		$request = $this->getRequest();

		// See if an item has been selected for editing
		if ( $request->getVal( 'item' ) ) {
			$item = finance_api::getItem( $request->getVal( 'item' ) );
		} else {
			$item = null;
		}

		// Get an array containing the date of the item
		$date = $item ? db_date_to_array( $item->date ) :
			array(
				'tm_year' => date( 'Y' ),
				'tm_mon' => date( 'm' ),
				'tm_mday' => date( 'd' )
			);

		// Fill fields with current date if no item selected
		$date['tm_year'] = $request->getVal( 'year' ) ? $request->getVal( 'year' ) : $date['tm_year'];
		$date['tm_mon'] = $request->getVal( 'month' ) ? $request->getVal( 'month' ) : $date['tm_mon'];
		$date['tm_mday'] = $request->getVal( 'day' ) ? $request->getVal( 'day' ) : $date['tm_mday'];

		// Retrieve other fields
		$itemd = $request->getVal( 'item' ) ? $request->getVal( 'item' ) : ( $item ? $item->item : '' );
		$amount = $request->getVal( 'amount' ) ? $request->getVal( 'amount' ) : ( $item ? $item->amount : 0 );
		$period = $request->getVal( 'period' ) ? $request->getVal( 'period' ) : ( $item ? $item->period : 0 );

		// Display form
		$text = '<form action="" method="post">' . "\n";

		// Show date edit boxes
		$text .= $this->dateSelect( $date );
		$text .= '<input name="item" type="text" size="20" value="' . htmlspecialchars( $itemd, ENT_QUOTES ) . '">' . "\n";
		$text .= '<input name="amount" type="text" size="4" value="' . htmlspecialchars( $amount, ENT_QUOTES ) . '">' . "\n";

		$text .= '<select name="period">' . "\n";
		$text .= '<option value="0"' . ( $period == 0 ? ' selected="selected"' : '' ) . '>' . $this->msg( 'finance-no-reassignment' )->text() . '</option>';
		foreach ( finance_api::getPeriods() as $i ) {
			$text .= '<option value="' . $i->id . '"' . ( $i->id == $period ? ' selected="selected"' : '' ) . '>' . $i->end_date . '</option>';
		}
		$text .= "</select>\n";

		if ( $item ) {
			$text .= '<input name="id" type="hidden" value="' . htmlspecialchars( $request->getVal( 'item' ), ENT_QUOTES ) . '">' . "\n";
			$text .= '<input type="submit" name="action" value="' . $this->msg( 'finance-update' )->text() . '" />' . "\n";
		} else {
			$text .= '<input type="submit" name="action" value="' . $this->msg( 'finance-add' )->text() . '" />' . "\n";
		}
		$text .= '</form>';

		if ( $item ) {
			$text .= '<form action="' . paypalCommon::pageLink( 'Special:Finance', 'Items', 'period=' . $request->getVal( 'period' ) ) . '" method="post">' . "\n";
			$text .= '<input type="hidden" name="id" value="' . htmlspecialchars( $request->getVal( 'item' ), ENT_QUOTES ) . '" />' . "\n";
			$text .= '<input type="submit" name="action" value="' . $this->msg( 'delete' )->text() . '" />' . "\n";
			$text .= '<input type="submit" value="' . $this->msg( 'cancel' )->text() . '" />' . "\n";
			$text .= '</form>';
		}

		$this->getOutput()->addHTML( $text );
	}

	/**
	 * Show three edit boxes designed for selecting a date in a form
	 */
	private function dateSelect( $date ) {
		$text = '<input name="year" type="text" size="2" value="' . $date['tm_year'] . '">' . "-\n";

		$text .= '<select name="month">' . "\n";
		$text .= '<option value=""></option>';
		foreach ( range( 1, 12 ) as $i ) {
			$text .= '<option value="' . $i . '"' . ( $i == $date['tm_mon'] ? ' selected="selected"' : '' ) .'>' .
				( strlen( $i ) == 1 ? '0' : '' ) . $i . '</option>';
		}
		$text .= "</select>-\n";

		$text .= '<select name="day">' . "\n";
		$text .= '<option value=""></option>';
		foreach ( range( 1, 31 ) as $i ) {
			$text .= '<option value="' . $i . '"' . ( $i == $date['tm_mday'] ? ' selected="selected"' : '' ) . '>' .
				( strlen( $i ) == 1 ? '0' : '' ) . $i . '</option>';
		}
		$text .= "</select>\n";
		return $text;
	}

	/**
	 * Validate POST data, and save/update/delete it to/from the database
	 */
	private function submitItemForm() {
		$dbw = wfGetDB( DB_MASTER );
		$request = $this->getRequest();

		// If Delete selected, delete and exit
		if ( $request->getVal( 'action' ) == 'Delete' ) {
			$dbw->delete(
				'finance_lineitem',
				array( 'id' => $request->getVal( 'id' ) ),
				__METHOD__
			);
			return true;
		}

		// If date not selected properly, fail
		if ( !$request->getVal( 'year' ) || !$request->getVal( 'month' ) || !$request->getVal( 'day' ) ) {
			return false;
		}

		// Format date for MySQL
		$date = $request->getVal( 'year' ) ) . '-' . $request->getVal( 'month' ) . '-' . $request->getVal( 'day' );
		// Make sure selected date is within the period the user is working with
		$date_unix = mktime( 0, 0, 0, $request->getVal( 'month' ), $request->getVal( 'day' ), $request->getVal( 'year' ) );
		$period = finance_api::getPeriod( $request->getVal( 'period' ) );
		if ( finance_api::$inclusive_date == 'start' ) {
			$valid_date = db_date_to_unix( $period->start_date ) == $date_unix;
		} else {
			$valid_date = db_date_to_unix( $period->end_date ) == $date_unix;
		}
		if ( !$valid_date ) {
			$valid_date = db_date_to_unix( $period->start_date ) < $date_unix && db_date_to_unix( $period->end_date ) > $date_unix;
		}
		if ( !$valid_date ) {
			// This section is called if selected date is outside period dates
			// We will fail and exit unless user has selected this item be
			// reported in the period being edited
			if ( $_GET['period'] != $_POST['period'] && !finance_api::getPeriod( $_POST['period'] ) ) {
				return false;
			}
		}

		// POST variable OK, update database
		switch ( $request->getVal( 'action' ) ) {
			case 'Add':
				$dbw->insert(
					'finance_lineitem',
					array(
						'date' => $date,
						'item' => $request->getVal( 'item' ),
						'amount' => $request->getVal( 'amount' ),
						'period' => $request->getVal( 'period' ),
					),
					__METHOD__
				);
				break;
			case 'Update':
				$dbw->update(
					'finance_lineitem',
					array(
						'date' => $date,
						'item' => $request->getVal( 'item' ),
						'amount' => $request->getVal( 'amount' ),
						'period' => $request->getVal( 'period' ),
					),
					array( 'id' => $request->getVal( 'id' ) ),
					__METHOD__
				*/
				break;
		}
		// TODO: could possible clear POST variables here
	}

	/**
	 * Show the page allowing addition/editing/removal of periods
	 */
	private function pagePeriods() {
		$this->getOutput()->setPageTitle( $this->msg( 'finance-periods-title' ) );

		if ( $this->getRequest()->wasPosted() ) {
			$this->submitPeriodForm();
		}

		// List periods for user
		finance_api::printPeriods( finance_api::getPeriods() );

		// Show edit form
		$this->editPeriodForm();
	}

	/**
	 * Prints a form allowing the user to add a period, or update or delete one
	 */
	private function editPeriodForm() {
		$request = $this->getRequest();
		if ( $request->getVal( 'period' ) ) {
			$item = finance_api::getPeriod( $request->getVal( 'period' ) );
		} else {
			$item = null;
		}
		$date = $item ? db_date_to_array( $item->end_date ) : array(
			'tm_year' => date( 'Y' ),
			'tm_mon' => date( 'm' ),
			'tm_mday' => date( 'd' )
		);
		$date['tm_year'] = $request->getVal( 'year' ) ? $request->getVal( 'year' ) : $date['tm_year'];
		$date['tm_mon'] = $request->getVal( 'month' ) ? $request->getVal( 'month' ) : $date['tm_mon'];
		$date['tm_mday'] = $request->getVal( 'day' ) ? $request->getVal( 'day' ) : $date['tm_mday'];

		$text = '<form action="" method="post">' . "\n";
		$text .= $this->dateSelect( $date );

		if ( $item ) {
			$text .= '<input name="id" type="hidden" value="' . htmlspecialchars( $request->getVal( 'period' ), ENT_QUOTES ) . '" />' . "\n";
			$text .= '<input type="submit" name="action" value="' . $this->msg( 'finance-update' )->text() . '" />' . "\n";
		} else {
			$text .= '<input type="submit" name="action" value="' . $this->msg( 'finance-add' )->text() . '" />' . "\n";
		}

		$text .= '</form>';

		if ( $item ) {
			$text .= '<form action="' . paypalCommon::pageLink( 'Special:Finance', 'Periods', '' ) . '" method="post">' . "\n";
			$text .= '<input type="hidden" name="id" value="' . htmlspecialchars( $request->getVal( 'period' ), ENT_QUOTES ) . '" />' . "\n";
			$text .= '<input type="submit" name="action" value="' . $this->msg( 'delete' )->text() . '" />' . "\n";
			$text .= '<input type="submit" value="' . $this->msg( 'cancel' )->text() . '">'."\n";
			$text .= '</form>';
		}

		$this->getOutput()->addHTML( $text );
	}

	/**
	 * Validate POST data and update database as requested by user
	 */
	private function submitPeriodForm() {
		$dbw = wfGetDB( DB_MASTER );
		$request = $this->getRequest();

		if ( $request->getVal( 'action' ) == 'Delete' ) {
			$dbw->delete(
				'finance_period',
				array( 'id' => $request->getVal( 'id' ) ),
				__METHOD__
			);
		}
		$date = $request->getVal( 'year' ) . '-' . $request->getVal( 'month' ) . '-' . $request->getVal( 'day' );

		switch ( $request->getVal( 'action' ) ) {
			case 'Add':
				$dbw->insert(
					'finance_period',
					array( 'end_date' => $date ),
					__METHOD__
				);
				break;
			case 'Update':
				$dbw->update(
					'finance_period',
					array( 'end_date' => $date ),
					array( 'id' => $request->getVal( 'id' ) ),
					__METHOD__
				);
				break;
		}
		// TODO: could possibly clear POST variables here
	}
}

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
	 * @param $par Mixed: parameter(s) passed to the page or null
	 */
	public function execute( $par ) {
		global $wgOut, $wgUser;

		$this->setHeaders();

		// Make sure the user is allowed to view this page
		if ( !$wgUser->isAllowed( 'finance-edit' ) ) {
			$wgOut->permissionRequired( 'finance-edit' );
			return;
		}

		// Display navigation links
		$wgOut->addWikiText( '[[Special:Finance/Periods|Periods]] - [[Special:Finance/Items|Items]]' );
		switch( $par ) {
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
	function pageItems() {
		global $wgOut;
		$wgOut->setPageTitle( wfMsgHtml( 'finance-title' ) );

		// Get select period (current period if none specified)
		$period = finance_api::getPeriod( $_GET['period'] );

		// Try to save data if supplied
		if( array_key_exists( 'action', $_POST ) ) {
			$this->submitItemForm();
		}

		// Display heading
		$wgOut->addWikiText( '==' . wfMsgHtml( 'finance-spanning', $period->start_date, $period->end_date ) . '==' );

		// Display transactions for period
		$wgOut->addWikiText( '===' . wfMsgHtml( 'finance-transactions-for' ) . '===' );
		finance_api::printItems( finance_api::getTransactions( $period ) );

		// Display report for period
		$wgOut->addWikiText( '===' . wfMsgHtml( 'finance-report-for' ) . '===' );
		finance_api::printItems( finance_api::getReport( $period ) );

		// Show item edit form
		$this->editItemForm();
	}

	/**
	 * Show a form allowing the user to add/update/delete items
	 */
	function editItemForm() {
		global $wgOut;

		// See if an item has been selected for editing
		$item = array_key_exists( 'item', $_GET ) ? finance_api::getItem( $_GET['item'] ) : null;

		// Get an array containing the date of the item
		$date = $item ? db_date_to_array( $item->date ) :
			array( 'tm_year' => date( 'Y' ), 'tm_mon' => date( 'm' ), 'tm_mday' => date( 'd' ) );

		// Fill fields with current date if no item selected
		$date['tm_year'] = $_POST['year'] ? $_POST['year'] : $date['tm_year'];
		$date['tm_mon'] = $_POST['month'] ? $_POST['month'] : $date['tm_mon'];
		$date['tm_mday'] = $_POST['day'] ? $_POST['day'] : $date['tm_mday'];

		// Retrieve other fields
		$itemd = $_POST['item'] ? $_POST['item'] : ( $item ? $item->item : '' );
		$amount = $_POST['amount'] ? $_POST['amount'] : ( $item ? $item->amount : 0 );
		$period = $_POST['period'] ? $_POST['period'] : ( $item ? $item->period : 0 );

		// Display form
		$text = '<form action="" method="post">' . "\n";

		// Show date edit boxes
		$text .= $this->dateSelect( $date );
		$text .= '<input name="item" type="text" size="20" value="' . htmlspecialchars( $itemd, ENT_QUOTES ) . '">' . "\n";
		$text .= '<input name="amount" type="text" size="4" value="' . htmlspecialchars( $amount, ENT_QUOTES ) . '">' . "\n";

		$text .= '<select name="period">' . "\n";
		$text .= '<option value="0"' . ( 0 == $period ? ' selected="selected"' : '' ) . '>' . wfMsgHtml( 'finance-no-reassignment' ) . '</option>';
		foreach( finance_api::getPeriods() as $i ) {
			$text .= '<option value="' . $i->id . '"' . ( $i->id == $period ? ' selected="selected"' : '' ) . '>' . $i->end_date . '</option>';
		}
		$text .= "</select>\n";

		if( $item ) {
			$text .= '<input name="id" type="hidden" value="' . $_GET['item'] . '">' . "\n";
			$text .= '<input type="submit" name="action" value="' . wfMsgHtml( 'finance-update' ) . '" />' . "\n";
		} else {
			$text .= '<input type="submit" name="action" value="' . wfMsgHtml( 'finance-add' ) . '" />' . "\n";
		}
		$text.= '</form>';

		if( $item ) {
			$text .= '<form action="' . paypalCommon::pageLink( 'Special:Finance', 'Items', 'period=' . $_GET['period'] ) . '" method="post">' . "\n";
			$text .= '<input type="hidden" name="id" value="' . $_GET['item'] . '" />' . "\n";
			$text .= '<input type="submit" name="action" value="' . wfMsgHtml( 'delete' ) . '" />' . "\n";
			$text .= '<input type="submit" value="' . wfMsgHtml( 'cancel' ) . '" />' . "\n";
			$text .= '</form>';
		}
		$wgOut->addHTML( $text );
	}

	/**
	 * Show three edit boxes designed for selecting a date in a form
	 */
	function dateSelect( $date ) {
		$text = '<input name="year" type="text" size="2" value="' . $date['tm_year'] . '">' . "-\n";

		$text .= '<select name="month">' . "\n";
		$text .= '<option value=""></option>';
		foreach( range( 1, 12 ) as $i ) {
			$text .= '<option value="' . $i . '"' . ( $i == $date['tm_mon'] ? ' selected="selected"' : '' ) .'>' .
				( strlen( $i ) == 1 ? '0' : '' ) . $i . '</option>';
		}
		$text .= "</select>-\n";

		$text .= '<select name="day">' . "\n";
		$text .= '<option value=""></option>';
		foreach( range( 1, 31 ) as $i ) {
			$text .= '<option value="' . $i . '"' . ( $i == $date['tm_mday'] ? ' selected="selected"' : '' ) . '>' .
				( strlen( $i ) == 1 ? '0' : '' ) . $i . '</option>';
		}
		$text .= "</select>\n";
		return $text;
	}

	/**
	 * Validate POST data, and save/update/delete it to/from the database
	 */
	function submitItemForm() {
		$dbw = wfGetDB( DB_MASTER );

		// If Delete selected, delete and exit
		if( $_POST['action'] == 'Delete' ) {
			$dbw->delete(
				'finance_lineitem',
				array( 'id' => $dbw->strencode( $_POST['id'] ) ),
				__METHOD__
			);
			return true;
		}

		// If date not selected properly, fail
		if( !$_POST['year'] || !$_POST['month'] || !$_POST['day'] ) {
			return false;
		}

		// Format date for MySQL
		$date = mysql_escape_string( $_POST['year'] ) . '-' . mysql_escape_string( $_POST['month'] ) . '-' . mysql_escape_string( $_POST['day'] );
		// Make sure selected date is within the period the user is working with
		$date_unix = mktime( 0, 0, 0, $_POST['month'], $_POST['day'], $_POST['year'] );
		$period = finance_api::getPeriod( $_GET['period'] );
		if( finance_api::$inclusive_date == 'start' ) {
			$valid_date = db_date_to_unix( $period->start_date ) == $date_unix;
		} else {
			$valid_date = db_date_to_unix( $period->end_date ) == $date_unix;
		}
		if( !$valid_date ) {
			$valid_date = db_date_to_unix( $period->start_date ) < $date_unix && db_date_to_unix( $period->end_date ) > $date_unix;
		}
		if( !$valid_date ) {
			// This section is called if selected date is outside period dates
			// We will fail and exit unless user has selected this item be
			// reported in the period being edited
			if( $_GET['period'] != $_POST['period'] && !finance_api::getPeriod( $_POST['period'] ) ) {
				return false;
			}
		}

		// POST variable OK, update database
		switch( $_POST['action'] ) {
			case 'Add':
				$dbw->insert(
					'finance_lineitem',
					array(
						'date' => $date,
						'item' => $dbw->strencode( $_POST['item'] ),
						'amount' => $dbw->strencode( $_POST['amount'] ),
						'period' => $dbw->strencode( $_POST['period'] ),
					),
					__METHOD__
				);
				break;
			case 'Update':
				$dbw->update(
					'finance_lineitem',
					array(
						'date' => $date,
						'item' => $dbw->strencode( $_POST['item'] ),
						'amount' => $dbw->strencode( $_POST['amount'] ),
						'period' => $dbw->strencode( $_POST['period'] ),
					),
					array( 'id' => $dbw->strencode( $_POST['id'] ) ),
					__METHOD__
				*/
				break;
		}
		// TODO: could possible clear POST variables here
	}

	/**
	 * Show the page allowing addition/editing/removal of periods
	 */
	function pagePeriods() {
		global $wgOut;
		$wgOut->setPageTitle( wfMsgHtml( 'finance-periods-title' ) );

		if( array_key_exists( 'action', $_POST ) ) {
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
	function editPeriodForm() {
		global $wgOut;
		$item = array_key_exists( 'period', $_GET ) ? finance_api::getPeriod( $_GET['period'] ) : null;
		$date = $item ? db_date_to_array( $item->end_date ) : array( 'tm_year' => date( 'Y' ), 'tm_mon' => date( 'm' ), 'tm_mday' => date( 'd' ) );
		$date['tm_year'] = $_POST['year'] ? $_POST['year'] : $date['tm_year'];
		$date['tm_mon'] = $_POST['month'] ? $_POST['month'] : $date['tm_mon'];
		$date['tm_mday'] = $_POST['day'] ? $_POST['day'] : $date['tm_mday'];

		$text = '<form action="" method="post">' . "\n";
		$text .= $this->dateSelect( $date );

		if( $item ) {
			$text .= '<input name="id" type="hidden" value="' . $_GET['period'] . '" />' . "\n";
			$text .= '<input type="submit" name="action" value="' . wfMsgHtml( 'finance-update' ) . '" />' . "\n";
		} else {
			$text .= '<input type="submit" name="action" value="' . wfMsgHtml( 'finance-add' ) . '" />' . "\n";
		}
		$text .= '</form>';
		if( $item ) {
			$text .= '<form action="' . paypalCommon::pageLink( 'Special:Finance', 'Periods', '' ) . '" method="post">' . "\n";
			$text .= '<input type="hidden" name="id" value="' . $_GET['period'] . '" />' . "\n";
			$text .= '<input type="submit" name="action" value="' . wfMsgHtml( 'delete' ) . '" />' . "\n";
			$text .= '<input type="submit" value="' . wfMsgHtml( 'cancel' ) . '">'."\n";
			$text .= '</form>';
		}
		$wgOut->addHTML( $text );
	}

	/**
	 * Validate POST data and update database as requested by user
	 */
	function submitPeriodForm() {
		$dbw = wfGetDB( DB_MASTER );

		if( $_POST['action'] == 'Delete' ) {
			$dbw->delete(
				'finance_period',
				array( 'id' => $dbw->strencode( $_POST['id'] ) ),
				__METHOD__
			);
		}
		$date = mysql_escape_string( $_POST['year'] ) . '-' . mysql_escape_string( $_POST['month'] ) . '-' . mysql_escape_string( $_POST['day'] );

		switch( $_POST['action'] ) {
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
					array( 'id' => $dbw->strencode( $_POST['id'] ) ),
					__METHOD__
				);
				break;
		}
		// TODO: could possibly clear POST variables here
	}
}

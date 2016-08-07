<?php
/**
 * Provides the Special:FinanceReports page.
 *
 * @file
 * @ingroup Extensions
 */
class SpecialFinanceReports extends SpecialPage {

	/**
	 * Constructor function called by MediaWiki
	 */
	public function __construct() {
		parent::__construct( 'FinanceReports', 'finance-view' );
	}

	/**
	 * Entry point for rendering the special page
	 * Display a subpage for periods or items
	 * @param $par Mixed: what subpage to show?
	 */
	public function execute( $par ) {
		global $wgOut, $wgUser;

		$this->setHeaders();
		$wgOut->setPageTitle( wfMsgHtml( 'financereports' ) );

		// Make sure the user is allowed to view this page
		if ( !$wgUser->isAllowed( 'finance-view' ) ) {
			throw new PermissionsError( 'finance-view' );
		}

		// Display navigation links
		$wgOut->addWikiText(
			'[[Special:FinanceReports/Periods|' . wfMsgHtml( 'financereports-periods' ) .
			']] - [[Special:FinanceReports/Items|' . wfMsgHtml( 'financereports-current' ) .
			']]'
		);
		switch( $par ) {
			case 'Periods':
				# print a list of periods
				finance_api::printPeriods( finance_api::getPeriods() );
				break;
			case 'Items':
			default:
				# print report for the selected period
				# getPeriod returns the current period if no period selected
				$period = finance_api::getPeriod( $_GET['period'] );
				$wgOut->addWikiText( '==' . wfMsgHtml( 'financereports-from', $period->start_date, $period->end_date ) . '==' );
				$wgOut->addWikiText( '===' . wfMsgHtml( 'financereports-reportfor' ) . '===' );
				finance_api::printItems( finance_api::getReport( $period ) );
				break;
		}
	}
}

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
	 *
	 * @param string|null $par Name of the subpage to show, if any
	 */
	public function execute( $par ) {
		$this->setHeaders();
		$out = $this->getOutput();
		$out->setPageTitle( $this->msg( 'financereports' ) );

		// Make sure the user is allowed to view this page
		if ( !$this->getUser()->isAllowed( 'finance-view' ) ) {
			throw new PermissionsError( 'finance-view' );
		}

		// Display navigation links
		$out->addWikiText(
			'[[Special:FinanceReports/Periods|' . $this->msg( 'financereports-periods' )->text() .
			']] - [[Special:FinanceReports/Items|' . $this->msg( 'financereports-current' )->text() .
			']]'
		);
		switch ( $par ) {
			case 'Periods':
				# print a list of periods
				finance_api::printPeriods( finance_api::getPeriods() );
				break;
			case 'Items':
			default:
				# print report for the selected period
				# getPeriod returns the current period if no period selected
				$period = finance_api::getPeriod( $this->getRequest()->getVal( 'period' ) );
				$out->addWikiText( '==' . $this->msg( 'financereports-from', $period->start_date, $period->end_date )->text() . '==' );
				$out->addWikiText( '===' . $this->msg( 'financereports-reportfor' )->text() . '===' );
				finance_api::printItems( finance_api::getReport( $period ) );
				break;
		}
	}
}

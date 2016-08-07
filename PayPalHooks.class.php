<?php
/**
 * @file
 * @date 7 August 2016
 */
class PayPalHooks {

	// This isn't technically even a hooked function...
	public static function setup() {
		paypalCommon::setVariables();
	}

	/**
	 * Inserts a progress bar into the top of wiki pages
	 * The progress bar shows how much of the current months
	 * costs are covered by donations
	 */
	public static function insertProgress( &$siteNotice, $skin ) {
		global $wgExtensionAssetsPath;

		// Only show this for enabled namespaces
		if ( !in_array( $skin->getTitle()->getNamespace(), $wgPayPalProgressBarEnabledNamespaces ) ) {
			return true;
		}

		$report = finance_api::getReport( finance_api::getPeriod() );
		$income = finance_api::getIncome( $report );
		$expense = finance_api::getExpense( $report );
		// @todo FIXME: inline CSS, hardcoded English in the link...what else?
		$text = '<div id="donationprogressbar" style="margin-top: -1.1em; text-align: center;">
	<a href="' . Title::newFromText( 'Site_support', NS_PROJECT )->getFullURL() . '">
		<img src="' . $wgExtensionAssetsPath . '/PayPal/progressbar.php?income=' . $income . '&expense=' . $expense . '"/>
	</a>
	</div>' . $text;

		return true;
	}

	/**
	 * Creates the necessary new database tables when the user runs
	 * /maintenance/update.php, the MediaWiki core updater script.
	 *
	 * @param DatabaseUpdater $updater
	 * @return bool
	 */
	public static function onLoadExtensionSchemaUpdates( $updater ) {
		$dir = __DIR__ . '/sql';

		$updater->addExtensionUpdate( array( 'addTable', 'donations', "$dir/donations.sql", true ) );
		$updater->addExtensionUpdate( array( 'addTable', 'finance_lineitem', "$dir/finance_lineitem.sql", true ) );
		$updater->addExtensionUpdate( array( 'addTable', 'finance_period', "$dir/finance_period.sql", true ) );

		return true;
	}
}
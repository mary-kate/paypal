<?php
/**
 * Allows visitors to make donations via PayPal
 * Features:
 * Allows users to complete a form allowing them to make a payment via PayPal
 * Records details of donations
 *
 * @file
 * @ingroup Extensions
 * @author Trevor Ian Peacock
 * @author Jack Phoenix <jack@countervandalism.net> -- cleanup
 * @copyright © 2007 Trevor Ian Peacock
 * @version 0.0.1
 */

# Sets up the plugin as a MediaWiki extension.
require_once( dirname( __FILE__ ) . '/common.php' );

$wgExtensionFunctions[] = 'paypalSetup';
$wgExtensionCredits['other'][] = array(
	'name' => 'PayPal Donation Extension',
	'version' => '0.0.1',
	'author' => array( '[http://about.peacocktech.com/trevorp/ Trevor Ian Peacock]', 'Jack Phoenix' ),
	'description' => 'Adds a special page allowing users to donate money to the wiki.',
	'url' => 'http://wiki.peacocktech.com/wiki/PaypalDonationExtension',
);

// Set up the new special pages
$dir = dirname( __FILE__ ) . '/';
$wgExtensionMessagesFiles['PayPal'] = $dir . 'PayPal.i18n.php';
$wgAutoloadClasses['FinanceReports'] = $dir . 'FinanceReports.php';
$wgAutoloadClasses['SpecialDonors'] = $dir . 'SpecialDonors.php';
$wgAutoloadClasses['SpecialDonate'] = $dir . 'SpecialDonate.php';
$wgAutoloadClasses['SpecialFinance'] = $dir . 'SpecialFinance.php';
$wgSpecialPages['Donate'] = 'SpecialDonate';
$wgSpecialPages['Donors'] = 'SpecialDonors';
$wgSpecialPages['Finance'] = 'SpecialFinance';
$wgSpecialPages['FinanceReports'] = 'SpecialFinanceReports';

// New user rights
$wgAvailableRights[] = 'finance-edit';
$wgAvailableRights[] = 'finance-view';
$wgGroupPermissions['treasurer']['finance-edit'] = true;
$wgGroupPermissions['*']['finance-view'] = true;

// Initialises messages in the MediaWiki engine
function paypalSetup() {
	paypalCommon::setVariables();
}

// Inserts a progress bar into the top of wiki pages
// The progress bar shows how much of the current months
// costs are covered by donations 
$wgHooks['OutputPageBeforeHTML'][] = 'insertProgress';

// Array of namespaces for which the progress bar will be shown
$wgPayPalProgressBarEnabledNamespaces = array(
	NS_MAIN, NS_USER, NS_PROJECT, NS_HELP, NS_CATEGORY
);

// Retrieve totals for current month, and show a progress bar
function insertProgress( $parserOutput, $text ) {
	global $wgArticle, $wgTitle, $wgServer, $wgScriptPath;

	// Enabled namespaces: main, user, project, help and category
	if( !in_array( $wgArticle->getTitle()->getNamespace(), $wgPayPalProgressBarEnabledNamespaces ) ) {
		return;
	}

	$report = finance_api::getReport( finance_api::getPeriod() );
	$income = finance_api::getIncome( $report );
	$expense = finance_api::getExpense( $report );
	$text = '<div id="donationprogressbar" style="margin-top: -1.1em; text-align: center;">
	<a href="' . Title::newFromText( 'Site_support', NS_PROJECT )->getFullURL() . '">
		<img src="' . $wgServer . $wgScriptPath . '/extensions/PayPal/progressbar.php?income=' . $income . '&expense=' . $expense . '"/>
	</a>
	</div>' . $text;
}
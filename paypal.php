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
$wgExtensionFunctions[] = 'PayPalHooks::setup';
$wgExtensionCredits['other'][] = array(
	'name' => 'PayPal Donation Extension',
	'version' => '0.0.1',
	'author' => array( '[http://about.peacocktech.com/trevorp/ Trevor Ian Peacock]', 'Jack Phoenix' ),
	'description' => 'Adds a special page allowing users to donate money to the wiki.',
	'url' => 'http://wiki.peacocktech.com/wiki/PaypalDonationExtension',
);

$wgMessagesDirs['PayPal'] = __DIR__ . '/i18n';

$wgAutoloadClasses['paypalFinanceAPI'] = __DIR__ . '/api/paypalFinanceAPI.class.php';
$wgAutoloadClasses['paypal_api'] = __DIR__ . '/api/paypalAPI.class.php';
$wgAutoloadClasses['paypalCommon'] = __DIR__ . '/paypalCommon.php';
$wgAutoloadClasses['paypal_ipn'] = __DIR__ . '/paypalCommon.php';

$wgAutoloadClasses['PayPalHooks'] = __DIR__ . '/PayPalHooks.class.php';

// Set up the new special pages
$wgAutoloadClasses['FinanceReports'] = __DIR__ . '/specials/SpecialFinanceReports.php';
$wgAutoloadClasses['SpecialDonors'] = __DIR__ . '/specials/SpecialDonors.php';
$wgAutoloadClasses['SpecialDonate'] = __DIR__ . '/specials/SpecialDonate.php';
$wgAutoloadClasses['SpecialFinance'] = __DIR__ . '/specials/SpecialFinance.php';
$wgSpecialPages['Donate'] = 'SpecialDonate';
$wgSpecialPages['Donors'] = 'SpecialDonors';
$wgSpecialPages['Finance'] = 'SpecialFinance';
$wgSpecialPages['FinanceReports'] = 'SpecialFinanceReports';

// New user rights
$wgAvailableRights[] = 'finance-edit';
$wgAvailableRights[] = 'finance-view';
$wgGroupPermissions['treasurer']['finance-edit'] = true;
$wgGroupPermissions['*']['finance-view'] = true;

$wgHooks['LoadExtensionSchemaUpdates'][] = 'PayPalHooks::onLoadExtensionSchemaUpdates';
$wgHooks['SiteNoticeBefore'][] = 'PayPalHooks::insertProgress';

// Array of namespaces for which the progress bar will be shown
$wgPayPalProgressBarEnabledNamespaces = array(
	NS_MAIN, NS_USER, NS_PROJECT, NS_HELP, NS_CATEGORY
);
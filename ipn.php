<?php
# ipn.php
#
# Callback (Instant Payment Notification) for Paypal Payments
#
# Part of the PaypalDonationExtension. See paypal.php.
#
# This script is called by paypal upon the completion of a transaction.
# The script is called directly (not as an extension for MediaWiki), and
#   must be reachable via the public internet.
# This script will make a http call back to paypal to confirm the payment,
#   and thus the apache server must be able to make outgoing http requests.
#
# Paypal calls this script, passing it details of the transaction via POST
#   variables. To confirm these are not forged, they are passed back to
#   paypal for confirmation.
# Once confirmed, details are stored in the database.
#
# To achieve access to the database, the mediawiki engine is initialised.
# The method for initialisation is not documented, and is not garunteed. Its
#   reliability may greatly depend on the version of the MediaWiki engine.
# The tested version of MediaWiki is MediaWiki 1.7.1
#

#simulate MediaWiki Startup
define( 'MEDIAWIKI', true );
require_once( '../../LocalSettings.php' );
require_once( 'includes/Setup.php' );
require_once( dirname( __FILE__ ) . '/common.php' );
paypalCommon::setVariables();
#process payment
paypal_ipn::processConfirmation();
<?php
# Callback (Instant Payment Notification) for PayPal payments
#
# Part of the PaypalDonationExtension. See extension.json.
#
# This script is called by PayPal upon the completion of a transaction.
# The script is called directly (not as an extension for MediaWiki), and
#   must be reachable via the public internet.
# This script will make a HTTP call back to PayPal to confirm the payment,
#   and thus the web server must be able to make outgoing HTTP requests.
#
# PayPal calls this script, passing it details of the transaction via POST
#   variables. To confirm these are not forged, they are passed back to
#   PayPal for confirmation.
# Once confirmed, details are stored in the database.
#
# To achieve access to the database, the MediaWiki engine is initialised.
# The method for initialisation is not documented, and is not guaranteed. Its
#   reliability may greatly depend on the version of the MediaWiki engine.

# simulate MediaWiki startup
define( 'MEDIAWIKI', true );
require_once '../../LocalSettings.php';
require_once '../../includes/Setup.php';
paypalCommon::setVariables();
# process payment
paypal_ipn::processConfirmation();

<?php

class finance_api {

	# Is the 'start' or 'end' of period included in period
	static $inclusive_date = 'start';

	# return a list of all defined periods
	static function getPeriods() {
		$sql = 'SELECT *, (SELECT end_date FROM finance_period AS fp WHERE fp.end_date<finance_period.end_date ORDER BY end_date DESC LIMIT 1) AS start_date FROM finance_period ORDER BY end_date';
		return usRunQuery( $sql );
	}

	# return a single period, with id=$id
	#
	# if no $id specified, retrieve the current period
	static function getPeriod( $id = null ) {
		if( $id != null ) {
			$sql = 'SELECT *, (SELECT end_date FROM finance_period AS fp WHERE fp.end_date<finance_period.end_date ORDER BY end_date DESC LIMIT 1) AS start_date FROM finance_period WHERE id=' . mysql_escape_string( $id );
		} else {
			$sql = 'SELECT *, (SELECT end_date FROM finance_period AS fp WHERE fp.end_date<finance_period.end_date ORDER BY end_date DESC LIMIT 1) AS start_date FROM finance_period WHERE end_date>now() ORDER BY end_date ASC LIMIT 1';
		}
		$result = usRunQuery( $sql );
		reset( $result );
		return current( $result );
	}

	# Retrieve all transactions with dates within the specified
	#   period (from getPeriod())
	static function getTransactions( $period ) {
		$sql = 'SELECT * FROM finance_lineitem WHERE date>' . (
			finance_api::$inclusive_date == 'start' ? '="' : '"' ) .
			$period->start_date . '" AND date<' . ( finance_api::$inclusive_date == 'end' ? '="' : '"' ) . $period->end_date . '";';
		return usRunQuery( $sql );
	}

	/**
	 * Get a financial report for the specified period.
	 * A report uses the lineitem.period field to reassign items
	 *
	 * $paypal may be:
	 * true - include PayPal total (if current month)
	 * false - Do not include total
	 * number - include this as PayPal total
	 */
	static function getReport( $period, $paypal = true ) {
		$sql = 'SELECT * from finance_lineitem where (date>' . ( finance_api::$inclusive_date == 'start' ? '="' : '"' ) .
			$period->start_date . '" AND date<' . ( finance_api::$inclusive_date == 'end' ? '="' : '"' ) .
			$period->end_date . '" AND period=0) OR period=' . $period->id . ';';
		$report = usRunQuery( $sql );
		if( $paypal !== false && time() > array_date_to_unix( db_date_to_array( $period->start_date ) ) &&
			time() < array_date_to_unix( db_date_to_array( $period->end_date ) )
		) {
			if( $paypal === true ) {
				$paypal = paypal_api::totalDuring( $period->start_date, $period->end_date );
			}
			$obj =& new stdClass();
			$obj->id = 0;
			$obj->item = 'Paypal Donations';
			$obj->date = unix_date_to_db( time() );
			$obj->amount = $paypal;
			$obj->period = 0;
			$report[] = $obj;
		}
		return $report;
	}

	/**
	 * Returns a total income vs. expence based on $report
	 *
	 * $report can be:
	 * object - a period (from getPeriod())
	 * array - report (from getReport())
	 * null - defaults to a report of current period
	 */
	static function getTotal( $report = null ) {
		switch( gettype( $report ) ) {
			case 'NULL':
				$report = finance_api::getPeriod();
			case 'object':
				$report = finance_api::getReport( $report );
			case 'array':
		}
		$total = 0;
		foreach( $report as $item ) {
			$total += $item->amount;
		}
		return $total;
	}

	/**
	 * Returns a total income based on $report
	 */
	static function getIncome( $report = null ) {
		switch( gettype( $report ) ) {
			case 'NULL':
				$report = finance_api::getPeriod();
			case 'object':
				$report = finance_api::getReport( $report );
			case 'array':
		}
		$total = 0;
		foreach( $report as $item ) {
			if( $item->amount > 0 ) {
				$total += $item->amount;
			}
		}
		return $total;
	}

	/**
	 * Returns a total expence based on $report
	 */
	static function getExpense( $report = null ) {
		switch( gettype( $report ) ) {
			case 'NULL':
				$report = finance_api::getPeriod();
			case 'object':
				$report = finance_api::getReport( $report );
			case 'array':
		}
		$total = 0;
		foreach( $report as $item ) {
			if( $item->amount < 0 ) {
				$total += $item->amount;
			}
		}
		return -$total;
	}

	/**
	 * Returns a single lineitem matching $id.
	 *
	 * @param $id Integer:
	 * @return
	 */
	static function getItem( $id ) {
		/*
		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select(
			'finance_lineitem',
			'*',
			array( 'id' => $dbr->strencode( $id ) ), __METHOD__ );
		$array = array();
		foreach( $res as $item ) {
			$array[] = $item;
		}
		*/
		$sql = 'SELECT * from finance_lineitem where id='.mysql_escape_string($id).';';
		$result = usRunQuery( $sql );
		reset( $result );
		return current( $result );
	}

	/**
	 * Display a series of lineitems in a table to the user
	 */
	static function printItems( $items ) {
		global $wgOut, $wgTitle, $wgUser;

		// Make sure there is something to print
		if( count( $items ) == 0 ) {
			$wgOut->addWikiText( 'No Items.' );
			return;
		}

		# Edit links are shown if the user has perimssion, and we are
		#   looking at Special:Finance
		$editor = $wgUser->isAllowed( 'finance-edit' ) && $wgTitle->getText() == 'Finance';

		$text = '<table>';
		$total = 0;
		foreach( $items as $item ) {
			$text .= '<tr>';
			if( $editor && $item->id ) {
				$text .= '<td><a href="' . paypalCommon::pageLink( 'Special:Finance', 'Items', 'period=' . $_GET['period'] . '&item=' . $item->id ) . '">' . $item->date . '</a></td>';
			} else {
				$text .= '<td>' . $item->date . '</td>';
			}
			$text .= '<td>&nbsp;&nbsp;&nbsp;' . $item->item . '</td>';
			$text .= '<td align="right">$' . number_format( $item->amount, 2 ) . '</td>';
			$text .= '</tr>';
			$total += $item->amount;
		}
		$text .= '<tr>';
		$text .= '<td>' . date( 'Y-m-d H:i:s' ) . '</td>';
		$text .= '<td>&nbsp;&nbsp;&nbsp;' . 'Total'. '</td>';
		$text .= '<td align="right">$' . number_format( $total, 2 ) . '</td>';
		$text .= '</tr>';

		$text .= '</table>';
		$wgOut->addHTML( $text );
	}

	# Display the provided list of periods to the user
	static function printPeriods( $periods ) {
		global $wgOut, $wgTitle, $wgUser;

		# Make sure there is something to print
		if( count( $periods ) == 0 ) {
			$wgOut->addWikiText( 'No Periods Defined.' );
			return;
		}

		# Edit links are shown if the user has perimssion, and we are
		#   looking at Special:Finance
		$editor = $wgUser->isAllowed( 'finance-edit' ) && $wgTitle->getText() == 'Finance';

		$text = "<table>\n";
		$text .= '<tr><th>Start Date</th><th>End Date</th><th>Income</th><th>Expense</th><th>Total</th>';
		if( $editor ) {
			$text .= '<th></th>';
		}
		$text .= "</tr>\n";
		$total = array();
		foreach( $periods as $period ) {
			$text .= '<tr>';
			$text .= '<td><a href="' . paypalCommon::pageLink( $wgTitle->getFullText(), 'Items', 'period=' . $period->id ) . '">';
			$text .= $period->start_date;
			$text .= '</a></td>';
			$text .= '<td><a href="' . paypalCommon::pageLink( $wgTitle->getFullText(), 'Items', 'period=' . $period->id ) . '">';
			$text .= $period->end_date;
			$text .= '</a></td>';

			$report = finance_api::getReport( $period );
			$in = finance_api::getIncome( $report );
			$out = finance_api::getExpense( $report );
			$total['in'] += $in;
			$total['out'] += $out;
			$text .= '<td align="right">$' . number_format( $in, 2 ) . '</a></td>';
			$text .= '<td align="right">$' . number_format( -$out, 2 ) . '</a></td>';
			$text .= '<td align="right">$' .number_format( $in - $out, 2 ) . '</a></td>';
			if( $editor ) {
				$text .= ' <td><a href="' . paypalCommon::pageLink( 'Special:Finance', 'Periods', 'period=' . $period->id ) . '">' . wfMsgHtml( 'edit' ) . '</a></td>';
			}
			$text .= "</tr>\n";
		}
		$text .= '<tr><td></td><td align="right">Total</td>';
		$text .= '<td align="right">&nbsp;&nbsp;&nbsp;$' . number_format( $total['in'], 2 ) . '</td>';
		$text .= '<td align="right">&nbsp;&nbsp;&nbsp;$' . number_format( $total['out'], 2 ) . '</td>';
		$text .= '<td align="right">&nbsp;&nbsp;&nbsp;$' . number_format( $total['in'] - $total['out'], 2 ) . '</td>';
		if( $editor ) {
			$text .= '<td></td>';
		}
		$text .= "</tr>\n";
		$text .= '</table>';
		$wgOut->addHTML( $text );
	}
}
<?php
/*
 * Copyright (c) 2015 Girino Vey.
 *
 * This software is licenced under Girino's Anarchist License.
 *
 * Permission to use this software, modify and distribute it, or parts of
 * it, is granted to everyone who wishes provided that the  conditions
 * in the Girino's Anarchist License are met. Please read it on the link
 * bellow.
 *
 * The full license is available at: http://girino.org/license
 */

require_once 'GenericAPI.php';
require_once 'ExchangeAPIs.php';

function usage() {
	print "Usage: " . $argv[0] . " <maxamount> [<min amount>=10]";
}

// Code bellow this comment is what you need to change in order to do whatever you want.

$foxbit = array( new FoxBitOrderbook(),  new FoxbitFeeCalculator(), 'BRL', 'FOX' );
$b2u = array( new B2UOrderbook(),  new B2UFeeCalculator(), 'BRL', 'B2U' );
$mbtc = array( new MBTCOrderbook(),  new MBTCFeeCalculator(), 'BRL', 'MBTC' );
$negocie = array( new NegocieCoinsOrderbook(),  new NegocieCoinsFeeCalculator(), 'BRL', 'NEGOCIE' );
$basebit = array( new BasebitOrderbook(),  new BasebitFeeCalculator(), 'BRL', 'BASEBIT' );
$bitfinex = array( new BitFinexOrderbook(),  new BitFinexFeeCalculator(), 'USD', 'BITFINEX' );
$coinbase = array( new CoinbaseOrderbook(),  new CoinbaseFeeCalculator(), 'USD', 'COINBASE' );
$kraken = array( new KrakenOrderbook(),  new KrakenFeeCalculator(), 'USD', 'KRAKEN' );
$bitstamp = array( new BitstampOrderbook(),  new BitstampFeeCalculator(), 'USD', 'BITSTAMP' );
$btce = array( new BtceOrderbook(),  new BtceFeeCalculator(), 'USD', 'BTC-E' );
$okcoin = array( new OKCoinOrderbook(),  new OKCoinFeeCalculator(), 'USD', 'OKCOIN' );

$brls = array($foxbit, $b2u, $mbtc, $negocie);

if (count($argv) <= 1) {
	usage();
	exit;
}
$value_max = (int)($argv[1]);
$value_min = $value_max;
if (count($argv) > 2) {
	$value_min = $argv[2];
}
$value_step = 1;
if (count($argv) > 3) {
	$value_step = $argv[3];
}

function find_rate($exchange, $min, $max, $step=1, $oper = 'asks') {
        $best_rate = 10e99;
	$cmp = strcmp($oper, 'asks');
	if ($cmp != 0) $best_rate = 1.0/$best_rate;
        $best = array();
	$currency_pair = array($exchange[2], 'BTC');
	$book_from = $exchange[0];
       	$fee_from = $exchange[1];
        for ($value = $min; $value <= $max; $value+=$step) {
		$bought = getValueOrders($value, $currency_pair, $book_from, $fee_from, $oper);
		$rate = $bought['initial'] / $bought['withdrawn'];
                if ($bought !== false && $rate > 0) {
			if ((($cmp == 0) && ($rate < $best_rate)) 
			   || (($cmp != 0) && (1.0/$rate < 1.0/$best_rate))) {
                        	$best = $bought;
				$best_rate = $rate;
			}
                }
                if ($bought === false) {
                        //print "Broken at $value\n";
                        break;
                }
        }
        return array($best_rate, $best);
}

foreach ($brls as $brl) {
	$ret = find_rate($brl, $value_min, $value_max, $value_step);
	print $brl[3] . " => " . $ret[0] . " for " . $brl[2] . " " . $ret[1]['initial'] . "\n";
	$ret = find_rate($brl, $value_min, $value_max, $value_step, 'bids');
	print $brl[3] . " => " . $ret[0] . " for " . $brl[2] . " " . $ret[1]['initial'] . "\n";
}

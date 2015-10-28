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

$brls = array($foxbit);
$usds = array($bitfinex, $okcoin);
#$usds = array($bitfinex);

$pairs_buy = array();
$pairs_sell = array();
$pairs_arbitrage_brl = array();
$pairs_arbitrage_usd = array();
// BRL -> USD
foreach ($brls as $brl) {
	foreach ($usds as $usd) {
		array_push($pairs_buy, array($brl, $usd));
	}
}
// USD -> BRL
foreach ($brls as $brl) {
	foreach ($usds as $usd) {
		array_push($pairs_sell, array($usd, $brl));
	}
}
// BRL -> BRL
foreach ($brls as $brl1) {
	foreach ($brls as $brl2) {
		array_push($pairs_arbitrage_brl, array($brl1, $brl2));
	}
}
// USD -> USD
foreach ($usds as $usd1) {
	foreach ($usds as $usd2) {
		array_push($pairs_arbitrage_usd, array($usd1, $usd2));
	}
}

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

$yahoo = yahoo_api_usdbrl();
$buy = $yahoo[0];
$sell = $yahoo[1];

$best_buy = find_best_rate($pairs_buy, $value_min, $value_max, $value_step, true);
$best_sell = find_best_rate($pairs_sell, $value_min, $value_max, $value_step, true);
$best_brl = find_best_rate($pairs_arbitrage_brl, $value_min, $value_max, $value_step, true);
$best_usd = find_best_rate($pairs_arbitrage_usd, $value_min, $value_max, $value_step, true);

//print_r($results);

//print_r($best);
print "BRL => BTC => USD (" . $best_buy['origin']['name'] . " => ". $best_buy['destination']['name'] . ")\n";
print $best_buy['origin']['results']['initial'] . " BRL => " . number_format($best_buy['destination']['results']['initial'], 8) . " BTC => " . number_format($best_buy['destination']['results']['bought'], 2) . " USD\n";
$vs_yahoo = $best_buy['rate_no_withdrawal'] / $yahoo[0] * 100 - 100;
$vs_yahoo = number_format($vs_yahoo, 2);
$sign = '';
if ( $vs_yahoo > 0 ) $sign = '+'; 
print number_format($best_buy['rate_no_withdrawal'], 4) . " (Buy) yahoo $sign $vs_yahoo%\n";
print number_format($best_buy['rate'], 4) . " (Buy and withdraw)\n";
print "\n";

print "USD => BTC => BRL (" . $best_sell['origin']['name'] . " => ". $best_sell['destination']['name'] . ")\n";
print $best_sell['origin']['results']['initial'] . " USD => " . number_format($best_sell['destination']['results']['initial'], 8) . " BTC => " . number_format($best_sell['destination']['results']['bought'], 2) . " BRL\n";
$vs_yahoo = (1.0/$best_sell['rate_no_withdrawal']) / $yahoo[1] * 100 - 100;
$vs_yahoo = number_format($vs_yahoo, 2);
$sign = '';
if ( $vs_yahoo > 0 ) $sign = '+'; 
print number_format(1.0/$best_sell['rate_no_withdrawal'],4) . " (Sell) yahoo $sign $vs_yahoo%\n";
print number_format(1.0/$best_sell['rate'],4) . " (Sell and withdraw)\n";
print "\n";

print "BRL => USD (Yahoo Finance - no bank fees considered)\n";
print $yahoo[0] . " (Buy) / " . $yahoo[1] . " (Sell)\n";
print "\n";

print "BRL => BTC => BRL (" . $best_brl['origin']['name'] . " => ". $best_brl['destination']['name'] . ")\n";
print $best_brl['origin']['results']['initial'] . " BRL => " . $best_brl['destination']['results']['initial'] . " BTC => " . $best_brl['destination']['results']['bought'] . " BRL\n";
print 1.0/$best_brl['rate_no_withdrawal'] . " (Sell)\n";
print 1.0/$best_brl['rate'] . " (Sell and withdraw)\n";
print "\n";

print "USD => BTC => USD (" . $best_usd['origin']['name'] . " => ". $best_usd['destination']['name'] . ")\n";
print $best_usd['origin']['results']['initial'] . " USD => " . $best_usd['destination']['results']['initial'] . " BTC => " . $best_usd['destination']['results']['bought'] . " USD\n";
print 1.0/$best_usd['rate_no_withdrawal'] . " (Sell)\n";
print 1.0/$best_usd['rate'] . " (Sell and withdraw)\n";
print "\n";


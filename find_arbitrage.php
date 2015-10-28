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
$usds = array($bitfinex, $coinbase, $kraken, $bitstamp, $btce, $okcoin);

$pairs_arbitrage_brl = array();
$pairs_arbitrage_usd = array();
// BRL -> BRL
foreach ($brls as $brl1) {
	$brl1[1]->setFee('BRL', 'withdrawal', 0, 0);
	$brl1[1]->setFee('BRL', 'deposit', 0, 0);
	foreach ($brls as $brl2) {
		$brl2[1]->setFee('BRL', 'withdrawal', 0, 0);
		$brl2[1]->setFee('BRL', 'deposit', 0, 0);
		array_push($pairs_arbitrage_brl, array($brl1, $brl2));
	}
}
// USD -> USD
foreach ($usds as $usd1) {
	$usd1[1]->setFee('USD', 'withdrawal', 0, 0);
	$usd1[1]->setFee('USD', 'deposit', 0, 0);
	foreach ($usds as $usd2) {
		$usd2[1]->setFee('USD', 'withdrawal', 0, 0);
		$usd2[1]->setFee('USD', 'deposit', 0, 0);
		array_push($pairs_arbitrage_usd, array($usd1, $usd2));
	}
}

$value_max = 10000;
if (count($argv) > 1) {
$value_max = (int)($argv[1]);
}
$value_min = 10;
if (count($argv) > 2) {
	$value_min = $argv[2];
}
$value_step = 10;
if (count($argv) > 3) {
	$value_step = $argv[3];
}

$best_brl = find_best_rate($pairs_arbitrage_brl, $value_min, $value_max, $value_step, false);
//$best_usd = find_best_rate($pairs_arbitrage_usd, $value_min, $value_max, $value_step, false);

date_default_timezone_set('America/Sao_Paulo');
$arb_log = "/tmp/arbitrage.log";
/* open trade log*/
$fh = fopen($arb_log, 'a+') or die("can't open file");

function print_log($tolog) {
        global $fh;
        fwrite($fh, date('Y-m-d H:i:s', time()) . " - " . $tolog . "\n");
        //print(date('Y-m-d H:i:s', time()) . " - " . $tolog . "\n");
}

if ($best_brl['rate_no_withdrawal'] < 1.0) {
	print_log( "BRL => BTC => BRL (" . $best_brl['origin']['name'] . " => ". $best_brl['destination']['name'] . ")");
	print_log ($best_brl['origin']['results']['initial'] . " BRL => " . $best_brl['destination']['results']['initial'] . " BTC => " . $best_brl['destination']['results']['bought'] . " BRL");
	print_log (1.0/$best_brl['rate_no_withdrawal'] . " (Sell)");
	print_log (1.0/$best_brl['rate'] . " (Sell and withdraw)");
	print_log( "");
}

//if ($best_usd['rate_no_withdrawal'] < 1.0) {
//	print_log( "USD => BTC => USD (" . $best_usd['origin']['name'] . " => ". $best_usd['destination']['name'] . ")");
//	print_log( $best_usd['origin']['results']['initial'] . " USD => " . $best_usd['destination']['results']['initial'] . " BTC => " . $best_usd['destination']['results']['bought'] . " USD");
//	print_log( 1.0/$best_usd['rate_no_withdrawal'] . " (Sell)");
//	print_log( 1.0/$best_usd['rate'] . " (Sell and withdraw)");
//	print_log( "");
//}


<?php

require_once('fees.php');
require_once('bitfinex_api.php');
require_once('blinktrade_api.php');

$orig_exchange = 'FOXBIT';
$dest_exchange = 'BITFINEX';

function usage($cmd) {
	print "Usage: $cmd <value>\n";
	print "       <value> = amount of BRL to convert to USD.\n";
}

function main($value) {
	// discount fee
	global $orig_exchange, $dest_exchange;
	$deposited = discount_fee($value, $orig_exchange, 'DEPOSIT');
	$ordered = discount_fee($deposited, $orig_exchange, 'BUY');
	$btc_bought = total_volume_buy($ordered);
	$transfered = discount_fee($btc_bought, $orig_exchange, 'TX');
	$ordered_sell = discount_fee($transfered, $dest_exchange, 'SELL');
	$sold = round(total_amount_sold($ordered_sell), 2);
	$rate = round($value/$sold, 4);

	print "Converting $value BRL to USD from $orig_exchange to $dest_exchange:\n";
	print "\t$value BRL => $sold USD, with rate $rate BRL/USD\n";
	#print_r(array($orig_exchange, $dest_exchange,$deposited,$ordered,$btc_bought,$transfered,$ordered_sell,$sold,$rate));
}

if (array_key_exists(1, $argv)) {
	main($argv[1]);
} else {
	usage($argv[0]);
}

?>

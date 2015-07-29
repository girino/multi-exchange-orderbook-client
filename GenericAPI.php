<?php

require_once('query_functions.php');

abstract class Order {
	private $currency;
	private $order_map;
	private $index_price;
	private $index_amount;
	private $index_id;
	
	public  function __construct($order, $currency, $index_price, $index_amount, $index_id=false) {
		$this->currency = $currency;
		$this->order_map = $order;
		$this->index_price = $index_price;
		$this->index_amount = $index_amount;
		$this->index_id = $index_id;
	}
	
	public function getCurrency() {
		return $currency;
	}
	
	public function getPrice($currency) {
		$price = $this->order_map[$this->index_price];
		if ($currency == 'BTC') return 1.0/$price;
		return $price;
	}
	public function getVolume($currency) {
		$volume = $this->order_map[$this->index_amount];
		if ($currency == 'BTC')	return $volume;
		return $this->order_map[$this->index_price] * $volume;
	}
	public function getId() {
		if ($this->index_id === false) {
			return false;
		}
		return $this->order_map[$this->index_id];
	}
	public function getOtherCurrency($currency) {
		if ($currency == 'BTC') return $this->currency;
		return 'BTC';
	}
}

class BlinktradeOrder extends Order {
	public  function __construct($order) {
		parent::__construct($order, 'BRL', 0, 1, 2);
	}
}
class BitfinexOrder extends Order {
	public  function __construct($order) {
		parent::__construct($order, 'USD', 'price', 'amount', 'timestamp');
	}
}

class B2UOrder extends Order {
	public  function __construct($order) {
		parent::__construct($order, 'BRL', 0, 1);
	}
}

class MBTCOrder extends Order {
	public  function __construct($order) {
		parent::__construct($order, 'BRL', 0, 1);
	}
}
class NegocieCoinsOrder extends Order {
	public  function __construct($order) {
		parent::__construct($order, 'BRL', 'price', 'quantity');
	}
}

class BasebitOrder extends Order {
	public  function __construct($order) {
		parent::__construct($order, 'BRL', 'price', 'quantity');
	}
}

class CoinbaseOrder extends Order {
	public  function __construct($order) {
		parent::__construct($order, 'USD', 0, 1);
	}
}

class KrakenOrder extends Order {
	public  function __construct($order) {
		parent::__construct($order, 'USD', 0, 1, 2);
	}
}

class BtceOrder extends Order {
	public  function __construct($order) {
		parent::__construct($order, 'USD', 0, 1);
	}
}

class BitstampOrder extends Order {
	public  function __construct($order) {
		parent::__construct($order, 'USD', 0, 1);
	}
}

class OKCoinOrder extends Order {
	public  function __construct($order) {
		parent::__construct($order, 'USD', 0, 1);
	}
}

abstract class GenericOrderbook {
	private $orderbook = array();
	abstract public function getOrderBook($operation);
	protected function getOrderBookByDepth($depth, $currency, $operation) {
		if (!array_key_exists($operation, $this->orderbook)) {
			$this->orderbook[$operation] = $this->getOrderBook($operation);
		}
		if ($depth < 0) {
			return $this->orderbook[$operation];
		}
		
		$curr_depth = 0;
		$depth_orders = array();
		foreach ($this->orderbook[$operation] as $order) {
			$curr_depth += $order->getVolume($currency);
			array_push($depth_orders, $order);
			if ($curr_depth >= $depth) {
				return $depth_orders;
			}
		}
		return false;
	}
	public function getVolumeOrdered($depth, $currency_from, $operation) {
		$orders = $this->getOrderBookByDepth($depth, $currency_from, $operation);
		if ($orders === false) return false;
		$current_volume = 0;
		$curr_depth = 0;
		foreach ($orders as $order) {
			$curr_depth += $order->getVolume($currency_from);
			if ($curr_depth <= $depth) {
				$current_volume += $order->getVolume($order->getOtherCurrency($currency_from));
			} else {
				$remaining_order = $depth - ($curr_depth - $order->getVolume($currency_from));
				$remaining_ordered = $remaining_order / $order->getPrice($currency_from);
				$current_volume += $remaining_ordered;
				break;
			}
		}
		return $current_volume;
	}
}

class FoxBitOrderbook extends GenericOrderbook {
	 public function getOrderBook($operation) {
		$orderbook = foxbit_api_query("orderbook");
		$orders = $orderbook[$operation];
		$ret = array();
		foreach ($orders as $order) {
			array_push($ret, new BlinktradeOrder($order));
		}
		return $ret;
	 }
}

class BitfinexOrderbook extends GenericOrderbook {
	public function getOrderBook($operation) {
		$orderbook = bitfinex_api_query('BTCUSD', 'book');
		$orders = $orderbook[$operation];
		$ret = array();
		foreach ($orders as $order) {
			array_push($ret, new BitfinexOrder($order));
		}
		return $ret;
	}
}

class B2UOrderbook extends GenericOrderbook {
	public function getOrderBook($operation) {
		$orderbook = b2u_api_query('orderbook');
		$orders = $orderbook[$operation];
		$ret = array();
		foreach ($orders as $order) {
			array_push($ret, new B2UOrder($order));
		}
		return $ret;
	}
}

class MBTCOrderbook extends GenericOrderbook {
	public function getOrderBook($operation) {
		$orderbook = mbtc_api_query('orderbook');
		$orders = $orderbook[$operation];
		$ret = array();
		foreach ($orders as $order) {
			array_push($ret, new MBTCOrder($order));
		}
		return $ret;
	}
}

class NegocieCoinsOrderbook extends GenericOrderbook {
	private function convert_operation($operation) {
		if ($operation == "asks") return "ask";
		return "bid";
	}
	public function getOrderBook($operation) {
		$orderbook = negociecoins_api_query('orderbook');
		$orders = $orderbook[$this->convert_operation($operation)];
		$ret = array();
		foreach ($orders as $order) {
			array_push($ret, new NegocieCoinsOrder($order));
		}
		return $ret;
	}
}

class BasebitOrderbook extends GenericOrderbook {
	public function getOrderBook($operation) {
		$orderbook = basebit_api_query('book');
		$orders = $orderbook['result'][$operation];
		$ret = array();
		foreach ($orders as $order) {
			array_push($ret, new BasebitOrder($order));
		}
		return $ret;
	}
}

class CoinbaseOrderbook extends GenericOrderbook {
	public function getOrderBook($operation) {
		$orderbook = coinbase_api_query('book', array('level' => 2));
		$orders = $orderbook[$operation];
		$ret = array();
		foreach ($orders as $order) {
			array_push($ret, new CoinbaseOrder($order));
		}
		return $ret;
	}
}

class KrakenOrderbook extends GenericOrderbook {
	public function getOrderBook($operation) {
		$orderbook = kraken_api_query('Depth', array('pair' => 'XBTUSD'));
		$orders = $orderbook['result']['XXBTZUSD'][$operation];
		$ret = array();
		foreach ($orders as $order) {
			array_push($ret, new KrakenOrder($order));
		}
		return $ret;
	}
}

class BtceOrderbook extends GenericOrderbook {
	public function getOrderBook($operation) {
		$orderbook = btce_api_query('depth');
		$orders = $orderbook['btc_usd'][$operation];
		$ret = array();
		foreach ($orders as $order) {
			array_push($ret, new BtceOrder($order));
		}
		return $ret;
	}
}

class BitstampOrderbook extends GenericOrderbook {
	public function getOrderBook($operation) {
		$orderbook = bitstamp_api_query('order_book');
		$orders = $orderbook[$operation];
		$ret = array();
		foreach ($orders as $order) {
			array_push($ret, new BitstampOrder($order));
		}
		return $ret;
	}
}

function sort_by_first($a, $b) {
	if ($a[0] == $b[0]) {
		return 0;
	}
	return ($a[0] < $b[0]) ? -1 : 1;
}

class OKCoinOrderbook extends GenericOrderbook {
	public function getOrderBook($operation) {
		$orderbook = okcoin_api_query('depth', array('symbol' => 'btc_usd'));
		$orders = $orderbook[$operation];
		if ($operation == 'asks') $orders = array_reverse( $orders );
		$ret = array();
		foreach ($orders as $order) {
			array_push($ret, new OKCoinOrder($order));
		}
		return $ret;
	}
}

abstract class FeeCalculator {
	private $fees = array();
	public function setFee($currency, $operation, $variable, $fixed) {
		if (!array_key_exists($currency, $this->fees)) {
			$this->fees[$currency] = array();
		}
		if (!array_key_exists($operation, $this->fees[$currency])) {
			$this->fees[$currency][$operation] = array();
		}
		$this->fees[$currency][$operation]['variable'] = $variable;
		$this->fees[$currency][$operation]['fixed'] = $fixed;
	}
	public function getFee($currency, $operation, $type) {
		if (!array_key_exists($currency, $this->fees)) {
			return 0;
		}
		if (!array_key_exists($operation, $this->fees[$currency])) {
			return 0;
		}
		if (!array_key_exists($type, $this->fees[$currency][$operation])) {
			return 0;
		}
		return $this->fees[$currency][$operation][$type];
	}
	
	protected function applyFee($value, $currency, $operation) {
		$variable = $this->getFee($currency, $operation, 'variable');
		$fixed = $this->getFee($currency, $operation, 'fixed');
		return $value * ( 1.0 - $variable) - $fixed;
	}
	
	public function applyDepositFee($value, $currency) {
		return $this->applyFee($value, $currency, 'deposit'); 
	}
	public function applyWithdrawalFee($value, $currency) {
		return $this->applyFee($value, $currency, 'withdrawal'); 
	}
	public function applyExecutingOrderFee($value, $currency) {
		return $this->applyFee($value, $currency, 'executing'); 
	}
	public function applyExecutedOrderFee($value, $currency) {
		return $this->applyFee($value, $currency, 'executed'); 
	}
}

class FoxbitFeeCalculator extends FeeCalculator {
	private $order_fee = 0.0025;
	public  function __construct() {
		$this->setFee('BTC', 'withdrawal', 0, 0.0001);
		$this->setFee('BRL', 'withdrawal', 0.0139, 0);
		$this->setFee('BTC', 'executing', $this->order_fee, 0);
		$this->setFee('BRL', 'executing', $this->order_fee, 0);
		$this->setFee('BTC', 'executed', $this->order_fee, 0);
		$this->setFee('BRL', 'executed', $this->order_fee, 0);
	}
}

class BitfinexFeeCalculator extends FeeCalculator {
	private $order_fee = 0.002;
	public  function __construct() {
		$this->setFee('BTC', 'withdrawal', 0, 0.0001);
		$this->setFee('USD', 'withdrawal', 0, 20);
		$this->setFee('USD', 'deposit', 0, 20);
		$this->setFee('BTC', 'executing', $this->order_fee, 0);
		$this->setFee('USD', 'executing', $this->order_fee, 0);
		$this->setFee('BTC', 'executed', $this->order_fee, 0);
		$this->setFee('USD', 'executed', $this->order_fee, 0);
	}
}


class B2UFeeCalculator extends FeeCalculator {
	private $order_fee = 0.0025;
	private $executing_order_fee = 0.006;

	public  function __construct() {
		$this->setFee('BTC', 'deposit', 0, 0);
		$this->setFee('BRL', 'deposit', 0.0189, 0);
		$this->setFee('BTC', 'withdrawal', 0, 0.0001);
		$this->setFee('BRL', 'withdrawal', 0.0189, 0);
		$this->setFee('BTC', 'executing', $this->executing_order_fee, 0);
		$this->setFee('BRL', 'executing', $this->executing_order_fee, 0);
		$this->setFee('BTC', 'executed', $this->order_fee, 0);
		$this->setFee('BRL', 'executed', $this->order_fee, 0);
	}
}

class MBTCFeeCalculator extends FeeCalculator {
	private $order_fee = 0.003;
	private $executing_order_fee = 0.007;

	public  function __construct() {
		$this->setFee('BTC', 'deposit', 0, 0);
		$this->setFee('BRL', 'deposit', 0.0199, 2.90);
		$this->setFee('BTC', 'withdrawal', 0, 0.0001);
		$this->setFee('BRL', 'withdrawal', 0.0199, 2.90);
		$this->setFee('BTC', 'executing', $this->executing_order_fee, 0);
		$this->setFee('BRL', 'executing', $this->executing_order_fee, 0);
		$this->setFee('BTC', 'executed', $this->order_fee, 0);
		$this->setFee('BRL', 'executed', $this->order_fee, 0);
	}
}

class NegocieCoinsFeeCalculator extends FeeCalculator {
	private $order_fee = 0.002;
	private $executing_order_fee = 0.003;

	public  function __construct() {
		$this->setFee('BTC', 'withdrawal', 0, 0.0001);
		$this->setFee('BRL', 'withdrawal', 0, 7.50);
		$this->setFee('BTC', 'executing', $this->executing_order_fee, 0);
		$this->setFee('BRL', 'executing', $this->executing_order_fee, 0);
		$this->setFee('BTC', 'executed', $this->order_fee, 0);
		$this->setFee('BRL', 'executed', $this->order_fee, 0);
	}
}

class BasebitFeeCalculator extends FeeCalculator {
	public  function __construct() {
		$this->setFee('BTC', 'withdrawal', 0, 0.0001);
		$this->setFee('BRL', 'withdrawal', 0.0149, 0);
		$this->setFee('BRL', 'deposit', 0.0149, 0);
		$this->setFee('BTC', 'executing', 0.006, 0);
		$this->setFee('BRL', 'executing', 0.006, 0);
		$this->setFee('BTC', 'executed', 0.0025, 0);
		$this->setFee('BRL', 'executed', 0.0025, 0);
	}
}

class CoinbaseFeeCalculator extends FeeCalculator {
	public  function __construct() {
		$this->setFee('BTC', 'withdrawal', 0, 0.0001);
		$this->setFee('BTC', 'executing', 0.0025, 0);
		$this->setFee('USD', 'executing', 0.0025, 0);
	}
}

class KrakenFeeCalculator extends FeeCalculator {
	public  function __construct() {
		$this->setFee('BTC', 'withdrawal', 0, 0.0001);
		$this->setFee('USD', 'withdrawal', 0, 20);
		$this->setFee('USD', 'deposit', 0, 20);
		$this->setFee('BTC', 'executing', 0.0035, 0);
		$this->setFee('USD', 'executing', 0.0035, 0);
		$this->setFee('BTC', 'executed', 0.0035, 0);
		$this->setFee('USD', 'executed', 0.0035, 0);
	}
}

class BtceFeeCalculator extends FeeCalculator {
	public  function __construct() {
		$this->setFee('BTC', 'withdrawal', 0, 0.01);
		$this->setFee('USD', 'withdrawal', 0.02, 0);
		$this->setFee('USD', 'deposit', 0.02, 0);
		$this->setFee('BTC', 'executing', 0.002, 0);
		$this->setFee('USD', 'executing', 0.002, 0);
		$this->setFee('BTC', 'executed', 0.002, 0);
		$this->setFee('USD', 'executed', 0.002, 0);
	}
}

class BitstampFeeCalculator extends FeeCalculator {
	public  function __construct() {
		$this->setFee('BTC', 'withdrawal', 0, 0.0001);
		$this->setFee('USD', 'withdrawal', 0, 15);
		$this->setFee('USD', 'deposit', 0, 15);
		$this->setFee('BTC', 'executing', 0.0025, 0);
		$this->setFee('USD', 'executing', 0.0025, 0);
		$this->setFee('BTC', 'executed', 0.0025, 0);
		$this->setFee('USD', 'executed', 0.0025, 0);
	}
}

class OKCoinFeeCalculator extends FeeCalculator {
	public  function __construct() {
		$this->setFee('BTC', 'withdrawal', 0, 0.0001);
		$this->setFee('USD', 'withdrawal', 0, 15);
		$this->setFee('USD', 'deposit', 0, 0);
		$this->setFee('BTC', 'executing', 0.002, 0);
		$this->setFee('USD', 'executing', 0.002, 0);
		$this->setFee('BTC', 'executed', 0.002, 0);
		$this->setFee('USD', 'executed', 0.002, 0);
	}
}

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

$brls = array($foxbit, $b2u, $mbtc, $negocie, $basebit);
$usds = array($bitfinex, $coinbase, $kraken, $bitstamp, $btce, $okcoin);
$usds = array($coinbase);

$pairs_buy = array();
$pairs_sell = array();
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
// // BRL -> BRL
// foreach ($brls as $brl1) {
// 	foreach ($brls as $brl2) {
// 		array_push($pairs, array($brl1, $brl2));
// 	}
// }

function effective_values($map) {
	$map['deposit_fee'] = $map['initial'] - $map['deposited'];
	$map['effective_rate'] = $map['sold']/$map['deposited'];
	$map['transaction_fee'] = $map['sold'] - $map['bought'];
	$map['no_withdrawal_rate'] = $map['bought']/$map['initial'];
	$map['withdrawal_fee'] = $map['bought'] - $map['withdrawn'];
	$map['withdrawn_rate'] = $map['withdrawn']/$map['initial'];
	return $map;
}

function getValueOrders($value, $currency_pair, $book, $feecalc, $operation, $withdrawal = true) {
	$ret = array('initial' => $value);
	$buy = $feecalc->applyDepositFee($value, $currency_pair[0]);
	$ret['deposited'] = $buy;
	$bought = $book->getVolumeOrdered($buy, $currency_pair[0], $operation);
 	$ret['sold'] = $bought;
	$bought = $feecalc->applyExecutingOrderFee($bought, $currency_pair[1]);
	$ret['bought'] = $bought;
// 	print "$bought\n";
	$bought = $feecalc->applyWithdrawalFee($bought,  $currency_pair[1]);
	$ret['withdrawn'] = $bought;
// 	print "$bought\n-----\n";
	return effective_values($ret);
}

function buildDetailedExchangeMap($value, $pair) {
	$book_from = $pair[0][0];
	$fee_from = $pair[0][1];
	$currency_pair_from = array($pair[0][2], 'BTC');
	$bought = getValueOrders($value, $currency_pair_from, $book_from, $fee_from, 'asks');
	$book_to = $pair[1][0];
	$fee_to = $pair[1][1];
	$currency_pair_to = array('BTC', $pair[1][2]);
	$sold = getValueOrders($bought['withdrawn'], $currency_pair_to, $book_to, $fee_to, 'bids', false);
	
	$results = array(
			'origin' => array('name' => $pair[0][3],
					'results' => $bought),
			'destination' => array('name' => $pair[1][3],
					'results' => $sold),
			'rate' => ($bought['initial'] / $sold['withdrawn']),
			'rate_no_withdrawal' => ($bought['initial'] / $sold['bought'])
	);
	return $results;
}

function usage() {
	print "Usage: " . $argv[0] . " <maxamount> [<min amount>=10]";
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

$yahoo = yahoo_api_usdbrl();
$buy = $yahoo[0];
$sell = $yahoo[1];

$best_buy = array('rate_no_withdrawal' => 10e99);
for ($value = $value_min; $value <= $value_max; $value+=1) {
	foreach ($pairs_buy as $pair) {
		$results = buildDetailedExchangeMap($value, $pair);
		if ($results['rate_no_withdrawal'] > 0 && 
		    $results['rate_no_withdrawal'] < $best_buy['rate_no_withdrawal']) {
			$best_buy = $results;
		}
	
	}
}

$best_sell = array('rate_no_withdrawal' => 10e99);
for ($value = $value_min; $value <= $value_max; $value+=1) {
	foreach ($pairs_sell as $pair) {
		$results = buildDetailedExchangeMap($value, $pair);
		if ($results['rate_no_withdrawal'] > 0 && 
		    $results['rate_no_withdrawal'] < $best_sell['rate_no_withdrawal']) {
			$best_sell = $results;
		}
	
	}
}

//print_r($results);

//print_r($best);
print "BRL => BTC => USD (" . $best_buy['origin']['name'] . " => ". $best_buy['destination']['name'] . ")\n";
print $best_buy['rate_no_withdrawal'] . " (Buy)\n";

print "USD => BTC => BRL (" . $best_sell['origin']['name'] . " => ". $best_sell['destination']['name'] . ")\n";
print 1.0/$best_sell['rate_no_withdrawal'] . " (Sell)\n";

print "BRL => USD (Yahoo Finance)\n";
print $yahoo[0] . " (Buy) / " . $yahoo[1] . " (Sell)\n";

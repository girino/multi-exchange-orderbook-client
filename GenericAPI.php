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
		parent::__construct($order, 'USD', 'price', 'quantity');
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

$foxbit = array( new FoxBitOrderbook(),  new FoxbitFeeCalculator(), 'BRL', 'FOX' );
$b2u = array( new B2UOrderbook(),  new B2UFeeCalculator(), 'BRL', 'B2U' );
$mbtc = array( new MBTCOrderbook(),  new MBTCFeeCalculator(), 'BRL', 'MBTC' );
$negocie = array( new NegocieCoinsOrderbook(),  new NegocieCoinsFeeCalculator(), 'BRL', 'NEGOCIE' );
$bitfinex = array( new BitFinexOrderbook(),  new BitFinexFeeCalculator(), 'USD', 'BITFINEX' );

$brls = array($foxbit, $b2u, $mbtc, $negocie);
$usds = array($bitfinex);

$pairs = array();
// BRL -> USD
foreach ($brls as $brl) {
	foreach ($usds as $usd) {
		array_push($pairs, array($brl, $usd));
	}
}
// // USD -> BRL
// foreach ($brls as $brl) {
// 	foreach ($usds as $usd) {
// 		array_push($pairs, array($usd, $brl));
// 	}
// }
// // BRL -> BRL
// foreach ($brls as $brl1) {
// 	foreach ($brls as $brl2) {
// 		array_push($pairs, array($brl1, $brl2));
// 	}
// }

function getValueOrders($value, $currency_pair, $book, $feecalc, $operation, $withdrawal = true) {
	$buy = $feecalc->applyDepositFee($value, $currency_pair[0]);
	$buy = $feecalc->applyExecutingOrderFee($buy, $currency_pair[0]);
// 	print "-----\n$buy\n";
	$bought = $book->getVolumeOrdered($buy, $currency_pair[0], $operation);
// 	print "$bought\n";
	if ($withdrawal)
		$bought = $feecalc->applyWithdrawalFee($bought,  $currency_pair[1]);
// 	print "$bought\n-----\n";
	return $bought;
}

$value = $argv[1];
foreach ($pairs as $pair) {
	$book_from = $pair[0][0];
	$fee_from = $pair[0][1];
	$currency_pair_from = array($pair[0][2], 'BTC');
	$bought = getValueOrders($value, $currency_pair_from, $book_from, $fee_from, 'asks');
	$book_to = $pair[1][0];
	$fee_to = $pair[1][1];
	$currency_pair_to = array('BTC', $pair[1][2]);
	$sold = getValueOrders($bought, $currency_pair_to, $book_to, $fee_to, 'bids', false);

	$rate = $value / $sold;
	$rrate = $sold / $value;

	print $pair[0][3] . " => " . $pair[1][3] . "\n";
	print "$value " . ($pair[0][2]) . " => $sold " . $pair[1][2] . "\n";
	print "Rate: $rate ($rrate)\n";
	print "\n";
}

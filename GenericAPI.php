<?php

require_once('blinktrade_api.php');
require_once('bitfinex_api.php');
require_once('bitcointoyou_api.php');

abstract class Order {
	private $currency_from;
	private $currency_to;
	
	public  function __construct($currency_from, $currency_to) {
		$this->currency_from = $currency_from;
		$this->currency_to = $currency_to;
	}
	
	public function getCurrencyPair() {
		return array($currency_from, $currency_to);
	}
	
	public function getCurrencyFrom() {
		return $currency_from;
	}
	
	public function getCurrencyTo() {
		return $currency_to;
	}
	
	public function getOtherCurrency($currency) {
		if ($currency == $this->currency_from) return $this->currency_to;
		return $this->currency_from;
	}
	
	abstract public function getPrice($currency);
	abstract public function getVolume($currency);
	abstract public function getId();
}

class BlinktradeOrder extends Order {
	
	private $order_map;
	private $price_currency = 'BRL';
	private $volume_currency = 'BTC';
	
	public  function __construct($order, $currency_from = 'BTC', $currency_to = 'BRL') {
		parent::__construct($currency_from, $currency_to);
		$this->order_map = $order;
	}
	
	public function getPrice($currency) {
		if 	($currency == $this->price_currency) {
			return $this->order_map[0];
		} else {
			return 1.0 / $this->order_map[0];
		}
	}
	
	public function getVolume($currency) {
		if 	($currency == $this->volume_currency) {
			return $this->order_map[1];
		} else {
			return $this->order_map[0] * $this->order_map[1];
		}
	}
	
	public function getId() {
		return $this->order_map[2];
	}
}

class BitfinexOrder extends Order {

	private $order_map;
	private $price_currency = 'USD';
	private $volume_currency = 'BTC';

	public  function __construct($order, $currency_from = 'BTC', $currency_to = 'USD') {
		parent::__construct($currency_from, $currency_to);
		$this->order_map = $order;
	}

	public function getPrice($currency) {
		if 	($currency == $this->price_currency) {
			return $this->order_map['price'];
		} else {
			return 1.0 / $this->order_map['price'];
		}
	}

	public function getVolume($currency) {
		if 	($currency == $this->volume_currency) {
			return $this->order_map['amount'];
		} else {
			return $this->order_map['price'] * $this->order_map['amount'];
		}
	}

	public function getId() {
		return $this->order_map['timestamp'];
	}
}

class B2UOrder extends Order {
	
	private $order_map;
	private $price_currency = 'BRL';
	private $volume_currency = 'BTC';
	
	public  function __construct($order, $currency_from = 'BTC', $currency_to = 'BRL') {
		parent::__construct($currency_from, $currency_to);
		$this->order_map = $order;
	}
	
	public function getPrice($currency) {
		if 	($currency == $this->price_currency) {
			return $this->order_map[0];
		} else {
			return 1.0 / $this->order_map[0];
		}
	}
	
	public function getVolume($currency) {
		if 	($currency == $this->volume_currency) {
			return $this->order_map[1];
		} else {
			return $this->order_map[0] * $this->order_map[1];
		}
	}
	
	public function getId() {
		return 0;
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

abstract class FeeCalculator {
	public function applyDepositFee($value, $currency) { return $value; }
	public function applyWithdrawalFee($value, $currency) { return $value; }
	public function applyExecutingOrderFee($value, $currency) { return $value; }
	public function applyExecutedOrderFee($value, $currency) { return $value; }
}

class FoxbitFeeCalculator extends FeeCalculator {
	private $order_fee = 0.0025;
	private $tx_fee = 0.0001;
	private $withdrawal_fee = 0.0139;
	public function applyWithdrawalFee($value, $currency) { 
		if ($currency == 'BTC')
			return $value - $this->tx_fee;
		else 
			return $value * (1-$this->withdrawal_fee); 
	}
	public function applyExecutingOrderFee($value, $currency) { 
		return $value * (1-$this->order_fee); 
	}
	public function applyExecutedOrderFee($value, $currency) { 
		return applyExecutingOrderFee($value, $currency); 
	}
}

class BitfinexFeeCalculator extends FeeCalculator {
	private $order_fee = 0.002;
	public function applyExecutingOrderFee($value, $currency) {
		return $value * (1-$this->order_fee);
	}
	public function applyExecutedOrderFee($value, $currency) {
		return applyExecutingOrderFee($value, $currency);
	}
}


class B2UFeeCalculator extends FeeCalculator {
	private $order_fee = 0.0025;
	private $executing_order_fee = 0.006;
	private $tx_fee = 0.0001;
	private $withdrawal_fee = 0.0299;
	public function applyWithdrawalFee($value, $currency) { 
		if ($currency == 'BTC')
			return $value - $this->tx_fee;
		else 
			return $value * (1-$this->withdrawal_fee); 
	}
	public function applyExecutingOrderFee($value, $currency) {
		return $value * (1-$this->order_fee);
	}
	public function applyExecutedOrderFee($value, $currency) {
		return $value * (1-$this->executing_order_fee);
	}
}

$foxbit = array( new FoxBitOrderbook(),  new FoxbitFeeCalculator(), 'BRL' );
$b2u = array( new B2UOrderbook(),  new B2UFeeCalculator(), 'BRL' );
$bitfinex = array( new BitFinexOrderbook(),  new BitFinexFeeCalculator(), 'USD' );

$pairs = array( array($foxbit, $bitfinex),
		array($b2u, $bitfinex),
		array($b2u, $foxbit),
		array($bitfinex, $foxbit),
		array($bitfinex, $b2u),
		array($foxbit, $b2u),
	);

function getValueOrders($value, $currency_pair, $book, $feecalc, $operation, $withdrawal = true) {
	$buy = $feecalc->applyExecutingOrderFee($value, $currency_pair[0]);
	$bought = $book->getVolumeOrdered($buy, $currency_pair[0], $operation);
	if ($withdrawal)
		$bought = $feecalc->applyWithdrawalFee($bought,  $currency_pair[1]);
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
	$currency_pair_to = array('BTC', $pair[1][2], 'BTC');
	$sold = getValueOrders($bought, $currency_pair_to, $book_to, $fee_to, 'bids', false);

	$rate = $value / $sold;
	$rrate = $sold / $value;
	
	print "$value " . ($pair[0][2]) . " => $sold " . $pair[1][2] . "\n";
	print "Rate: $rate ($rrate)\n";
	print "\n";
}

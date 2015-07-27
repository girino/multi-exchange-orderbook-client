<?php

require_once('blinktrade_api.php');
require_once('bitfinex_api.php');

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

$book1 = new FoxBitOrderbook();
$fee1 = new FoxbitFeeCalculator();
$brl_buy = $fee1->applyExecutingOrderFee($argv[1], 'BRL');
$btc_buy = $book1->getVolumeOrdered($brl_buy, 'BRL', 'asks');
$btc_buy = $fee1->applyWithdrawalFee($btc_buy, 'BTC');
print $argv[1] . " BRL => $btc_buy BTC" . "\n";
$book2 = new BitFinexOrderbook();
$fee2 = new BitfinexFeeCalculator();
$btc_buy = $fee2->applyExecutingOrderFee($btc_buy, 'BTC');
$usd_buy = $book2->getVolumeOrdered($btc_buy, 'BTC', 'bids');
print "$btc_buy BTC => $usd_buy USD\n";
print "Buy rate: " . ($argv[1]/$usd_buy) . "\n";

// sell
$usd_sell = $fee2->applyExecutingOrderFee($usd_buy, 'USD');
$btc_sell = $book2->getVolumeOrdered($usd_sell, 'USD', 'asks');
$btc_sell = $fee2->applyWithdrawalFee($btc_sell, 'BTC');
print "$usd_buy USD => $btc_sell BTC" . "\n";
$btc_sell = $fee1->applyExecutingOrderFee($btc_sell, 'BTC');
$brl_sell = $book1->getVolumeOrdered($btc_sell, 'BTC', 'bids');
$brl_sell = $fee1->applyWithdrawalFee($brl_sell, 'BRL');
print "$btc_sell BTC => $brl_sell BRL\n";
print "Sell rate: " . ($brl_sell/$usd_buy) . "\n";

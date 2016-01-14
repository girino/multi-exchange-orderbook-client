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
require_once 'query_functions.php';

// implementation of orders, orderbooks and feecalculators for several brazilian and internation exchanges.
// if you wish to extend this code, take a look on the examples here.

// orders
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

// orderbooks

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

class KrakenEuroOrderbook extends GenericOrderbook {
	public function getOrderBook($operation) {
		$orderbook = kraken_api_query('Depth', array('pair' => 'XBTEUR'));
		$orders = $orderbook['result']['XXBTZEUR'][$operation];
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

// fee calculators

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
?>

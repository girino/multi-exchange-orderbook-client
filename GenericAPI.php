<?php

// This class represents a single order. Its used to standardize the interface provided by json APIs
abstract class Order {
	private $currency;
	private $order_map;
	private $index_price;
	private $index_amount;
	private $index_id;
	
	// constructs a new Order from a map (usually parsed from json).
	// Inputs:
	//   * $order => the map returned by the REST API (see query_functions.php)
	//   * $currency => the currency represented by this order (usually set by subclasses)
	//   * $index_price => the array index used for the price from the $order map. (see examples)
	//   * $index_amount => the array index used for the volume from the $order map. (see examples)
	//   * $index_id => the array index used for the order ID from the $order map. (see examples)
	//                  Not all exchanges provide ID, so this is an optional field.
	public  function __construct($order, $currency, $index_price, $index_amount, $index_id=false) {
		$this->currency = $currency;
		$this->order_map = $order;
		$this->index_price = $index_price;
		$this->index_amount = $index_amount;
		$this->index_id = $index_id;
	}
	
	// getter for currency set on constructor
	public function getCurrency() {
		return $currency;
	}
	
	// recovers the price from the provided map.
	public function getPrice($currency) {
		$price = $this->order_map[$this->index_price];
		if ($currency == 'BTC') return 1.0/$price;
		return $price;
	}
	
	// recovers the volume/amount from the provided map.
	public function getVolume($currency) {
		$volume = $this->order_map[$this->index_amount];
		if ($currency == 'BTC')	return $volume;
		return $this->order_map[$this->index_price] * $volume;
	}
	
	// recovers the ID from the provided map.
	public function getId() {
		if ($this->index_id === false) {
			return false;
		}
		return $this->order_map[$this->index_id];
	}
	
	// helper function to find out what is the corrency we are converting to
	public function getOtherCurrency($currency) {
		if ($currency == 'BTC') return $this->currency;
		return 'BTC';
	}
}

// This class represents an orderbook. Methods for pruning and traversing teh orderbook are
// implemented here
abstract class GenericOrderbook {
	private $orderbook = array();
	
	// this method should be implemented by subclasses to recover 
	// the orderbook array (and array of maps, each map being an order).
	// See examples to see how it works.
	abstract public function getOrderBook($operation);
	
	// Recovers the orderbook from a REST provider (see query_functions.php).
	// If requested, it also prunes the orderbook so it contains only the orders 
	// that will be used to fulfill the requested volume.
	// Inputs:
	//   * $depth => this is the intended volume to exchange. -1 to get everything.
	//   * $currency => indicates the currency in wich the volume is represented
	//   * $operation => either "asks" or "bids". Indicates wich side of the
	//                   orderbook to examine.
	// Outputs:
	//   * returns a list of orders (class Order above) that are enough to fullfil 
	//             the requested volume. If volume is -1, returns all the orders
	//             provided by the API. Keep in mind that the API sometimes limits
	//             the provided orderbook size.
	protected function getOrderBookByDepth($depth, $currency, $operation) {
		if (!array_key_exists($operation, $this->orderbook)) {
			try {
				$this->orderbook[$operation] = $this->getOrderBook($operation);
			} catch (Exception $e) {
				print "Exception loading file: " . $e->getMessage() . "\n";
				$this->orderbook[$operation] = false;
			}
		}
		if ($this->orderbook[$operation] === false) {
			return $this->orderbook[$operation];
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
	
	// Calculates how much can we buy/sell with a certain amount of the original currency.
	// Inputs:
	//   * $depth => this is the intended volume to exchange. -1 to get everything.
	//   * $currency => indicates the currency in wich the volume is represented
	//   * $operation => either "asks" or "bids". Indicates wich side of the
	//                   orderbook to examine.
	// Outputs:
	//   * returns the exact amount that will be bought/sold if an order of this size
	//             is placed.
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

// This class is used to calculate the fees for a given exchange.
// I did not include this in the "orderbook" class above since this 
// can change often and vary from user to user. Please see examples 
// for how to subclass and implement it.
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

// utility functions to implement orderbook comparison

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
	if ($bought === false) {
		return false;
	}
 	$ret['sold'] = $bought;
	$bought = $feecalc->applyExecutingOrderFee($bought, $currency_pair[1]);
	$ret['bought'] = $bought;
// 	print "$bought\n";
	$bought = $feecalc->applyWithdrawalFee($bought,  $currency_pair[1]);
	$ret['withdrawn'] = $bought;
// 	print "$bought\n-----\n";
	return effective_values($ret);
}

// This function builds a detailed map with the operations between a pair of exchanges for a given value
// Inputs: 
//  * $value => the value of the operation you wish to compare
//  * $pair  => a pair of exchanges to execute the operations from
//              Each exchange must have the followin format:
//              array( new MyOrderbook(), // an orderbook subclass  
//                     new MyFeeCalculator(), // a feecalculator subclass 
//                     'BRL', // The "fiat" used on the exchnage (currently USD or BRL only)
//                     'EXCHANGENAME' // the name of the exchange (for display purposes) 
//	            );
function buildDetailedExchangeMap($value, $pair) {
	$book_from = $pair[0][0];
	$fee_from = $pair[0][1];
	$currency_pair_from = array($pair[0][2], 'BTC');
	$bought = getValueOrders($value, $currency_pair_from, $book_from, $fee_from, 'asks');
	if ($bought === false) return false;
	$book_to = $pair[1][0];
	$fee_to = $pair[1][1];
	$currency_pair_to = array('BTC', $pair[1][2]);
	$sold = getValueOrders($bought['withdrawn'], $currency_pair_to, $book_to, $fee_to, 'bids', false);
	if ($sold === false) return false;
	
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

// function, finds the best price among all listed exchanges for values
// in the given interval
// Inputs:
//   * $pairs => a list of pairs to be passed to buildDetailedExchangeMap (see buildDetailedExchangeMap)
//   * $min, $max => rango of values to evaluate
//   * $step => distance between the comapred values. 
// Example: 
//    find_best_rate($pairs, 500, 800, 100) // compare orderbook for values 500, 600, 700 and 800
function find_best_rate($pairs, $min, $max, $step=1) {
	$best = array('rate_no_withdrawal' => 10e99);
	foreach ($pairs as $pair) {
		//print $pair[0][3] . " => " . $pair[1][3] ."\n";
		for ($value = $min; $value <= $max; $value+=$step) {
			$results = buildDetailedExchangeMap($value, $pair);
			if ($results !== false && $results['rate_no_withdrawal'] > 0 &&
					$results['rate_no_withdrawal'] < $best['rate_no_withdrawal']) {
						$best = $results;
					}
					if ($results === false) {
						//print "Broken at $value\n";
						break;
					}
		}
	}
	return $best;
}

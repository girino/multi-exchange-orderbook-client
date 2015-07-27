<?php
$fees = array(
	'FOXBIT' => array(
		'FIXED' => array(
			'TX' => 0.0001,
		),
		'VARIABLE' => array(
			'BUY' => 0.0025,
		),
	),
	'BITFINEX' => array(
		'VARIABLE' => array(
			'SELL' => 0.002,
		),
	),
);

function get_fee($value, $exchange, $operation) {
	global $fees;
	$ret = 0;
	$exchange_map = $fees[strtoupper($exchange)];
	if (array_key_exists('FIXED', $exchange_map)) {
		$fixed_map = $exchange_map['FIXED'];
		if (array_key_exists(strtoupper($operation), $fixed_map)) {
			$ret += $fixed_map[strtoupper($operation)];
		}
	}
	if (array_key_exists('VARIABLE', $exchange_map)) {
		$variable_map = $exchange_map['VARIABLE'];
		if (array_key_exists(strtoupper($operation), $variable_map)) {
			$fee = $variable_map[strtoupper($operation)];
			$ret += $fee * $value;
		}
	}
	return $ret;
}

function discount_fee($value, $exchange, $operation) {
	return $value - get_fee($value, $exchange, $operation);
}

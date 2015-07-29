<?php

$cache = array();
function api_query($url, array $req = array()) {

	global $cache;
	$post_data = http_build_query($req, '', '&');
	$url = "$url?$post_data";
	
	if (!array_key_exists($url, $cache)) {
		print "Loading: $url\n";
		// our curl handle (initialize if required)
		static $ch = null;
		if (is_null($ch)) {
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; Girino Generic API PHP client; '.php_uname('s').'; PHP/'.phpversion().')');
		}
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
	
		// run the query
		$res = curl_exec($ch);
		
		if ($res === false) throw new Exception('Could not get reply: '.curl_error($ch));
		$dec = json_decode($res, true);
		if (!$dec) throw new Exception('Invalid data received, please make sure connection is working and requested API exists');
		
		$cache[$url] = $dec;
	}
	return $cache[$url];
}

function b2u_api_query($method, array $req = array()) {
	$url = "http://bitcointoyou.com/API/$method.aspx";
	return api_query($url, $req);
}

function bitfinex_api_query($currency, $method, array $req = array()) {

	$api_ver = 'v1';
	$url = "https://api.bitfinex.com/$api_ver/$method/$currency";

	return api_query($url, $req);
}

function blinktrade_api_query($currency, $method, array $req = array()) {

	$api_ver = 'v1';
	$url = "https://api.blinktrade.com/api/$api_ver/$currency/$method";
	return api_query($url, $req);
}

function foxbit_api_query($method, array $req = array()) {
	return blinktrade_api_query("BRL", $method, $req);
}

$mbtc_cache = false;
function mbtc_api_query($method, array $req = array()) {
	global $mbtc_cache;
	$url = "https://www.mercadobitcoin.com.br/api/$method/";
	if ($mbtc_cache === false) {
		$mbtc_cache = api_query($url, $req);
	}
	return $mbtc_cache;
}

function negociecoins_api_query($method, array $req = array()) {
	$url = "http://www.negociecoins.com.br/api/v3/btcbrl/$method";
	return api_query($url, $req);
}

function basebit_api_query($method, array $req = array()) {
	$url = "http://www.basebit.com.br/$method" . "-BTC_BRL";
	return api_query($url, $req);
}

function coinbase_api_query($method, array $req = array('level' => 2)) {
	$url = "https://api.exchange.coinbase.com/products/BTC-USD/$method";
	return api_query($url, $req);
}


function kraken_api_query($method, array $req = array('pair' => 'XBTUSD')) {
	$url = "https://api.kraken.com/0/public/$method";
	return api_query($url, $req);
}

function bitstamp_api_query($method, array $req = array()) {
	$url = "https://www.bitstamp.net/api/$method/";
	return api_query($url, $req);
}

function btce_api_query($method, array $req = array()) {
	$url = "https://btc-e.com/api/3/$method/btc_usd";
	return api_query($url, $req);
}

function okcoin_api_query($method, array $req = array('symbol' => 'btc_usd')) {
	$url = "https://www.okcoin.com/api/v1/$method.do";
	return api_query($url, $req);
}

function yahoo_api_query($query) {
	$req = array(
			'q' => $query, 
			'format' => 'json',
			'env' => 'store://datatables.org/alltableswithkeys'
	);
	$url = "http://query.yahooapis.com/v1/public/yql";
	return api_query($url, $req);
}

function yahoo_api_usdbrl() {
	$results = yahoo_api_query("select * from yahoo.finance.xchange where pair ='USDBRL'");
	return array($results['query']['results']['rate']['Ask'], 
					$results['query']['results']['rate']['Bid']);
}
?>

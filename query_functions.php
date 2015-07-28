<?php

function api_query($url, array $req = array()) {

	$post_data = http_build_query($req, '', '&');
	$url = "$url?$post_data";
	
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
	return $dec;
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

function mbtc_api_query($method, array $req = array()) {
	$url = "https://www.mercadobitcoin.com.br/api/$method/";
	return api_query($url, $req);
}

function negociecoins_api_query($method, array $req = array()) {
	$url = "http://www.negociecoins.com.br/api/v3/btcbrl/$method";
	return api_query($url, $req);
}

?>
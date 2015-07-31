# blinktrade-client
This project started as a client for the blinktrade API, but it evolved nto something 
else entirely. Sorry for that

So, this is a command line PHP utility to find arbitrage oportunities among brazilian 
and international exchanges.
It is flexible enough so you can add new exchanges, new coins or new fiat currencies, 
provided there is a REST API for retrieving the orderbook, and it follows the standard 
pattern of returning an array of pairs or triplets representing the orders.

examples: 

php brl_to_usd_comparator.php 1000 # finds the best rates for exactly 1000 BRL or 1000 USD
php brl_to_usd_comparator.php 1000 100 # finds the best rates for any value between 100 and 1000 USD or BRL
php brl_to_usd_comparator.php 1000 100 10 # finds the best rates for any value between 100 and 1000 USD or BRL in steps of 10 BRL or USD

See the comments in teh code for more information:
* If you just want to use what is already done, see brl_to_usd_comparator.php
* If you want to add new exchange implementations, see ExchangeAPIs.php and GenericAPI.php
* If you want to know how it works, read everything and hack the code a bit.

## License
(see LICENSE file)

Copyright (c) 2003-2015 Girino Vey.

This software is licenced under Girino's Anarchist License.

Permission to use this software, modify and distribute it, or parts of
it, is granted to everyone who wishes provided that the  conditions
in the Girino's Anarchist License are met. Please read it on the link
bellow.

The full license is available at: http://girino.org/license 
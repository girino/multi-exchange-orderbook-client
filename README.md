# multi-exchange-orderbook-client
This project started as a client for the blinktrade API, but it evolved into something 
else entirely. Sorry for that

So, this is a command line PHP utility to find arbitrage oportunities among brazilian 
and international exchanges.
It is flexible enough so you can add new exchanges, new coins or new fiat currencies, 
provided there is a REST API for retrieving the orderbook, and it follows the standard 
pattern of returning an array of pairs or triplets representing the orders.

examples: 

php GenericAPI.php 1000 # finds the best rates for exactly 1000 BRL or 1000 USD
php GenericAPI.php 1000 100 # finds the best rates for any value between 100 and 1000 USD or BRL
php GenericAPI.php 1000 100 10 # finds the best rates for any value between 100 and 1000 USD or BRL in steps of 10 BRL or USD

Don't worry. I wil lrename the project to something more useful... eventually...

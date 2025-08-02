<?php
/** example-01.php
 * Simple Http/Client example
 * @since 2025-05-16
 */

error_reporting(E_ALL); // report all errors

require_once __DIR__.'/../vendor/autoload.php'; // "vendor" folder should be beside "sample" & "src" folders

try {
	$url = 'https://example.com/';
	$webClient = new \Http\Client; // initialize the web client
	$request = new \Http\Request(new \Http\Uri($url)); // create the web request
	$response = $webClient->sendRequest($request); // <-- Exec the request. May throw an exception

	$data = $response->jsonDecode(); // It's a kind of magic...! But it may throw an exception
	forEach ($data->records as $record) {
		// process each record
	}
}
catch (\Exception $exc) {
	error_log($exc);
}

// vim: noexpandtab

<?php
/** example-02.php
 * PSR-18 Http/Client example with a PSR-3 logger
 * @since 2025-05-16
 */

error_reporting(E_ALL); // report all errors

require_once __DIR__.'/../vendor/autoload.php'; // "vendor" folder should be beside "sample" & "src" folders

try {
	$headerStr = 'X-Key: FAKE';
	$logger = new Logger(); // PSR-3 logger to get logs for debugging
	$url = 'https://example.com/';

	$webClient = new \Http\Client($logger); // init web client with the logger

	$webContextArr = [ // as options in: https://www.php.net/manual/en/function.stream-context-create.php
		'header' => [$headerStr], // custom HTTP request header(s)
		'timeout' => 5, // max time in seconds
		'method' => 'GET',
	];

	$uri = new \Http\Uri($url); // Convert URL string to Uri object

	$request = new \Http\Request($uri, $webContextArr); // create the web request
	if (isSet($dataToSend) && is_string($dataToSend) && trim($dataToSend)!=='') {
		$request = $request->withBody($dataToSend); // Can be a file to upload instead of a string (soon)
	}

	$response = $webClient->sendRequest($request); // <-- Exec the request. May throw ClientException

	$data = $response->jsonDecode(); // It's a kind of magic...! But it may throw an exception
	forEach ($data->records as $record) {
		// process each record
	}
}
catch (\Exception $exc) {
	error_log($exc);
}

// vim: noexpandtab

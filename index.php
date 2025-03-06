<?php
use APISDK\ApiException;
ini_set('display_errors', 1); 
ini_set('display_startup_errors', 1);
ini_set('max_execution_time', 0);
set_time_limit(0);
error_reporting(E_ALL);

//chdir(__DIR__);

require_once(__DIR__.'/../vendor/APISDK/loader.php');
require_once(__DIR__.'/../vendor/autoload.php');
require_once(__DIR__.'/../vendor-bin/fcm/vendor/autoload.php');
require_once(__DIR__.'/../vendor-bin/netracuni/vendor/autoload.php');


set_error_handler(function ($severity, $message, $file, $line) {
	if (!(error_reporting() & $severity)) {
		// This error code is not included in error_reporting
		return;
	}
	throw new ApiException($message);
});


try{
	//Instantiate Api
	
	$config1 = [
	    'host' => '64.225.9.163',
	    'username' => 'xvvfqaxdrz',
	    'password' => '33KCMGtr95',
	    'dbname' => 'xvvfqaxdrz'
	];
	
	/* $config1 = [
			'host' => 'localhost',
			'username' => 'root',
			'password' => '',
			'dbname' => 'gym'
	]; */
	
	$db = new \Phlib\Db\Adapter($config1);
	
	$fp = fopen('php://input', 'rb');
	stream_filter_append($fp, 'dechunk', STREAM_FILTER_READ);
	$HTTP_RAW_POST_DATA = stream_get_contents($fp);
	
	$payload = json_decode($HTTP_RAW_POST_DATA, true);
	
	//$request = empty($payload)?$_REQUEST:$payload;
	$request = array_merge((array)$payload, (array)$_REQUEST);
	//mail("arsen@intechopen.com", "post", file_get_contents('php://input').json_encode($request, true));
	$api = @new \APISDK\Sdk($db, $request, new \Firebase\JWT\JWT());
}catch(\Throwable $e){
	//throw $e;
	//Return formated failed response
	return \APISDK\Api::setOutput(500, \APISDK\Api::getFailedResponse($e->getMessage()."\n\n".$e->getTraceAsString()));
}

//Return response
return \APISDK\Api::setOutput(200, $api->getResponse());

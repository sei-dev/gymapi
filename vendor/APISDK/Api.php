<?php
namespace APISDK;

use APISDK\ApiException;
use Firebase\JWT\JWT;
use APISDK\DbAdapters\CIAdapter;
use APISDK\DbAdapters\DBAdapter;
use CodeIgniter\Database\BaseConnection;

/**
 * Api class
 * 
 * @package APISDK
 * @author arsen.leontijevic
 * @version 1.0
 * @since 2019-09-26
 * @copyright APISDK
 *
 */
abstract class Api {
	
	
	/**
	 * 
	 * Constants
	 */
	const STATUS_SUCCESS = 'success';
	const STATUS_FAILED = 'fail';
	const TOKEN_INVALID = 'token_invalid';
	const TOKEN_EXPIRED = 'token_expired';
	const TOKEN_VALID = 'token_valid';
	
	/**
	 * 
	 * @var string
	 */
	protected $domain = "http://gymapi";
	/**
	 * 
	 * @var string
	 */
	private $key = "gympi123";
	
	/**
	 * Hashing algorythm
	 * @var string
	 */
	private $alg = 'HS256';
	
	/**
	 * 
	 * @var JWT
	 */
	protected $jwt = null;
	
	/**
	 *
	 * @var DbAdapterInterface
	 */
	protected $dbAdapter = null;
	
	/**
	 * 
	 * @var array
	 */
	protected $request = array();
	
	/**
	 *
	 * @var array
	 */
	protected $response = array();
	
	/**
	 *
	 * @var int
	 */
	protected $user_id = null;
	
	/**
	 * Instantiate Api library
	 * 
	 * @param mixed \CI_DB_driver | Other Adapters $db
	 * @param array $request
	 * @param \Firebase\JWT\JWT $jwt
	 */
	public function __construct($db, array $request, \Firebase\JWT\JWT $jwt)
	{
		//Convert db errors to exceptions
		$driver = new \mysqli_driver();
		$driver->report_mode = MYSQLI_REPORT_STRICT | MYSQLI_REPORT_ERROR;
		
		//Turn notices and warrnings to exceptions
		set_error_handler(function ($severity, $message, $file, $line) {
			if (!(error_reporting() & $severity)) {
				// This error code is not included in error_reporting
				return;
			}
			throw new ApiException($message);
		});
			
		
		//Set proper adapter
		if ($db instanceof BaseConnection)
		{
			$dbAdapter = new CIAdapter($db);
			$this->dbAdapter = $dbAdapter;
		}elseif ($db instanceof \Phlib\Db\Adapter)
		{
			$dbAdapter = new DBAdapter($db);
			$this->dbAdapter = $dbAdapter;
		}else{
			throw new ApiException("Unknown Db Adapter");
		}
			
		$this->jwt = $jwt;
		$this->request = $request;
		$this->processRequest($request);
		
	}
	
	abstract protected function processRequest(array $request);
	
	/**
	 * Get response formatted as array
	 * 
	 * @return array
	 */
	public function getResponse()
	{
		return $this->response;
	}
	
	/**
	 * 
	 * @param unknown $param
	 * @param unknown $default
	 * @return string|mixed
	 */
	public function getParam($param, $default = NULL)
	{
		return isset($this->request[$param])?$this->request[$param]:$default;
	}
	
	/**
	 *
	 * @param unknown $param
	 * @param unknown $default
	 * @return string|mixed
	 */
	public function getArray($param, $default = "[]")
	{
		if(isset($this->request[$param])){
			$param = $this->request[$param];
			$paramArray = json_encode(array_map('intval',(array)$param));
			return is_null($paramArray)?"[]":$paramArray;
		}else {
			return $default;
		}
	}
	
	
	
	public function getJsonFromArray($param, $default = "[]")
	{
			$paramArray = json_encode(array_map('intval',(array)$param));
			return is_null($paramArray)?$default:$paramArray;
	}
	
	/**
	 * Set and check response if properly formatted
	 *
	 * @return array
	 */
	public function setResponse(array $response)
	{
		if(!isset($response['status']) || !isset($response['message']) || !isset($response['result']))
		{
			throw new ApiException("Faild to set response, wrong format");
		}
		$this->response = $response;
		return $this;
	}
	
	/**
	 * Get failed response
	 * 
	 * @return array
	 */
	public static function getFailedResponse($message = "")
	{
		return self::formatResponse("fail", $message);
	}
	
	
	
	
	
	/**
	 * Make sure we get only required params
	 * 
	 * @param array $required
	 * @throws ApiException
	 * @return string[]
	 */
	protected function filterParams(array $required = array())
	{
		$result = [];
		foreach ($required as $one)
		{
			if ((!isset($this->request[$one]) || $this->request[$one] == "") && (!isset($this->request[$one]) || $this->request[$one] !=0))
			{
				throw new ApiException('This action requires params: ' . implode(" ", $required) . '. The "' . $one . '" is not set or is empty');
			}else{
				$result[$one] = $this->request[$one];
			}
		}
		return $result;
	}
	
	/**
	 * Get user id from access token
	 *
	 * @return int
	 */
	protected function getUserId()
	{
		if(intval($this->user_id) < 1)
		{
			throw new ApiException('This action requires user id');
		}
		return $this->user_id;
	}
	
	/**
	 * Format response
	 * 
	 * @return array
	 */
	protected static function formatResponse($status, $message="", $res=array())
	{
		
		if(!in_array($status, [self::STATUS_SUCCESS,self::STATUS_FAILED]))
		{
			throw new ApiException("Given status is not allowed");
		}
		
		if (is_object($res)) {
			
		}
		$json = json_encode($res);
			
		if($json[0] == "{")
		{
			//Object format
			$result = (object)$res;
			$result->list = [];
		}else{
			//Array format
			$result["list"] = $res;
		}
		
		return array("status"=>$status, "message"=>$message, "result"=>$result);
	}
	
	/**
	 * Get header Authorization
	 * */
	private function getAuthorizationHeader(){
		$headers = null;
		if (isset($_SERVER['Authorization'])) {
			$headers = trim($_SERVER["Authorization"]);
		}
		else if (isset($_SERVER['HTTP_AUTHORIZATION'])) { //Nginx or fast CGI
			$headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
		} elseif (function_exists('apache_request_headers')) {
			$requestHeaders = apache_request_headers();
			// Server-side fix for bug in old Android versions (a nice side-effect of this fix means we don't care about capitalization for Authorization)
			$requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));
			//print_r($requestHeaders);
			if (isset($requestHeaders['Authorization'])) {
				$headers = trim($requestHeaders['Authorization']);
			}
		}
		return $headers;
	}
	
	
	/**
	 * get access token from header
	 * */
	protected function getBearerToken() {
		$headers = $this->getAuthorizationHeader();
		// HEADER: Get the access token from the header
		if (!empty($headers)) {
			if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
				return $matches[1];
			}
		}
		return null;
	}
	
	/**
	 * Check Access Token
	 * 
	 * @param unknown $accessToken
	 * @return string
	 */
	protected function checkAccessToken($accessToken)
	{
		
		//$this->user_id = 110;
		//return self::TOKEN_VALID;
		
		try{
			$decoded = $this->jwt::decode($accessToken, $this->key, array('HS256'));
		}catch (\Firebase\JWT\BeforeValidException $e)
		{
			return self::TOKEN_INVALID;
		}catch (\Firebase\JWT\ExpiredException $e)
		{
			return self::TOKEN_EXPIRED;
		}catch (\Firebase\JWT\SignatureInvalidException $e)
		{
			return self::TOKEN_INVALID;
		}catch(ApiException $e)
		{
			return self::TOKEN_INVALID;
		}
		try{
			//User is valid, proceed with id
			$this->user_id = $decoded->user_id;
			
		}catch(ApiException $e)
		{
			return self::TOKEN_INVALID;
		}
		return self::TOKEN_VALID;
	}
	
	
	/**
	 * Get Access Token
	 * 
	 * @param string $user_id
	 * @return unknown
	 */
	protected function getAccessToken(array $user)
	{
		$issuedAt = time();
		$expirationTime = $issuedAt + 315360000;  // jwt valid for 1 year from the issued time
		$payload = array(
				"name" => $user['name'],
				"email" => $user['email'],
				'user_id' => $user['id'],
				'iat' => $issuedAt,
				'exp' => $expirationTime
		);
		
		$jwt = JWT::encode($payload, $this->key, $this->alg);
		return $jwt;
	}
	
	
	public static function setOutput($statusCode, $output)
	{
		//Turn notices and warrnings to exceptions
		if (headers_sent()) {
			die(json_encode(array("status"=>"fail", "message"=>"Headers already sent", "result"=>[])));
		}
		
		if ($_SERVER['REQUEST_METHOD'] == "OPTIONS") {
			header('Content-Type: application/json');
			header('Access-Control-Allow-Origin: *');
			header('Access-Control-Allow-Credentials: true');
			if(isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])){
			header('Access-Control-Request-Headers: ' . $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']);
			}
			header('Access-Control-Allow-Methods: ' . $_SERVER['REQUEST_METHOD']);
			http_response_code($statusCode);
			die();
		}
		header("Expires: Tue, 01 Jan 2000 00:00:00 GMT");
		header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
		header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
		header("Cache-Control: post-check=0, pre-check=0", false);
		header("Pragma: no-cache");
		header('Content-Type: application/json');
		header('Access-Control-Allow-Origin: *');
		header('Access-Control-Allow-Credentials: true');
		if(isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])){
			header('Access-Control-Request-Headers: ' . $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']);
		}
		header('Access-Control-Allow-Methods: ' . $_SERVER['REQUEST_METHOD']);
		header('Access-Control-Expose-Headers: date,server,cache-control,access-control-allow-origin,access-control-allow-credentials,access-control-allow-headers,access-control-allow-methods,access-control-expose-headers,access-control-max-age,connection,vary,transfer-encoding,content-type,x-final-url');
		header('Connection: Keep-Alive');
		header('Last-Modified: ' . gmdate("D, d M Y H:i:s") . ' GMT');
		header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
		http_response_code($statusCode);
		echo json_encode($output);
	}
	
	
	
}
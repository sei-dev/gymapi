<?php
namespace APISDK;


use APISDK\ApiException;
use APISDK\Models\Products;
use Firebase\JWT\JWT;
use APISDK\Models\Users;
use APISDK\Models\Categories;
use APISDK\Models\Villages;
use APISDK\Models\Counties;
use APISDK\Models\News;
use APISDK\Models\Orders;
use APISDK\Models\Districts;
use APISDK\Models\OrderItems;

const URL = "http://gymapi";
/**
 * Site specific set of APIs
 *
 * @author arsenleontijevic
 * @since 30.09.2019
 */
class Sdk extends Api
{
    
    

	const DIR_UPLOADS = __DIR__ . "/../../images/";
	
	const DIR_PRODUCTS = "products";
	const DIR_USERS = "users";
	const DIR_CATEGORIES = "categories";
    
    
    const IMAGE_URL = "https://eosapi.atakdev.com/api/show";

    const FILE_URL = "https://eosapi.atakdev.com/api/file";

    /**
     * Instantiate Custom Api
     *
     * @param
     *            mixed \CI_DB_driver | Other Adapters $db
     * @param array $request
     * @param \Firebase\JWT\JWT $jwt
     */
    public function __construct($db, array $request, JWT $jwt = null)
    {
        parent::__construct($db, $request, $jwt);
    }

    /**
     * Analize request params
     *
     * @param array $request
     */
    protected function processRequest(array $request)
    {
        if (! isset($request['action'])) {
            throw new ApiException("The action param is required");
        }

        // Do not check acces_token for login and register actions
        if (! in_array($request['action'], [
            'login',
            'register',
            'forgotPassword'
        ])) {
            $at = null;
            if (! is_null($this->getBearerToken())) {
                $at = $this->getBearerToken();
            } elseif (isset($request['access_token'])) {
                $at = $request['access_token'];
            }
            if (is_null($at)) {
                throw new ApiException("The access_token param is required");
            }

            $decoded = $this->checkAccessToken($at);
            if ($decoded != self::TOKEN_VALID) {
                throw new ApiException("The access_token is not valid");
            }
        }

        if (method_exists($this, $request['action'])) {
            $action = $request['action'];
            $this->setResponse($this->$action());
        } else {
            $this->setResponse($this->formatResponse("fail", "Unknown action", array()));
        }
    }

    /**
     *
     * @api {post}? getUsers
     * @apiVersion 1.0.0
     * @apiSampleRequest http://gymapi
     * @apiName getUsers
     * @apiGroup Users
     * @apiDescription getUsers api will return all users
     * @apiParam {String} action=getUsers API Action.
     * @apiParam {String} email user email
     * @apiHeader {String} Authorization='Bearer <ACCESS_TOKEN>' access_token
     */
    private function getUsers()
    {
        $request = $this->filterParams([
            'email'
        ]);

        $productModel = new Products($this->dbAdapter);

        $products = $productModel->getProducts($request["email"]);
        

        // Return success
        return $this->formatResponse(self::STATUS_SUCCESS, "", $products);
    }
    
    
    /**
     *
     * @api {post}? saveProfile
     * @apiVersion 1.0.0
     * @apiSampleRequest http://gymapi
     * @apiName saveProfile
     * @apiGroup Users
     * @apiDescription saveProfile create new user profile
     * @apiParam {String} action=saveProfile API Action.
     * @apiParam {String} first_name user name
     * @apiParam {String} last_name user last name
     * @apiParam {String} email user email
     * @apiHeader {String} Authorization='Bearer <ACCESS_TOKEN>' access_token
     */
    private function saveProfile()
    {
        $request = $this->filterParams(['first_name', 'last_name',  'email',  'mobile_number', 'year_born']);
        
        $user_model = new Users($this->dbAdapter);
        
        if (isset($_REQUEST['password']))
        {
            $newPass = trim($_REQUEST['password']);
            if (!empty($newPass))
            {
                $newPass = password_hash($newPass, PASSWORD_BCRYPT);
                $request['password'] = $newPass;
            }
        }
        
        /**$users = $user_model->getByEmail($request['email']);
        
        if (isset($users[0])) {
        //Return fail, user exists
        return $this->formatResponse(self::STATUS_FAILED, "0");
        }**/
        if (isset($_REQUEST['zip_code']))
        {
            $request['zip_code'] = $_REQUEST['zip_code'];
        }
        $request['id'] = $this->user_id;
        $user_id = $user_model->update($request);
        
        if ($user_id) {
            //Get newly created user from db
            $users = $user_model->getById($this->user_id);
            $userEntities = $this->getUserEntities($users[0]);
            $user = (object) $userEntities;
            
            unset($user->password);
            //$user->image = $this->getDefaultImage();
            return $this->formatResponse(self::STATUS_SUCCESS, "", $user);
        }
        //Return fail, unable to register user
        return $this->formatResponse(self::STATUS_FAILED, "-1");
        
    }
    
    
    
    
    
    
    /**
     * 
     * @return array|unknown[]|string[]|StdClass[]
     */
    private function getUserById()
    {
        $request = $this->filterParams([
            'id'
        ]);
        
        $userModel = new Users($this->dbAdapter);
        
        $users = $userModel->getUserById($request["id"]);
        
        if ($this->isFileExists(self::DIR_USERS, $users["id"])) {
            $users['image'] = $this->domain."/images/users/".$users["id"].".png?r=" . rand(0,100000);
        }else{
            $users['image'] = $this->domain."/images/logo.png";
        }
        
        
        // unset($users['password']);
        
        // Return success
        return $this->formatResponse(self::STATUS_SUCCESS, "", $users);
    }
    

    /**
     * 
     * @throws \Exception
     * @return array|unknown[]|string[]|StdClass[]
     */
    private function saveImage()

    {
        $request = $this->filterParams([
            'dir',
            'base64',
            'file_name'
        ]);
        
        $dir = $request["dir"];
        $file_name = $request["file_name"];
        $base64_string = $request["base64"];
        
        if(!$base64_string)
        {
            throw new \Exception("base64_string is empty");
        }
        
        
        $base64_string = $this->base64UrlDecode(preg_replace('#^data:image/\w+;base64,#i', '', $base64_string));
        
        
        $upload_dir = self::DIR_UPLOADS . $dir . '/';
        $upload_path = $upload_dir.$file_name.".png";
        
        //var_dump($upload_dir);
        //die(var_dump($upload_path));
        
        
        //Create dir if not exists
        if(!is_dir($upload_dir)){
            mkdir($upload_dir, 0777, true);
        }
        file_put_contents($upload_path, $base64_string);
        
        return $this->formatResponse(self::STATUS_SUCCESS, "", []);
    }
    
    
    
    
    /**
     *
     * @param unknown $dir
     * @param unknown $id
     * @return boolean
     */
    private function isFileExists($dir, $id)
    {
        return file_exists(self::DIR_UPLOADS . DIRECTORY_SEPARATOR . $dir . DIRECTORY_SEPARATOR . $id . ".png");
    }
    
    /**
     * 
     * @param unknown $username
     * @param unknown $password
     */
    private function signup($username, $password)
    {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        save($username, $hash);
    }
    
    /**
     * 
     * @param unknown $userRow
     * @return StdClass
     */
    private function returnUser($userRow)
    {
        unset($userRow['password']);
        $user = (object) $userRow;
        $user->access_token = $this->getAccessToken($userRow);
        return $user;
    }
    
    
    
    
    
    
    
    
    
    /*
     * PUSH NOTIFICATIONS SECTION
     */
    
    
    
    
    
    /**
     * 
     * @return array|unknown[]|string[]|StdClass[]
     */
    private function setDeviceToken()
    {
        $request = $this->filterParams(['deviceToken']);
        
        $model = new Users($this->dbAdapter);
        $object = $model->getById($this->user_id);
        
        //Delete device token from old accounts
        $old_users = $model->getByDeviceToken($request['deviceToken']);
        foreach ($old_users as $one){
            if($one["id"] != $object[0]["id"]){
                $one["device_token"] = "deleted";
                $model->update($one);
            }
        }
        
        $object[0]['device_token'] = $request['deviceToken'];
        $model->update($object[0]);
        unset($object[0]["password"]);
        return $this->formatResponse(self::STATUS_SUCCESS, $object[0]);
    }
    
    /**
     * 
     * @return array|unknown[]|string[]|StdClass[]
     */
    private function testPush()
    {
        $request = $this->filterParams(['deviceToken']);
        
        $model = new Users($this->dbAdapter);
        $object = $model->getById($this->user_id);
        $user['id'] = $object[0]['id'];
        $user['device_token'] = $request['deviceToken'];
        
        $model->update($user);
        
        $result = $this->sendPush("Hello, " . $object[0]['first_name']. " sent you a request.", $object[0]);
        
        return $this->formatResponse(self::STATUS_SUCCESS, $result, []);
    }
    
    /**
     * 
     * @param unknown $msg
     * @param unknown $recipient
     * @param unknown $request
     * @return mixed
     */
    private function sendPush($msg, $recipient, $request = null)
    {
        $model = new Users($this->dbAdapter);
        $object = $model->getById($recipient['id']);
        $user = $object[0];
        
        //die(var_dump($object[0]));
        if($this->getDevice($user) == User::DEVICE_IOS)
        {
            return $this->sendIosPush($user, $msg, $request);
        }elseif($this->getDevice($user) == User::DEVICE_ANDROID){
            return $this->sendAndPush($user, $msg, $request);
        }
    }
    
    /**
     * 
     * @param unknown $user
     * @param unknown $msg
     * @param unknown $request
     * @throws Exception
     * @return mixed
     */
    private function sendIosPush($user, $msg, $request = null)
    {
        $token = $this->getDeviceToken($user);
        $keyfile = ROOT_PATH . '/' . 'AuthKey_7J74DGS2TH.p8';               # <- Your AuthKey file
        $keyid = '7J74DGS2TH';                            # <- Your Key ID
        $teamid = 'JPJZF4CHJ4';                           # <- Your Team ID (see Developer Portal)
        $bundleid = 'com.sei.coachApp';                # <- Your Bundle ID
        $url = 'https://api.push.apple.com';  # <- development url, or use http://api.development.push.apple.com for production environment
        //$url = 'https://api.development.push.apple.com';
        if ($request == null){
            $message = '{"aps":{"content-available" : 1, "alert":"'.$msg.'","sound":"default","badge":1}}';
        }else{
            $message = '{"aps":{"content-available" : 1, "alert":"'.$msg.'","sound":"default","badge":1}, "request":'.json_encode($request, true).'}';
        }
        $key = openssl_pkey_get_private('file://'.$keyfile);
        //die($token);
        
        $header = ['alg'=>'ES256','kid'=>$keyid];
        $claims = ['iss'=>$teamid,'iat'=>time()];
        
        $header_encoded = $this->base64($header);
        $claims_encoded = $this->base64($claims);
        
        $signature = '';
        openssl_sign($header_encoded . '.' . $claims_encoded, $signature, $key, 'sha256');
        $jwt = $header_encoded . '.' . $claims_encoded . '.' . base64_encode($signature);
        //die($jwt);
        // only needed for PHP prior to 5.5.24
        if (!defined('CURL_HTTP_VERSION_2_0')) {
            define('CURL_HTTP_VERSION_2_0', 3);
        }
        
        $full_url = $url."/3/device/".$token;
        
        $http2ch = curl_init();
        
        curl_setopt($http2ch, CURLOPT_POST, TRUE);
        curl_setopt($http2ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_2_0);
        curl_setopt($http2ch, CURLOPT_POSTFIELDS, $message);
        curl_setopt($http2ch, CURLOPT_HTTPHEADER, array(
            "apns-topic: {$bundleid}",
            "authorization: bearer $jwt"
        ));
        curl_setopt($http2ch, CURLOPT_URL, $full_url);
        curl_setopt($http2ch, CURLOPT_PORT, 443);
        curl_setopt($http2ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($http2ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($http2ch, CURLOPT_HEADER, 1);
        
        $result = curl_exec($http2ch);
        if ($result === FALSE) {
            throw new Exception("Curl failed: ".curl_error($http2ch));
        }
        
        $status = curl_getinfo($http2ch, CURLINFO_HTTP_CODE);
        //echo $result;
        
        return $result;
    }
    
    /**
     * 
     * @param unknown $data
     * @return string
     */
    private function base64($data) {
        return rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '=');
    }
    
    /**
     * 
     * @param unknown $user
     * @param unknown $msg
     * @param unknown $request
     * @throws \Exception
     * @return mixed
     */
    private function sendAndPush($user, $msg, $request = null)
    {
        $firebase_api = "AAAAN53TOQA:APA91bHWGx0hIvmiuURd1rFQaqmOAqv470xA-TpRKyWpMptTGPh4qYsE9V9h9EmdRQyBNAsKmz8EbmMHo-Y0U0ensVtzez2aV9gtd9ZBLxeOo0cXCA_mS5vu1KmX0k80JN9U7Yv3Wu0n";
        $notification = array();
        $notification['title'] = "Scrimmage Search";
        $notification['message'] = $msg;
        $notification['image'] = "ddd";
        $notification['badge'] = "1";
        $notification['click_action'] = "com.com.lepsha.lepsha.MainActivity";
        $notification['action_destination'] = "com.com.lepsha.lepsha.MainActivity";
        
        
        $fields = array(
            'to' => $this->getDeviceToken($user),
            'data' => $notification,
        );
        
        
        // Set POST variables
        $url = 'https://fcm.googleapis.com/fcm/send';
        
        $headers = array(
            'Authorization: key=' . $firebase_api,
            'Content-Type: application/json'
        );
        
        // Open connection
        $ch = curl_init();
        
        // Set the url, number of POST vars, POST data
        curl_setopt($ch, CURLOPT_URL, $url);
        
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        // Disabling SSL Certificate support temporarily
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
        
        
        $result = curl_exec($ch);
        if ($result === FALSE) {
            throw new \Exception("Curl failed: ".curl_error($ch));
        }
        
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        //var_dump($result);
        //mail("arsen.leontijevic@gmail.com", "and nots", $result);
        
        //die(var_dump($fields));
        
        return $result;
    }
    
    /**
     *
     * @return array
     */
    private function getDevice($user){
        return substr($user['device_token'], 0, 3);
    }
    
    /**
     *
     * @return array
     */
    private function getDeviceToken($user){
        return substr($user['device_token'], 4);
    }
    
    /**
     * Base64 encoding that doesn't need to be urlencode()ed.
     * Exactly the same as base64_encode except it uses
     *   - instead of +
     *   _ instead of /
     *   No padded =
     *
     * @param string $input base64UrlEncoded input
     *
     * @return string The decoded string
     */
    protected static function base64UrlDecode($input) {
        return base64_decode(strtr($input, ' ', '+'));
    }
    
}
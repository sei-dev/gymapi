<?php
namespace APISDK\Models;
use APISDK\Models\ModelAbstract;
use APISDK\ApiException;
use APISDK\DbAdapters\DbAdapterInterface;

class Users extends ModelAbstract implements ModelInterface
{
	
	/**
	 * 
	 * @param \CI_DB_driver $db
	 */
	public function __construct(DbAdapterInterface $dbAdapter)
	{
		$dbAdapter->setDbTable(self::getTablePrefix()."users");
		$this->setDbAdapter($dbAdapter);
	}
	
	/**
	 *
	 * @param string $email
	 * @throws ApiException
	 * @return array
	 */
	public function getUserById(string $id) {
		$sQuery = "SELECT *
				FROM ".self::getTablePrefix()."users
				WHERE id = '{$id}'
				LIMIT 1";
		$row = $this->getDbAdapter()->query($sQuery)->fetch(\PDO::FETCH_ASSOC);
		if (isset($row)) {
		    return $row;
		}
		return false;
	}
	
	/**
	 *
	 * @param string $email
	 * @throws ApiException
	 * @return array
	 */
	public function getUserByEmail(string $email) {
	    $sQuery = "SELECT *
				FROM ".self::getTablePrefix()."users
				WHERE email = '{$email}'
				LIMIT 1";
	    $row = $this->getDbAdapter()->query($sQuery)->fetch(\PDO::FETCH_ASSOC);
	    if (isset($row)) {
	        return $row;
	    }
	    return false;
	}
	
	/**
	 *
	 * @param string $email
	 * @throws ApiException
	 * @return array
	 */
	public function getByDeviceToken(string $token) {
		$sQuery = "SELECT *
				FROM ".self::getTablePrefix()."users
				WHERE device_token = '{$token}'
				LIMIT 1";
		$rows = $this->getDbAdapter()->query($sQuery)->fetchAll(\PDO::FETCH_ASSOC);
		
		return $rows;
	}
	
	
	
	/**
	 * 
	 * @param string $email
	 * @throws ApiException
	 * @return array
	 */
	public function getUsers(array $email) {
		
		$emailsString = "'" . implode("', '", $email) . "'";
		
		$sQuery = "SELECT *
				FROM ".self::getTablePrefix()."users
				WHERE email in ({$emailsString})
				";
		return $this->getDbAdapter()->query($sQuery)->fetchAll(\PDO::FETCH_ASSOC);
	}
	
	
	public function editProfile(String $id, String $name, String $address, String $pib,
	    String $phone, String $email, String $contact_name,
	    String $contact_lastname, String $contact_phone
	){
	    $sQuery = "UPDATE" . self::getTablePrefix() . " `users` SET `name`='{$name}',`pib`='{$pib}',`email`='{$email}',
                  `phone`='{$phone}',`address`='{$address}',`contact_name`='{$contact_name}',
                  `contact_lastname`='{$contact_lastname}',`contact_phone`='{$contact_phone}' WHERE id = '{$id}'
                  LIMIT 1";
	    
	    
	    return $this->getDbAdapter()->query($sQuery);
	}
	
	public function changePassword(String $id, String $hash_pass){
	    $sQuery = "UPDATE " . self::getTablePrefix() . "users
                   SET password = '{$hash_pass}'
				   WHERE id = '{$id}'
				";
	    
	    return $this->getDbAdapter()->query($sQuery);
	}
	
	public function forgotPassword(String $id, String $hash){
	    $sQuery = "UPDATE " . self::getTablePrefix() . "users
                   SET hash = '{$hash}'
				   WHERE id = '{$id}'
				";
	    
	    return $this->getDbAdapter()->query($sQuery);
	    
	}
	
	public function setDeviceToken(String $id, String $device_token){
	    $sQuery = "UPDATE " . self::getTablePrefix() . "users
                   SET device_token = '{$device_token}'
				   WHERE id = '{$id}'
				";
	    
	    return $this->getDbAdapter()->query($sQuery);
	   
	}
	
	public function register(String $email,String $contact_name, String $contact_lastname, String $contact_phone, String $password){
	    $sQuery = "INSERT INTO ". self::getTablePrefix() . "users
                   SET `email`='{$email}',
                  `contact_name`='{$contact_name}',
                  `contact_lastname`='{$contact_lastname}',`contact_phone`='{$contact_phone}', `password`='{$password}'";
	    
	    return $this->getDbAdapter()->query($sQuery);
	}
	
	
	//bcrypt, then compare hash with password
	
	public function login(string $email) {
	    
	    //$emailsString = "'" . implode("', '", $email) . "'";
	    //$passString = strval($password);
	    
	    $sQuery = "SELECT *
				FROM ".self::getTablePrefix()."users
				WHERE email = '{$email}'
				";
	    
	    return $this->getDbAdapter()->query($sQuery)->fetchAll(\PDO::FETCH_ASSOC);
	}
	
	/**
	 *
	 * @param string $email
	 * @param string $password
	 * @throws ApiException
	 * @return array
	 */
	public function signup(string $firstName, string $password) {
		
		
		$data = [
				"first_name"=>$firstName,
				"password"=>$password
		];
		
		return $this->getDbAdapter()->insert($data);
	}
	
	
	
	/**
	 *
	 * @param string $email
	 * @param string $password
	 * @throws ApiException
	 * @return array
	 */
	public function getHash(string $email) {
	    
	    
	    $sQuery = "SELECT password
				FROM ".self::getTablePrefix()."users
				WHERE email = '{$email}'
				LIMIT 1";
	    $row = $this->getDbAdapter()->query($sQuery)->fetchAll(\PDO::FETCH_ASSOC);
	    if (isset($row[0]["password"])) {
	        return $row[0]["password"];
	    }
	    return false;
	}
	
	
	
	/**
	 *
	 * @param array $data
	 * @return array
	 */
	public function update(array $data) {
		return $this->getDbAdapter()->update($data);
	}
	
}
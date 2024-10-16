<?php
namespace APISDK\Models;
use APISDK\Models\ModelAbstract;
use APISDK\ApiException;
use APISDK\DbAdapters\DbAdapterInterface;

class Gyms extends ModelAbstract implements ModelInterface
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
	
	public function getAllGyms(string $email) {
	    $sQuery = "SELECT *
				FROM ".self::getTablePrefix()."gyms";
	    $row = $this->getDbAdapter()->query($sQuery)->fetchAll(\PDO::FETCH_ASSOC);
	    if (isset($row)) {
	        return $row;
	    }
	    return false;
	}
	
	public function getGymsByCityId(string $id){
	    $sQuery = "SELECT gyms.*, cities.city
				FROM gyms LEFT JOIN cities ON gyms.city_id = cities.id WHERE city_id = '{$id}'";
	    $row = $this->getDbAdapter()->query($sQuery)->fetchAll(\PDO::FETCH_ASSOC);
	    if (isset($row)) {
	        return $row;
	    }
	    return false;
	}
	
	public function getGymsByUserId(string $user_id){
	    $sQuery = "SELECT gyms.*, cities.city FROM trainer_gyms 
                    LEFT JOIN users ON trainer_gyms.user_id = users.id 
                    LEFT JOIN gyms ON trainer_gyms.gym_id = gyms.id 
                    LEFT JOIN cities ON gyms.city_id = cities.id 
                    WHERE trainer_gyms.user_id = '{$user_id}';";
	    $row = $this->getDbAdapter()->query($sQuery)->fetchAll(\PDO::FETCH_ASSOC);
	    if (isset($row)) {
	        return $row;
	    }
	    return false;
	}
	
	public function addFitnessCenter(string $user_id, string $gym_id){
	    $sQuery = "INSERT INTO `trainer_gyms`(`user_id`, `gym_id`) VALUES ('{$user_id}','{$gym_id}');";
	    $row = $this->getDbAdapter()->query($sQuery)->fetchAll(\PDO::FETCH_ASSOC);
	    if (isset($row)) {
	        return $row;
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
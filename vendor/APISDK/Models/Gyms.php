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
		$dbAdapter->setDbTable(self::getTablePrefix()."gyms");
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
	    $sQuery = "SELECT gyms.* FROM trainer_gyms 
                    LEFT JOIN users ON trainer_gyms.user_id = users.id 
                    LEFT JOIN gyms ON trainer_gyms.gym_id = gyms.id 
                    #LEFT JOIN cities ON gyms.city_id = cities.id 
                    WHERE trainer_gyms.user_id = '{$user_id}';";
	    $row = $this->getDbAdapter()->query($sQuery)->fetchAll(\PDO::FETCH_ASSOC);
	    if (isset($row)) {
	        return $row;
	    }
	    return false;
	}
	
	public function getGymByUserId(string $user_id){
	    $sQuery = "SELECT gyms.* FROM trainer_gyms
                    LEFT JOIN users ON trainer_gyms.user_id = users.id
                    LEFT JOIN gyms ON trainer_gyms.gym_id = gyms.id
                    #LEFT JOIN cities ON gyms.city_id = cities.id
                    WHERE trainer_gyms.user_id = '{$user_id}' LIMIT 1;";
	    $row = $this->getDbAdapter()->query($sQuery)->fetchAll(\PDO::FETCH_ASSOC);
	    if (isset($row)) {
	        return $row;
	    }
	    return false;
	}
	
	public function addFitnessCenterIds(string $user_id, string $gym_id){
	    $sQuery = "INSERT INTO `trainer_gyms`(`user_id`, `gym_id`) VALUES ('{$user_id}','{$gym_id}');";
	    $row = $this->getDbAdapter()->query($sQuery)->fetchAll(\PDO::FETCH_ASSOC);
	    if (isset($row)) {
	        return $row;
	    }
	    return false;
	}
	
	public function addFitnessCenter(string $id, string $name, string $address, string $city, string $phone){
	    $sQuery = "INSERT INTO `gyms`(`name`, `address`, `city`, `phone`) VALUES ('{$name}','{$address}','{$city}','{$phone}');";
	    
	    //$sQuery2 = "SELECT * FROM `gyms` WHERE name = '{$name}' AND address = '{$address}' AND city = '{$city}' AND phone = '{$phone}';";
	    
	    $this->getDbAdapter()->query($sQuery);
	    
	    $new_id = $this->getDbAdapter()->getLastInsertId("gyms");
	    
	    return $new_id;
	}
	
	
	public function upsertFitnessCenter(string $userId, string $id, string $name, string $address, string $city, string $phone): int
	{
	    $userId = (int)$userId;
	    
	    $data = [
	        'id'      => $id,
	        'name'    => trim($name),
	        'address' => trim($address),
	        'city'    => trim($city),
	        'phone'   => trim($phone),
	    ];
	    
	    if (isset($data['id']) && (empty($data['id']) || $data['id'] === '0' || $data['id'] === 0)) {
	        unset($data['id']);
	    }
	    
	    $toUpdate = $data;
	    unset($toUpdate['id']);
	    
	    $this->getDbAdapter()->upsert('gyms', $data, $toUpdate);
	    
	    if (!isset($data['id'])) {
	        $gymId = (int)$this->getDbAdapter()->getLastInsertId('gyms');
	    } else {
	        $gymId = (int)$id;
	    }
	    
	    $this->getDbAdapter()->query(
	        "DELETE FROM trainer_gyms WHERE user_id = ?",
	        [$userId]
	        );
	    
	    if ($gymId > 0) {
	        $this->addFitnessCenterIds($userId, $gymId);
	    }
	    
	    return $gymId;
	}
	
	public function removeFitnessCenter(string $user_id, string $gym_id){
	    $sQuery = "DELETE FROM `trainer_gyms` WHERE user_id = '{$user_id}' and gym_id = '{$gym_id}';";
	    $row = $this->getDbAdapter()->query($sQuery)->fetchAll(\PDO::FETCH_ASSOC);
	    if (isset($row)) {
	        return $row;
	    }
	    return false;
	}
	
	
	public function removeFitnessCenterMain(string $gym_id){
	    $sQuery = "DELETE FROM `gyms` WHERE id = '{$gym_id}';";
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
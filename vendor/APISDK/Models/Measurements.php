<?php
namespace APISDK\Models;
use APISDK\Models\ModelAbstract;
use APISDK\ApiException;
use APISDK\DbAdapters\DbAdapterInterface;

class Measurements extends ModelAbstract implements ModelInterface
{
	
	/**
	 * 
	 * @param \CI_DB_driver $db
	 */
	public function __construct(DbAdapterInterface $dbAdapter)
	{
		$dbAdapter->setDbTable(self::getTablePrefix()."measurements");
		$this->setDbAdapter($dbAdapter);
	}
	
	/**
	 *
	 * @param string $email
	 * @throws ApiException
	 * @return array
	 */
	
	public function getMeasurementsByIds(string $trainer_id, string $client_id){
	    $sQuery = "SELECT * FROM `measurements` WHERE trainer_id = '{$trainer_id}' AND client_id = '{$client_id}'";
	    $row = $this->getDbAdapter()->query($sQuery)->fetchAll(\PDO::FETCH_ASSOC);
	    if (isset($row)) {
	        return $row;
	    }
	    return false;
	}
	
	
	public function getMeasurementsByClientId(string $client_id){
	    $sQuery = "SELECT measurements.*, users.first_name as trainer_name, users.last_name as trainer_last_name FROM measurements
                   LEFT JOIN users ON users.id = measurements.trainer_id
                   WHERE client_id = '{$client_id}'";
	    $row = $this->getDbAdapter()->query($sQuery)->fetchAll(\PDO::FETCH_ASSOC);
	    if (isset($row)) {
	        return $row;
	    }
	    return false;
	}
	
	
	
	public function addMeasurement(string $trainer_id, string $client_id, string $height, string $weight, string $neck,
	                               string $chest, string $gluteus, string $quad, string $leg, string $waist,
	                               string $biceps, string $date, string $e1_rep, string $e2_rep, string $e3_rep,
	                               string $e1_kg, string $e2_kg, string $e3_kg) {
		$sQuery = "INSERT INTO `measurements`(`trainer_id`, `client_id`, `height`,
                  `weight`, `neck`, `chest`, `gluteus`, `quadriceps`, `lower_leg`, `waist`, `biceps`, `measured_at`,
                  `exercise_one_reps`, `exercise_two_reps`, `exercise_three_reps`, `exercise_one_kg`, `exercise_two_kg`,
                  `exercise_three_kg`) VALUES ('{$trainer_id}','{$client_id}','{$height}','{$weight}',
                  '{$neck}','{$chest}','{$gluteus}','{$quad}','{$leg}','{$waist}','{$biceps}',
                  '{$date}','{$e1_rep}','{$e2_rep}','{$e2_rep}','{$e1_kg}','{$e2_kg}','{$e3_kg}')";
		
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
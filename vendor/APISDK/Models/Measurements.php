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
	
	/**
	 * 
	 * COALESCE obezbedjuje da se uvek vrati array
	 * 
	 */
	public function getMeasurementsByIdsNew(string $trainer_id, string $client_id){
	    $sQuery = "
        SELECT
            m.*,
            COALESCE(
                JSON_ARRAYAGG(
                    JSON_OBJECT(
                        'id', e.id,
                        'exercise', e.exercise,
                        'repetitions', e.repetitions,
                        'kilograms', e.kilograms,
                        'measurement_id', e.measurement_id,
                        'image_name', e.image_name,
                        'created_on', e.created_on
                    )
                ), JSON_ARRAY()
            ) AS exercises
        FROM measurements m
        LEFT JOIN exercises e ON e.measurement_id = m.id
        WHERE m.trainer_id = :trainer_id
          AND m.client_id = :client_id
        GROUP BY m.id
    ";
	    
	    $stmt = $this->getDbAdapter()->prepare($sQuery);
	    $stmt->execute([
	        ':trainer_id' => $trainer_id,
	        ':client_id'  => $client_id
	    ]);
	    
	    $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
	    
	    return !empty($rows) ? $rows : [];
	}
	
	
	
	
	public function getMeasurementsByClientIdNew(string $client_id){
	    $sQuery = "
        SELECT
            m.*,
            u.first_name AS trainer_name,
            u.last_name AS trainer_last_name,
            COALESCE(
                JSON_ARRAYAGG(
                    JSON_OBJECT(
                        'id', e.id,
                        'exercise', e.exercise,
                        'repetitions', e.repetitions,
                        'kilograms', e.kilograms,
                        'measurement_id', e.measurement_id,
                        'created_on', e.created_on
                    )
                ), JSON_ARRAY()
            ) AS exercises
        FROM measurements m
        LEFT JOIN exercises e ON e.measurement_id = m.id
        LEFT JOIN users u ON u.id = m.trainer_id
        WHERE m.client_id = :client_id
        GROUP BY m.id
    ";
	    
	    $stmt = $this->getDbAdapter()->prepare($sQuery);
	    $stmt->execute([
	        ':client_id' => $client_id
	    ]);
	    
	    $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
	    
	    return !empty($rows) ? $rows : false;
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
                  '{$date}','{$e1_rep}','{$e2_rep}','{$e3_rep}','{$e1_kg}','{$e2_kg}','{$e3_kg}')";
		
		$row = $this->getDbAdapter()->query($sQuery)->fetchAll(\PDO::FETCH_ASSOC);
		if (isset($row)) {
		    return $row;
		}
		return false;
	}
	
	/**
	 * 
	 * @param array $request
	 * @return int
	 */
	public function saveMeasurement(array $request) {
	    //Rename params
	    $request["measured_at"] = $this->replaceParam($request, "date");
	    $request["quadriceps"] = $this->replaceParam($request, "quad");
	    $request["lower_leg"] = $this->replaceParam($request, "leg");
	    
	    $exercises = $request["exercises"];
	    //Remove exercises, that doesn't exists
	    unset($request["exercises"]);
	    
	    //Fields to update on dxuplicate
	    $toUpdate = $request;
	    
	    //measured_at shouldn't be updated
	    unset($toUpdate["measured_at"]);
	    
	    $tbl = $this->getDbAdapter()->getDbTable();
	    
	    $result = $this->getDbAdapter()->upsert($tbl, $request, $toUpdate);
	    
	    if ($result === 1) {
	        //insert, result is lastInsertId
	        $lastInsertId = $this->getDbAdapter()->getLastInsertId($tbl);
	        $this->addExercisesFromJson($exercises, $lastInsertId);
	    } else {
	        if(isset($request["id"]) && intval($request["id"]) > 0){
	            $this->deleteExercise($request["id"]);
	            $this->addExercisesFromJson($exercises, $request["id"]);
	        }
	        
	    }
	    return $result;
	}
	
	public function addExercisesFromJson(string $json, string $measurementId)
	{
	    // Decode JSON u asocijativni niz
	    $json = urldecode($json ?? '[]');
	    $clean = json_encode(json_decode($json, true));
	    $exercises = json_decode($clean, true);
	    
	    if (!is_array($exercises)) {
	        return false;
	    }
	    
	    foreach ($exercises as $ex) {
	        unset($ex["id"]);
	        $ex["created_on"] = date("Y-m-d");
	        $ex["measurement_id"] = $measurementId;
	        $this->getDbAdapter()->insertIntoTable("exercises", $ex);
	    }
	    
	    return true;
	}
	
	public function deleteExercise(string $measurementId)
	{
	    if(empty($measurementId))
	    {
	        return false;
	    }
	    
	    $sql = "DELETE FROM exercises WHERE measurement_id = ?";
	    $stmt = $this->getDbAdapter()->query($sql, [$measurementId]);
	    
	    return $stmt->rowCount(); // broj obrisanih redova
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
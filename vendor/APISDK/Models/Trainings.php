<?php
namespace APISDK\Models;
use APISDK\Models\ModelAbstract;
use APISDK\ApiException;
use APISDK\DbAdapters\DbAdapterInterface;

class Trainings extends ModelAbstract implements ModelInterface
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
	public function getTodayTrainingsByTrainerId(string $id) {
		/* $sQuery = "SELECT client.id as client_id, client.first_name as client_first_name, client.last_name as client_last_name, training.*, users.first_name as trainer_first_name, users.last_name as trainer_last_name,
                   gyms.name as gym_name, gyms.address as gym_address, cities.city as gym_city FROM training
                   LEFT JOIN users ON training.trainer_id = users.id
                   LEFT JOIN gyms ON training.gym_id = gyms.id
                   LEFT JOIN cities ON cities.id = gyms.city_id
                   LEFT JOIN training_clients ON training_clients.training_id = training.id
                   LEFT JOIN users client ON training_clients.client_id = client.id
				   WHERE trainer_id = '{$id}'  AND training.date = CURRENT_DATE;
				    "; */
	    
	    $sQuery = "SELECT
                	    training.*,
                	    users.first_name AS trainer_first_name,
                	    users.last_name AS trainer_last_name,
                	    gyms.name AS gym_name,
                	    gyms.address AS gym_address,
                	    gyms.city AS gym_city,
                	    GROUP_CONCAT(client.id) AS client_ids,
                	    GROUP_CONCAT(CONCAT(client.first_name, ' ', client.last_name)) AS client_names
                	    FROM training
                	    LEFT JOIN users ON training.trainer_id = users.id
                	    LEFT JOIN gyms ON training.gym_id = gyms.id
                	    LEFT JOIN training_clients ON training_clients.training_id = training.id
                	    LEFT JOIN users client ON training_clients.client_id = client.id
                	    WHERE training.trainer_id = '{$id}'
                	    AND training.date = CURRENT_DATE
                	    GROUP BY training.id;
				    ";
		$rows = $this->getDbAdapter()->query($sQuery)->fetchAll(\PDO::FETCH_ASSOC);
		if (isset($rows)) {
		    return $rows;
		}
		return false;
	}
	
	public function setTrainingsFinished(){
	    $sQuery = "UPDATE `training` SET `finished`='1' WHERE (date = CURRENT_DATE 
                   AND time < (CURRENT_TIME - INTERVAL 45 MINUTE)) AND (finished = 0 AND cancelled = 0);";
	    
	    //proveri ovo
	    
	    $rows = $this->getDbAdapter()->query($sQuery);
	    
	    if (isset($rows)) {
	        return $rows;
	    }
	    return false;
	}
	
	public function updateTrainingPlan($request){
	    $sQuery = "UPDATE `training` SET training_plan = :training_plan
 WHERE id = :id";
	    
	    $rows = $this->getDbAdapter()->query($sQuery, $request);
	    
	    if (isset($rows)) {
	        return $rows;
	    }
	    return false;
	}
	
	public function updateTrainingGroup(array $request){
	    $sQuery = "UPDATE `training` SET is_group = :is_group WHERE id = :id";
	    return $this->getDbAdapter()->query($sQuery, $request)->rowCount();
	}
	
	public function getTrainingsByDate(string $id, string $date) {
	    /* $sQuery = "SELECT client.id as client_id, client.first_name as client_first_name, client.last_name as client_last_name, training.*, users.first_name as trainer_first_name, users.last_name as trainer_last_name,
                   gyms.name as gym_name, gyms.address as gym_address, cities.city as gym_city FROM training
                   LEFT JOIN users ON training.trainer_id = users.id
                   LEFT JOIN gyms ON training.gym_id = gyms.id
                   LEFT JOIN cities ON cities.id = gyms.city_id
                   LEFT JOIN training_clients ON training_clients.training_id = training.id
                   LEFT JOIN users client ON training_clients.client_id = client.id
				   WHERE trainer_id = '{$id}'  AND training.date = '{$date}'; 
				    ";*/
	    
	    $sQuery = "SELECT
                	    training.*,
                	    users.first_name AS trainer_first_name,
                	    users.last_name AS trainer_last_name,
                	    gyms.name AS gym_name,
                	    gyms.address AS gym_address,
                	    gyms.city AS gym_city,
                	    GROUP_CONCAT(client.id) AS client_ids,
                	    GROUP_CONCAT(CONCAT(client.first_name, ' ', client.last_name)) AS client_names
                	    FROM training
                	    LEFT JOIN users ON training.trainer_id = users.id
                	    LEFT JOIN gyms ON training.gym_id = gyms.id
                	    LEFT JOIN training_clients ON (training_clients.training_id = training.id ) 
                        #AND training_clients.cancelled = 0
                	    LEFT JOIN users client ON training_clients.client_id = client.id
                	    WHERE trainer_id = '{$id}'  AND training.date = '{$date}'
                	    GROUP BY training.id;
				    ";
	    
	    
	    $rows = $this->getDbAdapter()->query($sQuery)->fetchAll(\PDO::FETCH_ASSOC);
	    if (isset($rows)) {
	        return $rows;
	    }
	    return false;
	}
	
	public function setTrainingCancelledTrainer(string $id){
	    $sQuery = "UPDATE `training` SET `cancelled`='1' WHERE id = '{$id}';
				    ";
	    $rows = $this->getDbAdapter()->query($sQuery)->fetchAll(\PDO::FETCH_ASSOC);
	    if (isset($rows)) {
	        return $rows;
	    }
	    return false;
	}
	
	public function setCancelledClientsByTrainingId(string $id){
	    $sQuery = "UPDATE `training_clients` SET cancelled = '1' WHERE training_id = '{$id}';
				    ";
	    $sQuery2 = "SELECT * FROM `training_clients` WHERE training_id = '{$id}' AND cancelled = '1'; 
                    ";
	    $this->getDbAdapter()->query($sQuery);
	    $rows = $this->getDbAdapter()->query($sQuery2)->fetchAll(\PDO::FETCH_ASSOC);
	    if (isset($rows)) {
	        return $rows;
	    }
	    return false;
	}
	
	public function setCancelledClientsByClientId(string $training_id, string $client_id){
// 	    $sQuery = "UPDATE `training_clients` SET cancelled = '1' WHERE training_id = '{$training_id}' AND client_id ='{$client_id}';
// 				    ";
	    
	    $sQuery2 = "SELECT * FROM `training_clients` WHERE training_id = '{$training_id}' AND client_id = '{$client_id}' AND cancelled = '1';
                    ";

	    $updateClientQuery = "UPDATE `training_clients`
                          SET cancelled = '1'
                          WHERE training_id = '{$training_id}' AND client_id = '{$client_id}'";
	    
	    $this->getDbAdapter()->query($updateClientQuery);
	    
	    $checkAllCancelledQuery = "SELECT COUNT(*) AS total_clients,
                                      SUM(cancelled) AS cancelled_clients
                               FROM `training_clients`
                               WHERE training_id = '{$training_id}'";
	    
	    $result = $this->getDbAdapter()->query($checkAllCancelledQuery)->fetchAll(\PDO::FETCH_ASSOC);
	    
	    
	    if ($result && $result[0]['total_clients'] == $result[0]['cancelled_clients']) {
	        $updateTrainingQuery = "UPDATE `training`
                                SET cancelled = '1'
                                WHERE id = '{$training_id}'";
	        
	        $this->getDbAdapter()->query($updateTrainingQuery);
	    }
	    
// 	    $this->getDbAdapter()->query($sQuery);
	    $rows = $this->getDbAdapter()->query($sQuery2)->fetchAll(\PDO::FETCH_ASSOC);
	    
	    if (isset($rows)) {
	        return $rows;
	    }
	    return false;
	}
	
	public function deleteClientsByClientId(string $training_id, string $client_id){
	
	    $updateClientQuery = "
            DELETE FROM training_clients
            WHERE training_id = :training_id
              AND client_id   = :client_id
        ";
	    
	    $rowCount = $this->getDbAdapter()->query($updateClientQuery,[
	        'training_id' => $training_id,
	        'client_id'   => $client_id
	    ])->rowCount();
	    
	    $sql = "
            UPDATE training t
            SET t.cancelled = 1
            WHERE t.id = :training_id
              AND NOT EXISTS (
                    SELECT 1
                    FROM training_clients tc
                    WHERE tc.training_id = t.id
                      AND tc.cancelled = 0
              )
        ";
	
	    $this->getDbAdapter()->query($sql,['training_id' => $training_id])->rowCount();
	    
	    return $rowCount === 0 ? false : true;
	
	}
	
	public function getClientTrainingsByDate(string $id, string $date) {
	    $sQuery = "
                SELECT
                    users.id AS trainer_id,
                    training.id,
                    users.first_name AS trainer_first_name,
                    users.last_name AS trainer_last_name,
                    users.phone,
                    gyms.name AS gym_name,
                    gyms.address AS gym_address,
                    gyms.city AS gym_city,
                    training.date,
                    training.is_group,
                    training.cancelled,
                    training.finished,
                    training.duration,
                    training.time,
                    tc.cancelled AS one_cancelled,
                    GROUP_CONCAT(DISTINCT client.client_id) AS client_ids
                FROM training_clients tc
                INNER JOIN training ON tc.training_id = training.id
                LEFT JOIN users ON training.trainer_id = users.id
                LEFT JOIN gyms ON training.gym_id = gyms.id
                LEFT JOIN training_clients client ON (training.id = client.training_id AND client.cancelled = 0)
                WHERE tc.client_id = ?
                  AND training.date = ?
                GROUP BY training.id
            ";
	    
	    $stmt = $this->getDbAdapter()->prepare($sQuery);
	    $stmt->execute([$id, $date]);
	    $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
	    
	    return empty($rows) ? [] : $rows;
	}
	
	
	public function getTrainingsTrainer(string $user_id)
	{
	    $sQuery = "SELECT COUNT(*) As total_trainings FROM training WHERE trainer_id = '{$user_id}' AND cancelled != '1' AND finished ='1';";
	    
	    $row = $this->getDbAdapter()
	    ->query($sQuery)
	    ->fetchAll(\PDO::FETCH_ASSOC);
	    if (isset($row)) {
	        return $row[0]["total_trainings"];
	    }
	    return false;
	}
	
	public function getTrainingsClient(string $user_id)
	{
	    $sQuery = "SELECT COUNT(*) AS total_trainings FROM training_clients LEFT JOIN training ON training.id = training_clients.training_id WHERE training_clients.client_id = '{$user_id}' AND training.finished = 1 AND training_clients.cancelled != 1;";
	    
	    $row = $this->getDbAdapter()
	    ->query($sQuery)
	    ->fetchAll(\PDO::FETCH_ASSOC);
	    if (isset($row)) {
	        return $row[0]["total_trainings"];
	    }
	    return false;
	}
	
	function addClientsToTrainingsBatch($clients, $prices, $training_id)
	{
	    if (empty($training_id) || empty($clients)) {
	        return;
	    }
	    
	    $connections = [];
	        foreach ($clients as $client_id) {
	            $price = isset($prices[$client_id]) ? intval($prices[$client_id]) : 0;
	            
	            $connections[] = [
	                'training_id' => $training_id,
	                'client_id'   => $client_id,
	                'price'       => $price
	            ];
	        }
	    
	    // Batch INSERT po 1000 redova
	    $chunks = array_chunk($connections, 1000);
	    foreach ($chunks as $chunk) {
	        $values = [];
	        foreach ($chunk as $conn) {
	            $values[] = sprintf(
	                "(%d, %d, %d)",
	                $conn['training_id'],
	                $conn['client_id'],
	                $conn['price']
	                );
	        }
	        
	        $sql = "INSERT INTO training_clients (training_id, client_id, price) VALUES " . implode(',', $values);
	        
	        $this->getDbAdapter()->query($sql);
	    }
	}
	
	public function removeTrainings(string $id)
	{
	    if(empty($id))
	    {
	        return false;
	    }
	    
	    $bind["client_id"] = $id;
	    
	    $sql = "START TRANSACTION;
            
            -- GROUP treninzi
            DELETE tc
            FROM training_clients tc
            JOIN training t ON t.id = tc.training_id
            WHERE tc.client_id = :client_id
              AND t.is_group = 1
              AND t.date >= CURDATE();
            
            -- INDIVIDUAL treninzi
            DELETE t
            FROM training t
            JOIN training_clients tc ON tc.training_id = t.id
            WHERE tc.client_id = :client_id
              AND t.is_group = 0
              AND t.date >= CURDATE();
            
            COMMIT;";
	    
	    $stmt = $this->getDbAdapter()->query($sql, $bind);
	    
	    return $stmt->rowCount(); // broj obrisanih redova
	}
	
	public function clientIsInSystem(string $user_id)
	{
	    $bind["user_id"] = $user_id;
	    $sQuery = "SELECT 
            CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END AS inSystem
        FROM training_clients 
        JOIN training ON training.id = training_clients.training_id
        WHERE training_clients.client_id = :user_id
        AND training.date >= CURDATE()
        AND training_clients.cancelled != 1;
";
	    
	    $row = $this->getDbAdapter()
	    ->query($sQuery, $bind)
	    ->fetch(\PDO::FETCH_ASSOC);
	    
	    if (isset($row["inSystem"])) {
	        return (string)$row["inSystem"];
	    }
	    return "0";
	}
	
	public function getTrainingsClientTrainer(string $trainer_id, string $client_id){
	    $sQuery = "SELECT COUNT(*) AS total_trainings FROM training_clients LEFT JOIN training ON training.id = training_clients.training_id WHERE training_clients.client_id = '{$client_id}'
 #AND training.finished = 1 
AND training_clients.cancelled != 1 AND  training.trainer_id = '{$trainer_id}';";
	
	    $row = $this->getDbAdapter()
	    ->query($sQuery)
	    ->fetchAll(\PDO::FETCH_ASSOC);
	    if (isset($row)) {
	        return $row[0]["total_trainings"];
	    }
	    return false;
	
	}
	
	public function getConnectedSinceClientTrainer(string $trainer_id, string $client_id)
	{
	    $sQuery = "SELECT connected_since
               FROM training_clients
               LEFT JOIN training ON training.id = training_clients.training_id
               WHERE training_clients.client_id = ?
                 AND training.trainer_id = ?
                 AND training_clients.cancelled != 1
               ORDER BY training_clients.connected_since ASC
               LIMIT 1";
	    
	    try {
	        $stmt = $this->getDbAdapter()->prepare($sQuery);
	        $stmt->execute([$client_id, $trainer_id]);
	        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
	        
	        if ($row && isset($row['connected_since'])) {
	            return $row['connected_since']; // Returns the date string (e.g., '2024-01-15')
	        }
	        
	        return false; // No relationship found or no connected_since
	    } catch (\Exception $e) {
	        // Optional: log error
	        return false;
	    }
	}
	
	
	public function getTrainingById(string $id) {
	    $sQuery = "SELECT training.id, users.first_name as trainer_first_name, users.last_name as trainer_last_name,
                   gyms.name as gym_name, gyms.address as gym_address, gyms.city as gym_city, training.date, training.is_group,
                   training.time FROM training
                   LEFT JOIN users ON training.trainer_id = users.id
                   LEFT JOIN gyms ON training.gym_id = gyms.id 
				   WHERE training.id = '{$id}';
				    ";
	    $rows = $this->getDbAdapter()->query($sQuery)->fetchAll(\PDO::FETCH_ASSOC);
	    if (isset($rows)) {
	        return $rows;
	    }
	    return false;
	}
	
	public function insertTraining(string $trainer_id, string $gym_id, string $is_group, string $date, string $time, string $training_plan, string $duration){
	    $sQuery = "INSERT INTO `training`(`trainer_id`, `gym_id`, `is_group`, `date`, `time`, `training_plan`, `duration`
                  ) VALUES ('{$trainer_id}','{$gym_id}','{$is_group}','{$date}','{$time}', '{$training_plan}', '{$duration}');
				    ";
	    
	    $sQuery2 = "SELECT * 
                    FROM training
                    WHERE trainer_id = '{$trainer_id}'
                    ORDER BY id DESC 
                    LIMIT 1";
	    
	    $this->getDbAdapter()->query($sQuery)->fetchAll(\PDO::FETCH_ASSOC);
	    
	    $rows = $this->getDbAdapter()->query($sQuery2)->fetchAll(\PDO::FETCH_ASSOC);
	    if (isset($rows)) {
	        return $rows;
	    }
	    return false;
	}
	
	public function insertTrainingFix(string $trainer_id, string $gym_id, string $is_group, string $date, string $time, string $training_plan, string $duration){
	    $sQuery = "INSERT INTO `training`(`trainer_id`, `gym_id`, `is_group`, `date`, `time`, `training_plan`, `duration`
                  ) VALUES ('{$trainer_id}','{$gym_id}','{$is_group}','{$date}','{$time}', '{$training_plan}', '{$duration}');
				    ";
	    
	    $r = $this->getDbAdapter()->query($sQuery);
	    
	    if (isset($r)) {
	        return $this->getDbAdapter()->getLastInsertId("training");
	    }
	    return false;
	}
	
	public function insertClientToTraining(string $trainer_id, string $client_id, string $price){
	    $sQuery = "INSERT INTO `training_clients`(`training_id`, `client_id`, `price`)
                   VALUES
                   ('{$trainer_id}','{$client_id}','{$price}');
				    ";
	    $rows = $this->getDbAdapter()->query($sQuery)->fetchAll(\PDO::FETCH_ASSOC);
	    if (isset($rows)) {
	        return $rows;
	    }
	    return false;
	}
	
	public function getReportsByIds(string $trainer_id, string $client_id) {
	    $sQuery = "SELECT training_clients.*, training_clients.id as topay_id, training.date, training.time, gyms.name AS gym FROM training_clients 
                   LEFT JOIN training ON training.id = training_clients.training_id 
                   LEFT JOIN gyms ON gyms.id = training.gym_id 
                   WHERE training_clients.client_id = {$client_id} AND training.trainer_id = {$trainer_id} AND training.cancelled = '0' AND training_clients.cancelled = '0';
				    ";
	    $rows = $this->getDbAdapter()->query($sQuery)->fetchAll(\PDO::FETCH_ASSOC);
	    if (isset($rows)) {
	        return $rows;
	    }
	    return false;
	}
	
	public function getReportsByIdsAndDate(string $trainer_id, string $client_id, string $date_string) {
	    $sQuery = "SELECT training_clients.*, training_clients.id as topay_id, training.date, training.time, gyms.name AS gym FROM training_clients
                   LEFT JOIN training ON training.id = training_clients.training_id
                   LEFT JOIN gyms ON gyms.id = training.gym_id
                   WHERE training_clients.client_id = {$client_id} AND training.trainer_id = {$trainer_id} 
                   AND training.cancelled = '0' AND training_clients.cancelled = '0'  AND training.date LIKE '{$date_string}%';
				    ";
	    $rows = $this->getDbAdapter()->query($sQuery)->fetchAll(\PDO::FETCH_ASSOC);
	    if (isset($rows)) {
	        return $rows;
	    }
	    return false;
	}
	
	public function getReportsByTrainerId(string $trainer_id) {
	    $sQuery = "SELECT training_clients.*, training_clients.id as topay_id, training.date, training.time, gyms.name AS gym FROM training_clients
                   LEFT JOIN training ON training.id = training_clients.training_id
                   LEFT JOIN gyms ON gyms.id = training.gym_id
                   WHERE training.trainer_id = '{$trainer_id}' AND training.cancelled = '0' AND training_clients.cancelled = '0';
				    ";
	    $rows = $this->getDbAdapter()->query($sQuery)->fetchAll(\PDO::FETCH_ASSOC);
	    if (isset($rows)) {
	        return $rows;
	    }
	    return false;
	}
	
	
	public function getReportsByClientId(string $client_id) {
	    $sQuery = "SELECT training_clients.*, training_clients.id as topay_id, training.date, training.time, gyms.name AS gym FROM training_clients
                   LEFT JOIN training ON training.id = training_clients.training_id
                   LEFT JOIN gyms ON gyms.id = training.gym_id
                   WHERE training_clients.client_id = '{$client_id}' AND training.cancelled = '0' AND training_clients.cancelled = '0';
				    ";
	    $rows = $this->getDbAdapter()->query($sQuery)->fetchAll(\PDO::FETCH_ASSOC);
	    if (isset($rows)) {
	        return $rows;
	    }
	    return false;
	}
	
	
	public function setTrainingPaid(string $id) {
	    $sQuery = "UPDATE `training_clients` SET `paid`='1' WHERE id ={$id};
				    ";
	    $rows = $this->getDbAdapter()->query($sQuery)->fetchAll(\PDO::FETCH_ASSOC);
	    if (isset($rows)) {
	        return $rows;
	    }
	    return false;
	}
	
	public function getPriceByTrainingId(string $id) {
	    $sQuery = "SELECT * FROM training_clients WHERE id ={$id};
				    ";
	    $rows = $this->getDbAdapter()->query($sQuery)->fetchAll(\PDO::FETCH_ASSOC);
	    if (isset($rows)) {
	        return $rows;
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
	
	public function getTrainingDatesMonthlyTrainer(string $compare_string, string $trainer_id) {
	    $sQuery = "SELECT training.date FROM training WHERE training.date LIKE '{$compare_string}%'
                   AND training.trainer_id = '{$trainer_id}'";
	    $rows = $this->getDbAdapter()->query($sQuery)->fetchAll(\PDO::FETCH_ASSOC);
	    
	    return $rows;
	}
	
	public function getTrainingDatesMonthlyClient(string $compare_string, string $client_id) {
	    $sQuery = "SELECT training.date FROM training
                   LEFT JOIN training_clients ON training.id = training_clients.training_id
				   WHERE training_clients.client_id = '{$client_id}'  AND training.date LIKE '{$compare_string}%';
				    ";
	    $rows = $this->getDbAdapter()->query($sQuery)->fetchAll(\PDO::FETCH_ASSOC);
	    if (isset($rows)) {
	        return $rows;
	    }
	    return false;
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
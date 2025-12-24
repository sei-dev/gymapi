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
        $dbAdapter->setDbTable(self::getTablePrefix() . "users");
        $this->setDbAdapter($dbAdapter);
    }

    /**
     *
     * @param string $email
     * @throws ApiException
     * @return array
     */
    public function getUserById(string $id)
    {
        $sQuery = "SELECT users.*, cities.city as location FROM users LEFT JOIN cities ON users.city_id = cities.id
				   WHERE users.id = '{$id}'
				   LIMIT 1";
        $row = $this->getDbAdapter()
            ->query($sQuery)
            ->fetch(\PDO::FETCH_ASSOC);
        if (isset($row)) {
            return $row;
        }
        return false;
    }

    public function getDeviceTokenByUserId(string $id)
    {
        $sQuery = "SELECT users.device_token
				   WHERE users.id = '{$id}'
				   LIMIT 1";
        $row = $this->getDbAdapter()
            ->query($sQuery)
            ->fetch(\PDO::FETCH_ASSOC);
        if (isset($row)) {
            return $row;
        }
        return false;
    }
    
    
    public function getConnectionPriceByIds(string $trainer_id, string $client_id)
    {
        $sQuery = "SELECT price FROM connections WHERE
				   client_id = '{$client_id}' AND trainer_id = '{$trainer_id}'
				   LIMIT 1";
        $row = $this->getDbAdapter()
        ->query($sQuery)
        ->fetch(\PDO::FETCH_ASSOC);
        if (isset($row)) {
            return $row['price'];
        }
        return false;
    }
    
    public function getConnectionPricesByTrainerAndClients($trainer_id, $client_ids)
    {
        if (empty($client_ids)) return [];
        
        $placeholders = implode(',', array_fill(0, count($client_ids), '?'));
        $sql = "SELECT client_id, price
            FROM connections
            WHERE trainer_id = ? AND client_id IN ($placeholders)";
        
        $stmt = $this->getDbAdapter()->prepare($sql);
        $params = array_merge([$trainer_id], $client_ids);
        $stmt->execute($params);
        
        $result = [];
        while ($row = $stmt->fetch()) {
            $result[$row['client_id']] = $row['price'];
        }
        
        return $result;
    }

    public function getTrainerByConnectionId(string $id)
    {
        $sQuery = "SELECT users.* FROM users LEFT JOIN connections 
                   ON connections.trainer_id = users.id WHERE connections.id = '{$id}';";

        $row = $this->getDbAdapter()
            ->query($sQuery)
            ->fetch(\PDO::FETCH_ASSOC);
        if (isset($row)) {
            return $row;
        }
        return false;
    }

    public function getClientByConnectionId(string $id)
    {
        $sQuery = "SELECT users.* FROM users LEFT JOIN connections 
                   ON connections.client_id = users.id WHERE connections.id = '{$id}';";
        $row = $this->getDbAdapter()
            ->query($sQuery)
            ->fetch(\PDO::FETCH_ASSOC);
        if (isset($row)) {
            return $row;
        }
        return false;
    }

    public function checkIfConnected(string $client_id, string $trainer_id)
    {
        $sQuery = "SELECT * FROM connections WHERE client_id = '{$client_id}' AND trainer_id = '{$trainer_id}';
                ";
        $row = $this->getDbAdapter()
            ->query($sQuery)
            ->fetchAll(\PDO::FETCH_ASSOC);
        if (isset($row)) {
            return $row;
        }
        return false;
    }

    public function changeConnectionPrice(string $id, string $price)
    {
        $sQuery = "UPDATE `connections` SET `price`='{$price}' WHERE id = {$id};
                ";
        $row = $this->getDbAdapter()
            ->query($sQuery)
            ->fetchAll(\PDO::FETCH_ASSOC);
        if (isset($row)) {
            return $row;
        }
        return false;
    }
    
    
    
    public function changeConnectionPriceByIds(string $trainer_id, string $client_id, string $price)
    {
        $bind["trainer_id"] = $trainer_id;
        $bind["client_id"] = $client_id;
        $bind["price"] = $price;
        
        $sQuery = "UPDATE `connections` SET `price`= :price
        WHERE trainer_id = :trainer_id and client_id = :client_id;
                ";
        return $this->getDbAdapter()->prepare($sQuery)->execute($bind);
    }

    public function acceptConnection(string $id)
    {
        $sQuery = "UPDATE `connections` SET `accepted`='1' WHERE id = {$id};
                ";
        $row = $this->getDbAdapter()
            ->query($sQuery)
            ->fetchAll(\PDO::FETCH_ASSOC);
        if (isset($row)) {
            return $row;
        }
        return false;
    }

    public function checkIfSubPassed()
    {
        $sQuery = "UPDATE users
                    SET sub_paid = 0
                    WHERE sub_until < CURRENT_DATE;
                ";
        $row = $this->getDbAdapter()
            ->query($sQuery)
            ->fetchAll(\PDO::FETCH_ASSOC);
        if (isset($row)) {
            return $row;
        }
        return false;
    }

    public function removeConnection(string $id)
    {
        $sQuery = "DELETE FROM `connections` WHERE id = {$id}
                ";
        $row = $this->getDbAdapter()
            ->query($sQuery)
            ->fetchAll(\PDO::FETCH_ASSOC);
        if (isset($row)) {
            return $row;
        }
        return false;
    }

    public function getProfitProfileTrainer(string $trainer_id)
    {
        $sQuery = "SELECT SUM(training_clients.price) AS profit FROM training_clients
                   LEFT JOIN training ON training.id = training_clients.training_id
                   WHERE training_clients.paid = '1' AND training.trainer_id = '{$trainer_id}' AND training.cancelled = '0' AND training_clients.cancelled = '0';";

        $row = $this->getDbAdapter()
            ->query($sQuery)
            ->fetchAll(\PDO::FETCH_ASSOC);
        if (isset($row)) {
            if ($row[0]["profit"] == NULL || $row[0]["profit"] == "null") {
                $row[0]["profit"] = '0';
            }
            return $row[0]["profit"];
        }

        return false;
    }

    public function getProfitProfileClient(string $client_id)
    {
        $sQuery = "SELECT SUM(training_clients.price) AS profit FROM training_clients
                   LEFT JOIN training ON training.id = training_clients.training_id
                   WHERE training_clients.paid = '1' AND training_clients.client_id = '{$client_id}' AND training.cancelled = '0' AND training_clients.cancelled = '0';";

        $row = $this->getDbAdapter()
            ->query($sQuery)
            ->fetchAll(\PDO::FETCH_ASSOC);
        if (isset($row)) {
            if ($row[0]["profit"] == NULL || $row[0]["profit"] == "null") {
                $row[0]["profit"] = '0';
            }
            return $row[0]["profit"];
        }

        return false;
    }

    public function getProfitConnection(string $trainer_id, string $client_id)
    {
        $sQuery = "SELECT SUM(training_clients.price) AS profit FROM training_clients 
                    LEFT JOIN training ON training.id = training_clients.training_id 
                    WHERE training_clients.paid = '1' AND training.trainer_id = '{$trainer_id}' AND training_clients.client_id = '{$client_id}' AND training.cancelled = '0' AND training_clients.cancelled = '0';";

        $row = $this->getDbAdapter()
            ->query($sQuery)
            ->fetchAll(\PDO::FETCH_ASSOC);
        if (isset($row)) {
            if ($row[0]["profit"] == NULL || $row[0]["profit"] == "null") {
                $row[0]["profit"] = '0';
            }
            return $row[0]["profit"];
        }

        return false;
    }

    public function getDebtProfileTrainer(string $trainer_id)
    {
        $sQuery = "SELECT SUM(training_clients.price) AS debt FROM training_clients
                   LEFT JOIN training ON training.id = training_clients.training_id
                   WHERE training_clients.paid = '0' AND training.trainer_id = '{$trainer_id}' AND training.cancelled = '0' AND training_clients.cancelled = '0';";

        $row = $this->getDbAdapter()
            ->query($sQuery)
            ->fetchAll(\PDO::FETCH_ASSOC);
        if (isset($row)) {
            if ($row[0]["debt"] == NULL || $row[0]["debt"] == "null") {
                $row[0]["debt"] = '0';
            }
            return $row[0]["debt"];
        }

        return false;
    }

    public function getDebtProfileClient(string $client_id)
    {
        $sQuery = "SELECT SUM(training_clients.price) AS debt FROM training_clients
                   LEFT JOIN training ON training.id = training_clients.training_id
                   WHERE training_clients.paid = '0' AND training_clients.client_id = '{$client_id}' AND training.cancelled = '0' AND training_clients.cancelled = '0';";

        $row = $this->getDbAdapter()
            ->query($sQuery)
            ->fetchAll(\PDO::FETCH_ASSOC);
        if (isset($row)) {
            if ($row[0]["debt"] == NULL || $row[0]["debt"] == "null") {
                $row[0]["debt"] = '0';
            }
            return $row[0]["debt"];
        }

        return false;
    }

    public function getDebtConnection(string $trainer_id, string $client_id)
    {
        $sQuery = "SELECT SUM(training_clients.price) AS debt FROM training_clients
                    LEFT JOIN training ON training.id = training_clients.training_id
                    WHERE training_clients.paid = '0' AND training.trainer_id = '{$trainer_id}' AND training_clients.client_id = '{$client_id}' AND training.cancelled = '0' AND training_clients.cancelled = '0';";

        $row = $this->getDbAdapter()
            ->query($sQuery)
            ->fetchAll(\PDO::FETCH_ASSOC);

        if (isset($row)) {
            if ($row[0]["debt"] == NULL || $row[0]["debt"] == "null") {
                $row[0]["debt"] = '0';
            }
            return $row[0]["debt"];
        }

        return false;
    }

    public function getActiveTrainers(string $user_id)
    {
        $sQuery = "SELECT COUNT(*) As active_trainers FROM connections WHERE accepted = '1' and client_id = '{$user_id}';";

        $row = $this->getDbAdapter()
            ->query($sQuery)
            ->fetchAll(\PDO::FETCH_ASSOC);

        if (isset($row)) {
            return $row[0]["active_trainers"];
        }
        return false;
    }

    public function getActiveClients(string $user_id)
    {
        $sQuery = "SELECT COUNT(*) As active_clients FROM connections WHERE accepted = '1' and trainer_id = '{$user_id}';";

        $row = $this->getDbAdapter()
            ->query($sQuery)
            ->fetchAll(\PDO::FETCH_ASSOC);

        if (isset($row)) {
            return $row[0]["active_clients"];
        }
        return false;
    }

    public function getUsersByTrainingId(string $training_id)
    {
        $sQuery = "SELECT users.*, training_clients.training_id as training_id, training_clients.cancelled as user_cancelled,
                   cities.city as location FROM training_clients
                   LEFT JOIN users ON users.id = training_clients.client_id
                   LEFT JOIN cities ON users.city_id = cities.id
                   WHERE training_clients.training_id = {$training_id}
                   ;
                ";
        $row = $this->getDbAdapter()
            ->query($sQuery)
            ->fetchAll(\PDO::FETCH_ASSOC);
        if (isset($row)) {
            return $row;
        }
        return false;
    }

    public function searchConnectedUsers(string $trainer_id, string $param)
    {
        $sQuery = "SELECT users.id, connections.id as connection_id, users.first_name, users.last_name, users.phone, users.email, 
                    cities.city as location, connections.training_no, connections.price, connections.connected_since 
                    FROM connections LEFT JOIN users ON users.id = connections.client_id 
                    LEFT JOIN cities ON users.city_id = cities.id
                    WHERE connections.accepted = '1' AND connections.trainer_id = '{$trainer_id}' AND (users.first_name LIKE '{$param}%' 
                    OR users.last_name LIKE '{$param}%');
                ";
        $row = $this->getDbAdapter()
            ->query($sQuery)
            ->fetchAll(\PDO::FETCH_ASSOC);
        if (isset($row)) {
            return $row;
        }
        return false;
    }

    public function searchConnectedTrainers(string $client_id, string $param)
    {
        $sQuery = "SELECT users.id, connections.id as connection_id, users.first_name, users.last_name, users.phone, users.email,
                    cities.city as location, connections.training_no, connections.price, connections.connected_since
                    FROM connections LEFT JOIN users ON users.id = connections.trainer_id
                    LEFT JOIN cities ON users.city_id = cities.id
                    WHERE connections.accepted = '1' AND connections.client_id = '{$client_id}' AND (users.first_name LIKE '{$param}%' 
                    OR users.last_name LIKE '{$param}%');
                ";
        $row = $this->getDbAdapter()
            ->query($sQuery)
            ->fetchAll(\PDO::FETCH_ASSOC);
        if (isset($row)) {
            return $row;
        }
        return false;
    }

    public function getConnectedUsersByTrainerId(string $trainer_id)
    {
        $sQuery = "SELECT users.id, connections.id as connection_id, users.first_name, users.last_name, users.phone, users.email, 
                   cities.city as location, connections.training_no, connections.price, connections.connected_since, users.offline
                   FROM connections LEFT JOIN users ON users.id = connections.client_id 
                   LEFT JOIN cities ON users.city_id = cities.id
                   WHERE connections.trainer_id = {$trainer_id} AND connections.accepted = '1';
                ";
        $row = $this->getDbAdapter()
            ->query($sQuery)
            ->fetchAll(\PDO::FETCH_ASSOC);
        if (isset($row)) {
            return $row;
        }
        return false;
    }

    public function getConnectedUsersByClientId(string $client_id)
    {
        $sQuery = "SELECT users.id, connections.id as connection_id, users.first_name, users.last_name, users.phone, users.email, users.currency,
                   cities.city as location, connections.training_no, connections.price, connections.connected_since
                   FROM connections LEFT JOIN users ON users.id = connections.trainer_id
                   LEFT JOIN cities ON users.city_id = cities.id
                   WHERE connections.client_id = {$client_id} AND connections.accepted = '1';
                ";
        $row = $this->getDbAdapter()
            ->query($sQuery)
            ->fetchAll(\PDO::FETCH_ASSOC);
        if (isset($row)) {
            return $row;
        }
        return false;
    }

    public function getRequestsTrainer(string $trainer_id)
    {
        $sQuery = "SELECT users.*, connections.id as connection_id, connections.connected_since, connections.sent_by,
                   cities.city as location 
                   FROM connections LEFT JOIN users ON users.id = connections.client_id
                   LEFT JOIN cities ON users.city_id = cities.id
                   WHERE connections.trainer_id = {$trainer_id} AND connections.accepted = '0';
                    ";

        $row = $this->getDbAdapter()
            ->query($sQuery)
            ->fetchAll(\PDO::FETCH_ASSOC);
        if (isset($row)) {
            return $row;
        }
        return false;
    }

    public function getRequestsClient(string $client_id)
    {
        $sQuery = "SELECT users.*, connections.id as connection_id, connections.connected_since, connections.sent_by,
                   cities.city as location
                   FROM connections LEFT JOIN users ON users.id = connections.trainer_id
                   LEFT JOIN cities ON users.city_id = cities.id
                   WHERE connections.client_id = {$client_id} AND connections.accepted = '0';
                    ";

        $row = $this->getDbAdapter()
            ->query($sQuery)
            ->fetchAll(\PDO::FETCH_ASSOC);
        if (isset($row)) {
            return $row;
        }
        return false;
    }

    public function makeAcceptedConnection(string $client_id, string $trainer_id)
    {
        $sQuery = "INSERT IGNORE INTO `connections`(`trainer_id`, `client_id`, `accepted`) VALUES ('{$trainer_id}','{$client_id}', '1')
                    ";

        $row = $this->getDbAdapter()
            ->query($sQuery)
            ->fetchAll(\PDO::FETCH_ASSOC);
        if (isset($row)) {
            return $row;
        }
        return false;
    }
    
    public function makeConnection(string $client_id, string $trainer_id, string $sent_by)
    {
        $sQuery = "INSERT IGNORE INTO `connections`(`trainer_id`, `client_id`, `sent_by`) VALUES ('{$trainer_id}','{$client_id}','{$sent_by}')
                    ";
        
        $row = $this->getDbAdapter()
        ->query($sQuery)
        ->fetchAll(\PDO::FETCH_ASSOC);
        if (isset($row)) {
            return $row;
        }
        return false;
    }

    public function getTrainers(string $gender, string $city_id)
    {
        if ($city_id == "0") {
            $sQuery = "SELECT users.*, cities.city as location
                   FROM users LEFT JOIN cities ON users.city_id = cities.id
                   WHERE users.is_male = '{$gender}' AND users.is_trainer = '1'
                ";
        } else {
            $sQuery = "SELECT users.*, cities.city as location
                   FROM users LEFT JOIN cities ON users.city_id = cities.id
                   WHERE users.is_male = '{$gender}' AND users.city_id = '{$city_id}' AND users.is_trainer = '1'
                ";
        }

        $row = $this->getDbAdapter()
            ->query($sQuery)
            ->fetchAll(\PDO::FETCH_ASSOC);
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
    public function getUserByEmail(string $email)
    {
        $sQuery = "SELECT *
				FROM " . self::getTablePrefix() . "users
				WHERE email = '{$email}'
				LIMIT 1";
        $row = $this->getDbAdapter()
            ->query($sQuery)
            ->fetch(\PDO::FETCH_ASSOC);
        if (isset($row)) {
            return $row;
        }
        return false;
    }
    
    public function getClientByEmail(string $email)
    {
        $sQuery = "SELECT *
				FROM " . self::getTablePrefix() . "users
				WHERE email = '{$email}'AND is_trainer = '0'
				LIMIT 1";
        $row = $this->getDbAdapter()
        ->query($sQuery)
        ->fetch(\PDO::FETCH_ASSOC);
        if (isset($row)) {
            return $row;
        }
        return false;
    }

    public function getUserByHash(string $hash)
    {
        $sQuery = "SELECT *
				FROM " . self::getTablePrefix() . "users
				WHERE email_hash = '{$hash}'
				LIMIT 1";
        $row = $this->getDbAdapter()
        ->query($sQuery)
        ->fetch(\PDO::FETCH_ASSOC);
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
    public function getByDeviceToken(string $token)
    {
        $sQuery = "SELECT *
				FROM " . self::getTablePrefix() . "users
				WHERE device_token = '{$token}'
				LIMIT 1";
        $rows = $this->getDbAdapter()
            ->query($sQuery)
            ->fetchAll(\PDO::FETCH_ASSOC);

        return $rows;
    }

    public function setDeviceToken(String $id, String $device_token)
    {
        $sQuery = "UPDATE " . self::getTablePrefix() . "users
                   SET device_token = '{$device_token}'
				   WHERE id = '{$id}'
				";

        return $this->getDbAdapter()->query($sQuery);
    }
    
    
    
    public function setCurrency(String $id, String $currency)
    {
        $sQuery = "UPDATE " . self::getTablePrefix() . "users
                   SET currency = '{$currency}'
				   WHERE id = '{$id}'
				";
        
        return $this->getDbAdapter()->query($sQuery);
    }

    /**
     *
     * @param string $email
     * @throws ApiException
     * @return array
     */
    public function getUsers(array $email)
    {
        $emailsString = "'" . implode("', '", $email) . "'";

        $sQuery = "SELECT *
				FROM " . self::getTablePrefix() . "users
				WHERE email in ({$emailsString})
				";
        return $this->getDbAdapter()
            ->query($sQuery)
            ->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function saveServices(String $id, String $fun_tr, String $cardio_tr, String $str_tr, String $flex_tr, String $as_tr, String $fun_st, String $ub_tr, String $lb_tr, String $inj_tr)
    {
        $sQuery = "UPDATE `users` SET `functional_training`='{$fun_tr}',`cardio_training`='{$cardio_tr}',
                    `strength_training`='{$str_tr}',`flexibility_training`='{$flex_tr}',
                    `antistress_training`='{$as_tr}',`functional_stretching`='{$fun_st}',
                    `upperb_training`='{$ub_tr}',`lowerb_training`='{$lb_tr}',`injury_training`='{$inj_tr}'
                     WHERE id = {$id}";

        return $this->getDbAdapter()->query($sQuery);
    }

    public function updateInfo(String $id, String $name, String $surname, String $age, String $phone, String $email, String $deadline,
                               String $is_male, String $city_id, String $en, String $rs, String $ru, String $country_id, String $nationality)
    {
        $sQuery = "UPDATE `users` SET `first_name`='{$name}',`last_name`='{$surname}',`email`='{$email}',
                        `phone`='{$phone}',`deadline`='{$deadline}', 
                        `is_male`='{$is_male}',`age`='{$age}',`city_id`='{$city_id}',
                        `language_english`='{$en}',`language_serbian`='{$rs}',`language_russian`='{$ru}', `country_id`='{$country_id}', `nationality` = '{$nationality}'
                        WHERE `id`='{$id}'";

        return $this->getDbAdapter()->query($sQuery);
    }

    public function editProfile(String $id, String $name, String $address, String $pib, String $phone, String $email, String $contact_name, String $contact_lastname, String $contact_phone)
    {
        $sQuery = "UPDATE" . self::getTablePrefix() . " `users` SET `name`='{$name}',`pib`='{$pib}',`email`='{$email}',
                  `phone`='{$phone}',`address`='{$address}',`contact_name`='{$contact_name}',
                  `contact_lastname`='{$contact_lastname}',`contact_phone`='{$contact_phone}' WHERE id = '{$id}'
                  LIMIT 1";

        return $this->getDbAdapter()->query($sQuery);
    }

    public function updateSub(String $id, String $date)
    {
        $sQuery = "UPDATE" . self::getTablePrefix() . " `users` SET `sub_paid`='1',`sub_until`='{$date}'
                  WHERE id = '{$id}'
                  LIMIT 1";

        return $this->getDbAdapter()->query($sQuery);
    }

    public function changeSub(String $id, String $is_monthly)
    {
        $sQuery = "UPDATE" . self::getTablePrefix() . " `users` SET `is_monthly_subscription`= '{$is_monthly}'
                  WHERE id = '{$id}'
                  LIMIT 1";

        return $this->getDbAdapter()->query($sQuery);
    }

    public function getSubLength(String $id)
    {
        $sQuery = "SELECT sub_until FROM users
                  WHERE id = '{$id}'
                  LIMIT 1;";

        $row = $this->getDbAdapter()
            ->query($sQuery)
            ->fetchAll(\PDO::FETCH_ASSOC);
        if (isset($row[0]["sub_until"])) {
            return $row[0]["sub_until"];
        }
        return false;
    }

    public function changePassword(String $id, String $hash_pass)
    {
        $sQuery = "UPDATE " . self::getTablePrefix() . "users
                   SET password = '{$hash_pass}'
				   WHERE id = '{$id}'
				";

        return $this->getDbAdapter()->query($sQuery);
    }

    public function register(String $name, String $surname, String $age, String $phone, String $password, String $email,
        String $deadline, String $is_male, String $city_id, String $en, String $rs, String $ru, String $is_trainer, String $country_id, String $nationality, String $email_hash, String $offline = "0")
    {
        $sQuery = "INSERT INTO `users`(`first_name`, `last_name`, `email`, `password`, `phone`, `deadline`, 
                    `is_trainer`, `is_male`, `age`,`city_id`,
                    `language_english`, `language_serbian`, `language_russian`, `country_id`, `nationality`, `email_hash`, `offline`)
                     VALUES ('{$name}','{$surname}','{$email}','{$password}',
                    '{$phone}','{$deadline}','{$is_trainer}','{$is_male}','{$age}','{$city_id}',
                    '{$en}','{$rs}','{$ru}', '{$country_id}', '{$nationality}', '{$email_hash}', '{$offline}');
				";

        $sQuery2 = "SELECT * FROM `users` WHERE first_name = '{$name}' AND last_name = '{$surname}' AND email = '{$email}';";

        $this->getDbAdapter()->query($sQuery);

        return $this->getDbAdapter()
            ->query($sQuery2)
            ->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function removeInactive()
    {
        $sQuery = "DELETE FROM table_name WHERE active_profile = '0';";

        return $this->getDbAdapter()->query($sQuery);
    }

    public function forgotPassword(String $id, String $hash)
    {
        $sQuery = "UPDATE " . self::getTablePrefix() . "users
                   SET password = '{$hash}'
				   WHERE id = '{$id}'
				";

        return $this->getDbAdapter()->query($sQuery);
    }

    public function setMailHash(String $id, String $hash)
    {
        $sQuery = "UPDATE " . self::getTablePrefix() . "users
                   SET email_hash = '{$hash}'
				   WHERE id = '{$id}'
				";
        
        return $this->getDbAdapter()->query($sQuery);
    }
    
    public function setAppLanguage(String $id, String $language)
    {
        $sQuery = "UPDATE " . self::getTablePrefix() . "users
                   SET app_lang = '{$language}'
				   WHERE id = '{$id}'
				";
        
        return $this->getDbAdapter()->query($sQuery);
    }
    
    public function getAppLanguage(String $id)
    {
        $sQuery = "SELECT app_lang FROM users
				   WHERE id = '{$id}'
				";
        
        $row = $this->getDbAdapter()
        ->query($sQuery)
        ->fetchAll(\PDO::FETCH_ASSOC);
        if (isset($row[0]["app_lang"])) {
            return $row[0]["app_lang"];
        }
        return false;
    }
    
    // bcrypt, then compare hash with password
    public function login(string $email)
    {

        // $emailsString = "'" . implode("', '", $email) . "'";
        // $passString = strval($password);
        $sQuery = "SELECT *
				FROM " . self::getTablePrefix() . "users
				WHERE email = '{$email}'
				";

        return $this->getDbAdapter()
            ->query($sQuery)
            ->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     *
     * @param string $email
     * @param string $password
     * @throws ApiException
     * @return array
     */
    public function signup(string $firstName, string $password)
    {
        $data = [
            "first_name" => $firstName,
            "password" => $password
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
    public function getHash(string $email)
    {
        $sQuery = "SELECT password
				FROM " . self::getTablePrefix() . "users
				WHERE email = '{$email}'
				LIMIT 1";
        $row = $this->getDbAdapter()
            ->query($sQuery)
            ->fetchAll(\PDO::FETCH_ASSOC);
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
    public function update(array $data)
    {
        return $this->getDbAdapter()->update($data);
    }
}
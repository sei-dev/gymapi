<?php
namespace APISDK;

use APISDK\ApiException;
use Firebase\JWT\JWT;
use Exception;
use APISDK\Models\Users;
use APISDK\Models\Clients;
use APISDK\Models\ClientObjects;
use APISDK\Models\AppReports;
use APISDK\Models\Baits;
use APISDK\Models\Trainings;
use APISDK\Models\Cities;
use APISDK\Models\Measurements;
use APISDK\Models\Gyms;
use phpFCMv1\Client;
use phpFCMv1\Notification;
use phpFCMv1\Recipient;

// const URL = "https://trpezaapi.lokalnipazar.rs";
/**
 * Site specific set of APIs
 *
 * @author arsenleontijevic
 * @since 30.09.2019
 */
class Sdk extends Api
{

    const DIR_UPLOADS = __DIR__ . "/../../images/";

    const DIR_USERS = "users";

    /*
     * const DIR_BAITS = "baits";
     * const DIR_USERS = "users";
     * const DIR_CATEGORIES = "categories";
     * const DIR_REPORTS = "reports";
     */

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
            'forgotPassword',
            'setTrainingsFinished',
            'initPayment'
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
     * @api {post}? crossCheck
     * @apiVersion 1.0.0
     * @apiSampleRequest https://uapi.intechopen.com
     * @apiName crossCheck
     * @apiGroup Users
     * @apiDescription crossCheck api will remove ineligible emails from the call list (Internal users, editors..)
     * @apiParam {String} action=crossCheck API Action.
     * @apiParam {Array} emails JSON array of author emails
     * @apiParam {String} book_id Manager book ID
     * @apiHeader {String} Authorization='Bearer <ACCESS_TOKEN>' access_token
     */

    /*
     * array_walk($products, function(&$a) {
     * if ($this->isFileExists(self::, $a["id"])) {
     * $a['image'] = $this->domain."/images/products/".$a["id"].".png?r=" . rand(0,100000);
     * }else{
     * $a['image'] = $this->domain."/images/logo.png";
     * }
     * $a["description"] = strip_tags($a["description"]);
     * $a["description"] = html_entity_decode($a["description"]);
     * });
     */

    // Preradi
    private function isFileExists($dir, $id)
    {
        return file_exists(self::DIR_UPLOADS . DIRECTORY_SEPARATOR . $dir . DIRECTORY_SEPARATOR . $id . ".png");
    }

    private function getTodayTrainingsByTrainerId()
    {
        $request = $this->filterParams([
            'trainer_id'
        ]);

        $training_model = new Trainings($this->dbAdapter);
        $trainings = $training_model->getTodayTrainingsByTrainerId($request['trainer_id']);

        return $this->formatResponse(self::STATUS_SUCCESS, "", $trainings);
    }

    private function getTrainingById()
    {
        $request = $this->filterParams([
            'id'
        ]);

        $training_model = new Trainings($this->dbAdapter);
        $trainings = $training_model->getTrainingById($request['id']);

        return $this->formatResponse(self::STATUS_SUCCESS, "", $trainings);
    }

    private function getTrainingsByDate()
    {
        $request = $this->filterParams([
            'id',
            'date'
        ]);

        $training_model = new Trainings($this->dbAdapter);
        $trainings = $training_model->getTrainingsByDate($request['id'], $request['date']);

        return $this->formatResponse(self::STATUS_SUCCESS, "", $trainings);
    }

    private function checkIfConnected()
    {
        $request = $this->filterParams([
            'client_id',
            'trainer_id'
        ]);

        $user_model = new Users($this->dbAdapter);
        $users = $user_model->checkIfConnected($request['client_id'], $request['trainer_id']);

        return $this->formatResponse(self::STATUS_SUCCESS, "", $users);
    }

    private function insertOneTimeTraining()
    {
        $request = $this->filterParams([
            'trainer_id',
            'gym_id',
            'date',
            'time',
            'is_group'
        ]);

        $training_model = new Trainings($this->dbAdapter);
        $trainings = $training_model->insertTraining($request['trainer_id'], $request['gym_id'], $request['is_group'], $request['date'], $request['time']);

        return $this->formatResponse(self::STATUS_SUCCESS, "", $trainings);
    }

    private function addClientToTraining()
    {
        $request = $this->filterParams([
            'training_id',
            'client_id',
            'price',
            'trainer_id'
        ]);

        $training_model = new Trainings($this->dbAdapter);
        $trainings = $training_model->insertClientToTraining($request['training_id'], $request['client_id'], $request['price']);
        $training_model->addDebtConnection($request['trainer_id'], $request['client_id'], $request['price']);

        $training_info = $training_model->getTrainingById($request['training_id']);

        $user_model = new Users($this->dbAdapter);
        $client = $user_model->getUserById($request['client_id']);
        $trainer = $user_model->getUserById($request['trainer_id']);

        $date = $training_info['date'];
        $date = date_format($date, "d/m/Y H:i:s");
        $time = $training_info['time'];
        $time = date('H:i', strtotime($time));

        // $this->sendNotification($trainer['first_name'] . " je zakazao novi trening.", $date . " u " . $time, $client["device_token"]);

        return $this->formatResponse(self::STATUS_SUCCESS, "", $trainings);
    }

    private function setTrainingCancelledTrainer()
    {
        $request = $this->filterParams([
            'id',
            'trainer_id'
        ]);

        $training_model = new Trainings($this->dbAdapter);
        $user_model = new Users($this->dbAdapter);

        $clients = $user_model->getUsersByTrainingId($request['id']);
        $training_info = $training_model->getTrainingById($request['id']);
        $trainer = $user_model->getUserById($request['trainer_id']);

        $trainings = $training_model->setTrainingCancelledTrainer($request['id']);

        $params = $training_model->setCancelledClientsByTrainingId($request['id']);

        $date = $training_info['date'];
        $date = date_format($date, "d/m/Y H:i:s");
        $time = $training_info['time'];
        $time = date('H:i', strtotime($time));

        foreach ($clients as $one) {
            // $this->sendNotification($trainer['first_name'] . " je otkazao trening.", "Trening je bio zakazan za " . $date . " u " . $time, $one["device_token"]);
        }

        foreach ($params as $one) {
            $training_model->removeDebtConnection($request['trainer_id'], $one['client_id'], $one['price']);
        }

        return $this->formatResponse(self::STATUS_SUCCESS, "", $trainings);
    }

    private function setTrainingsFinished()
    {
        $training_model = new Trainings($this->dbAdapter);
        $training_model->setTrainingsFinished();
        
        
        return $this->formatResponse(self::STATUS_SUCCESS, "", "[]");
    }

    private function setTrainingCancelledClient()
    {
        $request = $this->filterParams([
            'training_id',
            'client_id',
            'trainer_id'
        ]);

        $training_model = new Trainings($this->dbAdapter);
        $trainings = $training_model->setCancelledClientsByClientId($request['training_id'], $request['client_id']);

        $user_model = new Users($this->dbAdapter);
        $client = $user_model->getUserById($request['client_id']);
        $training_info = $training_model->getTrainingById($request['training_id']);
        $trainer = $user_model->getUserById($request['trainer_id']);

        $date = $training_info['date'];
        $date = date_format($date, "d/m/Y H:i:s");
        $time = $training_info['time'];
        $time = date('H:i', strtotime($time));

        // $this->sendNotification($client['first_name'] . " je otkazao trening.", "Trening je bio zakazan za " . $date . " u " . $time, $trainer["device_token"]);

        foreach ($trainings as $one) {
            $training_model->removeDebtConnection($request['trainer_id'], $one['client_id'], $one['price']);
        }

        return $this->formatResponse(self::STATUS_SUCCESS, "", $trainings);
    }

    private function getClientTrainingsByDate()
    {
        $request = $this->filterParams([
            'id',
            'date'
        ]);

        $training_model = new Trainings($this->dbAdapter);
        $trainings = $training_model->getClientTrainingsByDate($request['id'], $request['date']);

        return $this->formatResponse(self::STATUS_SUCCESS, "", $trainings);
    }

    private function getUsersByTrainingId()
    {
        $request = $this->filterParams([
            'id'
        ]);

        $users_model = new Users($this->dbAdapter);
        $users = $users_model->getUsersByTrainingId($request['id']);

        array_walk($users, function (&$a) {
            if ($this->isFileExists(self::DIR_USERS, $a["id"])) {
                $a['image'] = $this->domain . "/images/users/" . $a["id"] . ".png?r=" . rand(0, 100000);
            } else {
                $a['image'] = $this->domain . "/images/users/logo.png";
            }
        });

        return $this->formatResponse(self::STATUS_SUCCESS, "", $users);
    }

    private function getGymsByUserId()
    {
        $request = $this->filterParams([
            'id'
        ]);

        $gyms_model = new Gyms($this->dbAdapter);
        $gyms = $gyms_model->getGymsByUserId($request['id']);

        return $this->formatResponse(self::STATUS_SUCCESS, "", $gyms);
    }

    private function addFitnessCenter()
    {
        $request = $this->filterParams([
            'user_id',
            'gym_id'
        ]);

        $gyms_model = new Gyms($this->dbAdapter);
        $gyms = $gyms_model->addFitnessCenter($request['user_id'], $request['gym_id']);

        return $this->formatResponse(self::STATUS_SUCCESS, "", $gyms);
    }

    private function removeFitnessCenter()
    {
        $request = $this->filterParams([
            'user_id',
            'gym_id'
        ]);

        $gyms_model = new Gyms($this->dbAdapter);
        $gyms = $gyms_model->removeFitnessCenter($request['user_id'], $request['gym_id']);

        return $this->formatResponse(self::STATUS_SUCCESS, "", $gyms);
    }

    private function getUserById()
    {
        $request = $this->filterParams([
            'id'
        ]);

        $users_model = new Users($this->dbAdapter);
        $training_model = new Trainings($this->dbAdapter);
        
        $users = $users_model->getUserById($request['id']);

        if ($this->isFileExists(self::DIR_USERS, $users["id"])) {
            $users['image'] = $this->domain . "/images/users/" . $users["id"] . ".png?r=" . rand(0, 100000);
        } else {
            $users['image'] = $this->domain . "/images/users/logo.png";
        }
        
        $users['active_clients'] = $users_model->getActiveClients($request['id']);
        $users['active_trainers'] = $users_model->getActiveTrainers($request['id']);
        $users['total_trainings_trainer'] = $training_model->getTrainingsTrainer($request['id']);
        $users['total_trainings_client'] = $training_model->getTrainingsClient($request['id']);
 
        return $this->formatResponse(self::STATUS_SUCCESS, "", $users);
    }

    private function saveServicesTrainer()
    {
        $request = $this->filterParams([
            'id',
            'fun_tr',
            'cardio_tr',
            'str_tr',
            'flex_tr',
            'as_tr',
            'fun_st',
            'ub_tr',
            'lb_tr',
            'inj_tr'
        ]);

        $users_model = new Users($this->dbAdapter);
        $users = $users_model->saveServices($request['id'], $request['fun_tr'], $request['cardio_tr'], $request['str_tr'], $request['flex_tr'], $request['as_tr'], $request['fun_st'], $request['ub_tr'], $request['lb_tr'], $request['inj_tr']);

        return $this->formatResponse(self::STATUS_SUCCESS, "", $users);
    }

    private function addMeasurement()
    {
        $request = $this->filterParams([
            'trainer_id',
            'client_id',
            'height',
            'weight',
            'neck',
            'chest',
            'gluteus',
            'quad',
            'leg',
            'waist',
            'biceps',
            'date',
            'e1_rep',
            'e2_rep',
            'e3_rep',
            'e1_kg',
            'e2_kg',
            'e3_kg'
        ]);

        $mes_model = new Measurements($this->dbAdapter);
        // $user_model = new Users($this->dbAdapter);

        $measurements = $mes_model->addMeasurement($request['trainer_id'], $request['client_id'], $request['height'], $request['weight'], $request['neck'], $request['chest'], $request['gluteus'], $request['quad'], $request['leg'], $request['waist'], $request['biceps'], $request['date'], $request['e1_rep'], $request['e2_rep'], $request['e3_rep'], $request['e1_kg'], $request['e2_kg'], $request['e3_kg']);

        return $this->formatResponse(self::STATUS_SUCCESS, "", $measurements);
    }

    private function getMeasurementsByIds()
    {
        $request = $this->filterParams([
            'trainer_id',
            'client_id'
        ]);

        $mes_model = new Measurements($this->dbAdapter);
        // $user_model = new Users($this->dbAdapter);

        $measurements = $mes_model->getMeasurementsByIds($request['trainer_id'], $request['client_id']);

        return $this->formatResponse(self::STATUS_SUCCESS, "", $measurements);
    }

    private function getMeasurementsByClientId()
    {
        $request = $this->filterParams([
            'client_id'
        ]);

        $mes_model = new Measurements($this->dbAdapter);
        // $user_model = new Users($this->dbAdapter);

        $measurements = $mes_model->getMeasurementsByClientId($request['client_id']);

        return $this->formatResponse(self::STATUS_SUCCESS, "", $measurements);
    }

    private function getReportsByIds()
    {
        $request = $this->filterParams([
            'trainer_id',
            'client_id'
        ]);

        $trainingModel = new Trainings($this->dbAdapter);
        // $user_model = new Users($this->dbAdapter);

        $reports = $trainingModel->getReportsByIds($request['trainer_id'], $request['client_id']);

        return $this->formatResponse(self::STATUS_SUCCESS, "", $reports);
    }

    /*
     * private function getReportsByIds(){
     *
     * $request = $this->filterParams(['trainer_id',
     * 'client_id'
     * ]);
     *
     *
     * $trainingModel = new Trainings($this->dbAdapter);
     * //$user_model = new Users($this->dbAdapter);
     *
     * $reports = $trainingModel->getReportsByIds($request['trainer_id'], $request['client_id']);
     *
     * return $this->formatResponse(self::STATUS_SUCCESS, "", $reports);
     * }
     */
    private function setReportPaid()
    {
        $request = $this->filterParams([
            'id',
            'trainer_id',
            'client_id'
        ]);

        $trainingModel = new Trainings($this->dbAdapter);
        $user_model = new Users($this->dbAdapter);

        $trainingModel->setTrainingPaid($request['id']);
        $price = $trainingModel->getPriceByTrainingId($request['id']);
        $price = $price[0]['price'];

        $user_model->addProfit($request['trainer_id'], $price);
        $user_model->removeDebt($request['client_id'], $price);
        $trainingModel->addProfitConnection($request['trainer_id'], $request['client_id'], $price);

        $reports = $trainingModel->getReportsByIds($request['trainer_id'], $request['client_id']);

        return $this->formatResponse(self::STATUS_SUCCESS, "", $reports);
    }

    private function updateProfile()
    {
        $request = $this->filterParams([
            'id',
            'name',
            'surname',
            'email',
            'phone',
            'deadline',
            'age',
            'city_id',
            'gender',
            'password',
            'en',
            'rs',
            'ru'
        ]);

        $users_model = new Users($this->dbAdapter);

        if ($request['password'] != "") {
            $newPassHash = password_hash($request['password'], PASSWORD_BCRYPT);
            $users_model->changePassword($request['id'], $newPassHash);
        }

        $users = $users_model->updateInfo($request['id'], $request['name'], $request['surname'], $request['age'], $request['phone'], $request['email'], $request['deadline'], $request['gender'], $request['city_id'], $request['en'], $request['rs'], $request['ru']);

        return $this->formatResponse(self::STATUS_SUCCESS, "", $users);
    }

    private function getConnectedUsersByTrainerId()
    {
        $request = $this->filterParams([
            'id'
        ]);

        $users_model = new Users($this->dbAdapter);
        $users = $users_model->getConnectedUsersByTrainerId($request['id']);

        array_walk($users, function (&$a) {
            if ($this->isFileExists(self::DIR_USERS, $a["id"])) {
                $a['image'] = $this->domain . "/images/users/" . $a["id"] . ".png?r=" . rand(0, 100000);
            } else {
                $a['image'] = $this->domain . "/images/users/logo.png";
            }
        });

        return $this->formatResponse(self::STATUS_SUCCESS, "", $users);
    }

    private function getConnectedUsersByClientId()
    {
        $request = $this->filterParams([
            'id'
        ]);

        $users_model = new Users($this->dbAdapter);
        $users = $users_model->getConnectedUsersByClientId($request['id']);

        array_walk($users, function (&$a) {
            if ($this->isFileExists(self::DIR_USERS, $a["id"])) {
                $a['image'] = $this->domain . "/images/users/" . $a["id"] . ".png?r=" . rand(0, 100000);
            } else {
                $a['image'] = $this->domain . "/images/users/logo.png";
            }
        });

        return $this->formatResponse(self::STATUS_SUCCESS, "", $users);
    }

    private function getRequestsTrainer()
    {
        $request = $this->filterParams([
            'id'
        ]);

        $users_model = new Users($this->dbAdapter);
        $users = $users_model->getRequestsTrainer($request['id']);

        array_walk($users, function (&$a) {
            if ($this->isFileExists(self::DIR_USERS, $a["id"])) {
                $a['image'] = $this->domain . "/images/users/" . $a["id"] . ".png?r=" . rand(0, 100000);
            } else {
                $a['image'] = $this->domain . "/images/users/logo.png";
            }
        });

        return $this->formatResponse(self::STATUS_SUCCESS, "", $users);
    }

    private function getGymsByCityId()
    {
        $request = $this->filterParams([
            'id'
        ]);

        $gyms_model = new Gyms($this->dbAdapter);
        $gyms = $gyms_model->getGymsByCityId($request['id']);

        return $this->formatResponse(self::STATUS_SUCCESS, "", $gyms);
    }

    private function getRequestsClient()
    {
        $request = $this->filterParams([
            'id'
        ]);

        $users_model = new Users($this->dbAdapter);
        $users = $users_model->getRequestsClient($request['id']);

        array_walk($users, function (&$a) {
            if ($this->isFileExists(self::DIR_USERS, $a["id"])) {
                $a['image'] = $this->domain . "/images/users/" . $a["id"] . ".png?r=" . rand(0, 100000);
            } else {
                $a['image'] = $this->domain . "/images/users/logo.png";
            }
        });

        return $this->formatResponse(self::STATUS_SUCCESS, "", $users);
    }

    private function sendRequestClient()
    {
        $request = $this->filterParams([
            'trainer_id',
            'client_id'
        ]);

        $users_model = new Users($this->dbAdapter);
        $users = $users_model->makeConnection($request['client_id'], $request['trainer_id']);

        // Send notification

        $trainer = $users_model->getUserById($request['trainer_id']);

        // $this->sendNotification("Novi zahtev", $trainer["first_name"] . " " . $trainer["last_name"], $trainer["device_token"]);

        return $this->formatResponse(self::STATUS_SUCCESS, "", $users);
    }

    private function acceptConnectionTrainer()
    {
        $request = $this->filterParams([
            'id'
        ]);

        $users_model = new Users($this->dbAdapter);
        $users = $users_model->acceptConnection($request['id']);

        // Send notification

        $client = $users_model->getClientByConnectionId($request['id']);

        // $this->sendNotification("Zahtev prihvaćen", $client["first_name"] . " " . $client["last_name"], $client["device_token"]);

        return $this->formatResponse(self::STATUS_SUCCESS, "", $users);
    }

    private function declineRequestTrainer()
    {
        $request = $this->filterParams([
            'id'
        ]);

        $users_model = new Users($this->dbAdapter);
        $users = $users_model->removeConnection($request['id']);

        return $this->formatResponse(self::STATUS_SUCCESS, "", $users);
    }

    private function searchMyClients()
    {
        $request = $this->filterParams([
            'id',
            'search_param'
        ]);

        $users_model = new Users($this->dbAdapter);
        $users = $users_model->searchConnectedUsers($request['id'], $request['search_param']);

        array_walk($users, function (&$a) {
            if ($this->isFileExists(self::DIR_USERS, $a["id"])) {
                $a['image'] = $this->domain . "/images/users/" . $a["id"] . ".png?r=" . rand(0, 100000);
            } else {
                $a['image'] = $this->domain . "/images/users/logo.png";
            }
        });

        return $this->formatResponse(self::STATUS_SUCCESS, "", $users);
    }

    private function searchMyTrainers()
    {
        $request = $this->filterParams([
            'id',
            'search_param'
        ]);

        $users_model = new Users($this->dbAdapter);
        $users = $users_model->searchConnectedTrainers($request['id'], $request['search_param']);

        array_walk($users, function (&$a) {
            if ($this->isFileExists(self::DIR_USERS, $a["id"])) {
                $a['image'] = $this->domain . "/images/users/" . $a["id"] . ".png?r=" . rand(0, 100000);
            } else {
                $a['image'] = $this->domain . "/images/users/logo.png";
            }
        });

        return $this->formatResponse(self::STATUS_SUCCESS, "", $users);
    }

    private function getAllTrainers()
    {
        $request = $this->filterParams([
            'is_male',
            'city_id'
        ]);

        $users_model = new Users($this->dbAdapter);
        $users = $users_model->getTrainers($request['is_male'], $request['city_id']);

        array_walk($users, function (&$a) {
            if ($this->isFileExists(self::DIR_USERS, $a["id"])) {
                $a['image'] = $this->domain . "/images/users/" . $a["id"] . ".png?r=" . rand(0, 100000);
            } else {
                $a['image'] = $this->domain . "/images/users/logo.png";
            }
        });

        return $this->formatResponse(self::STATUS_SUCCESS, "", $users);
    }

    private function changeConnectionPrice()
    {
        $request = $this->filterParams([
            'id',
            'price'
        ]);

        $users_model = new Users($this->dbAdapter);
        $users = $users_model->changeConnectionPrice($request['id'], $request['price']);

        return $this->formatResponse(self::STATUS_SUCCESS, "", $users);
    }

    private function removeConnection()
    {
        $request = $this->filterParams([
            'id'
        ]);

        $users_model = new Users($this->dbAdapter);
        $users = $users_model->removeConnection($request['id']);

        return $this->formatResponse(self::STATUS_SUCCESS, "", $users);
    }

    private function getCities()
    {
        $city_model = new Cities($this->dbAdapter);
        $cities = $city_model->getCities();

        return $this->formatResponse(self::STATUS_SUCCESS, "", $cities);
    }

    private function login()
    {
        $request = $this->filterParams([
            'email',
            'password'
        ]);

        $user_model = new Users($this->dbAdapter);
        $user = $user_model->getUserByEmail($request['email']);

        if (! $user) {
            return $this->formatResponse(self::STATUS_FAILED, "-1");
        }

        if (password_verify($request['password'], $user["password"])) {
            unset($user["password"]);
            $user["access_token"] = $this->getAccessToken($user);

            // $user->image = $this->getDefaultImage();
            return $this->formatResponse(self::STATUS_SUCCESS, "", $user);
        }
        return $this->formatResponse(self::STATUS_FAILED, "-1");
    }

    private function forgotPassword()
    {
        $request = $this->filterParams([
            'email'
        ]);
        // $request = $this->filterParams(['email']);

        $generated_pass = crc32(time());

        $password_hash = password_hash($generated_pass, PASSWORD_BCRYPT);

        $userModel = new Users($this->dbAdapter);
        $user = (array) $userModel->getUserByEmail($request["email"]);

        if (! isset($user['id'])) {
            throw new ApiException("There is no such user");
        }

        $userModel->forgotPassword($user['id'], $password_hash);

        $subject = 'Zahtev za promenu lozinke';
        $body = "Dobili smo zahtev da ste zaboravili lozinku. Vašа nova lozinka je: {$generated_pass}. Lozinku kasnije možete promeniti.";

        mail($request['email'], $subject, $body);

        return $this->formatResponse(self::STATUS_SUCCESS, "", []);
    }

    private function changePassword()
    {
        $request = $this->filterParams([
            'id',
            'old_password',
            'new_password'
        ]);
        // $request = $this->filterParams(['email']);

        $userModel = new Users($this->dbAdapter);
        $user = $userModel->getUserById($request["id"]);

        if ($user) {
            if (sha1($request['old_password']) == $user['password']) {
                $newPassHash = sha1($request['new_password']);
                $user = $userModel->changePassword($user["id"], $newPassHash);
                return $this->formatResponse(self::STATUS_SUCCESS, "", []);
            } else {
                // failure
                return $this->formatResponse(self::STATUS_FAILED, "Stara lozinka je neispravna.", []);
            }
        }
    }

    private function initPayment()
    {

        /*
         * $request = $this->filterParams([
         * 'amount'
         * ]);
         */
        $merchant_key = "TREESRS";
        $authenticity_token = "";

        $data = [
            "amount" => 100,
            // unique order identifier
            "order_number" => 'random' . time(),
            "currency" => "EUR",
            "transaction_type" => "purchase",
            "order_info" => "Create payment session order info",
            "scenario" => 'charge'
        ];

        $body_as_string = json_encode($data);
        $base_url = 'https://ipgtest.monri.com';
        $ch = curl_init($base_url . '/v2/payment/new');
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body_as_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

        $timestamp = time();
        $digest = hash('sha512', $merchant_key . $timestamp . $authenticity_token . $body_as_string);
        $authorization = "WP3-v2 $authenticity_token $timestamp $digest";

        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($body_as_string),
            'Authorization: ' . $authorization
        ));

        $result = curl_exec($ch);

        if (curl_errno($ch)) {
            curl_close($ch);
            $response = [
                'client_secret' => null,
                'status' => 'declined',
                'error' => curl_error($ch)
            ];
        } else {
            curl_close($ch);
            $response = [
                'status' => 'approved',
                'client_secret' => json_decode($result, true)['client_secret']
            ];
        }

        return $this->formatResponse(self::STATUS_SUCCESS, "", $response);
    }

    private function saveImageReport(String $base64_string, String $file_name, String $dir, String $report_id)
    {
        if (! $base64_string) {
            throw new \Exception("base64_string is empty");
        }

        $base64_string = $this->base64UrlDecode(preg_replace('#^data:image/\w+;base64,#i', '', $base64_string));

        /*
         * $upload_dir = "/home/ekozasti/public_html/app.ekozastita.com/public/uploads/reports/";
         * $upload_path = $upload_dir.$file_name.".png";
         * $url= "uploads/reports/".$file_name.png;
         */

        $upload_dir = self::DIR_UPLOADS . $dir . '/';
        $upload_path = $upload_dir . $file_name . ".png";
        $url = "images/" . $dir . "/" . $file_name . ".png";

        // Create dir if not exists
        if (! is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        file_put_contents($upload_path, $base64_string);

        $appReportModel = new AppReports($this->dbAdapter);
        $appReportModel->saveImage($file_name, $url, $report_id);

        return $this->formatResponse(self::STATUS_SUCCESS, "", []);
    }

    private function saveImages()
    {
        $request = $this->filterParams([
            'report_id',
            'images'
        ]);

        $images_array = json_decode($request['images'], true);
        $names_array = array();

        for ($i = 0; $i < sizeof($images_array); $i ++) {
            $file_name = $request['report_id'] . $i;

            /*
             * var_dump($file_name);
             * var_dump($request["report_id"]);
             */

            $this->saveImageReport($images_array[$i], $file_name, "reports", $request['report_id']);

            array_push($names_array, $file_name);
        }

        return $this->formatResponse(self::STATUS_SUCCESS, "", []);
    }

    private function saveImage()
    {
        $request = $this->filterParams([
            'base_64',
            'user_id'
        ]);

        $base64_string = $request['base_64'];
        $file_name = $request['user_id'];

        if (! $base64_string) {
            throw new \Exception("base64_string is empty");
        }

        $base64_string = $this->base64UrlDecode(preg_replace('#^data:image/\w+;base64,#i', '', $base64_string));

        $upload_dir = self::DIR_UPLOADS . 'users/';
        $upload_path = $upload_dir . $file_name . ".png";

        /*
         * var_dump($upload_dir);
         * die(var_dump($upload_path));
         */

        // Create dir if not exists
        if (! is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        file_put_contents($upload_path, $base64_string);

        return $this->formatResponse(self::STATUS_SUCCESS, "", []);
    }

    protected static function base64UrlDecode($input)
    {
        return base64_decode(strtr($input, ' ', '+'));
    }

    private function signup($username, $password)
    {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        save($username, $hash);
    }

    private function setDeviceToken()
    {
        $request = $this->filterParams([
            'device_token',
            'user_id'
        ]);

        $model = new Users($this->dbAdapter);
        $user = $model->getUserById($request['user_id']);

        // Delete device token from old accounts
        $old_users = $model->getByDeviceToken($request['device_token']);
        foreach ($old_users as $one) {
            if ($one["id"] != $user["id"]) {
                $one["device_token"] = "";
                $model->setDeviceToken($one['id'], $one['device_token']);
            }
        }

        $model->setDeviceToken($request['user_id'], $request['device_token']);
        return $this->formatResponse(self::STATUS_SUCCESS, $this->returnUser($user));
    }

    /*
     * private function test(){
     * $request = $this->filterParams([
     * 'device_token',
     * 'title',
     * 'body'
     * ]);
     *
     *
     * $this->sendNotification($request['title'], $request['body'], $request['device_token']);
     *
     * return $this->formatResponse(self::STATUS_SUCCESS, []);
     * }
     */
    private function sendNotification(string $title, string $body, string $device_token)
    {
        $client = new Client(__DIR__ . '/personalni-trener-440e6-firebase-adminsdk-vjod3-61b9d09dcc.json');
        // personalni-trener-440e6-firebase-adminsdk-vjod3-61b9d09dcc.json
        $recipient = new Recipient();
        $notification = new Notification();

        $recipient->setSingleRecipient($device_token);
        $notification->setNotification($title, $body);
        $client->build($recipient, $notification);
        $client->fire();
    }

    /*
     * private function sendAndroidPush($user, $msg, $request = null)
     * {
     * // $firebase_api = "MIIEvAIBADANBgkqhkiG9w0BAQEFAASCBKYwggSiAgEAAoIBAQCr+k+dh3qhlEu4\nkOYyaCzJw/q+tWa+DXyv8iCfwXX0O8r7eQs65q99RoFkUIdibWWJuQyOD0WM+Z1g\nzscKzGNUbT6MW8/baY7Ho5qsFpYj2B7MsjE7t56/lhOFXLN9s+z+GDVNm5n+jDpX\n3Jf/ZSDV7GXfV0+LM6niXmLyaSq+8nUC0ATweqQxWOawhsD4PpN5vTTTSuHUr9B9\n9Divrp7YjnLeIpvXTa1pNST6QN4HsOvN6DRXJ/CHDB5CsTLcAEFp3mPYAUXTvjwF\nKc4WV2VH4n7fgWlgLSjBkM0CN3p/dGDw0O4Gr4PXI9FBZaUbFdzl+vJuZRYnuHRM\ngX+bdxxfAgMBAAECggEAPzMZlvZ2pnJs8UKulc+axfrzZqobO7SRIceFHbBmvH5M\nteVhrx+fVhZW7pp5Zj51lgsfBgWutYP1xeG3W8yIpDoRRltnI2bDRbJl4N/cBQgj\nKW5CkYOFHzrzqYrLm2JHsYkL7Z1FFjpKJoe2g/CSBIt/VHgtjyZJRBsTman0P38W\nkmtkpEHUxPw2tl0Plvj6YcgZGAx03SulEqXpl8F14132OVLPjHCSbQtTSLa9ofCO\nfRPxQNNgvG9e26Qz+zMqmRkfvDyjmZX2PjiQCkJL2HS6xSZuqNG2EXuPexOpSso6\neRBbUvOJqlJdymVWC97ysSySNSwRRE2cps6C9ZJaYQKBgQDyhzw3Cdc10CnzOclQ\nfuofr7EyHiEQ3kCAjoDDXDt2remP6oVfOm4DludkpRZ8uG3r78cENt0q2a0XlmX9\nmKsJqro9UK44c1r/11gvXKCtkIBgsu8JBjNKmxzY8sQJy/wUulvIuElHEstKOl+K\nl3B1rhcyrp3SwSJG5xAJsXGHzQKBgQC1h9gZE4LPHs1191vh9Chukp6qptF/Odzb\nXpp8tNNmXITlbN03hMPIoTgw9klkqUqQJ+owCG7PixnFoZbZ8OZax06+mbsNevy5\n8eGgDleKUg6e+7S7mk0276FPRpAtqrdheqsuijvs8PJbGTAxCS8iQMihE0Jeewy0\n+zMyGOew2wKBgEGoFscXpOlul0y/Yh6mnR9C4wecXHtCj9e9vGIm//obDtXlOYIU\nQWA7ZB09DB9rlmZ/HTOo3qsRDukJ0EadJACT9aNPyjqCECqe08LOorkaG4cSKeAZ\ni50w7NhWsHeuf2nlIZ/vM/dHwT0xHFhasxlIrgMMfrFlk6/6Mb9OCFwpAoGAOgKK\nNSE+CRAv2kXMz/0lOoiSObiLdBu6j0PBHJ7we2KPeX17h/VeV2vluAfMVmWfFUgc\nF3Nqbdpmxvgna8gG5PWSHWilFN67inRYhLxwjxw/3eBT1iuuByM2qk3DX0SRy43W\nzE2SbtpkqGX5N4JW1JxdQNQVvnmWCvPHXXgF+kcCgYBFkdGkutIT2tRepIxA7uex\nNzU2KFYUopRAJ2ZY1iS2lIpb8xuTtvitAgw2AfcXkcCR2K9rBA+C8Mn14qMeOfVN\niQCmsY5+RzzQ8W8ZFAFRt2FdBr/Vp4utDjd/f+C1TuC2RgEy9/mjwcoZU9/EnH5X\n5q2KjdFp+S9Xku8O0Do8WA==";
     * // $firebase_api = "AIzaSyCTTc0zYQN2noVWca82czK_iQ-nVgDQnW4";
     * //$firebase_api = "AAAAaZzQSpw:APA91bENhf5YnYv09dPCwFQJKhgBE6auTVCHYKX6PPr-bcB0iK-srtHV199yAdkYWAPZJN3xJg6nATeTY1YYoY1vq8XjCwRD5wcsgT0zwhFwbh9eUiJc4yUNIzOk0yBZ_yhayNFJ5Hzr";
     * $notification = array();
     * $notification['title'] = "";
     * $notification['message'] = $msg;
     * $notification['image'] = "ddd";
     * $notification['badge'] = "1";
     *
     * $fields = array(
     * 'to' => $this->getDeviceToken($user),
     * 'data' => $notification
     * );
     *
     * // Set POST variables
     * $url = 'https://fcm.googleapis.com/fcm/send';
     *
     * $headers = array(
     * 'Authorization: key=' . $firebase_api,
     * 'Content-Type: application/json'
     * );
     *
     * // Open connection
     * $ch = curl_init();
     *
     * // Set the url, number of POST vars, POST data
     * curl_setopt($ch, CURLOPT_URL, $url);
     *
     * curl_setopt($ch, CURLOPT_POST, true);
     * curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
     * curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
     *
     * // Disabling SSL Certificate support temporarily
     * curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
     *
     * curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
     *
     * $result = curl_exec($ch);
     * if ($result === FALSE) {
     * throw new \Exception("Curl failed: " . curl_error($ch));
     * }
     *
     * $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
     *
     * // var_dump($result);
     * // mail("arsen.leontijevic@gmail.com", "and nots", $result);
     *
     * // die(var_dump($fields));
     *
     * return $result;
     * }
     */

    /**
     *
     * @return array
     */
    private function getDeviceToken($user)
    {
        return substr($user['device_token'], 4);
    }

    private function returnUser($userRow)
    {
        unset($userRow['password']);
        $user = (object) $userRow;
        $user->access_token = $this->getAccessToken($userRow);
        return $user;
    }
}

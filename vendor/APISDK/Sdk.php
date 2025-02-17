<?php
namespace APISDK;

use APISDK\ApiException;
use Firebase\JWT\JWT;
use Exception;
use APISDK\Models\Users;
use APISDK\Models\Trainings;
use APISDK\Models\Cities;
use APISDK\Models\Measurements;
use APISDK\Models\Gyms;
use phpFCMv1\Client;
use phpFCMv1\Notification;
use phpFCMv1\Recipient;
use Exchange\Client\Client as ExchangeClient;
use Exchange\Client\Data\Customer;
use Exchange\Client\Transaction\Debit;
use Exchange\Client\Transaction\Result;
use Exchange\Client\StatusApi\StatusRequestData;
use Exchange\Client\Callback\Result as CallbackResult;
use APISDK\Models\Invoices;
use APISDK\Models\Countries;
use PHPMailer\PHPMailer\PHPMailer;

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
            'initPayment',
            'register',
            'getCities',
            'getCitiesByCountryId',
            'getCountries',
            'getGymsByCityId',
            'addFitnessCenter',
            'removeFitnessCenter',
            'saveServicesTrainer',
            'removeInactive',
            'callback',
            'cronSubCheck',
            'testMail',
            'testInvoices'
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

        array_walk($trainings, function (&$a) {
            if ($this->isFileExists(self::DIR_USERS, $a["client_id"])) {
                $a['image'] = $this->domain . "/images/users/" . $a["client_id"] . ".png?r=" . rand(0, 100000);
            } else {
                $a['image'] = $this->domain . "/images/users/logo.png";
            }
        });

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

        array_walk($trainings, function (&$a) {
            if ($this->isFileExists(self::DIR_USERS, $a["client_id"])) {
                $a['image'] = $this->domain . "/images/users/" . $a["client_id"] . ".png?r=" . rand(0, 100000);
            } else {
                $a['image'] = $this->domain . "/images/users/logo.png";
            }
        });

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

        array_walk($trainings, function (&$a) {
            if ($this->isFileExists(self::DIR_USERS, $a["trainer_id"])) {
                $a['image'] = $this->domain . "/images/users/" . $a["trainer_id"] . ".png?r=" . rand(0, 100000);
            } else {
                $a['image'] = $this->domain . "/images/users/logo.png";
            }
        });

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
    
    private function insertRepeatedTraining()
    {
        $request = $this->filterParams([
            'trainer_id',
            'gym_id',
            'date',
            'time',
            'is_group',
            'training_plan'
        ],['mon',
            'tue',
            'wed',
            'thu',
            'fri',
            'sat',
            'sun',
            'end_date']);
        
        $start_date = new \DateTimeImmutable($request['start_date']);
        $end_date = new \DateTimeImmutable($request['end_date']);
        $training_model = new Trainings($this->dbAdapter);
        
        $trainings = [];
        
        $i = 0;
        do {
            if ($start_date->format('N') == 1 && $request['mon'] == "1") {
                $trainings = array_merge($trainings, $training_model->insertTraining($request['trainer_id'], $request['gym_id'], $request['is_group'], $start_date->format('Y-m-d'), $request['time'], $request['training_plan']));
            }
            if ($start_date->format('N') == 2 && $request['tue'] == "1") {
                $trainings = array_merge($trainings, $training_model->insertTraining($request['trainer_id'], $request['gym_id'], $request['is_group'], $start_date->format('Y-m-d'), $request['time'], $request['training_plan']));
            }
            if ($start_date->format('N') == 3 && $request['wed'] == "1") {
                $trainings = array_merge($trainings, $training_model->insertTraining($request['trainer_id'], $request['gym_id'], $request['is_group'], $start_date->format('Y-m-d'), $request['time'], $request['training_plan']));
            }
            if ($start_date->format('N') == 4 && $request['thu'] == "1") {
                $trainings = array_merge($trainings, $training_model->insertTraining($request['trainer_id'], $request['gym_id'], $request['is_group'], $start_date->format('Y-m-d'), $request['time'], $request['training_plan']));
            }
            if ($start_date->format('N') == 5 && $request['fri'] == "1") {
                $trainings = array_merge($trainings, $training_model->insertTraining($request['trainer_id'], $request['gym_id'], $request['is_group'], $start_date->format('Y-m-d'), $request['time'], $request['training_plan']));
            }
            if ($start_date->format('N') == 6 && $request['sat'] == "1") {
                $trainings = array_merge($trainings, $training_model->insertTraining($request['trainer_id'], $request['gym_id'], $request['is_group'], $start_date->format('Y-m-d'), $request['time'], $request['training_plan']));
            }
            if ($start_date->format('N') == 7 && $request['sun'] == "1") {
                $trainings = array_merge($trainings, $training_model->insertTraining($request['trainer_id'], $request['gym_id'], $request['is_group'], $start_date->format('Y-m-d'), $request['time'], $request['training_plan']));
            }
            
            $i ++;
            $start_date = $start_date->modify('+1 day');
        } while ($end_date != $start_date);
        
        return $this->formatResponse(self::STATUS_SUCCESS, "", $trainings);
    }

    private function insertTraining()
    {
        $request = $this->filterParams([
            'trainer_id',
            'gym_id',
            'date',
            'time',
            'is_group',
            'training_plan',
            'repeated',
            'clients'
        ],['mon',
            'tue',
            'wed',
            'thu',
            'fri',
            'sat',
            'sun',
            'end_date']);

        if($request['repeated']=='0'){
            $training_model = new Trainings($this->dbAdapter);
            $trainings = $training_model->insertTraining($request['trainer_id'], $request['gym_id'], $request['is_group'], $request['date'], $request['time'], $request['training_plan']);
            
            foreach ($request['clients'] as $one){
                $user_model = new Users($this->dbAdapter);
                $price = $user_model->getConnectionPriceByIds($request['trainer_id'], $one);
                $this->addClientToTraining($trainings[0]['id'], $one, $price, $request['trainer_id']);
            }
        }else if($request['repeated'=='1']){
            $start_date = new \DateTimeImmutable($request['start_date']);
            $end_date = new \DateTimeImmutable($request['end_date']);
            $training_model = new Trainings($this->dbAdapter);
            
            $trainings = [];
            
            $i = 0;
            do {
                if ($start_date->format('N') == 1 && $request['mon'] == "1") {
                    $trainings = array_merge($trainings, $training_model->insertTraining($request['trainer_id'], $request['gym_id'], $request['is_group'], $start_date->format('Y-m-d'), $request['time'], $request['training_plan']));
                }
                if ($start_date->format('N') == 2 && $request['tue'] == "1") {
                    $trainings = array_merge($trainings, $training_model->insertTraining($request['trainer_id'], $request['gym_id'], $request['is_group'], $start_date->format('Y-m-d'), $request['time'], $request['training_plan']));
                }
                if ($start_date->format('N') == 3 && $request['wed'] == "1") {
                    $trainings = array_merge($trainings, $training_model->insertTraining($request['trainer_id'], $request['gym_id'], $request['is_group'], $start_date->format('Y-m-d'), $request['time'], $request['training_plan']));
                }
                if ($start_date->format('N') == 4 && $request['thu'] == "1") {
                    $trainings = array_merge($trainings, $training_model->insertTraining($request['trainer_id'], $request['gym_id'], $request['is_group'], $start_date->format('Y-m-d'), $request['time'], $request['training_plan']));
                }
                if ($start_date->format('N') == 5 && $request['fri'] == "1") {
                    $trainings = array_merge($trainings, $training_model->insertTraining($request['trainer_id'], $request['gym_id'], $request['is_group'], $start_date->format('Y-m-d'), $request['time'], $request['training_plan']));
                }
                if ($start_date->format('N') == 6 && $request['sat'] == "1") {
                    $trainings = array_merge($trainings, $training_model->insertTraining($request['trainer_id'], $request['gym_id'], $request['is_group'], $start_date->format('Y-m-d'), $request['time'], $request['training_plan']));
                }
                if ($start_date->format('N') == 7 && $request['sun'] == "1") {
                    $trainings = array_merge($trainings, $training_model->insertTraining($request['trainer_id'], $request['gym_id'], $request['is_group'], $start_date->format('Y-m-d'), $request['time'], $request['training_plan']));
                }
                
                $i ++;
                $start_date = $start_date->modify('+1 day');
                
                
            } while ($end_date != $start_date);
            foreach ($trainings['id'] as $training_id) {
                foreach ($request['clients'] as $client_id){
                    $user_model = new Users($this->dbAdapter);
                    $price = $user_model->getConnectionPriceByIds($request['trainer_id'], $one);
                    $this->addClientToTraining($training_id, $client_id, $price, $request['trainer_id']);
                }
            }
        }

        return $this->formatResponse(self::STATUS_SUCCESS, "", $trainings);
    }

    private function addClientToTraining(string $training_id, string $client_id, string $price, string $trainer_id)
    {
        /* $request = $this->filterParams([
            'training_id',
            'client_id',
            'price',
            'trainer_id'
        ]); */

        $training_model = new Trainings($this->dbAdapter);
        $trainings = $training_model->insertClientToTraining($training_id, $client_id, $price);
        // $training_model->addDebtConnection($request['trainer_id'], $request['client_id'], $request['price']);

        $training_info = $training_model->getTrainingById($training_id);

        $user_model = new Users($this->dbAdapter);
        $client = $user_model->getUserById($client_id);
        $trainer = $user_model->getUserById($trainer_id);

        $date = $training_info[0]['date'];
        $date = date('d.m.Y', strtotime($date));

        $time = $training_info[0]['time'];
        $time = date('H:i', strtotime($time));

        $dataPayload = [
            'type' => 'new_training',
            'date' => $date,
            'time' => $time,
            'user' => $trainer['first_name'] . " " . $trainer['last_name']
        ];

        $this->sendNotification($trainer['first_name'] . " je zakazao novi trening.", $date . " u " . $time, $client["device_token"], $dataPayload);

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

        $date = $training_info[0]['date'];
        $date = date('d.m.Y', strtotime($date));

        $time = $training_info[0]['time'];
        $time = date('H:i', strtotime($time));

        $dataPayload = [
            'type' => 'training_canceled_trainer',
            'date' => $date,
            'time' => $time,
            'user' => $trainer['first_name'] . " " . $trainer['last_name']
        ];

        foreach ($clients as $one) {
            $this->sendNotification($trainer['first_name'] . " je otkazao trening.", "Trening je bio zakazan za " . $date . " u " . $time, $one["device_token"], $dataPayload);
        }

        foreach ($params as $one) {
            // $training_model->removeDebtConnection($request['trainer_id'], $one['client_id'], $one['price']);
        }

        return $this->formatResponse(self::STATUS_SUCCESS, "", $trainings);
    }

    private function setTrainingsFinished()
    {
        $training_model = new Trainings($this->dbAdapter);
        $training_model->setTrainingsFinished();

        return $this->formatResponse(self::STATUS_SUCCESS, "", "[]");
    }

    private function removeInactive()
    {
        $user_model = new Users($this->dbAdapter);
        $user_model->removeInactive();

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

        $date = $training_info[0]['date'];
        $date = date('d.m.Y', strtotime($date));

        $time = $training_info[0]['time'];
        $time = date('H:i', strtotime($time));

        $dataPayload = [
            'type' => 'training_canceled_client',
            'date' => $date,
            'time' => $time,
            'user' => $client['first_name'] . " " . $client['last_name']
        ];

        $this->sendNotification($client['first_name'] . " je otkazao trening.", "Trening je bio zakazan za " . $date . " u " . $time, $trainer["device_token"], $dataPayload);

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
        if ($users['is_trainer'] == '1') {
            $users['profit'] = $users_model->getProfitProfileTrainer($request['id']);
        } else {
            $users['debt'] = $users_model->getDebtProfileClient($request['id']);
        }

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

        $measurements = $mes_model->getMeasurementsByIds($request['trainer_id'], $request['client_id']);

        return $this->formatResponse(self::STATUS_SUCCESS, "", $measurements);
    }

    private function getMeasurementsByClientId()
    {
        $request = $this->filterParams([
            'client_id'
        ]);

        $mes_model = new Measurements($this->dbAdapter);

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

        $reports = $trainingModel->getReportsByIds($request['trainer_id'], $request['client_id']);

        return $this->formatResponse(self::STATUS_SUCCESS, "", $reports);
    }

    private function getReportsByIdsAndDate()
    {
        $request = $this->filterParams([
            'trainer_id',
            'client_id',
            'date_string'
        ]);

        $trainingModel = new Trainings($this->dbAdapter);
        // $user_model = new Users($this->dbAdapter);

        $reports = $trainingModel->getReportsByIdsAndDate($request['trainer_id'], $request['client_id'], $request['date_string']);

        return $this->formatResponse(self::STATUS_SUCCESS, "", $reports);
    }

    private function getReportsByTrainerId()
    {
        $request = $this->filterParams([
            'trainer_id'
        ]);

        $trainingModel = new Trainings($this->dbAdapter);
        // $user_model = new Users($this->dbAdapter);

        $reports = $trainingModel->getReportsByTrainerId($request['trainer_id']);

        return $this->formatResponse(self::STATUS_SUCCESS, "", $reports);
    }

    private function getReportsByClientId()
    {
        $request = $this->filterParams([
            'client_id'
        ]);

        $trainingModel = new Trainings($this->dbAdapter);
        // $user_model = new Users($this->dbAdapter);

        $reports = $trainingModel->getReportsByClientId($request['client_id']);

        return $this->formatResponse(self::STATUS_SUCCESS, "", $reports);
    }

    private function setReportPaid()
    {
        $request = $this->filterParams([
            'id',
            'trainer_id',
            'client_id',
            'date_string'
        ]);

        $trainingModel = new Trainings($this->dbAdapter);
        $user_model = new Users($this->dbAdapter);

        $trainingModel->setTrainingPaid($request['id']);

        $reports = $trainingModel->getReportsByIdsAndDate($request['trainer_id'], $request['client_id'], $request['date_string']);

        foreach ($reports as &$one) {
            $price = $trainingModel->getPriceByTrainingId($request['id']);
            $price = $price[0]['price'];
            $one['price'] = $price;
        }

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
            'ru',
            'country_id'
        ]);

        $users_model = new Users($this->dbAdapter);

        if ($request['password'] != "") {
            $newPassHash = password_hash($request['password'], PASSWORD_BCRYPT);
            $users_model->changePassword($request['id'], $newPassHash);
        }

        $users = $users_model->updateInfo($request['id'], $request['name'], $request['surname'], $request['age'], $request['phone'], $request['email'], $request['deadline'], $request['gender'], $request['city_id'], $request['en'], $request['rs'], $request['ru'], $request['country_id']);

        return $this->formatResponse(self::STATUS_SUCCESS, "", $users);
    }

    private function getConnectedUsersByTrainerId()
    {
        $request = $this->filterParams([
            'id'
        ]);

        $users_model = new Users($this->dbAdapter);
        $training_model = new Trainings($this->dbAdapter);
        $users = $users_model->getConnectedUsersByTrainerId($request['id']);

        array_walk($users, function (&$a) {
            if ($this->isFileExists(self::DIR_USERS, $a["id"])) {
                $a['image'] = $this->domain . "/images/users/" . $a["id"] . ".png?r=" . rand(0, 100000);
            } else {
                $a['image'] = $this->domain . "/images/users/logo.png";
            }
        });

        foreach ($users as &$one) {
            $one['total_trainings_client'] = $training_model->getTrainingsClientTrainer($request['id'], $one['id']);
            $one['profit'] = $users_model->getProfitConnection($request['id'], $one['id']);
            $one['debt'] = $users_model->getDebtConnection($request['id'], $one['id']);
        }

        return $this->formatResponse(self::STATUS_SUCCESS, "", $users);
    }

    private function getConnectedUsersByClientId()
    {
        $request = $this->filterParams([
            'id'
        ]);

        $users_model = new Users($this->dbAdapter);
        $training_model = new Trainings($this->dbAdapter);
        $users = $users_model->getConnectedUsersByClientId($request['id']);

        array_walk($users, function (&$a) {
            if ($this->isFileExists(self::DIR_USERS, $a["id"])) {
                $a['image'] = $this->domain . "/images/users/" . $a["id"] . ".png?r=" . rand(0, 100000);
            } else {
                $a['image'] = $this->domain . "/images/users/logo.png";
            }
        });

        foreach ($users as &$one) {
            $one['total_trainings_client'] = $training_model->getTrainingsClientTrainer($one['id'], $request['id']);
            $one['profit'] = $users_model->getProfitConnection($one['id'], $request['id']);
            $one['debt'] = $users_model->getDebtConnection($one['id'], $request['id']);
        }

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

    private function getMonthEventsTrainer()
    {
        $request = $this->filterParams([
            'yearmonth_string',
            'trainer_id'
        ]);

        $training_model = new Trainings($this->dbAdapter);
        $dates = $training_model->getTrainingDatesMonthlyTrainer($request['yearmonth_string'], $request['trainer_id']);

        return $this->formatResponse(self::STATUS_SUCCESS, "", $dates);
    }

    private function getMonthEventsClient()
    {
        $request = $this->filterParams([
            'yearmonth_string',
            'client_id'
        ]);

        $training_model = new Trainings($this->dbAdapter);
        $dates = $training_model->getTrainingDatesMonthlyClient($request['yearmonth_string'], $request['client_id']);

        return $this->formatResponse(self::STATUS_SUCCESS, "", $dates);
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

        $client = $users_model->getUserById($request['client_id']);

        $dataPayload = [
            'type' => 'new_request',
            'date' => "",
            'time' => "",
            'user' => $client['first_name'] . " " . $client['last_name']
        ];

        $this->sendNotification("Novi zahtev", $client["first_name"] . " " . $client["last_name"], $trainer["device_token"], $dataPayload);

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
        $trainer = $users_model->getTrainerByConnectionId($request['id']);

        $dataPayload = [
            'type' => 'accepted_request',
            'date' => "",
            'time' => "",
            'user' => $trainer['first_name'] . " " . $trainer['last_name']
        ];

        $this->sendNotification("Zahtev prihvaćen", $client["first_name"] . " " . $client["last_name"], $client["device_token"], $dataPayload);

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

    private function register()
    {
        $request = $this->filterParams([
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
            'ru',
            'is_trainer',
            'nationality',
            'country_id'
        ]);
        
        
        
        $users_model = new Users($this->dbAdapter);

        $user = $users_model->getUserByEmail($request['email']);
       

        if ($user) {
            return $this->formatResponse(self::STATUS_FAILED, "-1");
        }

        $password = password_hash($request['password'], PASSWORD_BCRYPT);
        
        $hash = md5(time());

        $users = $users_model->register($request['name'], $request['surname'], $request['age'], $request['phone'], $password, $request['email'], $request['deadline'], $request['gender'], $request['city_id'], $request['en'], $request['rs'], $request['ru'], $request['is_trainer'], $request['country_id'], $request['nationality'], $hash);
        
        $mail = new PHPMailer();
        // configure an SMTP
        $mail->isSMTP();
        $mail->Host = 'sandbox.smtp.mailtrap.io';
        $mail->SMTPAuth = true;
        $mail->Username = 'ff1891b36df9cb';
        $mail->Password = 'e1241525bc4bbb';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 2525;
        
        $mail->setFrom('confirmation@trener.com', 'Test');
        $mail->addAddress('nikola.bojovic9@gmail.com');
        $mail->addCC('arsen.leontijevic@gmail.com');
        $mail->Subject = 'Potvrda naloga';
        // Set HTML
        $mail->isHTML(TRUE);
        $mail->Body = '<html>Link za potvrdu naloga: https://phpstack-1301327-4732761.cloudwaysapps.com/log/activate/'.$hash.'</html>';
        $mail->AltBody = '<html>Alt Body</html>';
        
        $mail->send();
        
        return $this->formatResponse(self::STATUS_SUCCESS, "", $this->returnUser($users[0]));
    }

    private function searchMyClients()
    {
        $request = $this->filterParams([
            'id',
            'search_param'
        ]);

        $users_model = new Users($this->dbAdapter);
        $training_model = new Trainings($this->dbAdapter);
        $users = $users_model->searchConnectedUsers($request['id'], $request['search_param']);

        array_walk($users, function (&$a) {
            if ($this->isFileExists(self::DIR_USERS, $a["id"])) {
                $a['image'] = $this->domain . "/images/users/" . $a["id"] . ".png?r=" . rand(0, 100000);
            } else {
                $a['image'] = $this->domain . "/images/users/logo.png";
            }
        });

        foreach ($users as &$one) {
            $one['total_trainings_client'] = $training_model->getTrainingsClientTrainer($request['id'], $one['id']);
            $one['profit'] = $users_model->getProfitConnection($request['id'], $one['id']);
            $one['debt'] = $users_model->getDebtConnection($request['id'], $one['id']);
        }

        return $this->formatResponse(self::STATUS_SUCCESS, "", $users);
    }

    private function searchMyTrainers()
    {
        $request = $this->filterParams([
            'id',
            'search_param'
        ]);

        $users_model = new Users($this->dbAdapter);
        $training_model = new Trainings($this->dbAdapter);
        $users = $users_model->searchConnectedTrainers($request['id'], $request['search_param']);

        array_walk($users, function (&$a) {
            if ($this->isFileExists(self::DIR_USERS, $a["id"])) {
                $a['image'] = $this->domain . "/images/users/" . $a["id"] . ".png?r=" . rand(0, 100000);
            } else {
                $a['image'] = $this->domain . "/images/users/logo.png";
            }
        });

        foreach ($users as &$one) {
            $one['total_trainings_client'] = $training_model->getTrainingsClientTrainer($one['id'], $request['id']);
            $one['profit'] = $users_model->getProfitConnection($request['id'], $one['id']);
            $one['debt'] = $users_model->getDebtConnection($request['id'], $one['id']);
        }

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

    private function getCountries()
    {
        $country_model = new Countries($this->dbAdapter);
        $countries = $country_model->getCountries();

        return $this->formatResponse(self::STATUS_SUCCESS, "", $countries);
    }

    private function getCities()
    {
        $city_model = new Cities($this->dbAdapter);
        $cities = $city_model->getCities();

        return $this->formatResponse(self::STATUS_SUCCESS, "", $cities);
    }

    private function getCitiesByCountryId()
    {
        $request = $this->filterParams([
            'id'
        ]);
        $city_model = new Cities($this->dbAdapter);
        $cities = $city_model->getCitiesByCountryId($request['id']);

        return $this->formatResponse(self::STATUS_SUCCESS, "", $cities);
    }

    private function getInvoiceItems()
    {
        $invoice_model = new Invoices($this->dbAdapter);
        $invoice_items = $invoice_model->getInvoiceItems();

        return $this->formatResponse(self::STATUS_SUCCESS, "", $invoice_items);
    }

    private function getInvoicesByTrainerId()
    {
        $id = $this->user_id;

        $invoice_model = new Invoices($this->dbAdapter);
        $invoices = $invoice_model->getByTrainerId($id);

        return $this->formatResponse(self::STATUS_SUCCESS, "", $invoices);
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

    private function changeSubType()
    {
        $request = $this->filterParams([
            'id',
            'is_monthly'
        ]);

        $user_model = new Users($this->dbAdapter);

        $user_model->changeSub($request['id'], $request['is_monthly']);

        return $this->formatResponse(self::STATUS_SUCCESS, "", []);
    }

    private function cronSubCheck()
    {
        $user_model = new Users($this->dbAdapter);

        $user_model->checkIfSubPassed();

        return $this->formatResponse(self::STATUS_SUCCESS, "", []);
    }

    private function initPayment()
    {
        $request = $this->filterParams([
            'id',
            'token',
            'name',
            'surname',
            'email',
            'is_monthly'
        ]);

        $api_user = "personal-api";
        $api_password = "fvQoizXF7R.@LU#sCUzOj%$=Nm3+a";
        $connector_api_key = "personal-simulator";
        $connector_shared_secret = "9VkcsOb0snZRUAxiBeN0KaxPFFqPRb";
        $client = new ExchangeClient($api_user, $api_password, $connector_api_key, $connector_shared_secret);
        /*
         * $token = "IEta5qtej1cxZ1tBgKIotb+Owt+/yotP3COmU9ZCzAJpBeTqENIaNHyel2Uh4yCZQlFoOzOVLrhtYVvF10V31ge
         * EUSvqH3T70xvJCGF6XNBGnTr8t2UP9nv48gl1Mh7//86m8gNJEbtLIJvM99PsJv+aIF0jdOjekC6InyxthWd9w"
         */

        $price = "0";

        // define relevant objects
        $customer = new Customer();
        $customer->setFirstName($request['name'])
            ->setLastName($request['surname'])
            ->setEmail($request['email'])
            ->setIdentification($request['id'])
            ->setIsMonthly($request['is_monthly']);
        // add further customer details if necessary

        // define your transaction ID
        // must be unique! e.g.
        $merchantTransactionId = $merchantTransactionId ="Trener " . uniqid('myId', true) . '-' . date('YmdHis');

        // define transaction relevant object

        if ($request['is_monthly'] == "1") {
            $invoice_model = new Invoices($this->dbAdapter);
            $invoice_item = $invoice_model->getMonthlyItem();

            $price = $invoice_item['price'];
        } else if ($request['is_monthly'] == "0") {
            $invoice_model = new Invoices($this->dbAdapter);
            $invoice_item = $invoice_model->getYearlyItem();

            $price = $invoice_item['price'];
        }

        $debit = new Debit();
        $debit->setMerchantTransactionId($merchantTransactionId)
            ->setAmount($price)
            ->setCurrency('RSD')
            ->setCallbackUrl('https://phpstack-1301327-4919665.cloudwaysapps.com/?action=callback&id=' . $request['id'] . '&is_monthly=' . $request['is_monthly'] . '')
            ->setSuccessUrl('https://phpstack-1301327-4732761.cloudwaysapps.com/log/success')
            ->setErrorUrl('https://phpstack-1301327-4732761.cloudwaysapps.com/log/error')
            ->setDescription('Subscription')
            ->setCustomer($customer);

        // if token acquired via payment.js

        if (isset($request['token'])) {
            $debit->setTransactionToken($request['token']);
        }
        
        $result = $client->debit($debit);

        // handle the result
        if ($result->isSuccess()) {

            // store the uuid you receive from the gateway for future references
            $gatewayReferenceId = $result->getUuid();

            // handle result based on it's returnType
            if ($result->getReturnType() == Result::RETURN_TYPE_ERROR) {
                // error handling
                $response['status'] = "error";

                return $this->formatResponse(self::STATUS_FAILED, "", $response);

                die();
            } elseif ($result->getReturnType() == Result::RETURN_TYPE_REDIRECT) {
                // redirect the user

                $response['status'] = "redirect";
                $response['redirectUrl'] = $result->getRedirectUrl();

                return $this->formatResponse(self::STATUS_SUCCESS, "", $response);

                die();
            } elseif ($result->getReturnType() == Result::RETURN_TYPE_PENDING) {
                // payment is pending, wait for callback to complete

                $response['status'] = "pending";

                return $this->formatResponse(self::STATUS_SUCCESS, "", $response);

                die();

                // handle pending
                // setCartToPending();
            } elseif ($result->getReturnType() == Result::RETURN_TYPE_FINISHED) {

                // ovde sam stao nesto

                $response['status'] = "success";

                return $this->formatResponse(self::STATUS_SUCCESS, "", $response);
                die();
            }
        } else {

            // handle error
            $errors = $result->getErrors();
        }

        return $this->formatResponse(self::STATUS_FAILED, "", $errors);
    }

    private function callback()
    {
        $logFile = __DIR__ . '/callback_error_log.txt';
        $var_dumpFile= __DIR__ . '/var_dump_log.txt';
        $api_user = "personal-api";
        $api_password = "fvQoizXF7R.@LU#sCUzOj%$=Nm3+a";
        $connector_api_key = "personal-simulator";
        $connector_shared_secret = "9VkcsOb0snZRUAxiBeN0KaxPFFqPRb";
        $client = new ExchangeClient($api_user, $api_password, $connector_api_key, $connector_shared_secret);
        $request = $this->filterParams([
            'id',
            'is_monthly'
        ]);

        function logError($message, $logFile)
        {
            $timestamp = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
            $logEntry = "[{$timestamp}] ERROR: {$message}\n";
            file_put_contents($logFile, $logEntry, FILE_APPEND);
        }
        
        function logVarDump($message, $logFile)
        {
            $timestamp = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
            $logEntry = "[{$timestamp}] Object: {$message}\n";
            file_put_contents($logFile, $logEntry, FILE_APPEND);
        }

        try {

            $valid = $client->validateCallbackWithGlobals();

            if (! $valid) {
                logError("Callback validation failed.", $logFile);
                die();
            }

            $callbackInput = file_get_contents('php://input');
            if (! $callbackInput) {
                logError("Empty callback input received.", $logFile);
                die();
            }

            $callbackResult = $client->readCallback($callbackInput);
            $customer_id = $request['id'];
            $is_monthly = $request['is_monthly'];
            
            /* logVarDump($client, $var_dumpFile);
            logVarDump($callbackResult, $var_dumpFile);
            logError($callbackResult, $logFile);
            logError($client, $logFile);
            die(); */

            if ($callbackResult->getResult() === CallbackResult::RESULT_OK) {
                $user_model = new Users($this->dbAdapter);
                $invoice_model = new Invoices($this->dbAdapter);

                $current_sub_date = $user_model->getSubLength($customer_id);

                $date = $current_sub_date ? \DateTimeImmutable::createFromFormat('Y-m-d', $current_sub_date) : null;

                $current_date = new \DateTimeImmutable();
                $period_to_add = $is_monthly ? '+1 month' : '+1 year';

                if (! $date || $date < $current_date) {
                    $date = $current_date;
                }

                $new_date = $date->modify($period_to_add)->format('Y-m-d');

                $user_model->updateSub($customer_id, $new_date);
                
                //file_put_contents($logFile, print_r($callbackResult, true), FILE_APPEND);
                
                if ($is_monthly == "0") {
                    $invoice_model->addInvoiceYearly($customer_id, $new_date);
                } else {
                    $invoice_model->addInvoiceMonthly($customer_id, $new_date);
                }
            } elseif ($callbackResult->getResult() === CallbackResult::RESULT_ERROR) {
                $errorMessage = $callbackResult->getErrorMessage();
                $errorCode = $callbackResult->getErrorCode();
                $adapterMessage = $callbackResult->getAdapterMessage();
                $adapterCode = $callbackResult->getAdapterCode();

                $errorDetails = "Payment failed. ErrorMessage: {$errorMessage}, ErrorCode: {$errorCode}, AdapterMessage: {$adapterMessage}, AdapterCode: {$adapterCode}";
                logError($errorDetails, $logFile);
            }
        } catch (Exception $e) {
            logError("Exception caught: " . $e->getMessage(), $logFile);
        }

        echo "OK";
        die();
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

        // Remove the base64 URL prefix if it exists
        $base64_string = preg_replace('#^data:image/\w+;base64,#i', '', $base64_string);
        $decoded_data = base64_decode($base64_string);

        if ($decoded_data === false) {
            throw new \Exception("Failed to decode base64 string");
        }

        // Define the upload directory
        $upload_dir = $_SERVER['DOCUMENT_ROOT'] . "/images/users/";
        $upload_path = $upload_dir . $file_name . ".png";

        // Create directory if it does not exist
        if (! is_dir($upload_dir)) {
            if (! mkdir($upload_dir, 0777, true) && ! is_dir($upload_dir)) {
                throw new \Exception("Failed to create directory: " . $upload_dir);
            }
        }

        // Save the decoded image data to a file
        if (file_put_contents($upload_path, $decoded_data) === false) {
            throw new \Exception("Failed to save the image to: " . $upload_path);
        }

        return $this->formatResponse(self::STATUS_SUCCESS, "", []);
    }

    private function removeImage()
    {
        $request = $this->filterParams([
            'user_id'
        ]);

        $file_name = $request['user_id'];

        $upload_dir = $_SERVER['DOCUMENT_ROOT'] . "/images/users/";
        $upload_path = $upload_dir . $file_name . ".png";

        /*
         * var_dump($upload_dir);
         * die(var_dump($upload_path));
         */

        // Create dir if not exists
        if (! is_dir($upload_dir)) {
            $this->formatResponse(self::STATUS_SUCCESS, "", []);
        }

        unlink($upload_path);

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

    // private function test(){

    // $device_token = "czrk_P4vQkSWtz3EEqVE1p:APA91bELLrL9TY99N0AyQEm5N_iEkcqMj3oV6yyv4_LFRpEJHrIUSB7eukGQN_P9SPqVz9mhC9c4vufFn3WUy3kmQJRSXbxP4hgqO2gWBVkdKPa5IbUqiz0";

    // $this->sendNotification("Test", "Test Test", $device_token);

    // return $this->formatResponse(self::STATUS_SUCCESS, []);
    // }
    private function sendNotification(string $title, string $body, string $device_token, array $dataPayload = [])
    {
        $filePath = '/home/1301327.cloudwaysapps.com/xvvfqaxdrz/public_html/vendor/APISDK/personalni-trener-440e6-firebase-adminsdk-vjod3-044775a4e4.json';

        $client = new Client($filePath);

        $recipient = new Recipient();
        $notification = new Notification();

        $recipient->setSingleRecipient($device_token);

        $notification->setNotification($title, $body);

        if (! empty($dataPayload)) {
            $notification->setDataPayload($dataPayload);
        }

        $client->build($recipient, $notification);
        $client->fire();
    }

    /**
     *
     * @return array
     */
    private function getDeviceToken($user)
    {
        return substr($user['device_token'], 4);
    }

    private function testMail()
    {
        $mail = new PHPMailer();
         // configure an SMTP
         $mail->isSMTP();
         $mail->Host = 'sandbox.smtp.mailtrap.io';
         $mail->SMTPAuth = true;
         $mail->Username = 'ff1891b36df9cb';
         $mail->Password = 'e1241525bc4bbb';
         $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
         $mail->Port = 2525;
         
         $mail->setFrom('confirmation@trener.com', 'Test');
         $mail->addAddress('nikola.bojovic9@gmail.com');
         $mail->addCC('arsen.leontijevic@gmail.com');
         $mail->Subject = 'Testni mejl!';
         // Set HTML
         $mail->isHTML(TRUE);
         $mail->Body = '<html>Body.</html>';
         $mail->AltBody = '<html>Alt Body</html>';
         
         if(!$mail->send()){
             echo 'Message could not be sent.';
             echo 'Mailer Error: ' . $mail->ErrorInfo;
             
             die();
         } else {
             echo 'Message has been sent';
             die();
         }
         
         return $this->formatResponse(self::STATUS_SUCCESS, "", $mail);
    }
    
    private function testInvoices()
    {
        $invoice_model = new Invoices($this->dbAdapter);
        
        $is_monthly = "0";
        
        if ($is_monthly == "0") {
            $invoice_model->addInvoiceYearly("2", "2025-02-07");
        } else {
            $invoice_model->addInvoiceMonthly("2", "2025-02-07");
        }
        
        return $this->formatResponse(self::STATUS_SUCCESS, "", $err);
    }

    private function returnUser($userRow)
    {
        unset($userRow['password']);
        $user = (object) $userRow;
        $user->access_token = $this->getAccessToken($userRow);
        return $user;
    }

    /*
     * $merchant_key = "TREESRS";
     * $authenticity_token = "";
     *
     * $data = [
     * "amount" => 100,
     * // unique order identifier
     * "order_number" => 'random' . time(),
     * "currency" => "EUR",
     * "transaction_type" => "purchase",
     * "order_info" => "Create payment session order info",
     * "scenario" => 'charge'
     * ];
     *
     * $body_as_string = json_encode($data);
     * $base_url = 'https://ipgtest.monri.com';
     * $ch = curl_init($base_url . '/v2/payment/new');
     * curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
     * curl_setopt($ch, CURLOPT_POSTFIELDS, $body_as_string);
     * curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
     * curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
     * curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
     *
     * $timestamp = time();
     * $digest = hash('sha512', $merchant_key . $timestamp . $authenticity_token . $body_as_string);
     * $authorization = "WP3-v2 $authenticity_token $timestamp $digest";
     *
     * curl_setopt($ch, CURLOPT_HTTPHEADER, array(
     * 'Content-Type: application/json',
     * 'Content-Length: ' . strlen($body_as_string),
     * 'Authorization: ' . $authorization
     * ));
     *
     * $result = curl_exec($ch);
     *
     * if (curl_errno($ch)) {
     * curl_close($ch);
     * $response = [
     * 'client_secret' => null,
     * 'status' => 'declined',
     * 'error' => curl_error($ch)
     * ];
     * } else {
     * curl_close($ch);
     * $response = [
     * 'status' => 'approved',
     * 'client_secret' => json_decode($result, true)['client_secret']
     * ];
     * }
     */
}

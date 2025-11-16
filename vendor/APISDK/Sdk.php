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
use \APISDK\ConnStatus;
use phpFCMv1\Client;
use phpFCMv1\Notification;
use phpFCMv1\Recipient;
use Exchange\Client\Client as ExchangeClient;
use Exchange\Client\Data\Customer;
use Exchange\Client\Transaction\Debit;
use Exchange\Client\Transaction\Result;
use Exchange\Client\Transaction\Deregister;
use Exchange\Client\StatusApi\StatusRequestData;
use Exchange\Client\Callback\Result as CallbackResult;
use Exchange\Client\Data\ThreeDSecureData;
use APISDK\Models\Invoices;
use APISDK\Models\Countries;
use PHPMailer\PHPMailer\PHPMailer;
use DateTime;
use WdevRs\NetRacuniPhp\InvoiceResponse as NetRacunResponse;
use WdevRs\NetRacuniPhp\NetRacuniClient as NetRacun;
use APISDK\Models\PaymentCallbacks;

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
            'forgotPasswordCheck',
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
            'callbackDebug',
            'cronSubCheck',
            'testMail',
            'testInvoices',
            'test',
            'testPing',
            'testTaxLabels',
            'testInvoiceCheck',
            'testDateTime'
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
            
            $logFile = __DIR__ . '/api.log';
            $response = $this->$action();
            $this->logError(json_encode($request).json_encode($response), $logFile);
            $this->setResponse($response);
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
    /*
     * private function isFileExists($dir, $id)
     * {
     * return file_exists(self::DIR_UPLOADS . DIRECTORY_SEPARATOR . $dir . DIRECTORY_SEPARATOR . $id . ".png");
     * }
     */
    
    
    private function isFileExists($dir, $id)
    {
        return file_exists(self::DIR_UPLOADS . DIRECTORY_SEPARATOR . $dir . DIRECTORY_SEPARATOR . $id . ".png");
    }
    
    private function isImageExists($id)
    {
        
        $upload_dir = $_SERVER['DOCUMENT_ROOT'] . "/images/users/";
        $upload_path = $upload_dir . $id . ".png";
        
        return file_exists($upload_path);
    }

    private function getTodayTrainingsByTrainerId()
    {
        $request = $this->filterParams([
            'trainer_id'
        ]);

        $training_model = new Trainings($this->dbAdapter);
        $trainings = $training_model->getTodayTrainingsByTrainerId($request['trainer_id']);

        array_walk($trainings, function (&$a) {
            if ($this->isFileExists(self::DIR_USERS, $a["client_ids"])) {
                $a['image'] = $this->domain . "/images/users/" . $a["client_ids"] . ".png?r=" . rand(0, 100000);
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
            if ($this->isFileExists(self::DIR_USERS, $a["client_ids"])) {
                $a['image'] = $this->domain . "/images/users/" . $a["client_ids"] . ".png?r=" . rand(0, 100000);
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

        $user_model = new Users($this->dbAdapter);

        foreach ($trainings as &$one) {
            $users = $user_model->getUsersByTrainingId($one['id']);
            $count = sizeof($users);
            $one['count'] = strval($count);
        }

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
        ], [
            'mon',
            'tue',
            'wed',
            'thu',
            'fri',
            'sat',
            'sun',
            'end_date'
        ]);

        $start_date = new \DateTimeImmutable($request['start_date']);
        $end_date = new \DateTimeImmutable($request['end_date']);
        $training_model = new Trainings($this->dbAdapter);

        $trainings = [];

        $i = 0;

        if ($start_date == $end_date) {
            return $this->formatResponse(self::STATUS_SUCCESS, "", []);
        }

        var_dump($start_date);
        var_dump($end_date);
        die();

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
        ], [
            'mon',
            'tue',
            'wed',
            'thu',
            'fri',
            'sat',
            'sun',
            'end_date'
        ]);

        $clients = isset($request['clients']) ? json_decode($request['clients'], true) : [];

        if ($request['repeated'] == '0') {
            $training_model = new Trainings($this->dbAdapter);
            $trainings = $training_model->insertTraining($request['trainer_id'], $request['gym_id'], $request['is_group'], $request['date'], $request['time'], $request['training_plan']);

            foreach ($clients as $one) {
                $user_model = new Users($this->dbAdapter);
                $price = $user_model->getConnectionPriceByIds($request['trainer_id'], $one);
                $this->addClientToTraining($trainings[0]['id'], $one, $price, $request['trainer_id']);
            }

            return $this->formatResponse(self::STATUS_SUCCESS, "", $trainings);
        } else if ($request['repeated'] == '1') {

            $start_date = new \DateTimeImmutable($request['date']);
            $end_date = new \DateTimeImmutable($request['end_date']);
            $training_model = new Trainings($this->dbAdapter);

            $trainings = [];

            if ($end_date < $start_date) {
                return $this->formatResponse(self::STATUS_FAILED, "End date before start date.", $trainings);
            }

            if ($start_date == $end_date) {
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

                return $this->formatResponse(self::STATUS_SUCCESS, "", []);
            }

            /*
             * var_dump($start_date);
             * var_dump($end_date);
             * die();
             */

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

            // die(var_dump($trainings));

            foreach ($trainings as $training) {
                foreach ($clients as $client_id) {
                    $user_model = new Users($this->dbAdapter);
                    $price = $user_model->getConnectionPriceByIds($request['trainer_id'], $client_id);
                    $this->addClientToTraining($training['id'], $client_id, $price, $request['trainer_id']);
                }
            }
            return $this->formatResponse(self::STATUS_SUCCESS, "", $trainings);
        }

        return $this->formatResponse(self::STATUS_FAILED, "-1", $trainings);
    }

    private function addClientToTraining(string $training_id, string $client_id, string $price, string $trainer_id)
    {
        /*
         * $request = $this->filterParams([
         * 'training_id',
         * 'client_id',
         * 'price',
         * 'trainer_id'
         * ]);
         */
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

        $newTrainingMessage = $this->getTranslatedMessage('new_training', $this->getAppLanguage() ?: 'en', [
            'first_name' => $trainer['first_name'],
        ]);
        $this->sendNotification($newTrainingMessage, $date . ' ' . $time, $client['device_token'], $dataPayload);

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
            $trainerCancelMessage = $this->getTranslatedMessage('training_canceled_by_trainer', $this->getAppLanguage() ?: 'en', [
                'first_name' => $trainer['first_name'],
            ]);
            $cancelInfo = $this->getTranslatedMessage('canceled_training_info', $this->getAppLanguage() ?: 'en', [
                'date' => $date,
                'time' => $time,
            ]);
            $this->sendNotification($trainerCancelMessage, $cancelInfo, $one['device_token'], $dataPayload);
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
            'trainer_id',
            'notification'
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

        $moreTokens = [
            $client['device_token']
        ];

        if($request['notification'] == "1"){
            $clientCancelMessage = $this->getTranslatedMessage('training_canceled_by_client', $this->getAppLanguage() ?: 'en', [
                'first_name' => $client['first_name'],
            ]);
            $cancelInfo = $this->getTranslatedMessage('canceled_training_info', $this->getAppLanguage() ?: 'en', [
                'date' => $date,
                'time' => $time,
            ]);
            $this->sendNotification($clientCancelMessage, $cancelInfo, $trainer['device_token'], $dataPayload, $moreTokens);
        }
        
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
    
    private function createFitnessCenter()
    {
        $request = $this->filterParams([
            'name',
            'address',
            'city_id',
            'phone',
            'city'
        ]);
        
        $gyms_model = new Gyms($this->dbAdapter);
        
        $request["created_on"] = date("now");
        
        $gym_id = $gyms_model->getDbAdapter()->insert($request);
        
        $gyms = $gyms_model->addFitnessCenter($this->user_id, $gym_id);
        
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

    private function updateFitnessCenters()
    {
        $request = $this->filterParams([
            'added',
            'removed'
        ]);

        $user_id = $this->user_id;

        $added = isset($request['added']) ? json_decode($request['added'], true) : [];
        $removed = isset($request['removed']) ? json_decode($request['removed'], true) : [];

        foreach ($added as $gym) {
            $this->addFitnessCenterNew($user_id, $gym);
        }

        foreach ($removed as $gone) {
            $this->removeFitnessCenterNew($user_id, $gone);
        }

        return $this->formatResponse(self::STATUS_SUCCESS, "", []);
    }

    private function addFitnessCenterNew(string $user_id, string $gym_id)
    {
        /*
         * $request = $this->filterParams([
         * 'user_id',
         * 'gym_id'
         * ]);
         */
        $gyms_model = new Gyms($this->dbAdapter);
        $result = $gyms_model->addFitnessCenter($user_id, $gym_id);

        // Check if $result indicates success
        if ($result) {
            return true;
        } else {
            return false;
        }

        // return $this->formatResponse(self::STATUS_SUCCESS, "", $gyms);
    }

    private function removeFitnessCenterNew(string $user_id, string $gym_id)
    {
        /*
         * $request = $this->filterParams([
         * 'user_id',
         * 'gym_id'
         * ]);
         */
        $gyms_model = new Gyms($this->dbAdapter);
        $result = $gyms_model->removeFitnessCenter($user_id, $gym_id);

        if ($result) {
            return true;
        } else {
            return false;
        }

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

        $file_path = $_SERVER['DOCUMENT_ROOT'] . "/images/users/" . $users["id"] . ".png";
        if (file_exists($file_path)) {
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
        // $request = $this->filterParams([
        // 'client_id'
        // ]);
        $mes_model = new Measurements($this->dbAdapter);

        $measurements = $mes_model->getMeasurementsByClientId($this->user_id);

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
            'country_id',
            'nationality'
        ]);

        $users_model = new Users($this->dbAdapter);

        if ($request['password'] != "") {
            $newPassHash = password_hash($request['password'], PASSWORD_BCRYPT);
            $users_model->changePassword($request['id'], $newPassHash);
        }

        $users = $users_model->updateInfo($request['id'], $request['name'], $request['surname'], $request['age'], $request['phone'], $request['email'], $request['deadline'], $request['gender'], $request['city_id'], $request['en'], $request['rs'], $request['ru'], $request['country_id'], $request['nationality']);

        $user = $this->getUpdatedUser();
        return $this->formatResponse(self::STATUS_SUCCESS, "", $user);
    }

    private function getUpdatedUser()
    {
        $users_model = new Users($this->dbAdapter);
        $training_model = new Trainings($this->dbAdapter);

        $user = $users_model->getUserById($this->user_id);
        return $this->populateUserModel($user);
    }

    /**
     * Populate user model with expected properties
     */
    private function populateUserModel(array $user)
    {
        if(!isset($user["id"])){
            throw new \Exception("No userID specified");
        }
        
        $users_model = new Users($this->dbAdapter);
        $training_model = new Trainings($this->dbAdapter);
        if ($this->isImageExists($user["id"])) {
            $user['image'] = $this->domain . "/images/users/" . $user["id"] . ".png?r=" . rand(0, 100000);
        } else {
            $user['image'] = $this->domain . "/images/users/logo.png";
        }

        $user['active_clients'] = $users_model->getActiveClients($user["id"]);
        $user['active_trainers'] = $users_model->getActiveTrainers($user["id"]);
        $user['total_trainings_trainer'] = $training_model->getTrainingsTrainer($user["id"]);
        $user['total_trainings_client'] = $training_model->getTrainingsClient($user["id"]);
        if ($user['is_trainer'] == '1') {
            $user['profit'] = $users_model->getProfitProfileTrainer($user["id"]);
            $user['debt'] = "0";
        } else {
            $user['profit'] = "0";
            $user['debt'] = $users_model->getDebtProfileClient($user["id"]);
        }
        $user["access_token"] = $this->getAccessToken($user);
        return $user;
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

        $newRequestMessage = $this->getTranslatedMessage('new_request', $this->getAppLanguage() ?: 'en');
        $this->sendNotification($newRequestMessage, $client['first_name'] . ' ' . $client['last_name'], $trainer['device_token'], $dataPayload);
        
        //$this->logError($message, $logFile);
        
        return $result;

        // return $this->formatResponse(self::STATUS_SUCCESS, "", $result);
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

        $requestAcceptedMessage = $this->getTranslatedMessage('request_accepted', $this->getAppLanguage() ?: 'en');
        $this->sendNotification($requestAcceptedMessage, $client['first_name'] . ' ' . $client['last_name'], $client['device_token'], $dataPayload);
        
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
        

        if ($request['en'] == "1") {
            $lang = "en";
        } elseif ($request['ru'] == "1" && $request['rs'] != "1") {
            $lang = "ru";
        } elseif ($request['ru'] == "1" && $request['rs'] == "1") {
            $lang = "sr";
        } elseif ($request['rs'] == "1") {
            $lang = "sr";
        } else {
            $lang = "en";
        }
        
        $request['email'] = preg_replace('/\s/', '+', trim($request['email']));
        
//         if (!filter_var($request['email'], FILTER_VALIDATE_EMAIL)) {
            
//             return $this->formatResponse(self::STATUS_FAILED, "INVALID");
//         }

        //Add random mail if is "-1"
        $request['email'] = $request['email'] == "-1" ? $this->randomOfflineEmail() : $request['email'];
        

        $users_model = new Users($this->dbAdapter);
        $user = $users_model->getUserByEmail($request['email']);
        
        /**
         * OFFLINE FEATURE
         */
        $users[] = $user;
        $connection = ConnStatus::NO_CONN;
        //Check if its new client register by trainer
        if(isset($this->request["offline"]))
        {
            //If user dont exist or not confirmed email, add connection
            $connection = ConnStatus::ACCEPTED;
            if ($user) {
                //If user is using app, return -1, send request
                if ($user["email_confirmed"] == "1") {
                    $connection = ConnStatus::DEFAULT;
                }
            }
        }else{
            $this->request["offline"] = "0";
            if ($user) {
                return $this->formatResponse(self::STATUS_FAILED, "-1");
            }
        }

        $password = password_hash($request['password'], PASSWORD_BCRYPT);
        $hash = md5(time());
        $users = $users_model->register($request['name'], $request['surname'], $request['age'], $request['phone'], $password, $request['email'], $request['deadline'], $request['gender'], $request['city_id'], $request['en'], $request['rs'], $request['ru'], $request['is_trainer'], $request['country_id'], $request['nationality'], $hash, $this->request["offline"]);

        
        
        /**
         * MAKE AUTO CONNECTION IF TRAINER IS ADDING CLIENT
         */
        if ($connection == ConnStatus::ACCEPTED)
        {
            $users_model = new Users($this->dbAdapter);
            $users = $users_model->makeAcceptedConnection($users[0]['id'], $this->user_id);
            return $this->formatResponse(self::STATUS_SUCCESS, $connection, $this->returnUser($users[0]));
        }
        if ($connection == ConnStatus::DEFAULT)
        {
            $this->request["trainer_id"] = $this->user_id;
            $this->request["client_id"] = $users[0]['id'];
            $this->sendRequestClient();
            return $this->formatResponse(self::STATUS_SUCCESS, $connection, $this->returnUser($users[0]));
        }
        /**
         * END OFFLINE FEATURE
         */
        
        
        $mail = new PHPMailer();

        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'ptrenersrb@gmail.com';
        $mail->Password = 'dlvw rdak ejtk yqlm'; // use the App Password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('ptrenersrb@gmail.com', 'Personalni Trener');
        $mail->addAddress($request['email']);
        $mail->addAddress('nikola.bojovic9@gmail.com');
        $mail->addCC('arsen.leontijevic@gmail.com');
        $mail->Subject = 'Potvrda naloga';
        // Set HTML
        $mail->isHTML(TRUE);
        $mail->Body = $this->getRegisterMail($lang, $hash);

        $mail->send();

        return $this->formatResponse(self::STATUS_SUCCESS, "", $this->returnUser($users[0]));
    }
    
    private function registerClient()
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
            'country_id',
            'offline'
        ]);
        
        
        $request['email'] = preg_replace('/\s/', '+', trim($request['email']));
        
        $request['email'] = $request['email'] == "-1" ? $this->randomOfflineEmail() : $request['email'];
        
        
        $users_model = new Users($this->dbAdapter);
        $user = $users_model->getClientByEmail($request['email']);
        
        
        //If user dont exist or not confirmed email, add connection
        $connection = ConnStatus::ACCEPTED;
        $users = [];
        
        if ($user) {
            // User exists
            $users[] = $user;
            
            if ($user['email_confirmed'] ?? $user->email_confirmed ?? 0 == "1") {
                $connection = ConnStatus::DEFAULT;
            }
        } else {
            // Create new user
            $password = password_hash($request['password'], PASSWORD_BCRYPT);
            $hash = md5(time());
            
            $newUser = $users_model->register(
                $request['name'],
                $request['surname'],
                $request['age'],
                $request['phone'],
                $password,
                $request['email'],
                $request['deadline'],
                $request['gender'],
                $request['city_id'],
                $request['en'],
                $request['rs'],
                $request['ru'],
                $request['is_trainer'],
                $request['country_id'],
                $request['nationality'],
                $hash,
                $request['offline']
                );
            
            // CRITICAL: Ensure $newUser is an array or object we can work with
            $users[] = is_array($newUser) ? $newUser : (array) $newUser;
        }
        
        //return $this->formatResponse(self::STATUS_SUCCESS, $connection, $users);
        
        $userObject = (object) $users[0];
        
        $userToReturn = $this->returnUser((array)$userObject);
        $userToReturn['connection'] = $connection;
        
        /**
         * MAKE AUTO CONNECTION IF TRAINER IS ADDING CLIENT
         */
        if ($connection == ConnStatus::ACCEPTED)
        {
            $users_model = new Users($this->dbAdapter);
            $users = $users_model->makeAcceptedConnection($userObject->id, $this->user_id);
            return $this->formatResponse(self::STATUS_SUCCESS, $connection, $userToReturn);
        }
        if ($connection == ConnStatus::DEFAULT)
        {
            $this->request["trainer_id"] = $this->user_id;
            $this->request["client_id"] = $userObject->id;
            $this->sendRequestClient();
            return $this->formatResponse(self::STATUS_SUCCESS, $connection, $userToReturn);
        }
        /**
         * END OFFLINE FEATURE
         */
        
        return $this->formatResponse(self::STATUS_SUCCESS, "", $this->returnUser($users[0]));
    }
    
    private function randomOfflineEmail($length = 16) {
        $characters = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $localPart = '';
        
        for ($i = 0; $i < $length; $i++) {
            $localPart .= $characters[random_int(0, strlen($characters) - 1)];
        }
        
        return $localPart . '@offline.usr';
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
            if ($this->isImageExists($a["id"])) {
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
    
    /**
     * Validate Apple IAP receipt
     *
     * @param string $receiptData Base64 encoded receipt
     * @param bool $isSandbox Set true if validating sandbox receipts
     * @return array ['success' => bool, 'data' => array, 'error' => string|null]
     */
    private function validatePurchase() {
        
        $request = $this->filterParams([
            'receipt_data',
            'is_sendbox'
        ]);
        
        $request["is_sendbox"] = "1";
        
        $endpoint = (bool)$request["is_sendbox"]
        ? 'https://sandbox.itunes.apple.com/verifyReceipt'
            : 'https://buy.itunes.apple.com/verifyReceipt';
            
            $sharedSecret = 'c35d802af7c44a44b4f9a3bdd6292219'; // App Store Connect: App-Specific Shared Secret
            
            $request["receipt_data"] = urldecode($request["receipt_data"] ?? '');
            
            $postData = json_encode([
                'receipt-data' => $request["receipt_data"],
                'password' => $sharedSecret,
                'exclude-old-transactions' => true
            ]);
            
            $ch = curl_init($endpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            if ($response === false) {
                return $this->formatResponse(self::STATUS_FAILED, curl_error($ch), []);
            }
            
            curl_close($ch);
            
            $json = json_decode($response, true);
            if ($json === null) {
                return $this->formatResponse(self::STATUS_FAILED, 'Invalid JSON response from Apple', []);
            }
            
            // status 0 = valid receipt
            if (isset($json['status']) && $json['status'] === 0) {
                $user_model = new Users($this->dbAdapter);
                $invoice_model = new Invoices($this->dbAdapter);
                $callback_model = new PaymentCallbacks($this->dbAdapter);
                
                
                $latestReceiept = $json["latest_receipt_info"][0];
                
                $transactionId =  isset($latestReceiept["transaction_id"]) ? $latestReceiept["transaction_id"] : md5(time());
                $is_monthly =  $latestReceiept["product_id"];
                
                $new_datestamp = $latestReceiept["expires_date_ms"];
                $seconds = $new_datestamp / 1000;
                $new_date = date("Y-m-d", $seconds);
                
                // Update user subscription
                $user_model->updateSub($this->user_id, $new_date);
                
                // Save invoice and mark transaction as handled
                if ($is_monthly == "Monthly") {
                    //$invoice_model->addInvoiceMonthly($this->user_id, $new_date, $transactionId);
                    //$this->sandboxReceiptMonthly($email, $transactionData);
                } else {
                    //$invoice_model->addInvoiceYearly($this->user_id, $new_date, $transactionId);
                    //$this->sandboxReceiptYearly($email, $transactionData);
                }
                
                $data = json_encode($json, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                $callback_model->insertItem($transactionId, $data);
                
                return $this->formatResponse(self::STATUS_SUCCESS, '', $json);
            }
            
            return $this->formatResponse(self::STATUS_FAILED, 'Receipt invalid or expired. Status: ' . ($json['status'] ?? 'unknown'), $json);
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
            if ($this->isImageExists($a["id"])) {
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
        $request['email'] = preg_replace('/\s/', '+', trim($request['email']));
        
//         if (!filter_var($request['email'], FILTER_VALIDATE_EMAIL)) {
            
//             return $this->formatResponse(self::STATUS_FAILED, "INVALID");
//         }

        $user_model = new Users($this->dbAdapter);
        $user = $user_model->getUserByEmail($request['email']);

        if (! $user) {
            return $this->formatResponse(self::STATUS_FAILED, "-1");
        }

        if ($user['email_confirmed'] == '0') {
            return $this->formatResponse(self::STATUS_FAILED, "-1");
        }

        if (password_verify($request['password'], $user["password"])) {
            unset($user["password"]);

            $toReturn = $this->populateUserModel($user);
            return $this->formatResponse(self::STATUS_SUCCESS, "", $toReturn);
        }

        return $this->formatResponse(self::STATUS_FAILED, "-1");
    }

    private function getMyAccount()
    {
        $users_model = new Users($this->dbAdapter);
        $training_model = new Trainings($this->dbAdapter);

        $user = $users_model->getUserById($this->user_id);

        unset($user["password"]);

        $toReturn = $this->populateUserModel($user);
        return $this->formatResponse(self::STATUS_SUCCESS, "", $toReturn);
    }

    private function getAppLanguage()
    {
        $users_model = new Users($this->dbAdapter);
        $language = $users_model->getAppLanguage($this->user_id);

        return $language;
    }

    private function setAppLanguage()
    {
        $request = $this->filterParams([
            'language'
        ]);
        
        if($request['language'] == "sr-Latn") $request['language'] = 'sr';
        

        $users_model = new Users($this->dbAdapter);
        $users_model->setAppLanguage($this->user_id, $request['language']);

        return $this->formatResponse(self::STATUS_SUCCESS, "", []);
    }

    private function forgotPasswordCheck()
    {
        $request = $this->filterParams([
            'email'
        ]);

        $hash = md5(time());

        $userModel = new Users($this->dbAdapter);
        $user = (array) $userModel->getUserByEmail($request["email"]);

        $userModel->setMailHash($user['id'], $hash);

        if (! isset($user['id'])) {
            throw new ApiException("There is no such user");
        }

        $lang = $userModel->getAppLanguage($user['id']);
        // var_dump($user);
        // die(var_dump($lang));

        $generated_link = $this->getBaseUrl() . "/?action=forgotPassword&hash=" . $hash . "&language=" . $lang;

        $mail = new PHPMailer();

        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'ptrenersrb@gmail.com';
        $mail->Password = 'dlvw rdak ejtk yqlm';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('ptrenersrb@gmail.com', 'Personalni Trener');
        $mail->addAddress($user['email']);
        $mail->addCC('nikola.bojovic9@gmail.com');
        $mail->addCC('arsen.leontijevic@gmail.com');
        $mail->Subject = 'Zahtev za promenu lozinke';
        // Set HTML
        $mail->isHTML(TRUE);
        $mail->Body = $this->getPasswordCheckMail($lang, $generated_link);

        $mail->send();

        return $this->formatResponse(self::STATUS_SUCCESS, "", []);
    }

    private function forgotPassword()
    {
        $request = $this->filterParams([
            'hash',
            'language'
        ]);
        // $request = $this->filterParams(['email']);

        $generated_pass = crc32(time());

        $password_hash = password_hash($generated_pass, PASSWORD_BCRYPT);

        $userModel = new Users($this->dbAdapter);
        $user = (array) $userModel->getUserByHash($request["hash"]);

        if (! isset($user['id'])) {
            throw new ApiException("There is no such user");
        }

        $userModel->forgotPassword($user['id'], $password_hash);

        $lang = $request['language'];

        $mail = new PHPMailer();

        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'ptrenersrb@gmail.com';
        $mail->Password = 'dlvw rdak ejtk yqlm'; // use the App Password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('ptrenersrb@gmail.com', 'Personalni Trener');
        $mail->addAddress($user['email']);
        $mail->addCC('nikola.bojovic9@gmail.com');
        $mail->addCC('arsen.leontijevic@gmail.com');
        $mail->Subject = 'Zahtev za promenu lozinke';
        // Set HTML
        $mail->isHTML(TRUE);
        $mail->Body = $this->getForgotPassLanguageMail($lang, $generated_pass);

        $mail->send();

        echo $this->getForgotPassEcho($lang);
        exit();
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
            'is_monthly',
            'to_save',
            'uuid',
            'country_id',
            'city_id',
            'merchant_transaction_id'
        ]);

        $logFile = __DIR__ . '/init_error_log.txt';

        $request['token'] = str_replace(' ', '+', $request['token']);
        $api_user = "personal-1025-api";
        $api_password = "0wZAi#G#4Z@F13dZp3ktaYFeeu*GD";
        $connector_api_key = "personal-rfb-01";
        $connector_shared_secret = "9woAbQKFOyIWMrzR518GGtcjeUbG4J";
        $client = new ExchangeClient($api_user, $api_password, $connector_api_key, $connector_shared_secret);
        $request['token'] = str_replace(' ', '+', $request['token']);
        /*
         * $token = "IEta5qtej1cxZ1tBgKIotb+Owt+/yotP3COmU9ZCzAJpBeTqENIaNHyel2Uh4yCZQlFoOzOVLrhtYVvF10V31ge
         * EUSvqH3T70xvJCGF6XNBGnTr8t2UP9nv48gl1Mh7//86m8gNJEbtLIJvM99PsJv+aIF0jdOjekC6InyxthWd9w"
         */

        $country_model = new Countries($this->dbAdapter);
        $country = $country_model->getCountryById($request['country_id']);

        $city_model = new Cities($this->dbAdapter);
        $city = $city_model->getCityById($request['country_id']);

        $price = "0";

        $flag = "";

        // define relevant objects
        $customer = new Customer();
        $customer->setFirstName($request['name'])
            ->setLastName($request['surname'])
            ->setEmail($request['email'])
            ->setIdentification($request['id'])
            ->setIsMonthly($request['is_monthly'])
            ->setBillingCity($city['city'])
            ->setBillingCountry("RS");
        // add further customer details if necessary

        // define your transaction ID
        // must be unique! e.g.
        $merchantTransactionId = $request['merchant_transaction_id'];

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
        
        $threeDSecure = new \Exchange\Client\Data\ThreeDSecureData();
        
        $threeDSecure
        ->setBrowserAcceptHeader($_SERVER['HTTP_ACCEPT'] ?? '/')
        ->setBrowserUserAgent($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown')
        ->setBrowserScreenHeight(700)
        ->setBrowserScreenWidth(500)
        ->setBrowserColorDepth(24)
        ->setBrowserLanguage(substr($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'en', 0, 2))
        ->setBrowserTimezone((-date('Z') / 60));

        $debit = new Debit();
        
        $debit->setThreeDSecureData($threeDSecure);

        if ($request['to_save'] == "1" && $request['uuid'] == "0") {
            $debit->setWithRegister(true);
            $debit->setTransactionIndicator('SINGLE');
            if (isset($request['token'])) {
                $debit->setTransactionToken($request['token']);
            }
            $flag = "REGISTER to_save=1 i uuid=0";
        }
        if ($request['uuid'] != "0") {
            $debit->setWithRegister(false);
            $debit->setTransactionIndicator('CARDONFILE');
            $debit->setReferenceUuid($request['uuid']);
            $flag = "CARDONFILE uuid!=0";
        }

        if ($request['to_save'] == "0" && $request['uuid'] == "0") {
            if (isset($request['token'])) {
                $debit->setTransactionToken($request['token']);
            }
            $flag = "BEZ CUVANJA";
        }
        $debit->setMerchantTransactionId($merchantTransactionId)
            ->setAmount($price)
            ->setCurrency('RSD')
            ->setCallbackUrl('https://phpstack-1301327-4919665.cloudwaysapps.com/?action=callback&id=' . $request['id'] . '&is_monthly=' . $request['is_monthly'] . '&email=' . $request['email'] . '')
            ->
        // ->setCallbackUrl('https://phpstack-1301327-4919665.cloudwaysapps.com/?action=callbackDebug')
        setSuccessUrl('https://phpstack-1301327-4732761.cloudwaysapps.com/log/success')
            ->setErrorUrl('https://phpstack-1301327-4732761.cloudwaysapps.com/log/error')
            ->setDescription('Subscription')
            ->setCustomer($customer);

        $result = $client->debit($debit);

        if ($result->isSuccess()) {

            $gatewayReferenceId = $result->getUuid();
            $creditcardData = $result->getReturnData();
            $extraData = $result->getExtraData();

            // handle result based on it's returnType
            if ($result->getReturnType() == Result::RETURN_TYPE_ERROR) {
                // error handling
                $errors = $result->getErrors();
                $response['status'] = $result->getReturnType();
                $response['errors'] = $errors;

                return $this->formatResponse(self::STATUS_SUCCESS, "", $response);

                die();
            } elseif ($result->getReturnType() == Result::RETURN_TYPE_REDIRECT) {
                // redirect the user

                $response['status'] = $result->getReturnType();
                $response['redirectUrl'] = $result->getRedirectUrl();
                $response['uuid'] = $gatewayReferenceId;
                $response['merchant_transaction_id'] = $merchantTransactionId;
                $response['price_full'] = $price . " RSD";
                $response['card_type'] = $creditcardData->getBinBrand();
                $response['bank_code'] = isset($extraData['authCode']) ? $extraData['authCode'] : "XXXX";
                $response['flag'] = $flag;

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

                $response['status'] = $result->getReturnType();;
                $response['uuid'] = $gatewayReferenceId;
                $response['merchant_transaction_id'] = $merchantTransactionId;
                $response['price_full'] = $result->getAmount() . " " . $result->getCurrency();
                $response['card_type'] = $creditcardData->getBinBrand();
                $response['bank_code'] = isset($extraData['authCode']) ? $extraData['authCode'] : "XXXX";
                $response['flag'] = $flag;

                return $this->formatResponse(self::STATUS_SUCCESS, "", $response);
                die();
            }
        } else {
            $errors = $result->getErrors();
            $debug_td = var_export($errors, true);
            $this->logError($debug_td, $logFile);
        }

        $errors = $result->getErrors();
        $debug_td = var_export($errors, true);
        $this->logError($debug_td, $logFile);
        
        return $this->formatResponse(self::STATUS_SUCCESS, "", $errors);
    }

    private function deregisterCard()
    {
        try {
            $request = $this->filterParams([
                'referenceUuid'
            ]);

            $logFile = __DIR__ . '/deregister_error_log.txt';

            $api_user = "personal-1025-api";
            $api_password = "0wZAi#G#4Z@F13dZp3ktaYFeeu*GD";
            $connector_api_key = "personal-rfb-01";
            $connector_shared_secret = "9woAbQKFOyIWMrzR518GGtcjeUbG4J";
            $client = new ExchangeClient($api_user, $api_password, $connector_api_key, $connector_shared_secret);

            $deregister = new Deregister();
            $deregister->setReferenceUuid($request['referenceUuid']);
            $deregister->setMerchantTransactionId("Trener-" . md5(time()));

            $result = $client->deregister($deregister);

            file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Result: " . json_encode($result) . "\n", FILE_APPEND);
            file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Client: " . json_encode($client) . "\n", FILE_APPEND);

            if ($result->isSuccess()) {
                return $this->formatResponse(self::STATUS_SUCCESS, "", $result);
            } else {
                return $this->formatResponse(self::STATUS_FAILED, "", []);
            }
        } catch (Exception $e) {
            file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Error: " . $e . "\n", FILE_APPEND);
            return $this->formatResponse(self::STATUS_FAILED, "", []);
        }
    }
    
    
    private function fetchPaymentStatus(){
        
        $request = $this->filterParams([
            'merchant_transaction_id'
        ]);
        
        $callback_model = new PaymentCallbacks($this->dbAdapter);
        $result = $callback_model->getItemByMerchantTransactionId($request['merchant_transaction_id']);
        
        if($result==null){
            return $this->formatResponse(self::STATUS_FAILED, "", json_decode($result, true));
        }
        
        return $this->formatResponse(self::STATUS_SUCCESS, "", json_decode($result, true));
    }

    
    private function callback()
    {
        $logFile = __DIR__ . '/callback_error_log.txt';
        $varDumpFile = __DIR__ . '/var_dump_log.txt';

        $api_user = "personal-1025-api";
        $api_password = "0wZAi#G#4Z@F13dZp3ktaYFeeu*GD";
        $connector_api_key = "personal-rfb-01";
        $connector_shared_secret = "9woAbQKFOyIWMrzR518GGtcjeUbG4J";
        $client = new ExchangeClient($api_user, $api_password, $connector_api_key, $connector_shared_secret);

        $request = $this->filterParams([
            'id',
            'is_monthly',
            'email'
        ]);

        $user_model = new Users($this->dbAdapter);
        $lang = $user_model->getAppLanguage($request['id']);

        try {
            $valid = $client->validateCallbackWithGlobals();

            if (! $valid) {
                $this->logError("Callback validation failed.", $logFile);
                $this->respondOk(); // Exit safely
            }

            $callbackInput = file_get_contents('php://input');
            if (! $callbackInput) {
                $this->logError("Empty callback input received.", $logFile);
                $this->respondOk();
            }

            $callbackResult = $client->readCallback($callbackInput);
            $transactionId = $callbackResult->getMerchantTransactionId();
            $amount = $callbackResult->getAmount();
            $currency = $callbackResult->getCurrency();
            $paymentMethod = $callbackResult->getPaymentMethod();
            $purchaseId = $callbackResult->getPurchaseId();
            $extraData = $callbackResult->getExtraData();

            // Get card type from returnData (CreditcardData)
            $returnData = $callbackResult->getReturnData();
            $cardType = null;
            if ($returnData && method_exists($returnData, 'getBinBrand')) {
                $cardType = $returnData->getBinBrand(); // 'VISA' from your log
            } elseif ($returnData && method_exists($returnData, 'getType')) {
                $cardType = $returnData->getType(); // 'visa' from your log
            }

            // Alternative: Check binBrand first as it's more standardized
            if (! $cardType && $returnData && isset($returnData->binBrand)) {
                $cardType = $returnData->binBrand;
            }

            // Current date in dd.mm.yyyy format
            $currentDate = date('Y-m-d H:i:s', time());

            $transactionData = [
                'transaction_id' => $transactionId,
                'status' => 'Success',
                'amount' => $amount,
                'currency' => $currency,
                'payment_method' => $paymentMethod,
                'purchase_id' => $purchaseId,
                'card_type' => $cardType ?: 'Unknown', // Fallback if not available
                'processed_date' => $currentDate,
                'bank_code' => isset($extraData['authCode']) ? $extraData['authCode'] : "XXXX"
            ];

            // $debug_cb = var_export($callbackResult, true);
            // $debug_td = var_export($transactionData, true);

            // $this->logError($debug_cb, $logFile);
            // $this->logError($debug_td, $logFile);

            $customer_id = $request['id'];
            $is_monthly = $request['is_monthly'];
            $email = $request['email'];

            $user_model = new Users($this->dbAdapter);
            $invoice_model = new Invoices($this->dbAdapter);

            
            $callback_model = new PaymentCallbacks($this->dbAdapter);
            
            $data = json_encode(json_decode($callbackInput, true), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $callback_model->insertItem($transactionId, $data);
            

            // Avoid duplicate processing
            if ($invoice_model->wasTransactionAlreadyHandled($transactionId)) {
                file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Skipped duplicate callback for $transactionId\n", FILE_APPEND);
                $this->respondOk();
            }

            if ($callbackResult->getResult() === CallbackResult::RESULT_OK) {
                $current_sub_date = $user_model->getSubLength($customer_id);
                $date = $current_sub_date ? \DateTimeImmutable::createFromFormat('Y-m-d', $current_sub_date) : null;

                $current_date = new \DateTimeImmutable();
                $period_to_add = $is_monthly == "1" ? '+1 month' : '+1 year';

                if (! $date || $date < $current_date) {
                    $date = $current_date;
                }

                $new_date = $date->modify($period_to_add)->format('Y-m-d');

                // Update user subscription
                $user_model->updateSub($customer_id, $new_date);

                // Save invoice and mark transaction as handled
                if ($is_monthly == "1") {
                    $invoice_model->addInvoiceMonthly($customer_id, $new_date, $transactionId);
                    $this->sandboxReceiptMonthly($email, $transactionData);
                } else {
                    $invoice_model->addInvoiceYearly($customer_id, $new_date, $transactionId);
                    $this->sandboxReceiptYearly($email, $transactionData);
                }

                file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Processed transaction: $transactionId\n", FILE_APPEND);
            } elseif ($callbackResult->getResult() === CallbackResult::RESULT_ERROR) {
                $error = $callbackResult->getFirstError();
                // $debug_td = var_export($error, true);

                $error_code = 0;
                if ($error) {
                    $error_code = $error->getCode() ?: "Unexpected error or sandbox";
                } else {
                    $error_code = $callbackResult->getErrorCode() ?: "Unexpected error or sandbox";
                }
                $code = strval($error_code);

                $this->logError("CODE:" . $code, $logFile);

                
            }
        } catch (Exception $e) {
            $this->logError("Exception caught: " . $e->getMessage(), $logFile);
        }

        // Always respond OK at the end
        $this->respondOk();
    }

    private function respondOk()
    {
        if (ob_get_length()) {
            ob_end_clean();
        }

        http_response_code(200);
        header('Content-Type: text/plain');
        echo "OK";
        exit();
    }

    private function sandboxReceiptMonthly(string $email, array $transactionData)
    {
        // $logFile = __DIR__ . '/mail_error_log.txt';
        // Validate required transaction data
        if (empty($transactionData['transaction_id']) || empty($transactionData['status'])) {
            throw new Exception('Transaction data is incomplete');
        }

        // $debug_td = var_export($transactionData, true);

        $netRacuni = new NetRacun('net_racuni_staging_YgbuxF1Le0Y9KavjUnKoHeCGivlnXlCY4p5iHGju8480dec3');
        $invoice_model = new Invoices($this->dbAdapter);
        $item = $invoice_model->getMonthlyItem();
        $price = $item ? $item["price"] : null;
        $netRacuni->sandbox();

        // $this->logError($debug_td, $logFile);

        // OVDE
        $items = [
            "items" => [
                [
                    "name" => "Mesečna pretplata",
                    "taxLabels" => [
                        "Ж"
                    ],
                    "unit" => "KOM",
                    "quantity" => 1,
                    "price" => $price
                ]
            ]
        ];

        $result = $netRacuni->createInvoice($items);
        $invoiceUrl = $result->getInvoicePdfUrl();
        $invoice = $result->getInvoice();

        $array['invoice_url'] = $invoiceUrl;
        $array['invoice'] = $invoice;

        $mail = new PHPMailer(true);

        $rawReceipt = $invoice['journal'];

        // Normalize newlines (in case API uses \n or \r\n)
        $receiptFormatted = str_replace([
            "\r\n",
            "\r"
        ], "\n", $rawReceipt);

        // Insert <br> tags for HTML formatting
        $receiptHtml = nl2br($receiptFormatted);

        // Format transaction info for email
        $transactionInfo = "
        <div style='background-color: #e8f5e8; padding: 15px; border-left: 4px solid #28a745; margin-bottom: 20px;'>
            <h3 style='margin: 0 0 10px 0; color: #155724;'>✅ " . htmlspecialchars($transactionData['status']) . "</h3>
            <p><strong>ID Transakcije:</strong> " . htmlspecialchars($transactionData['transaction_id']) . "</p>
            <p><strong>Datum obrađivanja:</strong> " . htmlspecialchars($transactionData['processed_date']) . "</p>
            <p><strong>Iznos:</strong> {$transactionData['amount']} {$transactionData['currency']}</p>
            <p><strong>Način plaćanja:</strong> {$transactionData['payment_method']} ({$transactionData['card_type']})</p>
            <p><strong>Bank Code:</strong> {$transactionData['bank_code']}</p>
            <p><strong>Purchase ID:</strong> " . htmlspecialchars($transactionData['purchase_id']) . "</p>
        </div>
    ";

        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'ptrenersrb@gmail.com';
            $mail->Password = 'dlvw rdak ejtk yqlm';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            $mail->setFrom('ptrenersrb@gmail.com', 'Personalni Trener');
            $mail->addAddress($email);
            $mail->addCC('nikola.bojovic9@gmail.com');
            $mail->addCC('arsen.leontijevic@gmail.com');

            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Sandbox Invoice Monthly - ' . $transactionData['status'];
            $mail->Body = "
            <html>
              <head>
                <style>
                  body {
                    font-family: Arial, sans-serif;
                    background-color: #f5f5f5;
                    padding: 40px;
                    color: #333;
                  }
                  .container {
                    max-width: 600px;
                    margin: 0 auto;
                    background-color: #ffffff;
                    padding: 30px;
                    border-radius: 10px;
                    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
                  }
                  .receipt-box {
                    background-color: #fafafa;
                    border: 1px solid #ddd;
                    padding: 20px;
                    font-family: monospace;
                    white-space: pre-wrap;
                    border-radius: 6px;
                  }
                  .button {
                    display: inline-block;
                    margin-top: 20px;
                    padding: 12px 20px;
                    background-color: #211951;
                    color: #ffffff;
                    text-decoration: none;
                    border-radius: 6px;
                    font-weight: bold;
                  }
                  .button:hover {
                    background-color: #3b2c73;
                  }
                  .success-box {
                    background-color: #e8f5e8;
                    border-left: 4px solid #28a745;
                  }
                </style>
              </head>
              <body>
                <div class='container'>
                  <h2>Hvala na kupovini!</h2>
                  
                  $transactionInfo
                  
                  <p>Vaša potvrda uplate izgleda ovako:</p>
                  <div class='receipt-box'>$receiptHtml</div>
                  <a class='button' href='$invoiceUrl' target='_blank'>Preuzmi PDF fakturu</a>
                </div>
              </body>
            </html>
            ";
            $mail->AltBody = 'Hello! This is a test email with transaction confirmation.';

            $mail->send();
            echo 'Message has been sent';
        } catch (Exception $e) {
            echo "Message could not be sent. Error: {$mail->ErrorInfo}";
        }
    }

    private function sandboxReceiptYearly(string $email, array $transactionData)
    {
        $netRacuni = new NetRacun('net_racuni_staging_YgbuxF1Le0Y9KavjUnKoHeCGivlnXlCY4p5iHGju8480dec3');
        $invoice_model = new Invoices($this->dbAdapter);
        $item = $invoice_model->getYearlyItem();
        $price = $item ? $item["price"] : null;
        $netRacuni->sandbox();

        // NetRacunResponse
        $items = [
            "items" => [
                [
                    "name" => "Godišnja pretplata",
                    "taxLabels" => [
                        "Ж"
                    ],
                    "unit" => "KOM",
                    "quantity" => 1,
                    "price" => $price
                ]
            ]
        ];

        $result = $netRacuni->createInvoice($items);
        $invoiceUrl = $result->getInvoicePdfUrl();
        $invoice = $result->getInvoice();

        $array['invoice_url'] = $invoiceUrl;
        $array['invoice'] = $invoice;

        $rawReceipt = $invoice['journal'];

        $transactionInfo = "
        <div style='background-color: #e8f5e8; padding: 15px; border-left: 4px solid #28a745; margin-bottom: 20px;'>
            <h3 style='margin: 0 0 10px 0; color: #155724;'>✅ " . htmlspecialchars($transactionData['status']) . "</h3>
            <p><strong>ID Transakcije:</strong> " . htmlspecialchars($transactionData['transaction_id']) . "</p>
            <p><strong>Datum obrađivanja:</strong> " . htmlspecialchars($transactionData['processed_date']) . "</p>
            <p><strong>Iznos:</strong> {$transactionData['amount']} {$transactionData['currency']}</p>
            <p><strong>Način plaćanja:</strong> {$transactionData['payment_method']} ({$transactionData['card_type']})</p>
            <p><strong>Bank Code:</strong> {$transactionData['bank_code']}</p>
            <p><strong>Purchase ID:</strong> " . htmlspecialchars($transactionData['purchase_id']) . "</p>
        </div>
    ";

        // Normalize newlines (in case API uses \n or \r\n)
        $receiptFormatted = str_replace([
            "\r\n",
            "\r"
        ], "\n", $rawReceipt);

        // Insert <br> tags for HTML formatting
        $receiptHtml = nl2br($receiptFormatted);

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'ptrenersrb@gmail.com';
            $mail->Password = 'dlvw rdak ejtk yqlm';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            $mail->setFrom('ptrenersrb@gmail.com', 'Personalni Trener');
            $mail->addAddress($email);
            $mail->addCC('nikola.bojovic9@gmail.com');
            $mail->addCC('arsen.leontijevic@gmail.com');

            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Sandbox Invoice Yearly';
            $mail->Body = "
                <html>
              <head>
                <style>
                  body {
                    font-family: Arial, sans-serif;
                    background-color: #f5f5f5;
                    padding: 40px;
                    color: #333;
                  }
                  .container {
                    max-width: 600px;
                    margin: 0 auto;
                    background-color: #ffffff;
                    padding: 30px;
                    border-radius: 10px;
                    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
                  }
                  .receipt-box {
                    background-color: #fafafa;
                    border: 1px solid #ddd;
                    padding: 20px;
                    font-family: monospace;
                    white-space: pre-wrap;
                    border-radius: 6px;
                  }
                  .button {
                    display: inline-block;
                    margin-top: 20px;
                    padding: 12px 20px;
                    background-color: #211951;
                    color: #ffffff;
                    text-decoration: none;
                    border-radius: 6px;
                    font-weight: bold;
                  }
                  .button:hover {
                    background-color: #3b2c73;
                  }
                  .success-box {
                    background-color: #e8f5e8;
                    border-left: 4px solid #28a745;
                  }
                </style>
              </head>
              <body>
                <div class='container'>
                  <h2>Hvala na kupovini!</h2>
                  
                  $transactionInfo
                      <p>Vaša potvrda uplate izgleda ovako:</p>
                      <div class='receipt-box'>$receiptHtml</div>
                      <a class='button' href='$invoiceUrl' target='_blank'>Preuzmi PDF fakturu</a>
                    </div>
                  </body>
                </html>
                ";
            $mail->AltBody = 'Hello! This is a test email.';

            $mail->send();
            echo 'Message has been sent';
        } catch (Exception $e) {
            echo "Message could not be sent. Error: {$mail->ErrorInfo}";
        }
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
        $base64_string = str_replace(' ', '+', $base64_string);
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

    private function saveImageNew()
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
        $base64_string = str_replace(' ', '+', $base64_string);
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

        $image_url = "https://" . $_SERVER['HTTP_HOST'] . "/images/users/" . $file_name . ".png";

        var_dump($image_url);
        die();

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
            'device_token'
        ]);

        $model = new Users($this->dbAdapter);
        $user = $model->getUserById($this->user_id);

        // Delete device token from old accounts
        $old_users = $model->getByDeviceToken($request['device_token']);
        foreach ($old_users as $one) {
            if ($one["id"] != $user["id"]) {
                $one["device_token"] = "";
                $model->setDeviceToken($one['id'], $one['device_token']);
            }
        }

        $model->setDeviceToken($this->user_id, $request['device_token']);
        return $this->formatResponse(self::STATUS_SUCCESS, $this->returnUser($user));
    }

    /*
     * private function test(){
     *
     * $device_token = "dvqJzYkfRXKZsVsrqS6uiW:APA91bHUyvgSt9QYev8HcuIJ4NX8mVbSl2KvhD87q8NFAN5xWmEP6INPzWpYMyhxzXZ2P6sYw8uHSYZzopIS-xxQxpWNJFxoQpb1mUOfRZPvzP8PeEpeFAU";
     *
     * $dataPayload = [
     * 'type' => 'new_request',
     * 'date' => "",
     * 'time' => "",
     * 'user' => "Test Test"
     * ];
     *
     * $this->sendNotification("Novi zahtev", "Funkcija", $device_token, $dataPayload);
     *
     *
     * return $this->formatResponse(self::STATUS_SUCCESS, []);
     * }
     */
    private function testDateTime()
    {
        /**
         * Retrieves and formats the server's current date and time.
         *
         * @return array An array containing the status, message, and date/time data.
         */
        $currentTime = new DateTime(); // Get the current date and time

        $dateTimeData = [
            'date' => $currentTime->format('Y-m-d'), // Format as YYYY-MM-DD
            'time' => $currentTime->format('H:i:s'), // Format as HH:MM:SS
            'datetime' => $currentTime->format('Y-m-d H:i:s'), // Format as YYYY-MM-DD HH:MM:SS
            'timestamp' => $currentTime->getTimestamp(), // Unix timestamp
            'timezone' => $currentTime->getTimezone()->getName() // server timezone
        ];

        return $this->formatResponse(self::STATUS_SUCCESS, "", $dateTimeData);
    }

    private function testPing()
    {
        $netRacuni = new NetRacun('net_racuni_staging_YgbuxF1Le0Y9KavjUnKoHeCGivlnXlCY4p5iHGju8480dec3');
        $netRacuni->sandbox();

        $result = $netRacuni->ping();

        return $this->formatResponse(self::STATUS_SUCCESS, "", $result);
    }

    private function testTaxLabels()
    {
        $netRacuni = new NetRacun('net_racuni_e3gOhLmkSIeL5WtW18PGlkfZxwIfK2upy1HDvMNL378aaffe');
        // $netRacuni->sandbox();

        $result = $netRacuni->getTaxLabels();

        return $this->formatResponse(self::STATUS_SUCCESS, "", $result);
    }

    private function testInvoiceCheck()
    {
        $netRacuni = new NetRacun('net_racuni_staging_YgbuxF1Le0Y9KavjUnKoHeCGivlnXlCY4p5iHGju8480dec3');
        $netRacuni->sandbox();

        // NetRacunResponse
        $items = [
            "items" => [
                [
                    "name" => "Test Item",
                    "taxLabels" => [
                        "Ж"
                    ],
                    "unit" => "KOM",
                    "quantity" => 2,
                    "price" => 152.66
                ]
            ]
        ];

        $result = $netRacuni->createInvoice($items);
        $invoiceUrl = $result->getInvoicePdfUrl();
        $invoice = $result->getInvoice();

        $array['result'] = $result;
        $array['invoice_url'] = $invoiceUrl;
        $array['invoice'] = $invoice;

        var_dump($array);
        die();

        return $this->formatResponse(self::STATUS_SUCCESS, "", $array);
    }

    private function sendNotification(string $title, string $body, string $device_token, array $dataPayload = [], array $more_tokens = [])
    {
        $iosToken = $this->getIOSToken($device_token);
        if ($iosToken != false) {
            return $this->sendIOSPushNotification($iosToken, $title, $body, $dataPayload);
            // return;
        }

        $filePath = '/home/1301327.cloudwaysapps.com/xvvfqaxdrz/public_html/vendor/APISDK/personalni-trener-440e6-firebase-adminsdk-vjod3-044775a4e4.json';

        $client = new Client($filePath);
        $notification = new Notification();
        $notification->setNotification($title, $body);

        if (! empty($dataPayload)) {
            $notification->setDataPayload($dataPayload);
        }

        $allTokens = array_merge([
            $device_token
        ], $more_tokens);

        /*
         * var_dump($notification);
         * var_dump($client);
         * die();
         */

        foreach ($allTokens as $token) {
            $recipient = new Recipient();
            $recipient->setSingleRecipient($token);

            $client->build($recipient, $notification);
            $client->fire();
        }

        return $this->formatResponse(self::STATUS_SUCCESS, "Android", []);

        /*
         * $client = new Client($filePath);
         *
         * $recipient = new Recipient();
         * $notification = new Notification();
         *
         * $recipient->setSingleRecipient($device_token);
         *
         *
         * $notification->setNotification($title, $body);
         *
         * if (! empty($dataPayload)) {
         * $notification->setDataPayload($dataPayload);
         * }
         *
         * $client->build($recipient, $notification);
         * $client->fire();
         */
    }

    function getIOSToken($string)
    {
        // Provjeri jesu li prva 4 karaktera "ios_"
        if (substr($string, 0, 4) === "ios_") {
            // Izvuci ostatak stringa nakon "ios_"
            return substr($string, 4);
        } else {
            // Ako nije "ios_", vrati null ili originalni string, ovisno o potrebi
            return false;
        }
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
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'ptrenersrb@gmail.com';
            $mail->Password = 'dlvw rdak ejtk yqlm';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            // Recipients
            $mail->setFrom('ptrenersrb@gmail.com', 'Personalni Trener');
            $mail->addAddress('nikola.bojovic9@gmail.com');
            $mail->addCC('arsen.leontijevic@gmail.com');

            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Test email from app';
            $mail->Body = '<b>Hello! This is a test email.</b>';
            $mail->AltBody = 'Hello! This is a test email.';

            $mail->send();
            echo 'Message has been sent';
        } catch (Exception $e) {
            echo "Message could not be sent. Error: {$mail->ErrorInfo}";
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

    function generateJwtToken()
    {
        $teamId = 'L546VT8SZ5'; // Zamijeni s tvojim Team ID-om
        $keyId = 'MCFC3D7UC8'; // Zamijeni s tvojim Key ID-om
        $p8FilePath = __DIR__ . DIRECTORY_SEPARATOR . 'AuthKey_MCFC3D7UC8.p8';
        try {
            // Učitaj privatni ključ iz .p8 datoteke
            $privateKeyContent = file_get_contents($p8FilePath);
            if ($privateKeyContent === false) {
                throw new Exception("Ne mogu učitati .p8 datoteku iz putanje: $p8FilePath");
            }

            // Parsiraj privatni ključ
            $privateKey = openssl_pkey_get_private($privateKeyContent);
            if ($privateKey === false) {
                throw new Exception("Ne mogu parsirati privatni ključ: " . openssl_error_string());
            }

            $issuedAt = time();
            $expirationTime = $issuedAt + 315360000;

            // Pripremi payload za JWT
            $payload = [
                'iss' => $teamId, // Issuer (Team ID)
                'iat' => $issuedAt, // Issued At (trenutno vrijeme)
                'exp' => $expirationTime // Istječe za 1 god otp
            ];

            // Pripremi header za JWT
            $header = [
                'alg' => 'ES256', // Algoritam za potpisivanje (Apple zahtijeva ES256)
                'kid' => $keyId // Key ID
            ];

            // Generiraj JWT token
            $jwtToken = JWT::encode($payload, $privateKey, 'ES256', $keyId, $header);

            // Oslobodi resurse
            openssl_free_key($privateKey);
            // echo $jwtToken;
            return $jwtToken;
        } catch (Exception $e) {
            error_log("Greška pri generiranju JWT tokena: " . $e->getMessage());
            return null;
        }
    }

    private function sendIOSPush()
    {
        $request = $this->filterParams([
            'device_token'
        ]);
        return $this->sendIOSPushNotification($request["device_token"], "Gym Trainer", "Trening je zakazan za sutra u 10h");
    }

    private function sendIOSPushNotification($deviceToken, $title, $body, $dataPayload = [])
    {

        // return $this->formatResponse(self::STATUS_FAILED, "Greška pri slanju push notifikacije: " . $deviceToken . "", []);
        $bundleId = 'com.sei.PTrainer'; // Zamijeni s Bundle ID-om tvoje aplikacije
        $apnsUrl = 'https://api.push.apple.com:443/3/device/' . $deviceToken; // Koristi api.push.apple.com za produkciju
        $jwtToken = $this->generateJwtToken();

        if (is_null($jwtToken)) {
            return $this->formatResponse(self::STATUS_FAILED, "Failed generating JWT", []);
        }

        // Payload za push notifikaciju
        $payload = json_encode([
            'aps' => [
                'alert' => [
                    'title' => $title,
                    'body' => $body
                ],
                'sound' => 'default',
                'badge' => 1
            ],
            'type' => $dataPayload['type']
        ]);

        // Slanje push notifikacije pomoću curl
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apnsUrl);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "apns-topic: $bundleId",
            "apns-push-type: alert",
            "authorization: bearer $jwtToken",
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_2_0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_VERBOSE, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);
        if ($response === false) {
            return $this->formatResponse(self::STATUS_FAILED, "Greška pri slanju push notifikacije: " . curl_error($ch) . "", []);
        } else {
            return $this->formatResponse(self::STATUS_SUCCESS, "Push notifikacija poslana. HTTP kod: " . $httpCode . ". Odgovor: {$response}, Device Token: {$deviceToken}", $dataPayload);
        }
    }

    private function logError($message, $logFile)
    {
        $timestamp = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        file_put_contents($logFile, "[{$timestamp}] ERROR: {$message}\n", FILE_APPEND);
    }

    public function testPaymentInit()
    {
        // Simulate $_POST or filterParams equivalent
        $_POST = [
            'id' => '9999',
            'token' => 'dummy-token', // Use a valid one if needed for tokenized payments
            'name' => 'Test',
            'surname' => 'User',
            'email' => 'testuser@example.com',
            'is_monthly' => '1'
        ];

        try {
            // Call your initPayment method
            $response = $this->initPayment();

            // Dump the response for inspection
            echo "<pre>";
            print_r($response);
            echo "</pre>";
        } catch (Exception $e) {
            echo "Exception during test: " . $e->getMessage();
        }
    }

    function getBaseUrl(): string
    {
        $protocol = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $script = $_SERVER['SCRIPT_NAME'] ?? ''; // e.g. /index.php or /api.php
        $path = rtrim(dirname($script), '/\\'); // removes file part

        return $protocol . '://' . $host . $path;
    }

    private function getForgotPassLanguageMail(string $lang, string $generated_pass)
    {
        $forgotpassmail = [
            'en' => "
                    <html>
                        <head>
                            <style>
                                .container {
                                    font-family: Arial, sans-serif;
                                    padding: 20px;
                                    background-color: #f9f9f9;
                                    border-radius: 10px;
                                    color: #333;
                                }
                                .password {
                                    font-size: 18px;
                                    font-weight: bold;
                                    color: #211951;
                                }
                                .footer {
                                    margin-top: 30px;
                                    font-size: 12px;
                                    color: #777;
                                }
                            </style>
                        </head>
                        <body>
                            <div class='container'>
                                <h2>Your New Password</h2>
                                <p>Your password reset request has been successfully processed.</p>
                                <p>Your new password is:</p>
                                <p class='password'>{$generated_pass}</p>
                                <p>We recommend that you change it immediately in the app.</p>
                                <p class='footer'>Thank you for using the Personal Trainer app.</p>
                            </div>
                        </body>
                    </html>
                    ",
            'ru' => "
                        <html>
                            <head>
                                <style>
                                    .container {
                                        font-family: Arial, sans-serif;
                                        padding: 20px;
                                        background-color: #f9f9f9;
                                        border-radius: 10px;
                                        color: #333;
                                    }
                                    .password {
                                        font-size: 18px;
                                        font-weight: bold;
                                        color: #211951;
                                    }
                                    .footer {
                                        margin-top: 30px;
                                        font-size: 12px;
                                        color: #777;
                                    }
                                </style>
                            </head>
                            <body>
                                <div class='container'>
                                    <h2>Ваш новый пароль</h2>
                                    <p>Запрос на сброс пароля был успешно обработан.</p>
                                    <p>Ваш новый пароль:</p>
                                    <p class='password'>{$generated_pass}</p>
                                    <p>Рекомендуем сразу изменить его в приложении.</p>
                                    <p class='footer'>Спасибо за использование приложения Персональный Тренер.</p>
                                </div>
                            </body>
                        </html>
                        ",
            'sr' => "
                        <html>
                            <head>
                                <style>
                                    .container {
                                        font-family: Arial, sans-serif;
                                        padding: 20px;
                                        background-color: #f9f9f9;
                                        border-radius: 10px;
                                        color: #333;
                                    }
                                    .password {
                                        font-size: 18px;
                                        font-weight: bold;
                                        color: #211951;
                                    }
                                    .footer {
                                        margin-top: 30px;
                                        font-size: 12px;
                                        color: #777;
                                    }
                                </style>
                            </head>
                            <body>
                                <div class='container'>
                                    <h2>Vaša nova lozinka</h2>
                                    <p>Zahtev za resetovanje lozinke je uspešno obrađen.</p>
                                    <p>Vaša nova lozinka je:</p>
                                    <p class='password'>{$generated_pass}</p>
                                    <p>Preporučujemo da je odmah promenite u aplikaciji.</p>
                                    <p class='footer'>Hvala što koristite aplikaciju Personalni Trener.</p>
                                </div>
                            </body>
                        </html>
                    "
        ];

        if ($lang == "en")
            return $forgotpassmail['en'];
        else if ($lang == "sr")
            return $forgotpassmail['sr'];
        else if ($lang == "ru")
            return $forgotpassmail['ru'];
        else
            return $forgotpassmail['en'];
    }

    private function getForgotPassEcho(string $lang)
    {
        $languageReturn = [
            "sr" => "
                <html>
                    <head>
                        <title>Reset uspešan</title>
                        <script>
                            setTimeout(function() {
                                window.close();
                            }, 4000); // Close after 2 seconds
                        </script>
                        <style>
                            body {
                                font-family: Arial, sans-serif;
                                background-color: #f5f5f5;
                                text-align: center;
                                padding-top: 100px;
                                color: #333;
                            }
                            .message-box {
                                display: inline-block;
                                padding: 20px;
                                background-color: #fff;
                                border: 1px solid #ddd;
                                border-radius: 10px;
                                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
                            }
                        </style>
                    </head>
                    <body>
                        <div class='message-box'>
                            <h2>Lozinka je uspešno resetovana</h2>
                            <p>Možete zatvoriti ovu stranicu.</p>
                        </div>
                    </body>
                </html>
                ",
            "en" => "<html>
                <head>
                    <title>Reset Successful</title>
                    <script>
                        setTimeout(function() {
                            window.close();
                        }, 4000); // Close after 4 seconds
                    </script>
                    <style>
                        body {
                            font-family: Arial, sans-serif;
                            background-color: #f5f5f5;
                            text-align: center;
                            padding-top: 100px;
                            color: #333;
                        }
                        .message-box {
                            display: inline-block;
                            padding: 20px;
                            background-color: #fff;
                            border: 1px solid #ddd;
                            border-radius: 10px;
                            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
                        }
                    </style>
                </head>
                <body>
                    <div class='message-box'>
                        <h2>Password has been successfully reset</h2>
                        <p>You may close this page.</p>
                    </div>
                </body>
            </html>",
            "ru" => "<html>
                    <head>
                        <title>Сброс выполнен успешно</title>
                        <script>
                            setTimeout(function() {
                                window.close();
                            }, 4000); // Закрыть через 4 секунды
                        </script>
                        <style>
                            body {
                                font-family: Arial, sans-serif;
                                background-color: #f5f5f5;
                                text-align: center;
                                padding-top: 100px;
                                color: #333;
                            }
                            .message-box {
                                display: inline-block;
                                padding: 20px;
                                background-color: #fff;
                                border: 1px solid #ddd;
                                border-radius: 10px;
                                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
                            }
                        </style>
                    </head>
                    <body>
                        <div class='message-box'>
                            <h2>Пароль был успешно сброшен</h2>
                            <p>Вы можете закрыть эту страницу.</p>
                        </div>
                    </body>
                </html>"
        ];

        if ($lang == "en")
            return $languageReturn['en'];
        else if ($lang == "sr")
            return $languageReturn['sr'];
        else if ($lang == "ru")
            return $languageReturn['ru'];
        else
            return $languageReturn['en'];
    }

    private function getPasswordCheckMail(string $lang, string $generated_link)
    {
        $languageReturn = [
            "en" => "<html>
                        <head>
                            <style>
                                .container {
                                    font-family: Arial, sans-serif;
                                    padding: 20px;
                                    background-color: #f9f9f9;
                                    border-radius: 10px;
                                    color: #333;
                                }
                                .button {
                                    display: inline-block;
                                    padding: 10px 20px;
                                    margin-top: 20px;
                                    font-size: 16px;
                                    color: white;
                                    background-color: #211951;
                                    text-decoration: none;
                                    border-radius: 5px;
                                }
                                .footer {
                                    margin-top: 30px;
                                    font-size: 12px;
                                    color: #777;
                                }
                            </style>
                        </head>
                        <body>
                            <div class='container'>
                                <h2>Password Reset Request</h2>
                                <p>We received a request to reset the password for your account.</p>
                                <p>If you requested a new password, click the button below to continue:</p>
                                <a href='{$generated_link}' class='button'>Reset Password</a>
                                <p class='footer'>If you didn’t make this request, feel free to ignore this message.</p>
                            </div>
                        </body>
                    </html>",
            "sr" => "
                            <html>
                                <head>
                                    <style>
                                        .container {
                                            font-family: Arial, sans-serif;
                                            padding: 20px;
                                            background-color: #f9f9f9;
                                            border-radius: 10px;
                                            color: #333;
                                        }
                                        .button {
                                            display: inline-block;
                                            padding: 10px 20px;
                                            margin-top: 20px;
                                            font-size: 16px;
                                            color: white;
                                            background-color: #211951;
                                            text-decoration: none;
                                            border-radius: 5px;
                                        }
                                        .footer {
                                            margin-top: 30px;
                                            font-size: 12px;
                                            color: #777;
                                        }
                                    </style>
                                </head>
                                <body>
                                    <div class='container'>
                                        <h2>Zahtev za promenu lozinke</h2>
                                        <p>Dobili smo zahtev za resetovanje lozinke vašeg naloga.</p>
                                        <p>Ako ste vi zatražili novu lozinku, kliknite na dugme ispod da nastavite:</p>
                                        <a href='{$generated_link}' class='button'>Promeni lozinku</a>
                                        <p class='footer'>Ako niste Vi podneli zahtev, slobodno ignorišite ovu poruku.</p>
                                    </div>
                                </body>
                            </html>
                        ",
            "ru" => "<html>
                        <head>
                            <style>
                                .container {
                                    font-family: Arial, sans-serif;
                                    padding: 20px;
                                    background-color: #f9f9f9;
                                    border-radius: 10px;
                                    color: #333;
                                }
                                .button {
                                    display: inline-block;
                                    padding: 10px 20px;
                                    margin-top: 20px;
                                    font-size: 16px;
                                    color: white;
                                    background-color: #211951;
                                    text-decoration: none;
                                    border-radius: 5px;
                                }
                                .footer {
                                    margin-top: 30px;
                                    font-size: 12px;
                                    color: #777;
                                }
                            </style>
                        </head>
                        <body>
                            <div class='container'>
                                <h2>Запрос на изменение пароля</h2>
                                <p>Мы получили запрос на сброс пароля для вашего аккаунта.</p>
                                <p>Если вы действительно запрашивали новый пароль, нажмите кнопку ниже, чтобы продолжить:</p>
                                <a href='{$generated_link}' class='button'>Сбросить пароль</a>
                                <p class='footer'>Если вы не отправляли этот запрос, просто проигнорируйте это сообщение.</p>
                            </div>
                        </body>
                    </html>"
        ];

        if ($lang == "en")
            return $languageReturn['en'];
        else if ($lang == "sr")
            return $languageReturn['sr'];
        else if ($lang == "ru")
            return $languageReturn['ru'];
        else
            return $languageReturn['en'];
    }

    private function getRegisterMail(string $lang, string $hash)
    {
        $languageReturn = [
            "en" => '
                    <html>
                      <body style="font-family: Arial, sans-serif; background-color: #f4f4f4; padding: 20px;">
                        <div style="max-width: 600px; margin: auto; background-color: #ffffff; padding: 30px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1);">
                          <h2 style="color: #211951;">Account Confirmation</h2>
                          <p>Thank you for registering! To activate your account, please click the link below:</p>
                          <p style="margin: 30px 0;">
                            <a href="https://phpstack-1301327-4732761.cloudwaysapps.com/log/activate/' . $hash . '"
                               style="display: inline-block; padding: 12px 24px; background-color: #211951; color: #ffffff; text-decoration: none; border-radius: 5px;">
                              Activate Account
                            </a>
                          </p>
                          <p>If you did not request this registration, you can safely ignore this message.</p>
                          <br>
                          <p style="font-size: 12px; color: #888;">Personal Trainer Team</p>
                        </div>
                      </body>
                    </html>',
            "sr" => '
                    <html>
                      <body style="font-family: Arial, sans-serif; background-color: #f4f4f4; padding: 20px;">
                        <div style="max-width: 600px; margin: auto; background-color: #ffffff; padding: 30px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1);">
                          <h2 style="color: #211951;">Potvrda naloga</h2>
                          <p>Hvala što ste se registrovali! Da biste aktivirali svoj nalog, molimo kliknite na sledeći link:</p>
                          <p style="margin: 30px 0;">
                            <a href="https://phpstack-1301327-4732761.cloudwaysapps.com/log/activate/' . $hash . '"
                               style="display: inline-block; padding: 12px 24px; background-color: #211951; color: #ffffff; text-decoration: none; border-radius: 5px;">
                              Aktiviraj nalog
                            </a>
                          </p>
                          <p>Ako niste vi zatražili registraciju, slobodno ignorišite ovu poruku.</p>
                          <br>
                          <p style="font-size: 12px; color: #888;">Personalni Trener Tim</p>
                        </div>
                      </body>
                    </html>',
            "ru" => '
                    <html>
                      <body style="font-family: Arial, sans-serif; background-color: #f4f4f4; padding: 20px;">
                        <div style="max-width: 600px; margin: auto; background-color: #ffffff; padding: 30px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1);">
                          <h2 style="color: #211951;">Подтверждение аккаунта</h2>
                          <p>Спасибо за регистрацию! Чтобы активировать ваш аккаунт, пожалуйста, нажмите на ссылку ниже:</p>
                          <p style="margin: 30px 0;">
                            <a href="https://phpstack-1301327-4732761.cloudwaysapps.com/log/activate/' . $hash . '"
                               style="display: inline-block; padding: 12px 24px; background-color: #211951; color: #ffffff; text-decoration: none; border-radius: 5px;">
                              Активировать аккаунт
                            </a>
                          </p>
                          <p>Если вы не отправляли этот запрос, просто проигнорируйте это сообщение.</p>
                          <br>
                          <p style="font-size: 12px; color: #888;">Команда Personalni Trener</p>
                        </div>
                      </body>
                    </html>'
        ];

        if ($lang == "en")
            return $languageReturn['en'];
        else if ($lang == "sr")
            return $languageReturn['sr'];
        else if ($lang == "ru")
            return $languageReturn['ru'];
        else
            return $languageReturn['en'];
    }

    private function getTransactionRejectedMail(string $lang, string $error_code)
    {
        // Error code messages in all three languages
        $errorMessages = [
            "sr" => [
                "General Errors & Validation Errors" => "",
                "1000" => "Tehnički problem prilikom slanja zahteva. Molimo pozovite trgovca.",
                "1001" => "Nestandardan odgovor banke. Molimo pozovite trgovca.",
                "1002" => "Tehnički problem prilikom slanja zahteva. Molimo pozovite trgovca.",
                "1003" => "Tehnički problem prilikom slanja zahteva. Molimo pozovite trgovca.",
                "1004" => "Tehnički problem prilikom slanja zahteva. Molimo pozovite trgovca.",
                "1005" => "Tehnički problem prilikom slanja zahteva. Molimo pozovite trgovca.",
                "1006" => "Tehnički problem prilikom slanja zahteva. Molimo pozovite trgovca.",
                "1007" => "Tehnički problem prilikom slanja zahteva. Molimo pozovite trgovca.",
                "1008" => "Tehnički problem prilikom slanja zahteva. Molimo pozovite trgovca.",
                "9999" => "Tehnički problem prilikom slanja zahteva. Molimo pozovite trgovca.",
                "Payment Errors" => "",
                "2001" => "Kartica koju ste uneli ne može biti prihvacena. Molimo pokušajte drugom karticom.",
                "2002" => "Transakcija je otkazana od strane kupca.",
                "2003" => "Vaša transakcija nije odobrena. Pokušajte ponovo ili promenite karticu.",
                "2004" => "Kartica koju ste uneli je premašila limit. Molimo izaberite drugu karticu.",
                "2005" => "Korisnik nije u predviđenom roku završio transakciju",
                "2006" => "Kartica koju ste uneli je premašila limit. Molimo izaberite drugu karticu.",
                "2007" => "Uneti podaci o platnoj kartici nisu tacni. Proverite podatke i pokušajte ponovo.",
                "2008" => "Kartica koju ste uneli ne može biti prihvacena. Molimo pokušajte drugom karticom.",
                "2009" => "Kartica koju ste uneli je istekla. Molimo izaberite drugu karticu.",
                "2010" => "Vaša transakcija nije odobrena. Molimo izaberite drugu karticu ili proverite sa bankom koja vam je izdala karticu.",
                "2011" => "Kartica koju ste uneli ne može biti prihvacena. Molimo pokušajte drugom karticom.",
                "2012" => "Transakcija je otkazana.",
                "2013" => "Vaša transakcija nije odobrena. Molimo izaberite drugu karticu ili proverite sa bankom koja vam je izdala karticu.",
                "2014" => "Vaša transakcija nije odobrena. Molimo izaberite drugu karticu ili proverite sa bankom koja vam je izdala karticu.",
                "2015" => "Vaša transakcija nije odobrena. Molimo izaberite drugu karticu ili proverite sa bankom koja vam je izdala karticu.",
                "2016" => "Vaša transakcija nije odobrena. Molimo izaberite drugu karticu ili proverite sa bankom koja vam je izdala karticu.",
                "2017" => "IBAN nije validan. Proverite podatke i pokušajte ponovo.",
                "2018" => "BIC nije validan. Proverite podatke i pokušajte ponovo.",
                "2019" => "Podaci o korisniku nisu validni. Proverite podatke i pokušajte ponovo.",
                "2020" => "CVV kod je obavezan. Proverite podatke i pokušajte ponovo",
                "2021" => "Vaša 3D Secure autentifikacija nije uspela. Molimo pozovite banku koja vam je izdala karticu ili koristite karticu koja nije 3D-Secure.",
                "Network Errors" => "",
                "3001" => "Transakcija je istekla. Napravite pauzu i pokušajte nešto kasnije.",
                "3002" => "Transakcija nije dozvoljena.",
                "3003" => "Sistem privremeno nedostupan. Napravite pauzu i pokušajte nešto kasnije.",
                "3004" => "ID Transakcije mora već korišćen.",
                "3005" => "Transakcija nije odobrena usled problema u komunikaciji sa bankom. Pokušajte ponovo nešto kasnije.",
                "Schedule API Errors" => "",
                "7001" => "Zahtev za planiranjem transakcije nije validan.",
                "7002" => "Zahtev za planiranjem transakcije nije prihvaćen.",
                "7005" => "Planirana transakcija nije validna.",
                "7010" => "RegistrationId je neophodan.",
                "7020" => "RegistrationId nije validan.",
                "7030" => "RegistrationId nema odgovarajuću referencu.",
                "7035" => "Inicijalna transakcija kojom započinju planirana ponavljajuća plaćanja mora biti \"register\", \"debit+register\" ili \"preuth+register\".",
                "7036" => "Period između inicijalne i sledeće transakcije mora biti duži od 24 sata.",
                "7040" => "ScheduleId (id planirane transakcije) nije validan ili nije povezan na ovaj kanal plaćanja.",
                "7050" => "Planirana početna transakcija nije validnog formata ili je starija od 24h.",
                "7060" => "Planirana naredna transakcija  nije validnog formata ili je starija od 24h.",
                "7070" => "Status planirane transakcije nije validnog formata za zahtevanu operaciju.",
            ],
            "en" => [
                "General Errors & Validation Errors" => "",
                "1000" => "CONFIG ERROR. Some fundamental error in your request.",
                "1001" => "CONFIG ERROR. The upstream system responded with an unknown response.",
                "1002" => "CONFIG ERROR. Request data are malformed or missing.",
                "1003" => "CONFIG ERROR. Transaction could not be processed.",
                "1004" => "CONFIG ERROR. The request signature you provided was wrong.",
                "1005" => "CONFIG ERROR. The XML you provided was malformed or invalid.",
                "1006" => "CONFIG ERROR. Preconditions failed, e.g. capture on a failed authorize.",
                "1007" => "CONFIG ERROR. Something is wrong your configuration, please contact your integration engineer.",
                "1008" => "CONFIG ERROR. Unexpected system error.",
                "9999" => "We received an error which is not (yet) mapped to a better error code.",
                "Payment Errors" => "",
                "2001" => "The customer cancelled permission for his payment instrument externally. Please try with another card.",
                "2002" => "Transaction was cancelled by customer.",
                "2003" => "Transaction declined by the bank. Please try with another card.",
                "2004" => "Some limit reached. Please try with another card.",
                "2005" => "Transaction expired. Customer took to long to submit his payment info.",
                "2006" => "Insufficient funds/Card limit reached. Please try with another card.",
                "2007" => "Incorrect payment info. Please check and try again.",
                "2008" => "Invalid card. Please try with another card.",
                "2009" => "Expired card. Please try with another card.",
                "2010" => "Your card cannot be accepted. Please try with another card or contact your issuing bank.",
                "2011" => "Your card cannot be accepted. Please try with another card or contact your issuing bank.",
                "2012" => "Transaction cancelled.",
                "2013" => "Your card cannot be accepted. Please try with another card or contact your issuing bank.",
                "2014" => "Your card cannot be accepted. Please try with another card or contact your issuing bank.",
                "2015" => "Your card cannot be accepted. Please try with another card or contact your issuing bank.",
                "2016" => "Your card cannot be accepted. Please try with another card or contact your issuing bank.",
                "2017" => "IBAN not valid. Please check and try again.",
                "2018" => "BIC invalid. Please check and try again.",
                "2019" => "Customer data invalid. Please check and try again.",
                "2020" => "CVV required. Please check and try again.",
                "2021" => "3D-Secure Verification failed. Please with non 3D-Secure card or contact your issuing bank.",
                "Network Errors" => "",
                "3001" => "COMMUNICATION ERROR: Timeout. Please try again later.",
                "3002" => "COMMUNICATION ERROR: Transaction not Allowed.",
                "3003" => "COMMUNICATION ERROR: System temporary unavailable. Please try again later.",
                "3004" => "Duplicate transaction ID.",
                "3005" => "COMMUNICATION ERROR: Please try again later.",
                "Schedule API Errors" => "",
                "7001" => "Schedule request is invalid.",
                "7002" => "Schedule request failed.",
                "7005" => "ScheduleAction is not valid.",
                "7010" => "RegistrationId is required.",
                "7020" => "RegistrationId is not valid.",
                "7030" => "Reference transaction not a register.The registrationId must point to a register or a debit-with-register or a preauthorize-with-register.",
                "7035" => "Initial transaction is not a register.The transaction for starting a schedule must be a register, a debit-with-register or a preauthorize-with-register.",
                "7036" => "Invalid initial period. The period between the initial and second transaction must be greater than 24 hours.",
                "7040" => "The scheduleId is not valid or does not match to the connector.",
                "7050" => "The startDateTime is invalid or older than 24 hours.",
                "7060" => "The continueDateTime is invalid or older than 24 hours.",
                "7070" => "The status of the schedule is not valid for the requested operation.",
            ],
            "ru" => [
                "General Errors & Validation Errors" => "",
                "1000" => "ОШИБКА КОНФИГУРАЦИИ. Произошла фундаментальная ошибка в вашем запросе.",
                "1001" => "ОШИБКА КОНФИГУРАЦИИ. Система ответила неизвестным ответом.",
                "1002" => "ОШИБКА КОНФИГУРАЦИИ. Данные запроса повреждены или отсутствуют.",
                "1003" => "ОШИБКА КОНФИГУРАЦИИ. Транзакция не может быть обработана.",
                "1004" => "ОШИБКА КОНФИГУРАЦИИ. Неверная подпись запроса.",
                "1005" => "ОШИБКА КОНФИГУРАЦИИ. Некорректный или повреждённый XML.",
                "1006" => "ОШИБКА КОНФИГУРАЦИИ. Нарушены предварительные условия (например, попытка захвата при неудачной авторизации).",
                "1007" => "ОШИБКА КОНФИГУРАЦИИ. Проблема в вашей конфигурации. Свяжитесь с инженером интеграции.",
                "1008" => "ОШИБКА КОНФИГУРАЦИИ. Неожиданная системная ошибка.",
                "9999" => "Получена ошибка, которая пока не сопоставлена с известным кодом ошибки.",
                "Payment Errors" => "",
                "2001" => "Клиент отменил разрешение на использование платёжного инструмента. Попробуйте другую карту.",
                "2002" => "Транзакция отменена клиентом.",
                "2003" => "Транзакция отклонена банком. Попробуйте другую карту.",
                "2004" => "Превышен лимит. Попробуйте другую карту.",
                "2005" => "Истекло время выполнения транзакции. Клиент слишком долго вводил платёжные данные.",
                "2006" => "Недостаточно средств или превышен лимит карты. Попробуйте другую карту.",
                "2007" => "Некорректные платёжные данные. Проверьте и повторите попытку.",
                "2008" => "Неверная карта. Попробуйте другую карту.",
                "2009" => "Срок действия карты истёк. Попробуйте другую карту.",
                "2010" => "Ваша карта не может быть принята. Попробуйте другую карту или свяжитесь с банком-эмитентом.",
                "2011" => "Ваша карта не может быть принята. Попробуйте другую карту или свяжитесь с банком-эмитентом.",
                "2012" => "Транзакция отменена.",
                "2013" => "Ваша карта не может быть принята. Попробуйте другую карту или свяжитесь с банком-эмитентом.",
                "2014" => "Ваша карта не может быть принята. Попробуйте другую карту или свяжитесь с банком-эмитентом.",
                "2015" => "Ваша карта не может быть принята. Попробуйте другую карту или свяжитесь с банком-эмитентом.",
                "2016" => "Ваша карта не может быть принята. Попробуйте другую карту или свяжитесь с банком-эмитентом.",
                "2017" => "Неверный IBAN. Проверьте и повторите попытку.",
                "2018" => "Неверный BIC. Проверьте и повторите попытку.",
                "2019" => "Некорректные данные клиента. Проверьте и повторите попытку.",
                "2020" => "CVV обязателен. Проверьте и повторите попытку.",
                "2021" => "Ошибка 3D-Secure аутентификации. Используйте карту без 3D-Secure или обратитесь в ваш банк.",
                "Network Errors" => "",
                "3001" => "ОШИБКА СВЯЗИ: Тайм-аут. Попробуйте позже.",
                "3002" => "ОШИБКА СВЯЗИ: Транзакция не разрешена.",
                "3003" => "ОШИБКА СВЯЗИ: Система временно недоступна. Попробуйте позже.",
                "3004" => "Дубликат идентификатора транзакции.",
                "3005" => "ОШИБКА СВЯЗИ: Попробуйте позже.",
                "Schedule API Errors" => "",
                "7001" => "Неверный запрос расписания.",
                "7002" => "Ошибка при обработке запроса расписания.",
                "7005" => "Недопустимое действие расписания.",
                "7010" => "Необходимо указать RegistrationId.",
                "7020" => "Недопустимый RegistrationId.",
                "7030" => "Референсная транзакция не является регистрацией. RegistrationId должен ссылаться на 'register', 'debit-with-register' или 'preauthorize-with-register'.",
                "7035" => "Начальная транзакция не является регистрацией. Для начала расписания должна использоваться транзакция типа 'register', 'debit-with-register' или 'preauthorize-with-register'.",
                "7036" => "Неверный начальный период. Интервал между первой и второй транзакцией должен быть более 24 часов.",
                "7040" => "Неверный scheduleId или он не связан с данным платёжным каналом.",
                "7050" => "Недопустимое время начала или оно старше 24 часов.",
                "7060" => "Недопустимое время следующей транзакции или оно старше 24 часов.",
                "7070" => "Недопустимый статус расписания для запрошенной операции.",
                ],
                ];

        // Fallback in case of unknown error code
        $message_sr = $errorMessages['sr'][$error_code] ?? 'Došlo je do greške prilikom obrade transakcije.';
        $message_en = $errorMessages['en'][$error_code] ?? 'An error occurred while processing your transaction.';
        $message_ru = $errorMessages['ru'][$error_code] ?? 'Произошла ошибка при обработке транзакции.';

        $mails = [
            'sr' => "
            <html>
                <head>
                    <style>
                        .container {
                            font-family: Arial, sans-serif;
                            padding: 20px;
                            background-color: #f9f9f9;
                            border-radius: 10px;
                            color: #333;
                        }
                        .code {
                            font-size: 16px;
                            font-weight: bold;
                            color: #211951;
                        }
                        .footer {
                            margin-top: 30px;
                            font-size: 12px;
                            color: #777;
                        }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <h2>Transakcija odbijena</h2>
                        <p>Kod greške: <span class='code'>{$error_code}</span></p>
                        <p>Poruka: {$message_sr}</p>
                        <p>Žao nam je zbog neprijatnosti. Molimo pokušajte ponovo ili kontaktirajte svoju banku.</p>
                        <p class='footer'>Hvala što koristite aplikaciju Personalni Trener.</p>
                    </div>
                </body>
            </html>
        ",
            'en' => "
            <html>
                <head>
                    <style>
                        .container {
                            font-family: Arial, sans-serif;
                            padding: 20px;
                            background-color: #f9f9f9;
                            border-radius: 10px;
                            color: #333;
                        }
                        .code {
                            font-size: 16px;
                            font-weight: bold;
                            color: #211951;
                        }
                        .footer {
                            margin-top: 30px;
                            font-size: 12px;
                            color: #777;
                        }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <h2>Transaction Rejected</h2>
                        <p>Error Code: <span class='code'>{$error_code}</span></p>
                        <p>Message: {$message_en}</p>
                        <p>Sorry for the inconvenience. Please try again or contact your bank.</p>
                        <p class='footer'>Thank you for using the Personal Trainer app.</p>
                    </div>
                </body>
            </html>
        ",
            'ru' => "
            <html>
                <head>
                    <style>
                        .container {
                            font-family: Arial, sans-serif;
                            padding: 20px;
                            background-color: #f9f9f9;
                            border-radius: 10px;
                            color: #333;
                        }
                        .code {
                            font-size: 16px;
                            font-weight: bold;
                            color: #211951;
                        }
                        .footer {
                            margin-top: 30px;
                            font-size: 12px;
                            color: #777;
                        }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <h2>Транзакция отклонена</h2>
                        <p>Код ошибки: <span class='code'>{$error_code}</span></p>
                        <p>Сообщение: {$message_ru}</p>
                        <p>Извините за неудобства. Пожалуйста, попробуйте еще раз или свяжитесь со своим банком.</p>
                        <p class='footer'>Спасибо за использование приложения Персональный Тренер.</p>
                    </div>
                </body>
            </html>
        "
        ];

        if ($lang == "sr")
            return $mails['sr'];
        else if ($lang == "ru")
            return $mails['ru'];
        else
            return $mails['en'];
    }
    
    private function getTranslatedMessage($key, $language, $params = []) {
        // Default to English if language is not supported
        $lang = in_array($language, ['en', 'ru', 'sr']) ? $language : 'en';
        
        // Get the message template
        $message = $this->translations[$lang][$key] ?? $this->translations['en'][$key];
        
        // Replace placeholders with actual values
        foreach ($params as $placeholder => $value) {
            $message = str_replace('{' . $placeholder . '}', $value, $message);
        }
        
        return $message;
    }
    
    private $translations = [
        'en' => [
            'new_training' => '{first_name} has scheduled a new training.',
            'training_canceled_by_trainer' => '{first_name} has canceled the training.',
            'training_canceled_by_client' => '{first_name} has canceled the training.',
            'canceled_training_info' => 'The training was scheduled for {date} at {time}.',
            'new_request' => 'New request',
            'request_accepted' => 'Request accepted',
        ],
        'ru' => [
            'new_training' => '{first_name} запланировал(а) новую тренировку.',
            'training_canceled_by_trainer' => '{first_name} отменил(а) тренировку.',
            'training_canceled_by_client' => '{first_name} отменил(а) тренировку.',
            'canceled_training_info' => 'Тренировка была запланирована на {date} в {time}.',
            'new_request' => 'Новый запрос',
            'request_accepted' => 'Запрос принят',
        ],
        'sr' => [
            'new_training' => '{first_name} je zakazao novi trening.',
            'training_canceled_by_trainer' => '{first_name} je otkazao trening.',
            'training_canceled_by_client' => '{first_name} je otkazao trening.',
            'canceled_training_info' => 'Trening je bio zakazan za {date} u {time}.',
            'new_request' => 'Novi zahtev',
            'request_accepted' => 'Zahtev prihvaćen',
        ],
    ];

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
final class ConnStatus {
    public const ACCEPTED  = '1';
    public const NO_CONN = 'no_conn';
    public const DEFAULT  = '0';
}

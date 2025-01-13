<?php
/**
 * Created by PhpStorm.
 * User: lkaybob
 * Date: 20/03/2018
 * Time: 18:42
 */

namespace phpFCMv1;

class Notification extends Base {
    
    private $dataPayload = [];
    
    public function setNotification($title, $message) {
        $this->validateCurrent($title, $message);
        $this->setPayload(
            array('notification' => array(
                'title' => $title,
                'body' => $message
            ))
            );
    }
    
    /**
     * Set custom data payload
     * @param array $data
     */
    public function setDataPayload(array $data) {
        $this->dataPayload = $data;
    }
    
    /**
     * Build the final payload
     * @return array
     * @throws \UnderflowException
     */
    public function __invoke() {
        $payload = parent::__invoke();
        
        // Merge custom data payload into the final payload
        if (!empty($this->dataPayload)) {
            $payload['data'] = $this->dataPayload;
        }
        
        return $payload;
    }
}
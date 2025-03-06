<?php
/**
 * Created by PhpStorm.
 * User: lkaybob
 * Date: 20/03/2018
 * Time: 18:42
 */

namespace phpFCMv1;


class Notification extends Base {

    public function setNotification($title, $message) {
        $this -> validateCurrent($title, $message);
        $this -> setPayload(
            array('notification' => array(
                'title' => $title,
                'body' => $message
            ))
        );
    }
    
    public function setDataPayload(array $data) {
        $this->dataPayload = $data;
    }

    /**
     * @return array
     * @throws \UnderflowException
     */
    public function __invoke() {
        return parent ::__invoke();
    }
}
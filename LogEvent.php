<?php

namespace gateway;

use gateway\models\Request;
use yii\base\Event;

class LogEvent extends Event {

    /**
     * @var int
     */
    public $transactionId;

    /**
     * @var Request
     */
    public $request;

    /**
     * @var string
     */
    public $message;

}

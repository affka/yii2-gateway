<?php

namespace gateway;

use gateway\models\Process;
use gateway\models\Request;
use yii\base\Event;

class ProcessEvent extends Event {

    /**
     * @var Request
     */
    public $request;

    /**
     * @var Process
     */
    public $process;

}

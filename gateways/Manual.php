<?php

namespace gateway\gateways;

use gateway\enums\Result;
use gateway\enums\State;
use gateway\exceptions\UnsupportedStateMethodException;
use \gateway\models\Process;
use gateway\models\Request;

class Manual extends Base
{

    /**
     * @param string $id
     * @param integer|double $amount
     * @param string $description
     * @param array $params
     * @return \gateway\models\Process
     */
    public function start($id, $amount, $description, $params)
    {
        return new Process([
            'transactionId' => $id,
            'state' => State::WAIT_RESULT,
            'result' => Result::SUCCEED,
        ]);
    }

    /**
     * @param Request $requestModel
     * @return Process
     */
    public function callback(Request $requestModel)
    {
        return new Process([
            'state' => State::COMPLETE,
            'result' => $requestModel->params['result'],
        ]);
    }

}

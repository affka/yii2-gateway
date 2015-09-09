<?php

namespace gateway;

use gateway\components\IStateSaver;
use gateway\exceptions\NotFoundGatewayException;
use gateway\gateways\Base;
use gateway\models\Process;
use gateway\models\Request;
use yii\base\Module;
use yii\helpers\ArrayHelper;
use yii\helpers\Inflector;
use yii\web\Controller;

class GatewayModule extends Module
{
    /**
     * @event ProcessEvent
     */
    const EVENT_START = 'start';

    /**
     * @event ProcessEvent
     */
    const EVENT_CHECK = 'check';

    /**
     * @event ProcessEvent
     */
    const EVENT_END = 'end';

    /**
     * @event ProcessEvent
     */
    const EVENT_LOG = 'log';

    /**
     * @var string
     */
    public $controllerNamespace = 'gateway\controllers';

    /**
     * @var IStateSaver
     */
    public $stateSaver;

    /**
     * @var array
     */
    public $gateways = [];

    /**
     * @var string
     */
    public $logFilePath = '@runtime/gateway.log';

    /**
     * @return \gateway\GatewayModule
     */
    public static function getInstance() {
        return \Yii::$app->getModule('gateway');
    }

    public function init() {
        parent::init();

        $coreComponents = $this->coreComponents();
        foreach ($coreComponents as $id => $config) {
            if (is_string($this->$id)) {
                $config = ['class' => $this->$id];
            } elseif (is_array($this->$id)) {
                $config = ArrayHelper::merge($config, $this->$id);
            }
            $this->$id = \Yii::createObject($config);
        }
    }

    /**
     * @param string $name
     * @return Base
     * @throws NotFoundGatewayException
     * @throws \yii\base\InvalidConfigException
     */
    public function getGateway($name) {
        if (!isset($this->gateways[$name])) {
            $this->gateways[$name] = [];
        }
        if (is_string($this->gateways[$name])) {
            $this->gateways[$name]['class'] = $this->gateways[$name];
        }

        // Lazy create
        if (empty($this->gateways[$name]['class'])) {
            $this->gateways[$name]['class'] = $this->getGatewayClassByName($name);
        }
        $this->gateways[$name]['name'] = $name;
        $this->gateways[$name]['module'] = $this;
        $this->gateways[$name] = \Yii::createObject($this->gateways[$name]);

        return $this->gateways[$name];
    }

    /**
     * @param string $gatewayName
     * @param int $id
     * @param int|float $amount
     * @param string [$description]
     * @param array [$params]
     * @return models\Process
     * @throws \Exception
     */
    public function start($gatewayName, $id, $amount, $description = '', $params = []) {
        $this->log('Start transaction', 'log', $id, [
            'gatewayName' => $gatewayName,
            'amount' => $amount,
            'description' => $description,
            'params' => $params,
        ]);

        try {
            $process = $this->getGateway($gatewayName)->start($id, $amount, $description, $params);
            $this->trigger(self::EVENT_START, new ProcessEvent([
                'process' => $process,
            ]));
        } catch (\Exception $e) {
            $this->log('Failed on start transaction: ' . ((string) $e), 'error', $id, [
                'gatewayName' => $gatewayName,
                'amount' => $amount,
                'description' => $description,
                'params' => $params,
                'exception' => $e,
            ]);
            throw $e;
        }

        $process->transactionId = $id;
        return $process;
    }

    /**
     * @param string $gatewayName
     * @param Request $request
     * @return models\Process
     * @throws \Exception
     */
    public function check($gatewayName, Request $request) {
        $this->log('Check transaction', 'log', null, [
            'gatewayName' => $gatewayName,
            'request' => $request,
        ]);

        try {
            $process = $this->getGateway($gatewayName)->check($request);
            $this->trigger(self::EVENT_CHECK, new ProcessEvent([
                'request' => $request,
                'process' => $process,
            ]));
        } catch (\Exception $e) {
            $id = isset($process) ? $process->transactionId : null;
            $this->log('Failed on check transaction: ' . ((string) $e), 'error', $id, [
                'gatewayName' => $gatewayName,
                'request' => $request,
                'exception' => $e,
            ]);
            throw $e;
        }

        return $process;
    }

    /**
     * @param string $gatewayName
     * @param boolean $isSuccess
     * @param Request $request
     * @return models\Process
     * @throws \Exception
     */
    public function end($gatewayName, $isSuccess, Request $request) {
        $this->log('End transaction', 'log', null, [
            'gatewayName' => $gatewayName,
            'isSuccess' => $isSuccess,
            'request' => $request,
        ]);

        try {
            $process = $this->getGateway($gatewayName)->end($isSuccess, $request);
            $this->trigger(self::EVENT_END, new ProcessEvent([
                'request' => $request,
                'process' => $process,
            ]));
        } catch (\Exception $e) {
            $id = isset($process) ? $process->transactionId : null;
            $this->log('Failed on check transaction: ' . ((string) $e), 'error', $id, [
                'gatewayName' => $gatewayName,
                'isSuccess' => $isSuccess,
                'request' => $request,
                'exception' => $e,
            ]);
            throw $e;
        }
        return $process;
    }

    /**
     * @param Controller $controller
     * @param Process $process
     */
    public function redirect(Controller $controller, Process $process) {

    }

    /**
     * @param string $message
     * @param string $level
     * @param integer $transactionId
     * @param array $stateData
     * @throws \gateway\exceptions\GatewayException
     */
    public function log($message, $level = 'log', $transactionId = null, $stateData = []) {
        $message .= "\n" .
            "Date: " . date('Y-m-d H:i:s') . "\n" .
            "Level: " . $level . "\n" .
            "Transaction: " . $transactionId . "\n" .
            "State: " . var_export($stateData, true) . "\n";
        file_put_contents(\Yii::getAlias($this->logFilePath), $message, FILE_APPEND);

        $this->trigger(self::EVENT_LOG, new LogEvent([
            'transactionId' => $transactionId,
            'request' => isset($stateData['request']) ? $stateData['request'] : null,
            'message' => $message,
        ]));
    }

    /**
     * Отправляет POST запрос на указанный адрес
     * @param string $url
     * @param array $params
     * @param array $headers
     * @return string
     */
    public function httpSend($url, $params = [], $headers = []) {
        $headers = array_merge([
            'Content-Type' => 'application/x-www-form-urlencoded',
        ], $headers);

        $headersString = '';
        foreach ($headers as $key => $value) {
            $headersString .= trim($key) . ": " . trim($value) . "\n";
        }

        return file_get_contents($url, false, stream_context_create(array(
            'http' => array(
                'method' => 'POST',
                'header' => $headersString . "\n",
                'content' => is_array($params) ? http_build_query($params) : $params,
            ),
        )));
    }

    protected function coreComponents() {
        return [
            'stateSaver' => ['class' => '\gateway\components\StateSaverFile'],
        ];
    }

    /**
     * @param string $name
     * @return string
     * @throws NotFoundGatewayException
     */
    protected function getGatewayClassByName($name) {
        $className = __NAMESPACE__ . '\gateways\\' . Inflector::classify($name);
        if (!class_exists($className)) {
            throw new NotFoundGatewayException('Gateway `' . $name . '` is not found.');
        }
        return $className;
    }

}

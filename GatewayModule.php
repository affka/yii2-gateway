<?php

namespace gateway;

use gateway\components\IOrderInterface;
use gateway\components\IStateSaver;
use gateway\enums\State;
use gateway\exceptions\NotFoundGatewayException;
use gateway\gateways\Base;
use gateway\models\Process;
use gateway\models\Request;
use yii\base\Module;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\helpers\Inflector;
use yii\helpers\Url;
use yii\log\Logger;
use yii\web\Application;
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
    const EVENT_CALLBACK = 'callback';

    /**
     * @event ProcessEvent
     */
    const EVENT_END = 'end';

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
     * @var string
     */
    public $siteUrl = '';

    /**
     * @var IOrderInterface
     */
    public $orderClassName;

    /**
     * @var string
     */
    public $successUrl = ['/gateway/gateway/success'];

    /**
     * @var string
     */
    public $failureUrl = ['/gateway/gateway/success'];

    /**
     * @return \gateway\GatewayModule
     */
    public static function getInstance() {
        return \Yii::$app->getModule('gateway');
    }

    public function init() {
        parent::init();

        if (\Yii::$app instanceof Application) {
            $this->siteUrl = $this->siteUrl ? Url::to($this->siteUrl, true) : \Yii::$app->homeUrl;
            $this->successUrl = Url::to($this->successUrl, true);
            $this->failureUrl = Url::to($this->failureUrl, true);
        } else {
            $this->successUrl = is_array($this->successUrl) ? $this->successUrl[0] : $this->successUrl;
            $this->failureUrl = is_array($this->failureUrl) ? $this->failureUrl[0] : $this->failureUrl;
        }

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
        $this->gateways[$name] = \Yii::createObject(array_merge($this->gateways[$name], [
            'name' => $name,
            'module' => $this,
            'class' => isset($this->gateways[$name]['class']) ?
                $this->gateways[$name]['class'] :
                $this->getGatewayClassByName($name),
        ]));

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
        $this->log('Start transaction', Logger::LEVEL_INFO, $id, [
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
            $this->log('Failed on start transaction: ' . ((string) $e), Logger::LEVEL_ERROR, $id, [
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
    public function callback($gatewayName, Request $request) {
        try {
            $process = $this->getGateway($gatewayName)->callback($request);
            $this->trigger(self::EVENT_CALLBACK, new ProcessEvent([
                'request' => $request,
                'process' => $process,
            ]));
        } catch (\Exception $e) {
            $id = isset($process) ? $process->transactionId : null;
            $this->log('Failed on callback transaction: ' . ((string) $e), Logger::LEVEL_ERROR, $id, [
                'gatewayName' => $gatewayName,
                'request' => $request,
                'exception' => $e,
            ]);
            throw $e;
        }

        $id = isset($process) ? $process->transactionId : null;
        $this->log('Callback transaction', Logger::LEVEL_INFO, $id, [
            'gatewayName' => $gatewayName,
            'request' => $request,
        ]);

        switch ($process->state) {
            case State::COMPLETE:
            case State::COMPLETE_VERIFY:
                $this->trigger(self::EVENT_END, new ProcessEvent([
                    'request' => $request,
                    'process' => $process,
                ]));
        }

        return $process;
    }

    /**
     * @param string $message
     * @param integer $level
     * @param integer $transactionId
     * @param array $stateData
     * @throws \gateway\exceptions\GatewayException
     */
    public function log($message, $level = Logger::LEVEL_INFO, $transactionId = null, $stateData = []) {
        $message .= "\n" .
            "Transaction: " . $transactionId . "\n" .
            "State: " . print_r($stateData, true) . "\n\n" .
            "------------------------------------------\n\n";
        \Yii::getLogger()->log($message, $level, 'gateway');
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
            'stateSaver' => \Yii::$app->has('db') ?
                [
                    'class' => '\gateway\components\StateSaverDb',
                    'tableName' => 'gateway_states'
                ] : [
                    'class' => '\gateway\components\StateSaverFile'
                ],
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

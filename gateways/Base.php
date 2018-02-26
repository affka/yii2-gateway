<?php

namespace gateway\gateways;

use gateway\GatewayModule;
use gateway\models\Request;
use yii\base\Object;
use yii\log\Logger;

/**
 * Class Base
 * @package gateway\gateways
 */
abstract class Base extends Object
{

    /**
     * Флаг, отображающий включена ли платёжный шлюз.
     * @var boolean
     */
    public $enable = true;

    /**
     * Способ оплаты. Поле актуально только для платёжных интеграторов, где есть выбор способа оплаты.
     * @var string
     */
    public $paymentMethod;

    /**
     * Флаг, отображающий включен ли платёжный шлюз для реальных транзакций.
     * По-умолчанию включен режим разработчика.
     * @var boolean
     */
    public $testMode = true;

    /**
     * Имя платёжного шлюза, одно из значений enum GatewayName
     * @var string
     */
    public $name;

    /**
     *
     * @var GatewayModule
     */
    public $module;

    /**
     * @param int $id
     * @param integer|double $amount
     * @param string $description
     * @param array $params
     * @return \gateway\models\Process
     */
    abstract public function start($id, $amount, $description, $params);

    /**
     * @param Request $request
     * @return \gateway\models\Process
     */
    abstract public function callback(Request $request);

    /**
     * Адрес магазина/сайта
     * @return string
     */
    public function getSiteUrl()
    {
        return self::appendToUrl($this->module->siteUrl, 'gatewayName=' . $this->name);
    }

    /**
     * Адрес, по которому должна направить пользователя платёжная система при успешной оплате
     * @return string
     */
    public function getSuccessUrl()
    {
        return self::appendToUrl($this->module->successUrl, 'gatewayName=' . $this->name);
    }

    /**
     * Адрес, по которому должна направить пользователя платёжная система при неудачной оплате
     * @return string
     */
    public function getFailureUrl()
    {
        return self::appendToUrl($this->module->failureUrl, 'gatewayName=' . $this->name);
    }

    protected static function appendToUrl($url, $query)
    {
        return $url . (strpos($url, '?') === false ? '?' : '&') . $query;
    }

    /**
     * @param $message
     * @param integer $level
     * @param null $transactionId
     * @param array $stateData
     */
    protected function log($message, $level = Logger::LEVEL_INFO, $transactionId = null, $stateData = array())
    {
        $this->module->log($message, $level, $transactionId, $stateData);
    }

    /**
     * @param string|integer $id
     * @param array $data
     */
    protected function setStateData($id, $data = [])
    {
        $this->module->stateSaver->set($this->name . '_' . (string) $id, $data);
    }

    /**
     * @param $id
     * @return mixed
     */
    protected function getStateData($id)
    {
        return $this->module->stateSaver->get($id);
    }

    protected function httpSend($url, $params = [], $headers = [])
    {
        return $this->module->httpSend($url, $params, $headers);
    }

    protected function findOrderStateById($id) {
        $className = $this->module->orderClassName;
        return class_exists($className) ? $className::findOrderStateById($id) : null;
    }
}

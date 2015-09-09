<?php

namespace gateway\gateways;

use gateway\GatewayModule;
use gateway\models\Request;
use yii\base\Object;

/**
 * Class Base
 * @package gateway\gateways
 */
abstract class Base extends Object {

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
	abstract public function check(Request $request);

	/**
	 * @param string $result
     * @param Request $request
	 * @return \gateway\models\Process
	 */
	abstract public function end($result, Request $request);

	/**
	 * Адрес, по которому должна направить пользователя платёжная система при успешной оплате
	 * @return string
	 */
	public function getSuccessUrl() {
		return $this->module->getSuccessUrl($this->getName());
	}

	/**
	 * Адрес, по которому должна направить пользователя платёжная система при неудачной оплате
	 * @return string
	 */
	public function getFailureUrl() {
		return $this->module->getFailureUrl($this->getName());
	}

	/**
	 * Отсылает сообщения лога для записи его в БД
	 * @param string $message
	 * @param array $stateData
	 */
	protected function log($message, $level= 'log', $transactionId = null, $stateData = array()) {
		//GatewaysModule::findInstance()->api->log($message, $level, $transactionId, $stateData);
	}

    /**
     * @param $id
     * @param array $data
     */
    protected function setStateData($id, $data = []) {
        $this->module->stateSaver->set($id, $data);
    }

    /**
     * @param $id
     * @return mixed
     */
    protected function getStateData($id) {
        return $this->module->stateSaver->get($id);
    }

	protected function httpSend($url, $params = [], $headers = []) {
        $this->module->httpSend($url, $params, $headers);
	}
}

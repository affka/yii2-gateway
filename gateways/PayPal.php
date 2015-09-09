<?php

namespace gateway\gateways;

use gateway\enums\Result;
use gateway\enums\State;
use gateway\exceptions\InvalidArgumentException;
use gateway\exceptions\ProcessException;
use \gateway\exceptions\UnsupportedStateMethodException;
use gateway\models\Link;
use \gateway\models\Process;
use gateway\models\Request;

class PayPal extends Base {

	/**
	 * Api url. For developers: https://api.sandbox.paypal.com
	 * For production: https://api.paypal.com
	 * @var string
	 */
	public $apiUrl = '';

	/**
	 * Client ID. Example: EOJ2S-Z6OoN_le_KS1d75wsZ6y0SFdVsY9183IvxFyZp
	 * @var string
	 */
	public $clientId = '';

	/**
	 * Secret key. Example: EClusMEUk8e9ihI7ZdVLF5cZ6y0SFdVsY9183IvxFyZp
	 * @var string
	 */
	public $secretKey = '';

    /**
     * @var string
     */
	public $userEmail = '';

    /**
     * @param string $id
     * @param integer|double $amount
     * @param string $description
     * @param array $params
     * @return \gateway\models\Process
     * @throws ProcessException
     */
    public function start($id, $amount, $description, $params) {
		// Make response
		$processModel = new Process();
		$processModel->transactionId = $id;

		// Make and send payment call
		$requestData = [
			'intent' => 'sale',
			'payer' => [
				'payment_method' => 'paypal',
			],
			'transactions' => [
				[
					'amount' => [
						'total' => $amount,
						'currency' => 'USD', // @todo
					],
					'description' => $description,
				],
			],
			'redirect_urls' => [
				'return_url' => $this->getSuccessUrl(),
				'cancel_url' => $this->getFailureUrl(),
			],
		];
		$paymentResponseData = $this->httpSend($this->apiUrl . '/v1/payments/payment', json_encode($requestData), [
			'Content-Type' => 'application/json',
			'Authorization' => 'Bearer ' . $this->getAuthData()->access_token,
		]);
        $paymentResponseObject = $paymentResponseData ? json_decode($paymentResponseData) : null;
		if (!$paymentResponseObject) {
			throw new ProcessException('Wrong response data from paypal payment request.');
		}

		// Set state
		$processModel->state = State::WAIT_VERIFICATION;
		$processModel->result = isset($paymentResponseObject->state) && $paymentResponseObject->state === 'created' ?
            Result::SUCCEED :
            Result::ERROR;

		// Get redirect link
		$approvalUrl = null;
		foreach ($paymentResponseObject->links as $link) {
			if ($link->rel === 'approval_url') {
				$approvalUrl = $link;
				break;
			}
		}
		if (!$approvalUrl) {
			throw new ProcessException('Not find redirect link.');
		}

		$approvalLink = new Link($approvalUrl->href);

		// Save token as transaction ID
		if ($approvalLink->getParam('token')) {
			throw new ProcessException('Not find token in approval url.');
		}
		$processModel->outsideTransactionId = $approvalLink->getParam('token');
        $this->setStateData($processModel->outsideTransactionId, $paymentResponseObject);

		// Set redirect request
		$requestModel = new Request();
		$requestModel->params = $approvalLink->parameters;

		// Clean link
		$approvalLink->parameters = [];

		$requestModel->url = (string) $approvalLink;
		$processModel->request = $requestModel;

		return $processModel;
	}

    /**
     * @param Request $requestModel
     * @return void
     * @throws UnsupportedStateMethodException
     */
    public function check(Request $requestModel) {
        throw new UnsupportedStateMethodException();
    }

	/**
	 * @param string $result
	 * @param \gateway\models\Request $request
	 * @return \gateway\models\Process
	 * @throws \gateway\exceptions\ProcessException
	 * @throws \gateway\exceptions\InvalidArgumentException
	 */
	public function end($result, Request $request) {
		if (!isset($request->params['token']) || !isset($request->params['PayerID'])) {
			throw new InvalidArgumentException('Invalid request arguments. Need `token` and `PayerID`.');
		}

        $outsideTransactionId = $request->params['token'];

		// Get execute link
		$executeUrl = null;
        $gatewayData = $this->getStateData($outsideTransactionId);
        if ($gatewayData) {
            foreach ($gatewayData->links as $link) {
                if ($link->rel === 'execute') {
                    $executeUrl = $link;
                    break;
                }
            }
        }
		if (!$executeUrl) {
			throw new ProcessException('Not find execute link.');
		}

		// Send execute payment request
		$requestData = array(
			'payer_id' => $request->params['PayerID'],
		);
		$paymentResponseData = $this->httpSend($executeUrl->href, json_encode($requestData), array(
			'Content-Type' => 'application/json',
			'Authorization' => 'Bearer ' . $this->getAuthData()->access_token,
		));
		$paymentResponseObject = $paymentResponseData ? json_decode($paymentResponseData) : null;
		if (!$paymentResponseObject) {
			throw new ProcessException('Wrong response data from paypal payment request.');
		}

        return new Process([
            'state' => State::COMPLETE,
            'outsideTransactionId' => $outsideTransactionId,
            'result' => isset($paymentResponseObject->state) && $paymentResponseObject->state === 'approved' ?
                Result::SUCCEED :
                Result::ERROR,
        ]);
	}

	/**
	 * @return object
	 * @throws \gateway\exceptions\ProcessException
	 */
	private function getAuthData() {
		// Send auth request
		$authResponseData = $this->httpSend($this->apiUrl . '/v1/oauth2/token', array(
			'grant_type' => 'client_credentials',
		), array(
			'Accept' => 'application/json',
			'Accept-Language' => 'en_US',
			'Authorization' => 'Basic ' . base64_encode($this->clientId . ':' . $this->secretKey),
		));
		$authResponseObject = $authResponseData ? json_decode($authResponseData) : null;

		if (!$authResponseObject || !$authResponseObject->access_token) {
			throw new ProcessException('Wrong response data from paypal auth request.');
		}

		return $authResponseObject;
	}

}

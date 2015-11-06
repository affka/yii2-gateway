<?php

namespace gateway\gateways;

use gateway\enums\Result;
use gateway\enums\State;
use gateway\exceptions\InvalidArgumentException;
use gateway\exceptions\SignatureMismatchRequestException;
use gateway\models\Process;
use gateway\models\Request;

class PayInPayOut extends Base {

    const CURRENCY_RUR = 'RUR';
    const CURRENCY_EUR = 'EUR';
    const CURRENCY_USD = 'USD';
    const CURRENCY_GBP = 'GBP';
    const CURRENCY_UAH = 'UAH';

    const PREFERENCE_PAYINPAYOUT = 1;
    const PREFERENCE_QIWI_TERMINAL = 2;
    const PREFERENCE_BANK_RF = 5;
    const PREFERENCE_YANDEX_MONEY = 6;
    const PREFERENCE_BANK_SWIFT = 7;
    const PREFERENCE_QIWI_WALLET = 8;
    const PREFERENCE_RBK_MONEY = 13;
    const PREFERENCE_WALLET_ONE = 14;
    const PREFERENCE_PAYPAL = 15;
    const PREFERENCE_CARD_1 = 22;
    const PREFERENCE_ELECSENT_TERMINAL = 124;
    const PREFERENCE_CARD_2 = 125;
    const PREFERENCE_ALPHA_CLICK = 126;
    const PREFERENCE_SMS = 127;
    const PREFERENCE_SMS_BEELINE = 128;
    const PREFERENCE_WEBMONEY = 129;
    const PREFERENCE_EUROSET_AND_MTS = 130;

    const PAYMENT_STATUS_SUCCESS = 1;
    const PAYMENT_STATUS_FAILURE = 2;
    const PAYMENT_STATUS_QUEUE = 3;

    const API_URL = 'https://lk.payin-payout.net/api/shop';

    /**
     * @var string
     */
	public $agentId;

    /**
     * @var string
     */
	public $agentName = '';

    /**
     * @var string
     */
	public $secretKey;

    /**
     * @var string
     */
	public $currency = self::CURRENCY_RUR;

    /**
     * @var string
     */
	public $preference = null;

    public function init() {
        parent::init();

        if (\Yii::$app instanceof \yii\base\Application) {
            $this->agentName = $this->agentName ?: \Yii::$app->name;
        }
    }

    /**
     * @param int $id
     * @param float|int $amount
     * @param string $description
     * @param array $params
     * @return Process
     * @throws InvalidArgumentException
     */
    public function start($id, $amount, $description, $params) {
        $now = date('H:i:s d.m.Y', YII_BEGIN_TIME); // HH:mm:SS dd.MM.yyyy

        // Filter
        $amount = self::normalizeAmount($amount);
        $params['phone'] = self::normalizePhone($params['phone']);

        return new Process([
            'state' => State::WAIT_VERIFICATION,
            'result' => Result::SUCCEED,
            'request' => new Request([
                'url' => self::API_URL,
                'method' => 'post',
                'params' => array_merge($params, [
                    'agentId' => $this->agentId,
                    'agentName' => $this->agentName,
                    'orderId' => $id,
                    'amount' => $amount,
                    'agentTime' => $now,
                    'goods' => $description,
                    'currency' => $this->currency,
                    'shop_url' => $this->getSiteUrl(),
                    'successUrl' => $this->getSuccessUrl(),
                    'failUrl' => $this->getFailureUrl(),
                    'sign' => md5(implode('#', [
                        $this->agentId,
                        $id,
                        $now,
                        $amount,
                        $params['phone'],
                        md5($this->secretKey),
                    ]))
                ]),
            ]),
        ]);
	}

    /**
     * @param Request $request
     * @return Process
     * @throws InvalidArgumentException
     * @throws SignatureMismatchRequestException
     */
	public function callback(Request $request) {
		// Check required params
		if (empty($request->params['agentId']) || empty($request->params['orderId']) || empty($request->params['sign'])) {
            throw new InvalidArgumentException('Invalid request arguments.');
		}

        if ($request->params['agentId'] != $this->agentId) {
            throw new InvalidArgumentException('Agent id is not valid.');
        }

        // Generate hash sum
        $md5 = md5(implode('#', [
            $this->agentId,
            $request->params['orderId'],
            $request->params['paymentId'],
            $request->params['amount'],
            $request->params['phone'],
            $request->params['paymentStatus'],
            $request->params['paymentDate'],
            md5($this->secretKey),
        ]));
        $remoteMD5 = $request->params['sign'];

        // Check md5 hash
        if ($md5 !== $remoteMD5) {
            throw new SignatureMismatchRequestException();
        }

        $process = new Process();
        $process->responseText = 'OK';
        $process->transactionId = $request->params['orderId'];
        $process->outsideTransactionId = $request->params['outputId'];

        // Send result
        switch ($request->params['paymentStatus']) {
            case self::PAYMENT_STATUS_QUEUE:
                $process->state = State::WAIT_RESULT;
                $process->result = Result::SUCCEED;
                break;

            case self::PAYMENT_STATUS_SUCCESS:
                $process->state = State::COMPLETE;
                $process->result = Result::SUCCEED;
                break;

            case self::PAYMENT_STATUS_FAILURE:
                $process->state = State::COMPLETE;
                $process->result = Result::FAILED;
                break;

            default:
                throw new InvalidArgumentException('Unknown payment state');
        }

        return $process;
	}

    protected static function normalizeAmount($value) {
        return number_format($value, 2, '.', '');
    }

    protected static function normalizePhone($value) {
        $value = preg_replace('/[^0-9]/', '', $value);
        $value = preg_replace('/^8/', '7', $value);
        return $value;
    }
}
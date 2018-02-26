<?php

namespace gateway\gateways;

use Yii;
use gateway\enums\Result;
use gateway\enums\State;
use gateway\exceptions\GatewayException;
use gateway\exceptions\InvalidArgumentException;
use gateway\exceptions\SignatureMismatchRequestException;
use gateway\models\Process;
use gateway\models\Request;
use yii\web\Response;

class YandexKassa extends Base
{

    /**
     * Идентификатор магазина в Яндекс.Кассе
     * @var integer
     */
    public $shopId;

    /**
     * Идентификатор витрины магазина в Яндекс.Кассе
     * @var integer
     */
    public $scId;

    /**
     * Секретное слово для Яндекс.Кассы
     * @var string
     */
    public $password;

    /**
     * @var string
     */
    public $url;

    /**
     * @param string $id
     * @param integer|double $amount
     * @param string $description
     * @param array $params
     * @return \gateway\models\Process
     * @throws \gateway\exceptions\ProcessException
     * @throws GatewayException
     */
    public function start($id, $amount, $description, $params)
    {
        if (!isset($params['userId'])) {
            throw new GatewayException('Param userId is required for gateway `' . __CLASS__ . '`.');
        }

        // Remote url
        $url = $this->url ?: ($this->testMode ? 'https://demomoney.yandex.ru/eshop.xml' : 'https://money.yandex.ru/eshop.xml');

        return new Process([
            'state' => State::WAIT_VERIFICATION,
            'result' => Result::SUCCEED,
            'request' => new Request([
                'url' => $url,
                'method' => 'post',
                'params' => [
                    'shopId' => $this->shopId,
                    'scid' => $this->scId,
                    'sum' => number_format((float)$amount, 2, '.', ''),
                    'customerNumber' => $params['userId'],
                    'cps_email' => isset($params['email']) ? $params['email'] : '',
                    'cps_phone' => isset($params['phone']) ? $params['phone'] : '',
                    'paymentType' => isset($params['paymentType']) ? $params['paymentType'] : '', // See https://tech.yandex.ru/money/doc/payment-solution/reference/payment-type-codes-docpage/
                    'orderNumber' => $id,
                    'shopSuccessURL' => $this->getSuccessUrl(),
                    'shopFailURL' => $this->getFailureUrl(),
                ],
            ])
        ]);
    }

    /**
     * @param Request $request
     * @return Process
     * @see https://tech.yandex.ru/money/doc/payment-solution/payment-notifications/payment-notifications-check-docpage/
     * @see https://tech.yandex.ru/money/doc/payment-solution/payment-notifications/payment-notifications-aviso-docpage/
     * @see https://tech.yandex.ru/money/doc/payment-solution/payment-notifications/payment-notifications-cancel-docpage/
     * @throws InvalidArgumentException
     * @throws SignatureMismatchRequestException
     */
    public function callback(Request $request)
    {
        // Check required params
        $requiredParams = [
            'action',
            'orderSumAmount',
            'orderSumCurrencyPaycash',
            'orderSumBankPaycash',
            'invoiceId',
            'customerNumber',
        ];
        if (!$request->hasParams($requiredParams)) {
            return new Process([
                'transactionId' => $request->params['orderNumber'],
                'result' => Result::ERROR,
                'responseText' => $this->getXml($request, 200),
            ]);
        }

        // Check md5 signature
        $md5 = md5(implode(';', [
            $request->params['action'],
            $request->params['orderSumAmount'],
            $request->params['orderSumCurrencyPaycash'],
            $request->params['orderSumBankPaycash'],
            $this->shopId,
            $request->params['invoiceId'],
            $request->params['customerNumber'],
            $this->password,
        ]));
        $remoteMD5 = strtolower($request->params['md5']);
        if ($md5 !== $remoteMD5) {
            return new Process([
                'transactionId' => $request->params['orderNumber'],
                'result' => Result::ERROR,
                'responseText' => $this->getXml($request, 1),
            ]);
        }

        // Find state
        $state = $this->findOrderStateById($request->params['orderNumber']);

        switch ($request->params['action']) {
            case 'checkOrder':
                // Check order exists and state
                if ($state === null || $state !== State::WAIT_VERIFICATION) {
                    return new Process([
                        'transactionId' => $request->params['orderNumber'],
                        'result' => Result::ERROR,
                        'responseText' => $this->getXml($request, 100),
                    ]);
                }

                return new Process([
                    'transactionId' => $request->params['orderNumber'],
                    'state' => State::WAIT_RESULT,
                    'result' => Result::SUCCEED,
                    'outsideTransactionId' => (string) $request->params['invoiceId'],
                    'responseText' => $this->getXml($request, 0),
                ]);

            case 'paymentAviso':
                // Check order exists and state
                if ($state === null || !in_array($state, [State::WAIT_VERIFICATION, State::WAIT_RESULT])) {
                    return new Process([
                        'transactionId' => $request->params['orderNumber'],
                        'result' => Result::ERROR,
                        'responseText' => $this->getXml($request, 100),
                    ]);
                }

                return new Process([
                    'transactionId' => $request->params['orderNumber'],
                    'state' => State::COMPLETE,
                    'result' => Result::SUCCEED,
                    'outsideTransactionId' => (string) $request->params['invoiceId'],
                    'responseText' => $this->getXml($request, 0),
                ]);
        }

        // Send success result
        return new Process([
            'transactionId' => $request->params['orderNumber'],
            'result' => Result::ERROR,
            'responseText' => $this->getXml($request, 200),
        ]);
    }

    protected function getXml(Request $request, $code, $params = [])
    {
        $params = array_merge([
            'code' => $code,
            'shopId' => $this->shopId,
            'invoiceId' => isset($request->params['invoiceId']) ? $request->params['invoiceId'] : '',
            'performedDatetime' => isset($request->params['requestDatetime']) ? $request->params['requestDatetime'] : '',
        ], $params);

        $tagAttributes = [];
        foreach ($params as $name => $value) {
            $tagAttributes[] = sprintf('%s="%s"', $name, $value);
        }

        $tag = (!empty($request->params['action']) ? $request->params['action'] : 'checkOrder') . 'Response';
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<' . $tag . ' ' . implode(' ', $tagAttributes) . '/>';

        // TODO Need this headers? If need - move to Process?
        Yii::$app->response->format = Response::FORMAT_RAW;
        Yii::$app->response->headers->set('Content-Type', 'application/xml; charset=' . Yii::$app->charset);

        return $xml;
    }

}
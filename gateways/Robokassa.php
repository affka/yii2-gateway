<?php

namespace gateway\gateways;

use gateway\enums\Result;
use gateway\enums\State;
use gateway\exceptions\InvalidArgumentException;
use gateway\exceptions\SignatureMismatchRequestException;
use gateway\models\Process;
use gateway\models\Request;

class Robokassa extends Base
{

    /**
     * @var string
     */
    public $login;

    /**
     * @var string
     */
    public $password1;

    /**
     * @var string
     */
    public $password2;

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
     */
    public function start($id, $amount, $description, $params)
    {
        $shpParams = self::getShpParams($params);
        $shpSignature = self::getShpSignatureBase($params);

        // Remote url
        $url = $this->url ?: ($this->testMode ? 'http://test.robokassa.ru/Index.aspx' : 'http://auth.robokassa.ru/Merchant/Index.aspx');

        return new Process([
            'state' => State::WAIT_VERIFICATION,
            'result' => Result::SUCCEED,
            'request' => new Request([
                'url' => $url,
                'params' => array_merge($shpParams, [
                    'MrchLogin' => $this->login,
                    'OutSum' => $amount,
                    'InvId' => $id,
                    'Desc' => $description,
                    'SignatureValue' => md5($this->login . ":" . $amount . ":" . $id . ":" . $this->password1 . $shpSignature),
                    'IncCurrLabel' => $this->paymentMethod,
                    'Culture' => 'ru',
                    'Encoding' => 'utf-8',
                ]),
            ])
        ]);
    }

    /**
     * @param Request $request
     * @return Process
     * @throws InvalidArgumentException
     * @throws SignatureMismatchRequestException
     */
    public function callback(Request $request)
    {
        // Check required params
        if (empty($request->params['InvId']) || empty($request->params['SignatureValue'])) {
            throw new InvalidArgumentException('Invalid request arguments. Need `InvId` and `SignatureValue`.');
        }

        // @todo check transaction exists
        // Find transaction model
        $transactionId = $request->params['InvId'];

        $shpParams = array_filter($request->params, function($key){
            return preg_match('/Shp_.*/', $key);
        }, ARRAY_FILTER_USE_KEY);

        $shpSignature = self::getShpSignatureBase($shpParams, false);

        // Generate hash sum
        $md5 = strtoupper(md5($this->login . ':' . $request->params['OutSum'] . ':' . $transactionId . ':' . $this->password2 . $shpSignature));
        $remoteMD5 = $request->params['SignatureValue'];

        // Check md5 hash
        if ($md5 != $remoteMD5) {
            throw new SignatureMismatchRequestException();
        }

        // Send success result
        return new Process([
            'transactionId' => $transactionId,
            'state' => State::COMPLETE,
            'result' => Result::SUCCEED,
            'responseText' => 'OK' . $transactionId,
        ]);
    }

    public function resolveTransactionId(Request $request)
    {
        return !empty($request->params['InvId']) ? $request->params['InvId'] : null;
    }

    /**
     * Formats request params by Robokassa's requirements.
     * @param array $params
     * @return array
     */
    private static function getShpParams($params) {
        // Additional params
        $shpParams = [];

        if ($params) {
            foreach ($params as $key => $value) {
                $shpParams['Shp_' . $key] = $value;
            }
        }

        return $shpParams;
    }

    /**
     * Calculates a string which should be added to signature base if a request to Robakassa was made with params.
     * @see https://docs.robokassa.ru/#1250
     * @param array $params
     * @return string
     */
    private static function getShpSignatureBase($params, $needFormatting = true) {
        if ($needFormatting) {
            $params = self::getShpParams($params);
        }

        $shpSignatureBase = '';

        if ($params) {
            // params should be ordered by alphabet in MD5 signature
            ksort($params);
            foreach ($params as $key => $value) {
                $shpSignatureBase .= ':' . $key . '=' . $value;
            }
        }

        return $shpSignatureBase;
    }

}

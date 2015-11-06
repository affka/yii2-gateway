<?php

namespace gateway\tests\unit;

use gateway\GatewayModule;
use gateway\models\Process;
use gateway\models\Request;

class PayInTest extends \PHPUnit_Framework_TestCase {

    const AGENT_ID = 927;
    const SECRET_KEY = 'JsTjoe6KnK';

    public function testPay() {
        $id = 11;
        $amount = 1;
        $phone = '79509806194';

        $module = new GatewayModule('gateway', null, [
            'gateways' => [
                'payinpayout' => [
                    'enable' => true,
                    'agentId' => self::AGENT_ID,
                    'secretKey' => self::SECRET_KEY,
                    'testMode' => false,
                ],
            ],
            'stateSaver' => [
                'savePath' => __DIR__ . '/tmp/',
            ],
        ]);
        $gateway = $module->getGateway('payinpayout');

        date_default_timezone_set('UTC');
        $now = date('H:i:s d.m.Y', YII_BEGIN_TIME);
        $paymentDate = '05:39:11 06.11.2015';// date('H:i:s d.m.Y', strtotime('+1 day'));

        // Start
        /** @var Process $process */
        $process = $gateway->start($id, $amount, 'Test pay', ['phone' => $phone]);
        $this->assertEquals('wait_verification', $process->state);
        $this->assertEquals('succeed', $process->result);
        $this->assertEquals('https://lk.payin-payout.net/api/shop', $process->request->url);

        $signature = md5(self::AGENT_ID . '#' . $id . '#' . $now . '#' . $amount . '.00#' . $phone . '#' . md5(self::SECRET_KEY));
        $this->assertEquals(self::AGENT_ID, $process->request->params['agentId']);
        $this->assertEquals($id, $process->request->params['orderId']);
        $this->assertEquals($now, $process->request->params['agentTime']);
        $this->assertEquals($signature, $process->request->params['sign']);

        // Check
        $process = $gateway->callback(new Request([
            'method' => 'post',
            'url' => 'http://mysite.com/gateway/default/check?gatewayName=payinpayout',
            'params' => [
                'agentId' => self::AGENT_ID,
                'paymentId' => 64462799920969,
                'orderId' => $id,
                'amount' => $amount . '.00',
                'phone' => $phone,
                'currency' => 'RUR',
                'preference' => '125',
                'goods' => 'Рога, 10 кг',
                'agentName' => 'Рога и Копыта (TM)',
                'paymentStatus' => '1',
                'paymentDate' => $paymentDate,
                'outputId' => '008530-000002',
                'sign' => 'aebd37d607468a9202ae1ff03a48ccfb'// md5(self::AGENT_ID . '#' . $id . '#64462799920969#' . $amount . '.00#' . $phone . '#1#' . $paymentDate . '#' . md5(self::SECRET_KEY)),
            ],
        ]));
        $this->assertEquals('complete', $process->state);
        $this->assertEquals('succeed', $process->result);
        $this->assertEquals('OK', $process->responseText);
    }

}
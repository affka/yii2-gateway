<?php

namespace gateway\tests\unit;

use gateway\gateways\Robokassa;
use gateway\models\Process;
use gateway\models\Request;

class RobokassaTest extends \PHPUnit_Framework_TestCase {

    const ROBOKASSA_LOGIN = 'demo';
    const ROBOKASSA_PASSWORD = 'password1';
    const ROBOKASSA_PASSWORD_2 = 'password2';

    public function testPay() {
        $id = '15449';
        $amount = 199;

        /*$module = $this->getMock('gateway\GatewayModule', [
            'httpSend',
            'log',
        ], [
            'gateway',
            null,
            [
                'stateSaver' => [
                    'savePath' => __DIR__ . '/tmp'
                ]
            ]
        ]);
        //$mock->expects($this->once())->method('httpSend');*/

        $gateway = new Robokassa([
			'enable' => true,
			'login' => self::ROBOKASSA_LOGIN,
			'password1' => self::ROBOKASSA_PASSWORD,
			'password2' => self::ROBOKASSA_PASSWORD_2,
            'testMode' => false,
        ]);

        // Start
        /** @var Process $process */
        $process = $gateway->start($id, $amount, 'Test pay', ['Item' => 1]);
        $this->assertEquals('wait_verification', $process->state);
        $this->assertEquals('succeed', $process->result);
        $this->assertEquals('http://auth.robokassa.ru/Merchant/Index.aspx', $process->request->url);

        $signature = md5(self::ROBOKASSA_LOGIN . ':' . $amount . ':' . $id . ':' . self::ROBOKASSA_PASSWORD . ':Shp_Item=1');
        $startLink = 'http://auth.robokassa.ru/Merchant/Index.aspx?Shp_Item=1&MrchLogin=' . self::ROBOKASSA_LOGIN . '&OutSum=' . $amount . '&InvId=' .
            $id . '&Desc=Test+pay&SignatureValue=' . $signature . '&Culture=ru&Encoding=utf-8';
        $this->assertEquals($startLink, (string) $process->request);
        echo $startLink;

        // Check
        $process = $gateway->check(new Request([
            'method' => 'get',
            'url' => 'http://mysite.com/gateway/default/check?gatewayName=robokassa',
            'params' => [
                'out_summ' => $amount . '.000000',
                'OutSum' => $amount . '.000000',
                'inv_id' => $id,
                'InvId' => $id,
                'crc' => 'CB94E47BE59E29A7CDE39FE6C42634CA',
                'SignatureValue' => 'CB94E47BE59E29A7CDE39FE6C42634CA',
                'PaymentMethod' => 'OceanBank',
                'IncSum' => '199.000000',
                'IncCurrLabel' => 'BANKOCEAN2R',
                'paymentSystemName' => 'robokassa',
            ],
        ]));
        $this->assertEquals('complete', $process->state);
        $this->assertEquals('succeed', $process->result);
        $this->assertEquals('OK' . $id, $process->responseText);

        // End
        $process = $gateway->end(false, new Request());
        $this->assertEquals('complete', $process->state);
        $this->assertEquals('failed', $process->result);
    }

}
<?php

namespace gateway\controllers;

use gateway\GatewayModule;
use gateway\models\Request;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\web\Controller;

class GatewayController extends Controller
{
    public $enableCsrfValidation = false;

    public function actionStart($gatewayName, $id, $amount, $description = '', array $params = [])
    {
        $process = GatewayModule::getInstance()->start($gatewayName, $id, $amount, $description, $params);

        if ($process->request->method === 'get') {
            return $this->redirect((string)$process->request);
        } else {
            $html = '';
            $html .= Html::beginForm($process->request->url, 'post', ['name' => 'redirectForm']);
            foreach ($process->request->params as $key => $value) {
                $html .= Html::hiddenInput($key, $value);
            }
            $html .= Html::endForm();
            $html .= Html::script('document.redirectForm.submit()');

            return $html;
        }
    }

    public function actionCallback($gatewayName)
    {
        $process = GatewayModule::getInstance()->callback($gatewayName, $this->getRequest());
        echo $process->responseText;
    }

    public function actionSuccess($gatewayName)
    {
        $error = \Yii::$app->request->get('error');
        if ($error) {
            return $error;
        }
        // @todo
        return $this->redirect(\Yii::$app->homeUrl);
        //GatewayModule::getInstance()->end($gatewayName, true, $this->getRequest());
    }

    public function actionFailure($gatewayName)
    {
        // @todo
        //GatewayModule::getInstance()->end($gatewayName, false, $this->getRequest());
    }

    /**
     * @return Request
     */
    protected function getRequest()
    {
        /** @var \yii\web\Request $request */
        $request = \Yii::$app->request;

        $port = $request->port && $request->port !== 80 ? ':' . $request->port : '';
        return new Request([
            'method' => $request->method,
            'url' => $request->hostInfo . $port . str_replace('?' . $request->queryString, '', $request->url),
            'params' => ArrayHelper::merge($request->get(), $request->post()),
        ]);
    }
}

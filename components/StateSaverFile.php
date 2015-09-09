<?php

namespace gateway\components;

use yii\base\Component;

class StateSaverFile extends Component implements IStateSaver {

    public $savePath;

    public function init() {
        parent::init();

        $this->savePath = $this->savePath ?: \Yii::$app->runtimePath;
        $this->savePath = rtrim($this->savePath, '/');
    }

    /**
     * @param string|int $id
     * @param array $data
     */
    public function set($id, $data) {
        file_put_contents($this->savePath . '/' . md5($id) . '.json', json_encode($data));
    }

    /**
     * @param string|int $id
     * @return mixed|null
     */
    public function get($id) {
        $path = $this->savePath . '/' . md5($id) . '.json';
        if (!file_exists($path)) {
            return null;
        }
        return json_decode(file_get_contents($path));
    }

}
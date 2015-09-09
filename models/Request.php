<?php

namespace gateway\models;

use yii\base\Object;

class Request extends Object {

    public $url;
    public $method = 'get';
    public $params = [];

    public function __toString() {
        $link = new Link($this->url);
        if ($this->method === 'get') {
            $link->parameters = $this->params;
        }
        return (string) $link;
    }

}
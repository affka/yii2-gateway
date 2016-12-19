<?php

namespace gateway\models;

use yii\base\Object;

class Request extends Object
{

    public $url;
    public $method = 'get';
    public $params = [];

    /**
     * @param array|string $params
     * @return bool
     */
    public function hasParams($params) {
        if (!is_array($params)) {
            $params = [$params];
        }

        foreach ($params as $param) {
            if (!isset($this->params[$param])) {
                return false;
            }
        }
        return true;
    }

    public function __toString()
    {
        $link = new Link($this->url);
        if ($this->method === 'get') {
            $link->parameters = $this->params;
        }
        return (string)$link;
    }

}
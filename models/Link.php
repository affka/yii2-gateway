<?php

namespace gateway\models;
use yii\base\Object;

/**
 * Модель для парсинга и формирования ссылки
 * 
 * @author affka 
 */
class Link extends Object {
	
	public $protocol;
	public $source;
	public $domain;
	public $host;
	public $path;
	public $parameters = array();
	public $hash;
	public $hashParameters = array();
	
	public function __construct($config = '') {
        if (is_string($config)) {
            preg_match('/((https?:\/\/www\.)|(https?:\/\/)|(www\.))([^\/\n\r\t\"\' ]+)([^\?\n\r\t\"\'\# ]*)\??([^\n\r\t\"\'\# ]*)\#?([^\n\r\t\"\' ]*)/iu', $config, $match);

            if (count($match) > 5) {
                $this->protocol = strpos($match[1], 'https') !== false ? 'https' : 'http';
                $this->source = $config;
                $this->domain = preg_replace('/^(.+\.)?([^\.]+\.[^\.]+)$/iu', '\\2', $match[5]);
                $this->host = $match[5];
                $this->path = $match[6];
                $this->parameters = $this->stringToParameters($match[7]);
                $this->hash = $match[8];
                $this->hashParameters = $this->stringToParameters($match[8]);
            }

            $config = [];
        }

        parent::__construct($config);
	}

	public function hasParam($name) {
		return array_key_exists($name, $this->parameters) !== false;
	}

	public function getParam($name) {
		return $this->hasParam($name) ? $this->parameters[$name] : null;
	}

	public function setParam($name, $value) {
		$this->parameters[$name] = $value;
	}

	public function setParams(array $params) {
		$this->parameters = array_merge($this->parameters, $params);
	}

	public function hasHashParam($name) {
		return array_key_exists($name, $this->hashParameters);
	}

	public function getHashParam($name) {
		return $this->hasHashParam($name) ? $this->hashParameters[$name] : null;
	}

	public function setHashParam($name, $value) {
		$this->hashParameters[$name] = $value;
	}

	public function setHashParams(array $params) {
		$this->hashParameters = array_merge($this->hashParameters, $params);
	}
	
	public function __toString() {
		$link = $this->protocol.'://';
		$link .= $this->host;
		$link .= $this->path;
		
		$stringParameters = $this->parametersToString($this->parameters);
		if ($stringParameters) {
			$link .= '?'.$stringParameters;
		}
		
		return $link;
	}
	
	private function stringToParameters($parametersString) {
		$parameters = array();
		foreach (explode('&', $parametersString) as $paramString) {
			$paramArr = explode('=', $paramString);
			if (count($paramArr) === 2) {
				$parameters[$paramArr[0]] = $paramArr[1];
			}
		}
		return $parameters;
	}
	
	private function parametersToString($parameters) {
		return http_build_query($parameters);
	}
}
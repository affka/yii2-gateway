<?php

namespace gateway\components;

interface IStateSaver {

    /**
     * @param string|int $id
     * @param array $data
     */
    public function set($id, $data);

    /**
     * @param string|int $id
     * @return mixed|null
     */
    public function get($id);

}
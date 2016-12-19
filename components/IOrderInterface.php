<?php

namespace gateway\components;

interface IOrderInterface
{

    /**
     * @param string|int $id
     * @return string|null
     */
    public static function findOrderStateById($id);

}
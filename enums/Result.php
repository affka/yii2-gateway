<?php

namespace gateway\enums;

use yii\base\Object;

/**
 * Результат обработки события в платёжной системе
 * @author Vladimir Kozhin <affka@affka.ru>
 */
abstract class Result extends Object
{
    /**
     * Платежное поручение, поданное платежной системой в нашу на проверку, не подтверждено
     */
    const REJECTED = 'rejected';

    /**
     * Платежная система сообщила об успешно проведенном платеже
     */
    const SUCCEED = 'succeed';

    /**
     * Платежная система сообщила об ошибке в процессе платежа
     */
    const FAILED = 'failed';

    /**
     * Платеж отменен плательщиком через платежную систему
     */
    const CANCELED = 'canceled';

    /**
     * Зарегистрирована ошибка в обработке сообщения от ПС
     */
    const ERROR = 'error';

}

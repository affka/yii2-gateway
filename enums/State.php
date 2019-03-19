<?php

namespace gateway\enums;

use yii\base\BaseObject;

abstract class State extends BaseObject
{
    /**
     * Создано платежное поручение, но еще не отправлено в платежную систему
     */
    const CREATED = 'created';

    /**
     * Операция ожидает запроса верификации от платежной системы
     */
    const WAIT_VERIFICATION = 'wait_verification';

    /**
     * Операция ожидает получения окончательного результата от платежной системы
     */
    const WAIT_RESULT = 'wait_result';

    /**
     * Результат операции известен, получен о ПС или выставлен оператором вручную
     */
    const COMPLETE = 'complete';

    /**
     * Результат операции известен, отправленответ к ПС, ждем редиректа от ПС.
     * Необходимо для некоторых ПС.
     */
    const COMPLETE_VERIFY = 'complete_verify';

}

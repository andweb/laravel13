<?php

return [

    /*
     * Возврат из paid в refunded. Строка "false" из .env не должна стать true.
     */
    'refunds_enabled' => filter_var(env('REFUNDS_ENABLED', false), FILTER_VALIDATE_BOOL),

];

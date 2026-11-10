<?php

declare(strict_types=1);

return [

    'driver' => env('AI_CONTENT_DRIVER', 'fake'),

    'timezone' => 'Europe/Moscow',

    'horoscope' => [
        'days_range' => 7,
    ],

    'drivers' => [
        'fake' => [
            'text' => 'Фиксированный ответ без обращения к модели.',
        ],

        'deepseek' => [
            'key' => env('DEEPSEEK_API_KEY'),
            'url' => env('DEEPSEEK_URL', 'https://api.deepseek.com'),
            'model' => env('DEEPSEEK_MODEL', 'deepseek-flash'),
            'timeout' => 60,
            'max_tokens' => 2000,
        ],
    ],

];

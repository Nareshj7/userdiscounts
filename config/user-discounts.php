<?php

return [
    'models' => [
        'user' => null,
    ],

    'stacking' => [
        'order' => 'priority',
        'direction' => 'desc',
    ],

    'caps' => [
        'max_percentage' => 50,
        'rounding' => 'floor',
        'precision' => 2,
    ],

    'concurrency' => [
        'lock_timeout' => 5,
    ],
];
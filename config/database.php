<?php

return [
    'default' => 'mongodb',
    'connections' => ['mongodb' => [
        'driver' => 'mongodb',
        'rename_embedded_id_field' => false,
        'dsn' => env('MONGODB_URI', 'mongodb://127.0.0.1:27017/?replicaSet=rs0'),
        'database' => env('MONGODB_DATABASE', 'nabaku'),
        'options' => ['serverSelectionTimeoutMS' => 5000],
    ]],
];

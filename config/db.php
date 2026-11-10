<?php

return [
    'class' => \yii\db\Connection::class,
    'dsn' => 'pgsql:host=postgres' . ';port=5432;dbname=' . getenv('DB_NAME'),
    'username' => getenv('DB_USER'),
    'password' => getenv('DB_PASSWORD'),
    'charset' => 'utf8',

];

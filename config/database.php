<?php
/**
 * Configuración de conexión a la base de datos.
 * AJUSTA estos valores con los datos de tu hosting/servidor.
 */
return [
    'host'    => getenv('DB_HOST') ?: 'localhost',
    'dbname'  => getenv('DB_NAME') ?: 'cvb_sistema',
    'user'    => getenv('DB_USER') ?: 'root',
    'pass'    => getenv('DB_PASS') ?: '',
    'charset' => 'utf8mb4',
];

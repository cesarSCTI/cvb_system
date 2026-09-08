<?php
require_once __DIR__ . '/../includes/auth.php';

$usuario = usuarioActual();

if (!$usuario) {
    jsonResponse(['autenticado' => false]);
}

jsonResponse(['autenticado' => true, 'usuario' => $usuario]);

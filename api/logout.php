<?php
require_once __DIR__ . '/../includes/auth.php';

cerrarSesion();
jsonResponse(['ok' => true]);

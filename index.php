<?php
require_once __DIR__ . '/includes/auth.php';

$usuario = usuarioActual();

if ($usuario) {
    header('Location: ' . ($usuario['rol'] === 'admin' ? 'admin/dashboard.html' : 'cliente/dashboard.html'));
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>CVB | Sistema de Seguimiento de Expedientes</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" type="image/png" href="assets/logoCVB.png">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-page">
    <div class="login-box">
        <img src="assets/logoCVB.png" alt="CVB" class="login-logo-img">
        <div class="login-title" style="margin-bottom: 30px;">Selecciona tu portal</div>
        <div style="display:flex; flex-direction:column; gap: 14px;">
            <a href="cliente/login.html" class="btn btn-primary">Portal Cliente (Notarías)</a>
            <a href="admin/login.html" class="btn btn-outline">Admin Portal (CVB)</a>
        </div>
    </div>
</body>
</html>

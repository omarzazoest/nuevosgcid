<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestor CID - U P V M</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Crect width='64' height='64' rx='14' fill='%238a2036'/%3E%3Ctext x='32' y='40' text-anchor='middle' font-family='Arial' font-size='28' font-weight='700' fill='white'%3ECID%3C/text%3E%3C/svg%3E">
    <link rel="stylesheet" href="<?= htmlspecialchars(app_url('assets/css/styles.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
    <header class="hero">
        <div class="hero__inner">
            <div>
                <p class="eyebrow">Universidad Politécnica del Valle de México</p>
                <h1>Gestor del Centro de Información y Documentación</h1>
                <p class="subtitle">Registro de acceso, control de usuarios y exportación de visitas para el CID y la biblioteca.</p>
            </div>
            <nav class="nav">
                <a href="<?= htmlspecialchars(app_url('index.php'), ENT_QUOTES, 'UTF-8') ?>">Inicio</a>
                <a href="<?= htmlspecialchars(app_url('registro.php'), ENT_QUOTES, 'UTF-8') ?>">Registro de alumno</a>
                <a href="<?= htmlspecialchars(app_url('gestor/login.php'), ENT_QUOTES, 'UTF-8') ?>">Gestor</a>
            </nav>
        </div>
    </header>
    <main class="container">

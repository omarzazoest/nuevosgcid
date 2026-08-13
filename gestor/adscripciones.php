<?php
session_start();
if (!isset($_SESSION['gestor_logged_in']) || !$_SESSION['gestor_logged_in']) {
    header('Location: ' . app_url('gestor/login.php'));
    exit;
}

require_once __DIR__ . '/../config/db.php';

$conn = null;
$message = '';
$messageType = 'success';

try {
    $conn = get_connection();
} catch (Throwable $e) {
    $message = $e->getMessage();
    $messageType = 'error';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $conn) {
    $nombre = trim($_POST['nombre_adscripcion'] ?? '');
    if ($nombre === '') {
        $message = 'Escribe el nombre de la adscripción.';
        $messageType = 'error';
    } else {
        $stmt = $conn->prepare('INSERT INTO adscripciones (nombre_adscripcion) VALUES (?)');
        $stmt->bind_param('s', $nombre);
        if ($stmt->execute()) {
            $message = 'Adscripción agregada correctamente.';
            $messageType = 'success';
        } else {
            $message = 'No se pudo guardar la adscripción: ' . $stmt->error;
            $messageType = 'error';
        }
        $stmt->close();
    }
}

$adscripciones = $conn ? $conn->query('SELECT id_adscripcion, nombre_adscripcion FROM adscripciones ORDER BY nombre_adscripcion') : null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Adscripciones CID</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Crect width='64' height='64' rx='14' fill='%230f4c81'/%3E%3Ctext x='32' y='40' text-anchor='middle' font-family='Arial' font-size='28' font-weight='700' fill='white'%3ECID%3C/text%3E%3C/svg%3E">
    <link rel="stylesheet" href="<?= htmlspecialchars(app_url('assets/css/styles.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body class="manager-body">
    <div class="sidebar-backdrop" id="sidebar-backdrop"></div>

    <aside class="manager-sidebar" id="manager-sidebar">
        <div class="brand-box">
            <div>
                <span class="brand-box__label">ERP / CRM</span>
                <h2>CID</h2>
            </div>
            <button class="sidebar-close" id="sidebar-close" type="button">✕</button>
        </div>
        <nav class="manager-nav">
            <a href="<?= htmlspecialchars(app_url('gestor/index.php'), ENT_QUOTES, 'UTF-8') ?>">Dashboard</a>
            <a href="<?= htmlspecialchars(app_url('gestor/usuarios.php'), ENT_QUOTES, 'UTF-8') ?>">Usuarios</a>
            <a href="<?= htmlspecialchars(app_url('gestor/carreras.php'), ENT_QUOTES, 'UTF-8') ?>">Carreras</a>
            <a class="active" href="<?= htmlspecialchars(app_url('gestor/adscripciones.php'), ENT_QUOTES, 'UTF-8') ?>">Adscripciones</a>
            <a href="<?= htmlspecialchars(app_url('gestor/tipos.php'), ENT_QUOTES, 'UTF-8') ?>">Tipos de usuario</a>
            <a href="<?= htmlspecialchars(app_url('gestor/visitas.php'), ENT_QUOTES, 'UTF-8') ?>">Visitas</a>
        </nav>
        <div class="sidebar-footer">
            <a href="<?= htmlspecialchars(app_url('gestor/logout.php'), ENT_QUOTES, 'UTF-8') ?>">Cerrar sesión</a>
        </div>
    </aside>

    <main class="manager-main">
        <header class="manager-topbar">
            <button class="sidebar-toggle" id="sidebar-toggle" type="button">☰</button>
            <div>
                <span class="topbar-label">UPVM</span>
                <h1>Adscripciones</h1>
            </div>
            <a href="<?= htmlspecialchars(app_url('export.php'), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-primary">Exportar Excel</a>
        </header>

        <div class="manager-content">
            <?php if ($message): ?>
                <div class="alert alert-<?= htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <section class="panel">
                <div class="panel-header">
                    <h3>Nueva adscripción</h3>
                </div>
                <form method="post" class="form-grid single-field">
                    <div class="field">
                        <label>Nombre de la adscripción</label>
                        <input type="text" name="nombre_adscripcion" required>
                    </div>
                    <div class="field field--submit">
                        <button type="submit" class="btn btn-primary">Guardar</button>
                    </div>
                </form>
            </section>

            <section class="panel">
                <div class="panel-header">
                    <h3>Listado de adscripciones</h3>
                </div>
                <div class="tag-list">
                    <?php if ($adscripciones): while ($adscripcion = $adscripciones->fetch_assoc()): ?>
                        <span class="tag">#<?= (int) $adscripcion['id_adscripcion'] ?> <?= htmlspecialchars($adscripcion['nombre_adscripcion'], ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endwhile; else: ?>
                        <span class="tag">Sin adscripciones registradas</span>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </main>

    <script>
        const sidebar = document.getElementById('manager-sidebar');
        const backdrop = document.getElementById('sidebar-backdrop');
        const toggle = document.getElementById('sidebar-toggle');
        const closeBtn = document.getElementById('sidebar-close');

        function openSidebar() {
            sidebar.classList.add('manager-sidebar--open');
            backdrop.classList.add('sidebar-backdrop--visible');
        }

        function closeSidebar() {
            sidebar.classList.remove('manager-sidebar--open');
            backdrop.classList.remove('sidebar-backdrop--visible');
        }

        toggle.addEventListener('click', openSidebar);
        closeBtn.addEventListener('click', closeSidebar);
        backdrop.addEventListener('click', closeSidebar);
    </script>
</body>
</html>

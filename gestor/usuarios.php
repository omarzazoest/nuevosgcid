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
    $nombre = trim($_POST['nombre'] ?? '');
    $apellido1 = trim($_POST['apellido1'] ?? '');
    $apellido2 = trim($_POST['apellido2'] ?? '');
    $identificador = trim($_POST['identificador'] ?? '');
    $tipo = (int) ($_POST['id_tipo_usuario'] ?? 0);
    $carrera = (int) ($_POST['id_carrera'] ?? 0);
    $adscripcion = (int) ($_POST['id_adscripcion'] ?? 0);

    if ($nombre === '' || $apellido1 === '' || $apellido2 === '' || $identificador === '' || $tipo <= 0) {
        $message = 'Completa todos los campos obligatorios.';
        $messageType = 'error';
    } else {
        $stmt = $conn->prepare('INSERT INTO usuarioscid (nombre, apellido1, apellido2, id_tipo_usuario, identificador, id_carrera, id_adscripcion) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('sssissi', $nombre, $apellido1, $apellido2, $tipo, $identificador, $carrera, $adscripcion);
        if ($stmt->execute()) {
            $message = 'Usuario agregado correctamente.';
            $messageType = 'success';
        } else {
            $message = 'No se pudo guardar el usuario: ' . $stmt->error;
            $messageType = 'error';
        }
        $stmt->close();
    }
}

$usuarios = $conn ? $conn->query('SELECT u.id_usuario, u.identificador, CONCAT(u.nombre, " ", u.apellido1, " ", u.apellido2) AS nombre, t.nombre_tipo, c.nombre_carrera, a.nombre_adscripcion FROM usuarioscid u LEFT JOIN tipos_usuarios t ON t.id_tipo_usuario = u.id_tipo_usuario LEFT JOIN carreras c ON c.id_carrera = u.id_carrera LEFT JOIN adscripciones a ON a.id_adscripcion = u.id_adscripcion ORDER BY u.id_usuario DESC') : null;
$tipos = $conn ? $conn->query('SELECT id_tipo_usuario, nombre_tipo FROM tipos_usuarios ORDER BY nombre_tipo') : null;
$carreras = $conn ? $conn->query('SELECT id_carrera, nombre_carrera FROM carreras ORDER BY nombre_carrera') : null;
$adscripciones = $conn ? $conn->query('SELECT id_adscripcion, nombre_adscripcion FROM adscripciones ORDER BY nombre_adscripcion') : null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usuarios CID</title>
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
            <a class="active" href="<?= htmlspecialchars(app_url('gestor/usuarios.php'), ENT_QUOTES, 'UTF-8') ?>">Usuarios</a>
            <a href="<?= htmlspecialchars(app_url('gestor/carreras.php'), ENT_QUOTES, 'UTF-8') ?>">Carreras</a>
            <a href="<?= htmlspecialchars(app_url('gestor/adscripciones.php'), ENT_QUOTES, 'UTF-8') ?>">Adscripciones</a>
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
                <h1>Usuarios del CID</h1>
            </div>
            <a href="<?= htmlspecialchars(app_url('export.php'), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-primary">Exportar Excel</a>
        </header>

        <div class="manager-content">
            <?php if ($message): ?>
                <div class="alert alert-<?= htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <section class="panel">
                <div class="panel-header">
                    <h3>Agregar usuario</h3>
                </div>
                <form method="post" class="form-grid">
                    <div class="field">
                        <label>Nombre</label>
                        <input type="text" name="nombre" required>
                    </div>
                    <div class="field">
                        <label>Apellido paterno</label>
                        <input type="text" name="apellido1" required>
                    </div>
                    <div class="field">
                        <label>Apellido materno</label>
                        <input type="text" name="apellido2" required>
                    </div>
                    <div class="field">
                        <label>Identificador</label>
                        <input type="text" name="identificador" required>
                    </div>
                    <div class="field">
                        <label>Tipo de usuario</label>
                        <select name="id_tipo_usuario" required>
                            <option value="">Selecciona</option>
                            <?php while ($tipo = $tipos ? $tipos->fetch_assoc() : null): ?>
                                <option value="<?= (int) $tipo['id_tipo_usuario'] ?>"><?= htmlspecialchars($tipo['nombre_tipo'], ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label>Carrera</label>
                        <select name="id_carrera">
                            <option value="0">Sin carrera</option>
                            <?php while ($carrera = $carreras ? $carreras->fetch_assoc() : null): ?>
                                <option value="<?= (int) $carrera['id_carrera'] ?>"><?= htmlspecialchars($carrera['nombre_carrera'], ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label>Adscripción</label>
                        <select name="id_adscripcion">
                            <option value="0">Sin adscripción</option>
                            <?php while ($ads = $adscripciones ? $adscripciones->fetch_assoc() : null): ?>
                                <option value="<?= (int) $ads['id_adscripcion'] ?>"><?= htmlspecialchars($ads['nombre_adscripcion'], ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="field field--submit">
                        <button type="submit" class="btn btn-primary">Guardar usuario</button>
                    </div>
                </form>
            </section>

            <section class="panel">
                <div class="panel-header">
                    <h3>Usuarios registrados</h3>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Identificador</th>
                                <th>Nombre</th>
                                <th>Tipo</th>
                                <th>Carrera</th>
                                <th>Adscripción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($usuarios): while ($usuario = $usuarios->fetch_assoc()): ?>
                                <tr>
                                    <td><?= (int) $usuario['id_usuario'] ?></td>
                                    <td><?= htmlspecialchars($usuario['identificador'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($usuario['nombre'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($usuario['nombre_tipo'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($usuario['nombre_carrera'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($usuario['nombre_adscripcion'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                </tr>
                            <?php endwhile; else: ?>
                                <tr><td colspan="6">Sin usuarios registrados.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
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

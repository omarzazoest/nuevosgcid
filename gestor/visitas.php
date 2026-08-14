<?php
session_start();
if (!isset($_SESSION['gestor_logged_in']) || !$_SESSION['gestor_logged_in']) {
    header('Location: ' . app_url('gestor/login.php'));
    exit;
}

require_once __DIR__ . '/../config/db.php';

$conn = null;
try {
    $conn = get_connection();
} catch (Throwable $e) {
    $_SESSION['manager_error'] = $e->getMessage();
    header('Location: ' . app_url('gestor/index.php'));
    exit;
}

$search = trim($_GET['q'] ?? '');
$visitas = null;

if ($search !== '') {
    $like = '%' . $search . '%';
    $stmtVisitas = $conn->prepare('SELECT i.id_ingreso, i.momento_ingreso, i.servicio, i.actividad, i.detalle, COALESCE(u.identificador, "Sin identificador") AS identificador, COALESCE(CASE WHEN i.id_usuario IS NULL THEN CONCAT("Externo - ", TRIM(CONCAT_WS(" ", i.nombre_ext, i.apellido1_ext, i.apellido2_ext))) ELSE TRIM(CONCAT_WS(" ", u.nombre, u.apellido1, u.apellido2)) END, "Sin usuario") AS usuario FROM ingresoscid i LEFT JOIN usuarioscid u ON u.id_usuario = i.id_usuario WHERE u.identificador LIKE ? OR u.nombre LIKE ? OR u.apellido1 LIKE ? OR u.apellido2 LIKE ? OR i.nombre_ext LIKE ? OR i.apellido1_ext LIKE ? OR i.apellido2_ext LIKE ? OR i.servicio LIKE ? OR i.actividad LIKE ? OR i.detalle LIKE ? ORDER BY i.momento_ingreso DESC LIMIT 100');
    $stmtVisitas->bind_param('ssssssssss', $like, $like, $like, $like, $like, $like, $like, $like, $like, $like);
    $stmtVisitas->execute();
    $visitas = $stmtVisitas->get_result();
} else {
    $visitas = $conn->query('SELECT i.id_ingreso, i.momento_ingreso, i.servicio, i.actividad, i.detalle, COALESCE(u.identificador, "Sin identificador") AS identificador, COALESCE(CASE WHEN i.id_usuario IS NULL THEN CONCAT("Externo - ", TRIM(CONCAT_WS(" ", i.nombre_ext, i.apellido1_ext, i.apellido2_ext))) ELSE TRIM(CONCAT_WS(" ", u.nombre, u.apellido1, u.apellido2)) END, "Sin usuario") AS usuario FROM ingresoscid i LEFT JOIN usuarioscid u ON u.id_usuario = i.id_usuario ORDER BY i.momento_ingreso DESC LIMIT 100');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visitas CID</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Crect width='64' height='64' rx='14' fill='%238a2036'/%3E%3Ctext x='32' y='40' text-anchor='middle' font-family='Arial' font-size='28' font-weight='700' fill='white'%3ECID%3C/text%3E%3C/svg%3E">
    <link rel="stylesheet" href="<?= htmlspecialchars(app_url('assets/css/styles.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<!--dedicado a la memoria de pechocho, oct 2025. -Omar -->
<body class="manager-body">
    <div class="sidebar-backdrop" id="sidebar-backdrop"></div>

    <aside class="manager-sidebar" id="manager-sidebar">
        <div class="brand-box">
            <div>
                <h2>CID</h2>
            </div>
            <button class="sidebar-close" id="sidebar-close" type="button">✕</button>
        </div>
        <nav class="manager-nav">
            <a href="<?= htmlspecialchars(app_url('gestor/index.php'), ENT_QUOTES, 'UTF-8') ?>">Dashboard</a>
            <a href="<?= htmlspecialchars(app_url('gestor/usuarios.php'), ENT_QUOTES, 'UTF-8') ?>">Usuarios</a>
            <a href="<?= htmlspecialchars(app_url('gestor/carreras.php'), ENT_QUOTES, 'UTF-8') ?>">Carreras</a>
            <a href="<?= htmlspecialchars(app_url('gestor/adscripciones.php'), ENT_QUOTES, 'UTF-8') ?>">Adscripciones</a>
            <a href="<?= htmlspecialchars(app_url('gestor/tipos.php'), ENT_QUOTES, 'UTF-8') ?>">Tipos de usuario</a>
            <a class="active" href="<?= htmlspecialchars(app_url('gestor/visitas.php'), ENT_QUOTES, 'UTF-8') ?>">Visitas</a>
            <a href="<?= htmlspecialchars(app_url('gestor/carga_masiva.php'), ENT_QUOTES, 'UTF-8') ?>">Carga masiva</a>
            <a href="<?= htmlspecialchars(app_url('gestor/libros.php'), ENT_QUOTES, 'UTF-8') ?>">Libros</a>
            <a href="<?= htmlspecialchars(app_url('gestor/prestamos.php'), ENT_QUOTES, 'UTF-8') ?>">Préstamos</a>
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
                <h1>Visitas CID</h1>
            </div>
            <a href="<?= htmlspecialchars(app_url('export.php'), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-primary">Exportar Excel</a>
        </header>

        <div class="manager-content">
            <section class="panel">
                <div class="panel-header">
                    <h3>Historial de ingresos (últimos 100)</h3>
                </div>
                <form method="get" class="form-grid single-field" style="margin-bottom:1rem;">
                    <div class="field">
                        <label>Buscar visitas</label>
                        <input type="text" name="q" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" placeholder="Usuario, identificador, servicio, actividad, detalle...">
                    </div>
                    <div class="field field--submit"><button type="submit" class="btn btn-primary">Buscar</button></div>
                </form>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Fecha y hora</th>
                                <th>Usuario</th>
                                <th>Identificador</th>
                                <th>Servicio</th>
                                <th>Actividad</th>
                                <th>Detalle</th>
                            </tr>
                        </thead>
                        <tbody id="visitas-body">
                            <?php while ($registro = $visitas->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($registro['momento_ingreso'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($registro['usuario'] ?? 'Sin usuario', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($registro['identificador'] ?? 'Sin identificador', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($registro['servicio'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($registro['actividad'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($registro['detalle'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                </tr>
                            <?php endwhile; ?>
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
        const WS_CLIENT_URL = <?= json_encode(websocket_client_url(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        const visitasBody = document.getElementById('visitas-body');

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function addVisitRow(payload) {
            if (!visitasBody || !payload) return;

            const row = document.createElement('tr');
            row.innerHTML = '' +
                '<td>' + escapeHtml(payload.momento || '') + '</td>' +
                '<td>' + escapeHtml(payload.usuario || 'Sin usuario') + '</td>' +
                '<td>' + escapeHtml(payload.identificador || 'Sin identificador') + '</td>' +
                '<td>' + escapeHtml(payload.servicio || '') + '</td>' +
                '<td>' + escapeHtml(payload.actividad || '') + '</td>' +
                '<td>' + escapeHtml(payload.detalle || '') + '</td>';

            visitasBody.prepend(row);

            while (visitasBody.rows.length > 100) {
                visitasBody.deleteRow(visitasBody.rows.length - 1);
            }
        }

        function startVisitSocket() {
            if (!WS_CLIENT_URL) return;

            let socket;

            try {
                socket = new WebSocket(WS_CLIENT_URL);
            } catch (error) {
                return;
            }

            socket.addEventListener('message', function(event) {
                try {
                    const data = JSON.parse(event.data);
                    if (!data || data.type !== 'new_visit') {
                        return;
                    }

                    addVisitRow(data.payload || {});
                } catch (e) {
                    // Ignorar mensajes no JSON.
                }
            });

            socket.addEventListener('open', function() {
                socket.send(JSON.stringify({ type: 'subscribe', source: 'visitas' }));
            });

            socket.addEventListener('close', function() {
                setTimeout(startVisitSocket, 2500);
            });
        }

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
        startVisitSocket();
    </script>
</body>
</html>

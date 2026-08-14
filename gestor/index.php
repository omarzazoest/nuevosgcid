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

if (!empty($conn)) {
    $usuarios = (int) $conn->query('SELECT COUNT(*) AS total FROM usuarioscid')->fetch_assoc()['total'];
    $visitas = (int) $conn->query('SELECT COUNT(*) AS total FROM ingresoscid')->fetch_assoc()['total'];
    $carreras = $conn->query('SELECT id_carrera, nombre_carrera FROM carreras ORDER BY nombre_carrera')->fetch_all(MYSQLI_ASSOC);
    $adscripciones = $conn->query('SELECT id_adscripcion, nombre_adscripcion FROM adscripciones ORDER BY nombre_adscripcion')->fetch_all(MYSQLI_ASSOC);
    $ultimosIngresos = $conn->query('SELECT i.id_ingreso, i.momento_ingreso, i.servicio, i.actividad, i.detalle, COALESCE(u.identificador, "Sin identificador") AS identificador, COALESCE(CONCAT(u.nombre, " ", u.apellido1, " ", u.apellido2), "Sin usuario") AS usuario FROM ingresoscid i LEFT JOIN usuarioscid u ON u.id_usuario = i.id_usuario ORDER BY i.momento_ingreso DESC LIMIT 12');
} else {
    $usuarios = 0;
    $visitas = 0;
    $carreras = [];
    $adscripciones = [];
    $ultimosIngresos = null;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard CID</title>
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
            <a class="active" href="<?= htmlspecialchars(app_url('gestor/index.php'), ENT_QUOTES, 'UTF-8') ?>">Dashboard</a>
            <a href="<?= htmlspecialchars(app_url('gestor/usuarios.php'), ENT_QUOTES, 'UTF-8') ?>">Usuarios</a>
            <a href="<?= htmlspecialchars(app_url('gestor/carreras.php'), ENT_QUOTES, 'UTF-8') ?>">Carreras</a>
            <a href="<?= htmlspecialchars(app_url('gestor/adscripciones.php'), ENT_QUOTES, 'UTF-8') ?>">Adscripciones</a>
            <a href="<?= htmlspecialchars(app_url('gestor/tipos.php'), ENT_QUOTES, 'UTF-8') ?>">Tipos de usuario</a>
            <a href="<?= htmlspecialchars(app_url('gestor/visitas.php'), ENT_QUOTES, 'UTF-8') ?>">Visitas</a>
            <a href="<?= htmlspecialchars(app_url('gestor/carga_masiva.php'), ENT_QUOTES, 'UTF-8') ?>">Carga masiva</a>
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
                <h1>Dashboard del CID</h1>
            </div>
            <a href="<?= htmlspecialchars(app_url('export.php'), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-primary">Exportar Excel</a>
        </header>

        <div class="manager-content">
            <div id="ws-visit-alert" class="alert alert-success" style="display:none;"></div>
            <?php if ($message): ?>
                <div class="alert alert-<?= htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <section class="stats-grid">
                <article class="stats-card">
                    <span>Usuarios</span>
                    <strong><?= $usuarios ?></strong>
                </article>
                <article class="stats-card">
                    <span>Ingresos CID</span>
                    <strong id="visitas-total"><?= $visitas ?></strong>
                </article>
                <article class="stats-card">
                    <span>Carreras</span>
                    <strong><?= count($carreras) ?></strong>
                </article>
                <article class="stats-card">
                    <span>Adscripciones</span>
                    <strong><?= count($adscripciones) ?></strong>
                </article>
            </section>

            <section class="panel">
                <div class="panel-header">
                    <h3>Ingresos CID</h3>
                    <a href="<?= htmlspecialchars(app_url('gestor/visitas.php'), ENT_QUOTES, 'UTF-8') ?>" class="text-link">Ver todos</a>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Fecha / hora</th>
                                <th>Usuario</th>
                                <th>Identificador</th>
                                <th>Servicio</th>
                                <th>Actividad</th>
                                <th>Detalle</th>
                            </tr>
                        </thead>
                        <tbody id="ingresos-body">
                            <?php if ($ultimosIngresos): while ($ingreso = $ultimosIngresos->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($ingreso['momento_ingreso'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($ingreso['usuario'] ?? 'Sin usuario', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($ingreso['identificador'] ?? 'Sin identificador', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($ingreso['servicio'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($ingreso['actividad'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($ingreso['detalle'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr><td colspan="6">No hay ingresos registrados.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </main>

    <script>
        const WS_CLIENT_URL = <?= json_encode(websocket_client_url(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        const sidebar = document.getElementById('manager-sidebar');
        const backdrop = document.getElementById('sidebar-backdrop');
        const toggle = document.getElementById('sidebar-toggle');
        const closeBtn = document.getElementById('sidebar-close');
        const wsVisitAlert = document.getElementById('ws-visit-alert');
        const ingresosBody = document.getElementById('ingresos-body');
        const visitasTotal = document.getElementById('visitas-total');

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

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function addVisitToTable(payload) {
            if (!ingresosBody || !payload) return;

            const emptyRow = ingresosBody.querySelector('tr td[colspan="6"]');
            if (emptyRow) {
                emptyRow.parentElement.remove();
            }

            const row = document.createElement('tr');
            row.innerHTML = '' +
                '<td>' + escapeHtml(payload.momento || '') + '</td>' +
                '<td>' + escapeHtml(payload.usuario || 'Sin usuario') + '</td>' +
                '<td>' + escapeHtml(payload.identificador || 'Sin identificador') + '</td>' +
                '<td>' + escapeHtml(payload.servicio || '') + '</td>' +
                '<td>' + escapeHtml(payload.actividad || '') + '</td>' +
                '<td>' + escapeHtml(payload.detalle || '') + '</td>';

            ingresosBody.prepend(row);

            while (ingresosBody.rows.length > 12) {
                ingresosBody.deleteRow(ingresosBody.rows.length - 1);
            }
        }

        function increaseVisitCounter() {
            if (!visitasTotal) return;
            const current = Number(visitasTotal.textContent || '0');
            if (!Number.isNaN(current)) {
                visitasTotal.textContent = String(current + 1);
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

                    if (wsVisitAlert) {
                        wsVisitAlert.textContent = 'Nueva visita registrada: ' + (data.payload && data.payload.usuario ? data.payload.usuario : 'usuario') + '.';
                        wsVisitAlert.style.display = 'block';
                    }

                    addVisitToTable(data.payload || {});
                    increaseVisitCounter();
                } catch (e) {
                    // Ignorar mensajes no JSON.
                }
            });

            socket.addEventListener('close', function() {
                setTimeout(startVisitSocket, 2500);
            });
        }

        startVisitSocket();
    </script>
</body>
</html>

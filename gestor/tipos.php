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

$editId = (int) ($_GET['edit_id'] ?? 0);
try {
    $conn = get_connection();
} catch (Throwable $e) {
    $message = $e->getMessage();
    $messageType = 'error';
}

$editing = null;
if ($editId > 0 && $conn) {
    $editing = $conn->query('SELECT id_tipo_usuario, nombre_tipo, numero_digitos_identificador FROM tipos_usuarios WHERE id_tipo_usuario = ' . $editId . ' LIMIT 1')->fetch_assoc();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $conn) {
    $action = $_POST['action'] ?? 'save_tipo';
    if ($action === 'delete_tipo') {
        $deleteId = (int) ($_POST['id_tipo_usuario'] ?? 0);
        if ($deleteId > 0) {
            $conn->query('DELETE FROM tipos_usuarios WHERE id_tipo_usuario = ' . $deleteId);
            $message = 'Tipo eliminado correctamente.';
            $messageType = 'success';
        }
    } else {
        $idTipo = (int) ($_POST['id_tipo_usuario'] ?? 0);
        $nombre = trim($_POST['nombre_tipo'] ?? '');
        $digitos = (int) ($_POST['numero_digitos_identificador'] ?? 0);

        if ($nombre === '' || $digitos < 0) {
            $message = 'Escribe el tipo y la cantidad de dígitos.';
            $messageType = 'error';
        } else {
            if ($idTipo > 0) {
                $stmt = $conn->prepare('UPDATE tipos_usuarios SET nombre_tipo = ?, numero_digitos_identificador = ? WHERE id_tipo_usuario = ?');
                $stmt->bind_param('sii', $nombre, $digitos, $idTipo);
                $message = 'Tipo actualizado correctamente.';
            } else {
                $stmt = $conn->prepare('INSERT INTO tipos_usuarios (nombre_tipo, numero_digitos_identificador) VALUES (?, ?)');
                $stmt->bind_param('si', $nombre, $digitos);
                $message = 'Tipo de usuario agregado correctamente.';
            }
            if ($stmt->execute()) {
                $messageType = 'success';
            } else {
                $message = 'No se pudo guardar: ' . $stmt->error;
                $messageType = 'error';
            }
            $stmt->close();
        }
    }
}

$tipos = $conn ? $conn->query('SELECT id_tipo_usuario, nombre_tipo, numero_digitos_identificador FROM tipos_usuarios ORDER BY nombre_tipo') : null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tipos de usuario</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Crect width='64' height='64' rx='14' fill='%238a2036'/%3E%3Ctext x='32' y='40' text-anchor='middle' font-family='Arial' font-size='28' font-weight='700' fill='white'%3ECID%3C/text%3E%3C/svg%3E">
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
            <a href="<?= htmlspecialchars(app_url('gestor/adscripciones.php'), ENT_QUOTES, 'UTF-8') ?>">Adscripciones</a>
            <a class="active" href="<?= htmlspecialchars(app_url('gestor/tipos.php'), ENT_QUOTES, 'UTF-8') ?>">Tipos de usuario</a>
            <a href="<?= htmlspecialchars(app_url('gestor/visitas.php'), ENT_QUOTES, 'UTF-8') ?>">Visitas</a>
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
                <h1>Tipos de usuario</h1>
            </div>
            <a href="<?= htmlspecialchars(app_url('export.php'), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-primary">Exportar Excel</a>
        </header>

        <div class="manager-content">
            <?php if ($message): ?>
                <div class="alert alert-<?= htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <section class="panel">
                <div class="panel-header">
                    <h3><?= $editing ? 'Editar tipo de usuario' : 'Nuevo tipo de usuario' ?></h3>
                </div>
                <form method="post" class="form-grid single-field">
                    <input type="hidden" name="action" value="save_tipo">
                    <?php if ($editing): ?>
                        <input type="hidden" name="id_tipo_usuario" value="<?= (int) $editing['id_tipo_usuario'] ?>">
                    <?php endif; ?>
                    <div class="field">
                        <label>Nombre del tipo</label>
                        <input type="text" name="nombre_tipo" value="<?= htmlspecialchars($editing['nombre_tipo'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>
                    <div class="field">
                        <label>Número de dígitos</label>
                        <input type="number" name="numero_digitos_identificador" value="<?= htmlspecialchars((string) ($editing['numero_digitos_identificador'] ?? 8), ENT_QUOTES, 'UTF-8') ?>" min="0" required>
                    </div>
                    <div class="field field--submit">
                        <button type="submit" class="btn btn-primary"><?= $editing ? 'Actualizar' : 'Guardar' ?></button>
                        <?php if ($editing): ?>
                            <a href="<?= htmlspecialchars(app_url('gestor/tipos.php'), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-secondary">Cancelar</a>
                        <?php endif; ?>
                    </div>
                </form>
            </section>

            <section class="panel">
                <div class="panel-header">
                    <h3>Tipos configurados</h3>
                </div>
                <div class="tag-list" style="display:block;">
                    <?php if ($tipos): while ($tipo = $tipos->fetch_assoc()): ?>
                        <div class="tag-row">
                            <span class="tag"><?= htmlspecialchars($tipo['nombre_tipo'], ENT_QUOTES, 'UTF-8') ?> (<?= (int) $tipo['numero_digitos_identificador'] ?>)</span>
                            <div class="row-actions">
                                <a href="<?= htmlspecialchars(app_url('gestor/tipos.php?edit_id=' . (int) $tipo['id_tipo_usuario']), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-small btn-secondary">Editar</a>
                                <form method="post" class="inline-form">
                                    <input type="hidden" name="action" value="delete_tipo">
                                    <input type="hidden" name="id_tipo_usuario" value="<?= (int) $tipo['id_tipo_usuario'] ?>">
                                    <button type="submit" class="btn btn-small btn-danger" onclick="return confirm('¿Deseas eliminar este tipo?');">Eliminar</button>
                                </form>
                            </div>
                        </div>
                    <?php endwhile; else: ?>
                        <span class="tag">Sin tipos disponibles</span>
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

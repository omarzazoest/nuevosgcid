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

$editingUser = null;
$editId = (int) ($_GET['edit_id'] ?? 0);
if ($editId > 0 && $conn) {
    $editingUser = $conn->query('SELECT u.id_usuario, u.identificador, u.nombre, u.apellido1, u.apellido2, u.id_tipo_usuario, u.id_carrera, u.id_adscripcion, t.numero_digitos_identificador, t.nombre_tipo FROM usuarioscid u LEFT JOIN tipos_usuarios t ON t.id_tipo_usuario = u.id_tipo_usuario WHERE u.id_usuario = ' . (int) $editId . ' LIMIT 1')->fetch_assoc();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $conn) {
    $action = $_POST['action'] ?? 'save_user';

    if ($action === 'delete_user') {
        $deleteId = (int) ($_POST['id_usuario'] ?? 0);
        if ($deleteId > 0) {
            $conn->query('DELETE FROM usuarioscid WHERE id_usuario = ' . $deleteId);
            $message = 'Usuario eliminado correctamente.';
            $messageType = 'success';
        }
    } else {
        $idUsuario = (int) ($_POST['id_usuario'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        $apellido1 = trim($_POST['apellido1'] ?? '');
        $apellido2 = trim($_POST['apellido2'] ?? '');
        $identificador = trim($_POST['identificador'] ?? '');
        $tipo = (int) ($_POST['id_tipo_usuario'] ?? 0);
        $carreraRaw = (int) ($_POST['id_carrera'] ?? 0);
        $adscripcionRaw = (int) ($_POST['id_adscripcion'] ?? 0);

        $tipoInfo = $tipo > 0 ? $conn->query('SELECT nombre_tipo, numero_digitos_identificador FROM tipos_usuarios WHERE id_tipo_usuario = ' . $tipo . ' LIMIT 1')->fetch_assoc() : null;
        $expectedDigits = $tipoInfo ? (int) $tipoInfo['numero_digitos_identificador'] : 0;
        $role = $tipoInfo ? strtolower((string) $tipoInfo['nombre_tipo']) : '';
        $requireCarrera = str_contains($role, 'alumno') || str_contains($role, 'egresado');
        $requireAdscripcion = str_contains($role, 'profesor') || str_contains($role, 'administrativo') || str_contains($role, 'personal');
        $carrera = $requireCarrera && $carreraRaw > 0 ? $carreraRaw : null;
        $adscripcion = $requireAdscripcion && $adscripcionRaw > 0 ? $adscripcionRaw : null;

        if ($nombre === '' || $apellido1 === '' || $apellido2 === '' || $tipo <= 0 || ($expectedDigits > 0 && $identificador === '')) {
            $message = 'Completa todos los campos obligatorios.';
            $messageType = 'error';
        } elseif ($expectedDigits > 0 && strlen($identificador) !== $expectedDigits) {
            $message = 'El identificador debe tener exactamente ' . $expectedDigits . ' dígitos para este tipo de usuario.';
            $messageType = 'error';
        } elseif ($requireCarrera && $carrera === null) {
            $message = 'Selecciona una carrera para alumnos o egresados.';
            $messageType = 'error';
        } elseif ($requireAdscripcion && $adscripcion === null) {
            $message = 'Selecciona una adscripción para personal académico o administrativo.';
            $messageType = 'error';
        } else {
            if (!$requireCarrera) {
                $carrera = null;
            }
            if (!$requireAdscripcion) {
                $adscripcion = null;
            }

            if ($idUsuario > 0) {
                $stmt = $conn->prepare('UPDATE usuarioscid SET nombre = ?, apellido1 = ?, apellido2 = ?, identificador = ?, id_tipo_usuario = ?, id_carrera = ?, id_adscripcion = ? WHERE id_usuario = ?');
                $stmt->bind_param('ssssiiis', $nombre, $apellido1, $apellido2, $identificador, $tipo, $carrera, $adscripcion, $idUsuario);
                $message = 'Usuario actualizado correctamente.';
            } else {
                $stmt = $conn->prepare('INSERT INTO usuarioscid (nombre, apellido1, apellido2, id_tipo_usuario, identificador, id_carrera, id_adscripcion) VALUES (?, ?, ?, ?, ?, ?, ?)');
                $stmt->bind_param('sssissi', $nombre, $apellido1, $apellido2, $tipo, $identificador, $carrera, $adscripcion);
                $message = 'Usuario agregado correctamente.';
            }
            if ($stmt->execute()) {
                $messageType = 'success';
            } else {
                $message = 'No se pudo guardar el usuario: ' . $stmt->error;
                $messageType = 'error';
            }
            $stmt->close();
            if ($messageType === 'success') {
                $editId = 0;
                $editingUser = null;
            }
        }
    }
}

if ($editId > 0 && $conn && !$editingUser) {
    $editingUser = $conn->query('SELECT u.id_usuario, u.identificador, u.nombre, u.apellido1, u.apellido2, u.id_tipo_usuario, u.id_carrera, u.id_adscripcion, t.numero_digitos_identificador, t.nombre_tipo FROM usuarioscid u LEFT JOIN tipos_usuarios t ON t.id_tipo_usuario = u.id_tipo_usuario WHERE u.id_usuario = ' . $editId . ' LIMIT 1')->fetch_assoc();
}

$usuarios = $conn ? $conn->query('SELECT u.id_usuario, u.identificador, CONCAT(u.nombre, " ", u.apellido1, " ", u.apellido2) AS nombre, u.id_tipo_usuario, t.nombre_tipo, t.numero_digitos_identificador, c.nombre_carrera, a.nombre_adscripcion FROM usuarioscid u LEFT JOIN tipos_usuarios t ON t.id_tipo_usuario = u.id_tipo_usuario LEFT JOIN carreras c ON c.id_carrera = u.id_carrera LEFT JOIN adscripciones a ON a.id_adscripcion = u.id_adscripcion ORDER BY u.id_usuario DESC') : null;
$tipos = $conn ? $conn->query('SELECT id_tipo_usuario, nombre_tipo, numero_digitos_identificador FROM tipos_usuarios ORDER BY nombre_tipo') : null;
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
                    <h3><?= $editingUser ? 'Editar usuario' : 'Agregar usuario' ?></h3>
                </div>
                <form method="post" class="form-grid">
                    <input type="hidden" name="action" value="save_user">
                    <?php if ($editingUser): ?>
                        <input type="hidden" name="id_usuario" value="<?= (int) $editingUser['id_usuario'] ?>">
                    <?php endif; ?>

                    <div class="field">
                        <label>Nombre</label>
                        <input type="text" name="nombre" value="<?= htmlspecialchars($editingUser['nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>
                    <div class="field">
                        <label>Apellido paterno</label>
                        <input type="text" name="apellido1" value="<?= htmlspecialchars($editingUser['apellido1'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>
                    <div class="field">
                        <label>Apellido materno</label>
                        <input type="text" name="apellido2" value="<?= htmlspecialchars($editingUser['apellido2'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>
                    <div class="field">
                        <label>Identificador</label>
                        <input id="identificador" type="text" name="identificador" value="<?= htmlspecialchars($editingUser['identificador'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required maxlength="20" placeholder="Ej. 20241001">
                        <small id="identificador-help" class="helper-text">Selecciona un tipo de usuario para ver la longitud requerida.</small>
                    </div>
                    <div class="field">
                        <label>Tipo de usuario</label>
                        <select id="id_tipo_usuario" name="id_tipo_usuario" required>
                            <option value="">Selecciona</option>
                            <?php while ($tipo = $tipos ? $tipos->fetch_assoc() : null): ?>
                                <option value="<?= (int) $tipo['id_tipo_usuario'] ?>" data-digits="<?= (int) $tipo['numero_digitos_identificador'] ?>" data-role="<?= htmlspecialchars(strtolower((string) $tipo['nombre_tipo']), ENT_QUOTES, 'UTF-8') ?>" <?= ($editingUser && (int) $editingUser['id_tipo_usuario'] === (int) $tipo['id_tipo_usuario']) ? 'selected' : '' ?>><?= htmlspecialchars($tipo['nombre_tipo'], ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div id="carrera-field" class="field" style="display: <?= ($editingUser && (str_contains(strtolower((string) ($editingUser['nombre_tipo'] ?? '')), 'alumno') || str_contains(strtolower((string) ($editingUser['nombre_tipo'] ?? '')), 'egresado'))) ? 'flex' : 'none' ?>;">
                        <label>Carrera</label>
                        <select name="id_carrera">
                            <option value="0">Sin carrera</option>
                            <?php while ($carrera = $carreras ? $carreras->fetch_assoc() : null): ?>
                                <option value="<?= (int) $carrera['id_carrera'] ?>" <?= ($editingUser && (int) $editingUser['id_carrera'] === (int) $carrera['id_carrera']) ? 'selected' : '' ?>><?= htmlspecialchars($carrera['nombre_carrera'], ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div id="adscripcion-field" class="field" style="display: <?= ($editingUser && (str_contains(strtolower((string) ($editingUser['nombre_tipo'] ?? '')), 'profesor') || str_contains(strtolower((string) ($editingUser['nombre_tipo'] ?? '')), 'administrativo') || str_contains(strtolower((string) ($editingUser['nombre_tipo'] ?? '')), 'personal'))) ? 'flex' : 'none' ?>;">
                        <label>Adscripción</label>
                        <select name="id_adscripcion">
                            <option value="0">Sin adscripción</option>
                            <?php while ($ads = $adscripciones ? $adscripciones->fetch_assoc() : null): ?>
                                <option value="<?= (int) $ads['id_adscripcion'] ?>" <?= ($editingUser && (int) $editingUser['id_adscripcion'] === (int) $ads['id_adscripcion']) ? 'selected' : '' ?>><?= htmlspecialchars($ads['nombre_adscripcion'], ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="field field--submit">
                        <button type="submit" class="btn btn-primary"><?= $editingUser ? 'Actualizar usuario' : 'Guardar usuario' ?></button>
                        <?php if ($editingUser): ?>
                            <a href="<?= htmlspecialchars(app_url('gestor/usuarios.php'), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-secondary">Cancelar</a>
                        <?php endif; ?>
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
                                <th>Acciones</th>
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
                                    <td>
                                        <div class="row-actions">
                                            <a href="<?= htmlspecialchars(app_url('gestor/usuarios.php?edit_id=' . (int) $usuario['id_usuario']), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-small btn-secondary">Editar</a>
                                            <form method="post" class="inline-form">
                                                <input type="hidden" name="action" value="delete_user">
                                                <input type="hidden" name="id_usuario" value="<?= (int) $usuario['id_usuario'] ?>">
                                                <button type="submit" class="btn btn-small btn-danger" onclick="return confirm('¿Deseas eliminar este usuario?');">Eliminar</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; else: ?>
                                <tr><td colspan="7">Sin usuarios registrados.</td></tr>
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
        const tipoSelect = document.getElementById('id_tipo_usuario');
        const identificadorInput = document.getElementById('identificador');
        const identificadorHelp = document.getElementById('identificador-help');
        const carreraField = document.getElementById('carrera-field');
        const adscripcionField = document.getElementById('adscripcion-field');
        const carreraSelect = carreraField.querySelector('select');
        const adscripcionSelect = adscripcionField.querySelector('select');

        function updateTypeBehavior() {
            if (!tipoSelect) return;
            const selected = tipoSelect.options[tipoSelect.selectedIndex];
            const digits = selected && selected.dataset.digits ? Number(selected.dataset.digits) : 0;
            const role = (selected && selected.dataset.role ? selected.dataset.role : '').toLowerCase();

            const noIdentifierRequired = digits === 0;

            if (identificadorInput) {
                identificadorInput.disabled = noIdentifierRequired;
                identificadorInput.required = !noIdentifierRequired;
                identificadorInput.maxLength = digits > 0 ? String(digits) : 20;
                identificadorInput.placeholder = noIdentifierRequired ? 'No requiere identificador' : (digits > 0 ? 'Debe tener ' + digits + ' dígitos' : 'Ej. 20241001');
                if (noIdentifierRequired) {
                    identificadorInput.value = '';
                }
                if (identificadorHelp) {
                    identificadorHelp.textContent = noIdentifierRequired ? 'Este tipo no requiere identificador.' : (digits > 0 ? 'El tipo seleccionado exige ' + digits + ' dígitos.' : 'Selecciona un tipo para conocer la longitud requerida.');
                }
            }

            const requireCarrera = role.includes('alumno') || role.includes('egresado');
            const requireAdscripcion = role.includes('profesor') || role.includes('administrativo') || role.includes('personal');

            if (carreraField) {
                carreraField.style.display = requireCarrera ? 'flex' : 'none';
                carreraSelect.required = requireCarrera;
            }
            if (adscripcionField) {
                adscripcionField.style.display = requireAdscripcion ? 'flex' : 'none';
                adscripcionSelect.required = requireAdscripcion;
            }

            if (!requireCarrera) {
                if (carreraSelect) carreraSelect.value = '0';
            }
            if (!requireAdscripcion) {
                if (adscripcionSelect) adscripcionSelect.value = '0';
            }
        }

        function openSidebar() {
            sidebar.classList.add('manager-sidebar--open');
            backdrop.classList.add('sidebar-backdrop--visible');
        }

        function closeSidebar() {
            sidebar.classList.remove('manager-sidebar--open');
            backdrop.classList.remove('sidebar-backdrop--visible');
        }

        if (tipoSelect) {
            tipoSelect.addEventListener('change', updateTypeBehavior);
        }
        updateTypeBehavior();
        toggle.addEventListener('click', openSidebar);
        closeBtn.addEventListener('click', closeSidebar);
        backdrop.addEventListener('click', closeSidebar);
    </script>
</body>
</html>

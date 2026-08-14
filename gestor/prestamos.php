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
$moduleReady = false;

function table_exists(mysqli $conn, string $tableName): bool
{
    $safeName = $conn->real_escape_string($tableName);
    $result = $conn->query("SHOW TABLES LIKE '" . $safeName . "'");
    return $result && $result->num_rows > 0;
}

function loan_status_label(string $status): string
{
    return match ($status) {
        'Activo' => 'Activo',
        'Devuelto' => 'Devuelto',
        'Vencido' => 'Vencido',
        'Cancelado' => 'Cancelado',
        default => $status,
    };
}

try {
    $conn = get_connection();
    $moduleReady = table_exists($conn, 'libroscid') && table_exists($conn, 'prestamos_libroscid') && table_exists($conn, 'usuarioscid');
} catch (Throwable $e) {
    $message = $e->getMessage();
    $messageType = 'error';
}

if (($_GET['action'] ?? '') === 'buscar_usuario') {
    header('Content-Type: application/json; charset=UTF-8');

    if (!$conn || !$moduleReady) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'message' => 'Módulo no disponible.']);
        exit;
    }

    $identificadorBusqueda = trim($_GET['identificador'] ?? '');
    if ($identificadorBusqueda === '') {
        echo json_encode(['ok' => false, 'message' => 'Ingresa un identificador para buscar.']);
        exit;
    }

    $stmtBuscar = $conn->prepare('SELECT u.id_usuario, u.identificador, CONCAT(u.nombre, " ", u.apellido1, " ", u.apellido2) AS nombre FROM usuarioscid u WHERE u.identificador = ? LIMIT 1');
    $stmtBuscar->bind_param('s', $identificadorBusqueda);
    $stmtBuscar->execute();
    $usuarioEncontrado = $stmtBuscar->get_result()->fetch_assoc();
    $stmtBuscar->close();

    if (!$usuarioEncontrado) {
        echo json_encode(['ok' => false, 'message' => 'No existe usuario con ese identificador.']);
        exit;
    }

    echo json_encode([
        'ok' => true,
        'usuario' => [
            'id_usuario' => (int) $usuarioEncontrado['id_usuario'],
            'identificador' => (string) $usuarioEncontrado['identificador'],
            'nombre' => (string) $usuarioEncontrado['nombre'],
        ],
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $conn && $moduleReady) {
    $action = $_POST['action'] ?? 'save_prestamo';

    if ($action === 'registrar_prestamo') {
        $idLibro = (int) ($_POST['id_libro'] ?? 0);
        $tipoUsuario = trim($_POST['tipo_usuario'] ?? '');
        $idUsuario = (int) ($_POST['id_usuario'] ?? 0);
        $identificadorExterno = trim($_POST['identificador_externo'] ?? '');
        $nombreExterno = trim($_POST['nombre_externo'] ?? '');
        $apellido1Externo = trim($_POST['apellido1_externo'] ?? '');
        $apellido2Externo = trim($_POST['apellido2_externo'] ?? '');
        $sexoExterno = trim($_POST['sexo_externo'] ?? '');
        $fechaPrestamo = trim($_POST['fecha_prestamo'] ?? date('Y-m-d'));
        $fechaDevolucionProgramada = trim($_POST['fecha_devolucion_programada'] ?? '');
        $cantidad = (int) ($_POST['cantidad'] ?? 1);
        $observaciones = trim($_POST['observaciones'] ?? '');

        $isExternal = $tipoUsuario === 'Externo';
        $requireInternalUser = in_array($tipoUsuario, ['Alumno', 'Egresado', 'Profesor', 'Administrativo'], true);

        if ($idLibro <= 0 || $cantidad < 1 || $fechaDevolucionProgramada === '') {
            $message = 'Completa libro, cantidad y fecha de devolución programada.';
            $messageType = 'error';
        } elseif ($isExternal && ($nombreExterno === '' || $apellido1Externo === '' || $apellido2Externo === '' || $sexoExterno === '')) {
            $message = 'Para usuario externo debes completar nombre, apellidos y sexo.';
            $messageType = 'error';
        } elseif ($requireInternalUser && $idUsuario <= 0) {
            $message = 'Selecciona un usuario registrado.';
            $messageType = 'error';
        } else {
            $conn->begin_transaction();
            try {
                $stmtStock = $conn->prepare('UPDATE libroscid SET existencias_disponibles = existencias_disponibles - ?, estado = CASE WHEN existencias_disponibles - ? <= 0 THEN "Prestado" ELSE estado END WHERE id_libro = ? AND existencias_disponibles >= ?');
                $stmtStock->bind_param('iiii', $cantidad, $cantidad, $idLibro, $cantidad);
                $stmtStock->execute();
                $stockUpdated = $stmtStock->affected_rows;
                $stmtStock->close();

                if ($stockUpdated < 1) {
                    throw new RuntimeException('No hay suficientes existencias disponibles para este préstamo.');
                }

                if ($isExternal) {
                    $stmt = $conn->prepare('INSERT INTO prestamos_libroscid (id_libro, id_usuario, identificador_externo, nombre_externo, apellido1_externo, apellido2_externo, sexo_externo, tipo_usuario, fecha_prestamo, fecha_devolucion_programada, cantidad, estado, observaciones) VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, "Activo", ?)');
                    $stmt->bind_param('isssssssiss', $idLibro, $identificadorExterno, $nombreExterno, $apellido1Externo, $apellido2Externo, $sexoExterno, $tipoUsuario, $fechaPrestamo, $fechaDevolucionProgramada, $cantidad, $observaciones);
                } else {
                    $stmt = $conn->prepare('INSERT INTO prestamos_libroscid (id_libro, id_usuario, identificador_externo, nombre_externo, apellido1_externo, apellido2_externo, sexo_externo, tipo_usuario, fecha_prestamo, fecha_devolucion_programada, cantidad, estado, observaciones) VALUES (?, ?, NULL, NULL, NULL, NULL, NULL, ?, ?, ?, ?, "Activo", ?)');
                    $stmt->bind_param('iisssis', $idLibro, $idUsuario, $tipoUsuario, $fechaPrestamo, $fechaDevolucionProgramada, $cantidad, $observaciones);
                }

                $stmt->execute();
                $stmt->close();

                $conn->commit();
                $message = 'Préstamo registrado correctamente.';
                $messageType = 'success';
            } catch (Throwable $e) {
                $conn->rollback();
                $message = $e->getMessage();
                $messageType = 'error';
            }
        }
    }

    if ($action === 'devolver_prestamo') {
        $idPrestamo = (int) ($_POST['id_prestamo'] ?? 0);
        if ($idPrestamo > 0) {
            $conn->begin_transaction();
            try {
                $stmtPrestamo = $conn->prepare('SELECT id_libro, cantidad, estado FROM prestamos_libroscid WHERE id_prestamo = ? LIMIT 1');
                $stmtPrestamo->bind_param('i', $idPrestamo);
                $stmtPrestamo->execute();
                $prestamo = $stmtPrestamo->get_result()->fetch_assoc();
                $stmtPrestamo->close();

                if (!$prestamo) {
                    throw new RuntimeException('El préstamo no existe.');
                }

                if ($prestamo['estado'] !== 'Activo' && $prestamo['estado'] !== 'Vencido') {
                    throw new RuntimeException('El préstamo ya fue cerrado.');
                }

                $stmtDev = $conn->prepare('UPDATE prestamos_libroscid SET fecha_devolucion_real = CURDATE(), estado = "Devuelto" WHERE id_prestamo = ?');
                $stmtDev->bind_param('i', $idPrestamo);
                $stmtDev->execute();
                $stmtDev->close();

                $stmtBook = $conn->prepare('UPDATE libroscid SET existencias_disponibles = existencias_disponibles + ?, estado = "Disponible" WHERE id_libro = ?');
                $stmtBook->bind_param('ii', $prestamo['cantidad'], $prestamo['id_libro']);
                $stmtBook->execute();
                $stmtBook->close();

                $conn->commit();
                $message = 'Préstamo marcado como devuelto.';
                $messageType = 'success';
            } catch (Throwable $e) {
                $conn->rollback();
                $message = $e->getMessage();
                $messageType = 'error';
            }
        }
    }
}

$libros = [];
$prestamos = [];
$search = trim($_GET['q'] ?? '');
if ($conn && $moduleReady) {
    $resLibros = $conn->query('SELECT id_libro, titulo, autor, existencias_disponibles, existencias_total FROM libroscid ORDER BY titulo LIMIT 200');
    if ($resLibros) {
        $libros = $resLibros->fetch_all(MYSQLI_ASSOC);
    }

    if ($search !== '') {
        $like = '%' . $search . '%';
        $stmtPrestamos = $conn->prepare('SELECT p.id_prestamo, p.fecha_prestamo, p.fecha_devolucion_programada, p.fecha_devolucion_real, p.cantidad, p.estado, p.tipo_usuario, p.identificador_externo, p.nombre_externo, p.apellido1_externo, p.apellido2_externo, l.titulo, COALESCE(CONCAT(u.nombre, " ", u.apellido1, " ", u.apellido2), CONCAT("Externo - ", TRIM(CONCAT_WS(" ", p.nombre_externo, p.apellido1_externo, p.apellido2_externo))), "Sin usuario") AS usuario FROM prestamos_libroscid p INNER JOIN libroscid l ON l.id_libro = p.id_libro LEFT JOIN usuarioscid u ON u.id_usuario = p.id_usuario WHERE l.titulo LIKE ? OR u.identificador LIKE ? OR CONCAT_WS(" ", u.nombre, u.apellido1, u.apellido2) LIKE ? OR p.identificador_externo LIKE ? OR CONCAT_WS(" ", p.nombre_externo, p.apellido1_externo, p.apellido2_externo) LIKE ? OR p.estado LIKE ? ORDER BY p.id_prestamo DESC LIMIT 100');
        $stmtPrestamos->bind_param('ssssss', $like, $like, $like, $like, $like, $like);
        $stmtPrestamos->execute();
        $prestamos = $stmtPrestamos->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtPrestamos->close();
    } else {
        $resPrestamos = $conn->query('SELECT p.id_prestamo, p.fecha_prestamo, p.fecha_devolucion_programada, p.fecha_devolucion_real, p.cantidad, p.estado, p.tipo_usuario, p.identificador_externo, p.nombre_externo, p.apellido1_externo, p.apellido2_externo, l.titulo, COALESCE(CONCAT(u.nombre, " ", u.apellido1, " ", u.apellido2), CONCAT("Externo - ", TRIM(CONCAT_WS(" ", p.nombre_externo, p.apellido1_externo, p.apellido2_externo))), "Sin usuario") AS usuario FROM prestamos_libroscid p INNER JOIN libroscid l ON l.id_libro = p.id_libro LEFT JOIN usuarioscid u ON u.id_usuario = p.id_usuario ORDER BY p.id_prestamo DESC LIMIT 100');
        if ($resPrestamos) {
            $prestamos = $resPrestamos->fetch_all(MYSQLI_ASSOC);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Préstamos CID</title>
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
            <a href="<?= htmlspecialchars(app_url('gestor/visitas.php'), ENT_QUOTES, 'UTF-8') ?>">Visitas</a>
            <a href="<?= htmlspecialchars(app_url('gestor/carga_masiva.php'), ENT_QUOTES, 'UTF-8') ?>">Carga masiva</a>
            <a href="<?= htmlspecialchars(app_url('gestor/libros.php'), ENT_QUOTES, 'UTF-8') ?>">Libros</a>
            <a class="active" href="<?= htmlspecialchars(app_url('gestor/prestamos.php'), ENT_QUOTES, 'UTF-8') ?>">Préstamos</a>
        </nav>
        <div class="sidebar-footer"><a href="<?= htmlspecialchars(app_url('gestor/logout.php'), ENT_QUOTES, 'UTF-8') ?>">Cerrar sesión</a></div>
    </aside>

    <main class="manager-main">
        <header class="manager-topbar">
            <button class="sidebar-toggle" id="sidebar-toggle" type="button">☰</button>
            <div><span class="topbar-label">UPVM</span><h1>Préstamos de libros</h1></div>
        </header>

        <div class="manager-content">
            <?php if ($message): ?><div class="alert alert-<?= htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
            <?php if (!$moduleReady): ?>
                <section class="panel">
                    <h3>Módulo pendiente de SQL</h3>
                    <p>Importa el script [sql/modulo_libros_prestamos.sql](../sql/modulo_libros_prestamos.sql) en tu base `cidb` para activar esta pantalla.</p>
                </section>
            <?php else: ?>
                <section class="panel">
                    <div class="panel-header"><h3>Nuevo préstamo</h3></div>
                    <form method="post" class="form-grid">
                        <input type="hidden" name="action" value="registrar_prestamo">
                        <div class="field"><label>Libro</label><select name="id_libro" required><option value="">Selecciona libro</option><?php foreach ($libros as $libro): ?><option value="<?= (int) $libro['id_libro'] ?>"><?= htmlspecialchars($libro['titulo'], ENT_QUOTES, 'UTF-8') ?> (<?= (int) $libro['existencias_disponibles'] ?>/<?= (int) $libro['existencias_total'] ?>)</option><?php endforeach; ?></select></div>
                        <div class="field"><label>Tipo de usuario</label><select id="tipo_usuario_prestamo" name="tipo_usuario" required><option value="">Selecciona</option><option>Alumno</option><option>Egresado</option><option>Profesor</option><option>Administrativo</option><option>Externo</option></select></div>
                        <div id="usuarioInternoBox" class="field" style="grid-column:1/-1;">
                            <label>Buscar usuario por identificador</label>
                            <div class="row-actions">
                                <input type="text" id="identificador_usuario" placeholder="Ej. 20240001">
                                <button type="button" class="btn btn-secondary" id="buscarUsuarioBtn">Buscar</button>
                            </div>
                            <input type="hidden" name="id_usuario" id="id_usuario_prestamo" value="0">
                            <input type="text" id="usuario_encontrado" readonly placeholder="Aquí aparecerá el usuario seleccionado">
                            <small id="estadoBusquedaUsuario" class="small"></small>
                        </div>
                        <div id="externoBox" class="form-grid" style="display:none; grid-column:1/-1;">
                            <div class="field"><label>Identificador externo</label><input type="text" name="identificador_externo"></div>
                            <div class="field"><label>Nombre</label><input type="text" name="nombre_externo"></div>
                            <div class="field"><label>Apellido paterno</label><input type="text" name="apellido1_externo"></div>
                            <div class="field"><label>Apellido materno</label><input type="text" name="apellido2_externo"></div>
                            <div class="field"><label>Sexo</label><select name="sexo_externo"><option value="">Selecciona</option><option>Femenino</option><option>Masculino</option><option>Otro</option></select></div>
                        </div>
                        <div class="field"><label>Fecha préstamo</label><input type="date" name="fecha_prestamo" value="<?= date('Y-m-d') ?>" required></div>
                        <div class="field"><label>Fecha devolución programada</label><input type="date" name="fecha_devolucion_programada" required></div>
                        <div class="field"><label>Cantidad</label><input type="number" name="cantidad" value="1" min="1" required></div>
                        <div class="field"><label>Observaciones</label><input type="text" name="observaciones"></div>
                        <div class="field field--submit"><button type="submit" class="btn btn-primary">Guardar préstamo</button></div>
                    </form>
                </section>
                <section class="panel">
                    <div class="panel-header"><h3>Préstamos registrados (últimos 100)</h3></div>
                    <form method="get" class="form-grid single-field" style="margin-bottom:1rem;">
                        <div class="field">
                            <label>Buscar en préstamos</label>
                            <input type="text" name="q" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" placeholder="Libro, usuario, identificador, estado...">
                        </div>
                        <div class="field field--submit"><button type="submit" class="btn btn-primary">Buscar</button></div>
                    </form>
                    <div class="table-wrap">
                        <table>
                            <thead><tr><th>ID</th><th>Libro</th><th>Usuario</th><th>Prestamo</th><th>Devolución</th><th>Estado</th><th>Acciones</th></tr></thead>
                            <tbody>
                                <?php if ($prestamos): foreach ($prestamos as $prestamo): ?>
                                    <tr>
                                        <td><?= (int) $prestamo['id_prestamo'] ?></td>
                                        <td><?= htmlspecialchars($prestamo['titulo'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($prestamo['usuario'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($prestamo['fecha_prestamo'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($prestamo['fecha_devolucion_programada'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars(loan_status_label((string) $prestamo['estado']), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>
                                            <?php if ($prestamo['estado'] === 'Activo' || $prestamo['estado'] === 'Vencido'): ?>
                                                <form method="post" class="inline-form"><input type="hidden" name="action" value="devolver_prestamo"><input type="hidden" name="id_prestamo" value="<?= (int) $prestamo['id_prestamo'] ?>"><button type="submit" class="btn btn-small btn-secondary">Devolver</button></form>
                                            <?php else: ?>
                                                <span class="small"><?= htmlspecialchars($prestamo['fecha_devolucion_real'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; else: ?>
                                    <tr><td colspan="7">Sin préstamos registrados.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endif; ?>
        </div>
    </main>

    <script>
        const sidebar = document.getElementById('manager-sidebar');
        const backdrop = document.getElementById('sidebar-backdrop');
        const toggle = document.getElementById('sidebar-toggle');
        const closeBtn = document.getElementById('sidebar-close');
        const tipoUsuarioPrestamo = document.getElementById('tipo_usuario_prestamo');
        const externoBox = document.getElementById('externoBox');
        const usuarioInternoBox = document.getElementById('usuarioInternoBox');
        const buscarUsuarioBtn = document.getElementById('buscarUsuarioBtn');
        const identificadorUsuario = document.getElementById('identificador_usuario');
        const idUsuarioPrestamo = document.getElementById('id_usuario_prestamo');
        const usuarioEncontrado = document.getElementById('usuario_encontrado');
        const estadoBusquedaUsuario = document.getElementById('estadoBusquedaUsuario');
        const buscarUsuarioUrl = <?= json_encode(app_url('gestor/prestamos.php?action=buscar_usuario'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

        function openSidebar() { sidebar.classList.add('manager-sidebar--open'); backdrop.classList.add('sidebar-backdrop--visible'); }
        function closeSidebar() { sidebar.classList.remove('manager-sidebar--open'); backdrop.classList.remove('sidebar-backdrop--visible'); }
        function resetUsuarioInterno() {
            if (idUsuarioPrestamo) idUsuarioPrestamo.value = '0';
            if (usuarioEncontrado) usuarioEncontrado.value = '';
            if (estadoBusquedaUsuario) estadoBusquedaUsuario.textContent = '';
        }

        async function buscarUsuarioInterno() {
            if (!identificadorUsuario || !estadoBusquedaUsuario) return;
            const identificador = identificadorUsuario.value.trim();
            if (!identificador) {
                estadoBusquedaUsuario.textContent = 'Ingresa un identificador.';
                return;
            }

            resetUsuarioInterno();
            estadoBusquedaUsuario.textContent = 'Buscando usuario...';

            try {
                const response = await fetch(buscarUsuarioUrl + '&identificador=' + encodeURIComponent(identificador), {
                    headers: { 'Accept': 'application/json' },
                });
                const data = await response.json();

                if (!response.ok || !data.ok) {
                    estadoBusquedaUsuario.textContent = data.message || 'No se encontró usuario.';
                    return;
                }

                if (idUsuarioPrestamo) idUsuarioPrestamo.value = String(data.usuario.id_usuario || 0);
                if (usuarioEncontrado) {
                    usuarioEncontrado.value = (data.usuario.identificador || '') + ' - ' + (data.usuario.nombre || '');
                }
                estadoBusquedaUsuario.textContent = 'Usuario seleccionado.';
            } catch (error) {
                estadoBusquedaUsuario.textContent = 'No se pudo realizar la búsqueda.';
            }
        }

        function updateLoanMode() {
            const isExternal = tipoUsuarioPrestamo && tipoUsuarioPrestamo.value === 'Externo';
            if (externoBox) externoBox.style.display = isExternal ? 'grid' : 'none';
            if (usuarioInternoBox) usuarioInternoBox.style.display = isExternal ? 'none' : 'flex';
            if (isExternal) {
                resetUsuarioInterno();
            }
        }
        toggle.addEventListener('click', openSidebar);
        closeBtn.addEventListener('click', closeSidebar);
        backdrop.addEventListener('click', closeSidebar);
        if (tipoUsuarioPrestamo) { tipoUsuarioPrestamo.addEventListener('change', updateLoanMode); updateLoanMode(); }
        if (buscarUsuarioBtn) buscarUsuarioBtn.addEventListener('click', buscarUsuarioInterno);
        if (identificadorUsuario) {
            identificadorUsuario.addEventListener('keydown', function(event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    buscarUsuarioInterno();
                }
            });
        }
    </script>
</body>
</html>

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

try {
    $conn = get_connection();
    $moduleReady = table_exists($conn, 'libroscid');
} catch (Throwable $e) {
    $message = $e->getMessage();
    $messageType = 'error';
}

$editing = null;
$editId = (int) ($_GET['edit_id'] ?? 0);

if ($conn && $moduleReady && $editId > 0) {
    $stmtEdit = $conn->prepare('SELECT * FROM libroscid WHERE id_libro = ? LIMIT 1');
    $stmtEdit->bind_param('i', $editId);
    $stmtEdit->execute();
    $editing = $stmtEdit->get_result()->fetch_assoc();
    $stmtEdit->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $conn && $moduleReady) {
    $action = $_POST['action'] ?? 'save_libro';

    if ($action === 'delete_libro') {
        $deleteId = (int) ($_POST['id_libro'] ?? 0);
        if ($deleteId > 0) {
            $stmtDelete = $conn->prepare('DELETE FROM libroscid WHERE id_libro = ?');
            $stmtDelete->bind_param('i', $deleteId);
            if ($stmtDelete->execute()) {
                $message = 'Libro eliminado correctamente.';
                $messageType = 'success';
            } else {
                $message = 'No se pudo eliminar el libro.';
                $messageType = 'error';
            }
            $stmtDelete->close();
        }
    } else {
        $idLibro = (int) ($_POST['id_libro'] ?? 0);
        $isbn = trim($_POST['isbn'] ?? '');
        $titulo = trim($_POST['titulo'] ?? '');
        $autor = trim($_POST['autor'] ?? '');
        $editorial = trim($_POST['editorial'] ?? '');
        $anioPublicacion = trim($_POST['anio_publicacion'] ?? '');
        $edicion = trim($_POST['edicion'] ?? '');
        $categoria = trim($_POST['categoria'] ?? '');
        $ubicacion = trim($_POST['ubicacion'] ?? '');
        $existenciasTotal = (int) ($_POST['existencias_total'] ?? 1);
        $existenciasDisponibles = (int) ($_POST['existencias_disponibles'] ?? $existenciasTotal);
        $estado = trim($_POST['estado'] ?? 'Disponible');
        $observaciones = trim($_POST['observaciones'] ?? '');

        if ($titulo === '' || $autor === '' || $existenciasTotal < 1 || $existenciasDisponibles < 0) {
            $message = 'Completa los campos obligatorios del libro.';
            $messageType = 'error';
        } elseif ($existenciasDisponibles > $existenciasTotal) {
            $message = 'Las existencias disponibles no pueden ser mayores que el total.';
            $messageType = 'error';
        } else {
            $anioPublicacionDb = $anioPublicacion !== '' ? (int) $anioPublicacion : null;
            $editorialDb = $editorial !== '' ? $editorial : null;
            $edicionDb = $edicion !== '' ? $edicion : null;
            $categoriaDb = $categoria !== '' ? $categoria : null;
            $ubicacionDb = $ubicacion !== '' ? $ubicacion : null;
            $observacionesDb = $observaciones !== '' ? $observaciones : null;

            if ($idLibro > 0) {
                $stmt = $conn->prepare('UPDATE libroscid SET isbn = ?, titulo = ?, autor = ?, editorial = ?, anio_publicacion = ?, edicion = ?, categoria = ?, ubicacion = ?, existencias_total = ?, existencias_disponibles = ?, estado = ?, observaciones = ? WHERE id_libro = ?');
                $stmt->bind_param('ssssisssiissi', $isbn, $titulo, $autor, $editorialDb, $anioPublicacionDb, $edicionDb, $categoriaDb, $ubicacionDb, $existenciasTotal, $existenciasDisponibles, $estado, $observacionesDb, $idLibro);
                $message = 'Libro actualizado correctamente.';
            } else {
                $stmt = $conn->prepare('INSERT INTO libroscid (isbn, titulo, autor, editorial, anio_publicacion, edicion, categoria, ubicacion, existencias_total, existencias_disponibles, estado, observaciones) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $stmt->bind_param('sssssississs', $isbn, $titulo, $autor, $editorialDb, $anioPublicacionDb, $edicionDb, $categoriaDb, $ubicacionDb, $existenciasTotal, $existenciasDisponibles, $estado, $observacionesDb);
                $message = 'Libro agregado correctamente.';
            }

            try {
                if ($stmt->execute()) {
                    $messageType = 'success';
                    $editId = 0;
                    $editing = null;
                } else {
                    $message = 'No se pudo guardar el libro: ' . $stmt->error;
                    $messageType = 'error';
                }
            } catch (mysqli_sql_exception $e) {
                if ((int) $e->getCode() === 1062) {
                    $message = 'El ISBN ya existe. Usa un ISBN diferente o deja el campo vacío.';
                    $messageType = 'error';
                } else {
                    $message = 'No se pudo guardar el libro: ' . $e->getMessage();
                    $messageType = 'error';
                }
            }
            $stmt->close();
        }
    }
}

$libros = [];
$search = trim($_GET['q'] ?? '');
if ($conn && $moduleReady) {
    if ($search !== '') {
        $like = '%' . $search . '%';
        $stmtList = $conn->prepare('SELECT id_libro, isbn, titulo, autor, editorial, anio_publicacion, categoria, ubicacion, existencias_total, existencias_disponibles, estado FROM libroscid WHERE isbn LIKE ? OR titulo LIKE ? OR autor LIKE ? OR categoria LIKE ? OR estado LIKE ? ORDER BY id_libro DESC LIMIT 100');
        $stmtList->bind_param('sssss', $like, $like, $like, $like, $like);
        $stmtList->execute();
        $libros = $stmtList->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtList->close();
    } else {
        $result = $conn->query('SELECT id_libro, isbn, titulo, autor, editorial, anio_publicacion, categoria, ubicacion, existencias_total, existencias_disponibles, estado FROM libroscid ORDER BY id_libro DESC LIMIT 100');
        if ($result) {
            $libros = $result->fetch_all(MYSQLI_ASSOC);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Libros CID</title>
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
            <a href="<?= htmlspecialchars(app_url('gestor/tipos.php'), ENT_QUOTES, 'UTF-8') ?>">Tipos de usuario</a>
            <a href="<?= htmlspecialchars(app_url('gestor/visitas.php'), ENT_QUOTES, 'UTF-8') ?>">Visitas</a>
            <a href="<?= htmlspecialchars(app_url('gestor/carga_masiva.php'), ENT_QUOTES, 'UTF-8') ?>">Carga masiva</a>
            <a class="active" href="<?= htmlspecialchars(app_url('gestor/libros.php'), ENT_QUOTES, 'UTF-8') ?>">Libros</a>
            <a href="<?= htmlspecialchars(app_url('gestor/prestamos.php'), ENT_QUOTES, 'UTF-8') ?>">Préstamos</a>
        </nav>
        <div class="sidebar-footer"><a href="<?= htmlspecialchars(app_url('gestor/logout.php'), ENT_QUOTES, 'UTF-8') ?>">Cerrar sesión</a></div>
    </aside>

    <main class="manager-main">
        <header class="manager-topbar">
            <button class="sidebar-toggle" id="sidebar-toggle" type="button">☰</button>
            <div><span class="topbar-label">UPVM</span><h1>Libros del CID</h1></div>
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
                    <div class="panel-header"><h3><?= $editing ? 'Editar libro' : 'Nuevo libro' ?></h3></div>
                    <form method="post" class="form-grid">
                        <input type="hidden" name="action" value="save_libro">
                        <?php if ($editing): ?><input type="hidden" name="id_libro" value="<?= (int) $editing['id_libro'] ?>"><?php endif; ?>
                        <div class="field"><label>ISBN</label><input type="text" name="isbn" value="<?= htmlspecialchars($editing['isbn'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></div>
                        <div class="field"><label>Título</label><input type="text" name="titulo" value="<?= htmlspecialchars($editing['titulo'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required></div>
                        <div class="field"><label>Autor</label><input type="text" name="autor" value="<?= htmlspecialchars($editing['autor'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required></div>
                        <div class="field"><label>Editorial</label><input type="text" name="editorial" value="<?= htmlspecialchars($editing['editorial'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></div>
                        <div class="field"><label>Año</label><input type="number" name="anio_publicacion" value="<?= htmlspecialchars((string) ($editing['anio_publicacion'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" min="1900" max="2100"></div>
                        <div class="field"><label>Edición</label><input type="text" name="edicion" value="<?= htmlspecialchars($editing['edicion'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></div>
                        <div class="field"><label>Categoría</label><input type="text" name="categoria" value="<?= htmlspecialchars($editing['categoria'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></div>
                        <div class="field"><label>Ubicación</label><input type="text" name="ubicacion" value="<?= htmlspecialchars($editing['ubicacion'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></div>
                        <div class="field"><label>Existencias total</label><input type="number" name="existencias_total" value="<?= htmlspecialchars((string) ($editing['existencias_total'] ?? 1), ENT_QUOTES, 'UTF-8') ?>" min="1" required></div>
                        <div class="field"><label>Existencias disponibles</label><input type="number" name="existencias_disponibles" value="<?= htmlspecialchars((string) ($editing['existencias_disponibles'] ?? 1), ENT_QUOTES, 'UTF-8') ?>" min="0" required></div>
                        <div class="field"><label>Estado</label><select name="estado"><option>Disponible</option><option>Prestado</option><option>Mantenimiento</option><option>Baja</option></select></div>
                        <div class="field"><label>Observaciones</label><input type="text" name="observaciones" value="<?= htmlspecialchars($editing['observaciones'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></div>
                        <div class="field field--submit"><button type="submit" class="btn btn-primary"><?= $editing ? 'Actualizar' : 'Guardar' ?></button></div>
                    </form>
                </section>
                <section class="panel">
                    <div class="panel-header"><h3>Libros registrados (últimos 100)</h3></div>
                    <form method="get" class="form-grid single-field" style="margin-bottom:1rem;">
                        <div class="field">
                            <label>Buscar en libros</label>
                            <input type="text" name="q" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" placeholder="ISBN, título, autor, categoría, estado...">
                        </div>
                        <div class="field field--submit"><button type="submit" class="btn btn-primary">Buscar</button></div>
                    </form>
                    <div class="table-wrap">
                        <table>
                            <thead><tr><th>ID</th><th>Título</th><th>Autor</th><th>ISBN</th><th>Existencias</th><th>Estado</th><th>Acciones</th></tr></thead>
                            <tbody>
                                <?php if ($libros): foreach ($libros as $libro): ?>
                                    <tr>
                                        <td><?= (int) $libro['id_libro'] ?></td>
                                        <td><?= htmlspecialchars($libro['titulo'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($libro['autor'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($libro['isbn'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= (int) $libro['existencias_disponibles'] ?> / <?= (int) $libro['existencias_total'] ?></td>
                                        <td><?= htmlspecialchars($libro['estado'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>
                                            <div class="row-actions">
                                                <a class="btn btn-small btn-secondary" href="<?= htmlspecialchars(app_url('gestor/libros.php?edit_id=' . (int) $libro['id_libro']), ENT_QUOTES, 'UTF-8') ?>">Editar</a>
                                                <form method="post" class="inline-form"><input type="hidden" name="action" value="delete_libro"><input type="hidden" name="id_libro" value="<?= (int) $libro['id_libro'] ?>"><button type="submit" class="btn btn-small btn-danger" onclick="return confirm('¿Eliminar libro?');">Eliminar</button></form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; else: ?>
                                    <tr><td colspan="7">Sin libros registrados.</td></tr>
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
        function openSidebar() { sidebar.classList.add('manager-sidebar--open'); backdrop.classList.add('sidebar-backdrop--visible'); }
        function closeSidebar() { sidebar.classList.remove('manager-sidebar--open'); backdrop.classList.remove('sidebar-backdrop--visible'); }
        toggle.addEventListener('click', openSidebar);
        closeBtn.addEventListener('click', closeSidebar);
        backdrop.addEventListener('click', closeSidebar);
    </script>
</body>
</html>

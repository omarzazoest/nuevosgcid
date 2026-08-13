<?php
session_start();
require_once __DIR__ . '/config/db.php';

$conn = null;
$message = '';
$messageType = '';

try {
    $conn = get_connection();
} catch (Throwable $e) {
    $message = $e->getMessage();
    $messageType = 'error';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($message)) {
    $identificador = trim($_POST['identificador'] ?? '');
    $servicio = trim($_POST['servicio'] ?? 'CID');
    $actividad = trim($_POST['actividad'] ?? '');
    $detalle = trim($_POST['detalle'] ?? '');

    if ($identificador === '' || $actividad === '') {
        $message = 'Ingresa tu identificador y selecciona una actividad.';
        $messageType = 'error';
    } else {
        $stmt = $conn->prepare('SELECT id_usuario, nombre, apellido1, apellido2 FROM usuarioscid WHERE identificador = ? LIMIT 1');
        $stmt->bind_param('s', $identificador);
        $stmt->execute();
        $result = $stmt->get_result();
        $usuario = $result->fetch_assoc();
        $stmt->close();

        if (!$usuario) {
            $message = 'No existe un usuario con ese identificador. Acércate al gestor del CID para darte de alta.';
            $messageType = 'error';
        } else {
            $insert = $conn->prepare('INSERT INTO ingresoscid (servicio, actividad, detalle, id_usuario) VALUES (?, ?, ?, ?)');
            $insert->bind_param('sssi', $servicio, $actividad, $detalle, $usuario['id_usuario']);
            $insert->execute();
            $insert->close();
            $message = 'Visita registrada correctamente para ' . htmlspecialchars($usuario['nombre'] . ' ' . $usuario['apellido1'] . ' ' . $usuario['apellido2'], ENT_QUOTES, 'UTF-8') . '.';
            $messageType = 'success';
        }
    }
}

include __DIR__ . '/includes/header.php';
?>

<div class="grid">
    <section class="card">
        <h2>Registro rápido para alumnos</h2>
        <p>Solo ingresa tu identificador, selecciona la actividad y el detalle del motivo de tu visita. La hora se registra automáticamente.</p>
        <?php if ($message): ?>
        <div class="alert <?= $messageType ?>"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <form method="post">
            <div class="form-group">
                <label for="identificador">Identificador / matrícula</label>
                <input id="identificador" name="identificador" required placeholder="Ej. 20240001">
            </div>
            <div class="form-group">
                <label for="servicio">Servicio</label>
                <select id="servicio" name="servicio">
                    <option value="CID">CID</option>
                    <option value="Biblioteca">Biblioteca</option>
                    <option value="Laboratorio">Laboratorio</option>
                </select>
            </div>
            <div class="form-group">
                <label for="actividad">Actividad</label>
                <select id="actividad" name="actividad" required>
                    <option value="">Selecciona una opción</option>
                    <option value="Consulta">Consulta</option>
                    <option value="Préstamo">Préstamo</option>
                    <option value="Devolución">Devolución</option>
                    <option value="Asesoría">Asesoría</option>
                    <option value="Otro">Otro</option>
                </select>
            </div>
            <div class="form-group">
                <label for="detalle">Detalle</label>
                <textarea id="detalle" name="detalle" placeholder="Describe brevemente el motivo de tu visita"></textarea>
            </div>
            <button class="btn" type="submit">Registrar visita</button>
        </form>
    </section>
    <section class="card">
        <h3>¿Qué pasa después?</h3>
        <ul>
            <li>Tu sesión queda registrada con la fecha y hora actual.</li>
            <li>El gestor podrá ver el historial de visitas y exportarlo a Excel.</li>
            <li>Si tu matrícula no aparece, pide alta en el área del CID.</li>
        </ul>
        <p class="small">La identidad institucional de la UPVM se conserva en cada registro.</p>
    </section>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

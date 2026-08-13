<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (isset($_SESSION['gestor_logged_in']) && $_SESSION['gestor_logged_in']) {
    header('Location: ' . app_url('gestor/index.php'));
    exit;
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $password = trim($_POST['password'] ?? '');
    if ($usuario === 'upvm' && $password === 'cid2026') {
        $_SESSION['gestor_logged_in'] = true;
        header('Location: ' . app_url('gestor/index.php'));
        exit;
    }
    $message = 'Credenciales inválidas.';
}

include __DIR__ . '/../includes/header.php';
?>

<div class="grid">
    <section class="card">
        <h2>Acceso al panel del gestor</h2>
        <p>Ingresa con las credenciales de administración del CID.</p>
        <?php if ($message): ?>
        <div class="alert error"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <form method="post">
            <div class="form-group">
                <label for="usuario">Usuario</label>
                <input id="usuario" name="usuario" required value="upvm">
            </div>
            <div class="form-group">
                <label for="password">Contraseña</label>
                <input id="password" name="password" type="password" required>
            </div>
            <button class="btn" type="submit">Entrar</button>
        </form>
    </section>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

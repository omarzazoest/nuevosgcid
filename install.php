<?php
require_once __DIR__ . '/config/db.php';
include __DIR__ . '/includes/header.php';
?>

<div class="card">
    <h2>Base de datos CID</h2>
    <p>Este proyecto ya está preparado para trabajar con la base exportada del archivo SQL del proyecto. Importa el dump de CID y configura las variables de entorno antes de arrancar la aplicación.</p>
    <div class="alert error">
        El proceso de instalación automática fue quitado para evitar duplicar la estructura de la base. Usa el archivo cidb.sql y las variables de entorno del proyecto.
    </div>
    <a class="btn" href="<?= htmlspecialchars(app_url('index.php'), ENT_QUOTES, 'UTF-8') ?>">Volver al inicio</a>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

<?php
session_start();
require_once __DIR__ . '/config/db.php';

$homeImageUrl = '';
$homeImages = glob(__DIR__ . '/img/*.{png,jpg,jpeg,webp,gif,svg}', GLOB_BRACE);
if ($homeImages && isset($homeImages[0])) {
    $homeImageUrl = app_url('img/' . rawurlencode(basename($homeImages[0])));
}

$usuarios = 0;
$visitas = 0;
$ultima = ['momento_ingreso' => 'Sin datos'];
$dbError = null;

try {
    $conn = get_connection();
    $usuarios = (int) $conn->query('SELECT COUNT(*) AS total FROM usuarioscid')->fetch_assoc()['total'];
    $visitas = (int) $conn->query('SELECT COUNT(*) AS total FROM ingresoscid')->fetch_assoc()['total'];
    $ultima = $conn->query('SELECT momento_ingreso FROM ingresoscid ORDER BY momento_ingreso DESC LIMIT 1')->fetch_assoc() ?: ['momento_ingreso' => 'Sin datos'];
} catch (Throwable $e) {
    $dbError = $e->getMessage();
}

include __DIR__ . '/includes/header.php';
?>

<?php if ($homeImageUrl !== ''): ?>
<section class="home-banner">
    <div class="home-banner__image">
        <img src="<?= htmlspecialchars($homeImageUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Imagen institucional del CID">
    </div>
    <div class="home-banner__panel">
        <p class="eyebrow">Centro de Información y Documentación</p>
        <h2>Registro y control de visitas del CID</h2>
        <p>Accede rápido al registro de visitas, administración de usuarios y consulta de movimientos recientes desde una vista pensada para escritorio y celular.</p>
        <a class="btn" href="<?= htmlspecialchars(app_url('registro.php'), ENT_QUOTES, 'UTF-8') ?>">Registrar visita ahora</a>
    </div>
</section>
<?php endif; ?>

<div class="grid">
    <section class="card">
        <h2>Bienvenido al gestor CID</h2>
        <p>Este sistema permite registrar visitas de alumnos al CID o biblioteca, mantener alta de usuarios y exportar los registros a Excel.</p>
        <a class="btn" href="<?= htmlspecialchars(app_url('registro.php'), ENT_QUOTES, 'UTF-8') ?>">Registrar visita ahora</a>
    </section>
    <section class="card">
        <h2>Acceso para gestor</h2>
        <p>Gestiona usuarios, carreras, adscripciones y revisa las visitas recientes.</p>
        <a class="link-btn" href="<?= htmlspecialchars(app_url('gestor/login.php'), ENT_QUOTES, 'UTF-8') ?>">Entrar al panel</a>
    </section>
</div>

<?php if ($dbError): ?>
<div class="alert error"><?= htmlspecialchars($dbError, ENT_QUOTES, 'UTF-8') ?></div>
<div class="card">
    <h3>Base de datos no disponible</h3>
    <p>Verifica las variables de entorno de MySQL y asegúrate de que la base de datos cidb esté importada con el archivo SQL del proyecto.</p>
</div>
<?php else: ?>
<div class="grid" style="margin-top: 1rem;">
    <div class="card">
        <h3>Usuarios registrados</h3>
        <p class="eyebrow">Base de datos</p>
        <h2><?= $usuarios ?></h2>
    </div>
    <div class="card">
        <h3>Visitas capturadas</h3>
        <p class="eyebrow">Ingresos al CID</p>
        <h2><?= $visitas ?></h2>
    </div>
    <div class="card">
        <h3>Última visita</h3>
        <p class="eyebrow">Registro automático</p>
        <h2><?= $ultima['momento_ingreso'] ?? 'Sin datos' ?></h2>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>

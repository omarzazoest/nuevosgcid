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
$report = [
    'processed' => 0,
    'inserted' => 0,
    'errors' => [],
];

function normalize_key(string $value): string
{
    $value = trim($value);
    $value = str_replace(["\xC2\xA0", "\xA0"], ' ', $value);

    // Algunos CSV de Excel llegan en Windows-1252/ISO-8859-1.
    if (!preg_match('//u', $value)) {
        if (function_exists('mb_convert_encoding')) {
            $value = mb_convert_encoding($value, 'UTF-8', 'Windows-1252,ISO-8859-1,UTF-8');
        } else {
            $converted = @iconv('Windows-1252', 'UTF-8//IGNORE', $value);
            if ($converted !== false) {
                $value = $converted;
            }
        }
    }

    $value = str_replace(['_', '-', '.', ','], ' ', $value);

    // Convertir acentos/diacriticos para comparar catalogos con formatos distintos.
    $trans = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    if ($trans !== false) {
        $value = $trans;
    }

    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9 ]+/', ' ', $value) ?? $value;
    $value = preg_replace('/\s+/', ' ', $value) ?? $value;

    return trim($value);
}

function compact_key(string $value): string
{
    return str_replace(' ', '', normalize_key($value));
}

function detect_csv_delimiter(string $filePath): string
{
    $firstLine = '';
    $file = fopen($filePath, 'r');
    if ($file !== false) {
        $firstLine = (string) fgets($file);
        fclose($file);
    }

    $countComma = substr_count($firstLine, ',');
    $countSemicolon = substr_count($firstLine, ';');
    $countTab = substr_count($firstLine, "\t");

    if ($countSemicolon >= $countComma && $countSemicolon >= $countTab && $countSemicolon > 0) {
        return ';';
    }
    if ($countTab >= $countComma && $countTab > 0) {
        return "\t";
    }

    return ',';
}

try {
    $conn = get_connection();
} catch (Throwable $e) {
    $message = $e->getMessage();
    $messageType = 'error';
}

if (($_GET['action'] ?? '') === 'template') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="plantilla_usuarios_cid.csv"');

    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    if ($out !== false) {
        fputcsv($out, ['nombre', 'apellido1', 'apellido2', 'tipo_usuario', 'identificador', 'carrera', 'adscripcion']);
        fputcsv($out, ['Juan', 'Perez', 'Lopez', 'Alumno', '20240001', 'INGENIERIA INDUSTRIAL', '']);
        fputcsv($out, ['Ana', 'Garcia', 'Mora', 'Profesor', '99887766', '', 'SECRETARIA ACADEMICA']);
        fclose($out);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $conn) {
    $action = $_POST['action'] ?? '';

    if ($action === 'import_csv') {
        $file = $_FILES['archivo_csv'] ?? null;
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $message = 'Selecciona un archivo CSV valido para importar.';
            $messageType = 'error';
        } else {
            $handle = fopen((string) $file['tmp_name'], 'r');
            if (!$handle) {
                $message = 'No fue posible leer el archivo cargado.';
                $messageType = 'error';
            } else {
                $delimiter = detect_csv_delimiter((string) $file['tmp_name']);
                $header = fgetcsv($handle, 0, $delimiter);
                if (!is_array($header)) {
                    $message = 'El archivo CSV no contiene encabezados.';
                    $messageType = 'error';
                } else {
                    $header = array_map(static function ($item) {
                        $item = (string) $item;
                        $item = preg_replace('/^\xEF\xBB\xBF/', '', $item) ?? $item;
                        return normalize_key($item);
                    }, $header);

                    $requiredColumns = ['nombre', 'apellido1', 'apellido2', 'tipo_usuario', 'identificador', 'carrera', 'adscripcion'];
                    $columnAliases = [
                        'nombre' => ['nombre', 'nombres'],
                        'apellido1' => ['apellido1', 'apellido paterno', 'apellidopaterno'],
                        'apellido2' => ['apellido2', 'apellido materno', 'apellidomaterno'],
                        'tipo_usuario' => ['tipo usuario', 'tipousuario', 'tipo de usuario', 'tipo_usuario'],
                        'identificador' => ['identificador', 'matricula', 'numero de matricula', 'numero matricula'],
                        'carrera' => ['carrera'],
                        'adscripcion' => ['adscripcion', 'adscripción'],
                    ];

                    $index = [];

                    foreach ($requiredColumns as $column) {
                        $aliases = $columnAliases[$column] ?? [$column];
                        $normalizedAliases = array_map(static fn(string $alias): string => normalize_key($alias), $aliases);
                        $position = false;

                        foreach ($normalizedAliases as $alias) {
                            $found = array_search($alias, $header, true);
                            if ($found !== false) {
                                $position = $found;
                                break;
                            }
                        }

                        if ($position === false) {
                            $report['errors'][] = 'Falta la columna obligatoria: ' . $column . '.';
                        } else {
                            $index[$column] = (int) $position;
                        }
                    }

                    if (count($report['errors']) > 0) {
                        $message = 'El archivo no tiene el formato esperado.';
                        $messageType = 'error';
                    } else {
                        $tipos = [];
                        $resTipos = $conn->query('SELECT id_tipo_usuario, nombre_tipo, numero_digitos_identificador FROM tipos_usuarios');
                        while ($resTipos && ($rowTipo = $resTipos->fetch_assoc())) {
                            $key = normalize_key((string) $rowTipo['nombre_tipo']);
                            $tipos[$key] = [
                                'id' => (int) $rowTipo['id_tipo_usuario'],
                                'digits' => (int) $rowTipo['numero_digitos_identificador'],
                                'role' => $key,
                            ];
                        }

                        $carreras = [];
                        $resCarreras = $conn->query('SELECT id_carrera, nombre_carrera FROM carreras');
                        while ($resCarreras && ($rowCarrera = $resCarreras->fetch_assoc())) {
                            $nombreCarreraDb = (string) $rowCarrera['nombre_carrera'];
                            $idCarreraDb = (int) $rowCarrera['id_carrera'];
                            $carreras[normalize_key($nombreCarreraDb)] = $idCarreraDb;
                            $carreras[compact_key($nombreCarreraDb)] = $idCarreraDb;
                        }

                        $adscripciones = [];
                        $resAds = $conn->query('SELECT id_adscripcion, nombre_adscripcion FROM adscripciones');
                        while ($resAds && ($rowAds = $resAds->fetch_assoc())) {
                            $nombreAdsDb = (string) $rowAds['nombre_adscripcion'];
                            $idAdsDb = (int) $rowAds['id_adscripcion'];
                            $adscripciones[normalize_key($nombreAdsDb)] = $idAdsDb;
                            $adscripciones[compact_key($nombreAdsDb)] = $idAdsDb;
                        }

                        $stmt = $conn->prepare('INSERT INTO usuarioscid (nombre, apellido1, apellido2, id_tipo_usuario, identificador, id_carrera, id_adscripcion) VALUES (?, ?, ?, ?, ?, ?, ?)');

                        if (!$stmt) {
                            $message = 'No se pudo preparar la importacion: ' . $conn->error;
                            $messageType = 'error';
                        } else {
                            $hasImportErrors = false;
                            $conn->begin_transaction();
                            $line = 1;
                            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                                $line++;

                                $rowValues = [];
                                foreach ($requiredColumns as $column) {
                                    $rowValues[$column] = trim((string) ($row[$index[$column]] ?? ''));
                                }

                                $isEmptyRow = true;
                                foreach ($rowValues as $value) {
                                    if ($value !== '') {
                                        $isEmptyRow = false;
                                        break;
                                    }
                                }
                                if ($isEmptyRow) {
                                    continue;
                                }

                                $report['processed']++;

                                $nombre = $rowValues['nombre'];
                                $apellido1 = $rowValues['apellido1'];
                                $apellido2 = $rowValues['apellido2'];
                                $tipoNombre = normalize_key($rowValues['tipo_usuario']);
                                $identificador = $rowValues['identificador'];
                                $carreraNombre = normalize_key($rowValues['carrera']);
                                $adscripcionNombre = normalize_key($rowValues['adscripcion']);
                                $carreraNombreCompact = compact_key($rowValues['carrera']);
                                $adscripcionNombreCompact = compact_key($rowValues['adscripcion']);

                                if ($nombre === '' || $apellido1 === '' || $apellido2 === '' || $tipoNombre === '') {
                                    $report['errors'][] = 'Linea ' . $line . ': faltan datos personales o tipo_usuario.';
                                    $hasImportErrors = true;
                                    continue;
                                }

                                if (!isset($tipos[$tipoNombre])) {
                                    $report['errors'][] = 'Linea ' . $line . ': tipo_usuario no existe en catalogo.';
                                    $hasImportErrors = true;
                                    continue;
                                }

                                $tipoInfo = $tipos[$tipoNombre];
                                $tipoId = (int) $tipoInfo['id'];
                                $expectedDigits = (int) $tipoInfo['digits'];
                                $role = (string) $tipoInfo['role'];

                                $requireCarrera = str_contains($role, 'alumno') || str_contains($role, 'egresado');
                                $requireAdscripcion = str_contains($role, 'profesor') || str_contains($role, 'administrativo') || str_contains($role, 'personal');

                                if ($expectedDigits > 0 && $identificador === '') {
                                    $report['errors'][] = 'Linea ' . $line . ': el identificador es obligatorio para este tipo.';
                                    $hasImportErrors = true;
                                    continue;
                                }

                                if ($expectedDigits > 0 && strlen($identificador) !== $expectedDigits) {
                                    $report['errors'][] = 'Linea ' . $line . ': identificador debe tener ' . $expectedDigits . ' digitos.';
                                    $hasImportErrors = true;
                                    continue;
                                }

                                if ($expectedDigits === 0) {
                                    $identificador = '';
                                }

                                $idCarrera = null;
                                $idAdscripcion = null;

                                if ($requireCarrera) {
                                    $matchCarrera = null;
                                    $matchAdscripcion = null;

                                    // Prioriza columna carrera; si viene vacia, intenta con adscripcion.
                                    if ($carreraNombre !== '' && isset($carreras[$carreraNombre])) {
                                        $matchCarrera = $carreras[$carreraNombre];
                                    } elseif ($carreraNombreCompact !== '' && isset($carreras[$carreraNombreCompact])) {
                                        $matchCarrera = $carreras[$carreraNombreCompact];
                                    } elseif ($adscripcionNombre !== '' && isset($carreras[$adscripcionNombre])) {
                                        $matchCarrera = $carreras[$adscripcionNombre];
                                    } elseif ($adscripcionNombreCompact !== '' && isset($carreras[$adscripcionNombreCompact])) {
                                        $matchCarrera = $carreras[$adscripcionNombreCompact];
                                    }

                                    // Si no hay carrera valida, acepta una adscripcion valida como unica referencia.
                                    if ($matchCarrera === null) {
                                        if ($adscripcionNombre !== '' && isset($adscripciones[$adscripcionNombre])) {
                                            $matchAdscripcion = $adscripciones[$adscripcionNombre];
                                        } elseif ($adscripcionNombreCompact !== '' && isset($adscripciones[$adscripcionNombreCompact])) {
                                            $matchAdscripcion = $adscripciones[$adscripcionNombreCompact];
                                        } elseif ($carreraNombre !== '' && isset($adscripciones[$carreraNombre])) {
                                            $matchAdscripcion = $adscripciones[$carreraNombre];
                                        } elseif ($carreraNombreCompact !== '' && isset($adscripciones[$carreraNombreCompact])) {
                                            $matchAdscripcion = $adscripciones[$carreraNombreCompact];
                                        }
                                    }

                                    if ($matchCarrera === null && $matchAdscripcion === null) {
                                        $report['errors'][] = 'Linea ' . $line . ': para alumno/egresado se requiere una referencia valida (carrera o adscripcion).';
                                        $hasImportErrors = true;
                                        continue;
                                    }
                                    if ($matchCarrera !== null) {
                                        $idCarrera = (int) $matchCarrera;
                                        $idAdscripcion = null;
                                    } else {
                                        $idCarrera = null;
                                        $idAdscripcion = (int) $matchAdscripcion;
                                    }
                                }

                                if ($requireAdscripcion) {
                                    $matchAdscripcion = null;
                                    $matchCarrera = null;

                                    // Prioriza columna adscripcion; si viene vacia, intenta con carrera.
                                    if ($adscripcionNombre !== '' && isset($adscripciones[$adscripcionNombre])) {
                                        $matchAdscripcion = $adscripciones[$adscripcionNombre];
                                    } elseif ($adscripcionNombreCompact !== '' && isset($adscripciones[$adscripcionNombreCompact])) {
                                        $matchAdscripcion = $adscripciones[$adscripcionNombreCompact];
                                    } elseif ($carreraNombre !== '' && isset($adscripciones[$carreraNombre])) {
                                        $matchAdscripcion = $adscripciones[$carreraNombre];
                                    } elseif ($carreraNombreCompact !== '' && isset($adscripciones[$carreraNombreCompact])) {
                                        $matchAdscripcion = $adscripciones[$carreraNombreCompact];
                                    }

                                    // Si no hay adscripcion valida, acepta una carrera valida como unica referencia.
                                    if ($matchAdscripcion === null) {
                                        if ($carreraNombre !== '' && isset($carreras[$carreraNombre])) {
                                            $matchCarrera = $carreras[$carreraNombre];
                                        } elseif ($carreraNombreCompact !== '' && isset($carreras[$carreraNombreCompact])) {
                                            $matchCarrera = $carreras[$carreraNombreCompact];
                                        } elseif ($adscripcionNombre !== '' && isset($carreras[$adscripcionNombre])) {
                                            $matchCarrera = $carreras[$adscripcionNombre];
                                        } elseif ($adscripcionNombreCompact !== '' && isset($carreras[$adscripcionNombreCompact])) {
                                            $matchCarrera = $carreras[$adscripcionNombreCompact];
                                        }
                                    }

                                    if ($matchAdscripcion === null && $matchCarrera === null) {
                                        $report['errors'][] = 'Linea ' . $line . ': para profesor/administrativo se requiere una referencia valida (adscripcion o carrera).';
                                        $hasImportErrors = true;
                                        continue;
                                    }
                                    if ($matchAdscripcion !== null) {
                                        $idAdscripcion = (int) $matchAdscripcion;
                                        $idCarrera = null;
                                    } else {
                                        $idAdscripcion = null;
                                        $idCarrera = (int) $matchCarrera;
                                    }
                                }

                                $stmt->bind_param('sssissi', $nombre, $apellido1, $apellido2, $tipoId, $identificador, $idCarrera, $idAdscripcion);

                                try {
                                    $ok = $stmt->execute();

                                    if ($ok) {
                                        $report['inserted']++;
                                        continue;
                                    }

                                    if ((int) $stmt->errno === 1062) {
                                        $report['errors'][] = 'Linea ' . $line . ': identificador duplicado (' . $identificador . ').';
                                    } else {
                                        $report['errors'][] = 'Linea ' . $line . ': error SQL (' . $stmt->errno . ') ' . $stmt->error;
                                    }
                                    $hasImportErrors = true;
                                } catch (mysqli_sql_exception $e) {
                                    if ((int) $e->getCode() === 1062) {
                                        $report['errors'][] = 'Linea ' . $line . ': identificador duplicado (' . $identificador . ').';
                                    } else {
                                        $report['errors'][] = 'Linea ' . $line . ': error SQL (' . (int) $e->getCode() . ') ' . $e->getMessage();
                                    }
                                    $hasImportErrors = true;
                                }
                            }

                            $stmt->close();

                            if ($hasImportErrors || count($report['errors']) > 0) {
                                $conn->rollback();
                                $report['inserted'] = 0;
                                $message = 'Importacion cancelada: se detectaron errores. No se registro ningun usuario.';
                                $messageType = 'error';
                            } elseif ($report['inserted'] > 0) {
                                $conn->commit();
                                $message = 'Importacion completada. Usuarios insertados: ' . $report['inserted'] . '.';
                                $messageType = 'success';
                            } else {
                                $conn->rollback();
                                $message = 'No se insertaron usuarios. Revisa los datos del archivo.';
                                $messageType = 'error';
                            }
                        }
                    }
                }

                fclose($handle);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carga masiva de usuarios</title>
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
            <a class="active" href="<?= htmlspecialchars(app_url('gestor/carga_masiva.php'), ENT_QUOTES, 'UTF-8') ?>">Carga masiva</a>
            <a href="<?= htmlspecialchars(app_url('gestor/libros.php'), ENT_QUOTES, 'UTF-8') ?>">Libros</a>
            <a href="<?= htmlspecialchars(app_url('gestor/prestamos.php'), ENT_QUOTES, 'UTF-8') ?>">Préstamos</a>
        </nav>
        <div class="sidebar-footer">
            <a href="<?= htmlspecialchars(app_url('gestor/logout.php'), ENT_QUOTES, 'UTF-8') ?>">Cerrar sesion</a>
        </div>
    </aside>

    <main class="manager-main">
        <header class="manager-topbar">
            <button class="sidebar-toggle" id="sidebar-toggle" type="button">☰</button>
            <div>
                <span class="topbar-label">UPVM</span>
                <h1>Carga masiva de usuarios</h1>
            </div>
            <a href="<?= htmlspecialchars(app_url('gestor/carga_masiva.php?action=template'), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-primary">Descargar plantilla CSV</a>
        </header>

        <div class="manager-content">
            <?php if ($message): ?>
                <div class="alert alert-<?= htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <section class="panel">
                <div class="panel-header">
                    <h3>Instrucciones</h3>
                </div>
                <ol class="small" style="margin-top:0; padding-left:1rem;">
                    <li>Descarga la plantilla CSV y abrela con Excel.</li>
                    <li>Llena una fila por usuario con los encabezados exactamente iguales.</li>
                    <li>En tipo_usuario usa un nombre existente en tu catalogo (por ejemplo Alumno, Egresado, Profesor).</li>
                    <li>Si el tipo requiere carrera o adscripcion, el nombre debe coincidir con el catalogo configurado.</li>
                    <li>Guarda el archivo como CSV UTF-8 (delimitado por comas) y subelo aqui.</li>
                    <li>No repitas identificadores: ya son unicos en la base de datos.</li>
                </ol>
            </section>

            <section class="panel">
                <div class="panel-header">
                    <h3>Subir archivo CSV</h3>
                </div>
                <form method="post" enctype="multipart/form-data" class="form-stack">
                    <input type="hidden" name="action" value="import_csv">
                    <div class="field">
                        <label for="archivo_csv">Archivo CSV</label>
                        <input id="archivo_csv" type="file" name="archivo_csv" accept=".csv" required>
                    </div>
                    <div class="field field--submit">
                        <button type="submit" class="btn btn-primary">Importar usuarios</button>
                    </div>
                </form>
            </section>

            <section class="panel">
                <div class="panel-header">
                    <h3>Resultado de importacion</h3>
                </div>
                <div class="tag-list" style="display:block;">
                    <div class="tag-row"><span class="tag">Filas procesadas: <?= (int) $report['processed'] ?></span></div>
                    <div class="tag-row"><span class="tag">Usuarios insertados: <?= (int) $report['inserted'] ?></span></div>
                    <div class="tag-row"><span class="tag">Errores: <?= count($report['errors']) ?></span></div>
                </div>
                <?php if (count($report['errors']) > 0): ?>
                    <div class="table-wrap" style="margin-top:0.8rem;">
                        <table>
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Detalle</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report['errors'] as $idx => $error): ?>
                                    <tr>
                                        <td><?= (int) ($idx + 1) ?></td>
                                        <td><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
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

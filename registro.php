<?php
session_start();
require_once __DIR__ . '/config/db.php';

$conn = null;
$message = '';
$messageType = '';
$shouldEmitVisitEvent = false;
$visitEventPayload = [];

try {
    $conn = get_connection();
} catch (Throwable $e) {
    $message = $e->getMessage();
    $messageType = 'error';
}

if (($_GET['action'] ?? '') === 'buscar_usuario') {
    header('Content-Type: application/json; charset=UTF-8');

    if (!$conn) {
        http_response_code(500);
        echo json_encode([
            'ok' => false,
            'message' => 'No se pudo conectar con la base de datos.',
        ]);
        exit;
    }

    $identificadorBusqueda = trim($_GET['identificador'] ?? '');
    if ($identificadorBusqueda === '') {
        echo json_encode([
            'ok' => false,
            'message' => 'Ingresa un identificador para buscar.',
        ]);
        exit;
    }

    $stmtBuscar = $conn->prepare('SELECT u.id_usuario, u.nombre, u.apellido1, u.apellido2, COALESCE(c.nombre_carrera, "") AS carrera, COALESCE(a.nombre_adscripcion, "") AS adscripcion FROM usuarioscid u LEFT JOIN carreras c ON c.id_carrera = u.id_carrera LEFT JOIN adscripciones a ON a.id_adscripcion = u.id_adscripcion WHERE u.identificador = ? LIMIT 1');
    $stmtBuscar->bind_param('s', $identificadorBusqueda);
    $stmtBuscar->execute();
    $resBuscar = $stmtBuscar->get_result();
    $usuarioEncontrado = $resBuscar->fetch_assoc();
    $stmtBuscar->close();

    if (!$usuarioEncontrado) {
        echo json_encode([
            'ok' => false,
            'message' => 'No existe alumno/usuario con ese identificador. Solicita tu registro con el gestor del CID.',
        ]);
        exit;
    }

    echo json_encode([
        'ok' => true,
        'message' => 'Usuario encontrado correctamente.',
        'usuario' => [
            'id_usuario' => (int) $usuarioEncontrado['id_usuario'],
            'nombre_completo' => trim(($usuarioEncontrado['nombre'] ?? '') . ' ' . ($usuarioEncontrado['apellido1'] ?? '') . ' ' . ($usuarioEncontrado['apellido2'] ?? '')),
            'carrera' => (string) ($usuarioEncontrado['carrera'] ?? ''),
            'adscripcion' => (string) ($usuarioEncontrado['adscripcion'] ?? ''),
        ],
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($message)) {
    $identificador = trim($_POST['identificador'] ?? '');
    $funcion = trim($_POST['funcion'] ?? '');
    $nombreExt = trim($_POST['nombre_ext'] ?? '');
    $apellido1Ext = trim($_POST['apellido1_ext'] ?? '');
    $apellido2Ext = trim($_POST['apellido2_ext'] ?? '');
    $sexoExt = trim($_POST['sexo_ext'] ?? '');
    $carrera = trim($_POST['carrera'] ?? '');
    $adscripcion = trim($_POST['adscripcion'] ?? '');
    $servicioKey = trim($_POST['servicio'] ?? '');
    $opcionComun = trim($_POST['opcionComun'] ?? '');
    $digitalNombre = trim($_POST['digitalNombre'] ?? '');
    $digitalTitulo = trim($_POST['digitalTitulo'] ?? '');
    $numCubiculo = trim($_POST['numCubiculo'] ?? '');
    $actividadCubiculo = trim($_POST['actividadCubiculo'] ?? '');
    $actividadBiblioteca = trim($_POST['actividadBiblioteca'] ?? '');
    $actividadesBibliotecaPermitidas = [
        'Asesoria' => 'Asesoria',
        'Clase' => 'Clase',
        'Conferencia' => 'Conferencia',
        'Estudio' => 'Estudio',
        'Induccion' => 'Induccion',
        'Proyecto' => 'Proyecto',
        'Taller' => 'Taller',
        'Tarea' => 'Tarea',
    ];

    $servicios = [
        'consulta' => 'Consulta en sala',
        'devolucion' => 'Devolución de material',
        'renovacion' => 'Renovación de material',
        'prestamo' => 'Préstamo de material',
        'cubiculo' => 'Cubículo',
        'digital' => 'Biblioteca digital',
        'actividades' => 'Actividades en biblioteca',
    ];

    $servicio = $servicios[$servicioKey] ?? '';
    $actividad = '';
    $detalle = '';

    if ($funcion === '') {
        $message = 'Selecciona el tipo de usuario para continuar.';
        $messageType = 'error';
    } elseif ($funcion === 'externo' && ($nombreExt === '' || $apellido1Ext === '' || $apellido2Ext === '' || $sexoExt === '')) {
        $message = 'Para usuario externo debes completar nombre, apellidos y sexo.';
        $messageType = 'error';
    } elseif ($funcion !== 'externo' && $identificador === '') {
        $message = 'Ingresa tu identificador para continuar con el registro.';
        $messageType = 'error';
    } elseif (($funcion === 'estudiante' || $funcion === 'egresado') && $carrera === '') {
        $message = 'Selecciona la carrera para continuar con el registro.';
        $messageType = 'error';
    } elseif (($funcion === 'profesor' || $funcion === 'administrativo') && $adscripcion === '') {
        $message = 'Selecciona la adscripción para continuar con el registro.';
        $messageType = 'error';
    } elseif ($servicio === '') {
        $message = 'Selecciona el servicio solicitado.';
        $messageType = 'error';
    } else {
        if (in_array($servicioKey, ['consulta', 'devolucion', 'renovacion', 'prestamo'], true)) {
            if ($opcionComun === '') {
                $message = 'Especifica el título o material para este servicio.';
                $messageType = 'error';
            }
            $actividad = $servicio;
            $detalle = $opcionComun;
        } elseif ($servicioKey === 'digital') {
            if ($digitalNombre === '' || $digitalTitulo === '') {
                $message = 'Completa la biblioteca digital y el título consultado.';
                $messageType = 'error';
            }
            $actividad = $servicio;
            $detalle = 'Biblioteca: ' . $digitalNombre . ' | Título: ' . $digitalTitulo;
        } elseif ($servicioKey === 'cubiculo') {
            if ($numCubiculo === '' || $actividadCubiculo === '') {
                $message = 'Completa el cubículo y la actividad a realizar.';
                $messageType = 'error';
            }
            $actividad = $servicio;
            $detalle = 'Cubículo ' . $numCubiculo . ' | Actividad: ' . $actividadCubiculo;
        } elseif ($servicioKey === 'actividades') {
            if ($actividadBiblioteca === '' || !isset($actividadesBibliotecaPermitidas[$actividadBiblioteca])) {
                $message = 'Especifica la actividad a realizar en biblioteca.';
                $messageType = 'error';
            }
            $actividad = $servicio;
            $detalle = $actividadesBibliotecaPermitidas[$actividadBiblioteca] ?? '';
        }
    }

    if ($messageType !== 'error') {
        if ($actividad === '') {
            $actividad = $servicio;
        }

        if ($detalle === '') {
            $contexto = $funcion;
            if ($carrera !== '') {
                $contexto .= ' | Carrera: ' . $carrera;
            }
            if ($adscripcion !== '') {
                $contexto .= ' | Adscripción: ' . $adscripcion;
            }
            $detalle = $contexto;
        }

        $idUsuario = null;
        $nombreOk = '';

        if ($funcion !== 'externo') {
            $stmt = $conn->prepare('SELECT u.id_usuario, u.nombre, u.apellido1, u.apellido2, COALESCE(c.nombre_carrera, "") AS carrera, COALESCE(a.nombre_adscripcion, "") AS adscripcion FROM usuarioscid u LEFT JOIN carreras c ON c.id_carrera = u.id_carrera LEFT JOIN adscripciones a ON a.id_adscripcion = u.id_adscripcion WHERE u.identificador = ? LIMIT 1');
            $stmt->bind_param('s', $identificador);
            $stmt->execute();
            $result = $stmt->get_result();
            $usuario = $result->fetch_assoc();
            $stmt->close();

            if (!$usuario) {
                $message = 'No existe un usuario con ese identificador. Acércate al gestor del CID para darte de alta.';
                $messageType = 'error';
            } else {
                $idUsuario = (int) $usuario['id_usuario'];
                $nombreOk = trim(($usuario['nombre'] ?? '') . ' ' . ($usuario['apellido1'] ?? '') . ' ' . ($usuario['apellido2'] ?? ''));
                $carrera = trim((string) ($usuario['carrera'] ?? ''));
                $adscripcion = trim((string) ($usuario['adscripcion'] ?? ''));

                if (($funcion === 'estudiante' || $funcion === 'egresado') && $carrera === '') {
                    $message = 'El usuario existe, pero no tiene carrera asignada. Solicita actualización con el gestor del CID.';
                    $messageType = 'error';
                } elseif (($funcion === 'profesor' || $funcion === 'administrativo') && $adscripcion === '') {
                    $message = 'El usuario existe, pero no tiene adscripción asignada. Solicita actualización con el gestor del CID.';
                    $messageType = 'error';
                }
            }
        } else {
            $nombreOk = trim($nombreExt . ' ' . $apellido1Ext . ' ' . $apellido2Ext);
        }

        if ($messageType !== 'error') {
            if ($funcion === 'externo') {
                $insert = $conn->prepare('INSERT INTO ingresoscid (servicio, actividad, detalle, id_usuario, nombre_ext, apellido1_ext, apellido2_ext, sexo_ext) VALUES (?, ?, ?, NULL, ?, ?, ?, ?)');
                $insert->bind_param('sssssss', $servicio, $actividad, $detalle, $nombreExt, $apellido1Ext, $apellido2Ext, $sexoExt);
                $insert->execute();
                $insert->close();
            } else {
                $insert = $conn->prepare('INSERT INTO ingresoscid (servicio, actividad, detalle, id_usuario, nombre_ext, apellido1_ext, apellido2_ext, sexo_ext) VALUES (?, ?, ?, ?, NULL, NULL, NULL, NULL)');
                $insert->bind_param('sssi', $servicio, $actividad, $detalle, $idUsuario);
                $insert->execute();
                $insert->close();
            }

            $message = 'Visita registrada correctamente para ' . htmlspecialchars($nombreOk, ENT_QUOTES, 'UTF-8') . '.';
            $messageType = 'success';
            $shouldEmitVisitEvent = true;
            $visitEventPayload = [
                'usuario' => $nombreOk,
                'tipo_usuario' => $funcion,
                'identificador' => $funcion === 'externo' ? 'Externo' : $identificador,
                'servicio' => $servicio,
                'actividad' => $actividad,
                'detalle' => $detalle,
                'momento' => date('Y-m-d H:i:s'),
            ];
        }
    } else {
        $messageType = 'error';
    }
}

$imagenRegistroUrl = '';
$imagenesRegistro = glob(__DIR__ . '/img/*.{png,jpg,jpeg,webp,gif,svg}', GLOB_BRACE);
if ($imagenesRegistro && isset($imagenesRegistro[0])) {
    $nombreImagenRegistro = basename($imagenesRegistro[0]);
    $imagenRegistroUrl = app_url('img/' . rawurlencode($nombreImagenRegistro));
}

include __DIR__ . '/includes/header.php';
?>

<div class="grid registro-grid">
    <section class="card registro-main-card">
        <h2>Registro de visita</h2>
        <p>Primero selecciona tu tipo de usuario. Si eres externo, captura tus datos personales; en otros casos, captura identificador y continúa con el servicio.</p>
        
        <?php if ($message): ?>
        <div class="alert <?= $messageType ?>"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <form method="post" id="registroForm">
            <div class="form-group">
                <label for="funcion">Tipo de usuario</label>
                <!--dedicado a la memoria de pechocho, oct 2025. -Omar -->
                <select id="funcion" name="funcion" required onchange="mostrarCampo()">
                    <option value="">Selecciona la respuesta</option>
                    <option value="estudiante">Estudiante</option>
                    <option value="egresado">Egresado</option>
                    <option value="profesor">Profesor</option>
                    <option value="administrativo">Personal administrativo</option>
                    <option value="externo">Externo</option>
                </select>
            </div>

            <div id="datosExterno" class="opcional">
                <div class="form-group">
                    <label for="nombreExt">Nombre</label>
                    <input id="nombreExt" name="nombre_ext" type="text" placeholder="Nombre(s)">
                </div>
                <div class="form-group">
                    <label for="apellido1Ext">Apellido paterno</label>
                    <input id="apellido1Ext" name="apellido1_ext" type="text" placeholder="Apellido paterno">
                </div>
                <div class="form-group">
                    <label for="apellido2Ext">Apellido materno</label>
                    <input id="apellido2Ext" name="apellido2_ext" type="text" placeholder="Apellido materno">
                </div>
                <div class="form-group">
                    <label for="sexoExt">Sexo</label>
                    <select id="sexoExt" name="sexo_ext">
                        <option value="">Selecciona sexo</option>
                        <option value="Femenino">Femenino</option>
                        <option value="Masculino">Masculino</option>
                        <option value="Otro">Otro</option>
                    </select>
                </div>
            </div>

            <div id="identificadorBox" class="opcional">
                <div class="form-group">
                    <label for="identificador">Identificador / matrícula</label>
                    <input id="identificador" name="identificador" placeholder="Ej. 20240001">
                </div>
                <div class="form-group">
                    <button type="button" class="btn btn--ghost" id="buscarAlumnoBtn" onclick="buscarAlumno()">Buscar alumno / usuario</button>
                </div>
                <div id="estadoBusqueda" class="small" aria-live="polite"></div>
            </div>

            <div id="carrera" class="opcional">
                <div class="form-group">
                    <label for="carreraView">Carrera (autocargada)</label>
                    <input id="carreraView" type="text" readonly>
                    <input id="carreraSelect" type="hidden" name="carrera" value="">
                </div>
            </div>

            <div id="adscripcion" class="opcional">
                <div class="form-group">
                    <label for="adsView">Adscripción (autocargada)</label>
                    <input id="adsView" type="text" readonly>
                    <input id="adsSelect" type="hidden" name="adscripcion" value="">
                </div>
            </div>

            <div id="servicio" class="opcional">
                <div class="form-group">
                    <label for="servicioSelect">Servicio solicitado</label>
                    <select id="servicioSelect" name="servicio" onchange="mostrarSubservicio()">
                        <option value="">Selecciona la respuesta</option>
                        <option value="consulta">Consulta en sala</option>
                        <option value="devolucion">Devolución de material</option>
                        <option value="renovacion">Renovación de material</option>
                        <option value="prestamo">Préstamo de material</option>
                        <option value="cubiculo">Cubículo</option>
                        <option value="digital">Biblioteca digital</option>
                        <option value="actividades">Actividades en biblioteca</option>
                    </select>
                </div>
            </div>

            <div id="opcionComunBox" class="opcional">
                <div class="form-group">
                    <label for="opcionComun">Especifica título o material</label>
                    <input id="opcionComun" name="opcionComun" type="text" placeholder="Libro, tema, materia, etc.">
                </div>
            </div>

            <div id="digitalNombreBox" class="opcional">
                <div class="form-group">
                    <label for="digitalNombre">Biblioteca digital</label>
                    <select id="digitalNombre" name="digitalNombre" onchange="mostrarTituloDigital()">
                        <option value="">Selecciona una biblioteca</option>
                        <option value="biblioteca1">Biblioteca 1</option>
                        <option value="biblioteca2">Biblioteca 2</option>
                    </select>
                </div>
            </div>

            <div id="digitalTituloBox" class="opcional">
                <div class="form-group">
                    <label for="digitalTitulo">Título de la biblioteca digital</label>
                    <input id="digitalTitulo" name="digitalTitulo" type="text">
                </div>
            </div>

            <div id="cubiculoNumBox" class="opcional">
                <div class="form-group">
                    <label for="numCubiculo">Número de cubículo</label>
                    <select name="numCubiculo" id="numCubiculo" onchange="mostrarActividadCubiculo()">
                        <option value="">Selecciona cubículo</option>
                        <option value="1">Cubículo 1</option>
                        <option value="2">Cubículo 2</option>
                        <option value="3">Cubículo 3</option>
                    </select>
                </div>
            </div>

            <div id="cubiculoActBox" class="opcional">
                <div class="form-group">
                    <label for="actividadCubiculo">Actividad del cubículo</label>
                    <input type="text" id="actividadCubiculo" name="actividadCubiculo">
                </div>
            </div>
    <!--dedicado a la memoria de pechocho, oct 2025. -Omar -->
            <div id="actividadesBox" class="opcional">
                <div class="form-group">
                    <label for="actividadBiblioteca">Actividad a realizar</label>
                    <select id="actividadBiblioteca" name="actividadBiblioteca">
                        <option value="">Selecciona la respuesta</option>
                        <option value="Asesoria">Asesoria</option>
                        <option value="Clase">Clase</option>
                        <option value="Conferencia">Conferencia</option>
                        <option value="Estudio">Estudio</option>
                        <option value="Induccion">Induccion</option>
                        <option value="Proyecto">Proyecto</option>
                        <option value="Taller">Taller</option>
                        <option value="Tarea">Tarea</option>
                    </select>
                </div>
            </div>

            <button class="btn" id="registrarBtn" type="submit" style="display:none;">Registrar visita</button>
        </form>
    </section>
    <section class="card registro-info-card">
        <h3>¿Qué pasa después?</h3>
        <ul>
            <li>Tu sesión queda registrada con la fecha y hora actual.</li>
            <li>El gestor podrá ver tu registro y validar tu visita.</li>
            <li>Si tu matrícula no aparece, pide alta en el área del CID.</li>
        </ul>
        <p class="small">La identidad institucional de la UPVM se conserva en cada registro.</p>
    </section>
</div>

<style>
    .opcional { display: none; }
</style>

<script>
const BUSCAR_USUARIO_URL = <?= json_encode(app_url('registro.php?action=buscar_usuario'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const WS_CLIENT_URL = <?= json_encode(websocket_client_url(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const SHOULD_EMIT_VISIT_EVENT = <?= $shouldEmitVisitEvent ? 'true' : 'false' ?>;
const VISIT_EVENT_PAYLOAD = <?= json_encode($visitEventPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
let usuarioValidado = false;

function ocultar(...ids) {
    ids.forEach(function(id) {
        const el = document.getElementById(id);
        if (!el) return;
        el.querySelectorAll('input, select, textarea').forEach(function(campo) {
            campo.required = false;
            if (campo.type === 'checkbox' || campo.type === 'radio') {
                campo.checked = false;
            } else if (campo.tagName.toLowerCase() === 'select') {
                campo.selectedIndex = 0;
            } else {
                campo.value = '';
            }
        });
        el.style.display = 'none';
    });
}

function ocultarSolo(...ids) {
    ids.forEach(function(id) {
        const el = document.getElementById(id);
        if (!el) return;
        el.style.display = 'none';
        el.querySelectorAll('input, select, textarea').forEach(function(campo) {
            campo.required = false;
        });
    });
}

function setEstadoBusqueda(texto, tipo) {
    const box = document.getElementById('estadoBusqueda');
    if (!box) return;
    box.textContent = texto || '';
    box.style.color = tipo === 'ok' ? 'var(--success)' : (tipo === 'error' ? 'var(--error)' : 'var(--muted)');
}

function emitirEventoNuevaVisita() {
    if (!SHOULD_EMIT_VISIT_EVENT || !WS_CLIENT_URL) {
        return;
    }

    try {
        const ws = new WebSocket(WS_CLIENT_URL);
        ws.addEventListener('open', function() {
            ws.send(JSON.stringify({
                type: 'new_visit',
                payload: VISIT_EVENT_PAYLOAD,
            }));
            setTimeout(function() {
                ws.close();
            }, 250);
        });
    } catch (error) {
        // Silencioso: no bloquea el registro aunque el socket no esté activo.
    }
}

function limpiarUsuarioEncontrado() {
    const carreraView = document.getElementById('carreraView');
    const adsView = document.getElementById('adsView');
    const carreraHidden = document.getElementById('carreraSelect');
    const adsHidden = document.getElementById('adsSelect');
    if (carreraView) carreraView.value = '';
    if (adsView) adsView.value = '';
    if (carreraHidden) carreraHidden.value = '';
    if (adsHidden) adsHidden.value = '';
}

function actualizarBotonRegistro() {
    const btn = document.getElementById('registrarBtn');
    if (!btn) return;

    const funcion = document.getElementById('funcion').value;
    const servicio = document.getElementById('servicioSelect').value;
    let visible = false;

    if (funcion === 'externo') {
        const nombreOk = document.getElementById('nombreExt').value.trim() !== '';
        const ap1Ok = document.getElementById('apellido1Ext').value.trim() !== '';
        const ap2Ok = document.getElementById('apellido2Ext').value.trim() !== '';
        const sexoOk = document.getElementById('sexoExt').value !== '';
        visible = nombreOk && ap1Ok && ap2Ok && sexoOk && servicio !== '';
    } else if (funcion !== '') {
        visible = usuarioValidado && servicio !== '';
    }

    btn.style.display = visible ? 'inline-flex' : 'none';
}

async function buscarAlumno() {
    const funcion = document.getElementById('funcion').value;
    const identificador = document.getElementById('identificador').value.trim();

    if (funcion === '' || funcion === 'externo') {
        setEstadoBusqueda('Selecciona un tipo interno antes de buscar.', 'error');
        return;
    }

    if (identificador === '') {
        setEstadoBusqueda('Ingresa un identificador para buscar.', 'error');
        return;
    }

    usuarioValidado = false;
    limpiarUsuarioEncontrado();
    mostrarFlujoNoExterno();
    actualizarBotonRegistro();
    setEstadoBusqueda('Buscando usuario...', 'info');

    try {
        const url = BUSCAR_USUARIO_URL + '&identificador=' + encodeURIComponent(identificador);
        const resp = await fetch(url, { headers: { 'Accept': 'application/json' } });
        const data = await resp.json();

        if (!resp.ok || !data.ok) {
            setEstadoBusqueda(data.message || 'No se encontró el usuario.', 'error');
            return;
        }

        const carrera = (data.usuario && data.usuario.carrera ? data.usuario.carrera : '').trim();
        const adscripcion = (data.usuario && data.usuario.adscripcion ? data.usuario.adscripcion : '').trim();

        if ((funcion === 'estudiante' || funcion === 'egresado') && carrera === '') {
            setEstadoBusqueda('El usuario existe, pero no tiene carrera asignada. Solicita actualización con el gestor del CID.', 'error');
            return;
        }

        if ((funcion === 'profesor' || funcion === 'administrativo') && adscripcion === '') {
            setEstadoBusqueda('El usuario existe, pero no tiene adscripción asignada. Solicita actualización con el gestor del CID.', 'error');
            return;
        }

        document.getElementById('carreraView').value = carrera;
        document.getElementById('adsView').value = adscripcion;
        document.getElementById('carreraSelect').value = carrera;
        document.getElementById('adsSelect').value = adscripcion;

        usuarioValidado = true;
        setEstadoBusqueda('Usuario encontrado: ' + (data.usuario.nombre_completo || 'Sin nombre'), 'ok');
        mostrarFlujoNoExterno();
        actualizarBotonRegistro();
    } catch (e) {
        setEstadoBusqueda('No fue posible completar la búsqueda. Intenta de nuevo.', 'error');
    }
}

function hacerObligatorio(idContenedor, idCampo) {
    const contenedor = document.getElementById(idContenedor);
    const campo = document.getElementById(idCampo);
    if (!contenedor || !campo) return;
    contenedor.style.display = 'block';
    campo.required = true;
}

function hacerObligatorios(idContenedor, idsCampos) {
    const contenedor = document.getElementById(idContenedor);
    if (!contenedor) return;
    contenedor.style.display = 'block';
    idsCampos.forEach(function(idCampo) {
        const campo = document.getElementById(idCampo);
        if (campo) {
            campo.required = true;
        }
    });
}

function mostrarCampo() {
    const f = document.getElementById('funcion').value;
    usuarioValidado = false;
    limpiarUsuarioEncontrado();
    setEstadoBusqueda('', 'info');
    ocultar(
        'datosExterno', 'identificadorBox', 'carrera', 'adscripcion', 'servicio',
        'opcionComunBox', 'digitalNombreBox', 'digitalTituloBox',
        'cubiculoNumBox', 'cubiculoActBox', 'actividadesBox'
    );

    if (f === 'externo') {
        hacerObligatorios('datosExterno', ['nombreExt', 'apellido1Ext', 'apellido2Ext', 'sexoExt']);
        hacerObligatorio('servicio', 'servicioSelect');
    } else if (f !== '') {
        hacerObligatorio('identificadorBox', 'identificador');
        setEstadoBusqueda('Primero busca el alumno/usuario por identificador.', 'info');
    }

    actualizarBotonRegistro();
}

function mostrarFlujoNoExterno() {
    const f = document.getElementById('funcion').value;
    const carrera = document.getElementById('carreraSelect').value.trim();
    const adscripcion = document.getElementById('adsSelect').value.trim();

    ocultarSolo('carrera', 'adscripcion', 'servicio');
    ocultar('opcionComunBox', 'digitalNombreBox', 'digitalTituloBox', 'cubiculoNumBox', 'cubiculoActBox', 'actividadesBox');

    if (f === 'externo' || f === '' || !usuarioValidado) {
        return;
    }

    if (f === 'estudiante' || f === 'egresado') {
        hacerObligatorio('carrera', 'carreraSelect');
        if (carrera !== '') {
            hacerObligatorio('servicio', 'servicioSelect');
        }
    } else if (f === 'profesor' || f === 'administrativo') {
        hacerObligatorio('adscripcion', 'adsSelect');
        if (adscripcion !== '') {
            hacerObligatorio('servicio', 'servicioSelect');
        }
    }

    actualizarBotonRegistro();
}

function mostrarServicioEstudiante() {
    mostrarFlujoNoExterno();
}

function mostrarServicioAds() {
    mostrarFlujoNoExterno();
}

function mostrarSubservicio() {
    const s = document.getElementById('servicioSelect').value;
    ocultar(
        'opcionComunBox', 'digitalNombreBox', 'digitalTituloBox',
        'cubiculoNumBox', 'cubiculoActBox', 'actividadesBox'
    );

    if (['consulta', 'devolucion', 'renovacion', 'prestamo'].includes(s)) {
        hacerObligatorio('opcionComunBox', 'opcionComun');
    } else if (s === 'digital') {
        hacerObligatorio('digitalNombreBox', 'digitalNombre');
    } else if (s === 'cubiculo') {
        hacerObligatorio('cubiculoNumBox', 'numCubiculo');
    } else if (s === 'actividades') {
        hacerObligatorio('actividadesBox', 'actividadBiblioteca');
    }

    actualizarBotonRegistro();
}

function mostrarTituloDigital() {
    const d = document.getElementById('digitalNombre').value;
    ocultar('digitalTituloBox');
    if (d) {
        hacerObligatorio('digitalTituloBox', 'digitalTitulo');
    }
    actualizarBotonRegistro();
}

function mostrarActividadCubiculo() {
    const c = document.getElementById('numCubiculo').value;
    ocultar('cubiculoActBox');
    if (c) {
        hacerObligatorio('cubiculoActBox', 'actividadCubiculo');
    }
    actualizarBotonRegistro();
}

document.addEventListener('DOMContentLoaded', function() {
    mostrarCampo();
    emitirEventoNuevaVisita();

    ['nombreExt', 'apellido1Ext', 'apellido2Ext', 'sexoExt', 'opcionComun', 'digitalTitulo', 'actividadCubiculo', 'actividadBiblioteca', 'identificador'].forEach(function(id) {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('input', actualizarBotonRegistro);
            el.addEventListener('change', actualizarBotonRegistro);
        }
    });

    const servicioSelect = document.getElementById('servicioSelect');
    if (servicioSelect) {
        servicioSelect.addEventListener('change', actualizarBotonRegistro);
    }

    const form = document.getElementById('registroForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            actualizarBotonRegistro();
            if (document.getElementById('registrarBtn').style.display === 'none') {
                e.preventDefault();
            }
        });
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

<?php
require_once __DIR__ . '/config/db.php';

try {
    $conn = get_connection();
    $query = 'SELECT i.id_ingreso, i.momento_ingreso, i.servicio, i.actividad, i.detalle, COALESCE(u.identificador, "Sin identificador") AS identificador, COALESCE(CONCAT(u.nombre, " ", u.apellido1, " ", u.apellido2), "Sin usuario") AS alumno FROM ingresoscid i LEFT JOIN usuarioscid u ON u.id_usuario = i.id_usuario ORDER BY i.momento_ingreso DESC';
    $result = $conn->query($query);
} catch (Throwable $e) {
    http_response_code(500);
    echo $e->getMessage();
    exit;
}

$filename = 'visitas_cid_' . date('Ymd_His') . '.xls';
header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);

$handle = fopen('php://output', 'w');
fputcsv($handle, ['ID', 'Fecha y hora', 'Servicio', 'Actividad', 'Detalle', 'Identificador', 'Alumno'], ';');
while ($row = $result->fetch_assoc()) {
    fputcsv($handle, [
        $row['id_ingreso'],
        $row['momento_ingreso'],
        $row['servicio'] ?? '',
        $row['actividad'] ?? '',
        $row['detalle'] ?? '',
        $row['identificador'] ?? 'Sin identificador',
        $row['alumno'] ?? 'Sin usuario',
    ], ';');
}
fclose($handle);

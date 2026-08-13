<?php

declare(strict_types=1);

function load_env_file(): void
{
    $path = dirname(__DIR__) . '/.env';
    if (!is_file($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
        $key = trim($key);
        $value = trim($value, " \t\n\r\0\x0B\"'");

        if ($key !== '') {
            $_ENV[$key] = $value;
            putenv($key . '=' . $value);
        }
    }
}

load_env_file();

function env(string $key, string $default = ''): string
{
    $value = getenv($key);
    if ($value === false || $value === null || $value === '') {
        $value = $_ENV[$key] ?? $default;
    }

    return (string) $value;
}

function app_base_path(): string
{
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $scriptDir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');

    if ($scriptDir === '' || $scriptDir === '/') {
        return '';
    }

    if (basename($scriptDir) === 'gestor') {
        $scriptDir = rtrim(str_replace('\\', '/', dirname($scriptDir)), '/');
    }

    return $scriptDir === '/' ? '' : $scriptDir;
}

function app_url(string $path = ''): string
{
    $basePath = app_base_path();
    $path = ltrim($path, '/');

    if ($path === '') {
        return $basePath === '' ? '/' : $basePath . '/';
    }

    return ($basePath === '' ? '' : $basePath) . '/' . $path;
}

function get_connection(): mysqli
{
    static $connection = null;

    if ($connection instanceof mysqli) {
        return $connection;
    }

    $host = env('DB_HOST', '127.0.0.1');
    $port = (int) env('DB_PORT', '3306');
    $user = env('DB_USER', 'root');
    $pass = env('DB_PASS', '');
    $database = env('DB_NAME', 'cidb');

    $connection = @new mysqli($host, $user, $pass, $database, $port);
    if ($connection->connect_errno) {
        throw new RuntimeException('No fue posible conectar a MySQL. Verifica DB_HOST, DB_PORT, DB_USER, DB_PASS y que el servidor esté activo.');
    }

    $connection->set_charset('utf8mb4');
    return $connection;
}

function get_reference_options(): array
{
    $conn = get_connection();
    $options = [
        'carreras' => [],
        'adscripciones' => [],
        'tipos_usuarios' => [],
    ];

    $queries = [
        'carreras' => ['SELECT id_carrera, nombre_carrera FROM carreras ORDER BY nombre_carrera', 'id_carrera', 'nombre_carrera'],
        'adscripciones' => ['SELECT id_adscripcion, nombre_adscripcion FROM adscripciones ORDER BY nombre_adscripcion', 'id_adscripcion', 'nombre_adscripcion'],
        'tipos_usuarios' => ['SELECT id_tipo_usuario, nombre_tipo FROM tipos_usuarios ORDER BY nombre_tipo', 'id_tipo_usuario', 'nombre_tipo'],
    ];

    foreach ($queries as $key => [$sql, $idColumn, $nameColumn]) {
        $result = $conn->query($sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $options[$key][] = ['id' => $row[$idColumn], 'nombre' => $row[$nameColumn]];
            }
        }
    }

    return $options;
}

<?php

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/vendor/autoload.php';

use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;

final class VisitNotifier implements MessageComponentInterface
{
    private SplObjectStorage $clients;

    public function __construct()
    {
        $this->clients = new SplObjectStorage();
    }

    public function onOpen(ConnectionInterface $conn): void
    {
        $this->clients->attach($conn);
    }

    public function onMessage(ConnectionInterface $from, $msg): void
    {
        $data = json_decode((string) $msg, true);
        if (!is_array($data) || !isset($data['type'])) {
            return;
        }

        // Reenviar solo eventos de nuevas visitas.
        if ($data['type'] !== 'new_visit') {
            return;
        }

        $payload = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($payload === false) {
            return;
        }

        foreach ($this->clients as $client) {
            $client->send($payload);
        }
    }

    public function onClose(ConnectionInterface $conn): void
    {
        if ($this->clients->contains($conn)) {
            $this->clients->detach($conn);
        }
    }

    public function onError(ConnectionInterface $conn, \Exception $e): void
    {
        if ($this->clients->contains($conn)) {
            $this->clients->detach($conn);
        }
        $conn->close();
    }
}

$host = websocket_bind_host();
$port = websocket_port();

$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new VisitNotifier()
        )
    ),
    $port,
    $host
);

echo 'Servidor websocket activo en ws://' . $host . ':' . $port . PHP_EOL;
$server->run();

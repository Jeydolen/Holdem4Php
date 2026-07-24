<?php

namespace App\Game\WebSocket;

use App\Entity\User;
use OpenSwoole\WebSocket\Server;
use Symfony\Component\Serializer\SerializerInterface;

class ConnectionWrapper
{
    private ?User $user = null;

    public function __construct(
        private Server $server,
        private int $fd,
        private SerializerInterface $serializer
    ) {
    }

    public function close(): void
    {
        $this->server->disconnect($this->fd, Server::WEBSOCKET_CLOSE_NORMAL);
    }

    public function send(mixed $sendBuffer): bool|null
    {
        if (!$this->server->exists($this->fd)) {
            return null;
        }

        return $this->server->push($this->fd, $sendBuffer);
    }

    public function sendJson(mixed $data, array $context = []): bool|null
    {
        $json = $this->serializer->serialize($data, "json", $context);
        return $this->send($json);
    }

    public function setUser(User $user): void
    {
        $this->user = $user;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }
}
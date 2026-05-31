<?php

namespace App\Game\WebSocket;

use App\Entity\User;
use Symfony\Component\Serializer\SerializerInterface;
use Workerman\Connection\ConnectionInterface;
use Workerman\Connection\TcpConnection;

class ConnectionWrapper extends ConnectionInterface
{
    public int $auth_timer_id;

    private ?User $user = null;

    public function __construct(
        private TcpConnection $connection,
        private SerializerInterface $serializer
    ) {
    }

    public function close(mixed $data = null, bool $raw = false): void
    {
        $this->connection->close($data, $raw);
    }

    public function send(mixed $sendBuffer, bool $raw = false): bool|null
    {
        return $this->connection->send($sendBuffer, $raw);
    }

    public function sendJson(mixed $data)
    {
        $json = $this->serializer->serialize($data, "json");
        return $this->send($json);
    }

    public function getRemoteIp(): string
    {
        return $this->connection->getRemoteIp();
    }

    public function getLocalAddress(): string
    {
        return $this->connection->getLocalAddress();
    }

    public function getRemotePort(): int
    {
        return $this->connection->getRemotePort();
    }

    public function getRemoteAddress(): string
    {
        return $this->connection->getRemoteAddress();
    }

    public function getLocalIp(): string
    {
        return $this->connection->getLocalIp();
    }

    public function getLocalPort(): int
    {
        return $this->connection->getLocalPort();
    }

    public function isIpV4(): bool
    {
        return $this->connection->isIpV4();
    }

    public function isIpV6(): bool
    {
        return $this->connection->isIpV6();
    }

    public function setUser(User $user)
    {
        $this->user = $user;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }
}
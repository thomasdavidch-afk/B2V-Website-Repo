<?php

namespace App\Service;

use MongoDB\Client;
use MongoDB\Collection;
use MongoDB\BSON\UTCDateTime;
use Symfony\Component\Security\Core\User\UserInterface;

class AuditLoggerService
{
    private Collection $collection;

    public function __construct(Client $mongoClient, string $mongoDatabase)
    {
        $this->collection = $mongoClient->selectDatabase($mongoDatabase)->selectCollection('audit_logs');
    }

    /**
     * Enregistre un événement dans la base NoSQL
     */
    public function logEvent(
        string $eventType,
        ?UserInterface $author = null,
        array $context = []
    ): void {
        $document = [
            'event_type'  => $eventType,
            'created_at'  => new UTCDateTime(),
            'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            'user_agent'  => $_SERVER['HTTP_USER_AGENT'] ?? 'CLI',
            'author'      => $author ? [
                'user_id' => method_exists($author, 'getId') ? $author->getId() : null,
                'email'   => $author->getUserIdentifier(),
            ] : null,
            'context'     => $context,
        ];

        $this->collection->insertOne($document);
    }

    /**
     * Recherche des logs par type d'événement
     */
    public function findLogsByEventType(string $eventType, int $limit = 50): array
    {
        return $this->collection
            ->find(
                ['event_type' => $eventType],
                ['limit' => $limit, 'sort' => ['created_at' => -1]]
            )
            ->toArray();
    }

    /**
     * Récupère tous les logs d'audit récents (tous types confondus)
     */
    public function findAllLogs(int $limit = 50): array
    {
        return $this->collection
            ->find(
                [],
                ['limit' => $limit, 'sort' => ['created_at' => -1]]
            )
            ->toArray();
    }
}
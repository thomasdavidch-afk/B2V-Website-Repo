<?php

namespace App\Controller;

use App\Service\AuditLoggerService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[OA\Tag(name: 'Audit')]
class AuditTestController extends AbstractController
{
    /**
     * Récupérer les logs d'audit NoSQL (Panneau Administrateur)
     */
    #[Route('/api/admin/audit-logs', name: 'api_admin_audit_logs', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    #[OA\Get(
        path: '/api/admin/audit-logs',
        summary: "Consulter l'historique des journaux d'audit NoSQL (Admin)",
        description: "Permet aux administrateurs de consulter l'historique complet des actions tracées dans MongoDB Atlas.",
        security: [['Bearer' => []]]
    )]
    #[OA\Parameter(
        name: 'limit',
        in: 'query',
        description: 'Nombre de logs à retourner (défaut : 50, max : 200)',
        required: false,
        schema: new OA\Schema(type: 'integer', default: 50)
    )]
    #[OA\Response(
        response: 200,
        description: 'Historique des logs récupéré avec succès depuis MongoDB',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'count', type: 'integer', example: 2),
                new OA\Property(
                    property: 'logs',
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id', type: 'string', example: '65e9b8f0d4f1a23c89b4e123'),
                            new OA\Property(property: 'event_type', type: 'string', example: 'USER_REGISTERED'),
                            new OA\Property(
                                property: 'author',
                                properties: [
                                    new OA\Property(property: 'user_id', type: 'integer', nullable: true, example: 1),
                                    new OA\Property(property: 'email', type: 'string', nullable: true, example: 'admin@studio-sport.fr')
                                ],
                                type: 'object',
                                nullable: true
                            ),
                            new OA\Property(property: 'ip_address', type: 'string', example: '127.0.0.1'),
                            new OA\Property(property: 'user_agent', type: 'string', example: 'Mozilla/5.0...'),
                            new OA\Property(property: 'context', type: 'object'),
                            new OA\Property(property: 'created_at', type: 'string', example: '2026-09-07 10:15:30')
                        ],
                        type: 'object'
                    )
                )
            ]
        )
    )]
    #[OA\Response(response: 401, description: 'JWT manquant ou expiré')]
    #[OA\Response(response: 403, description: 'Accès réservé aux administrateurs (ROLE_ADMIN)')]
    public function getAdminLogs(Request $request, AuditLoggerService $auditLogger): JsonResponse
    {
        $limit = max(1, min((int) $request->query->get('limit', 50), 200));
        $logs = $auditLogger->findAllLogs($limit);

        $formattedLogs = array_map(function ($log) {
            $logArray = (array) $log;

            return [
                'id'         => (string) $logArray['_id'],
                'event_type' => $logArray['event_type'] ?? 'UNKNOWN',
                'author'     => isset($logArray['author']) ? (array) $logArray['author'] : null,
                'ip_address' => $logArray['ip_address'] ?? null,
                'user_agent' => $logArray['user_agent'] ?? null,
                'context'    => isset($logArray['context']) ? (array) $logArray['context'] : [],
                'created_at' => isset($logArray['created_at']) && $logArray['created_at'] instanceof \MongoDB\BSON\UTCDateTime
                    ? $logArray['created_at']->toDateTime()->format('Y-m-d H:i:s')
                    : null,
            ];
        }, $logs);

        return $this->json([
            'success' => true,
            'count'   => count($formattedLogs),
            'logs'    => $formattedLogs,
        ]);
    }

    #[Route('/api/test-audit', name: 'app_test_audit', methods: ['GET'])]
    #[OA\Get(
        path: '/api/test-audit',
        summary: 'Tester la journalisation MongoDB (Audit Log)',
        description: 'Écrit un événement de test dans MongoDB et récupère les 5 derniers logs de ce type.',
        security: [['Bearer' => []]]
    )]
    #[OA\Response(
        response: 200,
        description: 'Test réussi : événement écrit et récupéré depuis MongoDB',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'message', type: 'string', example: 'Log enregistré et récupéré depuis MongoDB !'),
                new OA\Property(
                    property: 'logs',
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id', type: 'string', example: '65e9b8f0d4f1a23c89b4e123'),
                            new OA\Property(property: 'event_type', type: 'string', example: 'TEST_AUDIT_MONGODB'),
                            new OA\Property(property: 'context', type: 'object'),
                            new OA\Property(property: 'created_at', type: 'string', example: '2026-09-07 09:50:00')
                        ],
                        type: 'object'
                    )
                )
            ]
        )
    )]
    #[OA\Response(
        response: 401,
        description: 'JWT manquant ou invalide'
    )]
    public function test(AuditLoggerService $auditLogger): JsonResponse
    {
        // 1. Écriture d'un log
        $auditLogger->logEvent('TEST_AUDIT_MONGODB', $this->getUser(), [
            'action'  => 'Test de connexion NoSQL réussi',
            'details' => 'Le service AuditLogger fonctionne parfaitement avec MongoDB',
        ]);

        // 2. Lecture des logs de test
        $logs = $auditLogger->findLogsByEventType('TEST_AUDIT_MONGODB', 5);

        // Transformation pour l'affichage JSON
        $formattedLogs = array_map(function ($log) {
            $logArray = (array) $log;

            return [
                'id'         => (string) $logArray['_id'],
                'event_type' => $logArray['event_type'] ?? 'UNKNOWN',
                'context'    => isset($logArray['context']) ? (array) $logArray['context'] : [],
                'created_at' => isset($logArray['created_at']) && $logArray['created_at'] instanceof \MongoDB\BSON\UTCDateTime
                    ? $logArray['created_at']->toDateTime()->format('Y-m-d H:i:s')
                    : null,
            ];
        }, $logs);

        return $this->json([
            'message' => 'Log enregistré et récupéré depuis MongoDB !',
            'logs'    => $formattedLogs,
        ]);
    }
}
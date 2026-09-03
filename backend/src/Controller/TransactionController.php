<?php

namespace App\Controller;

use App\Entity\CarnetSession;
use App\Entity\Transaction;
use App\Entity\User;
use App\Repository\CarnetSessionRepository;
use App\Repository\TransactionRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/transactions', name: 'api_transactions_')]
#[OA\Tag(name: 'Transactions')]
class TransactionController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private TransactionRepository $transactionRepository,
        private UserRepository $userRepository,
        private CarnetSessionRepository $carnetRepository
    ) {}

    /**
     * Obtenir l'historique de ses transactions (Adhérent connecté)
     */
    #[Route('/me', name: 'me', methods: ['GET'])]
    #[OA\Get(
        path: '/api/transactions/me',
        summary: 'Historique des transactions de l\'adhérent connecté',
        security: [['Bearer' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des transactions',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 1),
                            new OA\Property(property: 'idHelloAsso', type: 'string', example: 'PAY-8923472', nullable: true),
                            new OA\Property(property: 'typeTransaction', type: 'string', example: 'Achat Pack 10 Séances'),
                            new OA\Property(property: 'nbSessions', type: 'integer', example: 10),
                            new OA\Property(property: 'montant', type: 'number', format: 'float', example: 60.00),
                            new OA\Property(property: 'origin', type: 'string', example: 'HelloAsso'),
                            new OA\Property(property: 'dateTransaction', type: 'string', format: 'date-time', example: '2026-03-15T14:30:00+00:00')
                        ]
                    )
                )
            ),
            new OA\Response(response: 401, description: 'Non authentifié')
        ]
    )]
    public function getMyTransactions(): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->json(['error' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        $transactions = $this->transactionRepository->findBy(['user' => $user], ['dateTransaction' => 'DESC']);

        $data = array_map(function (Transaction $t) {
            return [
                'id' => $t->getId(),
                'idHelloAsso' => $t->getIdHelloAsso(),
                'typeTransaction' => $t->getTypeTransaction(),
                'nbSessions' => $t->getNbSessions(),
                'montant' => $t->getMontant(),
                'origin' => $t->getOrigin(),
                'dateTransaction' => $t->getDateTransaction()?->format(\DateTimeInterface::ATOM),
            ];
        }, $transactions);

        return $this->json($data, Response::HTTP_OK);
    }

    /**
     * Lister toutes les transactions (Admin)
     */
    #[Route('', name: 'list', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    #[OA\Get(
        path: '/api/transactions',
        summary: 'Lister toutes les transactions (Admin)',
        security: [['Bearer' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste de toutes les transactions',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 1),
                            new OA\Property(property: 'idHelloAsso', type: 'string', example: 'PAY-8923472', nullable: true),
                            new OA\Property(property: 'typeTransaction', type: 'string', example: 'Achat Pack'),
                            new OA\Property(property: 'nbSessions', type: 'integer', example: 10),
                            new OA\Property(property: 'montant', type: 'number', example: 60.0),
                            new OA\Property(property: 'origin', type: 'string', example: 'HelloAsso'),
                            new OA\Property(property: 'dateTransaction', type: 'string', format: 'date-time'),
                            new OA\Property(
                                property: 'user',
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 3),
                                    new OA\Property(property: 'nom', type: 'string', example: 'Dupont'),
                                    new OA\Property(property: 'prenom', type: 'string', example: 'Jean'),
                                    new OA\Property(property: 'email', type: 'string', example: 'jean.dupont@example.com')
                                ]
                            )
                        ]
                    )
                )
            ),
            new OA\Response(response: 403, description: 'Accès refusé')
        ]
    )]
    public function list(): JsonResponse
    {
        $transactions = $this->transactionRepository->findBy([], ['dateTransaction' => 'DESC']);

        $data = array_map(function (Transaction $t) {
            $user = $t->getUser();
            return [
                'id' => $t->getId(),
                'idHelloAsso' => $t->getIdHelloAsso(),
                'typeTransaction' => $t->getTypeTransaction(),
                'nbSessions' => $t->getNbSessions(),
                'montant' => $t->getMontant(),
                'origin' => $t->getOrigin(),
                'dateTransaction' => $t->getDateTransaction()?->format(\DateTimeInterface::ATOM),
                'user' => [
                    'id' => $user->getId(),
                    'nom' => method_exists($user, 'getNom') ? $user->getNom() : null,
                    'prenom' => method_exists($user, 'getPrenom') ? $user->getPrenom() : null,
                    'email' => $user->getUserIdentifier(),
                ]
            ];
        }, $transactions);

        return $this->json($data, Response::HTTP_OK);
    }

    /**
     * Webhook HelloAsso : point d'entrée pour la future synchronisation API
     */
    #[Route('/webhook/helloasso', name: 'webhook_helloasso', methods: ['POST'])]
    #[OA\Post(
        path: '/api/transactions/webhook/helloasso',
        summary: 'Point d\'entrée Webhook pour HelloAsso (Simulation et intégration future)',
        description: 'Permet de recevoir les notifications d\'achat HelloAsso, d\'enregistrer la transaction et de créditer automatiquement le carnet de l\'adhérent.',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'nbSessions', 'typeTransaction'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', example: 'jean.dupont@example.com', description: 'Email de l\'acheteur adhérent'),
                    new OA\Property(property: 'idHelloAsso', type: 'string', example: 'HA-ORDER-2026-9923', description: 'ID de commande / paiement HelloAsso'),
                    new OA\Property(property: 'typeTransaction', type: 'string', example: 'Pack 10 séances'),
                    new OA\Property(property: 'nbSessions', type: 'integer', example: 10),
                    new OA\Property(property: 'montant', type: 'number', format: 'float', example: 75.00)
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Webhook traité avec succès, carnet crédité',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Transaction HelloAsso traitée avec succès'),
                        new OA\Property(property: 'transactionId', type: 'integer', example: 12),
                        new OA\Property(property: 'nouveauSoldeRestant', type: 'integer', example: 10)
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Données de webhook invalides ou incomplètes'),
            new OA\Response(response: 404, description: 'Adhérent introuvable avec cette adresse email')
        ]
    )]
    public function webhookHelloAsso(Request $request): JsonResponse
    {
        // TODO: En production, valider la signature / jeton de sécurité envoyé par HelloAsso dans les headers
        $data = json_decode($request->getContent(), true);

        if (!$data || !isset($data['email'], $data['nbSessions'], $data['typeTransaction'])) {
            return $this->json(['error' => 'Payload webhook incomplet ou invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->userRepository->findOneBy(['email' => $data['email']]);

        if (!$user) {
            return $this->json([
                'error' => "Aucun utilisateur trouvé avec l'email {$data['email']}. La transaction ne peut être liée."
            ], Response::HTTP_NOT_FOUND);
        }

        $nbSessions = (int) $data['nbSessions'];
        $montant = isset($data['montant']) ? (float) $data['montant'] : null;
        $idHelloAsso = $data['idHelloAsso'] ?? null;

        // 1. Enregistrement de la transaction
        $transaction = new Transaction();
        $transaction->setUser($user);
        $transaction->setIdHelloAsso($idHelloAsso);
        $transaction->setTypeTransaction($data['typeTransaction']);
        $transaction->setNbSessions($nbSessions);
        $transaction->setMontant($montant);
        $transaction->setOrigin('HelloAsso');
        $transaction->setDateTransaction(new \DateTimeImmutable());

        $this->em->persist($transaction);

        // 2. Mise à jour automatique du Carnet de Session
        $carnet = $this->carnetRepository->findOneBy(['user' => $user]);
        if (!$carnet) {
            $carnet = new CarnetSession();
            $carnet->setUser($user);
            $carnet->setNbSessionsTotal($nbSessions);
            $carnet->setNbSessionsRestant($nbSessions);
            $this->em->persist($carnet);
        } else {
            $carnet->setNbSessionsTotal($carnet->getNbSessionsTotal() + $nbSessions);
            $carnet->setNbSessionsRestant($carnet->getNbSessionsRestant() + $nbSessions);
        }

        $this->em->flush();

        return $this->json([
            'message' => 'Transaction HelloAsso traitée avec succès.',
            'transactionId' => $transaction->getId(),
            'nouveauSoldeRestant' => $carnet->getNbSessionsRestant(),
            'nouveauTotal' => $carnet->getNbSessionsTotal()
        ], Response::HTTP_CREATED);
    }

    /**
     * Création manuelle d'une transaction par un Administrateur
     */
    #[Route('/admin', name: 'admin_create', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    #[OA\Post(
        path: '/api/transactions/admin',
        summary: 'Créer manuellement une transaction (Admin)',
        security: [['Bearer' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['userId', 'nbSessions', 'typeTransaction'],
                properties: [
                    new OA\Property(property: 'userId', type: 'integer', example: 5),
                    new OA\Property(property: 'typeTransaction', type: 'string', example: 'Règlement Espèces / Chèque'),
                    new OA\Property(property: 'nbSessions', type: 'integer', example: 5),
                    new OA\Property(property: 'montant', type: 'number', format: 'float', example: 35.00),
                    new OA\Property(property: 'origin', type: 'string', example: 'ADMIN', default: 'ADMIN')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Transaction enregistrée et carnet mis à jour',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Transaction enregistrée avec succès.'),
                        new OA\Property(property: 'transactionId', type: 'integer', example: 4)
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Données invalides'),
            new OA\Response(response: 404, description: 'Utilisateur introuvable'),
            new OA\Response(response: 403, description: 'Accès refusé')
        ]
    )]
    public function createAdminTransaction(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['userId'], $data['nbSessions'], $data['typeTransaction'])) {
            return $this->json(['error' => 'Champs obligatoires manquants (userId, nbSessions, typeTransaction).'], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->userRepository->find($data['userId']);
        if (!$user) {
            return $this->json(['error' => 'Utilisateur introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $nbSessions = (int) $data['nbSessions'];
        $montant = isset($data['montant']) ? (float) $data['montant'] : null;
        $origin = $data['origin'] ?? 'ADMIN';

        // 1. Création de la transaction
        $transaction = new Transaction();
        $transaction->setUser($user);
        $transaction->setTypeTransaction($data['typeTransaction']);
        $transaction->setNbSessions($nbSessions);
        $transaction->setMontant($montant);
        $transaction->setOrigin($origin);
        $transaction->setDateTransaction(new \DateTimeImmutable());

        $this->em->persist($transaction);

        // 2. Mise à jour du carnet
        $carnet = $this->carnetRepository->findOneBy(['user' => $user]);
        if (!$carnet) {
            $carnet = new CarnetSession();
            $carnet->setUser($user);
            $carnet->setNbSessionsTotal($nbSessions);
            $carnet->setNbSessionsRestant($nbSessions);
            $this->em->persist($carnet);
        } else {
            $carnet->setNbSessionsTotal($carnet->getNbSessionsTotal() + $nbSessions);
            $carnet->setNbSessionsRestant($carnet->getNbSessionsRestant() + $nbSessions);
        }

        $this->em->flush();

        return $this->json([
            'message' => 'Transaction enregistrée avec succès.',
            'transactionId' => $transaction->getId(),
            'carnet' => [
                'total' => $carnet->getNbSessionsTotal(),
                'restant' => $carnet->getNbSessionsRestant()
            ]
        ], Response::HTTP_CREATED);
    }
}

<?php

namespace App\Controller;

use App\Entity\CarnetSession;
use App\Entity\User;
use App\Repository\CarnetSessionRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/carnets', name: 'api_carnets_')]
#[OA\Tag(name: 'Carnets de Session')]
class CarnetSessionController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private CarnetSessionRepository $carnetRepository,
        private UserRepository $userRepository
    ) {}

    /**
     * Obtenir le carnet de l'adhérent actuellement connecté
     */
    #[Route('/me', name: 'me', methods: ['GET'])]
    #[OA\Get(
        path: '/api/carnets/me',
        summary: 'Obtenir le carnet de séances de l\'utilisateur connecté',
        security: [['Bearer' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Détails du carnet de séances',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'nbSessionsTotal', type: 'integer', example: 10),
                        new OA\Property(property: 'nbSessionsRestant', type: 'integer', example: 7),
                        new OA\Property(property: 'userId', type: 'integer', example: 4)
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Non authentifié'),
            new OA\Response(response: 404, description: 'Carnet introuvable')
        ]
    )]
    public function getMyCarnet(): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->json(['error' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        $carnet = $this->carnetRepository->findOneBy(['user' => $user]);

        if (!$carnet) {
            return $this->json(['error' => 'Aucun carnet trouvé pour cet utilisateur'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'id' => $carnet->getId(),
            'nbSessionsTotal' => $carnet->getNbSessionsTotal(),
            'nbSessionsRestant' => $carnet->getNbSessionsRestant(),
            'userId' => $user->getId(),
        ], Response::HTTP_OK);
    }

    /**
     * Lister tous les carnets de session (Admin)
     */
    #[Route('', name: 'list', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    #[OA\Get(
        path: '/api/carnets',
        summary: 'Lister tous les carnets (Admin)',
        security: [['Bearer' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste de tous les carnets',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 1),
                            new OA\Property(property: 'nbSessionsTotal', type: 'integer', example: 10),
                            new OA\Property(property: 'nbSessionsRestant', type: 'integer', example: 4),
                            new OA\Property(
                                property: 'user',
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 12),
                                    new OA\Property(property: 'nom', type: 'string', example: 'Dupont'),
                                    new OA\Property(property: 'prenom', type: 'string', example: 'Jean'),
                                    new OA\Property(property: 'email', type: 'string', example: 'jean.dupont@example.com')
                                ]
                            )
                        ]
                    )
                )
            ),
            new OA\Response(response: 401, description: 'Non authentifié'),
            new OA\Response(response: 403, description: 'Accès refusé (Admin requis)')
        ]
    )]
    public function list(): JsonResponse
    {
        $carnets = $this->carnetRepository->findAll();

        $data = array_map(function (CarnetSession $carnet) {
            $user = $carnet->getUser();
            return [
                'id' => $carnet->getId(),
                'nbSessionsTotal' => $carnet->getNbSessionsTotal(),
                'nbSessionsRestant' => $carnet->getNbSessionsRestant(),
                'user' => [
                    'id' => $user?->getId(),
                    'nom' => method_exists($user, 'getNom') ? $user->getNom() : null,
                    'prenom' => method_exists($user, 'getPrenom') ? $user->getPrenom() : null,
                    'email' => $user?->getUserIdentifier(),
                ]
            ];
        }, $carnets);

        return $this->json($data, Response::HTTP_OK);
    }

    /**
     * Obtenir le carnet d'un adhérent spécifique par son ID (Admin)
     */
    #[Route('/user/{id}', name: 'by_user', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    #[OA\Get(
        path: '/api/carnets/user/{id}',
        summary: 'Obtenir le carnet d\'un utilisateur par son ID (Admin)',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID de l\'utilisateur',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Détails du carnet',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'nbSessionsTotal', type: 'integer', example: 20),
                        new OA\Property(property: 'nbSessionsRestant', type: 'integer', example: 15),
                        new OA\Property(property: 'userId', type: 'integer', example: 5)
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Utilisateur ou carnet non trouvé'),
            new OA\Response(response: 403, description: 'Accès refusé')
        ]
    )]
    public function getByUser(int $id): JsonResponse
    {
        $user = $this->userRepository->find($id);

        if (!$user) {
            return $this->json(['error' => 'Utilisateur introuvable'], Response::HTTP_NOT_FOUND);
        }

        $carnet = $this->carnetRepository->findOneBy(['user' => $user]);

        if (!$carnet) {
            return $this->json(['error' => 'Aucun carnet trouvé pour cet utilisateur'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'id' => $carnet->getId(),
            'nbSessionsTotal' => $carnet->getNbSessionsTotal(),
            'nbSessionsRestant' => $carnet->getNbSessionsRestant(),
            'userId' => $user->getId(),
        ], Response::HTTP_OK);
    }

    /**
     * Créditer / ajouter des séances à un adhérent (Admin)
     */
    #[Route('/user/{id}/crediter', name: 'crediter', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    #[OA\Post(
        path: '/api/carnets/user/{id}/crediter',
        summary: 'Ajouter/créditer des séances à un utilisateur (Admin)',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID de l\'utilisateur à créditer',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['nbSessions'],
                properties: [
                    new OA\Property(property: 'nbSessions', type: 'integer', example: 5, description: 'Nombre de séances à ajouter')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Séances créditées avec succès',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: '5 séances ajoutées avec succès.'),
                        new OA\Property(
                            property: 'carnet',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'nbSessionsTotal', type: 'integer', example: 15),
                                new OA\Property(property: 'nbSessionsRestant', type: 'integer', example: 12)
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Données invalides'),
            new OA\Response(response: 404, description: 'Utilisateur non trouvé'),
            new OA\Response(response: 403, description: 'Accès refusé')
        ]
    )]
    public function crediter(int $id, Request $request): JsonResponse
    {
        $user = $this->userRepository->find($id);

        if (!$user) {
            return $this->json(['error' => 'Utilisateur introuvable'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        $sessionsAAjouter = $data['nbSessions'] ?? null;

        if ($sessionsAAjouter === null || !is_numeric($sessionsAAjouter) || $sessionsAAjouter <= 0) {
            return $this->json(['error' => 'Veuillez fournir un nombre de séances valide supérieur à 0'], Response::HTTP_BAD_REQUEST);
        }

        $carnet = $this->carnetRepository->findOneBy(['user' => $user]);

        if (!$carnet) {
            $carnet = new CarnetSession();
            $carnet->setUser($user);
            $carnet->setNbSessionsTotal((int) $sessionsAAjouter);
            $carnet->setNbSessionsRestant((int) $sessionsAAjouter);
            $this->em->persist($carnet);
        } else {
            $carnet->setNbSessionsTotal($carnet->getNbSessionsTotal() + (int) $sessionsAAjouter);
            $carnet->setNbSessionsRestant($carnet->getNbSessionsRestant() + (int) $sessionsAAjouter);
        }

        $this->em->flush();

        return $this->json([
            'message' => "{$sessionsAAjouter} séances ajoutées avec succès.",
            'carnet' => [
                'id' => $carnet->getId(),
                'nbSessionsTotal' => $carnet->getNbSessionsTotal(),
                'nbSessionsRestant' => $carnet->getNbSessionsRestant(),
            ]
        ], Response::HTTP_OK);
    }

    /**
     * Modifier manuellement le nombre total et restant d'un carnet (Admin)
     */
    #[Route('/{id}', name: 'update', methods: ['PUT', 'PATCH'])]
    #[IsGranted('ROLE_ADMIN')]
    #[OA\Put(
        path: '/api/carnets/{id}',
        summary: 'Modifier directement les compteurs d\'un carnet (Admin)',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID du carnet',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'nbSessionsTotal', type: 'integer', example: 20),
                    new OA\Property(property: 'nbSessionsRestant', type: 'integer', example: 10)
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Carnet modifié avec succès',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Carnet mis à jour avec succès.'),
                        new OA\Property(
                            property: 'carnet',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'nbSessionsTotal', type: 'integer', example: 20),
                                new OA\Property(property: 'nbSessionsRestant', type: 'integer', example: 10)
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Carnet non trouvé'),
            new OA\Response(response: 403, description: 'Accès refusé')
        ]
    )]
    public function update(int $id, Request $request): JsonResponse
    {
        $carnet = $this->carnetRepository->find($id);

        if (!$carnet) {
            return $this->json(['error' => 'Carnet introuvable'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['nbSessionsTotal'])) {
            $carnet->setNbSessionsTotal((int) $data['nbSessionsTotal']);
        }

        if (isset($data['nbSessionsRestant'])) {
            $carnet->setNbSessionsRestant((int) $data['nbSessionsRestant']);
        }

        $this->em->flush();

        return $this->json([
            'message' => 'Carnet mis à jour avec succès.',
            'carnet' => [
                'id' => $carnet->getId(),
                'nbSessionsTotal' => $carnet->getNbSessionsTotal(),
                'nbSessionsRestant' => $carnet->getNbSessionsRestant(),
            ]
        ], Response::HTTP_OK);
    }
}

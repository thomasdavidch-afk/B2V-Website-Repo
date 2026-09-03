<?php

namespace App\Controller;

use App\Entity\CarnetSession;
use App\Entity\Presence;
use App\Entity\SessionEntrainement;
use App\Entity\User;
use App\Repository\CarnetSessionRepository;
use App\Repository\PresenceRepository;
use App\Repository\SessionEntrainementRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin/presences')]
#[IsGranted('ROLE_ADMIN')]
#[OA\Tag(name: 'Presences (Admin)')]
class PresenceController extends AbstractController
{
    /**
     * 1. Liste des présences pour une session donnée
     */
    #[Route('/session/{sessionId}', name: 'api_admin_presences_by_session', methods: ['GET'])]
    #[OA\Get(
        path: '/api/admin/presences/session/{sessionId}',
        summary: 'Lister les présences d\'un créneau d\'entraînement',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'sessionId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des présences récupérée',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 1),
                            new OA\Property(property: 'statut', type: 'string', example: 'present'),
                            new OA\Property(property: 'date_enregistrement', type: 'string', example: '2026-06-15 18:05:00'),
                            new OA\Property(
                                property: 'adherent',
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 4),
                                    new OA\Property(property: 'nom', type: 'string', example: 'Dupont'),
                                    new OA\Property(property: 'prenom', type: 'string', example: 'Jean'),
                                    new OA\Property(property: 'email', type: 'string', example: 'jean.dupont@test.fr'),
                                    new OA\Property(property: 'sessions_restantes', type: 'integer', example: 7)
                                ]
                            )
                        ]
                    )
                )
            ),
            new OA\Response(response: 404, description: 'Séance non trouvée')
        ]
    )]
    public function getPresencesBySession(
        int $sessionId,
        SessionEntrainementRepository $sessionRepository,
        PresenceRepository $presenceRepository
    ): JsonResponse {
        $session = $sessionRepository->find($sessionId);
        if (!$session) {
            return $this->json(['message' => 'Séance d\'entraînement non trouvée.'], Response::HTTP_NOT_FOUND);
        }

        $presences = $presenceRepository->findBy(['sessionEntrainement' => $session]);

        $data = array_map(function (Presence $presence) {
            $user = $presence->getUser();
            $carnet = $user?->getCarnetSession();

            return [
                'id' => $presence->getId(),
                'statut' => $presence->getStatut(),
                'date_enregistrement' => $presence->getDateEnregistrement()?->format('Y-m-d H:i:s'),
                'adherent' => $user ? [
                    'id' => $user->getId(),
                    'nom' => $user->getNom(),
                    'prenom' => $user->getPrenom(),
                    'email' => $user->getEmail(),
                    'sessions_restantes' => $carnet ? $carnet->getNbSessionsRestant() : 0,
                ] : null,
            ];
        }, $presences);

        return $this->json($data);
    }

    /**
     * 2. Enregistrer une présence et impacter le CarnetSession
     */
    #[Route('', name: 'api_admin_presences_create', methods: ['POST'])]
    #[OA\Post(
        path: '/api/admin/presences',
        summary: 'Émarger / Pointer un adhérent à une session (décompte carnet)',
        security: [['Bearer' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['user_id', 'session_id'],
                properties: [
                    new OA\Property(property: 'user_id', type: 'integer', example: 4),
                    new OA\Property(property: 'session_id', type: 'integer', example: 2),
                    new OA\Property(property: 'statut', type: 'string', example: 'present', enum: ['present', 'absent', 'annule'])
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Présence enregistrée et carnet mis à jour'),
            new OA\Response(response: 400, description: 'Carnet épuisé, statut invalide ou adhérent déjà pointé'),
            new OA\Response(response: 404, description: 'Utilisateur ou Séance non trouvé')
        ]
    )]
    public function createPresence(
        Request $request,
        UserRepository $userRepository,
        SessionEntrainementRepository $sessionRepository,
        PresenceRepository $presenceRepository,
        CarnetSessionRepository $carnetRepository,
        EntityManagerInterface $em
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $userId = $data['user_id'] ?? null;
        $sessionId = $data['session_id'] ?? null;
        $statut = strtolower($data['statut'] ?? 'present');

        if (!$userId || !$sessionId) {
            return $this->json(['message' => 'user_id et session_id sont obligatoires.'], Response::HTTP_BAD_REQUEST);
        }

        $user = $userRepository->find($userId);
        if (!$user) {
            return $this->json(['message' => 'Adhérent introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $session = $sessionRepository->find($sessionId);
        if (!$session) {
            return $this->json(['message' => 'Séance introuvable.'], Response::HTTP_NOT_FOUND);
        }

        // Vérifier si l'adhérent n'est pas déjà émargé sur cette séance
        $existing = $presenceRepository->findOneBy(['user' => $user, 'sessionEntrainement' => $session]);
        if ($existing) {
            return $this->json(['message' => 'Cet adhérent est déjà enregistré sur cette séance.'], Response::HTTP_BAD_REQUEST);
        }

        // Gestion du carnet si le statut est "present"
        $carnet = $carnetRepository->findOneBy(['user' => $user]);
        if ($statut === 'present') {
            if (!$carnet) {
                return $this->json(['message' => 'L\'adhérent ne possède aucun carnet de sessions.'], Response::HTTP_BAD_REQUEST);
            }
            if ($carnet->getNbSessionsRestant() <= 0) {
                return $this->json(['message' => 'Solde insuffisant : le carnet de l\'adhérent est épuisé (0 séance restante).'], Response::HTTP_BAD_REQUEST);
            }

            // Décompte de 1 séance
            $carnet->setNbSessionsRestant($carnet->getNbSessionsRestant() - 1);
        }

        $presence = new Presence();
        $presence->setUser($user);
        $presence->setSessionEntrainement($session);
        $presence->setStatut($statut);
        $presence->setDateEnregistrement(new \DateTimeImmutable());

        $em->persist($presence);
        $em->flush();

        return $this->json([
            'message' => 'Présence enregistrée avec succès.',
            'presence_id' => $presence->getId(),
            'statut' => $presence->getStatut(),
            'sessions_restantes' => $carnet ? $carnet->getNbSessionsRestant() : null
        ], Response::HTTP_CREATED);
    }

    /**
     * 3. Modifier le statut d'une présence (réajuste automatiquement le carnet)
     */
    #[Route('/{id}', name: 'api_admin_presences_update', methods: ['PUT', 'PATCH'])]
    #[OA\Put(
        path: '/api/admin/presences/{id}',
        summary: 'Modifier le statut d\'une présence (ajuste le carnet en conséquence)',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['statut'],
                properties: [
                    new OA\Property(property: 'statut', type: 'string', example: 'annule', enum: ['present', 'absent', 'annule'])
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Statut mis à jour et carnet réajusté'),
            new OA\Response(response: 400, description: 'Solde insuffisant ou statut invalide'),
            new OA\Response(response: 404, description: 'Présence introuvable')
        ]
    )]
    public function updatePresence(
        Presence $presence,
        Request $request,
        CarnetSessionRepository $carnetRepository,
        EntityManagerInterface $em
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $nouveauStatut = strtolower($data['statut'] ?? '');

        if (!in_array($nouveauStatut, ['present', 'absent', 'annule'], true)) {
            return $this->json(['message' => 'Statut invalide. Valeurs acceptées : present, absent, annule.'], Response::HTTP_BAD_REQUEST);
        }

        $ancienStatut = $presence->getStatut();

        if ($ancienStatut !== $nouveauStatut) {
            $carnet = $carnetRepository->findOneBy(['user' => $presence->getUser()]);

            // Si on passe de absent/annule -> present (on doit consommer 1 séance)
            if ($nouveauStatut === 'present' && $ancienStatut !== 'present') {
                if (!$carnet || $carnet->getNbSessionsRestant() <= 0) {
                    return $this->json(['message' => 'Impossible de passer en "présent" : solde de séances épuisé.'], Response::HTTP_BAD_REQUEST);
                }
                $carnet->setNbSessionsRestant($carnet->getNbSessionsRestant() - 1);
            }

            // Si on passe de present -> absent/annule (on re-crédite 1 séance)
            if ($ancienStatut === 'present' && $nouveauStatut !== 'present') {
                if ($carnet) {
                    $carnet->setNbSessionsRestant($carnet->getNbSessionsRestant() + 1);
                }
            }

            $presence->setStatut($nouveauStatut);
            $em->flush();
        }

        return $this->json([
            'message' => 'Présence mise à jour avec succès.',
            'statut' => $presence->getStatut(),
        ]);
    }

    /**
     * 4. Supprimer une présence (re-crédite le carnet si l'adhérent était présent)
     */
    #[Route('/{id}', name: 'api_admin_presences_delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/api/admin/presences/{id}',
        summary: 'Supprimer une présence (re-crédite le carnet si le statut était "present")',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Présence supprimée'),
            new OA\Response(response: 404, description: 'Présence introuvable')
        ]
    )]
    public function deletePresence(
        Presence $presence,
        CarnetSessionRepository $carnetRepository,
        EntityManagerInterface $em
    ): JsonResponse {
        // Si la présence était comptabilisée, on rend le crédit à l'adhérent
        if ($presence->getStatut() === 'present') {
            $carnet = $carnetRepository->findOneBy(['user' => $presence->getUser()]);
            if ($carnet) {
                $carnet->setNbSessionsRestant($carnet->getNbSessionsRestant() + 1);
            }
        }

        $em->remove($presence);
        $em->flush();

        return $this->json(['message' => 'Présence supprimée et carnet réajusté avec succès.']);
    }
}

<?php

namespace App\Controller;

use App\Entity\SessionEntrainement;
use App\Entity\Utilisateur;
use App\Repository\PresenceRepository;
use App\Repository\SessionEntrainementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Annotation\Security;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api')]
#[OA\Tag(name: 'Sessions')]
class SessionEntrainementController extends AbstractController
{
    /**
     * 1. ADHÉRENT : Voir son journal de sessions effectuées
     */
    #[Route('/adherent/my-sessions', name: 'api_adherent_sessions', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Get(
        path: '/api/adherent/my-sessions',
        summary: 'Consulter l\'historique de ses séances (justificatif carnet)',
        description: 'Permet à l\'adhérent connecté de consulter la liste des séances passées où il a été noté présent.',
        security: [['Bearer' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Historique des sessions récupéré',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'total_sessions_effectuees', type: 'integer', example: 5),
                        new OA\Property(
                            property: 'sessions',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'presence_id', type: 'integer', example: 12),
                                    new OA\Property(property: 'presence_statut', type: 'string', example: 'present'),
                                    new OA\Property(property: 'date_enregistrement', type: 'string', example: '2026-06-10 18:30:00'),
                                    new OA\Property(
                                        property: 'session',
                                        type: 'object',
                                        properties: [
                                            new OA\Property(property: 'id', type: 'integer', example: 3),
                                            new OA\Property(property: 'date', type: 'string', format: 'date', example: '2026-06-10'),
                                            new OA\Property(property: 'heure_debut', type: 'string', example: '18:00'),
                                            new OA\Property(property: 'heure_fin', type: 'string', example: '20:00')
                                        ]
                                    )
                                ]
                            )
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Non authentifié')
        ]
    )]
    public function getMySessions(PresenceRepository $presenceRepository): JsonResponse
    {
        /** @var Utilisateur $user */
        $user = $this->getUser();

        $presences = $presenceRepository->findBy(
            ['utilisateur' => $user],
            ['dateEnregistrement' => 'DESC']
        );

        $data = [];
        foreach ($presences as $presence) {
            $session = $presence->getSessionEntrainement();
            if ($session) {
                $data[] = [
                    'presence_id' => $presence->getId(),
                    'presence_statut' => $presence->getStatut(),
                    'date_enregistrement' => $presence->getDateEnregistrement()?->format('Y-m-d H:i:s'),
                    'session' => [
                        'id' => $session->getId(),
                        'date' => $session->getDateSession()?->format('Y-m-d'),
                        'heure_debut' => $session->getHeureDebut()?->format('H:i'),
                        'heure_fin' => $session->getHeureFin()?->format('H:i'),
                    ],
                ];
            }
        }

        return $this->json([
            'total_sessions_effectuees' => count($data),
            'sessions' => $data
        ]);
    }

    /**
     * 2. ADMIN : Lister toutes les séances d'entraînement
     */
    #[Route('/admin/sessions', name: 'api_admin_sessions_list', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    #[OA\Get(
        path: '/api/admin/sessions',
        summary: 'Lister toutes les séances créées (Admin)',
        description: 'Permet à un administrateur de voir toutes les séances d\'entraînement enregistrées.',
        security: [['Bearer' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des séances',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 1),
                            new OA\Property(property: 'date_session', type: 'string', format: 'date', example: '2026-06-15'),
                            new OA\Property(property: 'heure_debut', type: 'string', example: '18:00'),
                            new OA\Property(property: 'heure_fin', type: 'string', example: '20:00'),
                            new OA\Property(property: 'nb_presents', type: 'integer', example: 8)
                        ]
                    )
                )
            ),
            new OA\Response(response: 403, description: 'Accès refusé (Admin requis)')
        ]
    )]
    public function listAllSessions(SessionEntrainementRepository $sessionRepository): JsonResponse
    {
        $sessions = $sessionRepository->findBy([], ['dateSession' => 'DESC']);

        $data = array_map(function (SessionEntrainement $session) {
            return [
                'id' => $session->getId(),
                'date_session' => $session->getDateSession()?->format('Y-m-d'),
                'heure_debut' => $session->getHeureDebut()?->format('H:i'),
                'heure_fin' => $session->getHeureFin()?->format('H:i'),
                'nb_presents' => $session->getPresences()->count(),
            ];
        }, $sessions);

        return $this->json($data);
    }

    /**
     * 3. ADMIN : Détail d'une séance spécifique
     */
    #[Route('/admin/sessions/{id}', name: 'api_admin_sessions_show', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    #[OA\Get(
        path: '/api/admin/sessions/{id}',
        summary: 'Détail d\'une séance avec les adhérents émargés (Admin)',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Détails de la séance',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'date_session', type: 'string', format: 'date', example: '2026-06-15'),
                        new OA\Property(property: 'heure_debut', type: 'string', example: '18:00'),
                        new OA\Property(property: 'heure_fin', type: 'string', example: '20:00'),
                        new OA\Property(
                            property: 'presences',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'presence_id', type: 'integer', example: 5),
                                    new OA\Property(property: 'statut', type: 'string', example: 'present'),
                                    new OA\Property(
                                        property: 'adherent',
                                        type: 'object',
                                        properties: [
                                            new OA\Property(property: 'id', type: 'integer', example: 2),
                                            new OA\Property(property: 'nom', type: 'string', example: 'Dupont'),
                                            new OA\Property(property: 'prenom', type: 'string', example: 'Jean'),
                                            new OA\Property(property: 'email', type: 'string', example: 'jean.dupont@test.fr')
                                        ]
                                    )
                                ]
                            )
                        )
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Séance non trouvée')
        ]
    )]
    public function showSession(SessionEntrainement $session): JsonResponse
    {
        $presences = [];
        foreach ($session->getPresences() as $presence) {
            $adherent = $presence->getUtilisateur();
            $presences[] = [
                'presence_id' => $presence->getId(),
                'statut' => $presence->getStatut(),
                'adherent' => $adherent ? [
                    'id' => $adherent->getId(),
                    'nom' => $adherent->getNom(),
                    'prenom' => $adherent->getPrenom(),
                    'email' => $adherent->getEmail(),
                ] : null,
            ];
        }

        return $this->json([
            'id' => $session->getId(),
            'date_session' => $session->getDateSession()?->format('Y-m-d'),
            'heure_debut' => $session->getHeureDebut()?->format('H:i'),
            'heure_fin' => $session->getHeureFin()?->format('H:i'),
            'presences' => $presences,
        ]);
    }

    /**
     * 4. ADMIN : Créer une séance d'entraînement
     */
    #[Route('/admin/sessions', name: 'api_admin_sessions_create', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    #[OA\Post(
        path: '/api/admin/sessions',
        summary: 'Créer une nouvelle séance (Admin)',
        security: [['Bearer' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['date_session', 'heure_debut', 'heure_fin'],
                properties: [
                    new OA\Property(property: 'date_session', type: 'string', format: 'date', example: '2026-06-15'),
                    new OA\Property(property: 'heure_debut', type: 'string', example: '18:00'),
                    new OA\Property(property: 'heure_fin', type: 'string', example: '20:00')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Séance créée avec succès'),
            new OA\Response(response: 400, description: 'Données manquantes ou format de date invalide')
        ]
    )]
    public function createSession(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (empty($data['date_session']) || empty($data['heure_debut']) || empty($data['heure_fin'])) {
            return $this->json(['message' => 'date_session, heure_debut et heure_fin sont requis.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $session = new SessionEntrainement();
            $session->setDateSession(new \DateTime($data['date_session']));
            $session->setHeureDebut(new \DateTime($data['heure_debut']));
            $session->setHeureFin(new \DateTime($data['heure_fin']));

            $em->persist($session);
            $em->flush();

            return $this->json([
                'message' => 'Séance d\'entraînement créée avec succès.',
                'id' => $session->getId(),
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            return $this->json(['message' => 'Format de date ou d\'heure invalide.'], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * 5. ADMIN : Mettre à jour une séance
     */
    #[Route('/admin/sessions/{id}', name: 'api_admin_sessions_update', methods: ['PUT', 'PATCH'])]
    #[IsGranted('ROLE_ADMIN')]
    #[OA\Put(
        path: '/api/admin/sessions/{id}',
        summary: 'Modifier une séance existante (Admin)',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'date_session', type: 'string', format: 'date', example: '2026-06-16'),
                    new OA\Property(property: 'heure_debut', type: 'string', example: '19:00'),
                    new OA\Property(property: 'heure_fin', type: 'string', example: '21:00')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Séance mise à jour'),
            new OA\Response(response: 400, description: 'Format invalide'),
            new OA\Response(response: 404, description: 'Séance non trouvée')
        ]
    )]
    public function updateSession(
        SessionEntrainement $session,
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        try {
            if (!empty($data['date_session'])) {
                $session->setDateSession(new \DateTime($data['date_session']));
            }
            if (!empty($data['heure_debut'])) {
                $session->setHeureDebut(new \DateTime($data['heure_debut']));
            }
            if (!empty($data['heure_fin'])) {
                $session->setHeureFin(new \DateTime($data['heure_fin']));
            }

            $em->flush();

            return $this->json(['message' => 'Séance mise à jour avec succès.']);
        } catch (\Exception $e) {
            return $this->json(['message' => 'Erreur lors de la mise à jour : format invalide.'], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * 6. ADMIN : Supprimer une séance
     */
    #[Route('/admin/sessions/{id}', name: 'api_admin_sessions_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    #[OA\Delete(
        path: '/api/admin/sessions/{id}',
        summary: 'Supprimer une séance (Admin)',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Séance supprimée avec succès'),
            new OA\Response(response: 404, description: 'Séance non trouvée')
        ]
    )]
    public function deleteSession(SessionEntrainement $session, EntityManagerInterface $em): JsonResponse
    {
        $em->remove($session);
        $em->flush();

        return $this->json(['message' => 'Séance supprimée avec succès.']);
    }
}
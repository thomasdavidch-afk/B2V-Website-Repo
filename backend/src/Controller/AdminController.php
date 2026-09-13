<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\CarnetSession;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use OpenApi\Attributes as OA;
use App\Service\AuditLoggerService;

#[Route('/api/admin', name: 'api_admin_')]
#[IsGranted('ROLE_ADMIN')]
#[OA\Tag(name: 'Administration')]
class AdminController extends AbstractController
{
    private AuditLoggerService $auditLogger;

    public function __construct(AuditLoggerService $auditLogger)
    {
        $this->auditLogger = $auditLogger;
    }
    
    /**
     * Liste tous les utilisateurs / adhérents avec leur statut et informations
     */
    #[Route('/users', name: 'users_list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/admin/users',
        summary: 'Liste de tous les utilisateurs',
        description: 'Récupère la liste complète des utilisateurs avec leurs informations. Réservé aux administrateurs.',
        security: [['Bearer' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des utilisateurs récupérée avec succès',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 1),
                            new OA\Property(property: 'email', type: 'string', example: 'jean.dupont@example.com'),
                            new OA\Property(property: 'nom', type: 'string', example: 'Dupont'),
                            new OA\Property(property: 'prenom', type: 'string', example: 'Jean'),
                            new OA\Property(property: 'telephone', type: 'string', nullable: true, example: '0601020304'),
                            new OA\Property(property: 'rue', type: 'string', nullable: true, example: '12 rue des Volleyeuses'),
                            new OA\Property(property: 'codePostal', type: 'string', nullable: true, example: '33000'),
                            new OA\Property(property: 'ville', type: 'string', nullable: true, example: 'Bordeaux'),
                            new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string'), example: ['ROLE_USER']),
                            new OA\Property(property: 'actif', type: 'boolean', example: true),
                            new OA\Property(property: 'dateInscription', type: 'string', format: 'date-time', nullable: true, example: '2026-01-15T10:00:00+01:00'),
                            new OA\Property(
                                property: 'carnetSession',
                                type: 'object',
                                nullable: true,
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                    new OA\Property(property: 'nbSessionsTotal', type: 'integer', example: 10),
                                    new OA\Property(property: 'nbSessionsRestant', type: 'integer', example: 7)
                                ]
                            ),
                            new OA\Property(
                                property: 'certificatMedical',
                                type: 'object',
                                nullable: true,
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                    new OA\Property(property: 'statut', type: 'string', example: 'VALIDE'),
                                    new OA\Property(property: 'dateValidation', type: 'string', format: 'date-time', nullable: true)
                                ]
                            )
                        ]
                    )
                )
            ),
            new OA\Response(response: 401, description: 'Non authentifié (Token JWT manquant ou expiré)'),
            new OA\Response(response: 403, description: 'Accès refusé (Droits administrateur requis)')
        ]
    )]
    public function listUsers(UserRepository $userRepository): JsonResponse
    {
        $users = $userRepository->findAll();

        $data = array_map(function (User $user) {
            $carnet = $user->getCarnetSession();
            $certificat = $user->getCertificatMedical();

            return [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'nom' => $user->getNom(),
                'prenom' => $user->getPrenom(),
                'telephone' => $user->getTelephone(),
                'rue' => $user->getRue(),
                'codePostal' => $user->getCodePostal(),
                'ville' => $user->getVille(),
                'roles' => $user->getRoles(),
                'actif' => $user->isActif(),
                'dateInscription' => $user->getDateInscription()?->format(\DateTimeInterface::ATOM),
                'carnetSession' => $carnet ? [
                    'id' => $carnet->getId(),
                    'nbSessionsTotal' => method_exists($carnet, 'getNbSessionsTotal') ? $carnet->getNbSessionsTotal() : null,
                    'nbSessionsRestant' => method_exists($carnet, 'getNbSessionsRestant') ? $carnet->getNbSessionsRestant() : null,
                ] : null,
                'certificatMedical' => $certificat ? [
                    'id' => $certificat->getId(),
                    'statut' => method_exists($certificat, 'getStatut') ? $certificat->getStatut() : null,
                ] : null,
            ];
        }, $users);

        return $this->json($data, Response::HTTP_OK);
    }

    /**
     * Créer un nouvel adhérent
     */
    #[Route('/users', name: 'users_create', methods: ['POST'])]
    #[OA\Post(
        path: '/api/admin/users',
        summary: 'Créer un adhérent',
        security: [['Bearer' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'nom', type: 'string', example: 'David'),
                    new OA\Property(property: 'prenom', type: 'string', example: 'Thomas'),
                    new OA\Property(property: 'email', type: 'string', example: 'thomas.david@example.com'),
                    new OA\Property(property: 'telephone', type: 'string', nullable: true, example: '0633341801'),
                    new OA\Property(property: 'password', type: 'string', example: 'MotDePasse123!'),
                    new OA\Property(property: 'solde', type: 'integer', example: 10)
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Adhérent créé avec succès'),
            new OA\Response(response: 400, description: 'Données manquantes ou email déjà existant')
        ]
    )]
    public function createUser(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
        UserRepository $userRepository
    ): JsonResponse {
        $data = json_decode($request->getContent(), true) ?? [];

        // Supporte aussi bien les clés françaises qu'anglaises
        $nom = $data['nom'] ?? $data['lastname'] ?? null;
        $prenom = $data['prenom'] ?? $data['firstname'] ?? null;
        $email = $data['email'] ?? null;
        $password = $data['password'] ?? null;
        $telephone = $data['telephone'] ?? $data['phone'] ?? null;
        $solde = (int)($data['solde'] ?? $data['balance'] ?? 0);

        if (empty($nom) || empty($prenom) || empty($email) || empty($password)) {
            return $this->json(['error' => 'Champs obligatoires manquants (nom, prénom, email, mot de passe).'], Response::HTTP_BAD_REQUEST);
        }

        if ($userRepository->findOneBy(['email' => $email])) {
            return $this->json(['error' => 'Un utilisateur avec cette adresse email existe déjà.'], Response::HTTP_BAD_REQUEST);
        }

        $user = new User();
        $user->setNom($nom);
        $user->setPrenom($prenom);
        $user->setEmail($email);
        $user->setTelephone($telephone);
        $user->setRoles(['ROLE_USER']);
        $user->setActif(true);
        $user->setDateInscription(new \DateTimeImmutable());
        $user->setPassword($hasher->hashPassword($user, $password));

        // Initialisation du carnet de sessions si la classe CarnetSession existe
        if (class_exists(CarnetSession::class)) {
            $carnet = new CarnetSession();
            if (method_exists($carnet, 'setNbSessionsTotal')) {
                $carnet->setNbSessionsTotal($solde);
            }
            if (method_exists($carnet, 'setNbSessionsRestant')) {
                $carnet->setNbSessionsRestant($solde);
            }
            $user->setCarnetSession($carnet);
            $em->persist($carnet);
        }

        $em->persist($user);
        $em->flush();

        $this->auditLogger->logEvent('ADMIN_USER_CREATED', $this->getUser(), [
            'target_user_id' => $user->getId(),
            'target_email'   => $user->getEmail(),
            'initial_balance' => $solde
        ]);

        return $this->json([
            'message' => 'Adhérent créé avec succès',
            'id' => $user->getId()
        ], Response::HTTP_CREATED);
    }

    /**
     * Modifier le solde de sessions d'un adhérent
     */
    #[Route('/users/{id}/balance', name: 'users_update_balance', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/api/admin/users/{id}/balance',
        summary: 'Ajuster le solde de sessions d\'un adhérent',
        security: [['Bearer' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Solde mis à jour'),
            new OA\Response(response: 404, description: 'Utilisateur non trouvé')
        ]
    )]
    public function updateBalance(
        int $id,
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $em
    ): JsonResponse {
        $user = $userRepository->find($id);
        if (!$user) {
            return $this->json(['error' => 'Adhérent non trouvé'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $newBalance = (int)($data['balance'] ?? $data['solde'] ?? 0);

        $carnet = $user->getCarnetSession();
        if (!$carnet && class_exists(CarnetSession::class)) {
            $carnet = new CarnetSession();
            $user->setCarnetSession($carnet);
            $em->persist($carnet);
        }

        if ($carnet) {
            if (method_exists($carnet, 'setNbSessionsRestant')) {
                $carnet->setNbSessionsRestant($newBalance);
            }
            if (method_exists($carnet, 'getNbSessionsTotal') && method_exists($carnet, 'setNbSessionsTotal')) {
                if ($carnet->getNbSessionsTotal() < $newBalance) {
                    $carnet->setNbSessionsTotal($newBalance);
                }
            }
            $em->flush();
        }

        $this->auditLogger->logEvent('ADMIN_USER_BALANCE_UPDATED', $this->getUser(), [
            'target_user_id' => $user->getId(),
            'target_email'   => $user->getEmail(),
            'new_balance' => $newBalance
        ]);

        return $this->json(['message' => 'Solde mis à jour avec succès', 'balance' => $newBalance], Response::HTTP_OK);
    }

    /**
     * Supprimer un adhérent
     */
    #[Route('/users/{id}', name: 'users_delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/api/admin/users/{id}',
        summary: 'Supprimer un utilisateur',
        security: [['Bearer' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Adhérent supprimé'),
            new OA\Response(response: 404, description: 'Utilisateur non trouvé')
        ]
    )]
    public function deleteUser(
        int $id,
        UserRepository $userRepository,
        EntityManagerInterface $em
    ): JsonResponse {
        $user = $userRepository->find($id);
        if (!$user) {
            return $this->json(['error' => 'Utilisateur introuvable'], Response::HTTP_NOT_FOUND);
        }

        $targetId = $user->getId();
        $targetEmail = $user->getEmail();        

        $em->remove($user);
        $em->flush();

        $this->auditLogger->logEvent('ADMIN_USER_DELETED', $this->getUser(), [
            'target_user_id' => $targetId,
            'target_email'   => $targetEmail
        ]);

        return $this->json(['message' => 'Adhérent supprimé avec succès'], Response::HTTP_OK);
    }

    /**
     * Mettre à jour les informations d'un adhérent
     */
    #[Route('/users/{id}', name: 'users_update', methods: ['PUT', 'PATCH'])]
    #[OA\Put(
        path: '/api/admin/users/{id}',
        summary: 'Mettre à jour un utilisateur',
        security: [['Bearer' => []]]
    )]
    #[OA\Patch(
        path: '/api/admin/users/{id}',
        summary: 'Mettre à jour partiellement un utilisateur',
        security: [['Bearer' => []]]
    )]
    public function updateUser(
        User $user,
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['message' => 'Données invalides'], Response::HTTP_BAD_REQUEST);
        }

        // Nom & Prénom
        if (isset($data['nom'])) $user->setNom(trim((string)$data['nom']));
        if (isset($data['prenom'])) $user->setPrenom(trim((string)$data['prenom']));
        
        // Coordonnées
        if (isset($data['email'])) $user->setEmail(trim((string)$data['email']));
        if (isset($data['telephone'])) $user->setTelephone(trim((string)$data['telephone']));
        if (isset($data['rue'])) $user->setRue(trim((string)$data['rue']));
        if (isset($data['ville'])) $user->setVille(trim((string)$data['ville']));

        // Code Postal (supporte codePostal et code_postal)
        $codePostal = $data['codePostal'] ?? $data['code_postal'] ?? null;
        if ($codePostal !== null) {
            $user->setCodePostal(trim((string)$codePostal));
        }

        // Statut Actif / Inactif (supporte is_active, isActive, actif)
        if (array_key_exists('is_active', $data)) {
            $user->setActif(filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN));
        } elseif (array_key_exists('isActive', $data)) {
            $user->setActif(filter_var($data['isActive'], FILTER_VALIDATE_BOOLEAN));
        } elseif (array_key_exists('actif', $data)) {
            $user->setActif(filter_var($data['actif'], FILTER_VALIDATE_BOOLEAN));
        }

        $em->flush();

        $this->auditLogger->logEvent('ADMIN_USER_UPDATED', $this->getUser(), [
            'target_user_id' => $user->getId(),
            'target_email'   => $user->getEmail(),
            'updated_fields' => array_keys($data)
        ]);

        return $this->json([
            'message' => 'Adhérent mis à jour avec succès',
            'user' => [
                'id' => $user->getId(),
                'nom' => $user->getNom(),
                'prenom' => $user->getPrenom(),
                'email' => $user->getEmail(),
                'actif' => $user->isActif()
            ]
        ], Response::HTTP_OK);
    }
}

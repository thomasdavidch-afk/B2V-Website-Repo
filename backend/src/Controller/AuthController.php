<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\AuditLoggerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;

#[Route('/api', name: 'api_')]
#[OA\Tag(name: 'Authentification')]
class AuthController extends AbstractController
{
    /**
     * Inscription d'un nouvel utilisateur
     */
    #[Route('/register', name: 'register', methods: ['POST'])]
    #[OA\Post(
        summary: "Inscription d'un nouvel utilisateur",
        description: "Permet de créer un compte utilisateur avec ses informations personnelles.",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password', 'nom', 'prenom'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'jean.dupont@example.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'MonMotDePasse123!'),
                    new OA\Property(property: 'nom', type: 'string', example: 'Dupont'),
                    new OA\Property(property: 'prenom', type: 'string', example: 'Jean'),
                    new OA\Property(property: 'telephone', type: 'string', nullable: true, example: '0601020304'),
                    new OA\Property(property: 'rue', type: 'string', nullable: true, example: '12 rue des Lilas'),
                    new OA\Property(property: 'codePostal', type: 'string', nullable: true, example: '75001'),
                    new OA\Property(property: 'ville', type: 'string', nullable: true, example: 'Paris')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Utilisateur créé avec succès',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Utilisateur créé avec succès'),
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'email', type: 'string', example: 'jean.dupont@example.com'),
                        new OA\Property(property: 'nom', type: 'string', example: 'Dupont'),
                        new OA\Property(property: 'prenom', type: 'string', example: 'Jean')
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Données invalides ou email déjà utilisé'
            )
        ]
    )]
    #[Security(name: null)]
    public function register(
        Request $request,
        UserPasswordHasherInterface $hasher,
        EntityManagerInterface $em,
        AuditLoggerService $auditLogger
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        // Validation des champs obligatoires
        if (
            !$data ||
            empty($data['email']) ||
            empty($data['password']) ||
            empty($data['nom']) ||
            empty($data['prenom'])
        ) {
            return $this->json([
                'message' => 'Les champs email, mot de passe, nom et prénom sont obligatoires.'
            ], Response::HTTP_BAD_REQUEST);
        }

        $email = trim((string) $data['email']);
        $password = (string) $data['password'];
        $nom = trim((string) $data['nom']);
        $prenom = trim((string) $data['prenom']);

        // Validation format email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['message' => 'Format d\'email invalide.'], Response::HTTP_BAD_REQUEST);
        }

        // Validation longueur du mot de passe
        if (strlen($password) < 6) {
            return $this->json(['message' => 'Le mot de passe doit contenir au moins 6 caractères.'], Response::HTTP_BAD_REQUEST);
        }

        // Vérification unicité email
        $existingUser = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existingUser) {
            return $this->json(['message' => 'Un compte existe déjà avec cet email.'], Response::HTTP_BAD_REQUEST);
        }

        // Création de l'utilisateur (MySQL)
        $user = new User();
        $user->setEmail($email);
        $user->setNom($nom);
        $user->setPrenom($prenom);
        $user->setPassword($hasher->hashPassword($user, $password));
        $user->setRoles(['ROLE_USER']);
        $user->setActif(true);
        $user->setDateInscription(new \DateTimeImmutable());

        // Champs optionnels
        if (!empty($data['telephone'])) {
            $user->setTelephone(trim((string) $data['telephone']));
        }
        if (!empty($data['rue'])) {
            $user->setRue(trim((string) $data['rue']));
        }
        if (!empty($data['codePostal'])) {
            $user->setCodePostal(trim((string) $data['codePostal']));
        }
        if (!empty($data['ville'])) {
            $user->setVille(trim((string) $data['ville']));
        }

        $em->persist($user);
        $em->flush();

        // Trace d'audit (MongoDB NoSQL)
        $auditLogger->log(
            eventType: 'USER_REGISTERED',
            context: [
                'registered_user_id' => $user->getId(),
                'email' => $user->getUserIdentifier(),
                'nom' => $user->getNom(),
                'prenom' => $user->getPrenom(),
                'ip' => $request->getClientIp()
            ],
            user: $user
        );

        return $this->json([
            'message' => 'Utilisateur créé avec succès',
            'id' => $user->getId(),
            'email' => $user->getUserIdentifier(),
            'nom' => $user->getNom(),
            'prenom' => $user->getPrenom()
        ], Response::HTTP_CREATED);
    }

    /**
     * Connexion utilisateur (Récupération du token JWT)
     */
    #[Route('/login_check', name: 'login_check', methods: ['POST'])]
    #[OA\Post(
        summary: "Connexion utilisateur",
        description: "Permet de s'authentifier et de recevoir un token JWT Bearer.",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'jean.dupont@example.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'MonMotDePasse123!')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Authentification réussie, retourne le jeton JWT',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'token', type: 'string', example: 'eyJhbGciOiJSUzI1NiIs...')
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Identifiants invalides'
            )
        ]
    )]
    #[Security(name: null)]
    public function login(): void
    {
        throw new \LogicException('Cette route est interceptée par LexikJWTAuthenticationBundle.');
    }

    /**
     * Récupérer les informations complètes de l'utilisateur connecté
     */
    #[Route('/me', name: 'me', methods: ['GET'])]
    #[OA\Get(
        summary: "Informations du profil connecté",
        description: "Retourne les informations complètes de l'utilisateur connecté via le Bearer Token.",
        responses: [
            new OA\Response(
                response: 200,
                description: 'Profil récupéré',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'email', type: 'string', example: 'jean.dupont@example.com'),
                        new OA\Property(property: 'nom', type: 'string', example: 'Dupont'),
                        new OA\Property(property: 'prenom', type: 'string', example: 'Jean'),
                        new OA\Property(property: 'telephone', type: 'string', nullable: true, example: '0601020304'),
                        new OA\Property(property: 'rue', type: 'string', nullable: true, example: '12 rue des Lilas'),
                        new OA\Property(property: 'codePostal', type: 'string', nullable: true, example: '75001'),
                        new OA\Property(property: 'ville', type: 'string', nullable: true, example: 'Paris'),
                        new OA\Property(property: 'dateInscription', type: 'string', format: 'date-time', example: '2026-03-30T10:00:00+00:00'),
                        new OA\Property(property: 'actif', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'roles',
                            type: 'array',
                            items: new OA\Items(type: 'string', example: 'ROLE_USER')
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Non autorisé'
            )
        ]
    )]
    #[Security(name: 'Bearer')]
    public function me(#[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json(['message' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json([
            'id' => $user->getId(),
            'email' => $user->getUserIdentifier(),
            'nom' => $user->getNom(),
            'prenom' => $user->getPrenom(),
            'telephone' => $user->getTelephone(),
            'rue' => $user->getRue(),
            'codePostal' => $user->getCodePostal(),
            'ville' => $user->getVille(),
            'dateInscription' => $user->getDateInscription()?->format(\DateTimeInterface::ATOM),
            'actif' => $user->isActif(),
            'roles' => $user->getRoles(),
        ]);
    }

    /**
     * Mettre à jour les informations du profil connecté
     */
    #[Route('/me', name: 'me_update', methods: ['PUT', 'PATCH'])]
    #[OA\Put(
        summary: "Mise à jour du profil connecté",
        description: "Permet à l'adhérent connecté de mettre à jour ses coordonnées personnelles.",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'nom', type: 'string', example: 'Dupont'),
                    new OA\Property(property: 'prenom', type: 'string', example: 'Jean'),
                    new OA\Property(property: 'telephone', type: 'string', example: '0601020304'),
                    new OA\Property(property: 'rue', type: 'string', example: '12 rue des Lilas'),
                    new OA\Property(property: 'codePostal', type: 'string', example: '75001'),
                    new OA\Property(property: 'ville', type: 'string', example: 'Paris'),
                    new OA\Property(property: 'currentPassword', type: 'string', format: 'password', example: 'AncienMotDePasse'),
                    new OA\Property(property: 'newPassword', type: 'string', format: 'password', example: 'NouveauMotDePasse123!')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Profil mis à jour avec succès'),
            new OA\Response(response: 400, description: 'Données invalides ou mot de passe actuel erroné'),
            new OA\Response(response: 401, description: 'Non autorisé')
        ]
    )]
    #[Security(name: 'Bearer')]
    public function updateMe(
        #[CurrentUser] ?User $user,
        Request $request,
        UserPasswordHasherInterface $hasher,
        EntityManagerInterface $em,
        AuditLoggerService $auditLogger
    ): JsonResponse {
        if (!$user) {
            return $this->json(['message' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);
        if (!$data) {
            return $this->json(['message' => 'Données invalides'], Response::HTTP_BAD_REQUEST);
        }

        $updatedFields = [];

        if (isset($data['nom'])) {
            $user->setNom(trim((string)$data['nom']));
            $updatedFields[] = 'nom';
        }
        if (isset($data['prenom'])) {
            $user->setPrenom(trim((string)$data['prenom']));
            $updatedFields[] = 'prenom';
        }
        if (isset($data['telephone'])) {
            $user->setTelephone(trim((string)$data['telephone']));
            $updatedFields[] = 'telephone';
        }
        if (isset($data['rue'])) {
            $user->setRue(trim((string)$data['rue']));
            $updatedFields[] = 'rue';
        }
        if (isset($data['codePostal'])) {
            $user->setCodePostal(trim((string)$data['codePostal']));
            $updatedFields[] = 'codePostal';
        }
        if (isset($data['ville'])) {
            $user->setVille(trim((string)$data['ville']));
            $updatedFields[] = 'ville';
        }

        // Si l'utilisateur souhaite changer son mot de passe
        $passwordChanged = false;
        if (!empty($data['newPassword'])) {
            if (empty($data['currentPassword'])) {
                return $this->json(['message' => 'Veuillez renseigner votre mot de passe actuel.'], Response::HTTP_BAD_REQUEST);
            }
            if (!$hasher->isPasswordValid($user, $data['currentPassword'])) {
                return $this->json(['message' => 'Le mot de passe actuel est incorrect.'], Response::HTTP_BAD_REQUEST);
            }
            if (strlen((string)$data['newPassword']) < 6) {
                return $this->json(['message' => 'Le nouveau mot de passe doit contenir au moins 6 caractères.'], Response::HTTP_BAD_REQUEST);
            }
            $user->setPassword($hasher->hashPassword($user, (string)$data['newPassword']));
            $passwordChanged = true;
            $updatedFields[] = 'password';
        }

        $em->flush();

        // Trace d'audit (MongoDB NoSQL)
        $auditLogger->log(
            eventType: 'USER_PROFILE_UPDATED',
            context: [
                'updated_fields' => $updatedFields,
                'password_changed' => $passwordChanged,
                'ip' => $request->getClientIp()
            ],
            user: $user
        );

        return $this->json([
            'message' => 'Profil mis à jour avec succès',
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getUserIdentifier(),
                'nom' => $user->getNom(),
                'prenom' => $user->getPrenom(),
                'telephone' => $user->getTelephone(),
                'rue' => $user->getRue(),
                'codePostal' => $user->getCodePostal(),
                'ville' => $user->getVille()
            ]
        ]);
    }
}
<?php

namespace App\Controller;

use App\Entity\User;
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
     * Inscription d'un utilisateur
     */
    #[Route('/register', name: 'register', methods: ['POST'])]
    #[OA\Post(
        summary: "Inscription d'un nouvel utilisateur",
        description: "Permet de créer un compte utilisateur avec un email et un mot de passe.",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'user@example.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'MonMotDePasse123!')
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
                        new OA\Property(property: 'email', type: 'string', example: 'user@example.com')
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Données invalides ou email déjà utilisé'
            )
        ]
    )]
    public function register(
        Request $request,
        UserPasswordHasherInterface $hasher,
        EntityManagerInterface $em
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!$data || !isset($data['email']) || !isset($data['password'])) {
            return $this->json(['message' => 'Email et mot de passe obligatoires.'], Response::HTTP_BAD_REQUEST);
        }

        $email = trim((string) $data['email']);
        $password = (string) $data['password'];

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['message' => 'Format d\'email invalide.'], Response::HTTP_BAD_REQUEST);
        }

        if (strlen($password) < 6) {
            return $this->json(['message' => 'Le mot de passe doit contenir au moins 6 caractères.'], Response::HTTP_BAD_REQUEST);
        }

        $existingUser = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existingUser) {
            return $this->json(['message' => 'Un compte existe déjà avec cet email.'], Response::HTTP_BAD_REQUEST);
        }

        $user = new User();
        $user->setEmail($email);
        $user->setRoles(['ROLE_USER']);
        $user->setPassword($hasher->hashPassword($user, $password));

        $em->persist($user);
        $em->flush();

        return $this->json([
            'message' => 'Utilisateur créé avec succès',
            'email' => $user->getUserIdentifier()
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
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'user@example.com'),
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
     * Récupérer les informations de l'utilisateur connecté
     */
    #[Route('/me', name: 'me', methods: ['GET'])]
    #[OA\Get(
        summary: "Informations du profil",
        description: "Retourne les informations du profil connecté via le Bearer Token.",
        responses: [
            new OA\Response(
                response: 200,
                description: 'Profil récupéré',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'email', type: 'string', example: 'user@example.com'),
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
            'roles' => $user->getRoles(),
        ]);
    }
}
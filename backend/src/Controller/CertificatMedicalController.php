<?php

namespace App\Controller;

use App\Entity\CertificatMedical;
use App\Entity\User;
use App\Repository\CertificatMedicalRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/api')]
#[OA\Tag(name: 'Certificats Médicaux')]
class CertificatMedicalController extends AbstractController
{
    // ==========================================
    // ESPACE ADHÉRENT (ROLE_USER)
    // ==========================================

    /**
     * Adhérent : Consulter l'état de son certificat
     */
    #[Route('/adherent/certificat', name: 'api_adherent_certificat_get', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Get(
        path: '/api/adherent/certificat',
        summary: 'Consulter l\'état de son certificat médical',
        security: [['Bearer' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Informations du certificat médical',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'statut', type: 'string', example: 'en_attente', enum: ['en_attente', 'valide', 'refuse', 'expire']),
                        new OA\Property(property: 'date_upload', type: 'string', example: '2026-09-03 10:15:00'),
                        new OA\Property(property: 'date_expiration', type: 'string', nullable: true, example: '2027-09-03'),
                        new OA\Property(property: 'fichier_nom', type: 'string', example: 'certificat_dupont_65f12.pdf')
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Aucun certificat médical enregistré')
        ]
    )]
    public function getMyCertificat(#[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json(['message' => 'Non authentifié.'], Response::HTTP_UNAUTHORIZED);
        }

        $certificat = $user->getCertificatMedical();
        if (!$certificat) {
            return $this->json(['message' => 'Aucun certificat médical déposé.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'id' => $certificat->getId(),
            'statut' => $certificat->getStatut(),
            'date_upload' => $certificat->getDateUpload()?->format('Y-m-d H:i:s'),
            'date_expiration' => $certificat->getDateExpiration()?->format('Y-m-d'),
            'fichier_nom' => $certificat->getFichier(),
        ]);
    }

    /**
     * Adhérent : Déposer / Remplacer son certificat médical
     */
    #[Route('/adherent/certificat', name: 'api_adherent_certificat_upload', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Post(
        path: '/api/adherent/certificat',
        summary: 'Déposer ou remplacer son certificat médical (PDF, PNG, JPG - Max 5Mo)',
        security: [['Bearer' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['file'],
                    properties: [
                        new OA\Property(
                            property: 'file',
                            description: 'Fichier du certificat médical (PDF, JPG, PNG, max 5Mo)',
                            type: 'string',
                            format: 'binary'
                        )
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Certificat téléversé avec succès'),
            new OA\Response(response: 400, description: 'Fichier manquant ou format invalide')
        ]
    )]
    public function uploadCertificat(
        Request $request,
        #[CurrentUser] ?User $user,
        SluggerInterface $slugger,
        EntityManagerInterface $em
    ): JsonResponse {
        if (!$user) {
            return $this->json(['message' => 'Non authentifié.'], Response::HTTP_UNAUTHORIZED);
        }

        /** @var UploadedFile|null $file */
        $file = $request->files->get('file');

        if (!$file) {
            return $this->json(['message' => 'Veuillez fournir un fichier via le champ "file".'], Response::HTTP_BAD_REQUEST);
        }

        // Vérification de l'extension et du type MIME
        $allowedMimeTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($file->getMimeType(), $allowedMimeTypes, true)) {
            return $this->json(['message' => 'Format invalide. Formats acceptés : PDF, JPEG, PNG, WEBP.'], Response::HTTP_BAD_REQUEST);
        }

        // Vérification taille max (5 Mo)
        if ($file->getSize() > 5 * 1024 * 1024) {
            return $this->json(['message' => 'Le fichier dépasse la taille maximale autorisée (5 Mo).'], Response::HTTP_BAD_REQUEST);
        }

        $uploadsDirectory = $this->getParameter('certificats_directory');

        // Générer un nom de fichier unique et sécurisé
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $slugger->slug($originalFilename);
        $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

        try {
            $file->move($uploadsDirectory, $newFilename);
        } catch (FileException $e) {
            return $this->json(['message' => 'Erreur lors de l\'enregistrement du fichier.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        // Récupérer le certificat existant ou en créer un nouveau
        $certificat = $user->getCertificatMedical();

        if ($certificat) {
            // Supprimer l'ancien fichier sur le disque s'il existe
            $oldFilePath = $uploadsDirectory . '/' . $certificat->getFichier();
            if (file_exists($oldFilePath) && is_file($oldFilePath)) {
                unlink($oldFilePath);
            }
        } else {
            $certificat = new CertificatMedical();
            $certificat->setUser($user);
            $em->persist($certificat);
        }

        $certificat->setFichier($newFilename);
        $certificat->setDateUpload(new \DateTimeImmutable());
        $certificat->setStatut('en_attente');
        // La date d'expiration sera validée ou ajustée par l'administrateur
        $certificat->setDateExpiration(null);

        $em->flush();

        return $this->json([
            'message' => 'Certificat médical déposé avec succès. En attente de validation par l\'administration.',
            'fichier' => $newFilename,
            'statut' => $certificat->getStatut()
        ], Response::HTTP_CREATED);
    }

    /**
     * Adhérent : Télécharger / Visualiser son propre certificat
     */
    #[Route('/adherent/certificat/download', name: 'api_adherent_certificat_download', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Get(
        path: '/api/adherent/certificat/download',
        summary: 'Télécharger son certificat médical',
        security: [['Bearer' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Fichier binaire du certificat'),
            new OA\Response(response: 404, description: 'Certificat ou fichier non trouvé')
        ]
    )]
    public function downloadMyCertificat(#[CurrentUser] ?User $user): Response
    {
        $certificat = $user?->getCertificatMedical();
        if (!$certificat || !$certificat->getFichier()) {
            return $this->json(['message' => 'Aucun certificat trouvé.'], Response::HTTP_NOT_FOUND);
        }

        $filePath = $this->getParameter('certificats_directory') . '/' . $certificat->getFichier();
        if (!file_exists($filePath)) {
            return $this->json(['message' => 'Fichier introuvable sur le serveur.'], Response::HTTP_NOT_FOUND);
        }

        $response = new BinaryFileResponse($filePath);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $certificat->getFichier());

        return $response;
    }

    // ==========================================
    // ESPACE ADMIN (ROLE_ADMIN)
    // ==========================================

    /**
     * Admin : Lister tous les certificats médicaux
     */
    #[Route('/admin/certificats', name: 'api_admin_certificats_list', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    #[OA\Get(
        path: '/api/admin/certificats',
        summary: 'Lister tous les certificats médicaux (Admin)',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(
                name: 'statut',
                in: 'query',
                required: false,
                description: 'Filtrer par statut (en_attente, valide, refuse, expire)',
                schema: new OA\Schema(type: 'string', enum: ['en_attente', 'valide', 'refuse', 'expire'])
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des certificats récupérée',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 1),
                            new OA\Property(property: 'statut', type: 'string', example: 'en_attente'),
                            new OA\Property(property: 'date_upload', type: 'string', example: '2026-09-03 10:15:00'),
                            new OA\Property(property: 'date_expiration', type: 'string', nullable: true, example: '2027-09-03'),
                            new OA\Property(property: 'fichier', type: 'string', example: 'certificat_jean_65f12.pdf'),
                            new OA\Property(
                                property: 'adherent',
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 4),
                                    new OA\Property(property: 'nom', type: 'string', example: 'Dupont'),
                                    new OA\Property(property: 'prenom', type: 'string', example: 'Jean'),
                                    new OA\Property(property: 'email', type: 'string', example: 'jean.dupont@test.fr')
                                ]
                            )
                        ]
                    )
                )
            )
        ]
    )]
    public function listCertificats(Request $request, CertificatMedicalRepository $repository): JsonResponse
    {
        $statut = $request->query->get('statut');
        $criteria = $statut ? ['statut' => $statut] : [];

        $certificats = $repository->findBy($criteria, ['dateUpload' => 'DESC']);

        $data = array_map(function (CertificatMedical $c) {
            $u = $c->getUser();
            return [
                'id' => $c->getId(),
                'statut' => $c->getStatut(),
                'date_upload' => $c->getDateUpload()?->format('Y-m-d H:i:s'),
                'date_expiration' => $c->getDateExpiration()?->format('Y-m-d'),
                'fichier' => $c->getFichier(),
                'adherent' => $u ? [
                    'id' => $u->getId(),
                    'nom' => $u->getNom(),
                    'prenom' => $u->getPrenom(),
                    'email' => $u->getEmail(),
                ] : null,
            ];
        }, $certificats);

        return $this->json($data);
    }

    /**
     * Admin : Valider ou Refuser un certificat médical
     */
    #[Route('/admin/certificats/{id}/validation', name: 'api_admin_certificat_valider', methods: ['PATCH'])]
    #[IsGranted('ROLE_ADMIN')]
    #[OA\Patch(
        path: '/api/admin/certificats/{id}/validation',
        summary: 'Valider ou Refuser un certificat médical (Admin)',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['statut'],
                properties: [
                    new OA\Property(property: 'statut', type: 'string', example: 'valide', enum: ['valide', 'refuse', 'expire', 'en_attente']),
                    new OA\Property(property: 'date_expiration', type: 'string', format: 'date', example: '2027-09-03', description: 'Requis si statut = valide (format YYYY-MM-DD)')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Certificat médical mis à jour'),
            new OA\Response(response: 400, description: 'Statut ou date d\'expiration invalide'),
            new OA\Response(response: 404, description: 'Certificat non trouvé')
        ]
    )]
    public function validateCertificat(
        CertificatMedical $certificat,
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $statut = strtolower($data['statut'] ?? '');
        $allowedStatuts = ['valide', 'refuse', 'expire', 'en_attente'];

        if (!in_array($statut, $allowedStatuts, true)) {
            return $this->json(['message' => 'Statut invalide. Valeurs acceptées : ' . implode(', ', $allowedStatuts)], Response::HTTP_BAD_REQUEST);
        }

        $certificat->setStatut($statut);

        if ($statut === 'valide') {
            if (!empty($data['date_expiration'])) {
                try {
                    $certificat->setDateExpiration(new \DateTimeImmutable($data['date_expiration']));
                } catch (\Exception $e) {
                    return $this->json(['message' => 'Format de date_expiration invalide (attendu: YYYY-MM-DD).'], Response::HTTP_BAD_REQUEST);
                }
            } else {
                // Par défaut : expiration à +1 an si non précisé
                $certificat->setDateExpiration((new \DateTimeImmutable())->modify('+1 year'));
            }
        } elseif ($statut === 'refuse') {
            $certificat->setDateExpiration(null);
        }

        $em->flush();

        return $this->json([
            'message' => 'Statut du certificat médical mis à jour avec succès.',
            'id' => $certificat->getId(),
            'statut' => $certificat->getStatut(),
            'date_expiration' => $certificat->getDateExpiration()?->format('Y-m-d')
        ]);
    }

    /**
     * Admin : Télécharger / Consulter le fichier d'un certificat adhérent
     */
    #[Route('/admin/certificats/{id}/download', name: 'api_admin_certificat_download', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    #[OA\Get(
        path: '/api/admin/certificats/{id}/download',
        summary: 'Télécharger / Consulter le fichier d\'un certificat adhérent (Admin)',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Fichier binaire du certificat'),
            new OA\Response(response: 404, description: 'Certificat ou fichier non trouvé')
        ]
    )]
    public function downloadCertificatAdmin(CertificatMedical $certificat): Response
    {
        if (!$certificat->getFichier()) {
            return $this->json(['message' => 'Aucun fichier associé à ce certificat.'], Response::HTTP_NOT_FOUND);
        }

        $filePath = $this->getParameter('certificats_directory') . '/' . $certificat->getFichier();
        if (!file_exists($filePath)) {
            return $this->json(['message' => 'Fichier introuvable sur le serveur.'], Response::HTTP_NOT_FOUND);
        }

        $response = new BinaryFileResponse($filePath);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $certificat->getFichier());

        return $response;
    }
}
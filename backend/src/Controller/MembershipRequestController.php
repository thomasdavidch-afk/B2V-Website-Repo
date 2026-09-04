<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api', name: 'api_')]
class MembershipRequestController extends AbstractController
{
    #[Route('/membership-request', name: 'membership_request', methods: ['POST'])]
    public function submitRequest(Request $request, MailerInterface $mailer): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // 1. Validation minimale côté serveur
        if (
            empty($data['lastname']) || 
            empty($data['firstname']) || 
            empty($data['email']) || 
            !filter_var($data['email'], FILTER_VALIDATE_EMAIL) ||
            empty($data['phone']) || 
            empty($data['level'])
        ) {
            return $this->json([
                'message' => 'Veuillez remplir tous les champs obligatoires avec des valeurs valides.'
            ], Response::HTTP_BAD_REQUEST);
        }

        $slotsFormatted = !empty($data['slots']) ? implode(', ', array_map('ucfirst', $data['slots'])) : 'Non renseigné';
        $experienceFormatted = !empty($data['experience']) ? nl2br(htmlspecialchars((string)$data['experience'])) : 'Aucune précision apportée.';

        // 2. Email envoyé à l'administrateur
        try {
            $adminEmailAddress = $this->getParameter('app.admin_email');
        } catch (\Throwable $e) {
            $adminEmailAddress = 'thomas.david.ch@gmail.com';
        }

        $adminEmail = (new Email())
            ->from('no-reply@beachvolleyvibes.fr')
            ->to($adminEmailAddress)
            ->replyTo($data['email'])
            ->subject('🏐 Nouvelle demande d\'adhésion : ' . $data['firstname'] . ' ' . $data['lastname'])
            ->html("
                <h2>Nouvelle candidature pour rejoindre le club B2V</h2>
                <p>Un utilisateur vient de remplir le formulaire d'adhésion :</p>
                <hr>
                <ul>
                    <li><strong>Nom :</strong> " . htmlspecialchars($data['lastname']) . "</li>
                    <li><strong>Prénom :</strong> " . htmlspecialchars($data['firstname']) . "</li>
                    <li><strong>Email :</strong> <a href='mailto:{$data['email']}'>" . htmlspecialchars($data['email']) . "</a></li>
                    <li><strong>Téléphone :</strong> <a href='tel:{$data['phone']}'>" . htmlspecialchars($data['phone']) . "</a></li>
                    <li><strong>Niveau estimé :</strong> " . ucfirst(htmlspecialchars($data['level'])) . "</li>
                    <li><strong>Créneaux souhaités :</strong> {$slotsFormatted}</li>
                </ul>
                <p><strong>Expérience / Attentes :</strong><br>{$experienceFormatted}</p>
                <hr>
                <p><em>Rendez-vous sur l'espace d'administration pour créer son compte et lui assigner une séance d'essai.</em></p>
            ");

        // 3. Envoi de l'email
        try {
            $mailer->send($adminEmail);
        } catch (\Throwable $e) {
            return $this->json([
                'message' => 'Erreur lors de l\'envoi de l\'email : ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json([
            'message' => 'Votre demande a bien été transmise à notre équipe.'
        ], Response::HTTP_OK);
    }
}
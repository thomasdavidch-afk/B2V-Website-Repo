<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;

class ContactController extends AbstractController
{
    #[Route('/api/contact', name: 'api_contact', methods: ['POST'])]
    public function contact(Request $request, MailerInterface $mailer): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Récupération des données du front (adaptez les clés selon ce que transmet contact.js)
        $lastName  = $data['lastName'] ?? $data['nom'] ?? null;
        $firstName = $data['firstName'] ?? $data['prenom'] ?? null;
        $email     = $data['email'] ?? null;
        $phone     = $data['phone'] ?? $data['telephone'] ?? 'Non renseigné';
        $subject   = $data['subject'] ?? $data['sujet'] ?? 'Nouveau message de contact';
        $message   = $data['message'] ?? null;

        // Validation basique
        if (!$email || !$message) {
            return new JsonResponse([
                'success' => false,
                'message' => 'L\'adresse email et le message sont obligatoires.'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Préparation et envoi du mail
        $emailMessage = (new Email())
            ->from('noreply@b2v.fr')
            ->to('contact@b2v.fr') // L'adresse du club/admin qui reçoit le message
            ->replyTo($email)       // Permet de répondre directement à l'expéditeur en 1 clic
            ->subject('[Contact] ' . $subject)
            ->html(sprintf(
                '<h2>Nouveau message de contact</h2>' .
                '<p><strong>Nom :</strong> %s %s</p>' .
                '<p><strong>Email :</strong> %s</p>' .
                '<p><strong>Téléphone :</strong> %s</p>' .
                '<p><strong>Sujet :</strong> %s</p>' .
                '<hr>' .
                '<p><strong>Message :</strong></p>' .
                '<p>%s</p>',
                htmlspecialchars((string)$lastName),
                htmlspecialchars((string)$firstName),
                htmlspecialchars((string)$email),
                htmlspecialchars((string)$phone),
                htmlspecialchars((string)$subject),
                nl2br(htmlspecialchars((string)$message))
            ));

        $mailer->send($emailMessage);

        return new JsonResponse([
            'success' => true,
            'message' => 'Votre message a bien été envoyé !'
        ], Response::HTTP_OK);
    }
}
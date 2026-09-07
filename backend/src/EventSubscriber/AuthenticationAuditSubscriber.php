<?php

namespace App\EventSubscriber;

use App\Service\AuditLoggerService;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class AuthenticationAuditSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly AuditLoggerService $auditLogger
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            Events::AUTHENTICATION_SUCCESS => 'onAuthenticationSuccess',
        ];
    }

    public function onAuthenticationSuccess(AuthenticationSuccessEvent $event): void
    {
        $user = $event->getUser();

        // Appel de la bonne méthode logEvent avec les bons arguments
        $this->auditLogger->logEvent(
            eventType: 'USER_LOGIN_SUCCESS',
            author: $user instanceof UserInterface ? $user : null,
            context: [
                'action' => 'Connexion JWT réussie',
            ]
        );
    }
}
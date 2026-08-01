<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Enum\LogTypeEnum;
use App\Service\LogManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * Trace les connexions dans le journal d'audit (CDC §6.3, UC-11).
 *
 * Découplé du flux d'authentification lui-même (json_login + handlers
 * LexikJWTBundle, cf. security.yaml) : Symfony dispatche ces événements
 * indépendamment des success_handler/failure_handler déjà configurés,
 * voir docs/EVENT_SUBSCRIBERS.md.
 */
class LoginLogSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly LogManager $logManager,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
            LoginFailureEvent::class => 'onLoginFailure',
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();

        // getUser() est typé UserInterface (contrat générique Symfony) —
        // on vérifie le type concret avant d'appeler des méthodes propres
        // à notre entité (getEmail() n'existe pas sur UserInterface).
        if (!$user instanceof User) {
            return;
        }

        $this->logManager->log(LogTypeEnum::LoginSuccess, $user, sprintf('Connexion réussie : %s', $user->getEmail()));
    }

    public function onLoginFailure(LoginFailureEvent $event): void
    {
        // Pas de getUser() sur un échec (protection énumération, CDC §6.1) —
        // on relit le body JSON envoyé pour tracer l'email tenté, sans jamais
        // résoudre vers un vrai compte (LogEntry::user reste null ici).
        $data = json_decode($event->getRequest()->getContent(), true);
        $email = $data['email'] ?? 'inconnu';

        $this->logManager->log(LogTypeEnum::LoginFailure, null, sprintf('Échec de connexion pour : %s', $email));
    }
}

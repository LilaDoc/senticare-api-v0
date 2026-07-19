<?php

namespace App\Service;

use App\Entity\Declaration;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;

/**
 * Envoi des notifications email (CDC UC-08/UC-09, US-3.2).
 *
 * Toute erreur d'envoi doit être journalisée SANS bloquer la soumission de la
 * déclaration (US-3.2 — critère d'acceptation explicite).
 */
class NotificationManager
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Notifie le(s) cadre(s) du service concerné lors d'une nouvelle soumission (UC-09).
     * Contenu : service, date, type d'EI, gravité — rédigé dans un esprit blameless (US-3.2).
     * Ne doit jamais laisser une exception remonter à l'appelant.
     */
    public function notifySubmission(Declaration $declaration): void
    {
        // TODO:
        // 1. récupérer les cadres du service concerné (Service::getUsers() filtré ROLE_CADRE)
        // 2. construire l'email (template Twig blameless — jamais "erreur commise par X")
        // 3. try { $this->mailer->send(...) } catch (\Throwable $e) { $this->logger->error(...) } — ne pas relancer.
        throw new \RuntimeException('TODO: implement NotificationManager::notifySubmission()');
    }

    /**
     * Envoie le mot de passe provisoire à la création d'un compte (US-1.2).
     */
    public function notifyAccountCreated(\App\Entity\User $user, string $plainProvisionalPassword): void
    {
        // TODO: email avec le mot de passe provisoire — même politique try/catch + log que ci-dessus.
        throw new \RuntimeException('TODO: implement NotificationManager::notifyAccountCreated()');
    }
}

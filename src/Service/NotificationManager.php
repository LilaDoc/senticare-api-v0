<?php

namespace App\Service;

use App\Entity\Declaration;
use App\Entity\Notification;
use App\Entity\User;
use App\Enum\NotificationStatusEnum;
use App\Enum\RoleEnum;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

/**
 * Envoi des notifications email (CDC UC-08/UC-09, US-3.2).
 *
 * Toute erreur d'envoi doit être journalisée SANS bloquer la soumission de la
 * déclaration (US-3.2 — critère d'acceptation explicite).
 */
class NotificationManager
{
    // POC : pas de front déployé pour l'instant, à externaliser en paramètre le jour venu.
    private const LOGIN_URL = 'http://localhost:5173/login';
    private const FROM_ADDRESS = 'no-reply@senticare.fr';

    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
    ) {
    }

    /**
     * Notifie le(s) cadre(s) du service concerné lors d'une nouvelle soumission (UC-09).
     * Contenu : service, date, type d'EI, gravité — rédigé dans un esprit blameless (US-3.2).
     * Ne doit jamais laisser une exception remonter à l'appelant.
     */
    public function notifySubmission(Declaration $declaration): void
    {
        $cadres = $this->userRepository->findCadresByService($declaration->getService());

        foreach ($cadres as $cadre) {
            $notification = new Notification();
            $notification->setDestinataire($cadre);
            $notification->setDeclaration($declaration);

            try {
                $email = (new TemplatedEmail())
                    ->from(new Address(self::FROM_ADDRESS, 'SentiCare'))
                    ->to($cadre->getEmail())
                    ->subject('Nouvel événement déclaré dans votre service')
                    ->htmlTemplate('emails/notification_submission.html.twig')
                    ->context([
                        'declaration' => $declaration,
                    ]);

                $this->mailer->send($email);

                $notification->setSentAt(new \DateTimeImmutable());
                $notification->setStatus(NotificationStatusEnum::Sent);
            } catch (\Throwable $e) {
                $this->logger->error('Échec envoi notification de soumission', [
                    'declarationId' => $declaration->getId(),
                    'destinataireId' => $cadre->getId(),
                    'exception' => $e->getMessage(),
                ]);

                $notification->setStatus(NotificationStatusEnum::Failed);
            }

            $this->entityManager->persist($notification);
        }

        $this->entityManager->flush();
    }

    /**
     * Envoie le mot de passe provisoire à la création d'un compte (US-1.2).
     * Pas d'entité Notification ici : elle référence obligatoirement une
     * Declaration, qui n'existe pas dans ce flux.
     */
    public function notifyAccountCreated(User $user, string $plainProvisionalPassword): void
    {
        $service = $user->getServices()->first() ?: null;
        $roleValue = $user->getRoles()[0] ?? null;
        $roleLabel = RoleEnum::tryFrom($roleValue)?->label() ?? '—';

        try {
            $email = (new TemplatedEmail())
                ->from(new Address(self::FROM_ADDRESS, 'SentiCare'))
                ->to($user->getEmail())
                ->subject('Bienvenue sur SentiCare')
                ->htmlTemplate('emails/notification_creationAccount.html.twig')
                ->context([
                    'user' => $user,
                    'service' => $service,
                    'roleLabel' => $roleLabel,
                    'temporaryPassword' => $plainProvisionalPassword,
                    'login_url' => self::LOGIN_URL,
                ]);

            $this->mailer->send($email);
        } catch (\Throwable $e) {
            $this->logger->error('Échec envoi email de création de compte', [
                'userId' => $user->getId(),
                'exception' => $e->getMessage(),
            ]);
        }
    }
}

<?php

namespace App\Tests\Unit\Service;

use App\Entity\Declaration;
use App\Entity\Notification;
use App\Entity\Pole;
use App\Entity\Service as ServiceEntity;
use App\Entity\User;
use App\Enum\NotificationStatusEnum;
use App\Enum\RoleEnum;
use App\Enum\TypeEIEnum;
use App\Service\NotificationManager;
use App\Tests\Unit\Fakes\InMemoryUserRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;

/**
 * Test unitaire (cf. 01_tests-2.md, niveau 1). Le Mailer et le Logger sont
 * de véritables dépendances externes (comme la passerelle de paiement du
 * cours) — Mock quand on vérifie l'appel, Stub quand on veut juste
 * provoquer un échec pour tester le try/catch.
 */
class NotificationManagerTest extends TestCase
{
    private function createDeclarationWithCadre(): array
    {
        $pole = new Pole();
        $pole->setNom('Chirurgie');

        $service = new ServiceEntity();
        $service->setNom('Bloc opératoire');
        $service->setPole($pole);

        $declarant = new User();
        $declarant->setEmail('soignant@test.fr');
        $declarant->setRole(RoleEnum::Soignant);
        $declarant->setPassword('hash');
        $declarant->addService($service);

        $cadre = new User();
        $cadre->setEmail('cadre@test.fr');
        $cadre->setRole(RoleEnum::Cadre);
        $cadre->setPassword('hash');
        $cadre->addService($service);

        $declaration = new Declaration();
        $declaration->setDeclarant($declarant);
        $declaration->setService($service);
        $declaration->setTypeEI(TypeEIEnum::Chute);
        $declaration->setDateConstat(new \DateTimeImmutable('-1 day'));
        $declaration->setDateSurvenue(new \DateTimeImmutable('-1 day'));

        return [$declaration, $cadre];
    }

    public function testNotifySubmissionPersistsSentNotificationForEachCadre(): void
    {
        [$declaration, $cadre] = $this->createDeclarationWithCadre();

        $userRepository = new InMemoryUserRepository();
        $userRepository->add($cadre);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (Notification $notification) use ($cadre, $declaration): bool {
                return $notification->getStatus() === NotificationStatusEnum::Sent
                    && $notification->getDestinataire() === $cadre
                    && $notification->getDeclaration() === $declaration
                    && null !== $notification->getSentAt();
            }));
        $entityManager->expects($this->once())->method('flush');

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())->method('send');

        $manager = new NotificationManager($mailer, $this->createStub(LoggerInterface::class), $entityManager, $userRepository);

        $manager->notifySubmission($declaration);
    }

    public function testNotifySubmissionPersistsFailedNotificationAndLogsWhenMailerThrows(): void
    {
        [$declaration, $cadre] = $this->createDeclarationWithCadre();

        $userRepository = new InMemoryUserRepository();
        $userRepository->add($cadre);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())
            ->method('persist')
            ->with($this->callback(fn (Notification $notification): bool => NotificationStatusEnum::Failed === $notification->getStatus()));

        $mailer = $this->createStub(MailerInterface::class);
        $mailer->method('send')->willThrowException($this->createStub(TransportExceptionInterface::class));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error');

        $manager = new NotificationManager($mailer, $logger, $entityManager, $userRepository);

        // Ne doit jamais laisser l'exception remonter (CDC §6.3 / US-3.2).
        $manager->notifySubmission($declaration);
    }

    public function testNotifySubmissionDoesNothingWhenNoCadreOnService(): void
    {
        [$declaration] = $this->createDeclarationWithCadre();

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');
        $entityManager->expects($this->once())->method('flush');

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->never())->method('send');

        $manager = new NotificationManager($mailer, $this->createStub(LoggerInterface::class), $entityManager, new InMemoryUserRepository());

        $manager->notifySubmission($declaration);
    }

    public function testNotifyAccountCreatedSendsEmail(): void
    {
        $service = new ServiceEntity();
        $service->setNom('Bloc opératoire');

        $user = new User();
        $user->setEmail('soignant@test.fr');
        $user->setNom('Nom');
        $user->setPrenom('Prenom');
        $user->setRole(RoleEnum::Soignant);
        $user->setPassword('hash');
        $user->addService($service);

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())->method('send');

        $manager = new NotificationManager(
            $mailer,
            $this->createStub(LoggerInterface::class),
            $this->createStub(EntityManagerInterface::class),
            new InMemoryUserRepository(),
        );

        $manager->notifyAccountCreated($user, 'mot-de-passe-provisoire');
    }

    public function testNotifyAccountCreatedLogsErrorWithoutThrowingWhenMailerFails(): void
    {
        $user = new User();
        $user->setEmail('soignant@test.fr');
        $user->setRole(RoleEnum::Soignant);
        $user->setPassword('hash');

        $mailer = $this->createStub(MailerInterface::class);
        $mailer->method('send')->willThrowException($this->createStub(TransportExceptionInterface::class));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error');

        $manager = new NotificationManager(
            $mailer,
            $logger,
            $this->createStub(EntityManagerInterface::class),
            new InMemoryUserRepository(),
        );

        // Ne doit jamais laisser l'exception remonter.
        $manager->notifyAccountCreated($user, 'mot-de-passe-provisoire');
    }
}

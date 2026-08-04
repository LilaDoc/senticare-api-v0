<?php

namespace App\EventSubscriber;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Garantit que toute erreur sur /api/* est renvoyée en JSON, quel que soit le
 * header Accept envoyé par le client.
 *
 * Sans ce filet, une exception qui n'a pas déjà été transformée en réponse
 * plus haut dans la chaîne (ex: NotFoundHttpException via
 * createNotFoundException(), ou tout bug non prévu) tombe sur le rendu HTML
 * par défaut de Symfony si le client n'envoie pas explicitement
 * "Accept: application/json" — ce que fetch() ne fait PAS par défaut (il
 * envoie "Accept: * / *"). Le front recevrait alors une page HTML là où il
 * attend du JSON.
 *
 * Priorité -10, volontairement entre les deux bornes qui comptent :
 * - après Symfony\Component\Security\Http\Firewall\ExceptionListener
 *   (priorité 1, gère déjà les 401/403 en JSON) — on ne doit jamais lui
 *   voler la main, d'où le `hasResponse()` ci-dessous en garde-fou en plus ;
 * - avant Symfony\Component\HttpKernel\EventListener\ErrorListener par
 *   défaut (priorité -128), qui est celui qui produirait la page HTML.
 */
class ApiExceptionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        #[Autowire('%kernel.debug%')]
        private readonly bool $debug,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', -10],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        if ($event->hasResponse() || !str_starts_with($event->getRequest()->getPathInfo(), '/api/')) {
            return;
        }

        $throwable = $event->getThrowable();
        $isHttpException = $throwable instanceof HttpExceptionInterface;
        $statusCode = $isHttpException ? $throwable->getStatusCode() : 500;

        // Une HttpException (404 via createNotFoundException(), 400...) porte
        // toujours un message volontairement destiné au client. Une exception
        // "générique" (bug non prévu, 500) ne doit jamais fuiter son message
        // réel hors dev/test (trace/requête SQL/chemin serveur...).
        $message = match (true) {
            $isHttpException => $throwable->getMessage() ?: 'Error',
            $this->debug => $throwable->getMessage(),
            default => 'Erreur interne du serveur.',
        };

        $response = new JsonResponse(['code' => $statusCode, 'message' => $message], $statusCode);
        if ($isHttpException) {
            $response->headers->add($throwable->getHeaders());
        }

        $event->setResponse($response);
    }
}

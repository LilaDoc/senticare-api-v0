# Les EventSubscribers Symfony — cours

Comment tracer les connexions (CDC §6.3) sans toucher au flux d'authentification existant. Nouveau concept dans ce projet — on n'a encore jamais eu besoin de "réagir" à quelque chose qui se passe ailleurs dans le framework sans en être l'appelant direct.

## 1. Le problème que ça résout

Jusqu'ici, chaque fois qu'on voulait déclencher une action (envoyer un email, écrire un log), on faisait un appel direct : `DeclarationManager::submit()` appelle explicitement `$this->notificationManager->notifySubmission(...)`. C'est un appel de méthode classique — `submit()` **connaît** `NotificationManager` et décide explicitement de l'appeler.

Pour les connexions, ce modèle ne marche pas : la logique d'authentification (vérifier le mot de passe, émettre le JWT) est **entièrement gérée par Symfony Security et LexikJWTBundle** — on n'écrit aucun contrôleur ni service pour ça (regarde `security.yaml` : `json_login` + `success_handler`/`failure_handler` fournis par le bundle). On n'a donc *aucun endroit à nous* dans ce flux où on pourrait écrire `$this->logManager->log(...)`.

C'est exactement le problème que le pattern **Observateur** (Observer) résout : plutôt que le code qui déclenche l'événement connaisse à l'avance tout ce qui doit réagir, il se contente d'annoncer *"il vient de se passer ceci"* — et n'importe quel code, écrit n'importe où, peut s'abonner pour réagir, sans que le code d'origine (ici : Symfony Security) ait besoin d'être modifié ou même d'être au courant que notre code existe.

## 2. Le mécanisme : EventDispatcher

Symfony a un service central, l'**EventDispatcher**, qui fait transiter des "événements" (de simples objets PHP) entre codes qui n'ont pas besoin de se connaître :

```
Authenticator Symfony (json_login)
       │
       │  authentification réussie
       ▼
EventDispatcher->dispatch(new LoginSuccessEvent(...))
       │
       │  "quelqu'un s'est-il abonné à LoginSuccessEvent ?"
       ▼
   Notre EventSubscriber (une fois câblé)
```

Le dispatcher ne sait rien de nous. Notre Subscriber ne sait rien de l'authenticator. C'est le **découplage** qui fait tout l'intérêt du pattern — le même principe que les Voters découplent "qui a le droit" de "comment on vérifie" (`supports()`/`voteOnAttribute()`), sauf qu'ici c'est "il s'est passé quelque chose" / "voici qui veut réagir".

## 3. Listener vs Subscriber

Deux façons de s'abonner à un événement Symfony — tu ne verras que la deuxième dans ce projet, mais autant connaître la différence :

| | EventListener | EventSubscriber |
|---|---|---|
| Où on déclare l'événement écouté | Dans `config/services.yaml` (tag `kernel.event_listener`) | **Dans la classe elle-même**, via une méthode statique |
| Avantage | — | La classe est auto-suffisante : ouvrir le fichier suffit à savoir ce qu'elle écoute, pas besoin d'aller chercher dans la config |

On utilise systématiquement `EventSubscriber` dans ce projet (comme partout en Symfony moderne) pour cette raison : toute l'info est dans un seul fichier.

## 4. Anatomie d'un EventSubscriber

```php
namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

class LoginLogSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        // $event contient tout ce qui concerne CET événement précis :
        // l'utilisateur authentifié, la requête, le firewall concerné...
    }
}
```

Trois choses à retenir :

1. **`getSubscribedEvents()` est `static`** — Symfony l'appelle *avant même d'instancier la classe*, juste pour savoir "à quoi dois-je m'abonner ?". C'est pour ça qu'elle ne peut pas dépendre de `$this` ni du constructeur.
2. **Le tableau associe une classe d'événement à un nom de méthode** (une chaîne de caractères) — Symfony appellera `$subscriber->onLoginSuccess($event)` lui-même le moment venu. On peut aussi écouter plusieurs événements dans la même classe, en ajoutant d'autres entrées.
3. **Aucune config YAML nécessaire.** Comme tout le reste de `src/`, la classe est auto-découverte et auto-taguée (`config/services.yaml` : `App\: resource: '../src/'` avec `autoconfigure: true`) — Symfony détecte qu'elle implémente `EventSubscriberInterface` et l'enregistre tout seul comme service `kernel.event_subscriber`. Exactement le même mécanisme automatique qui fait qu'un `Voter` n'a besoin d'aucune déclaration manuelle.

## 5. Les événements de connexion dans CE projet

Setup actuel (`config/packages/security.yaml`) :

```yaml
firewalls:
    login:
        pattern: ^/api/login
        stateless: true
        json_login:
            check_path: /api/login_check
            success_handler: lexik_jwt_authentication.handler.authentication_success
            failure_handler: lexik_jwt_authentication.handler.authentication_failure
```

Le firewall `login` utilise `json_login`, l'authenticator natif de Symfony. Les `success_handler`/`failure_handler` pointent vers ceux de LexikJWTBundle — ce sont eux qui construisent la réponse HTTP (`{ token, refresh_token }` ou une erreur 401). **Ça ne change rien pour nous** : que des handlers custom soient configurés ou non, Symfony dispatche toujours les deux événements génériques suivants pendant le processus d'authentification, avant même d'appeler ces handlers :

- **`Symfony\Component\Security\Http\Event\LoginSuccessEvent`** — authentification réussie.
  - `$event->getUser()` → l'entité `User` authentifiée.
  - `$event->getRequest()` → la requête HTTP d'origine.
- **`Symfony\Component\Security\Http\Event\LoginFailureEvent`** — authentification échouée (mauvais mot de passe, compte inconnu, compte désactivé...).
  - **Pas de `getUser()`** — par construction, on ne sait pas *qui* a échoué à se connecter (protection énumération, le même principe que le message d'erreur générique "Invalid credentials." déjà en place, CDC §6.1).
  - `$event->getException()` → l'`AuthenticationException` (le type précis donne une indication : mauvais mot de passe, utilisateur introuvable...).
  - `$event->getRequest()` → permet de relire le body JSON (`email`) envoyé par le client, si on veut le journaliser malgré tout dans le champ `message` (pas dans `LogEntry::user`, qui reste `null` puisqu'aucun compte n'a été résolu).

## 6. Où ça branche dans ce qu'on a déjà construit

`LogEntry::user` est **nullable** précisément pour ce cas — décision prise dès la conception de l'entité (voir `docs/ROADMAP.md` §8) : un login raté sur un email inconnu ne correspond à aucun `User` en base, mais on veut quand même tracer la tentative.

Le plan :

```php
class LoginLogSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly LogManager $logManager) {}

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
            LoginFailureEvent::class => 'onLoginFailure',
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        // $this->logManager->log(LogTypeEnum::LoginSuccess, $event->getUser(), ...);
    }

    public function onLoginFailure(LoginFailureEvent $event): void
    {
        // $this->logManager->log(LogTypeEnum::LoginFailure, null, ...);
    }
}
```

`LogManager` s'injecte dans le constructeur exactement comme dans n'importe quel autre service — un `EventSubscriber` est un service Symfony comme les autres, l'autowiring fonctionne pareil.

## Ce qu'il te reste à écrire

- `onLoginSuccess()` : construire le message (ex: email de l'utilisateur) et appeler `logManager->log(LogTypeEnum::LoginSuccess, $event->getUser(), ...)`.
- `onLoginFailure()` : récupérer l'email tenté depuis `$event->getRequest()` (body JSON, champ `email` — attention, peut être absent/malformé, à sécuriser), appeler `logManager->log(LogTypeEnum::LoginFailure, null, ...)`.
- Vérifier que `$event->getUser()` renvoie bien une instance de `App\Entity\User` (le type de retour de l'interface est `UserInterface`, plus générique) avant d'appeler des méthodes spécifiques comme `getEmail()`.

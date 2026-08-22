# Comprendre `vendor/` — ce que fournit le framework vs ce qu'on écrit

## Qu'est-ce que `vendor/` ?

`vendor/` contient tout le code **tiers** (Symfony, Doctrine, Lexik JWT, etc.) — téléchargé automatiquement par Composer à partir de `composer.json`/`composer.lock`.

- Écrit à la main par les équipes de ces projets open-source, pas "généré".
- **Jamais** à modifier directement : tout changement serait écrasé au prochain `composer install`/`composer update`.
- On peut (et doit) le **lire** pour comprendre comment un mécanisme fonctionne (comme on l'a fait avec `Voter.php`), mais on l'étend/l'utilise depuis `src/`, jamais on ne le touche.

Principe général dans tout le projet : **le framework fournit la tuyauterie (quand et comment ton code est appelé), toi tu fournis le contenu (la logique métier réelle)**.

---

## Exemple concret : les Voters

| | Fourni par Symfony (`vendor/symfony/security-core/.../Voter.php`) | Écrit par nous (`src/Security/Voter/DeclarationVoter.php`) |
|---|---|---|
| Quoi | La classe abstraite `Voter` avec la méthode `vote()` déjà écrite | `class DeclarationVoter extends Voter` |
| Rôle | Orchestre : appelle `supports()` puis, si `true`, `voteOnAttribute()` | Répond aux deux questions posées par l'orchestrateur |
| Déclenchement | Le système de sécurité appelle `vote()` sur **tous** les Voters à chaque `isGranted()`/`denyAccessUnlessGranted()` | Rien à déclencher — Symfony détecte et enregistre le Voter automatiquement dès qu'une classe fait `extends Voter` |
| On y touche ? | Jamais | C'est tout notre travail : `supports()`, `voteOnAttribute()`, et les `can...()` privées |

La chaîne complète :
```
denyAccessUnlessGranted('DECLARATION_VIEW', $declaration)   [nous, dans un contrôleur]
   → vote() [Symfony, hérité, jamais écrit par nous]
       → supports('DECLARATION_VIEW', $declaration)          [nous]
           → si true : voteOnAttribute(...)                  [nous]
               → canView($declaration, $user)                [nous, logique métier CDC]
```

---

## Comment utiliser un Voter (depuis un contrôleur)

Deux méthodes disponibles, héritées d'`AbstractController` (donc accessibles via `$this` dans n'importe quel contrôleur qui `extends AbstractController`) :

### `denyAccessUnlessGranted($attribut, $sujet)` — bloque si refusé

```php
$service = $this->serviceRepository->find($id) ?? throw $this->createNotFoundException();

$this->denyAccessUnlessGranted(ServiceVoter::VIEW, $service);

// si on arrive ici, c'est que l'accès est accordé — sinon la ligne du dessus
// a déjà levé une AccessDeniedException (→ HTTP 403 automatique) et la
// méthode s'est arrêtée là, le reste du code ne s'exécute jamais.
return $this->json([...]);
```

C'est un **garde-fou** : pas de `if`/`else` autour, pas de valeur de retour à gérer. Soit ça laisse passer silencieusement (accès accordé), soit ça interrompt tout (exception + 403). À utiliser quand on protège l'accès à **une ressource précise déjà chargée** (`show`, `update`, `delete`, `deactivate`...).

### `isGranted($attribut, $sujet = null)` — répond juste oui/non

```php
if ($this->isGranted('ROLE_ADMIN')) {
    $services = $this->serviceRepository->findAll();
} elseif ($this->isGranted('ROLE_CHEF_POLE')) {
    $services = $currentUser->getServices()->toArray();
} else {
    throw $this->createAccessDeniedException();
}
```

Renvoie un booléen au lieu de lever une exception — utile quand le comportement doit **s'adapter** selon le droit (ex: filtrer une liste différemment selon le rôle), plutôt que tout bloquer d'un coup. `$sujet` est optionnel : sans lui, ça vérifie juste un rôle brut (comme `#[IsGranted('ROLE_ADMIN')]`) ; avec un objet, ça déclenche un Voter (comme `denyAccessUnlessGranted`).

### Ce qui se passe derrière l'appel

Peu importe laquelle des deux méthodes, le mécanisme est le même (cf. section précédente) :

```
$this->denyAccessUnlessGranted(ServiceVoter::VIEW, $service)   [nous, contrôleur]
   → Symfony interroge TOUS les Voters enregistrés de l'appli
       → ServiceVoter::supports('SERVICE_VIEW', $service) → true
           → ServiceVoter::voteOnAttribute(...) → canManage($service, $user)
               → accordé ou refusé, selon le rôle + périmètre de l'utilisateur connecté
```

Le `$user` utilisé à l'intérieur du Voter n'est **jamais** passé explicitement par le contrôleur — Symfony le récupère lui-même depuis le token de sécurité de la requête en cours. C'est pour ça que `show()` peut appeler `denyAccessUnlessGranted()` sans avoir `#[CurrentUser] User $currentUser` en paramètre : inutile ici, seul le Voter a besoin de connaître l'utilisateur, pas le contrôleur.

---

## Autres exemples déjà rencontrés dans ce projet

### Repository (Doctrine)

| Fourni par Doctrine (`ServiceEntityRepository`) | Écrit par nous (`PoleRepository`) |
|---|---|
| `find($id)`, `findAll()`, `findBy(...)`, `findOneBy(...)` | `findByUser(User $user): array` — logique de requête propre à SentiCare |

### Cycle de vie d'une entité (Doctrine)

| Fourni par Doctrine | Écrit par nous (`Pole.php`) |
|---|---|
| Le moteur qui, pendant `flush()`, détecte les entités modifiées et appelle automatiquement les méthodes taguées `#[ORM\PreUpdate]` | La méthode `onPreUpdate()` elle-même (son contenu : `$this->updatedAt = new \DateTimeImmutable();`) et l'attribut `#[ORM\PreUpdate]` qui l'annonce |
| `persist()` (commence à suivre un nouvel objet) / `flush()` (écrit en base tout ce qui a changé) | Le moment où on les appelle (dans les Managers, jamais dans les entités elles-mêmes) |

### Contrôleur (Symfony)

| Fourni par Symfony | Écrit par nous |
|---|---|
| Le routeur qui associe une URL (`#[Route(...)]`) à une méthode de contrôleur, l'injection automatique des services dans le constructeur (dependency injection) | Le contenu des méthodes (`list()`, `create()`...), le constructeur listant les services dont on a besoin |

---

## Comment vérifier "à qui appartient ce fichier ?"

- Chemin qui commence par `vendor/` → tiers, lecture seule.
- Chemin qui commence par `src/`, `tests/`, `config/`, `migrations/` → à nous, à modifier librement.
- Dans le doute : `git status` ne montrera jamais rien de suivi dans `vendor/` (il est dans `.gitignore`) — signe que ce n'est pas du code du projet.

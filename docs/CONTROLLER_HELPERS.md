# Méthodes utilitaires d'`AbstractController` — quand utiliser quoi

Toutes les méthodes ci-dessous sont **héritées** (`extends AbstractController`), jamais définies dans nos fichiers — cf. `docs/VENDOR.md` pour le principe général "framework fournit la tuyauterie, nous le contenu".

Aucune ne prend l'utilisateur connecté en argument explicite : elles vont toutes le chercher elles-mêmes dans le contexte de sécurité de la requête en cours.

---

## Table de référence rapide

| Méthode | Renvoie | Lève une exception ? | Code HTTP si refus/absence |
|---|---|---|---|
| `json($data, $status = 200)` | `JsonResponse` | Non | — |
| `isGranted($attribut, $sujet = null)` | `bool` | Non | — (à toi de gérer) |
| `denyAccessUnlessGranted($attribut, $sujet = null)` | `void` | Oui, si refusé | 403 |
| `createNotFoundException($message = '')` | `NotFoundHttpException` (à `throw` toi-même) | — | 404 |
| `createAccessDeniedException($message = '')` | `AccessDeniedException` (à `throw` toi-même) | — | 403 |
| `getUser()` | `?UserInterface` | Non | — |

---

## `$this->json($data, $status = JsonResponse::HTTP_OK)`

Construit une `JsonResponse` — sérialise `$data` (tableau associatif, jamais une entité brute) en JSON.

**Quand l'utiliser** : à chaque fois qu'un contrôleur renvoie une réponse réussie.

```php
return $this->json([
    'id' => (string) $pole->getId(),
    'nom' => $pole->getNom(),
]);

// avec un code HTTP différent (ex: création)
return $this->json([...], JsonResponse::HTTP_CREATED); // 201
```

⚠️ Ne jamais faire `$this->json($entity)` directement — voir `docs/VENDOR.md` (boucle infinie possible sur les relations, et risque d'exposer des champs sensibles comme `password`, CDC §6.3). Toujours mapper vers un tableau explicite d'abord.

---

## `$this->isGranted($attribut, $sujet = null)`

Pose la question et **renvoie juste `true`/`false`** — ne bloque jamais l'exécution toute seule.

**Quand l'utiliser** : quand le comportement doit *varier* selon le droit, plutôt que tout bloquer d'un coup. Typiquement une liste dont le contenu dépend du rôle.

```php
// ServiceController::list()
if ($this->isGranted('ROLE_ADMIN')) {
    $services = $this->serviceRepository->findAll();
} elseif ($this->isGranted('ROLE_CHEF_POLE')) {
    $services = $currentUser->getServices()->toArray();
} else {
    throw $this->createAccessDeniedException();
}
```

Sans sujet (`isGranted('ROLE_ADMIN')`) → vérifie juste un rôle brut. Avec un sujet (`isGranted(ServiceVoter::VIEW, $service)`) → déclenche un Voter, comme `denyAccessUnlessGranted`, mais sans lever d'exception.

---

## `$this->denyAccessUnlessGranted($attribut, $sujet = null)`

Pose la même question que `isGranted()`, mais **bloque tout de suite** si refusé (lève `AccessDeniedException` → 403 automatique). Aucune valeur de retour à gérer : soit ça laisse passer silencieusement, soit l'exécution s'arrête là.

**Quand l'utiliser** : protéger l'accès à **une ressource précise déjà chargée**, où il n'y a rien d'autre à faire en cas de refus que bloquer.

```php
// ServiceController::show()
$service = $this->serviceRepository->find($id) ?? throw $this->createNotFoundException();

$this->denyAccessUnlessGranted(ServiceVoter::VIEW, $service);

return $this->json([...]);
```

C'est la méthode la plus utilisée du projet (14 fois) — c'est le réflexe par défaut pour `show()`/`update()`/`deactivate()`/`create()` dès qu'un Voter existe pour la ressource concernée.

---

## `throw $this->createNotFoundException($message = '')`

Crée (sans lever) une `NotFoundHttpException`. Le `throw` devant est obligatoire — sans lui, l'exception est juste un objet inerte, rien ne se passe.

**Quand l'utiliser** : la ressource demandée par `{id}` n'existe pas en base. Motif quasi systématique :

```php
$pole = $this->poleRepository->find($id) ?? throw $this->createNotFoundException();
```

Le `??` (opérateur de coalescence nulle) + `throw` comme expression (PHP 8+) permet de tout écrire sur une seule ligne : *"charge, et si `null`, 404 direct"*.

---

## `throw $this->createAccessDeniedException($message = '')`

Crée (sans lever) une `AccessDeniedException`. Version manuelle de ce que `denyAccessUnlessGranted()` fait automatiquement.

**Quand l'utiliser** : uniquement quand la logique d'autorisation est écrite à la main (`if`/`elseif`/`else` sur des rôles, pas de Voter dédié) et qu'il faut un refus explicite dans la branche `else` :

```php
if ($this->isGranted('ROLE_ADMIN')) {
    // ...
} elseif ($this->isGranted('ROLE_CHEF_POLE')) {
    // ...
} else {
    throw $this->createAccessDeniedException();
}
```

Si un Voter existe déjà pour la ressource, préférer `denyAccessUnlessGranted()` — plus court, et la logique de périmètre reste centralisée dans le Voter plutôt que dupliquée dans le contrôleur.

---

## `$this->getUser()`

Renvoie l'utilisateur actuellement connecté (`?UserInterface`), ou `null` si personne n'est authentifié (rare dans nos contrôleurs, puisque le firewall bloque déjà les requêtes non authentifiées avant d'arriver ici).

**Quand l'utiliser** : uniquement si l'utilisateur courant n'est pas déjà disponible autrement. Dans ce projet, on préfère systématiquement `#[CurrentUser] User $currentUser` en paramètre de méthode (voir `PoleController::create()`, `ServiceController::list()`...) — ça donne directement un objet `User` typé (pas besoin de caster/vérifier `null`), donc `getUser()` reste rarement utile ici. Utile surtout dans du code qui n'est *pas* une méthode de contrôleur (un service, un event listener) où on n'a pas la main sur les paramètres de la requête.

---

## Arbre de décision

```
Je dois répondre avec des données          → $this->json([...])
Je dois vérifier un droit...
  ├─ ...et juste bloquer si refusé          → $this->denyAccessUnlessGranted($attr, $sujet)
  ├─ ...et adapter le comportement selon    → $this->isGranted($attr, $sujet)
  └─ ...sans Voter dédié, logique à la main → if/elseif/else + throw $this->createAccessDeniedException()
Je charge une ressource par {id}
  └─ ...et elle peut ne pas exister         → $x = $repo->find($id) ?? throw $this->createNotFoundException();
```

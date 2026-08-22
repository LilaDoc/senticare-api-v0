# Fiche de révision — notions techniques SentiCare

Document de préparation à l'oral CDA. Chaque notion est expliquée avec un exemple concret tiré du code du projet — pour l'oral, sache retrouver le fichier et expliquer le "pourquoi", pas juste réciter la définition.

---

## 1. Architecture en couches — Controller / Manager / Repository

**La règle** : un contrôleur ne doit jamais contenir de logique métier ni de requête. Trois responsabilités séparées :

| Couche | Rôle | Exemple |
|---|---|---|
| **Controller** | Reçoit la requête HTTP, valide le format des données, appelle le Manager, sérialise la réponse | `DeclarationController::create()` |
| **Manager** (`src/Service/`) | Logique métier : règles, transitions, autorisations "qui a le droit de voir quoi" | `DeclarationManager::submit()` |
| **Repository** | Traduit des critères en requête Doctrine (QueryBuilder/DQL), aucune règle métier | `DeclarationRepository::search()` |

**Pourquoi séparer ?** Testabilité (un Manager se teste sans HTTP ni base de données réelle), réutilisabilité (`StatsManager` réutilise `DeclarationManager::resolvePerimeterCriteria()` au lieu de dupliquer la règle de périmètre), lisibilité (chaque fichier a une seule raison de changer — principe de responsabilité unique SOLID).

**Exemple concret de réutilisation** : `DeclarationManager::search()` (liste filtrée UC-06) et `StatsManager::aggregate()` (tableau de bord) ont besoin exactement de la même règle "qui a le droit de voir quoi". Plutôt que de la dupliquer, elle est extraite dans `DeclarationManager::resolvePerimeterCriteria(User $requester): array`, appelée par les deux Managers. Une seule source de vérité pour une règle de sécurité.

---

## 2. Authentification — JWT + Argon2id

- **JWT (JSON Web Token)** : standard *stateless* — le serveur ne garde aucune session en mémoire, toute l'info d'identité est dans le token, signé cryptographiquement. Adapté à une architecture découplée SPA (React) + API REST, contrairement aux sessions PHP classiques qui supposent un client "collant" au même serveur.
- **Argon2id** : algorithme de hachage de mot de passe recommandé par l'ANSSI (remplace bcrypt/MD5/SHA1 qui sont soit cassés soit trop rapides à bruteforcer). Symfony l'utilise par défaut via `UserPasswordHasherInterface`.
- **Refresh token rotatif** (GesdinetJWTRefreshTokenBundle) : le JWT d'accès a une durée de vie courte (1h) pour limiter la fenêtre d'exploitation en cas de vol ; le refresh token permet d'en obtenir un nouveau sans re-authentification complète, et "tourne" (nouveau refresh token à chaque utilisation) pour détecter un vol.
- **UUID v4 comme clé primaire** (au lieu d'un entier auto-incrémenté) : empêche l'énumération de ressources par ID (`/api/users/1`, `/api/users/2`, ...) — CDC §6.2. Un ID auto-incrémenté fuite de l'information (nombre d'utilisateurs créés, ordre de création).

---

## 3. Autorisation — les Voters Symfony

**Le principe** : un Voter répond à *"cet utilisateur a-t-il le droit de faire CETTE action sur CETTE ressource précise ?"*. Toujours un couple (attribut, sujet).

**Le pattern (Template Method)** :
```php
class DeclarationVoter extends Voter
{
    protected function supports(string $attribute, mixed $subject): bool
    {
        // "Est-ce que JE sais juger cette combinaison ?"
        return in_array($attribute, self::ATTRIBUTES, true) && $subject instanceof Declaration;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        // Appelée SEULEMENT si supports() a renvoyé true. Ici on répond vraiment.
    }
}
```
`vote()` (la méthode publique appelée par Symfony) est héritée de la classe abstraite `Voter` et n'est jamais réécrite — elle appelle `supports()` puis `voteOnAttribute()` pour toi. C'est le patron de conception *Template Method* : la classe mère fixe le squelette de l'algorithme, les classes filles ne remplissent que les étapes variables.

**Trois façons de vérifier un droit dans un contrôleur** :
- `$this->denyAccessUnlessGranted(Voter::ATTRIBUT, $subject)` → lève une `AccessDeniedException` (403) automatiquement si refusé.
- `$this->isGranted(...)` → renvoie juste un booléen, pour un `if` (ex: brancher `list()` selon le rôle).
- `$this->createAccessDeniedException()` / `createNotFoundException()` → à lever soi-même quand la logique ne rentre pas dans un Voter (ex: règle métier indépendante du Voter dans `UserController::create()` : "un chef de pôle ne crée que des soignants/cadres").

**Quand NE PAS utiliser de Voter** : quand il n'y a pas de sujet unique à juger — typiquement une requête agrégée ("donne-moi MES stats", "donne-moi MA liste filtrée"). Dans ce cas, la résolution du périmètre se fait directement dans le Manager via le rôle de l'utilisateur (`hasRole()`), pas via `denyAccessUnlessGranted()`. Exemple : `DeclarationManager::resolvePerimeterCriteria()` et `StatsManager::aggregate()` n'utilisent aucun Voter — il n'y a pas de "ressource cible" à voter, juste "mon propre périmètre".

**Défense en profondeur** : un Voter doit refuser *explicitement* les cas interdits plutôt que de compter sur une déduction implicite. Exemple concret dans `DeclarationVoter::canView()` :
```php
if ($this->hasRole($user, RoleEnum::Admin)) {
    // CDC §3 : interdiction explicite, on sort tout de suite, peu importe le reste.
    return false;
}
```
Le même réflexe a été appliqué dans `DeclarationManager::resolvePerimeterCriteria()` : plutôt qu'un `if/elseif/else` où le `else` traite implicitement "tout ce qui n'est pas chef de pôle/cadre" comme un soignant, chaque rôle autorisé a sa propre branche explicite, et le dernier `else` lève une exception — ça protège aussi contre un futur rôle ajouté à `RoleEnum` sans qu'on pense à mettre à jour cette méthode.

---

## 4. Filtrage sécurisé : `array_key_exists()` vs `isset()`

Un piège classique en PHP : `isset($tableau['cle'])` renvoie **`false`** si la valeur associée à la clé est `null` — pas seulement si la clé n'existe pas. `array_key_exists()` ne regarde que l'existence de la clé, peu importe sa valeur.

**Pourquoi ça compte pour la sécurité**, dans `DeclarationRepository::applyPerimeterAndFilters()` :
```php
if (array_key_exists('pole', $criteria)) {
    $qb->andWhere('s.pole = :pole')->setParameter('pole', $criteria['pole']);
}
```
Un chef de pôle mal configuré (aucun service rattaché) a `$criteria['pole'] === null`. Avec `isset()`, cette condition serait `false` → le filtre ne serait **jamais appliqué** → la requête renverrait *toutes* les déclarations, tous pôles confondus. Faille de sécurité critique (accès hors périmètre). Avec `array_key_exists()`, le filtre s'applique quand même avec `pole = NULL`, ce qui en SQL ne matche jamais aucune ligne → liste vide. C'est un choix délibéré de **fail-closed** (échouer du côté restrictif) plutôt que fail-open (échouer du côté permissif) — un des principes de base de la sécurité applicative (OWASP).

---

## 5. PHP 8 — syntaxe et pièges

### Nullsafe operator `?->`
```php
$firstService?->getPole()
```
Si `$firstService` est `null`, PHP n'appelle rien et renvoie `null` directement — sans lever d'erreur fatale ("Call to a member function on null"). Équivalent condensé de :
```php
$firstService !== null ? $firstService->getPole() : null;
```

### Enums *backed* : `from()` vs `tryFrom()`
```php
enum StatutEnum: string { case Soumise = 'soumise'; ... }

StatutEnum::from('soumise');       // renvoie StatutEnum::Soumise
StatutEnum::from('nimportequoi');  // lève une \ValueError (fatal si pas capturée → 500)

StatutEnum::tryFrom('soumise');       // renvoie StatutEnum::Soumise
StatutEnum::tryFrom('nimportequoi');  // renvoie null, pas d'exception
```
**Règle pratique** : dès qu'une valeur vient de l'extérieur (query string, body JSON — donnée *non fiable*), utiliser `tryFrom()` + vérifier `null` explicitement pour renvoyer un 400 propre. `from()` n'est acceptable que sur une donnée déjà validée/interne. Exemple dans `DashboardController::stats()` / `DeclarationController::list()` :
```php
if ($statutValue = $query->get('statut')) {
    $statut = StatutEnum::tryFrom($statutValue);
    if (!$statut) {
        return $this->json(['error' => 'Statut invalide.'], JsonResponse::HTTP_BAD_REQUEST);
    }
    $filters['statut'] = $statut;
}
```
(remarque au passage : `$statutValue = $query->get('statut')` dans le `if` est une affectation, pas juste un test — ça évite d'appeler `$query->get('statut')` deux fois.)

### Les enums ne peuvent pas définir `__toString()`
Restriction du langage PHP : `enum X { public function __toString() {...} }` est une erreur fatale de compilation (rencontré sur `GraviteEnum`/`RoleEnum`/`StatutEnum`/`TypeEIEnum` pendant le développement). Utiliser une vraie méthode nommée à la place (`label()` dans ce projet).

### `Doctrine\Common\Collections\Collection::first()`
Renvoie **`false`** (pas `null`) quand la collection est vide — piège classique si on chaîne directement avec `?->` :
```php
$requester->getServices()->first()?->getPole(); // BUG si vide : false?->getPole() ne fonctionne pas comme prévu
$requester->getServices()->first() ?: null;      // sécurisé : convertit false en null d'abord
```

---

## 6. Doctrine / DQL

- **QueryBuilder** : construit une requête DQL (Doctrine Query Language, proche de SQL mais porte sur les entités/propriétés, pas les tables/colonnes) de façon programmatique et paramétrée — jamais de concaténation de chaînes (protection injection SQL, CDC §6.3).
- **`clone $qb`** pour dériver plusieurs requêtes d'une base commune sans les faire interférer entre elles — utilisé dans `DeclarationRepository::aggregate()` pour construire `parService`/`parType`/`parPeriode` à partir du même `$qb` filtré (périmètre + filtres appliqués une seule fois).
- **Limite de DQL standard** : pas de fonction portable pour tronquer une date à un mois (`DATE_TRUNC`/`YEAR()`/`MONTH()`) sans bundle tiers (`beberlei/doctrineextensions`, non installé ici). Solution retenue : ne récupérer que les dates (`SELECT d.dateConstat`, pas les entités complètes) et regrouper par mois côté PHP — compromis pragmatique plutôt que d'introduire du SQL natif (contraire à la contrainte CDC §6.3 "ORM Doctrine exclusivement").

---

## 7. Principe *blameless* — traduction technique

Le CDC pose une règle métier forte (§2.1, §4.6) : les statistiques du tableau de bord ne doivent **jamais** permettre de reconstituer un classement individuel des soignants. Ce n'est pas qu'une intention de design, elle est appliquée en dur dans le code :

```php
// StatsManager::aggregate()
if (array_key_exists('declarant', $criteria)) {
    throw new \RuntimeException('Les statistiques ne peuvent jamais être scopées à un déclarant individuel (règle blameless, CDC §2.1).');
}
```
`resolvePerimeterCriteria()` autorise légitimement un critère `declarant` pour `search()` (un soignant voit sa propre liste), mais ce même critère ne doit **jamais** atteindre l'agrégation du tableau de bord — d'où ce garde-fou explicite, redondant avec le fait que la route est déjà protégée par `#[IsGranted('ROLE_CADRE')]` (défense en profondeur : ne pas compter sur une seule couche de protection).

---

## 8. Sécurité — génération PDF (dompdf)

`dompdf` exécute du code côté **serveur** pour transformer du HTML/CSS en PDF — contrairement à un navigateur qui s'exécute chez le client. Deux risques classiques :
- **SSRF** (Server-Side Request Forgery) : si dompdf peut charger des ressources distantes (`isRemoteEnabled: true`) et que l'URL est influençable par un utilisateur, le serveur peut être forcé de faire des requêtes vers un réseau interne ou un service de métadonnées cloud.
- **LFI** (Local File Inclusion) : lecture de fichiers locaux hors du dossier prévu via un chemin mal maîtrisé.

Mitigations appliquées dans `DeclarationController::exportPdf()` :
```php
$options->set('isRemoteEnabled', false);                  // aucune requête distante possible
$options->set('chroot', $projectDir . '/public');          // accès fichiers restreint à public/
```
Et le chemin utilisé dans le template (`absolute_path`) est une constante serveur (`kernel.project_dir`), jamais une donnée venant de la requête utilisateur — sinon ces deux protections perdraient leur intérêt.

---

## 9. Git — commits et amend

- `git commit -m "titre" -m "corps"` : chaque `-m` devient un paragraphe séparé. Pour un corps multi-lignes propre, un heredoc est plus lisible :
  ```bash
  git commit -m "$(cat <<'EOF'
  titre du commit

  - point 1
  - point 2
  EOF
  )"
  ```
- `git commit --amend` : modifie le dernier commit local. **Sans danger tant que le commit n'a pas été poussé** (`git push`) — après un push, amender forcerait un `push --force` qui peut écraser le travail de quelqu'un d'autre.

---

## 10. Points bloquants rencontrés — et comment les diagnostiquer

De vrais incidents rencontrés pendant le développement, avec la méthode de diagnostic — c'est ce genre de récit qui montre une vraie maîtrise à l'oral (pas juste "ça a marché du premier coup").

### Migration appliquée sur `dev` mais pas sur `app_test`
**Symptôme** : `LogController::list()` renvoyait un 500 en test alors que tout fonctionnait en dev.
**Diagnostic** : lecture de `var/log/test.log` → `Doctrine\DBAL\Exception\TableNotFoundException: relation "log_entry" does not exist`. La migration avait été jouée sur la base `dev` (`php bin/console doctrine:migrations:migrate`) mais jamais sur `app_test`, une base Postgres séparée (suffixe `_test`, cf. `.env.test`).
**Leçon** : chaque environnement Symfony a sa propre base — une migration doit être rejouée sur chacune (`--env=test`). Un test qui passe en local juste après une migration "dev" peut donc être un faux négatif si la base de test n'a pas été mise à jour.

### `KernelBrowser::loginUser()` appelé deux fois dans le même test
**Symptôme** : un test qui authentifie un acteur A (créer un compte), puis authentifie un acteur B pour vérifier le résultat, recevait un 401 `"JWT Token not found"` sur la deuxième requête — alors que la même méthode marche très bien utilisée une seule fois par test partout ailleurs dans le projet.
**Diagnostic** : `fwrite(STDERR, ...)` temporaire pour dumper la réponse brute (plus fiable que déduire depuis les assertions PHPUnit) → a révélé que la 2e requête n'était en réalité pas authentifiée du tout. Sur un firewall **stateless** (JWT), le mécanisme de bypass de test ne se comporte pas de façon fiable quand on change d'utilisateur authentifié en plein milieu d'un test — contrairement à un firewall à session classique.
**Leçon / contournement** : sur un firewall stateless, éviter de changer d'acteur authentifié dans un même test. Si le but est juste de vérifier qu'une action a eu un effet (ici : qu'une `LogEntry` a bien été créée), interroger directement `$this->entityManager` plutôt que de refaire un aller-retour HTTP avec un autre utilisateur.

### Divergence git après un commit fait depuis un mauvais point de l'historique
**Symptôme** : `git push` rejeté (`non-fast-forward`) alors qu'aucune autre personne ne travaille sur la branche.
**Diagnostic** : `git fetch` + `git log --oneline --all --graph` → deux commits parents du même ancêtre commun (`git merge-base`), l'un local, l'un distant : un commit avait été fait localement depuis un état où le dernier commit distant n'était pas encore intégré. `git diff <ancêtre-distant> <commit-local> --stat` a confirmé que le commit local contenait déjà tout le contenu du commit distant (rien n'était perdu) — juste deux lignes d'historique parallèles.
**Résolution** : `git merge origin/<branche>` ; conflits résolus en gardant systématiquement la version locale (`git checkout --ours <fichier>`) après avoir vérifié qu'elle était bien un sur-ensemble strict de la version distante.
**Leçon** : avant de résoudre un conflit à l'aveugle, toujours vérifier lequel des deux côtés est réellement le plus à jour — ne jamais supposer, comparer.

### `LIKE` sur une colonne Postgres de type `json`
**Symptôme** : en activant les derniers `markTestIncomplete` de `DeclarationControllerTest` (soumission d'une déclaration), 500 avec `SQLSTATE[42883]: Undefined function: operator does not exist: json ~~ unknown`.
**Diagnostic** : `UserRepository::findCadresByService()`/`findByRole()` filtraient avec `u.roles LIKE :role` en DQL — `roles` est stocké en colonne Postgres native `json` (`#[ORM\Column(type: 'json')]`), et Postgres n'a **aucun opérateur `LIKE`/`~~` pour le type `json`** (contrairement à `text`/`varchar`). Doctrine DQL ne propose pas de fonction `CAST()` portable pour contourner ça. Ce bug existait depuis le début mais n'avait **jamais été détecté** : tous les tests qui passaient par ce code (`DeclarationManagerTest`, `NotificationManagerTest`...) le faisaient avec des doublures (Mock/Fake), donc aucune vraie requête SQL n'avait jamais été exécutée contre Postgres sur ce chemin avant l'activation de ce test fonctionnel.
**Résolution** : remplacé le DQL par du SQL natif via `$this->getEntityManager()->getConnection()->fetchFirstColumn(...)` avec un `CAST(roles AS TEXT) LIKE ...` explicite, puis re-résolution des entités via `findBy(['id' => $ids])`.
**Leçon** : les tests unitaires avec doublures et les tests d'intégration/fonctionnels ne prouvent pas la même chose. Un Mock/Fake vérifie *ta* logique métier ; seul un test qui touche la vraie base peut révéler qu'une requête est syntaxiquement invalide pour le moteur SQL réellement utilisé. C'est exactement pour ça que la pyramide de tests a plusieurs étages — aucun niveau ne remplace les autres.

---

## 11. EventSubscribers — pattern Observateur

**Le problème** : le flux d'authentification (`json_login` + handlers LexikJWTBundle, cf. `security.yaml`) est entièrement géré par Symfony/le bundle — aucun contrôleur à nous dans ce flux où appeler `LogManager::log()` pour tracer les connexions (CDC §6.3).

**La solution : le pattern Observateur.** Plutôt qu'un appel de méthode direct (`$this->notificationManager->notifySubmission(...)`, où l'appelant connaît explicitement l'appelé), Symfony dispatche des **événements** — de simples objets — via un `EventDispatcher` central. N'importe quel code, écrit n'importe où, peut s'abonner sans que le code d'origine ait besoin de le connaître. Découplage total, même logique que les Voters (`supports()`/`voteOnAttribute()`) mais pour "il vient de se passer X" plutôt que "qui a le droit de faire X".

```php
class LoginLogSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
            LoginFailureEvent::class => 'onLoginFailure',
        ];
    }
    // ...
}
```
`getSubscribedEvents()` est **statique** (Symfony l'appelle avant même d'instancier la classe, juste pour savoir à quoi s'abonner) et associe une classe d'événement à un nom de méthode. Aucune config YAML : comme un Voter, la classe est auto-découverte et auto-taguée `kernel.event_subscriber` simplement parce qu'elle implémente `EventSubscriberInterface`.

**Piège de typage rencontré** : `LoginSuccessEvent::getUser()` est typé `UserInterface` — le contrat générique de Symfony Security, pas notre entité concrète `App\Entity\User`. `UserInterface` ne définit que `getUserIdentifier()`/`getRoles()` ; pas de `getEmail()`. Il faut donc vérifier le type concret avant d'appeler une méthode propre à notre entité :
```php
public function onLoginSuccess(LoginSuccessEvent $event): void
{
    $user = $event->getUser();

    if (!$user instanceof User) {
        return;
    }

    $this->logManager->log(LogTypeEnum::LoginSuccess, $user, sprintf('Connexion réussie : %s', $user->getEmail()));
}
```
C'est un principe général en PHP typé (et en POO en général) : un framework expose des **interfaces** (contrats larges, réutilisables par n'importe quelle application) alors que le code applicatif manipule des **implémentations concrètes** plus riches. Dès qu'on récupère un objet via un type d'interface générique fourni par une lib externe, si on a besoin d'une méthode qui n'existe que sur *notre* classe, un `instanceof` (ou équivalent) est nécessaire avant de l'appeler — sinon erreur statique (ou fatale à l'exécution si pas de vérification stricte des types).

**Autre piège, sur l'échec de connexion** : `LoginFailureEvent` n'a **pas** de `getUser()` — par construction, on ne sait pas qui a échoué à se connecter (protection énumération, même principe que le message d'erreur générique "Invalid credentials.", CDC §6.1). Pour tracer quand même la tentative, il faut relire le body de la requête (`$event->getRequest()->getContent()`), sans jamais résoudre vers un vrai compte — c'est justement pour ce cas que `LogEntry::user` est nullable.

---

## 12. Tests d'intégration — pourquoi `KernelTestCase` sur CE projet

**Rappel de la pyramide des tests (vocabulaire du cours)** : un test **unitaire** isole une classe, toutes ses dépendances remplacées par des doublures (Fixture/Stub/Mock/Fake). Un test **d'intégration** vérifie qu'une classe précise dialogue correctement avec **une** ressource technique réelle (souvent : un Repository ↔ une vraie base) — c'est le seul niveau où on ne double **rien**, exprès, puisque le but est justement de prouver que ça marche pour de vrai. Un test **fonctionnel** entre par le vrai point d'entrée applicatif (route HTTP) et laisse tourner toute la chaîne réelle.

**Le piège initial** : l'exemple du cours construit un `PDO` à la main (`new PDO('sqlite::memory:')`), sans aucun framework — logique, puisque PDO n'a aucune dépendance à un framework. J'ai voulu reproduire ça à l'identique pour un Repository Doctrine (`new EntityManager(...)` via `ORMSetup`/`DriverManager`, sans conteneur Symfony) — et ça a cassé, en cascade :

1. `PoleRepository` étend `ServiceEntityRepository`, qui exige un `ManagerRegistry` — contournable avec un simple stub PHPUnit (`getManagerForClass()` renvoie notre `EntityManager`).
2. DBAL 4 n'interprète plus une URL de connexion directement — il faut désormais un `DsnParser` explicite.
3. **Le mur final** : nos entités déclarent `#[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]`. Cette chaîne n'est **pas** un nom de classe PHP — c'est un identifiant de service du conteneur Symfony (vérifié dans `vendor/doctrine/doctrine-bundle/config/orm.php`, qui l'associe à la vraie classe `Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator`). Doctrine ORM seul, sans conteneur, fait juste `class_exists($nom) ? new $nom() : throw` — et `class_exists('doctrine.uuid_generator')` est forcément `false` (un point n'est même pas un caractère valide dans un nom de classe PHP).

**La leçon** : l'exemple du cours (PDO brut) est délibérément sans couplage framework — c'est ce qui rend le "à la main" trivial. Dès qu'on utilise un ORM intégré à un framework (Doctrine + Symfony), certains mécanismes de confort (ici : la génération d'UUID) sont volontairement câblés sur le conteneur. On aurait pu corriger ça en changeant l'attribut sur nos entités pour le vrai nom de classe (`UuidGenerator::class` fonctionne très bien sans conteneur, on l'a vérifié) — mais modifier 6 entités partagées par toute l'application juste pour un choix de méthodologie de test n'en valait pas la peine.

**La solution retenue** : `KernelTestCase` (pas `WebTestCase`). On démarre le vrai conteneur Symfony — juste assez pour que `doctrine.uuid_generator` se résolve normalement — et on récupère le Repository directement dedans :
```php
abstract class RepositoryIntegrationTestCase extends KernelTestCase
{
    protected EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
    }
}
```
**Ce que ça reste, et ce que ça n'est pas** : toujours un vrai test d'intégration au sens du cours — une seule ressource réelle (la base), zéro doublure dessus, le Repository est appelé **directement** (`static::getContainer()->get(PoleRepository::class)`), sans passer par une route ni un `$client->request(...)`. C'est précisément ce qui le distingue d'un test fonctionnel (`ApiTestCase`/`WebTestCase`) : pas de HTTP, pas de contrôleur, pas de Voter — juste "est-ce que `PoleRepository::findByUser()` dialogue correctement avec Postgres".

**Bonus** : en passant par la vraie connexion du conteneur, DAMADoctrineTestBundle continue de fonctionner automatiquement (transaction annulée en fin de test) — contrairement à une connexion construite entièrement à la main, qui l'aurait contournée silencieusement.

---

## 13. `User::$role` — d'un tableau JSON à une valeur unique (refactor assumé)

Question piège classique à l'oral : "pourquoi `roles` était un tableau JSON alors que le CDC dit qu'un compte n'a qu'un seul rôle métier (§3) ?" Bonne question à se préparer — et ici l'histoire complète (le "avant", le raisonnement, et le refactor final) est un excellent exemple de démarche à raconter à l'oral.

**La contrainte qui imposait le tableau à l'origine** : `User` implémente `Symfony\Component\Security\Core\User\UserInterface`, qui exige :
```php
public function getRoles(): array;
```
Le type de retour est fixé par le **framework**, pas par nous — Symfony Security doit rester générique et supporter des utilisateurs multi-rôles dans le cas général, même si telle ou telle application (comme SentiCare) n'en a pas besoin. Le squelette standard Symfony (`make:user`) mappe donc naturellement `roles` en tableau JSON stocké tel quel — ce qu'on avait suivi sans le retailler.

**Pourquoi "garder le tableau pour un futur multi-rôle" ne tenait pas** : toute la logique de périmètre (`DeclarationVoter`, `UserVoter`, `ServiceVoter`, `DeclarationManager::resolvePerimeterCriteria()`) suppose déjà un seul rôle vrai par compte (`if ChefPole -> elseif Cadre -> ...`, la première branche qui matche gagne). Un vrai passage au multi-rôle demanderait de toute façon de réécrire cette logique partout — le type de la colonne n'est pas ce qui coûte cher dans ce changement-là.

**Décision finale : refactor vers une valeur unique**, une fois le compromis bien compris :
```php
#[ORM\Column(enumType: RoleEnum::class)]
private ?RoleEnum $role = null;

public function getRoles(): array
{
    // Le contrat d'interface (array) est toujours respecté ici — construit à
    // la volée, jamais stocké tel quel.
    return array_unique([$this->role->value, 'ROLE_USER']);
}
```
**Le point clé qui a rendu ce refactor sûr** : `getRoles(): array` — la méthode que *tout le reste du code* appelle (les 3 Voters, `DeclarationManager`, `SecurityController::me()`, le payload JWT via LexikJWTBundle) — a gardé exactement la même signature et le même comportement en sortie. Seul le **stockage interne** a changé (`$role` unique au lieu de `$roles` tableau) ; aucun appelant de `getRoles()` n'a eu besoin d'être modifié. Seuls les endroits qui *construisaient* un `User` (`setRoles([...])` → `setRole(...)`) ont dû changer — une quinzaine d'endroits, tous mécaniques, principalement des fixtures de test.

**Bénéfice concret obtenu** : le bug `LIKE`/`json` du §10 devient **structurellement impossible** — `UserRepository::findByRole()`/`findCadresByService()` sont repassées à un DQL classique `->andWhere('u.role = :role')`, plus besoin du contournement `CAST(... AS TEXT) LIKE`.

**Migration** : `Version20260801043103` — ajoute `role` (nullable), migre les données existantes (`role = roles->>0`, l'opérateur Postgres qui extrait un élément de tableau JSON en texte), passe `role` en `NOT NULL`, supprime `roles`. Écrite à la main plutôt que de garder le DDL brut généré par `doctrine:migrations:diff` (qui aurait fait un `ADD role VARCHAR NOT NULL` sans étape intermédiaire nullable — invalide sur une table non vide).

**La bonne réponse à donner à l'oral**, maintenant : *"Le tableau venait du squelette standard Symfony (`UserInterface::getRoles(): array`), pas d'un vrai besoin métier — le CDC est clair sur un seul rôle par compte. On l'a d'abord gardé par défaut, puis retaillé une fois le compromis bien identifié : la méthode `getRoles()` respecte toujours le contrat d'interface, seul le stockage a changé, donc aucun impact sur l'authentification JWT ni sur la logique de périmètre existante."*

---

## 14. CORS et headers de sécurité HTTP (CDC §6.3)

### CORS — un mécanisme du navigateur, pas du serveur

Point à bien maîtriser à l'oral : CORS n'est **pas** une protection appliquée par Symfony contre des attaquants — c'est une règle que le **navigateur** applique côté client. Par défaut, un navigateur suit la *same-origin policy* : le JavaScript chargé depuis une page ne peut lire la réponse d'un `fetch`/`XHR` que si cette requête part vers la **même origine** que la page (origine = schéma + domaine + port, les trois doivent matcher).

**Pourquoi le besoin apparaît en dev et disparaît en prod** — c'est une question de topologie, pas de niveau de sécurité :
- **En dev** : le front (Vite) tourne sur `http://localhost:5173`, l'API Symfony sur `http://localhost:8000`. Même domaine, **port différent** → deux origines différentes pour le navigateur → toute requête du front vers l'API est *cross-origin*. Sans header `Access-Control-Allow-Origin` renvoyé par le serveur, le navigateur bloque la lecture de la réponse côté JS (et pour les requêtes avec `Authorization`/JSON, il envoie d'abord un `OPTIONS` de vérification — le *preflight* — qui doit lui-même être autorisé avant que la vraie requête ne parte).
- **En prod** : le CDC (§7.3) prévoit **un seul nginx** qui sert à la fois le build React statique et fait reverse-proxy vers php-fpm pour `/api/*`, **sous un seul domaine**. Le front et l'API sont donc à la même origine exacte → le navigateur n'applique même pas la vérification CORS, il n'y a structurellement rien à bloquer.

Implémenté avec `nelmio/cors-bundle` (`config/packages/nelmio_cors.yaml`), origines autorisées pilotées par la variable d'env `CORS_ALLOW_ORIGIN` (restreinte à `localhost`/`127.0.0.1` par défaut). La config reste active dans tous les environnements (elle est simplement sans effet en prod tant que l'architecture "un seul nginx, un seul domaine" est respectée) — si jamais front et API finissent sur deux domaines séparés, il suffit de surcharger `CORS_ALLOW_ORIGIN` dans `.env.prod.local`.

### Les 4 headers de sécurité exigés par le CDC §6.3

Implémentés via `nelmio/security-bundle` (`config/packages/nelmio_security.yaml`), un sujet indépendant de CORS :

| Header | Ce qu'il empêche | Configuration retenue |
|---|---|---|
| `X-Frame-Options` | *Clickjacking* — un autre site affiche SentiCare dans une `<iframe>` invisible pour piéger un clic utilisateur | `DENY` (aucun site, y compris SentiCare lui-même, ne peut se framer) |
| `X-Content-Type-Options` | *MIME sniffing* — le navigateur réinterprète un fichier avec un type différent de son `Content-Type` déclaré, ouvrant la porte à de l'exécution de contenu injecté | `nosniff` |
| `Content-Security-Policy` (CSP) | Chargement de ressources (script/style/image/frame) non prévues, notamment en cas d'injection | `default-src 'none'; frame-ancestors 'none'` — politique la plus stricte possible, cohérente avec une API qui ne sert **que du JSON**, jamais de HTML/JS exécutable |
| `Strict-Transport-Security` (HSTS) | Un attaquant qui intercepte la première connexion HTTP avant la redirection HTTPS (*SSL stripping*) | Activé **uniquement `when@prod`** (`forced_ssl`, `max-age` 1 an, `includeSubDomains`) — en dev/test la connexion est en HTTP local, le header y serait sans effet réel |

Vérifié manuellement (`curl -i`) que les 4 headers sont bien présents sur une réponse réelle. Le nonce CSP visible en dev sur `script-src`/`style-src` ne vient pas de notre config — c'est la Web Debug Toolbar (profiler Symfony) qui s'intègre automatiquement à `nelmio/security-bundle` pour pouvoir afficher son propre JS/CSS inline ; il disparaît en prod car le profiler y est désactivé.

### Audit RGPD des champs texte libre

Pas un correctif de code : relecture des 5 champs texte libre de `Declaration` (`description`, `lieuDifferentDetail`, `consequencesAutresDetail`, `mesuresImmediatesPatientDetail`, `autresMesures`) pour vérifier qu'aucun n'est structurellement dédié à une donnée patient nominative — ce qui est le cas. Rien n'empêche techniquement un soignant d'y écrire un nom par réflexe, mais c'est un risque de saisie/process (à couvrir par un texte d'aide dans le futur formulaire React), pas une faille technique corrigeable côté API — filtrer des noms propres de façon fiable côté serveur produirait surtout des faux positifs.

**La bonne réponse à donner à l'oral** : *"CORS et les headers de sécurité sont deux sujets différents qu'on regroupe souvent à tort. CORS gère qui a le droit d'appeler l'API depuis un navigateur — nécessaire en dev parce que front et API sont sur deux ports différents, transparent en prod parce que l'architecture (un seul nginx, un seul domaine) place tout à la même origine. Les headers (CSP, X-Frame-Options, X-Content-Type-Options, HSTS) protègent contre des classes d'attaques différentes (clickjacking, MIME sniffing, injection de ressources, interception réseau) et sont actifs indépendamment de la topologie de déploiement, avec HSTS activé seulement en prod puisqu'il n'a de sens qu'en HTTPS."*

---

## 15. `kernel.exception` et l'ordre des listeners — garantir du JSON même en erreur

Trouvé en testant volontairement une route inexistante avant de passer au front, avec différents headers `Accept` :

```
Accept: application/json  → 404 en JSON
Accept: */*               → 404 en HTML   ← le vrai défaut de fetch() !
```

`fetch()` dans un navigateur envoie `Accept: */*` par défaut si le code front ne force pas explicitement `application/json`. Sans correctif, une route mal tapée, une méthode HTTP invalide, ou n'importe quel `createNotFoundException()` (utilisé dans quasiment tous les Controllers pour un ID absent) renvoyait une page HTML au lieu de JSON — le front aurait fait `response.json()` sur du HTML et planté avec une erreur de parsing au lieu du vrai message d'erreur.

**Pourquoi ça ne cassait pas les 401/403 existants**, alors qu'eux étaient déjà en JSON avant même ce correctif : Symfony dispatche l'événement `kernel.exception` à plusieurs listeners, **du plus prioritaire au moins prioritaire**. Dès qu'un listener appelle `$event->setResponse(...)`, ça déclenche automatiquement `stopPropagation()` — les listeners suivants (moins prioritaires) ne sont jamais appelés. Trois acteurs sur cet événement, dans l'ordre où ils s'exécutent :

| Listener | Priorité | Rôle |
|---|---|---|
| `Security\Http\Firewall\ExceptionListener` | `1` | Transforme `AuthenticationException`/`AccessDeniedException` en 401/403 JSON — déjà correct avant ce correctif |
| `App\EventSubscriber\ApiExceptionSubscriber` (ajouté) | `-10` | Filet de sécurité : si rien n'a encore répondu et que la route est `/api/*`, force du JSON |
| `Symfony\HttpKernel\EventListener\ErrorListener` (par défaut) | `-128` | Rendu HTML par défaut (négocié sur `Accept`) — c'est lui qui produisait le bug |

Le nouveau listener est volontairement calé **entre les deux** : après Security (pour ne jamais lui voler la main sur un 401/403, doublement garanti par un `if ($event->hasResponse()) return;` explicite dans le code), avant l'`ErrorListener` par défaut (pour l'empêcher de rendre du HTML).

**Le point sécurité en plus** : pour une exception HTTP volontaire (`NotFoundHttpException`, `BadRequestHttpException`...), le message réel est renvoyé — il est fait pour être vu par le client. Pour une exception généraliste (un vrai bug non prévu, code 500), le message n'est renvoyé **que si `kernel.debug` est vrai** (dev/test) ; en prod, un message générique fixe, pour ne jamais fuiter une trace, une requête SQL ou un chemin serveur dans la réponse HTTP.

**La bonne réponse à donner à l'oral** : *"J'ai testé le comportement d'erreur avec le header Accept réel que fetch() envoie par défaut (`*/*`), pas celui que curl envoie par défaut — et j'ai trouvé que Symfony retombait sur du HTML. J'ai ajouté un EventSubscriber sur kernel.exception, calé par la priorité entre le listener de sécurité (qui gère déjà les 401/403 en JSON) et le listener d'erreur par défaut de Symfony, pour garantir du JSON systématique sur /api/*, avec un message générique en prod pour les erreurs non prévues afin de ne rien fuiter."*

---

## 16. Les fichiers `.env` — hiérarchie, secrets, et un écart corrigé

Question piège classique : *« pourquoi avez-vous des secrets dans des fichiers
`.env` committés sur git ? »* La réponse a évolué au cours du projet — et c'est
l'évolution elle-même qui est intéressante à raconter.

### 16.1 La hiérarchie de chargement

Le dernier chargé l'emporte :

```
.env  →  .env.local  →  .env.$APP_ENV  →  .env.$APP_ENV.local
```

| Fichier | Versionné | Contient |
|---|:---:|---|
| `.env` | ✅ **oui** | valeurs par défaut non secrètes, et la liste des variables attendues |
| `.env.local` | ❌ non | surcharges propres à la machine **et les secrets** |
| `.env.dev` / `.env.test` | ✅ oui | valeurs par défaut d'un environnement |
| `.env.dev.local` / `.env.test.local` | ❌ non | surcharges locales d'un environnement |

Le `.gitignore` n'exclut que les fichiers `.local` :

```
/.env.local
/.env.local.php
/.env.*.local
```

**Versionner `.env` n'est pas une mauvaise pratique** : c'est la convention
Symfony, et c'est de la documentation exécutable. Qui clone le dépôt voit
immédiatement quelles variables l'application attend, et l'app démarre avec des
valeurs inoffensives (`!ChangeMe!`, `null://null`). La faute serait d'y mettre
un secret.

### 16.2 Deux pièges de l'ordre de chargement

**`.env.local` n'est JAMAIS chargé quand `APP_ENV=test`.** Vérifiable :

```
$ php bin/console debug:dotenv --env=test
 * ⨯ .env.local.php
 * ✓ .env.test.local
 * ✓ .env.test
 * ✓ .env
```

Volontaire : un test doit être reproductible indépendamment des réglages
personnels de qui le lance. Conséquence — tout ce dont les tests ont besoin doit
être répété dans `.env.test.local`.

**`.env.$APP_ENV` est chargé APRÈS `.env.local`.** Une valeur déclarée dans
`.env.dev` écrase donc celle de `.env.local`. Il ne suffit pas d'y vider un
secret : il faut l'en **retirer**.

Ces deux pièges ont chacun cassé les tests pendant la correction décrite plus
bas. Ils ne sont pas théoriques.

### 16.3 L'argument qui justifiait de committer — et il est solide

La distinction classique oppose **secret de convenance d'équipe pour le dev
local** (inoffensif) et **secret de production** (jamais committé). Deux
raisonnements la soutenaient ici :

**`APP_SECRET` de dev** — sert à signer CSRF et cookies. Le committer donne à
toute l'équipe le même secret après un `git clone`, sans étape de génération.
Sans risque tant qu'il ne protège que des données jetables. Symfony fait
d'ailleurs pareil : `symfony new` génère un `APP_SECRET` en clair.

**`JWT_PASSPHRASE`** — inerte seule. La vraie clé cryptographique est
`config/jwt/private.pem`, et lui **n'a jamais été versionné**
(`/config/jwt/*.pem` dans `.gitignore`). Une passphrase sans la clé qu'elle
protège ne permet de forger aucun jeton.

Cet argument reste valable, et beaucoup de projets s'y tiennent.

### 16.4 Pourquoi on a quand même déplacé les secrets (2026-08-11)

Une raison, décisive, et elle n'est pas technique :

> **Le CDC §6.3 exige : « Variables sensibles stockées dans `.env` non
> versionné ». Le projet ne respectait pas sa propre spécification.**

Peu importe que le risque réel soit faible : un jury qui ouvre le dépôt et le
cahier des charges côte à côte relève l'écart. Mieux vaut l'avoir vu et corrigé
que d'avoir à l'expliquer.

**Correction appliquée :**

1. `JWT_PASSPHRASE` et `APP_SECRET` déplacés vers `.env.local` ;
   `APP_SECRET` **retiré** de `.env.dev` — pas seulement vidé (cf. §16.2).
2. **Passphrase et paire de clés régénérées.** Un secret qui a fui est un secret
   mort, même si son exploitation était improbable.
3. `.env.test.local` complété pour l'environnement de test.
4. 146 tests re-vérifiés verts, émission de jeton re-vérifiée.

**Assumé et documenté :** les anciennes valeurs subsistent dans l'historique git.
Les purger (`git filter-repo`) réécrirait tout l'historique et casserait le dépôt
distant. Les secrets ayant été révoqués et les clés privées n'ayant jamais fuité,
le rapport bénéfice/risque ne le justifie pas.

**Exception conservée volontairement :** `.env.test` contient toujours
`APP_SECRET='$ecretf0rt3st'`. Valeur manifestement factice, nécessaire pour que
les tests s'exécutent à l'identique partout — y compris en intégration continue,
qui n'a pas accès aux fichiers `.local`.

**Le contre-exemple, déjà correct dès le départ :** le mot de passe PostgreSQL
local vit dans `.env.local`, et `.env` ne porte qu'un placeholder `!ChangeMe!`
jamais fonctionnel. La bonne pratique était déjà appliquée là où la valeur
dépendait de la machine.

### 16.5 Et en production

Les fichiers `.env` ne sont pas la bonne réponse en production. Deux voies
standard :

**Variables d'environnement réelles**, injectées par le serveur ou le service de
déploiement. Elles l'emportent toujours sur les fichiers `.env`. C'est la voie
retenue pour OVHcloud.

**Symfony Secrets** (`bin/console secrets:set`) — un coffre chiffré dont le
contenu peut être versionné, la clé de déchiffrement restant hors du dépôt.

Pour l'intégration continue, la passphrase devient un **secret GitHub Actions** :
le workflow reconstruit `.env.test.local` et génère une paire de clés dédiée.
Aucun secret ne transite par le dépôt.

### 16.6 La réponse à donner à l'oral

> « Symfony distingue les fichiers versionnés — `.env`, `.env.dev` — qui portent
> des valeurs par défaut non secrètes, et les fichiers `.local`, gitignorés, qui
> portent les secrets. Au départ j'avais committé `APP_SECRET` et
> `JWT_PASSPHRASE` en me disant que c'étaient des secrets de dev, inoffensifs —
> la passphrase étant même inerte sans la clé privée, elle jamais versionnée.
> Mais mon propre cahier des charges §6.3 dit l'inverse. Je les ai donc déplacés
> dans `.env.local`, régénéré la passphrase et les clés, et documenté ce qui
> reste dans l'historique git. En production ce serait de vraies variables
> d'environnement, ou Symfony Secrets. »

---

## 17. Comment démarrent les tests — la chaîne complète, fichier par fichier

Question à savoir dérouler sans hésiter : "que se passe-t-il exactement entre `php bin/phpunit` et le premier test qui s'exécute ?"

```
phpunit.dist.xml
  → <server name="APP_ENV" value="test" force="true" />  : force l'environnement AVANT tout
  → <extensions><bootstrap class="DAMA\...\PHPUnitExtension"/></extensions> : enregistre DAMA
  → bootstrap="tests/bootstrap.php" : fichier exécuté une seule fois, avant tous les tests
        ↓
tests/bootstrap.php
  → require vendor/autoload.php
  → (new Dotenv())->bootEnv('.env')  — lit la cascade .env selon APP_ENV déjà forcé à "test"
        ↓
.env → .env.local (SAUTÉ en env test) → .env.test → .env.test.local
  → toutes les variables d'env posées, dont DATABASE_URL
        ↓
Un test hérite de tests/Functional/ApiTestCase.php (WebTestCase)
  → setUp() appelle static::createClient() → C'EST ICI que le vrai kernel Symfony démarre
        ↓
config/packages/doctrine.yaml, bloc when@test
  → dbname_suffix: '_test...' — rajoute "_test" au nom de la base, quoi que dise DATABASE_URL
  → connexion réelle sur app_test, jamais sur app (la base de dev)
        ↓
config/bundles.php → DAMADoctrineTestBundle::class => ['test' => true]
  → transaction ouverte avant le test, ROLLBACK automatique après — quel que soit le résultat
```

Le point à ne pas rater : **rien dans cette chaîne ne crée la base ni n'exécute les migrations**. C'est un prérequis manuel, à faire une fois (et à refaire à chaque nouvelle migration) :
```bash
php bin/console doctrine:database:create --env=test
php bin/console doctrine:migrations:migrate --env=test
```
Si cette étape n'a jamais été faite, `createClient()` plante à la connexion — pas d'auto-création silencieuse.

### Piège rencontré : "je ne vois pas `app_test`" alors qu'elle existe

Vérifié en ligne de commande (`psql -l`, `doctrine:migrations:status --env=test`) : la base existait bien, migrations à jour. Le souci n'était pas côté Postgres/Doctrine mais côté **outil client** (DBeaver/pgAdmin) : sa liste de bases est chargée une fois à la connexion et ne se rafraîchit pas toute seule quand une base est créée après coup. Un simple **Refresh** sur la connexion (clic droit → Refresh / F5 sous DBeaver, clic droit sur *Databases* → Refresh sous pgAdmin) suffit. Deuxième cause possible si le refresh ne suffit pas : un filtre de bases actif sur la connexion (DBeaver : onglet PostgreSQL des propriétés de connexion, case *"Show all databases"*).

**Leçon** : avant de conclure "Doctrine n'a pas créé la base", vérifier côté serveur directement (`psql -l` ou `doctrine:migrations:status --env=test`) plutôt que de se fier à l'affichage d'un client graphique — qui a son propre cache, indépendant de l'état réel du serveur.

**La bonne réponse à donner à l'oral** : *"PHPUnit force l'environnement `test` avant même de lire mon code, ce qui fait charger `.env.test`/`.env.test.local` et fait basculer Doctrine sur une base suffixée `_test`, complètement séparée de la base de dev. Cette base n'est pas créée automatiquement — c'est une commande à lancer une fois (`doctrine:database:create` + `migrations:migrate --env=test`). Ensuite, DAMADoctrineTestBundle encadre chaque test d'une transaction annulée automatiquement, donc aucun test ne pollue le suivant."*

---

## Récap express pour l'oral

| Question probable | Réponse courte |
|---|---|
| Pourquoi séparer Controller/Manager/Repository ? | Testabilité, responsabilité unique, réutilisation (ex: `resolvePerimeterCriteria()` partagé) |
| Pourquoi JWT plutôt que sessions ? | Stateless, adapté à une architecture découplée SPA + API |
| Pourquoi Argon2id ? | Recommandation ANSSI, résistant au bruteforce/GPU |
| Pourquoi UUID plutôt qu'un ID auto-incrémenté ? | Anti-énumération des ressources (CDC §6.2) |
| Qu'est-ce qu'un Voter Symfony ? | Autorisation sur un couple (attribut, sujet), pattern Template Method (`supports()`/`voteOnAttribute()`) |
| Pourquoi pas de Voter pour le dashboard ? | Pas de sujet unique — c'est toujours "mon propre périmètre", résolu par rôle dans le Manager |
| `isset()` vs `array_key_exists()` ? | `isset()` est `false` si la valeur est `null` — piège de sécurité si une valeur `null` légitime doit quand même déclencher un filtre restrictif |
| `from()` vs `tryFrom()` sur un enum ? | `from()` lève une exception sur valeur invalide (fatal si pas capturé), `tryFrom()` renvoie `null` — à utiliser sur toute donnée venant de l'extérieur |
| Comment le principe blameless est-il appliqué techniquement ? | Agrégation uniquement par service/type/période, `throw` explicite si le périmètre résolu contient un `declarant` |
| Qu'est-ce qu'un EventSubscriber ? | Pattern Observateur — s'abonne à un événement dispatché ailleurs (`getSubscribedEvents()` statique), découplage total, auto-enregistré comme un Voter |
| Pourquoi `instanceof User` avant `getEmail()` sur `LoginSuccessEvent::getUser()` ? | `getUser()` est typé `UserInterface` (contrat générique Symfony), pas notre entité concrète — `getEmail()` n'existe que sur `App\Entity\User` |
| Différence test unitaire / intégration / fonctionnel ? | Unitaire : classe isolée, tout doublé. Intégration : une ressource réelle précise (Repository ↔ BDD), zéro doublure dessus. Fonctionnel : vrai point d'entrée (route HTTP), toute la chaîne réelle |
| Pourquoi `KernelTestCase` et pas un `EntityManager` construit à la main pour les tests d'intégration ? | Nos entités utilisent `doctrine.uuid_generator`, un identifiant de service Symfony (pas un nom de classe) — seul le conteneur sait le résoudre |
| `KernelTestCase` vs `WebTestCase` ? | Les deux démarrent le conteneur ; seul `WebTestCase` ajoute un client HTTP simulé. `KernelTestCase` sert à appeler un service (Repository) directement, sans route ni contrôleur |
| Pourquoi `User` stocke un seul `RoleEnum` alors que `getRoles()` renvoie un tableau ? | `UserInterface::getRoles(): array` est un contrat Symfony, pas notre règle métier (CDC §3 : un seul rôle/compte) — `getRoles()` construit le tableau à la volée, refactor sûr car aucun appelant n'a eu à changer |
| Pourquoi CORS est nécessaire en dev mais pas en prod ? | En dev, front (`:5173`) et API (`:8000`) sont deux ports = deux origines différentes pour le navigateur. En prod, un seul nginx sert les deux sous un seul domaine (CDC §7.3) → même origine → pas de vérification CORS déclenchée |
| CSP `default-src 'none'` — pourquoi si strict ? | L'API ne sert que du JSON, jamais de HTML/JS à exécuter — la politique la plus restrictive possible ne casse rien et réduit la surface d'attaque au maximum |
| Pourquoi HSTS seulement `when@prod` ? | HSTS force le HTTPS ; en dev/test la connexion est en HTTP local, le header y serait sans effet et gênant |
| Comment garantir du JSON même sur une erreur non prévue ? | `EventSubscriber` sur `kernel.exception`, priorité calée entre le listener Security (401/403 déjà en JSON) et l'`ErrorListener` par défaut de Symfony (HTML) — `setResponse()` stoppe la propagation automatiquement |
| Pourquoi `Accept: */*` change tout ici ? | C'est le header réel envoyé par `fetch()` par défaut, pas `application/json` — Symfony négocie le format de la page d'erreur sur ce header |
| Vos secrets sont-ils versionnés ? | Non, plus depuis le 11/08. Ils l'étaient — argument : secrets de dev, passphrase inerte sans la clé privée jamais versionnée. Mais le CDC §6.3 exige l'inverse : déplacés dans `.env.local`, passphrase et clés régénérées. Historique git non purgé, décision documentée (§16.4) |
| Pourquoi `.env.local` ne compte pas en environnement `test` ? | Pour que les tests soient reproductibles indépendamment des réglages locaux du développeur |
| Qui crée la base `app_test` et ses tables ? | Personne automatiquement — commande manuelle `doctrine:database:create --env=test` + `doctrine:migrations:migrate --env=test`, à refaire à chaque nouvelle migration |
| Pourquoi Doctrine se connecte à `app_test` et pas `app` en test ? | `config/packages/doctrine.yaml`, bloc `when@test` : `dbname_suffix: '_test...'` rajoute le suffixe quoi que dise `DATABASE_URL` |
| Qui garantit qu'un test ne pollue pas le suivant ? | `DAMADoctrineTestBundle` — transaction ouverte avant chaque test, `ROLLBACK` automatique après, quel que soit le résultat |

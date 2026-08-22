# SentiCare API — Feuille de route de développement

Document de traçabilité à cocher au fur et à mesure. Chaque bloc = un cycle TDD :
écrire/relire le test → rouge → implémenter → vert.

Lancer la suite : `php bin/phpunit` (ou `--testdox` pour la vue lisible, `--filter=NomDeClasse` pour cibler).

Légende : `[x]` fait · `[ ]` à faire · `[b]` bloqué (précisé en commentaire)

---

## 0. Authentification — CDC §6.1 ✅ TERMINÉ

- [x] JWT (LexikJWTBundle) + refresh token rotatif (GesdinetJWTRefreshTokenBundle)
- [x] Hachage Argon2id
- [x] Login throttling (5 tentatives/min)
- [x] Routes `/api/login_check`, `/api/token/refresh`, `/api/me`, `/api/logout`
- [x] Testé manuellement de bout en bout (voir `docs/AUTHENTICATION.md`)
- [x] `SecurityControllerTest` (fonctionnel, `tests/Functional/Controller/`) —
  ✅ 7/7 verts : login (token + refresh_token émis), message générique
  identique sur mauvais mot de passe ET email inconnu (anti-énumération),
  `/api/me` (401 sans token, identité+rôles avec), `/api/token/refresh`
  (nouveau token + rotation du refresh_token), et surtout un refresh token
  déjà utilisé ne peut pas être rejoué (`single_use: true`, vrai test de
  sécurité, pas juste de la couverture)

## 1. Entités & enums conformes CDC ✅ TERMINÉ

- [x] `User`, `Declaration` en UUID v4 (CDC §6.2)
- [x] `RoleEnum`, `GraviteEnum`, `StatutEnum`, `TypeEIEnum` conformes CDC §3/§4

---

## 2. Structure clinique — Pôles (CDC UC-12, ROLE_ADMIN) / Services (CDC UC-13, partagé Admin+ChefPole)

> ⚠️ Changement de périmètre (2026-07-17) : à l'origine la gestion des services
> était réservée à ROLE_ADMIN comme les pôles. Le CDC a été corrigé : le chef
> de pôle gère désormais les services de **son propre pôle** (UC-13), l'admin
> garde une supervision globale (tous pôles). Les pôles eux-mêmes restent
> 100% admin. Voir `SentiCare_CDC_v4.3.md` §3/§4.1/US-1.2/US-1.3.

### PoleManager (`src/Service/PoleManager.php`) ✅ TERMINÉ
- [x] `create(nom, createdBy): Pole`
- [x] `update(pole, nom): Pole`
- [x] `deactivate(pole): void` — champ `isActive` ajouté (migration `Version20260715032521` + `Version20260715032619`)

### PoleController (`src/Controller/PoleController.php`) ✅ TERMINÉ
- [x] `list()` — GET /api/admin/poles
- [x] `show()` — GET /api/admin/poles/{id}
- [x] `create()` — POST /api/admin/poles
- [x] `update()` — PATCH /api/admin/poles/{id}
- [x] `deactivate()` — PATCH /api/admin/poles/{id}/deactivate

### ServiceVoter (`src/Security/Voter/ServiceVoter.php`) ✅ TERMINÉ
- [x] `canCreate()` — admin → tout pôle ; chef de pôle → uniquement son propre pôle (sujet = `Pole` cible, connu à l'avance contrairement à `UserVoter::CREATE`)
- [x] `canManage()` — VIEW/EDIT/DEACTIVATE, même règle de périmètre
- [x] `poleBelongsToChefPole()` — helper (même convention que `UserVoter::sharePole()`)

### ServiceManager (`src/Service/ServiceManager.php`) ✅ TERMINÉ
- [x] `create(nom, pole, createdBy): Service`
- [x] `update(service, nom, pole?): Service`
- [x] `deactivate(service): void`

### ServiceController (`src/Controller/ServiceController.php`) ✅ TERMINÉ — route déplacée `/api/admin/services` → `/api/services`
- [x] `list()` / `show()` / `create()` / `update()` / `deactivate()` — tous câblés sur `ServiceVoter`
- Pas de `#[IsGranted]` de classe (même raison que `UserController` : ROLE_ADMIN orthogonal à la hiérarchie, "chef de pôle OU admin" pas exprimable avec un seul rôle)

### Tests
- [x] `PoleControllerTest` — bornes d'accès (auth, ROLE_ADMIN) ✅ vertes
- [x] `ServiceVoterTest` — ✅ 8/8 verts
- [x] `ServiceControllerTest` — ✅ 7/7 verts (`markTestIncomplete` tous activés — les commentaires du fichier de test étaient périmés, `list()`/`create()` étaient en réalité déjà câblés sur `ServiceVoter`)
- [x] `PoleRepositoryTest` (intégration, `tests/Integration/`) — ✅ 3/3 verts,
  premier test d'intégration du projet (cf. `docs/REVISION_ORAL.md` §12) :
  `KernelTestCase` (pas de client HTTP), Repository récupéré directement
  depuis le conteneur, vraie base `app_test`
- [x] `PoleManagerTest` (unitaire, `tests/Unit/Service/`) — ✅ 3/3 verts.
  `PoleRepository` inutilisé par `PoleManager` (stub muet suffit) ;
  `EntityManagerInterface`/`LogManager` doublés en **Mock** (on vérifie
  qu'ils sont appelés correctement, pas juste une valeur de retour — voir
  `docs/REVISION_ORAL.md` §12)
- [x] `ServiceManagerTest` (unitaire, `tests/Unit/Service/`) — ✅ 4/4 verts,
  même constat que `PoleManagerTest` (`ServiceRepository` inutilisé, Mocks
  sur `EntityManagerInterface`/`LogManager`)
- [x] `ServiceRepositoryTest` (intégration, `tests/Integration/`) — ✅ 3/3
  verts : `findByPole()`/`findByName()`
- [x] `PoleControllerTest::testAdminCanCreatePole` — activé, vérifie 201 + `createdBy` en base ✅

---

## 3. Gestion des comptes utilisateurs — CDC UC-10, UC-12, US-1.2, US-1.3

> ⚠️ Refactor (2026-08-01) : `User::$roles` (tableau JSON, hérité du squelette
> standard Symfony) remplacé par `User::$role` (`RoleEnum` unique), conforme
> à la vraie règle métier (CDC §3 : un seul rôle par compte). `getRoles()`
> (imposée par `UserInterface`) continue de renvoyer un tableau, construit à
> la volée — donc **aucun impact** sur l'authentification JWT, les Voters ou
> `DeclarationManager` (tous appellent seulement `getRoles()`, jamais
> `setRoles()`). Élimine au passage le contournement SQL `CAST(...AS TEXT)
> LIKE` sur `UserRepository::findByRole()`/`findCadresByService()` (cf. bug
> §10 de `docs/REVISION_ORAL.md`) — retour à un DQL `u.role = :role` simple.
> Migration `Version20260801043103`. Détail complet : `docs/REVISION_ORAL.md` §13.

### UserVoter (`src/Security/Voter/UserVoter.php`) ✅ TERMINÉ
- [x] `canView()` — chef de pôle → comptes de son pôle ; admin → comptes chefs de pôle uniquement
- [x] `canCreate()` — ROLE_CHEF_POLE ou ROLE_ADMIN
- [x] `canEdit()` — même périmètre que `canDeactivate()`
- [x] `canDeactivate()` — admin → tout compte ; chef de pôle → son pôle uniquement
- [x] `canReactivate()` — même périmètre que `canDeactivate()` (CDC §5.1 : réversible)

### UserManager (`src/Service/UserManager.php`) ✅ TERMINÉ
- [x] `create()` — génère mdp provisoire, hash Argon2id, envoie l'email (US-1.2)
- [x] `update()` — nom/prénom/services uniquement (jamais email ni rôle)
- [x] `deactivate()`
- [x] `reactivate()` — symétrique de `deactivate()`
- [x] `changePassword()` — vérifie l'ancien mdp, hash Argon2id du nouveau (gestion mdp par l'utilisateur, hors CDC explicite mais nécessaire)
- [x] `findByPole()`
- [x] `findChefsPole()`

### UserController (`src/Controller/UserController.php`) ✅ TERMINÉ
- [x] `list()` / `create()` / `update()` / `deactivate()` / `reactivate()`
- [x] `changePassword()` — `PATCH /api/users/me/password`, self-scoped (pas de Voter, pas d'ambiguïté de périmètre)
- [x] Validation : chef de pôle ne peut créer que Soignant/Cadre ; admin que ChefPole (CDC §3)

### Tests
- [x] `DeclarationVoterTest` — ✅ 9/9 verts
- [x] `UserVoterTest` — ✅ 11/11 verts
- [x] `UserControllerTest` — ✅ 6/6 verts, tous les `markTestIncomplete` activés
  (création soignant/chef de pôle, refus chef de pôle → chef de pôle,
  désactivation hors périmètre)
- [x] `UserManagerTest` (unitaire, `tests/Unit/Service/`) — ✅ 6/6 verts.
  Premier Manager où le Repository sert vraiment (unicité de l'email) —
  extraction de `UserRepositoryInterface` + `InMemoryUserRepository` (vrai
  Fake en mémoire, cf. `docs/REVISION_ORAL.md` §12), réutilisée aussi par
  `NotificationManager`

---

## 4. Cycle de vie des déclarations — CDC §4.2-§4.4, UC-01 à UC-07

> ⚠️ Changement de périmètre (2026-07-27) : la génération de fiches RMM (UC-11
> historique, §4.5) est entièrement reportée en V2 — génération automatique,
> suggestion manuelle ET consultation. Le périmètre V1 de cet axe s'arrête à
> créer/modifier/abandonner/soumettre/lister/consulter une déclaration. Voir
> `SentiCare_CDC_v4.3.md` §4.5/§9.2. Entité `SuggestionRmm`, `RmmController`,
> `SuggestionRmmManager` et le champ `Declaration::suggestionRMM` supprimés du
> code (migration `Version20260727041825`).

### DeclarationVoter (`src/Security/Voter/DeclarationVoter.php`) ✅ TERMINÉ
- [x] `canView()` — soignant: ses déclarations · cadre: son service · chef de pôle: son pôle · admin: jamais
- [x] `canEdit()` — déclarant + statut brouillon uniquement
- [x] `canAbandon()` — déclarant + statut brouillon uniquement
- [x] `canSubmit()` — déclarant + statut brouillon uniquement
- [x] `canChangeStatut()` — cadre/chef de pôle, périmètre

### DeclarationManager (`src/Service/DeclarationManager.php`) ✅ TERMINÉ
- [x] `calculerGravite()` — assistant HAS 3 questions, CDC §4.2
- [x] `createDraft()` — étendu pour prendre les 3 questions de gravité + `setGravite()` à la création (sinon la gravité n'était jamais posée nulle part, `updateDraft()` la verrouille explicitement)
- [x] `updateDraft()` — champs autorisés uniquement, exclut gravite/isEIGS/service.
  4 règles CDC §4.3 ajoutées le 2026-08-01 (trouvées en relisant le tableau
  des champs du formulaire, jamais appliquées jusque-là) :
  - `dateConstat` pas dans le futur, aussi en modification (existait déjà à la création)
  - `description` : minimum 20 caractères — première (et seule) utilisation
    de `symfony/validator` du projet (`#[Assert\Length]` sur l'entité,
    `validatePropertyValue()` dans le Manager) ; les autres règles restent en
    `if` explicites pour rester cohérentes avec le style déjà en place
  - `lieuDifferentDetail`/`consequencesAutresDetail`/`mesuresImmediatesPatientDetail`
    requis quand leur booléen associé est `true` ("champ conditionnel si Oui")
- [x] `abandon()` — vérifie `StatutEnum::transitionsAutorisees()`
- [x] `submit()` — transition + notification (ne doit jamais bloquer si l'email échoue) — la génération RMM a été retirée (V2)
- [x] `changeStatut()` — cadre/chef de pôle uniquement, ne délègue jamais vers `Soumise` (réservé à `submit()`/déclarant)
- [x] `search()` — résout le périmètre (pole/services/declarant), délègue la requête à `DeclarationRepository::search()`

### DeclarationController (`src/Controller/DeclarationController.php`) ✅ TERMINÉ
- [x] `list()` — filtres lus depuis la query string (statut/date/type/gravité/eigsOnly/motCle)
- [x] `show()` / `create()` / `update()` / `abandon()` / `submit()` / `changeStatut()`
- [x] `exportPdf()` — dompdf, réutilise le template `emails/notification_submission.html.twig` (déjà pensé pour l'impression) plutôt qu'un template dédié

### Tests
- [x] `DeclarationVoterTest` — ✅ 9/9 verts
- [x] `DeclarationManagerGraviteTest` — ✅ 7/7 verts
- [x] `DeclarationControllerTest` — ✅ 8/8 verts, tous les `markTestIncomplete`
  activés. A révélé un vrai bug en base réelle (voir plus bas)
- [x] `DeclarationManagerTest` (unitaire, `tests/Unit/Service/`) — ✅ 22/22
  verts : `createDraft()`/`updateDraft()`/`abandon()`/`submit()`/`changeStatut()`/
  `resolvePerimeterCriteria()`/`search()`, + les 4 règles CDC §4.3 ajoutées.
  `calculerGravite()` déjà couvert par `DeclarationManagerGraviteTest`, pas
  dupliqué ici. Utilise un vrai `ValidatorInterface` (`Validation::createValidatorBuilder()`,
  léger, pas besoin du kernel) plutôt qu'un stub — on veut vraiment exercer
  la contrainte `#[Assert\Length]`, pas la simuler
- [x] `DeclarationRepositoryTest` (intégration, `tests/Integration/`) — ✅
  7/7 verts : `search()` (declarant/services/pole/statut/type/gravité/
  eigsOnly/motCle — dont un test dédié au fail-closed `pole => null`, cf.
  `docs/REVISION_ORAL.md` §4) et `aggregate()` (comptages par service/type)

---

## 5. Notifications email — CDC UC-08/UC-09, US-3.2 ✅ TERMINÉ

### NotificationManager (`src/Service/NotificationManager.php`) ✅ TERMINÉ
- [x] `notifySubmission()` — email blameless au(x) cadre(s) du service, persiste une `Notification` (statut Sent/Failed) par destinataire, log si échec sans bloquer
- [x] `notifyAccountCreated()` — mot de passe provisoire (US-1.2), pas de `Notification` persistée (pas de `Declaration` à lier dans ce flux)
- [x] Entité `Notification` + `NotificationStatusEnum` créées (CDC §5.1, manquaient entièrement) + migration `Version20260724144314`

### Tests
- [x] `NotificationManagerTest` (unitaire, `tests/Unit/Service/`) — ✅ 5/5
  verts : email envoyé + `Notification` persistée (Sent/Failed) par cadre,
  aucune exception ne remonte même si le Mailer échoue, `notifyAccountCreated()`
  idem. Mailer/Logger doublés en Mock, `UserRepositoryInterface` en Fake
  (`InMemoryUserRepository`, réutilisé de `UserManagerTest`)
- `NotificationRepository` : pas de test d'intégration — aucune méthode
  custom (juste le boilerplate `ServiceEntityRepository`), rien à nous qui
  vaille la peine d'être testé

---

## 6. Fiches RMM — hors périmètre V1, reporté en V2 (CDC §4.5, §9.2)

`SuggestionRmm` (entité), `SuggestionRmmManager`, `RmmController` et leurs tests
ont été supprimés du code le 2026-07-27 (cf. note en tête de section 4). À
reconstruire en V2 si le projet est repris au-delà du POC.

---

## 7. Tableau de bord superviseur — CDC §4.6, US-3.1 ✅ TERMINÉ

### DeclarationManager (`src/Service/DeclarationManager.php`)
- [x] `resolvePerimeterCriteria(User): array` — extrait de `search()`, réutilisé par `StatsManager` (une seule source de vérité pour le périmètre) ; refus explicite ROLE_ADMIN (CDC §3) au lieu d'un `else` implicite

### StatsManager (`src/Service/StatsManager.php`) ✅ TERMINÉ
- [x] `aggregate()` — délègue le périmètre à `resolvePerimeterCriteria()`, `throw` explicite si le périmètre contient un `declarant` (règle blameless, ne doit jamais arriver via la route mais défense en profondeur)

### DeclarationRepository (`src/Repository/DeclarationRepository.php`)
- [x] `aggregate(criteria, filters): array` — `parService`/`parType` via `GROUP BY` DQL, `parPeriode` regroupé côté PHP (pas de fonction DQL portable pour tronquer une date sans extension tierce)
- [x] `applyPerimeterAndFilters()` — extrait de `search()`, partagé avec `aggregate()`

### DashboardController (`src/Controller/DashboardController.php`) ✅ TERMINÉ
- [x] `stats()` — filtres lus depuis la query string (statut/date/type/gravité/eigsOnly), `tryFrom()` partout (400 propre sur valeur invalide, pas de 500)

### Tests
- [x] `DashboardControllerTest` — ✅ 3/3 verts, `testStatsNeverGroupByIndividualDeclarant` activé (vérifie que les clés de la réponse sont exactement `parService`/`parType`/`parPeriode`)
- [x] `StatsManagerTest` (unitaire, `tests/Unit/Service/`) — ✅ 2/2 verts :
  délégation au repository avec le périmètre résolu, et surtout le garde-fou
  blameless (`throw` + `DeclarationRepository::aggregate()` jamais appelé
  si le périmètre contient un `declarant`)

---

## 8. Journal d'audit — CDC §6.3, UC-11 ✅ TERMINÉ

> Décision d'architecture (2026-07-29) : entité dédiée (`LogEntry`) en base
> Postgres, pas un store NoSQL séparé ni une exploitation des fichiers
> Monolog. Raisons : échelle du POC trop faible pour justifier une 2e techno
> de base de données, schéma simple et fixe, cohérence avec le choix ACID de
> Postgres (CDC §7.1), et surtout — pouvoir tracer une action et l'action
> elle-même dans la même transaction Doctrine (`persist()`+`flush()`), ce
> qu'un store séparé ne garantirait pas (risque de dual-write). Monolog reste
> utilisé pour le logging technique (ex: échec d'envoi email), pas pour cette
> feature métier consultable par l'admin.
>
> Type d'événement granulaire par action (`LogTypeEnum`), pas 3 catégories
> larges — plus filtrable côté admin.

### LogTypeEnum (`src/Enum/LogTypeEnum.php`) ✅ TERMINÉ
- [x] 10 cas : connexions (LoginSuccess/LoginFailure), soumission
  (DeclarationSubmitted), comptes (UserCreated/Deactivated/Reactivated),
  pôles (PoleCreated/Updated/Deactivated), services (ServiceCreated/Deactivated)

### LogEntry (`src/Entity/LogEntry.php`) ✅ TERMINÉ
- [x] UUID v4, `type` (LogTypeEnum), `user` (FK **nullable** — un login
  échoué sur un email inconnu ne résout à aucun compte), `message`, `createdAt`
- [x] Migration `Version20260729044525`

### LogEntryRepository (`src/Repository/LogEntryRepository.php`) ✅ TERMINÉ
- [x] `search(array $filters): array` — filtre type/user/dateFrom/dateTo, triée par `createdAt` DESC

### LogManager (`src/Service/LogManager.php`) ✅ TERMINÉ
- [x] `log(LogTypeEnum $type, ?User $user, string $message): void` — `try/catch \Throwable` + log technique, ne fait jamais échouer l'action qu'elle trace (même politique que `NotificationManager` — CDC §6.3)
- [x] `search(array $filters): array` — délègue au repository

### Appels câblés ✅ TERMINÉ
- [x] `DeclarationManager::submit()` — DeclarationSubmitted (acteur = déclarant)
- [x] `UserManager::create()`/`deactivate()`/`reactivate()` — UserCreated/Deactivated/Reactivated (acteur = `$createdBy`/`$actor`, ajouté aux signatures + aux contrôleurs via `#[CurrentUser]`)
- [x] `PoleManager::create()`/`update()`/`deactivate()` — PoleCreated/Updated/Deactivated
- [x] `ServiceManager::create()`/`update()`/`deactivate()` — ServiceCreated/Updated/Deactivated (ajout du cas `ServiceUpdated` à `LogTypeEnum`, absent au départ)
- [x] Connexions : `LoginLogSubscriber` (`src/EventSubscriber/LoginLogSubscriber.php`)
  sur `LoginSuccessEvent`/`LoginFailureEvent` — découplé du flux `json_login` +
  handlers LexikJWT existants, auto-enregistré (aucune config YAML, comme un Voter)

### LogController (`src/Controller/LogController.php`) ✅ TERMINÉ
- [x] `list()` — `GET /api/admin/logs`, `#[IsGranted('ROLE_ADMIN')]`, filtres
  query string (type/user/dateFrom/dateTo), `tryFrom()` + 400 propre

### Tests
- [x] `LogControllerTest` — bornes d'accès (auth, ROLE_ADMIN exclusif — même
  un chef de pôle est refusé), `list()` répond 200, et vérification de bout
  en bout que `UserManager::create()` écrit bien un `LogEntry` ✅ vertes (4/4)
- [x] `LoginLogSubscriberTest` — connexion réussie ET échouée écrivent bien
  chacune une `LogEntry` (échouée → `user` reste `null`) ✅ vertes (2/2)
- [x] `LogManagerTest` (unitaire, `tests/Unit/Service/`) — ✅ 4/4 verts,
  y compris la panne du journal elle-même (`flush()` qui lève) qui ne doit
  jamais remonter à l'appelant
- [x] `LogEntryRepositoryTest` (intégration, `tests/Integration/`) — ✅ 4/4
  verts : `search()` filtré par type/user/dateFrom

---

## 9. Ajouts pour le frontend — 2026-08-05 ✅ TERMINÉ

Trois manques rendaient le développement du front React impossible ou bancal.
Corrigés en amont de son démarrage.

### `GET /api/me` enrichi
- [x] Renvoie `id`, `role` (rôle métier brut) et **`services[]` avec leur `pole`**
  — le wizard doit pré-remplir le service depuis le profil (CDC §4.3), or un
  soignant reçoit un 403 sur `GET /api/services`. C'est aussi le seul moyen pour
  un chef de pôle de connaître son `poleId`, nécessaire pour créer un service :
  `/api/admin/poles` lui est interdit.
- [x] `roles[]` contient désormais **l'héritage résolu côté serveur**
  (`RoleHierarchyInterface::getReachableRoleNames()`) : sans ça le front devrait
  réimplémenter `ROLE_CHEF_POLE ⊃ ROLE_CADRE ⊃ ROLE_SOIGNANT`, et un chef de
  pôle perdrait les écrans cadre et soignant. `ROLE_ADMIN` reste orthogonal.
- [x] 3 tests dans `SecurityControllerTest` verrouillent ce contrat, dont un qui
  vérifie qu'un admin ne reçoit **aucun** rôle hérité

### Fixtures conformes au CDC §10
- [x] **4 pôles, 9 services, 32 comptes** (1 admin + 1 chef par pôle + 1 cadre
  par service + 2 soignants par service), structure réelle de la clinique
- [x] **18 déclarations** couvrant tous les statuts et toutes les gravités,
  étalées sur 6 mois — le tableau de bord agrège `parPeriode` sur `dateConstat`,
  sans étalement les graphiques n'auraient qu'une seule barre
- [x] Les services portent enfin un pôle : avant, `pole = null` rendait le
  périmètre du chef de pôle vide (fail-closed) et son dashboard désespérément
  creux
- [x] Les 4 emails de démo historiques (`admin@`, `chefpole@`, `cadre@`,
  `soignant@`) restent valides

### Référence lisible des déclarations
- [x] `Declaration::$reference` (`DCL-2026-0042`) + migration
  `Version20260805040000`, exposée par `DeclarationController::serialize()`
- [x] `ReferenceGenerator` s'appuie sur une **séquence PostgreSQL**
  (`declaration_reference_seq`) et non sur un `COUNT(*) + 1` : deux soignants
  déclarant simultanément liraient le même total, généreraient la même référence
  et l'une des deux insertions échouerait sur la contrainte d'unicité — un
  signalement perdu. `nextval()` est atomique.
- [x] Attribuée **dès le brouillon** : un soignant doit pouvoir en parler à son
  cadre avant de soumettre
- [x] ⚠️ **Ce n'est pas un identifiant de route.** L'UUID reste seul à jouer ce
  rôle : `DCL-2026-0043` se devine à partir de `DCL-2026-0042`, l'exposer en URL
  réintroduirait la faille d'énumération que les UUID évitent (CDC §6.2)
- [x] `ReferenceGeneratorTest` (intégration — la séquence n'existe qu'en base,
  un test unitaire ne peut pas l'exercer) + `testCreateDraftAssignsReference`
  (unitaire) + `testCreatedDeclarationExposesReadableReference` (fonctionnel)
- [x] Les fixtures remettent la séquence à zéro : la purge ne le fait pas, et le
  jeu de démonstration doit rester reproductible

### Reste à traiter
- [ ] `PATCH /api/declarations/{id}` avec un `typeEI` invalide renvoie **500** au
  lieu de 400 (`TypeEIEnum::from()` au lieu de `tryFrom()`,
  `DeclarationController.php` ~ligne 182)
- [ ] **Export PDF probablement cassé** : `templates/emails/notification_submission.html.twig`
  référence `declaration.deces`, `pronosticVitalEnJeu` et
  `risqueDeficitFonctionnelPermanent`, qui n'existent pas sur l'entité (seule la
  gravité résultante est persistée), et rend des enums sans `__toString`. Aucun
  test ne rend réellement ce template (le Mailer est mocké).
- [ ] `Service` sérialisé **sans son pôle** par `/api/services` → l'UI admin ne
  peut pas grouper par pôle sans appels croisés
- [ ] Aucune **pagination ni tri** sur `GET /api/declarations` ni
  `GET /api/admin/logs`
- [ ] `MAILER_DSN=null://null` en dev alors que Mailpit tourne : les mots de
  passe provisoires ne partent pas, impossible de tester le parcours de création
  de compte de bout en bout. Basculer sur `smtp://localhost:1025`.

---

## Blocages connus à lever

| Blocage | Impact | Action |
|---|---|---|
| ~~`Pole`/`Service` sans champ `isActive`~~ | ~~`deactivate()` impossible à implémenter~~ | ✅ Résolu (migrations `Version20260715032521` + `Version20260715032619`) |
| ~~Pas de lib PDF installée~~ | ~~`DeclarationController::exportPdf()` (UC-07)~~ | ✅ Résolu (`dompdf/dompdf`, réutilise le template email) |
| ~~Entité `Notification` absente (CDC §5.1)~~ | ~~`NotificationManager` ne compilait pas~~ | ✅ Résolu (entité + enum + repository + migration créés) |
| ~~Design des logs non tranché~~ | ~~UC-11 (consultation logs)~~ | ✅ Résolu (entité `LogEntry` dédiée, cf. §8) |
| ~~`UserRepository::findByRole()`/`findCadresByService()` : `roles LIKE` invalide en Postgres (colonne `json`, pas d'opérateur `~~`)~~ | ~~500 sur `submit()` (notification) et sur la liste des chefs de pôle — jamais détecté car jamais exécuté contre une vraie base avant l'activation des `markTestIncomplete`~~ | ✅ Résolu (SQL natif + `CAST(... AS TEXT)`, cf. `docs/REVISION_ORAL.md` §10) |

---

## Comment avancer

1. Commence par les **Voters** (`DeclarationVoterTest`, `UserVoterTest`) — purs, rapides, sans DB, aucune dépendance sur le reste.
2. Puis les **Managers** un par un, en écrivant d'abord le test d'intégration manquant (voir sections ci-dessus) avant d'implémenter.
3. Les **Controllers** suivent presque automatiquement une fois Manager + Voter prêts (ils ne font que déléguer).
4. Coche chaque ligne ici au fur et à mesure — ce fichier est la source de vérité de l'avancement, pas ma todo de session (qui ne persiste pas).

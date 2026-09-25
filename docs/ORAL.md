# SentiCare — Fiche de soutenance

Ce document dit **quoi raconter, dans quel ordre**. Il ne remplace pas les
fiches de révision, qui répondent aux questions techniques :

| Fiche | Contenu |
|---|---|
| `senticare-api-v0/docs/REVISION_ORAL.md` | API, sécurité, base de données, `.env` |
| `senticare-front-v0/docs/REVISION_ORAL.md` | React, TypeScript, styles, authentification |
| `senticare-infra/docs/REVISION_ORAL.md` | Docker, nginx, Compose, HDS, déploiement |
| `docs/DECISIONS.md` | les 13 décisions structurantes |
| `docs/SPRINTS.md` | l'organisation et les backlogs |

> ⚠️ **Ne récite pas ce document.** Les phrases en encadré sont à connaître parce
> qu'elles sont difficiles à formuler sur le moment. Tout le reste est à dire
> avec tes mots — un exposé récité s'entend, et il s'effondre à la première
> question hors script.

---

## 0. Avant d'entrer

- [ ] `docker compose up -d` lancé, fixtures chargées, **testé 10 minutes avant**
- [ ] Onglets ouverts : l'application, le dépôt GitHub, un terminal
- [ ] Terminal prêt sur `senticare-api-v0` (pour lancer les tests en direct)
- [ ] Le CDC v4.5 accessible
- [ ] Mot de passe de démonstration en tête : `Senticare2026!`
- [ ] **Plan B** si la démonstration tombe : captures d'écran, et les tests
      end-to-end qui prouvent le parcours

---

## 1. Plan et minutage

| § | Partie | Durée |
|---|---|---|
| 2 | Ouverture — le problème métier | 3 min |
| 3 | Le blameless, contrainte structurante | 2 min |
| 4 | **Conception — le modèle de données** | 5 min |
| 5 | Architecture et choix techniques | 3 min |
| 6 | **Conception de l'expérience — les 3 minutes** | 3 min |
| 7 | **Démonstration** | 8 min |
| 8 | **Extraits de code commentés** | 6 min |
| 9 | Sécurité | 4 min |
| 10 | Tests et qualité | 3 min |
| 11 | Conteneurisation et intégration continue | 3 min |
| 12 | Gestion de projet et écarts | 3 min |
| 13 | Bilan et perspectives | 2 min |
| | **Total** | **45 min** |

⚠️ **Vise 42 minutes en répétition.** Le jour J, on parle plus lentement, on
répond à une interruption, la démonstration prend trente secondes de plus. Un
exposé calé à 45 en répétition en fera 50.

**Si tu dois couper en direct**, dans cet ordre : les extraits de code (§8) se
réduisent de trois à un, puis la conception de l'expérience (§6) passe à une
minute. Ne coupe **jamais** la démonstration ni les écarts (§12).

---

## 2. Ouverture — le problème métier

**Ce qu'il faut faire passer** : le problème n'est pas technique, il est humain.

Dans un établissement de santé, un événement indésirable — une chute, une erreur
médicamenteuse, une infection — doit être signalé. La déclaration alimente une
démarche d'amélioration. Mais **elle est sous-déclarée**, pour deux raisons : le
formulaire est long, et le soignant craint d'être mis en cause.

> « J'ai construit une application de signalement d'événements indésirables pour
> une clinique. Le vrai problème n'était pas de collecter des données, c'était
> de faire en sorte qu'on ose déclarer. »

Les deux objectifs du cahier des charges découlent de là :

- **3 minutes** pour déclarer — sinon on ne déclare pas
- **approche blameless** — sinon on n'ose pas

---

## 3. Le blameless — la contrainte qui structure tout

C'est ce qui distingue ton projet d'un CRUD. À dire tôt, et à relier au code.

Le principe : le dispositif ne sert **jamais** à mettre en cause un individu.
Conséquences concrètes dans l'application :

| Règle | Traduction technique |
|---|---|
| Aucun classement individuel | le tableau de bord agrège par service, type et période — jamais par déclarant |
| Le déclarant n'est jamais sur le même axe de lecture que la gravité | contrainte de conception des statistiques |
| Pas de formulation accusatoire | « un événement est survenu », jamais « une erreur a été commise » |
| Pas de donnée nominative de patient | l'intitulé de déclaration est **composé automatiquement** |

> « Le choix d'un intitulé composé automatiquement plutôt que saisi librement
> vient de là : un champ libre aurait appelé des formulations du type "chute de
> M. Dupont chambre 12". Incompatible avec le RGPD, et contraire à l'objectif
> des trois minutes. »

---

## 4. Conception — le modèle de données — 5 minutes

Montre le schéma. **Sept entités, six énumérations.**

```
Pole ──< Service >──< User ──< Declaration
                              │
                              ├── Notification
                              └── LogEntry     (journal d'audit)

RefreshToken   (jetons de rafraîchissement, à rotation)
```

Ne les énumère pas : présente **quatre décisions de conception**, c'est ce qui
est évalué.

### 4.1 Identifiants en UUID plutôt qu'en entiers

> « Un identifiant auto-incrémenté se devine. Avec `/api/declarations/42`, on
> essaie 41, puis 40 : on découvre combien il y a de déclarations, et on tente
> d'y accéder. Les UUID suppriment cette possibilité — on ne devine pas
> `019fd00b-22de-73fd-9db0-e899711a5f61`. »

Le Voter refuse de toute façon l'accès hors périmètre. Mais **empêcher la
tentative vaut mieux que la refuser** : ça évite aussi de renseigner
l'attaquant sur le volume de données.

### 4.2 Une référence lisible en plus de l'UUID

Un UUID ne se dicte pas au téléphone et ne se reporte pas sur un compte rendu.
D'où `DCL-2026-0042`, attribuée dès le brouillon.

**Le point technique à raconter** — comment garantir son unicité :

| Approche | Problème |
|---|---|
| `COUNT(*) + 1` | deux déclarations créées à la même seconde reçoivent le **même** numéro |
| **Séquence PostgreSQL** (`nextval`) | atomique par construction, y compris sous accès concurrent |

> « J'ai utilisé une séquence native PostgreSQL. `nextval` est atomique : deux
> transactions simultanées obtiennent forcément deux valeurs différentes. C'est
> une des raisons du choix de PostgreSQL, et c'est vérifié par un test
> d'intégration. »

⚠️ Et la conséquence de sécurité, à donner sans qu'on la demande :

> « Cette référence n'est **jamais** utilisée comme identifiant d'URL. Elle est
> séquentielle, donc devinable : s'en servir réintroduirait exactement la faille
> d'énumération que les UUID préviennent. »

### 4.3 Les statuts forment une machine à états

`brouillon → soumise → en analyse → clôturée → transmise HAS`, plus `abandonnée`.

Ce n'est pas un champ libre : les transitions autorisées sont déclarées dans
l'énumération. Une déclaration clôturée ne peut pas revenir en brouillon.

> « Le statut n'est pas une chaîne de caractères qu'on écrase. Chaque transition
> est validée côté serveur, et l'interface ne propose que les actions réellement
> possibles depuis l'état courant. »

### 4.4 Les énumérations sont sérialisées avec leur libellé

L'API ne renvoie pas `"cloturee"` mais :

```json
"statut": { "value": "cloturee", "label": "Clôturée" }
```

> « Le libellé français est produit par le serveur. Sans ça, le frontend
> maintiendrait son propre dictionnaire de traduction, qui divergerait au
> premier ajout de statut. »

### Si on te demande ce que tu ferais autrement

> « La relation entre un utilisateur et son pôle passe aujourd'hui par ses
> services — il n'y a pas de lien direct. C'est une convention, pas une
> contrainte du modèle, et un chef de pôle sans service rattaché la mettrait en
> défaut. Je la matérialiserais dans le schéma. »

---

## 5. Architecture et choix techniques — 3 minutes

**Trois dépôts** : l'API, le frontend, l'infrastructure.

### 5.1 Les composants — attention au piège

⚠️ **Le front n'est pas un service en production.** React s'exécute dans le
navigateur de l'utilisateur. Sur le serveur, il n'y a que des fichiers `.js` et
`.css`. Ne dis jamais « le front communique avec l'API » — c'est **le
navigateur** qui communique.

| Composant | Rôle |
|---|---|
| **Navigateur** | exécute React. N'est pas un conteneur. |
| **nginx** | le seul point d'entrée |
| **php-fpm** | exécute Symfony |
| **PostgreSQL** | les données |

En développement s'ajoute **Vite**, qui sert le front avec rechargement à chaud.
Là, c'est un vrai service qui tourne.

### 5.2 Qui parle à qui, et comment

| De → vers | Protocole | Adresse |
|---|---|---|
| navigateur → nginx | HTTP(S) | port publié |
| nginx → php-fpm | **FastCGI**, pas HTTP | `php:9000` |
| php-fpm → PostgreSQL | protocole PostgreSQL | `database:5432` |

**Les liaisons qui n'existent pas**, et qu'on dessine à tort :

- ❌ Vite → nginx : Vite ne relaie rien, il envoie le JavaScript et s'arrête
- ❌ navigateur → php-fpm : aucun port publié, et il ne parle pas HTTP
- ❌ navigateur → PostgreSQL : aucun accès, toute donnée passe par l'API

> « Le navigateur ne peut pas atteindre la base : elle n'a aucun port publié en
> production. Toute donnée transite par l'API, donc par les Voters. »

### 5.3 Dev et prod — une seule différence structurante

```
   DÉVELOPPEMENT                        PRODUCTION

navigateur ─┬─► Vite    :5173      navigateur ──► nginx  :80
            └─► nginx   :8000                       │
                  │                                 │ sert AUSSI le dist/
                  ▼                                 ▼
                php-fpm                           php-fpm
                  ▼                                 ▼
               database :5433                    database (aucun port)

   2 origines → CORS                     1 origine → pas de CORS
```

**Le nombre de flèches partant du navigateur : tout en découle.**

La chaîne nginx → php → base est identique dans les deux cas. Ce qui change,
c'est la configuration : en production nginx sert aussi le front, n'a pas le
code de l'API, et transmet le protocole d'origine à php-fpm.

### 5.4 Les choix à savoir défendre

Un mot chacun, développer seulement si on te le demande :

| Choix | Raison en une phrase |
|---|---|
| API REST + SPA découplées | le contrat est explicite et typé ; l'API pourra servir une application mobile en V3 |
| **Pas d'API Platform** | les endpoints sont écrits à la main : je maîtrise chaque réponse, et la logique d'autorisation par périmètre ne se réduit pas à de la configuration |
| JWT plutôt que sessions | l'API est *stateless*, adaptée à une SPA ; les sessions supposent un couplage que le découplage écarte |
| PostgreSQL | séquences natives (la référence des déclarations en dépend), typage JSON, contraintes d'intégrité |
| **Aucune librairie de composants** | le §2.1 exige un design propre à l'application ; l'interface est écrite intégralement (20 composants) |
| Sass + CSS Modules | isolation par composant sans convention de nommage ; jetons centralisés — couleurs en propriétés CSS, mesures en variables Sass |

> « Je n'ai pas utilisé API Platform. Il aurait généré les endpoints à partir des
> entités, mais l'autorisation de ce projet dépend du périmètre de chaque rôle,
> et je voulais pouvoir expliquer chaque réponse de l'API. »

### 5.5 Si on creuse — trois questions probables

**« Pourquoi nginx ? »**

> « Parce que php-fpm ne parle pas HTTP mais FastCGI. Un navigateur ne peut pas
> l'atteindre directement : il faut un serveur web devant, quel que soit
> l'environnement. »

**« Pourquoi pas le serveur intégré `php -S` ? »**

> « C'est un serveur de développement — mono-processus, sans durcissement, et la
> documentation PHP déconseille explicitement de l'exposer. php-fpm gère un pool
> de processus et les recycle.
>
> Mais la vraie raison est la parité : avec `php -S`, la liaison nginx ↔ php-fpm
> n'existe pas en développement. C'est précisément là qu'était mon bug de boucle
> de redirection — il aurait été structurellement invisible. »

**« Pourquoi une SPA plutôt qu'une application Symfony classique ? »**

⭐ La meilleure question qu'on puisse te poser sur l'architecture. Réponds en
reconnaissant le coût, puis en le justifiant.

> « Le découplage a un coût que j'ai sous-estimé : un client HTTP avec gestion
> des jetons et rafraîchissement concurrent, un contrat d'API typé, des gardes
> de route en double, le CORS. Rien de tout ça n'existerait avec du Twig rendu
> côté serveur.
>
> Je maintiens le choix pour une raison d'usage : la déclaration se fait au
> chevet du patient, sur tablette. Une API découplée sert indifféremment un
> navigateur, une tablette et l'application native prévue en V3. Avec un
> monolithe, il aurait fallu construire cette API après coup, en doublon.
>
> Cela dit, si le périmètre s'était arrêté au web, le monolithe aurait été le
> bon choix — plus simple, et livrable dans les délais que je n'ai pas tenus. »

⚠️ **Ne va pas plus loin.** Reconnaître une complexité assumée est fort ; dire
« mon architecture était une erreur » ne l'est pas, alors que l'usage la
justifie.

⚠️ **Et ne cite pas le cahier des charges comme argument** — c'est toi qui l'as
écrit. « Le CDC impose php-fpm » est circulaire. La justification doit être
technique ou métier.

---

## 6. Conception de l'expérience — les 3 minutes — 3 minutes

Montre les maquettes (`senticare_maquette/`), puis explique **ce que la
contrainte de temps a imposé**.

L'objectif de trois minutes n'est pas décoratif : au-delà, on ne déclare pas.
Il a dicté trois choix.

### L'assistant de gravité — ne pas demander ce que le déclarant ne sait pas

Un soignant n'a pas à savoir ce qu'est un EIGS. Le formulaire ne lui demande
donc pas de qualifier la gravité : il pose **trois questions factuelles**
issues des critères de la Haute Autorité de Santé — décès, mise en jeu du
pronostic vital, risque de déficit fonctionnel permanent — et **en déduit** la
gravité.

> « Demander directement "s'agit-il d'un événement grave ?" aurait produit des
> réponses par excès de prudence, et une classification inutilisable. Les trois
> questions sont factuelles, la qualification est calculée. »

Les **trois textes du bandeau de confirmation sont imposés au mot près** par le
cahier des charges §4.2. Ils sont reproduits tels quels dans le code, et
c'est volontaire : ce sont eux qui rassurent le déclarant sur ce qui va se
passer ensuite.

### Le formulaire en quatre étapes plutôt qu'un écran unique

Un formulaire long décourage. Découpé, chaque écran demande peu, la progression
est visible, et l'abandon est moins probable.

### ⚠️ L'arbitrage à raconter — l'ordre des étapes

C'est le meilleur exemple d'une décision où le métier et la technique se sont
parlé.

> « J'avais découpé le formulaire selon une logique métier. En écrivant le
> contrat d'API, je me suis aperçue que les champs obligatoires à la création
> d'une déclaration étaient répartis sur trois étapes : il aurait fallu
> conserver un état intermédiaire côté navigateur, sans rien enregistrer, et le
> perdre au moindre rechargement.
>
> J'ai réorganisé les étapes pour que les deux premières correspondent
> exactement à la charge utile de création. La déclaration existe donc en base
> dès la fin de l'étape 2.
>
> La conséquence est assumée : la sauvegarde en brouillon n'apparaît qu'à partir
> de l'étape 3, puisqu'avant, il n'y a rien à sauvegarder. C'est documenté dans
> le cahier des charges. »

---

## 7. Démonstration — 8 minutes

⚠️ **Annonce le périmètre AVANT de démontrer.** Une omission découverte en
direct coûte trois fois plus cher qu'une limite annoncée.

> « Le frontend livré couvre l'authentification et la consultation. Les autres
> écrans sont spécifiés et maquettés, leurs composants sont écrits, mais ils ne
> sont pas reliés aux données — c'est un arbitrage que j'ai daté et documenté,
> j'y reviens en fin de présentation. »

### Scénario — dans cet ordre

**1. La connexion** — `soignant@senticare.fr`

- montrer l'écran, puis se connecter
- **F5** : « la session survit au rechargement — c'est la réhydratation via
  `/api/me`, et c'est le bug le plus classique d'une SPA »
- ouvrir la console → `localStorage` → montrer les **deux** jetons

**2. Le cloisonnement par rôle** — le moment le plus démonstratif

- taper `/admin/poles` dans la barre d'adresse → **refus explicite**
- se déconnecter, se reconnecter en `cadre@senticare.fr`
- montrer l'atterrissage différent
- se reconnecter en `admin@senticare.fr` → atterrit sur l'administration
- taper `/mes-declarations` → **refusé**

> « L'administrateur est orthogonal à la hiérarchie : il n'est pas au-dessus des
> autres rôles, il est à côté. Il gère la structure et n'a aucun accès aux
> déclarations — l'API lui répond 403. C'est la règle la plus contre-intuitive du
> cahier des charges, et c'est celle que je teste le plus. »

**3. Le périmètre côté serveur** — montrer que la garde d'interface n'est pas la
sécurité

- un terminal, deux `curl` avec deux jetons différents sur `/api/declarations`
- le cadre voit **3** déclarations, l'administrateur reçoit **403**

> « La garde côté interface est une commodité d'usage. Le contrôle qui fait foi
> est le Voter côté serveur. Si quelqu'un contourne l'interface, il se heurte au
> 403. »

**4. Les tests, en direct** — c'est ce qui impressionne le plus, et ça coûte 1 min

```bash
php bin/phpunit                 # 146 tests, ~2 s
npm test                        # 60 tests, ~3 s
npm run e2e                     # 11 scénarios dans un vrai navigateur, ~45 s
```

Lance l'end-to-end en dernier : le navigateur s'ouvre, les scénarios défilent.

### Si le jalon 2 est livré d'ici là

Insérer après l'étape 2 : se connecter en soignante → **voir ses déclarations** →
se reconnecter en cadre → **voir celles de son service**. Une seule séquence qui
montre l'authentification, le métier et la règle de périmètre.

---

## 8. Extraits de code commentés — 6 minutes

Trois extraits, deux minutes chacun. **Prépare-les dans des onglets ouverts** :
chercher un fichier en direct coûte trente secondes et casse le rythme.

Pour chacun, dis trois choses : **le problème**, **l'alternative écartée**, **ce
que ça protège**.

### 8.1 `ReferenceGenerator` — la concurrence

`src/Service/ReferenceGenerator.php`

**Le problème** — attribuer un numéro unique à chaque déclaration, y compris si
deux soignants valident à la même seconde.

**L'alternative écartée** — `COUNT(*) + 1`. Deux transactions simultanées lisent
le même total et produisent le même numéro.

**La solution** — une séquence PostgreSQL. `nextval` est atomique : le moteur
garantit que deux appels concurrents renvoient deux valeurs distinctes.

> « Ce n'est pas un choix d'optimisation, c'est un choix de correction. Le
> comportement de `COUNT(*)+1` est faux dès qu'il y a deux utilisateurs, et il
> ne se manifeste jamais en développement où l'on est seul. »

### 8.2 `DeclarationVoter` — l'autorisation par périmètre

`src/Security/Voter/DeclarationVoter.php`

**Le problème** — un cadre voit les déclarations de son service, un chef de pôle
celles de son pôle, un soignant les siennes. Un administrateur, aucune.

**L'alternative écartée** — tester les rôles dans les contrôleurs. La règle
serait dupliquée sur chaque route, et un oubli passerait inaperçu.

**La solution** — un Voter, appelé par `#[IsGranted]`. La règle est écrite une
fois et appliquée déclarativement.

> « L'administrateur est le cas intéressant : il n'hérite d'aucun rôle métier.
> Une hiérarchie naïve en ferait un super-utilisateur ayant accès à tout, alors
> que le cahier des charges exige l'inverse — il gère la structure et ne doit
> voir aucune déclaration. C'est explicitement testé. »

### 8.3 Le rafraîchissement sérialisé — le plus technique

`senticare-front-v0/src/api/client.ts`

**Le problème** — le jeton expire au bout d'une heure. Si un écran lance trois
requêtes en parallèle à ce moment-là, les trois reçoivent un 401 simultanément.

Le jeton de rafraîchissement est **à usage unique** : le premier qui s'en sert
l'invalide. Sans précaution, les trois appels tentent le rafraîchissement avec
le même jeton, le premier réussit, **les deux autres échouent** — et l'utilisateur
est déconnecté alors que sa session vient d'être renouvelée.

**La solution** — une promesse partagée au niveau du module. Le premier appelant
lance le rafraîchissement, les suivants attendent le même résultat.

```ts
rafraichissementEnCours ??= demanderNouvellePaire().finally(() => {
  rafraichissementEnCours = null
})
```

**Et raconte le bug** — c'est ce qui rend l'extrait mémorable :

> « Le verrou était initialement libéré par un `finally` à l'intérieur de la
> fonction. Sur un chemin particulier, cette fonction se termine sans opération
> asynchrone : elle s'exécute donc entièrement de façon synchrone, et le `finally`
> remettait la variable à zéro **avant** que l'affectation ne la remplisse. Le
> verrou restait bloqué définitivement.
>
> En production : après un seul incident, plus aucun rafraîchissement n'était
> tenté pour la durée de vie de l'onglet, même après une reconnexion. Une
> déconnexion une heure plus tard, sans cause visible et impossible à
> reproduire à la main.
>
> C'est un test unitaire qui l'a trouvé. »

---

## 9. Sécurité — 4 minutes

> « La sécurité n'a fait l'objet d'aucun arbitrage quand j'ai réduit le
> périmètre. C'est la seule partie du projet où je n'ai rien coupé. »

**Authentification** — JWT signés par paire de clés asymétriques, une heure de
validité. Jetons de rafraîchissement **à usage unique** : chaque échange en
émet un nouveau et invalide l'ancien. Argon2id pour les mots de passe.
Blocage après cinq tentatives. **Messages d'erreur identiques** quel que soit le
motif du refus.

> « Un message différent pour un compte inconnu et un mot de passe erroné
> permettrait de découvrir quels comptes existent dans l'établissement. C'est
> vérifié automatiquement par un test end-to-end qui compare les deux messages. »

**Autorisation** — trois Voters, appliqués route par route. Moindre privilège.

**Données** — identifiants en UUID, donc pas d'énumération par incrémentation.
La référence lisible `DCL-2026-0042` n'est **jamais** utilisée dans une URL,
précisément pour ne pas réintroduire la faille que les UUID préviennent.

**Interface** — React échappe le texte affiché, `dangerouslySetInnerHTML` est
proscrit et n'apparaît nulle part dans le code.

**Secrets** — aucun n'est versionné. En intégration continue, la paire de clés
est **générée à chaque exécution** avec une passphrase aléatoire.

> « Aucun secret n'est configuré dans GitHub Actions. Les jetons signés pendant
> les tests sont vérifiés dans la même exécution : rien n'a besoin de survivre.
> Un secret qui n'existe pas ne peut pas fuiter. »

**Hébergement** — données de santé à caractère personnel ⇒ hébergeur **certifié
HDS** (art. L.1111-8 du Code de la santé publique). Azure l'est, et c'est
l'environnement disponible dans l'entreprise d'accueil.

⚠️ **Énonce la réserve toi-même, ne la laisse pas venir du jury** :

> « Azure est certifié HDS, mais c'est un fournisseur soumis au Cloud Act —
> réserve régulièrement soulevée par la CNIL pour les données de santé. Un
> hébergeur français certifié y échapperait. C'est acceptable ici, données
> fictives et projet de formation ; en exploitation réelle l'arbitrage
> reviendrait à l'établissement et à son DPO. »

⚠️ **Et ne dis jamais « je suis conforme HDS ».** La formulation juste :

> « La certification couvre six activités en responsabilité partagée. La base
> managée met les sauvegardes du côté de l'hébergeur, mais l'exploitation de la
> machine virtuelle reste à ma charge et relève du même référentiel. Une mise en
> production réelle supposerait un contrat HDS et une analyse d'impact. »

---

## 10. Tests et qualité — 3 minutes

**217 tests automatisés.**

| Niveau | Nombre | Ce qu'ils atteignent |
|---|---|---|
| Unitaire | 102 | logique pure, sans base ni réseau |
| Intégration | 20 | dépôts contre un **vrai** PostgreSQL |
| Fonctionnel / composant | 84 | routes de bout en bout, gardes, écran de connexion |
| Bout en bout (Playwright) | 11 | l'application entière, navigateur réel |

**Sur la couverture** — question probable, réponse préparée :

> « Je ne mesure pas de pourcentage. Un taux compte les lignes exécutées, pas
> les comportements vérifiés : couvrir des accesseurs ne prouve rien. Mon
> critère est que toute règle ayant une conséquence métier, réglementaire ou de
> sécurité soit couverte — le cloisonnement par rôle, les transitions de statut,
> l'unicité de la référence sous accès concurrent, l'égalité des messages
> d'erreur. »

**Standards** — ⚠️ Sois précise sur l'origine : le modèle Vite fournit déjà
quatre options de rigueur. **Deux ont été ajoutées**, et ce sont celles-là que
tu revendiques.

| Ajoutée | Pourquoi |
|---|---|
| `strict` | l'API renvoie des valeurs nullables ; sans elle, un écran blanc pour l'administrateur, qui n'a aucun pôle |
| `noUncheckedIndexedAccess` | indexer un tableau potentiellement vide est l'erreur d'exécution la plus fréquente en JavaScript |

> « Le modèle Vite en fournissait quatre — code mort, syntaxe non effaçable,
> oubli de `break`. Je les ai gardées, mais je ne les revendique pas. J'en ai
> ajouté deux. »

### ⭐ L'anecdote à raconter — la meilleure de la section

C'est un moment où l'outillage a dépassé son rôle. Prends les vingt secondes.

> « `noUncheckedIndexedAccess` a produit un effet que je n'attendais pas.
>
> Elle interdit d'écrire `tableau[0]` sans traiter le cas où l'élément n'existe
> pas. En m'obligeant à écrire `me.services[0]?.pole` plutôt que
> `me.services[0].pole`, elle m'a contrainte à répondre à une question que je ne
> m'étais pas posée : **dans quel cas un utilisateur n'a-t-il aucun service ?**
>
> La réponse était un cas métier que je n'avais pas identifié — l'administrateur.
> Il gère la structure de la clinique, les pôles, les comptes, mais il n'est
> rattaché à aucun service.
>
> Une contrainte de compilation a fait remonter une lacune de conception. »

**Si on te demande la conséquence concrète** : sans cette option, la fonction qui
déduit le pôle d'un utilisateur aurait planté sur le premier administrateur
connecté — et le cas n'apparaît nulle part dans les jeux de test qu'on écrit
spontanément, puisqu'on pense d'abord aux soignants.

**Et le lien à faire avec le reste de la présentation** : c'est le même schéma
que les quatre défauts trouvés par la chaîne de construction (§12). Un outil de
vérification ne se contente pas de confirmer ce qu'on sait — il pose des
questions qu'on ne s'est pas posées.

**Limites, à annoncer soi-même** : pas d'analyse statique côté PHP (asymétrie
avec le frontend), pas de revue par les pairs (projet solo), validation cliente
non encore implémentée.

---

## 11. Conteneurisation et intégration continue — 3 minutes

Quatre services, deux cibles de construction.

| | Développement | Production |
|---|---|---|
| Code | monté depuis le disque | copié dans l'image |
| Origines | deux (Vite 5173, API 8000) → CORS | **une seule** → aucun CORS |
| Image PHP | étage `dev` | étage `prod`, sans dépendances de développement |

> « En production, nginx sert le bundle du frontend et relaie `/api` vers
> php-fpm. Le navigateur ne voit qu'une seule origine : aucune vérification CORS
> n'est déclenchée. La variable de configuration du frontend est vide au build,
> les appels sont donc relatifs. »

### Montages et volumes — trois choses différentes

Question fréquente, et facile à confondre. **Un montage n'est pas une
communication** : c'est d'où viennent les fichiers d'un conteneur.

| | Ce que c'est | Chez toi |
|---|---|---|
| **Montage de dossier** | un dossier du disque apparaît dans le conteneur | le code, en développement uniquement |
| **Volume nommé** | un espace géré par Docker, qui **survit** au conteneur | les données PostgreSQL |
| **Volume anonyme** | protège un sous-dossier d'être masqué | `node_modules` du front |

> « En développement, l'image PHP ne contient **pas** le code : l'étage `dev` du
> Dockerfile ne fait aucun `COPY`. Le dossier `/var/www/api` est vide, et le
> montage le remplit. Ce ne sont pas deux copies synchronisées — c'est le même
> fichier, vu depuis deux chemins. »

Le volume anonyme mérite un mot si on te le demande :

> « Monter mon dossier sur `/app` masque le `node_modules` installé au build. Or
> il contient des binaires natifs compilés pour l'architecture de l'image. Le
> volume anonyme sur `/app/node_modules` le remet — et l'ordre de déclaration
> compte. »

### Ce que Docker apporte vraiment

⚠️ Piège : on répond « la reproductibilité » sans savoir de quoi.

| | Reproduit par |
|---|---|
| **Le code** | git |
| **L'environnement** | Docker |

> « Git reproduit le code, Docker reproduit l'environnement : PHP 8.3 avec la
> seule extension nécessaire, PostgreSQL 16, nginx, et le réseau entre eux. Sur
> une machine neuve, trois clones et un `docker compose up` suffisent, sans
> installer PHP ni PostgreSQL.
>
> En production les deux fusionnent : le code entre dans l'image, qui devient un
> objet autonome. C'est pour ça qu'elle n'a aucun montage. »

**La limite, si on creuse** : il manque après un clone les fichiers `.env`, les
clés JWT et les fixtures — tous exclus du dépôt pour de bonnes raisons. Ce n'est
pas une commande unique, et le README le documente.

### Intégration continue

Deux workflows. Côté API : PostgreSQL 16 en service, clés JWT générées, schéma
construit **par les migrations**.

> « Par les migrations et non depuis les entités, pour emprunter le même chemin
> que la production. Une migration oubliée fait donc échouer l'intégration
> continue, pas le déploiement. »

⚠️ **Deux affirmations à ne pas faire :**

| ❌ | ✅ |
|---|---|
| « la CI construit mes images Docker » | elle installe PHP et Node directement. **Aucun `docker build`.** C'est un item encore ouvert — et c'est justement ce qui aurait pu attraper plus tôt la boucle de redirection |
| « si les tests échouent, le push est refusé » | le push réussit toujours. La CI **signale**, elle ne bloque pas. Une règle de protection de branche apporterait ce blocage ; elle n'est pas configurée |

**⚠️ Le passage à raconter absolument** — les quatre défauts trouvés par
l'outillage, section 12.

---

## 12. Gestion de projet et écarts — 3 minutes

**Trois sprints thématiques** : back (29 juin → 5 août), front (6 → 22 août),
infrastructure (22 août). Backlog par dépôt, chaque tâche rattachée à une
exigence du cahier des charges. Journal de décisions écrit.

**L'écart principal, à annoncer sans détour** :

> « Le cahier des charges annonçait une livraison au 1ᵉʳ juillet. Elle n'a pas
> été tenue. J'ai réduit le périmètre plutôt que la qualité : le frontend livre
> l'authentification et la consultation, les autres écrans restent spécifiés et
> maquettés. La décision est datée du 11 août et documentée. »

**Le changement de contexte** :

> « La stratégie de mon entreprise a basculé vers du low-code et l'espace Azure
> a été supprimé. J'ai perdu d'un coup le fournisseur d'identité prévu et la
> cible de déploiement. J'en ai tiré une règle : ne dépendre d'aucun
> fournisseur — authentification JWT autonome, déploiement conteneurisé. J'ai
> ensuite négocié avec mon responsable le maintien de l'environnement pour la
> durée du projet, et je déploie donc sur Azure. Mais l'application n'en dépend
> plus : la cible a changé deux fois sans coûter une ligne de code. »

⚠️ L'ordre compte — contrainte, décision, négociation. Dans cet ordre tu montres
que tu as agi. Dans l'autre, tu racontes juste que ça s'est arrangé.

### Les quatre défauts trouvés par l'outillage

C'est **le meilleur passage de ta soutenance**. Ne le survole pas.

| Trouvé par | Le défaut |
|---|---|
| Tests d'intégration | une requête `LIKE` sur une colonne JSON, invalide en PostgreSQL → 500 à la soumission d'une déclaration |
| Construction de l'image de production | les clés de signature absentes de l'image → 500 après une authentification **réussie** |
| Idem | **une boucle de redirection infinie** entre nginx et Symfony |
| Tests du client HTTP | un verrou de session libéré avant d'être posé → déconnexion une heure plus tard, sans cause apparente |

> « Ces quatre défauts ont un point commun : aucun n'était observable en
> développement. Il a fallu, à chaque fois, construire ou exécuter quelque chose
> de nouveau — une vraie base, une image de production, une suite de tests. Un
> environnement de développement est permissif par nature : ce qu'il ne montre
> pas n'est pas absent, il est invisible. »

Le troisième mérite d'être détaillé si on te laisse le temps : l'application
aurait été **totalement inutilisable en ligne**, et la correction tient en une
ligne de configuration.

---

## 13. Bilan et perspectives — 2 minutes

**Ce qui est livré** — une API fonctionnellement complète, une authentification
de bout en bout, une chaîne de construction et d'intégration qui tourne.

**Ce qui ne l'est pas, et pourquoi** — les écrans reportés, le déploiement non
exécuté. Décisions datées, pas des oublis.

**Les suites, par ordre de priorité** :

| | |
|---|---|
| Court terme | brancher la consultation, puis le formulaire de déclaration |
| Avant mise en ligne | analyse statique côté PHP, pagination des listes, correction de l'export PDF |
| V2 | partie 2 du formulaire HAS (analyse ALARM), fiches RMM, transmission des EIGS |
| Exploitation | déploiement réel chez un hébergeur certifié HDS, avec services managés |

**Ce que j'en retiens** :

> « Le plus instructif n'a pas été d'écrire le code, mais de construire ce qui
> l'entoure. Quelques heures de conteneurisation et de tests ont trouvé un
> défaut bloquant que six mois de développement avaient laissé passer. Je ne
> vois plus l'intégration continue comme une formalité, mais comme un moyen de
> détection. »

---

## 14. Questions probables

Les réponses détaillées sont dans les trois fiches de révision. Les dix qui
tombent le plus souvent :

| Question | Où est la réponse |
|---|---|
| Pourquoi `localStorage` et pas un cookie `HttpOnly` ? | front §5.6 — **exclu par incompatibilité**, pas par préférence : l'API n'authentifie que par l'en-tête `Authorization` |
| Comment gérez-vous l'expiration du jeton ? | front §5 — rafraîchissement **sérialisé**, une seule promesse partagée |
| Pourquoi pas de tests unitaires côté front avant ? | ils existent : 60 + 11 end-to-end |
| Votre application est-elle conforme RGPD / HDS ? | infra §11 — responsabilité partagée, AIPD, contrat HDS |
| Pourquoi trois dépôts ? | infra §7 — `COPY` ne peut pas sortir du contexte de construction |
| Avez-vous déployé ? | **non**, procédure documentée, priorisation assumée |
| Comment testez-vous les migrations ? | la CI construit le schéma par les migrations |
| Qu'est-ce qui empêche un cadre de voir un autre service ? | le Voter, côté serveur — démontrable en `curl` |
| Pourquoi Argon2id ? | défaut Symfony, recommandation ANSSI |
| Comment garantissez-vous l'unicité de la référence ? | séquence PostgreSQL — `COUNT(*)+1` produirait des doublons sous accès concurrent |

---

## 15. Ce qu'il ne faut PAS dire

| ❌ | ✅ |
|---|---|
| « J'ai fait du TDD » | « Les tests sont écrits dans le même commit que la fonctionnalité » — l'historique Git le montrerait |
| « Je suis conforme HDS » | « L'hébergeur est certifié ; la conformité est partagée et suppose d'autres éléments » |
| « J'ai travaillé en Scrum » | « Sprints thématiques, backlog par dépôt, journal de décisions ; pas de cérémonies d'équipe, il n'y a pas d'équipe » |
| « Tout est testé » | « Toute règle à conséquence métier, réglementaire ou de sécurité est testée » |
| « Le front valide les données » | la validation cliente n'est **pas** implémentée |
| « L'image de production est validée » | « Partiellement : le parcours authentifié exige HTTPS, non mis en place localement » |
| « J'ai manqué de temps » *(seul)* | toujours suivi de **ce que tu as arbitré** et **pourquoi** |

---

## 16. Les chiffres à connaître par cœur

```
3      dépôts Git
31     routes d'API
7      entités · 9 migrations
20     composants d'interface écrits à la main
217    tests automatisés   (146 API + 60 front + 11 bout en bout)
4      services conteneurisés
13     décisions au journal
```

Avancement : API **110/115** · front **71/145** · infrastructure **34/53**

Comptes de démonstration — mot de passe `Senticare2026!` :

```
soignant@senticare.fr    → /mes-declarations
cadre@senticare.fr       → /tableau-de-bord     (voit 3 déclarations sur 18)
chefpole@senticare.fr    → /tableau-de-bord
admin@senticare.fr       → /admin/poles         (403 sur toute déclaration)
```

---

## 17. Si ça tourne mal

**La démonstration ne démarre pas** — ne t'acharne pas au-delà d'une minute.
Bascule sur les tests end-to-end : ils prouvent exactement le même parcours, et
les lancer en direct est plus convaincant qu'un écran figé.

**Une question dont tu ignores la réponse** — dis-le, et propose où tu
chercherais. « Je ne sais pas » suivi d'une méthode vaut mieux qu'une
approximation, qui appellera une question de plus.

**On te reproche un manque** — vérifie d'abord s'il est déjà dans ta section
« limites identifiées ». Si oui : « oui, je l'ai identifié, voici pourquoi je
l'ai laissé de côté ». Un manque annoncé n'est plus un reproche.

---

*Fiche établie le 23 août 2026.*

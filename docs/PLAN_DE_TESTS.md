# SentiCare — Plan de tests

Ce document définit ce qui est testé, à quel niveau, sur quel environnement, et
par qui. Il sert de référence au compte rendu de tests (§9).

Il couvre les trois dépôts du projet : `senticare-api-v0`, `senticare-front-v0`
et `senticare-infra`.

---

## 1. Périmètre

Le plan couvre **les fonctionnalités retenues pour la version 1**, telles que
définies au §9.1 du cahier des charges : cycle de vie des déclarations,
qualification de la gravité, périmètre de visibilité par rôle, gestion des
comptes et de la structure, authentification, notifications et export PDF.

Les fonctionnalités reportées en version 2 (analyse ALARM, fiches de revue de
mortalité et de morbidité, transmission des EIGS à la HAS) sont hors périmètre
de ce plan.

---

## 2. Environnements de test

| Environnement | Composition | Usage |
|---|---|---|
| **Local** | Pile Docker Compose complète : PostgreSQL 16, PHP-FPM, nginx, serveur Vite | Exécution pendant le développement, et **seul environnement des tests de bout en bout** |
| **Intégration continue** | GitHub Actions : conteneur PostgreSQL 16 en service, PHP 8.3 avec `pdo_pgsql`, Node 22 | Exécution automatique à chaque poussée |

**Le SGBD des tests est le même que celui de la cible de déploiement.**
PostgreSQL 16 dans les deux environnements — pas SQLite. C'est une décision, pas
une commodité : SQLite accepte des requêtes que PostgreSQL rejette, et un test
qui passe sur un moteur différent de la production donne une confiance fausse.
Ce choix a effectivement révélé un défaut (§9).

**Isolation des tests.** Chaque test s'exécute dans une transaction annulée à la
fin (`dama/doctrine-test-bundle`). Aucun test ne dépend de l'état laissé par un
autre, et la base n'est jamais rechargée entre deux tests.

**Aucune fixture en intégration continue.** Chaque test construit les données
dont il a besoin. Les fixtures ne servent qu'à l'exploration manuelle en local.

**Le schéma est construit par les migrations**, jamais par
`doctrine:schema:create`. C'est ainsi que la base de production sera créée : les
migrations sont donc testées elles aussi, et une migration cassée fait échouer
l'intégration continue plutôt que le déploiement.

---

## 3. Niveaux de tests

| Niveau | Ce qu'il vérifie | Outil | Nombre |
|---|---|---|---|
| **Unitaire** | Une règle métier isolée, dépendances remplacées par des doublures | PHPUnit, Vitest | 82 + 40 |
| **Intégration** | Le dialogue réel avec PostgreSQL — requêtes, contraintes, séquences | PHPUnit | 20 |
| **Fonctionnel (API)** | Un point d'entrée HTTP de bout en bout : authentification, autorisation, code de statut, corps de réponse | PHPUnit + BrowserKit | 44 |
| **Composant (frontend)** | Le rendu et les interactions d'un composant dans un DOM simulé | Vitest + Testing Library | 20 |
| **Bout en bout** | L'application réelle dans un vrai navigateur, contre la vraie API et la vraie base | Playwright | 11 |

**Total : 217 tests automatisés.**

---

## 4. Couverture par fonctionnalité

| Fonctionnalité | Unitaire | Intégration | Fonctionnel | Bout en bout |
|---|---|---|---|---|
| Authentification, jetons, rafraîchissement | ✅ | | ✅ | ✅ |
| Hiérarchie des rôles et héritage | ✅ | | ✅ | |
| Périmètre de visibilité (Voters) | ✅ | | ✅ | |
| Cycle de vie d'une déclaration | ✅ | ✅ | ✅ | |
| Qualification de la gravité | ✅ | | | |
| Référence lisible `DCL-AAAA-NNNN` | | ✅ | | |
| Gestion des comptes utilisateurs | ✅ | | ✅ | |
| Gestion des pôles et des services | ✅ | ✅ | ✅ | |
| Notifications | ✅ | | | |
| Statistiques agrégées | ✅ | | ✅ | |
| Journalisation des actions sensibles | ✅ | ✅ | ✅ | |
| Garde de route et redirection frontend | ✅ | | | ✅ |

**Ce que la colonne « bout en bout » dit de l'état du projet.** Elle n'est
remplie que sur l'authentification et la navigation, parce que le frontend livré
couvre l'authentification et la consultation — les autres écrans sont maquettés
mais non raccordés à l'API (décision D-007). Le plan reflète l'application
réelle, pas l'application attendue.

---

## 5. Tests de non-régression

La non-régression n'est pas une campagne distincte : **l'intégralité des 217
tests est rejouée à chaque poussée**. Un test écrit pour un défaut corrigé
devient de fait un test de non-régression permanent.

**Trois des cinq défauts** recensés au §9 sont couverts par un test permanent
qui échouerait s'ils réapparaissaient : la requête sur colonne JSON (§9.1), le
verrou de rafraîchissement (§9.2) et les dépendances mortes (§9.5, rejouées par
l'analyse statique).

**Les deux autres ne le sont pas.** Les défauts §9.3 et §9.4 tiennent à la
construction et à la configuration de l'image de production, non au code
applicatif : aucun test unitaire ou fonctionnel ne peut les atteindre. Seule une
vérification exécutée **contre l'image de production démarrée** les couvrirait.
C'est une lacune identifiée du plan, reprise au §11 — et la raison pour laquelle
ces deux défauts n'ont été trouvés qu'en construisant réellement l'image.

---

## 6. Tests de sécurité

Les vérifications de sécurité sont intégrées aux niveaux existants plutôt que
traitées à part :

| Vérification | Niveau | Comment |
|---|---|---|
| Accès sans jeton refusé | Fonctionnel | 401 attendu sur les points d'entrée protégés |
| Accès hors périmètre refusé | Unitaire + fonctionnel | 403 attendu, et la ressource n'est jamais transmise |
| Cloisonnement de l'administrateur | Fonctionnel | Aucun accès aux déclarations, quel que soit le chemin |
| Rotation des jetons de rafraîchissement | Fonctionnel | Un nouveau couple est émis, et un jeton déjà utilisé est rejeté |
| Analyse statique | Automatisée | PHPStan niveau 5 côté API, TypeScript strict côté frontend |

**Aucune donnée réelle n'entre dans les tests.** Les jeux de données sont
fictifs et construits par les tests eux-mêmes. C'est une exigence propre au
domaine : un jeu de test issu de données de santé réelles constituerait un
traitement de données personnelles à part entière.

---

## 7. Planification — qui exécute quoi, quand, où

| Quoi | Qui | Quand | Où |
|---|---|---|---|
| Tests unitaires, d'intégration, fonctionnels, de composants | Automatique | À chaque poussée sur `dev`, `master`, `main`, et sur chaque pull request | Intégration continue |
| Analyse statique (PHPStan, TypeScript, oxlint) | Automatique | Idem, **avant** les tests | Intégration continue |
| Construction du livrable | Automatique | Après les tests | Intégration continue |
| Tests de bout en bout | Manuel | Avant chaque étape importante | Local, pile Docker Compose démarrée |

**Une branche dont l'intégration continue échoue n'est pas fusionnée.**

**Limite assumée : les tests de bout en bout ne sont pas automatisés.** Ils
exigent la pile complète démarrée et les fixtures chargées, ce que le workflow
actuel ne fait pas. Leur intégration à la chaîne suppose de démarrer les
services dans le job — c'est l'évolution identifiée du plan.

---

## 8. Hors périmètre du plan — et pourquoi

Déclaré explicitement plutôt que laissé à découvrir.

| Type de test | Statut | Motif |
|---|---|---|
| **Tests de charge** | Non réalisés | Supposent un environnement de production dimensionné et un profil de charge réel. Aucun des deux n'existe pour un projet de formation. |
| **Fuzzing** | Non réalisé | Recherche de failles par entrées aléatoires. Identifié comme une suite pertinente, notamment sur le formulaire de déclaration. |
| **Tests d'acceptation** | Non réalisés | Supposent des utilisateurs réels de l'établissement. Le client étant fictif, ils n'ont pas pu être conduits. |
| **Couverture de code mesurée** | Non mesurée | L'instrumentation ralentit la suite d'un facteur 2 à 3, et aucun seuil défendable n'a été fixé. Le nombre de tests dit ce qui est vérifié, pas ce qui ne l'est pas. |

---

## 9. Compte rendu — anomalies trouvées

Cinq défauts ont été détectés par la chaîne de tests et d'analyse. Aucun n'était
observable en développement courant.

### 9.1 Requête `LIKE` sur une colonne JSON

**Trouvé par** — les tests d'intégration, sur PostgreSQL 16 réel.
**Symptôme** — erreur 500 à la soumission d'une déclaration.
**Cause** — une requête `LIKE` appliquée à une colonne de type JSON. Valide en
SQLite, rejetée par PostgreSQL.
**Correction** — requête réécrite.
**Ce que ça démontre** — tester sur le moteur de la cible n'est pas une
précaution théorique.

### 9.2 Verrou de rafraîchissement libéré avant d'être posé

**Trouvé par** — les tests unitaires du client HTTP frontend.
**Symptôme** — déconnexion environ une heure après la connexion, sans cause
apparente, non reproductible à la demande.
**Cause** — le `finally` chargé de libérer le verrou de rafraîchissement était
placé à l'intérieur de la fonction asynchrone. Sur le chemin sans jeton de
rafraîchissement, la fonction retourne avant toute attente : le `finally`
s'exécutait donc de façon synchrone, remettant la variable à `null` **avant**
que la promesse n'y soit affectée. Le verrou restait bloqué sur une promesse
déjà résolue.
**Correction** — libération attachée à la promesse elle-même, donc différée.
**Ce que ça démontre** — un défaut de concurrence ne se voit pas à l'usage ; il
se voit quand on écrit le test qui force le cas limite.

### 9.3 Clés de signature absentes de l'image de production

**Trouvé par** — la première exécution de l'image de production.
**Symptôme** — erreur 500 **après une authentification réussie**, alors qu'un
mauvais mot de passe renvoyait correctement 401.
**Cause** — les clés JWT ne sont pas versionnées, donc absentes de l'image.
**Correction** — montage en lecture seule par l'orchestration.
**Ce que ça démontre** — le symptôme désignait l'authentification, la cause était
dans la construction de l'image.

### 9.4 Boucle de redirection infinie

**Trouvé par** — la première exécution de l'image de production.
**Symptôme** — l'application redirige indéfiniment.
**Cause** — la configuration Symfony force HTTPS en production, mais la liaison
entre nginx et PHP-FPM est en clair : Symfony ne voyait pas que la requête
d'origine était sécurisée et redirigeait sans fin.
**Correction** — transmission de l'information à PHP-FPM par un paramètre
FastCGI.
**Ce que ça démontre** — ce défaut aurait rendu l'application inutilisable en
ligne, et rien en développement ne pouvait le révéler.

### 9.5 Deux dépendances injectées et jamais utilisées

**Trouvé par** — PHPStan, niveau 5.
**Symptôme** — aucun. Le code fonctionnait.
**Cause** — deux services recevaient un repository qu'ils n'utilisaient pas.
**Correction** — retirées du constructeur, ainsi que les doublures
correspondantes dans les tests.
**Ce que ça démontre** — les tests vérifient le comportement, l'analyse statique
vérifie la structure. Aucun test ne pouvait détecter ce défaut, puisqu'il
n'altérait aucun comportement.

---

## 10. Veille — évolutions et risques propres aux tests

CP9 demande que le plan tienne compte des évolutions techniques et des
problématiques de sécurité liées aux tests. Quatre points ont été identifiés et
appliqués.

**Les secrets d'intégration continue sont un risque de test, pas seulement
d'exploitation.** Un secret stocké dans une plateforme de CI est exposé aux
journaux, aux dépendances de la chaîne et aux dépôts dérivés. Le workflow de
l'API n'utilise **aucun secret** : la paire de clés JWT est régénérée à chaque
exécution avec une passphrase aléatoire, et les jetons signés pendant les tests
sont vérifiés dans la même exécution. Rien n'a besoin de survivre.

**Tester sur un moteur de base différent de la cible produit une confiance
fausse.** C'est un piège connu des suites rapides. Le choix de PostgreSQL 16 en
intégration continue coûte quelques secondes par exécution et a évité un défaut
en production (§9.1).

**L'isolation par transaction annulée remplace le rechargement de base.** Elle
supprime la dépendance entre tests, qui est la première cause de suites
instables, sans le coût d'un rechargement complet.

**Les jeux de données de test ne doivent jamais provenir de données réelles.**
Sur une application de santé, un extrait de production utilisé comme jeu de test
serait un traitement de données personnelles soumis au RGPD, dans un
environnement moins protégé que la production. Les données du projet sont
intégralement fictives.

---

## 11. Évolutions du plan

Par ordre de valeur décroissante :

1. **Automatiser les tests de bout en bout** dans l'intégration continue, en
   démarrant la pile Docker au sein du job. Cela couvrirait du même coup les
   deux défauts d'image aujourd'hui sans test de non-régression (§5).
2. **Ajouter le fuzzing du formulaire de déclaration**, qui est la surface
   d'entrée la plus exposée.
3. **Mesurer la couverture** une fois un seuil défendable défini — un seuil
   arbitraire produit des tests écrits pour l'indicateur.
4. **Conduire des tests d'acceptation** si l'application est présentée à des
   soignants.

---

*Plan de tests — projet de fin d'études CDA Niveau 6 — 29 août 2026.*

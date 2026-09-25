# SentiCare — Sprints et backlogs

Vue d'ensemble de l'organisation du travail : le découpage en sprints, le
backlog de chacun, son critère de sortie et son résultat.

Ce document **synthétise** les trois feuilles de route, qui restent la source de
vérité au niveau de la tâche :

| Backlog détaillé | Dépôt |
|---|---|
| [`docs/ROADMAP.md`](ROADMAP.md) | `senticare-api-v0` |
| `docs/ROADMAP.md` | `senticare-front-v0` |
| `docs/ROADMAP.md` | `senticare-infra` |

Décisions structurantes : [`DECISIONS.md`](DECISIONS.md) ·
Évolutions du cahier des charges : CDC §12.

> Chiffres arrêtés au **22 août 2026**.

---

## Méthode

Projet mené **en solo sur 6 mois**, organisé en **sprints thématiques
successifs** : chacun livre un ensemble cohérent et utilisable avant que le
suivant ne s'ouvre.

Ce séquencement n'est pas arbitraire. Le frontend ne pouvait pas être conçu
avant que le contrat d'API soit stable ; la conteneurisation supposait les deux
applications existantes.

### Ce qui est appliqué

| Pratique | Où elle se vérifie |
|---|---|
| **Backlog par sprint** | `docs/ROADMAP.md`, tâches à cocher, **chacune référencée à une exigence du CDC** (UC-xx, §x) |
| **Définition de terminé** | Critère de réussite explicite par étape (sprint 3), marqueurs de complétion par section (sprints 1 et 2) |
| **Re-priorisation en cours de route** | Réduction du périmètre frontend, décidée et datée le 11/08/2026 |
| **Rétrospective écrite** | `DECISIONS.md` — 13 décisions avec contexte, alternatives écartées, conséquence |
| **Traçabilité** | Commits *Conventional Commits*, corps expliquant le pourquoi |

Une tâche non rattachée à une exigence du CDC est une tâche à justifier.

### Ce qui est écarté, et pourquoi

- **Sprints à durée fixe** — les sprints sont bornés par un **périmètre**, pas
  par un calendrier. Sans engagement envers un tiers, une date de fin arbitraire
  n'aurait discipliné personne.
- **Cérémonies collectives** (mêlée quotidienne, revue, rétrospective d'équipe)
  — elles synchronisent une équipe. Il n'y en a pas.
- **Vélocité et points de complexité** — la mesure n'a de sens que comparée à un
  historique d'équipe. Seule, elle produit un chiffre sans référentiel.

Le principe qui remplace les cérémonies : **toute décision et tout écart
laissent une trace dans le dépôt.**

---

## Sprint 0 — Analyse et conception

**Mars → juin 2026** · Livrable : le cahier des charges

| Backlog | |
|---|---|
| Contexte métier, acteurs, rôles | CDC §1, §3 |
| Cas d'utilisation (UC-01 à UC-13) | CDC §4.1 |
| Assistant de qualification de la gravité, critères HAS | CDC §4.2 |
| Modèle de données | CDC §5 |
| Exigences de sécurité | CDC §6 |
| Choix de la pile technique | CDC §7.1 |
| User stories | CDC §8 |

**Résultat** — CDC v3.0 validé, puis v4.0 et v4.1 en juillet : transfert de la
gestion des services au chef de pôle (D-003), report des RMM et de l'analyse
ALARM en V2 (D-004).

---

## Sprint 1 — Back · API REST ✅

**29 juin → 5 août 2026** · **110 / 115 tâches**

> **Critère de sortie** — les 146 tests passent, les 31 endpoints sont conformes
> au CDC.

### Backlog

| Thème | Réf. CDC | Tâches | État |
|---|---|---|---|
| Authentification | §6.1 | 6 / 6 | ✅ |
| Entités et énumérations | §5 | 2 / 2 | ✅ |
| Structure clinique — pôles et services | UC-12, UC-13 | 23 / 23 | ✅ |
| Gestion des comptes | UC-10, US-1.2, US-1.3 | 19 / 19 | ✅ |
| Cycle de vie des déclarations | §4.2–§4.4, UC-01→07 | 20 / 20 | ✅ |
| Notifications email | UC-08, UC-09, US-3.2 | 4 / 4 | ✅ |
| Tableau de bord superviseur | §4.6, US-3.1 | 7 / 7 | ✅ |
| Journal d'audit | §6.3, UC-11 | 16 / 16 | ✅ |
| Ajouts pour le frontend | — | 13 / 18 | 🔨 |

### Résultat

31 endpoints · 7 entités · 9 migrations · **146 tests, 300 assertions** ·
17 commits.

Les 5 tâches ouvertes du dernier thème sont de la **dette identifiée**, pas du
travail en cours : elles sont listées en fin de document.

### Ce que ce sprint a produit d'inattendu

Trois blocages non prévus au découpage initial, tous levés et tracés dans le
tableau « Blocages connus » de la feuille de route — dont une requête
`roles LIKE` invalide en PostgreSQL, jamais détectée parce que jamais exécutée
contre une vraie base avant l'activation des tests d'intégration.

---

## Sprint 2 — Front · Authentification ✅

**6 → 22 août 2026** · **53 / 121 tâches**

> **Critère de sortie** — parcours d'authentification complet : connexion,
> session persistante, rafraîchissement transparent, gardes de route par rôle.

### Backlog

| Thème | Tâches | État |
|---|---|---|
| Fondation du projet (Vite, TypeScript, Sass) | 6 / 7 | 🔨 |
| Types et contrat d'API | 4 / 4 | ✅ |
| Stockage des jetons | 3 / 3 | ✅ |
| Client HTTP (erreurs, rafraîchissement sérialisé) | 7 / 7 | ✅ |
| Modules d'endpoints | 1 / 7 | 🔨 |
| Contexte de session et hooks | 2 / 4 | 🔨 |
| Domaine (règles métier dupliquées, CDC §6.3) | 1 / 4 | 🔨 |
| Routage et gardes par rôle | 4 / 4 | ✅ |
| Composants d'interface | 21 / 22 | 🔨 |
| Écrans | 0 / 31 | ⬜ |

### Résultat

20 composants d'interface · couche HTTP et authentification complètes ·
7 commits.

Vérifié de bout en bout côté API le 22/08 : connexion, `/api/me`,
rafraîchissement, cloisonnement par rôle, CORS. **Le parcours navigateur reste à
valider.**

### Re-priorisation du 11 août

Constat : le temps restant ne permet pas de livrer les six espaces fonctionnels.
Décision — réduire le **périmètre**, pas la qualité (D-007).

| Retenu | Reporté, spécifié et maquetté |
|---|---|
| Authentification complète | Wizard de déclaration (UC-01→05) |
| Consultation des déclarations | Tableau de bord superviseur (§4.6) |
| | Gestion des comptes et services (UC-10) |
| | Administration et journal d'audit (UC-11, UC-12) |
| | Export PDF (UC-07) |

Les composants d'affichage de ces écrans **sont écrits** ; seul le branchement
aux données et aux routes manque. Les 31 tâches du thème « Écrans » relèvent
donc en grande partie de ce report, non d'un retard.

---

## Sprint 3 — Infra · Conteneurisation et CI ✅

**22 août 2026** · **34 / 53 tâches**

> **Critères de sortie** — `docker compose up` démarre l'application ;
> `docker compose -f compose.yaml up --build` sert le tout sur un seul port.

### Backlog

| Étape | Critère | Tâches | État |
|---|---|---|---|
| A — l'API en conteneur | `curl /api/login_check` renvoie un jeton | 10 / 11 | ✅ vérifiée |
| B — le front en développement | `localhost:5173` répond | 6 / 8 | ✅ vérifiée |
| C — l'image de production | une seule origine, pas de CORS | 10 / 22 | ⚠️ partielle |
| D — intégration continue | deux workflows | 4 / 6 | ✍️ écrite, non exécutée |
| Documentation du dépôt | README, schémas | 1 / 2 | 🔨 |

### Résultat

4 services conteneurisés · images figées sur une version mineure · 2 workflows
d'intégration continue · 1 commit.

### Ce que ce sprint a révélé

Construire l'image de production a mis au jour **deux défauts qu'aucun test en
développement ne pouvait montrer** :

1. **Clés JWT absentes de l'image** — volontairement exclues (une clé privée n'a
   rien à faire dans une image publiable), mais rien ne les fournissait à
   l'exécution. L'authentification aboutissait, seule la signature échouait.
2. **Boucle de redirection infinie** — nginx n'annonçait pas le schéma de la
   connexion à php-fpm. Avec `forced_ssl` actif en production, l'application
   aurait redirigé indéfiniment vers `https://`. **Elle aurait été inutilisable
   en ligne.**

Les deux sont corrigés. C'est le principal enseignement du projet : quelques
heures de conteneurisation ont trouvé un défaut que six mois de développement
avaient laissé passer.

---

## Sprint 4 — Front · Consultation 🔨 en cours

> **Critère de sortie** — se connecter en soignante, voir ses déclarations ; se
> reconnecter en cadre, voir celles de son service.

| Backlog | |
|---|---|
| `api/declarations.ts` — fonction de liste | à écrire |
| `domain/gravite.ts` — couleurs et textes imposés (CDC §4.2) | à écrire |
| `domain/statut.ts` — transitions autorisées | à écrire |
| `hooks/useApiQuery.ts` | à écrire |
| Écran « Mes déclarations » | à brancher |

Sprint court : les composants d'affichage existent, l'API renvoie déjà les
données avec le bon cloisonnement. Une seule séquence de démonstration y
exercera l'authentification, le métier et la règle de périmètre du CDC §3.

---

## Synthèse

| Sprint | Période | Avancement | État |
|---|---|---|---|
| 0 — Analyse et conception | mars → juin | — | ✅ |
| 1 — Back | 29 juin → 5 août | **110 / 115 (95 %)** | ✅ |
| 2 — Front, authentification | 6 → 22 août | **53 / 121 (43 %)** | ✅ |
| 3 — Infra | 22 août | **34 / 53 (64 %)** | ✅ partiel |
| 4 — Front, consultation | à venir | — | 🔨 |

Le dénominateur du sprint 2 couvre **l'intégralité du périmètre V1**, écrans
volontairement reportés compris.

---

## Dette identifiée, non résorbée

Connue, tracée, non corrigée. Une dette documentée se planifie ; une dette
ignorée se découvre en production.

| Réf | Point | Gravité |
|---|---|---|
| T-01 | Export PDF vraisemblablement non fonctionnel — le template Twig référence des propriétés inexistantes | Élevée |
| T-02 | Aucune pagination ni tri sur les listes de déclarations | Élevée à terme |
| T-03 | Fuseau horaire — sérialisation UTC, affichage décalé de 2 h en été | Moyenne |
| T-04 | `PATCH /api/declarations/{id}` : 500 au lieu de 422 sur un type invalide | Faible |
| T-05 | `/api/services` sérialise le service sans son pôle | Faible |
| T-06 | Absence de tests automatisés côté frontend | Moyenne |

---

*Document de suivi — projet de fin d'études CDA Niveau 6. Dernière mise à jour
le 22 août 2026.*

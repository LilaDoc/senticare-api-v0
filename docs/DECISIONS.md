# SentiCare — Journal des décisions

Registre des décisions structurantes du projet : ce qui a été décidé, pourquoi,
ce qui a été écarté, et ce que ça a coûté.

Il complète le CDC, qui décrit **l'état cible**, et le §12 du CDC, qui trace les
**versions du document**. Ici on trace les **choix**, y compris ceux qui ont été
révisés.

> ⚠️ Les dates marquées *(à confirmer)* n'ont pas pu être établies depuis les
> dépôts — le CDC antérieur à v3.0 et l'historique git initial ne les portent
> pas. À corriger avant remise du dossier.

| Réf | Décision | Date | Statut |
|---|---|---|---|
| [D-001](#d-001) | Authentification par Microsoft Entra ID | *(à confirmer)* | ❌ Abandonnée — D-008 |
| [D-002](#d-002) | Déploiement sur Azure | *(à confirmer)* | ❌ Abandonnée — D-009 |
| [D-003](#d-003) | Gestion des services confiée au chef de pôle | Juillet 2026 | ✅ Actée |
| [D-004](#d-004) | Report des RMM et de l'analyse ALARM en V2 | Juillet 2026 | ✅ Actée |
| [D-005](#d-005) | Référence lisible `DCL-AAAA-NNNN` | 5 août 2026 | ✅ Actée |
| [D-006](#d-006) | Sass + CSS Modules à la place de Tailwind | Août 2026 | ✅ Actée |
| [D-007](#d-007) | Réduction du périmètre pour la soutenance | 11 août 2026 | ✅ Actée |
| [D-008](#d-008) | Authentification JWT autonome | 22 août 2026 | ✅ Actée |
| [D-009](#d-009) | Hébergeur choisi sur le critère HDS | 22 août 2026 | ✅ Actée |
| [D-010](#d-010) | Trois dépôts, Dockerfile dans le dépôt applicatif | Août 2026 | ✅ Actée |
| [D-011](#d-011) | Refus des dépendances non nécessaires | Août 2026 | ✅ Actée |
| [D-012](#d-012) | Secrets sortis des fichiers versionnés | Août 2026 | ✅ Actée |
| [D-013](#d-013) | TLS local reporté, validation prod partielle | 22 août 2026 | ⏸️ Reportée (optionnelle) |

---

## D-001

### Authentification par Microsoft Entra ID — ❌ abandonnée

**Contexte** — L'entreprise d'accueil disposait d'un environnement Azure. Une
authentification fédérée via Microsoft Entra ID était envisagée : SSO, MFA et
cycle de vie des comptes délégués à l'annuaire de l'organisation.

**Décision** — Non retenue au final. Voir [D-008](#d-008).

**Ce qu'on y perdait** — SSO, MFA fournie, gestion centralisée des comptes. Ce
sont de vrais avantages dans une organisation qui dispose déjà de l'annuaire.

**Ce qu'on y gagne** — L'application ne dépend plus d'un tenant : elle reste
déployable dans un établissement sans infrastructure Microsoft, ce qui est le
cas d'usage visé par le CDC. Une intégration Entra ID resterait possible en
évolution, le pare-feu Symfony acceptant plusieurs authenticateurs.

---

## D-002

### Déploiement sur Azure — ❌ abandonné

**Contexte** — Cible naturelle tant que l'espace Azure de l'entreprise était
disponible.

**Décision** — Abandonné. Voir [D-009](#d-009).

---

## D-003

### Gestion des services confiée au chef de pôle

**Date** — Juillet 2026 · CDC v4.0–4.1

**Contexte** — La création et la modification des services relevaient
initialement de l'administrateur.

**Décision** — Transférée au chef de pôle, pour son propre pôle uniquement
(UC-12).

**Motif** — L'administrateur n'a pas la connaissance métier de l'organisation
d'un pôle. Centraliser la saisie chez lui crée un goulot d'étranglement sans
apporter de contrôle réel.

**Conséquence** — Un Voter supplémentaire : le chef de pôle n'agit que dans son
périmètre.

---

## D-004

### Report des RMM et de l'analyse ALARM en V2

**Date** — Juillet 2026 · CDC v4.0–4.1, §9.2

**Contexte** — Le formulaire HAS complet comporte une seconde partie (analyse
ALARM, causes profondes, barrières, plan d'actions), et le dispositif RMM
suppose planification, participants et comptes rendus.

**Décision** — Reportés en V2.

**Motif** — Six mois, projet solo. Livrer une chaîne de déclaration complète et
soignée vaut mieux qu'une couverture fonctionnelle large et superficielle.

---

## D-005

### Référence lisible `DCL-AAAA-NNNN`

**Date** — 5 août 2026 · CDC v4.2, §4.3 et §5.1

**Contexte** — Les déclarations sont identifiées par UUID. Un UUID ne se dicte
pas au téléphone et ne se reporte pas sur un compte rendu de revue.

**Décision** — Chaque déclaration reçoit une référence lisible dès le brouillon,
générée par une **séquence PostgreSQL** (`nextval`).

**Alternative écartée** — `COUNT(*) + 1`, qui produit des doublons dès que deux
déclarations sont créées simultanément. La séquence est atomique par
construction.

**Conséquence de sécurité** — La référence n'est **jamais** utilisée comme
identifiant d'URL : cela réintroduirait la faille d'énumération que les UUID
préviennent (CDC §6.2).

**Décision liée** — L'intitulé de déclaration est composé automatiquement. Un
champ libre appellerait des formulations identifiantes (« Chute de M. Dupont
chambre 12 »), incompatibles avec le RGPD (§6.3), et ajouterait une saisie
contraire à l'objectif des 3 minutes.

---

## D-006

### Sass + CSS Modules à la place de Tailwind

**Date** — Août 2026 · acté au CDC en v4.3

**Contexte** — Le frontend avait démarré avec Tailwind CSS.

**Décision** — Refonte en Sass (SCSS) + CSS Modules.

**Motif** — Les CSS Modules isolent les styles par composant sans convention de
nommage à respecter : deux fichiers peuvent utiliser la même classe sans
collision. Les jetons de design sont centralisés dans `_tokens.scss` — couleurs
en propriétés CSS personnalisées, thématisables à l'exécution ; mesures en
variables Sass, résolues à la compilation.

**Écart constaté** — Le CDC a continué d'annoncer Tailwind jusqu'à la v4.3. Écart
documentaire corrigé le 22 août 2026.

---

## D-007

### Réduction du périmètre pour la soutenance

**Date** — 11 août 2026

**Contexte** — Le temps restant avant la soutenance ne permettait pas de câbler
l'ensemble des écrans du frontend.

**Décision** — Le frontend livré couvre **l'authentification et la lecture des
déclarations**. Les autres écrans restent spécifiés et maquettés, mais non
raccordés à l'API.

**Motif** — Une chaîne complète et fonctionnelle, du formulaire de connexion à
l'affichage des données, démontre mieux la maîtrise qu'un ensemble d'écrans
partiellement câblés.

**Conséquence** — Écart à annoncer explicitement dans le dossier et à l'oral. Le
CDC continue de décrire le périmètre V1 **attendu** ; la section « Suivi des
tâches et écarts » du dossier porte le périmètre **réalisé**.

---

## D-008

### Authentification JWT autonome

**Date** — 22 août 2026 · CDC v4.3, §6.1 et §12.2 · remplace [D-001](#d-001)

**Contexte** — La stratégie de l'entreprise d'accueil a évolué vers une approche
entièrement low-code (Power Apps, Code Apps). L'espace Azure a été supprimé,
faisant disparaître simultanément le fournisseur d'identité prévu et la cible de
déploiement.

**Décision** — Authentification JWT autonome : LexikJWTAuthenticationBundle,
hachage Argon2id, refresh tokens à usage unique (rotation), blocage temporaire
après 5 tentatives, messages d'erreur génériques contre l'énumération de
comptes.

**Le principe qui en découle** — *ne dépendre d'aucun fournisseur*. Cette règle
gouverne aussi [D-009](#d-009).

**Conséquence** — L'application fonctionne sans annuaire externe. Aucune trace
d'OAuth, d'Entra ou d'Azure ne subsiste dans le code — vérifié par recherche sur
les trois dépôts.

**Objection anticipée** — *« Entra ID n'aurait-il pas été préférable pour un
établissement de santé ? »* Oui, dans une organisation qui dispose déjà de
l'annuaire. Mais cela rend l'application dépendante d'un tenant. Pour un produit
destiné à des cliniques dont l'infrastructure est inconnue, l'authentification
autonome est le défaut raisonnable.

---

## D-009

### Hébergeur choisi sur le critère HDS

**Date** — 22 août 2026 · CDC v4.3, §7.1 et §12.2 · remplace [D-002](#d-002)

**Contexte** — Le CDC prévoyait Digital Ocean depuis la v3.0, retenu sur des
critères de coût et de simplicité. La contrainte réglementaire n'avait pas été
identifiée.

**Constat** — Les déclarations contiennent des données de santé à caractère
personnel. Leur hébergement impose un hébergeur **certifié HDS** (art. L.1111-8
du Code de la santé publique). Digital Ocean ne l'est pas.

**Décision** — **OVHcloud (VPS)** : certifié HDS, et hébergeur français, donc
hors du champ du Cloud Act — réserve régulièrement soulevée par la CNIL pour les
données de santé.

**Alternatives** — Azure et AWS sont certifiés HDS mais restent soumis au droit
américain. Azure était par ailleurs indisponible ([D-008](#d-008)).

**Limite assumée** — La certification couvre **six activités** en responsabilité
partagée. Sur un VPS auto-administré, l'exploitation du système et les
sauvegardes (activités 4 et 5) restent à la charge de l'exploitant. Une mise en
production réelle supposerait des services managés, un contrat HDS explicite et
une analyse d'impact (AIPD).

**Vérification obtenue** — Le passage d'une cible d'hébergement à l'autre n'a
nécessité **aucune modification applicative**, uniquement documentaire. C'est la
portabilité annoncée par la conteneurisation, constatée en conditions réelles.

> À vérifier avant remise : le périmètre exact de la certification d'OVHcloud sur
> la liste officielle de l'Agence du Numérique en Santé (esante.gouv.fr).

---

## D-010

### Trois dépôts, Dockerfile dans le dépôt applicatif

**Date** — Août 2026

**Contexte** — L'API et le frontend sont dans deux dépôts git distincts. Une
orchestration qui démarre les deux ne peut appartenir ni à l'un ni à l'autre.

**Décision** — Un troisième dépôt `senticare-infra` porte l'orchestration.
**Mais chaque Dockerfile vit dans le dépôt de son application.**

**Contrainte technique** — `COPY` ne peut lire que dans le contexte de
construction, qui doit être le dépôt applicatif. Élargir le contexte au dossier
parent enverrait le `node_modules` du frontend à chaque build.

**Contrepartie assumée** — Les trois dossiers doivent être côte à côte, les
`build.context` pointant vers `../`. Cloner `senticare-infra` seul ne suffit pas.

**Alternative écartée** — Les sous-modules git : complexité non justifiée pour
trois dépôts et une seule personne.

---

## D-011

### Refus des dépendances non nécessaires

**Date** — Août 2026

**Contexte** — Les Dockerfile de référence trouvés en ligne installent
systématiquement un jeu d'extensions PHP et d'optimisations.

**Décision** — Ne retenir que ce dont l'application a besoin, après vérification.

| Écarté | Vérification |
|---|---|
| `ext-intl` | aucun paquet ne la réclame dans `composer.lock`, non chargée en local, 146 tests au vert |
| `ext-zip` | le binaire `unzip` suffit à Composer |
| `opcache` | optimisation, pas prérequis |
| `--optimize --classmap-authoritative` | optimisation, non mesurée sur ce projet |

**Conservé** — `pdo_pgsql` uniquement, sans laquelle Doctrine échoue sur « could
not find driver ».

**Motif** — Chaque dépendance est une ligne à justifier, du temps de
construction et une surface à maintenir. Le critère appliqué est constant :
**ce qui n'est pas nécessaire et n'a pas été mesuré ne rentre pas.** Les refus
sont documentés dans le Dockerfile lui-même.

---

## D-012

### Secrets sortis des fichiers versionnés

**Date** — Août 2026 · CDC §6.3

**Contexte** — `APP_SECRET` et `JWT_PASSPHRASE` figuraient dans des fichiers
`.env` versionnés.

**Décision** — Déplacés dans des fichiers `.local`, non versionnés. Passphrase et
paire de clés JWT **régénérées**, les anciennes étant à considérer comme
compromises.

**Répartition retenue** —

| Contexte | Origine des secrets |
|---|---|
| Développement | fichiers `.local`, gitignorés |
| Intégration continue | secrets GitHub Actions |
| Production | variables d'environnement du serveur |

**Vérifié** — Une variable d'environnement réelle l'emporte toujours sur les
fichiers `.env` de Symfony, y compris `.env.local` (contrôlé avec
`debug:dotenv`). C'est ce qui rend sûr le montage du code dans le conteneur de
développement.

**Piège rencontré** — `.env.$APP_ENV` est chargé **après** `.env.local`. Vider
une variable dans `.env` ne suffit donc pas : il faut la supprimer.

---

## D-013

### TLS local reporté, validation de l'image de production partielle

**Date** — 22 août 2026

**Contexte** — La construction et l'exécution de l'image de production ont
révélé deux défauts, tous deux corrigés :

1. les clés JWT, volontairement exclues de l'image, n'étaient fournies par rien
   à l'exécution — l'authentification réussissait puis échouait en 500 à la
   signature du jeton ;
2. nginx n'annonçait pas le schéma de la connexion à php-fpm. Avec `forced_ssl`
   actif en environnement prod, Symfony croyait répondre en HTTP et redirigeait
   vers `https://…` — **boucle de redirection infinie** en déploiement réel.

**Le point bloquant** — L'image de production exige HTTPS (CDC §6.3). Le
parcours authentifié n'est donc pas vérifiable sur `http://localhost`.

**Décision** — **Reporter** la mise en place du TLS local, sans l'abandonner.
Valider ce qui est vérifiable en HTTP, documenter le reste comme non constaté.
La procédure est écrite et chiffrée dans `senticare-infra/docs/ROADMAP.md`
(étape C, section optionnelle) : elle sera exécutée si le dossier de projet est
terminé avant la soutenance.

**Motif du report plutôt que de l'abandon** — c'est le seul moyen de *constater*
la correction de la boucle de redirection, et le coût est faible (une trentaine
de minutes). Mais c'est un bonus de vérification, pas un livrable : le priorité
va au dossier.

**Alternative écartée** —

| | Raison |
|---|---|
| Désactiver `forced_ssl` pour le test | Aurait validé une configuration qui n'est pas celle de production. **Un test qui ment est pire que pas de test.** |

**Ce qui est validé** — nginx sert le bundle, repli SPA, 404 franc sur asset
manquant, en-têtes de cache, `server_tokens off`, et surtout : **aucun appel
cross-origin**, vérifié dans le bundle servi (zéro occurrence de
`localhost:8000`). L'API répond à travers nginx — le 401 sur mauvais
identifiants le prouve.

**Ce qui ne l'est pas** — Le parcours authentifié complet en environnement prod.
La correction de la boucle de redirection est **raisonnée, non constatée**.

**À dire tel quel** — *« J'ai construit et exécuté l'image de production. Elle a
révélé deux défauts que le développement ne pouvait pas montrer, dont une boucle
de redirection qui aurait rendu l'application inutilisable en ligne. Les deux
sont corrigés. La vérification du parcours authentifié demande du TLS local ; la
procédure est écrite, je ne l'ai pas exécutée faute de temps. Je le signale
plutôt que de laisser croire que tout est vérifié. »*

---

*Journal tenu dans le cadre du projet de fin d'études CDA Niveau 6 — dernière
mise à jour le 22 août 2026.*

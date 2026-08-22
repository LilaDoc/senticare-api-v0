# TODO Sécurité — CDC §6.3

Liste de suivi des points sécurité identifiés lors de la revue "qu'est-ce qu'il manque pour que le back soit carré" (2026-08-01), pas encore traités. Cocher au fur et à mesure comme dans `ROADMAP.md`.

---

## [x] Headers HTTP de sécurité (2026-08-03)

CDC §6.3 (tableau OWASP, ligne "Mauvaise configuration") exige explicitement :
```
Content-Security-Policy, X-Frame-Options, HSTS, X-Content-Type-Options
```
Fait via `nelmio/security-bundle` (`config/packages/nelmio_security.yaml`) :
- `clickjacking: DENY` → `X-Frame-Options: DENY`
- `content_type: nosniff: true` → `X-Content-Type-Options: nosniff`
- `csp.enforce: default-src 'none', frame-ancestors 'none'` → API JSON pure, aucune ressource (script/style/image/frame) n'a de raison d'être chargée par un navigateur depuis cette origine
- `forced_ssl` (HSTS, `max-age=31536000`, `includeSubDomains`) activé **uniquement en `when@prod`** — en HTTP local (dev/test) le header serait sans effet et gênant

Vérifié manuellement (`curl -i`) : les 4 headers sont bien présents sur une réponse réelle (`/api/me`). Le nonce CSP visible en dev (`script-src 'unsafe-inline' 'nonce-...'`) vient de la Web Debug Toolbar (profiler) qui s'auto-intègre à nelmio_security ; il disparaît en prod puisque le profiler y est désactivé. 138 tests toujours au vert après ajout.

## [x] CORS (2026-08-03)

CDC §7.2/§7.3 : SPA React + API REST, mais un seul nginx en reverse proxy devant php-fpm et le build React (§7.3) — en prod, front et API sont donc censés être sur la **même origine** (routage par chemin), donc CORS n'y est pas strictement nécessaire. En revanche en **dev**, Vite (ex: `:5173`) et Symfony (`:8000`) sont deux ports différents = deux origines différentes → CORS requis.

Fait via `nelmio/cors-bundle` (`config/packages/nelmio_cors.yaml`), origines autorisées pilotées par `CORS_ALLOW_ORIGIN` (`.env`) :
```
CORS_ALLOW_ORIGIN='^https?://(localhost|127\.0\.0\.1)(:[0-9]+)?$'
```
Restreint à localhost par défaut ; à resurchargée dans `.env.local`/`.env.prod.local` si jamais le déploiement final utilise deux domaines distincts. Vérifié manuellement : preflight `OPTIONS` depuis `http://localhost:5173` → headers CORS présents ; requête depuis une origine arbitraire (`https://evil.example.com`) → aucun header CORS renvoyé (le navigateur bloquerait l'appel).

## [x] Audit rapide RGPD (2026-08-03, CDC §6.3)

> "RGPD : aucune donnée patient nominative stockée."

Relu les 5 champs texte libre de `Declaration` : `description`, `lieuDifferentDetail`, `consequencesAutresDetail`, `mesuresImmediatesPatientDetail`, `autresMesures`. Aucun n'est structurellement dédié à un nom de patient (ce sont des champs de circonstances/mesures), mais rien n'empêche techniquement un soignant d'en écrire un par réflexe — c'est un risque de saisie, pas un défaut de modélisation.

**Conclusion** : pas de correctif de code pertinent ici (on ne peut pas filtrer un nom propre de façon fiable côté serveur sans risquer des faux positifs sur des mots courants). Aucune doc utilisateur n'existe encore dans ce projet — à noter pour une future version : ajouter un texte d'aide sous ces champs côté frontend ("ne pas mentionner de nom de patient") au moment où le formulaire React sera développé. Point de process/formation, pas de faille technique.

---

## [x] Erreurs API toujours en JSON, même sans `Accept` explicite (2026-08-04)

Trouvé en vérifiant le comportement réel avant de basculer sur le front : une route inexistante ou un `createNotFoundException()` (utilisé partout dans les Controllers) renvoyait une page **HTML** si le client n'envoyait pas explicitement `Accept: application/json` — or `fetch()` envoie `Accept: */*` par défaut, pas `application/json`. Le front aurait planté sur `response.json()` face à du HTML.

Fait : `ApiExceptionSubscriber` (`src/EventSubscriber/ApiExceptionSubscriber.php`), écouteur `kernel.exception` priorité `-10` (après `Security\ExceptionListener` à `1` qui gère déjà les 401/403 en JSON, avant l'`ErrorListener` par défaut de Symfony à `-128` qui rendrait du HTML). Force une réponse JSON `{"code": ..., "message": ...}` pour toute exception non déjà transformée en réponse sur `/api/*`. Message réel exposé pour les `HttpExceptionInterface` (404, 400... — toujours volontaires côté client), générique (`"Erreur interne du serveur."`) en prod pour tout le reste (bug non prévu → pas de fuite de trace/message interne), message réel gardé en dev/test pour debug.

Vérifié manuellement : `/api/nawak` avec `Accept: */*` → JSON (avant : HTML) ; `/api/me` sans token → toujours `{"code":401,"message":"JWT Token not found"}` inchangé (le listener Security garde la main). 138 tests toujours verts.

## Déjà fait (pour mémoire, ne pas refaire)

- [x] Headers d'auth (JWT Bearer), Argon2id, login throttling — CDC §6.1, fait.
- [x] Voters sur chaque route sensible, UUID v4 anti-énumération — CDC §6.2, fait.
- [x] Injection SQL — ORM Doctrine partout sauf 2 exceptions SQL natif déjà paramétrées (`UserRepository`, `DeclarationRepository::aggregate()` parPeriode) — CDC §6.3, fait.
- [x] `symfony/validator` adopté pour la contrainte `description` (CDC §4.3) — 2026-08-01.

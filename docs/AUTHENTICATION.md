# Authentification — Senticare API

## Principe général

Authentification JWT stateless (LexikJWTAuthenticationBundle), conforme CDC §6.1 :
mot de passe haché **Argon2id**, tokens d'accès courts + refresh token rotatif.

```
Front (React)
   │  POST /api/login_check { email, password }
   ▼
Firewall "login" (json_login) → UserProvider::loadUserByIdentifier(email)
   │  vérifie le hash Argon2id du mot de passe
   ▼
Réponse { token, refresh_token }
   │
   │  Requêtes suivantes : header Authorization: Bearer <token>
   ▼
Firewall "api" (jwt authenticator) → UserProvider::loadUserByIdentifier(email)
   ▼
App\Entity\User (roles stockés en base)
   ▼
Controller (ex: SecurityController::me()) via #[CurrentUser]
```

## Routes

| Route | Méthode | Description |
|---|---|---|
| `/api/login_check` | POST | `{ email, password }` → `{ token, refresh_token }`. Throttling : 5 tentatives / minute (IP + email), au-delà 429 générique. |
| `/api/token/refresh` | POST | `{ refresh_token }` → nouveau `{ token, refresh_token }`. Refresh token à usage unique (rotation à chaque appel). |
| `/api/me` | GET | Identité + rôles de l'utilisateur courant (nécessite `Authorization: Bearer <token>`). |
| `/api/logout` | POST | Invalide le refresh token fourni (interceptée par le firewall, jamais exécutée par le contrôleur). |

## Sécurité (CDC §6.1)

- Hachage **Argon2id** (`config/packages/security.yaml` → `password_hashers`).
- Token JWT access : 1h (par défaut LexikJWTBundle).
- Refresh token : 30 jours, à usage unique (rotation), révocable via `/api/logout`.
- Login throttling : 5 tentatives échouées / minute par IP+email, message d'erreur générique dans tous les cas (`Invalid credentials.`) — aucune distinction entre mauvais mot de passe, compte inexistant ou compte bloqué (protection énumération, OWASP A07).
- Clés JWT RSA générées via `bin/console lexik:jwt:generate-keypair` (non versionnées, `config/jwt/*.pem`).

## Provisioning des utilisateurs

Aucune inscription libre : un compte est créé par un chef de pôle (soignants/cadres)
ou un administrateur (chefs de pôle), avec un mot de passe provisoire (CDC US-1.2).
`UserProvider::loadUserByIdentifier()` cherche par email ; si absent ou `isActive = false`,
retourne une erreur générique (pas de détail sur l'existence du compte).

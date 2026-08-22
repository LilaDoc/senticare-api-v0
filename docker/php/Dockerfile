# =============================================================================
# Image PHP-FPM de l'API SentiCare
# =============================================================================
#
# Deux cibles dans un seul fichier :
#   dev   code monté depuis l'hôte, dépendances de développement incluses
#   prod  code copié dans l'image, dépendances optimisées
#
# Compose choisit avec `target: dev` ou `target: prod`.
#
# Construction manuelle :
#   docker build --target dev -f docker/php/Dockerfile -t senticare-api:dev .
#
# =============================================================================
# Socle commun
# =============================================================================
# Variante Alpine : ~80 Mo contre ~400 pour la variante Debian. Le compromis
# est la bibliothèque C (musl au lieu de glibc), qui peut poser problème avec
# certaines extensions natives — pas avec `pdo_pgsql`, universellement testée.
FROM php:8.3-fpm-alpine AS base

# Doit correspondre EXACTEMENT à la racine configurée dans nginx
# (`root /var/www/api/public`). Une incohérence donne un « File not found »
# que rien n'explique.
WORKDIR /var/www/api

# -----------------------------------------------------------------------------
# Extensions PHP — une seule est nécessaire
# -----------------------------------------------------------------------------
# `composer.json` n'exige que ext-ctype et ext-iconv, toutes deux compilées
# d'office dans l'image officielle. Reste pdo_pgsql, sans laquelle Doctrine
# échoue sur « could not find driver ».
#
# Écartées après vérification : ext-zip (le binaire unzip suffit à Composer),
# ext-intl (aucun paquet ne la réclame), opcache (optimisation, pas prérequis).
#
# Tout tient dans UN SEUL `RUN` : chaque instruction crée une couche, et
# installer puis supprimer dans deux RUN distincts laisserait les paquets de
# compilation dans la couche intermédiaire — l'image ne maigrirait pas.
RUN apk add --no-cache libpq git unzip \
 && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS postgresql-dev \
 && docker-php-ext-install pdo_pgsql \
 && apk del .build-deps
# `libpq` est installé hors du groupe virtuel : c'est la bibliothèque dont
# pdo_pgsql a besoin à l'EXÉCUTION, elle doit survivre au `apk del`.
# `postgresql-dev` n'apporte que les en-têtes de compilation.

# Récupéré depuis l'image officielle plutôt qu'installé : plus court, et
# toujours la dernière version stable.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Prépare l'application au démarrage (attente de la base, migrations) puis
# passe la main. ENTRYPOINT s'exécute toujours, CMD est ce qu'il lance à la fin.
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]

# =============================================================================
# Développement
# =============================================================================
FROM base AS dev

COPY docker/php/php-dev.ini /usr/local/etc/php/conf.d/senticare.ini

# Aucun COPY du code, et aucun `composer install` : compose montera le dossier
# de l'hôte par-dessus, ce qui masquerait tout ce qu'on aurait installé ici.
# C'est l'entrypoint qui lance `composer install` si `vendor/` est absent.

# =============================================================================
# Production
# =============================================================================
FROM base AS prod

# Lues par Symfony au démarrage. Les définir ici évite de dépendre d'un
# fichier .env dans l'image.
ENV APP_ENV=prod \
    APP_DEBUG=0

COPY docker/php/php-prod.ini /usr/local/etc/php/conf.d/senticare.ini

# -----------------------------------------------------------------------------
# L'ordre des COPY est le point le plus important du fichier
# -----------------------------------------------------------------------------
# Docker met chaque instruction en cache et ne rejoue que ce qui suit un
# changement. En copiant les fichiers de dépendances SEULS puis en installant,
# le `composer install` n'est rejoué que si composer.lock change — pas à chaque
# modification d'un fichier PHP.
#
# Dans l'ordre inverse, la moindre correction de code relancerait une
# installation complète : plusieurs minutes à chaque construction.
COPY composer.json composer.lock symfony.lock ./

# --no-scripts --no-autoloader : les scripts Symfony (vidage du cache,
# installation des assets) ont besoin du code, qui n'est pas encore copié.
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-progress

COPY . .

# Maintenant que le code est là, on génère l'autoloader et on exécute les
# scripts reportés.
#
# Cette ligne est OBLIGATOIRE : `--no-autoloader` ci-dessus a empêché la
# génération, et sans `vendor/autoload_runtime.php` l'application ne démarre
# pas du tout (public/index.php le requiert en première ligne).
#
# `--no-dev` : sans lui, Composer ajouterait la règle `App\Tests\ → tests/`,
# un dossier exclu par .dockerignore — une entrée qui pointerait dans le vide.
#
# Écarté : `--optimize --classmap-authoritative`, qui remplace la résolution
# PSR-4 sur disque par une table figée classe → fichier. C'est de
# l'optimisation, au même titre qu'opcache, et elle n'a pas été mesurée ici.
RUN composer dump-autoload --no-dev \
 && composer run-script post-install-cmd

# Symfony écrit son cache et ses journaux dans var/. Sans ces droits, l'API
# renvoie un 500 dès la première requête — c'est la cause n°1 des démarrages
# ratés.
#
# Inutile de créer un utilisateur dédié : le processus maître de php-fpm tourne
# en root (il en a besoin pour se lier au port), mais les processus qui
# EXÉCUTENT le PHP tournent déjà en `www-data`. Le code applicatif n'est donc
# jamais exécuté avec les pleins droits.
RUN mkdir -p var && chown -R www-data:www-data var

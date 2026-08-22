#!/bin/sh
# =============================================================================
# Point d'entrée du conteneur PHP
# =============================================================================
# Exécuté à chaque démarrage, AVANT php-fpm. Prépare l'application puis lui
# passe la main.

# Arrête le script à la première erreur. Sans cette ligne, une migration qui
# échoue laisserait php-fpm démarrer quand même et servir des 500 sur une base
# incohérente. Mieux vaut un conteneur qui refuse de démarrer : l'erreur est
# visible immédiatement.
set -e

# -----------------------------------------------------------------------------
# 1. Dépendances — développement uniquement
# -----------------------------------------------------------------------------
# En dev, compose monte le code de l'hôte par-dessus l'image, ce qui masque
# tout `vendor/` qu'on aurait installé à la construction. On l'installe donc
# ici, au premier démarrage seulement.
# En production, `vendor/` est dans l'image : ce bloc ne s'exécute jamais.
if [ ! -f vendor/autoload_runtime.php ]; then
  echo "→ vendor/ absent, installation des dépendances…"
  composer install --no-interaction --prefer-dist --no-progress
fi

# -----------------------------------------------------------------------------
# 2. Attendre PostgreSQL
# -----------------------------------------------------------------------------
# `depends_on: condition: service_healthy` couvre déjà ce cas dans compose,
# mais cette boucle rend le conteneur autonome — il se débrouille aussi lancé
# seul, ou si la base redémarre.
#
# Le compteur est indispensable : une boucle infinie sur une base mal
# configurée donne un conteneur qui « démarre » sans jamais aboutir, et
# personne ne comprend pourquoi.
echo "→ attente de la base de données…"
tentatives=0
until php bin/console dbal:run-sql "SELECT 1" >/dev/null 2>&1; do
  tentatives=$((tentatives + 1))
  if [ "$tentatives" -ge 30 ]; then
    echo "✗ base injoignable après 30 secondes, abandon." >&2
    exit 1
  fi
  sleep 1
done

# -----------------------------------------------------------------------------
# 3. Migrations
# -----------------------------------------------------------------------------
# `--allow-no-migration` : sans lui, la commande renvoie un code d'erreur quand
# il n'y a rien à appliquer — ce qui ferait échouer tous les redémarrages après
# le premier, à cause du `set -e` ci-dessus.
#
# ⚠️ Migrer au démarrage est pratique et acceptable ici (une seule instance).
#    Avec plusieurs instances, elles migreraient en parallèle : la migration
#    deviendrait alors une étape séparée du pipeline de déploiement.
echo "→ application des migrations…"
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

# -----------------------------------------------------------------------------
# 4. Passer la main
# -----------------------------------------------------------------------------
# `exec` REMPLACE le shell par la commande reçue (php-fpm, défini par CMD) au
# lieu de la lancer comme processus enfant.
#
# C'est essentiel : php-fpm devient le processus 1 du conteneur et reçoit
# directement les signaux d'arrêt de Docker. Sans `exec`, un `docker compose
# down` tuerait le shell, et php-fpm serait terminé brutalement sans laisser
# les requêtes en cours se finir.
echo "→ démarrage de php-fpm"
exec "$@"

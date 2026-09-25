<?php

namespace App\Repository;

use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshTokenRepository as BaseRefreshTokenRepository;

/**
 * Repository des refresh tokens.
 *
 * Pas d'annotation `@extends ...<RefreshToken>` : la classe parente du bundle
 * Gesdinet n'est pas générique, l'annotation serait fausse (relevé par PHPStan).
 */
class RefreshTokenRepository extends BaseRefreshTokenRepository
{
}

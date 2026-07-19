<?php

namespace App\Security\Voter;

use App\Entity\Declaration;
use App\Entity\User;
use App\Enum\RoleEnum;
use App\Enum\StatutEnum;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Contrôle d'accès par périmètre sur les déclarations (CDC §3, §4.4, §6.2).
 *
 * - Soignant   : uniquement ses propres déclarations.
 * - Cadre      : déclarations des services auxquels il est rattaché.
 * - Chef pôle  : déclarations de tous les services de son pôle.
 * - Admin      : aucun accès — interdiction explicite (CDC §3, US-1.3).
 */
class DeclarationVoter extends Voter
{
    // Les "attributs" sont les noms qu'on utilise dans le code appelant, ex:
    // $this->denyAccessUnlessGranted(DeclarationVoter::VIEW, $declaration).
    // Ce sont juste des chaînes de caractères, mais les mettre en constantes
    // évite les fautes de frappe et permet l'autocomplétion.
    public const VIEW = 'DECLARATION_VIEW';
    public const EDIT = 'DECLARATION_EDIT';
    public const ABANDON = 'DECLARATION_ABANDON';
    public const SUBMIT = 'DECLARATION_SUBMIT';
    public const CHANGE_STATUT = 'DECLARATION_CHANGE_STATUT';

    private const ATTRIBUTES = [
        self::VIEW,
        self::EDIT,
        self::ABANDON,
        self::SUBMIT,
        self::CHANGE_STATUT,
    ];

    /**
     * Symfony appelle cette méthode EN PREMIER, pour chaque Voter enregistré,
     * à chaque fois que le code fait un denyAccessUnlessGranted(...) ou isGranted(...).
     * Elle répond juste "est-ce que JE sais juger cette combinaison attribut/objet ?"
     * Si elle renvoie false, Symfony ne m'appelle même pas et passe au Voter suivant.
     * Ici : je ne sais juger que VIEW/EDIT/ABANDON/SUBMIT/CHANGE_STATUT, et
     * seulement si l'objet concerné est bien une Declaration.
     */
    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, self::ATTRIBUTES, true) && $subject instanceof Declaration;
    }

    /**
     * Appelée seulement si supports() a renvoyé true. C'est ici qu'on répond
     * vraiment "oui" (true) ou "non" (false) à la question posée.
     * $token contient l'utilisateur actuellement connecté (accessible via getUser()).
     */
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            // Personne n'est connecté (ou ce n'est pas un compte applicatif) -> refus.
            return false;
        }

        // $subject est typé "mixed" dans la signature de la méthode (Symfony peut
        // voter sur n'importe quel type d'objet, pas seulement Declaration). On sait
        // que c'est une Declaration parce que supports() l'a déjà vérifié juste avant
        // -- ce commentaire /** @var */ ne fait rien à l'exécution, il sert juste à
        // l'IDE pour qu'il propose l'autocomplétion des méthodes de Declaration.
        /** @var Declaration $declaration */
        $declaration = $subject;

        // Un match = un gros switch qui renvoie une valeur. On redirige vers la
        // méthode privée correspondant à l'attribut demandé.
        return match ($attribute) {
            self::VIEW => $this->canView($declaration, $user),
            self::EDIT => $this->canEdit($declaration, $user),
            self::ABANDON => $this->canAbandon($declaration, $user),
            self::SUBMIT => $this->canSubmit($declaration, $user),
            self::CHANGE_STATUT => $this->canChangeStatut($declaration, $user),
            default => false,
        };
    }

    /**
     * Soignant : uniquement s'il est le déclarant.
     * Cadre / chef de pôle : uniquement si la déclaration appartient à leur périmètre.
     * Admin : jamais.
     */
    private function canView(Declaration $declaration, User $user): bool
    {
        if ($this->hasRole($user, RoleEnum::Admin)) {
            // CDC §3 : interdiction explicite, on sort tout de suite, peu importe le reste.
            return false;
        }

        if ($this->hasRole($user, RoleEnum::ChefPole) || $this->hasRole($user, RoleEnum::Cadre)) {
            // Superviseur : la règle dépend du périmètre, déléguée à une méthode dédiée.
            return $this->isInSupervisorPerimeter($declaration, $user);
        }

        // Reste : un simple soignant. Comparaison d'objets avec "===" : vrai
        // seulement si $declaration->getDeclarant() est EXACTEMENT le même
        // utilisateur (même ligne en base), pas juste un compte similaire.
        return $declaration->getDeclarant() === $user;
    }

    /**
     * Uniquement le déclarant, et uniquement tant que statut === Brouillon (CDC §4.4).
     */
    private function canEdit(Declaration $declaration, User $user): bool
    {
        return $declaration->getDeclarant() === $user
            && StatutEnum::Brouillon === $declaration->getStatut();
    }

    /**
     * Uniquement le déclarant, uniquement depuis Brouillon (UC-04 — action irréversible).
     * Même règle que canEdit(), donc on la réutilise plutôt que de la recopier.
     */
    private function canAbandon(Declaration $declaration, User $user): bool
    {
        return $this->canEdit($declaration, $user);
    }

    /**
     * Uniquement le déclarant, uniquement depuis Brouillon (UC-05). Même règle que canEdit().
     */
    private function canSubmit(Declaration $declaration, User $user): bool
    {
        return $this->canEdit($declaration, $user);
    }

    /**
     * Cadre / chef de pôle uniquement, dans leur périmètre respectif — la validité de
     * la transition elle-même (en_analyse -> cloturee, etc.) est vérifiée par
     * StatutEnum::transitionsAutorisees() côté DeclarationManager, pas ici (le Voter
     * ne s'occupe QUE du "qui a le droit", pas du "est-ce que l'action a un sens").
     */
    private function canChangeStatut(Declaration $declaration, User $user): bool
    {
        if (!$this->hasRole($user, RoleEnum::ChefPole) && !$this->hasRole($user, RoleEnum::Cadre)) {
            return false;
        }

        return $this->isInSupervisorPerimeter($declaration, $user);
    }

    /**
     * Chef de pôle : la déclaration appartient à un service de son pôle.
     * Cadre : la déclaration appartient à l'un des services auxquels il est rattaché.
     *
     * Convention (documentée dans docs/ROADMAP.md) : User n'a pas de relation
     * directe vers Pole, donc pour un chef de pôle on déduit son pôle via SES
     * PROPRES services (il est rattaché à tous les services de son pôle).
     */
    private function isInSupervisorPerimeter(Declaration $declaration, User $user): bool
    {
        if ($this->hasRole($user, RoleEnum::ChefPole)) {
            // Le "?->" (nullsafe) évite une erreur si getService() renvoyait null.
            $pole = $declaration->getService()?->getPole();

            // On parcourt tous les services du chef de pôle : s'il en trouve UN SEUL
            // qui appartient au même pôle que le service de la déclaration, c'est
            // que la déclaration est dans son périmètre -> on peut arrêter là (return
            // dans la boucle) sans avoir besoin de vérifier les autres services.
            foreach ($user->getServices() as $service) {
                if ($service->getPole() === $pole) {
                    return true;
                }
            }

            return false;
        }

        // Cadre : plus simple, pas de notion de pôle à traverser. contains() vérifie
        // juste si le service de la déclaration fait partie de la Collection des
        // services du cadre (comparaison stricte d'objets, comme ===).
        return $user->getServices()->contains($declaration->getService());
    }

    /**
     * Petit helper pour éviter de répéter in_array(...->value, $user->getRoles(), true)
     * partout. getRoles() renvoie les rôles BRUTS de l'entité (ex: ['ROLE_CADRE']),
     * sans la hiérarchie de security.yaml (ROLE_CHEF_POLE hérite de ROLE_CADRE...) --
     * ici on veut justement savoir le rôle EXACT de l'utilisateur, pas ses rôles hérités.
     */
    private function hasRole(User $user, RoleEnum $role): bool
    {
        return in_array($role->value, $user->getRoles(), true);
    }
}

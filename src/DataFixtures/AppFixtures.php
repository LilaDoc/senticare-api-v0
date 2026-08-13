<?php

namespace App\DataFixtures;

use App\Entity\Declaration;
use App\Entity\Pole;
use App\Entity\Service;
use App\Entity\User;
use App\Enum\GraviteEnum;
use App\Enum\RoleEnum;
use App\Enum\StatutEnum;
use App\Enum\TypeEIEnum;
use App\Service\ReferenceGenerator;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Jeu de données de démonstration reproduisant la structure de la clinique
 * Sainte-Élisabeth (CDC §1.1) : 4 pôles, 9 services, 32 comptes.
 *
 * Répartition des comptes (CDC §10) :
 *   1 administrateur
 * + 1 chef de pôle par pôle          (4)
 * + 1 cadre de santé par service     (9)
 * + 2 soignants par service          (18)
 * = 32 comptes, tous au mot de passe self::PASSWORD.
 *
 * Convention structurante (il n'existe pas de relation directe User -> Pole) :
 * le pôle d'un chef de pôle est déduit de ses services, il est donc rattaché à
 * TOUS les services de son pôle. Sans ça son périmètre est vide et le tableau
 * de bord ne renvoie rien.
 */
class AppFixtures extends Fixture
{
    /** Mot de passe commun à tous les comptes de démo — haché Argon2id (CDC §6.1). */
    private const PASSWORD = 'Senticare2026!';

    /**
     * Structure clinique complète. Les 4 emails historiques
     * (admin@ / chefpole@ / cadre@ / soignant@) sont conservés tels quels pour
     * ne pas casser les habitudes de test ; les autres sont dérivés du slug du
     * service.
     */
    private const STRUCTURE = [
        [
            'pole' => 'Chirurgie',
            'chef' => ['email' => 'chefpole@senticare.fr', 'nom' => 'Martin', 'prenom' => 'Paul'],
            'services' => [
                [
                    'nom' => 'Bloc opératoire',
                    'slug' => 'bloc-operatoire',
                    'cadre' => ['email' => 'cadre@senticare.fr', 'nom' => 'Bernard', 'prenom' => 'Sophie'],
                    'soignants' => [
                        ['email' => 'soignant@senticare.fr', 'nom' => 'Dupont', 'prenom' => 'Marie'],
                        ['nom' => 'Lefèvre', 'prenom' => 'Thomas'],
                    ],
                ],
                [
                    'nom' => 'Chirurgie ambulatoire',
                    'slug' => 'chirurgie-ambulatoire',
                    'cadre' => ['nom' => 'Roux', 'prenom' => 'Camille'],
                    'soignants' => [
                        ['nom' => 'Girard', 'prenom' => 'Julien'],
                        ['nom' => 'Moreau', 'prenom' => 'Léa'],
                    ],
                ],
                [
                    'nom' => 'Chirurgie conventionnelle',
                    'slug' => 'chirurgie-conventionnelle',
                    'cadre' => ['nom' => 'Fontaine', 'prenom' => 'Nadia'],
                    'soignants' => [
                        ['nom' => 'Perrin', 'prenom' => 'Antoine'],
                        ['nom' => 'Blanc', 'prenom' => 'Sarah'],
                    ],
                ],
            ],
        ],
        [
            'pole' => 'Soins Intensifs',
            'chef' => ['nom' => 'Chevalier', 'prenom' => 'Hélène'],
            'services' => [
                [
                    'nom' => 'USI',
                    'slug' => 'usi',
                    'cadre' => ['nom' => 'Barbier', 'prenom' => 'Olivier'],
                    'soignants' => [
                        ['nom' => 'Renaud', 'prenom' => 'Inès'],
                        ['nom' => 'Vasseur', 'prenom' => 'Karim'],
                    ],
                ],
            ],
        ],
        [
            'pole' => 'Oncologie',
            'chef' => ['nom' => 'Leroy', 'prenom' => 'Vincent'],
            'services' => [
                [
                    'nom' => 'Hôpital de jour',
                    'slug' => 'hopital-de-jour',
                    'cadre' => ['nom' => 'Marchand', 'prenom' => 'Aurélie'],
                    'soignants' => [
                        ['nom' => 'Da Silva', 'prenom' => 'Rui'],
                        ['nom' => 'Colin', 'prenom' => 'Manon'],
                    ],
                ],
                [
                    'nom' => 'Médecine interne',
                    'slug' => 'medecine-interne',
                    'cadre' => ['nom' => 'Guillot', 'prenom' => 'Pierre'],
                    'soignants' => [
                        ['nom' => 'Aubert', 'prenom' => 'Fatima'],
                        ['nom' => 'Noël', 'prenom' => 'Gabriel'],
                    ],
                ],
            ],
        ],
        [
            'pole' => 'Maternité',
            'chef' => ['nom' => 'Dubois', 'prenom' => 'Claire'],
            'services' => [
                [
                    'nom' => 'Urgences maternité',
                    'slug' => 'urgences-maternite',
                    'cadre' => ['nom' => 'Lemoine', 'prenom' => 'Sonia'],
                    'soignants' => [
                        ['nom' => 'Bertrand', 'prenom' => 'Hugo'],
                        ['nom' => 'Cordier', 'prenom' => 'Amélie'],
                    ],
                ],
                [
                    'nom' => 'Hospitalisation',
                    'slug' => 'hospitalisation',
                    'cadre' => ['nom' => 'Rey', 'prenom' => 'Bruno'],
                    'soignants' => [
                        ['nom' => 'Hamon', 'prenom' => 'Clara'],
                        ['nom' => 'Pichon', 'prenom' => 'Yanis'],
                    ],
                ],
                [
                    'nom' => 'Néonatalogie',
                    'slug' => 'neonatalogie',
                    'cadre' => ['nom' => 'Tkachenko', 'prenom' => 'Olena'],
                    'soignants' => [
                        ['nom' => 'Faure', 'prenom' => 'Louis'],
                        ['nom' => 'Berger', 'prenom' => 'Naïma'],
                    ],
                ],
            ],
        ],
    ];

    /**
     * Déclarations de démonstration.
     *
     * Couvrent volontairement TOUS les statuts et TOUTES les gravités, étalées
     * sur plusieurs mois : le tableau de bord agrège parPeriode sur
     * dateConstat, sans étalement les graphiques n'auraient qu'une barre.
     *
     * Wording blameless (CDC §2.1) : on décrit ce qui est survenu, jamais qui a
     * fauté. Aucun nom de patient (RGPD, docs/TODO_SECURITE.md).
     */
    private const DECLARATIONS = [
        [
            'service' => 'bloc-operatoire',
            'typeEI' => TypeEIEnum::Materiovigilance,
            'gravite' => GraviteEnum::Modere,
            'statut' => StatutEnum::Cloturee,
            'dateConstat' => '2026-03-12 09:20:00',
            'dateSurvenue' => '2026-03-12 08:45:00',
            'description' => "Un défaut d'étanchéité a été constaté sur un insufflateur en cours d'intervention. Le matériel a été remplacé immédiatement et retiré du circuit.",
            'mesuresImmediatesPatient' => true,
            'mesuresImmediatesPatientDetail' => "Surveillance renforcée jusqu'à la fin de l'intervention, constantes stables.",
            'autresMesures' => 'Signalement au service biomédical, matériel mis en quarantaine.',
        ],
        [
            'service' => 'bloc-operatoire',
            'typeEI' => TypeEIEnum::Infection,
            'gravite' => GraviteEnum::Grave,
            'statut' => StatutEnum::EnAnalyse,
            'dateConstat' => '2026-06-04 14:10:00',
            'dateSurvenue' => '2026-06-02 07:00:00',
            'description' => "Une infection du site opératoire est survenue en post-opératoire immédiat, avec reprise chirurgicale nécessaire.",
            'consequencesAutres' => false,
            'mesuresImmediatesPatient' => true,
            'mesuresImmediatesPatientDetail' => 'Antibiothérapie adaptée après prélèvement, reprise au bloc le jour même.',
            'mesuresImmediatesProches' => true,
        ],
        [
            'service' => 'bloc-operatoire',
            'typeEI' => TypeEIEnum::Autre,
            'gravite' => GraviteEnum::Mineur,
            'statut' => StatutEnum::Brouillon,
            'dateConstat' => '2026-08-03 11:00:00',
            'dateSurvenue' => '2026-08-03 10:30:00',
            'description' => "Un retard de programmation a décalé le début de l'intervention, sans conséquence clinique constatée.",
        ],
        [
            'service' => 'chirurgie-ambulatoire',
            'typeEI' => TypeEIEnum::ErreurMedicament,
            'gravite' => GraviteEnum::Modere,
            'statut' => StatutEnum::Soumise,
            'dateConstat' => '2026-07-21 16:40:00',
            'dateSurvenue' => '2026-07-21 16:00:00',
            'description' => "Une dose d'antalgique supérieure au protocole a été administrée en salle de réveil, détectée lors de la double vérification.",
            'mesuresImmediatesPatient' => true,
            'mesuresImmediatesPatientDetail' => 'Surveillance prolongée en SSPI, aucun effet indésirable observé.',
            'autresMesures' => "Rappel du protocole de double contrôle en réunion d'équipe.",
        ],
        [
            'service' => 'chirurgie-ambulatoire',
            'typeEI' => TypeEIEnum::Chute,
            'gravite' => GraviteEnum::Mineur,
            'statut' => StatutEnum::Cloturee,
            'dateConstat' => '2026-04-18 10:05:00',
            'dateSurvenue' => '2026-04-18 09:55:00',
            'description' => "Une chute de sa hauteur est survenue au lever, sans traumatisme apparent après examen clinique.",
            'mesuresImmediatesPatient' => true,
            'mesuresImmediatesPatientDetail' => 'Examen clinique immédiat, surveillance neurologique sur 4 heures.',
        ],
        [
            'service' => 'chirurgie-conventionnelle',
            'typeEI' => TypeEIEnum::Chute,
            'gravite' => GraviteEnum::Grave,
            'statut' => StatutEnum::EnAnalyse,
            'dateConstat' => '2026-05-09 03:30:00',
            'dateSurvenue' => '2026-05-09 03:15:00',
            'description' => "Une chute nocturne au passage du lit au fauteuil a entraîné une fracture nécessitant une prise en charge orthopédique.",
            'consequencesAutres' => false,
            'mesuresImmediatesPatient' => true,
            'mesuresImmediatesPatientDetail' => 'Immobilisation, imagerie en urgence, avis orthopédique dans l\'heure.',
            'mesuresImmediatesProches' => true,
            'autresMesures' => 'Réévaluation du risque de chute pour les patients du secteur.',
        ],
        [
            'service' => 'chirurgie-conventionnelle',
            'typeEI' => TypeEIEnum::Autre,
            'gravite' => GraviteEnum::Mineur,
            'statut' => StatutEnum::Abandonnee,
            'dateConstat' => '2026-06-28 13:00:00',
            'dateSurvenue' => '2026-06-28 12:30:00',
            'description' => "Déclaration ouverte par erreur, l'événement décrit relève du circuit de maintenance et non de la déclaration d'EI.",
        ],
        [
            'service' => 'usi',
            'typeEI' => TypeEIEnum::Materiovigilance,
            'gravite' => GraviteEnum::Critique,
            'statut' => StatutEnum::EnAnalyse,
            'dateConstat' => '2026-07-02 22:15:00',
            'dateSurvenue' => '2026-07-02 22:10:00',
            'description' => "Un arrêt inopiné d'un respirateur est survenu la nuit, avec mise en jeu du pronostic vital et relais immédiat par ventilation manuelle.",
            'consequencesAutres' => false,
            'mesuresImmediatesPatient' => true,
            'mesuresImmediatesPatientDetail' => 'Ventilation manuelle immédiate puis remplacement du respirateur en moins de deux minutes.',
            'mesuresImmediatesProches' => true,
            'autresMesures' => "Retrait du matériel, signalement à l'ANSM en cours, vérification du parc du service.",
        ],
        [
            'service' => 'usi',
            'typeEI' => TypeEIEnum::Infection,
            'gravite' => GraviteEnum::Deces,
            'statut' => StatutEnum::TransmiseHAS,
            'dateConstat' => '2026-05-27 06:45:00',
            'dateSurvenue' => '2026-05-24 00:00:00',
            'description' => "Une infection nosocomiale d'évolution rapide est survenue chez un patient immunodéprimé et a conduit au décès malgré la prise en charge.",
            'consequencesAutres' => false,
            'mesuresImmediatesPatient' => true,
            'mesuresImmediatesPatientDetail' => 'Antibiothérapie probabiliste puis adaptée, mesures de réanimation maximales.',
            'mesuresImmediatesProches' => true,
            'autresMesures' => "Renforcement des précautions complémentaires, revue de morbi-mortalité programmée.",
        ],
        [
            'service' => 'usi',
            'typeEI' => TypeEIEnum::ErreurMedicament,
            'gravite' => GraviteEnum::Modere,
            'statut' => StatutEnum::Soumise,
            'dateConstat' => '2026-08-01 05:20:00',
            'dateSurvenue' => '2026-08-01 05:00:00',
            'description' => "Une confusion entre deux seringues de présentation proche a été détectée avant administration complète du produit.",
            'mesuresImmediatesPatient' => true,
            'mesuresImmediatesPatientDetail' => 'Arrêt immédiat de la perfusion, surveillance hémodynamique rapprochée.',
            'autresMesures' => 'Étiquetage différencié mis en place le jour même.',
        ],
        [
            'service' => 'hopital-de-jour',
            'typeEI' => TypeEIEnum::ErreurMedicament,
            'gravite' => GraviteEnum::Grave,
            'statut' => StatutEnum::Cloturee,
            'dateConstat' => '2026-04-03 11:30:00',
            'dateSurvenue' => '2026-04-03 10:45:00',
            'description' => "Un écart de dosage sur une cure de chimiothérapie a été identifié après le début de la perfusion, avec surveillance hématologique renforcée sur plusieurs semaines.",
            'consequencesAutres' => false,
            'mesuresImmediatesPatient' => true,
            'mesuresImmediatesPatientDetail' => 'Arrêt de la perfusion, avis oncologique immédiat, bilan biologique de contrôle.',
            'mesuresImmediatesProches' => false,
            'autresMesures' => 'Informatisation du contrôle de dose validée avec la pharmacie.',
        ],
        [
            'service' => 'hopital-de-jour',
            'typeEI' => TypeEIEnum::Autre,
            'gravite' => GraviteEnum::Mineur,
            'statut' => StatutEnum::Soumise,
            'dateConstat' => '2026-07-15 09:10:00',
            'dateSurvenue' => '2026-07-15 08:50:00',
            'description' => "Une rupture de stock temporaire de dispositifs de perfusion a retardé le démarrage des cures de la matinée.",
            'autresMesures' => 'Dépannage par le service voisin, réajustement du seuil de réapprovisionnement.',
        ],
        [
            'service' => 'medecine-interne',
            'typeEI' => TypeEIEnum::Chute,
            'gravite' => GraviteEnum::Modere,
            'statut' => StatutEnum::EnAnalyse,
            'dateConstat' => '2026-06-11 19:40:00',
            'dateSurvenue' => '2026-06-11 19:30:00',
            'description' => "Une chute est survenue dans le couloir sur un sol récemment nettoyé, avec contusion sans fracture à l'imagerie.",
            'mesuresImmediatesPatient' => true,
            'mesuresImmediatesPatientDetail' => 'Examen clinique, radiographie de contrôle, antalgie de palier 1.',
            'autresMesures' => 'Repositionnement de la signalétique de sol mouillé.',
        ],
        [
            'service' => 'medecine-interne',
            'typeEI' => TypeEIEnum::Infection,
            'gravite' => GraviteEnum::Modere,
            'statut' => StatutEnum::Brouillon,
            'dateConstat' => '2026-08-04 15:00:00',
            'dateSurvenue' => '2026-08-03 20:00:00',
            'description' => "Une infection urinaire associée aux soins a été diagnostiquée après pose de sonde, actuellement en cours de documentation.",
        ],
        [
            'service' => 'urgences-maternite',
            'typeEI' => TypeEIEnum::Autre,
            'gravite' => GraviteEnum::Critique,
            'statut' => StatutEnum::Soumise,
            'dateConstat' => '2026-07-28 02:05:00',
            'dateSurvenue' => '2026-07-28 01:50:00',
            'description' => "Un retard de prise en charge lié à une saturation simultanée des salles a mis en jeu le pronostic vital, résolu par transfert en urgence au bloc.",
            'consequencesAutres' => true,
            'consequencesAutresDetail' => "Report de prise en charge pour une seconde patiente, sans conséquence clinique.",
            'mesuresImmediatesPatient' => true,
            'mesuresImmediatesPatientDetail' => 'Transfert immédiat au bloc obstétrical, équipe de garde rappelée.',
            'mesuresImmediatesProches' => true,
        ],
        [
            'service' => 'urgences-maternite',
            'typeEI' => TypeEIEnum::Chute,
            'gravite' => GraviteEnum::Mineur,
            'statut' => StatutEnum::Cloturee,
            'dateConstat' => '2026-03-30 17:20:00',
            'dateSurvenue' => '2026-03-30 17:15:00',
            'description' => "Un malaise avec chute est survenu en salle d'attente, sans traumatisme constaté après examen.",
            'mesuresImmediatesPatient' => true,
            'mesuresImmediatesPatientDetail' => 'Mise en décubitus, prise des constantes, surveillance sur place 30 minutes.',
        ],
        [
            'service' => 'hospitalisation',
            'typeEI' => TypeEIEnum::ErreurMedicament,
            'gravite' => GraviteEnum::Mineur,
            'statut' => StatutEnum::Soumise,
            'dateConstat' => '2026-08-02 08:30:00',
            'dateSurvenue' => '2026-08-02 08:15:00',
            'description' => "Un décalage horaire d'administration d'un traitement a été constaté lors de la relève, sans retentissement clinique.",
            'autresMesures' => 'Ajustement du plan de soins informatisé.',
        ],
        [
            'service' => 'neonatalogie',
            'typeEI' => TypeEIEnum::Materiovigilance,
            'gravite' => GraviteEnum::Grave,
            'statut' => StatutEnum::EnAnalyse,
            'dateConstat' => '2026-06-19 04:00:00',
            'dateSurvenue' => '2026-06-19 03:40:00',
            'description' => "Un dysfonctionnement de la régulation thermique d'une couveuse a été détecté par l'alarme, avec hypothermie transitoire nécessitant un réchauffement contrôlé.",
            'consequencesAutres' => false,
            'lieuDifferent' => true,
            'lieuDifferentDetail' => "Constat effectué en salle de soins, l'événement est survenu dans la chambre 4.",
            'mesuresImmediatesPatient' => true,
            'mesuresImmediatesPatientDetail' => 'Transfert immédiat sur une couveuse de secours, réchauffement progressif et monitoring continu.',
            'mesuresImmediatesProches' => true,
            'autresMesures' => 'Contrôle du parc de couveuses par le service biomédical.',
        ],
    ];

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ReferenceGenerator $referenceGenerator,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $admin = $this->createUser($manager, 'admin@senticare.fr', 'Admin', 'System', RoleEnum::Admin);

        // Un seul parcours de la structure : pôle -> chef -> services -> équipes.
        // Chaque entité porte son createdBy réel (admin pour les pôles et les
        // chefs de pôle, chef de pôle pour ses services et ses équipes), pour
        // que la traçabilité affichée soit cohérente avec le CDC §3.
        foreach (self::STRUCTURE as $poleData) {
            $pole = new Pole();
            $pole->setNom($poleData['pole']);
            $pole->setCreatedBy($admin);
            $manager->persist($pole);

            $chef = $this->createUser(
                $manager,
                $poleData['chef']['email'] ?? $this->emailFor('chef', $this->slugify($poleData['pole'])),
                $poleData['chef']['nom'],
                $poleData['chef']['prenom'],
                RoleEnum::ChefPole,
                $admin,
            );

            foreach ($poleData['services'] as $serviceData) {
                $service = new Service();
                $service->setNom($serviceData['nom']);
                $service->setPole($pole);
                $service->setCreatedBy($chef);
                $manager->persist($service);

                // Le pôle du chef est déduit de ses services : il doit être
                // rattaché à tous, sinon son périmètre de supervision est vide.
                $service->addUser($chef);

                $cadre = $this->createUser(
                    $manager,
                    $serviceData['cadre']['email'] ?? $this->emailFor('cadre', $serviceData['slug']),
                    $serviceData['cadre']['nom'],
                    $serviceData['cadre']['prenom'],
                    RoleEnum::Cadre,
                    $chef,
                );
                $service->addUser($cadre);

                foreach ($serviceData['soignants'] as $rang => $soignantData) {
                    $soignant = $this->createUser(
                        $manager,
                        $soignantData['email'] ?? $this->emailFor('soignant'.($rang + 1), $serviceData['slug']),
                        $soignantData['nom'],
                        $soignantData['prenom'],
                        RoleEnum::Soignant,
                        $chef,
                    );
                    $service->addUser($soignant);

                    // Le premier soignant du service est le déclarant de
                    // référence utilisé par les déclarations de démonstration.
                    if (0 === $rang) {
                        $this->addReference('declarant_'.$serviceData['slug'], $soignant);
                    }
                }

                $this->addReference('service_'.$serviceData['slug'], $service);
            }
        }

        $this->loadDeclarations($manager);

        $manager->flush();
    }

    private function loadDeclarations(ObjectManager $manager): void
    {
        // La purge des fixtures ne remet pas les séquences PostgreSQL à zéro :
        // sans ça, un rechargement produirait DCL-2026-0019 et suivants. On
        // repart de 1 pour que le jeu de démonstration soit reproductible.
        $this->referenceGenerator->reset();

        foreach (self::DECLARATIONS as $data) {
            $declaration = new Declaration();
            $declaration->setReference(
                $this->referenceGenerator->generate(new \DateTimeImmutable($data['dateConstat']))
            );
            $declaration->setService($this->getReference('service_'.$data['service'], Service::class));
            $declaration->setDeclarant($this->getReference('declarant_'.$data['service'], User::class));
            $declaration->setTypeEI($data['typeEI']);
            // setGravite() dérive isEIGS automatiquement (CDC §4.3) — on ne le
            // positionne jamais à la main, y compris ici.
            $declaration->setGravite($data['gravite']);
            $declaration->setDateConstat(new \DateTimeImmutable($data['dateConstat']));
            $declaration->setDateSurvenue(new \DateTimeImmutable($data['dateSurvenue']));
            $declaration->setDescription($data['description']);

            $declaration->setLieuDifferent($data['lieuDifferent'] ?? false);
            $declaration->setLieuDifferentDetail($data['lieuDifferentDetail'] ?? null);
            $declaration->setConsequencesAutres($data['consequencesAutres'] ?? null);
            $declaration->setConsequencesAutresDetail($data['consequencesAutresDetail'] ?? null);
            $declaration->setMesuresImmediatesPatient($data['mesuresImmediatesPatient'] ?? null);
            $declaration->setMesuresImmediatesPatientDetail($data['mesuresImmediatesPatientDetail'] ?? null);
            $declaration->setMesuresImmediatesProches($data['mesuresImmediatesProches'] ?? null);
            $declaration->setAutresMesures($data['autresMesures'] ?? null);

            // Tout statut au-delà du brouillon passe forcément par "soumise"
            // (StatutEnum::transitionsAutorisees) : on rejoue la transition pour
            // que submittedAt soit renseigné, sinon les déclarations clôturées
            // afficheraient une date de soumission vide.
            if (StatutEnum::Brouillon !== $data['statut']) {
                $declaration->setStatut(StatutEnum::Soumise);
            }
            $declaration->setStatut($data['statut']);

            $manager->persist($declaration);
        }
    }

    private function createUser(
        ObjectManager $manager,
        string $email,
        string $nom,
        string $prenom,
        RoleEnum $role,
        ?User $createdBy = null,
    ): User {
        $user = new User();
        $user->setEmail($email);
        $user->setNom($nom);
        $user->setPrenom($prenom);
        $user->setRole($role);
        $user->setIsActive(true);
        $user->setCreatedBy($createdBy);
        // Mot de passe de démo — haché Argon2id, jamais stocké en clair (CDC §6.1).
        $user->setPassword($this->passwordHasher->hashPassword($user, self::PASSWORD));

        $manager->persist($user);

        return $user;
    }

    private function emailFor(string $prefix, string $slug): string
    {
        return sprintf('%s.%s@senticare.fr', $prefix, $slug);
    }

    private function slugify(string $value): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT', $value) ?: $value;

        return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($ascii)), '-');
    }
}

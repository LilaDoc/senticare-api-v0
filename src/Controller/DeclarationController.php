<?php

namespace App\Controller;

use App\Entity\Declaration;
use App\Entity\User;
use App\Enum\GraviteEnum;
use App\Enum\StatutEnum;
use App\Enum\TypeEIEnum;
use App\Repository\DeclarationRepository;
use App\Repository\ServiceRepository;
use App\Security\Voter\DeclarationVoter;
use App\Service\DeclarationManager;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Cycle de vie des déclarations d'EI (CDC §4.2 à §4.4, UC-01 à UC-07).
 *
 * ROLE_SOIGNANT est le rôle minimal requis (hiérarchie security.yaml : cadre et
 * chef de pôle en héritent). Le périmètre exact (mes déclarations / mon service /
 * mon pôle) est géré par DeclarationVoter, appelé explicitement dans chaque action.
 */
#[Route('/api/declarations')]
#[IsGranted('ROLE_SOIGNANT')]
class DeclarationController extends AbstractController
{
    public function __construct(
        private readonly DeclarationManager $declarationManager,
        private readonly DeclarationRepository $declarationRepository,
        private readonly ServiceRepository $serviceRepository,
    ) {
    }

    #[Route('', name: 'declarations_list', methods: ['GET'])]
    public function list(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $query = $request->query;

        // Filtres optionnels — lus depuis la query string (GET n'a pas de body),
        // CDC §4.6 : statut/date/type/gravité/EIGS + recherche par mot-clé.
        $filters = [];
        if ($query->get('statut')) {
            $filters['statut'] = StatutEnum::from($query->get('statut'));
        }
        if ($query->get('dateFrom')) {
            $filters['dateFrom'] = new \DateTimeImmutable($query->get('dateFrom'));
        }
        if ($query->get('dateTo')) {
            $filters['dateTo'] = new \DateTimeImmutable($query->get('dateTo'));
        }
        if ($query->get('typeEI')) {
            $filters['typeEI'] = TypeEIEnum::from($query->get('typeEI'));
        }
        if ($query->get('gravite')) {
            $filters['gravite'] = GraviteEnum::from($query->get('gravite'));
        }
        if ($query->getBoolean('eigsOnly')) {
            $filters['eigsOnly'] = true;
        }
        if ($query->get('motCle')) {
            $filters['motCle'] = $query->get('motCle');
        }

        $declarations = $this->declarationManager->search($user, $filters);

        return $this->json(array_map($this->serialize(...), $declarations));
    }

    #[Route('/{id}', name: 'declarations_show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $declaration = $this->declarationRepository->find($id) ?? throw $this->createNotFoundException();
        $this->denyAccessUnlessGranted(DeclarationVoter::VIEW, $declaration);

        return $this->json($this->serialize($declaration));
    }

    #[Route('', name: 'declarations_create', methods: ['POST'])]
    public function create(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $serviceId = $data['service'] ?? null;
        $typeEIValue = $data['typeEI'] ?? null;
        $dateConstat = $data['dateConstat'] ?? null;
        $dateSurvenue = $data['dateSurvenue'] ?? null;

        if (!$serviceId || !$typeEIValue || !$dateConstat || !$dateSurvenue) {
            return $this->json(['error' => 'Les champs "service", "typeEI", "dateConstat" et "dateSurvenue" sont requis.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $typeEI = TypeEIEnum::tryFrom($typeEIValue);
        if (!$typeEI) {
            return $this->json(['error' => 'Type d\'EI invalide.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $service = $this->serviceRepository->find($serviceId) ?? throw $this->createNotFoundException();

        // Assistant de gravité en 3 questions (CDC §4.2).
        $deces = (bool) ($data['deces'] ?? false);
        $pronosticVitalEnJeu = (bool) ($data['pronosticVitalEnJeu'] ?? false);
        $risqueDeficitFonctionnelPermanent = (bool) ($data['risqueDeficitFonctionnelPermanent'] ?? false);
        $choixSiNonEIGS = isset($data['choixSiNonEIGS']) ? GraviteEnum::tryFrom($data['choixSiNonEIGS']) : null;

        try {
            $declaration = $this->declarationManager->createDraft(
                $user,
                $service,
                $typeEI,
                new \DateTimeImmutable($dateConstat),
                new \DateTimeImmutable($dateSurvenue),
                $deces,
                $pronosticVitalEnJeu,
                $risqueDeficitFonctionnelPermanent,
                $choixSiNonEIGS,
            );
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], JsonResponse::HTTP_BAD_REQUEST);
        }

        return $this->json($this->serialize($declaration), JsonResponse::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'declarations_update', methods: ['PATCH'])]
    public function update(string $id, Request $request): JsonResponse
    {
        $declaration = $this->declarationRepository->find($id) ?? throw $this->createNotFoundException();

        $this->denyAccessUnlessGranted(DeclarationVoter::EDIT, $declaration);

        $data = json_decode($request->getContent(), true);

        // Ne garder que les champs autorisés — gravite/isEIGS/service ne
        // doivent jamais transiter par cette voie (cf. DeclarationManager::updateDraft()).
        $changes = array_intersect_key($data, array_flip([
            'dateConstat', 'dateSurvenue', 'lieuDifferent', 'lieuDifferentDetail',
            'typeEI', 'description', 'consequencesAutres', 'consequencesAutresDetail',
            'mesuresImmediatesPatient', 'mesuresImmediatesPatientDetail',
            'mesuresImmediatesProches', 'autresMesures',
        ]));

        // Le JSON n'envoie que des chaînes/scalaires — on convertit vers les
        // vrais types attendus par les setters de Declaration avant de déléguer.
        if (isset($changes['dateConstat'])) {
            $changes['dateConstat'] = new \DateTimeImmutable($changes['dateConstat']);
        }
        if (isset($changes['dateSurvenue'])) {
            $changes['dateSurvenue'] = new \DateTimeImmutable($changes['dateSurvenue']);
        }
        if (isset($changes['typeEI'])) {
            $changes['typeEI'] = TypeEIEnum::from($changes['typeEI']);
        }

        try {
            $declaration = $this->declarationManager->updateDraft($declaration, $changes);
        } catch (\RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], JsonResponse::HTTP_BAD_REQUEST);
        }

        return $this->json($this->serialize($declaration));
    }

    #[Route('/{id}/abandon', name: 'declarations_abandon', methods: ['POST'])]
    public function abandon(string $id): JsonResponse
    {
        $declaration = $this->declarationRepository->find($id) ?? throw $this->createNotFoundException();

        $this->denyAccessUnlessGranted(DeclarationVoter::ABANDON, $declaration);

        try {
            $this->declarationManager->abandon($declaration);
        } catch (\RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], JsonResponse::HTTP_BAD_REQUEST);
        }

        return $this->json($this->serialize($declaration));
    }

    #[Route('/{id}/submit', name: 'declarations_submit', methods: ['POST'])]
    public function submit(string $id): JsonResponse
    {
        $declaration = $this->declarationRepository->find($id) ?? throw $this->createNotFoundException();

        $this->denyAccessUnlessGranted(DeclarationVoter::SUBMIT, $declaration);

        try {
            $this->declarationManager->submit($declaration);
        } catch (\RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], JsonResponse::HTTP_BAD_REQUEST);
        }

        return $this->json($this->serialize($declaration));
    }

    #[Route('/{id}/statut', name: 'declarations_change_statut', methods: ['PATCH'])]
    public function changeStatut(string $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $statut = StatutEnum::tryFrom($data['statut'] ?? '');

        if (!$statut) {
            return $this->json(['error' => 'Statut invalide.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $declaration = $this->declarationRepository->find($id) ?? throw $this->createNotFoundException();

        $this->denyAccessUnlessGranted(DeclarationVoter::CHANGE_STATUT, $declaration);

        try {
            $this->declarationManager->changeStatut($declaration, $statut);
        } catch (\RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], JsonResponse::HTTP_BAD_REQUEST);
        }

        return $this->json($this->serialize($declaration));
    }

    #[Route('/{id}/pdf', name: 'declarations_pdf', methods: ['GET'])]
    public function exportPdf(string $id): Response
    {
        $declaration = $this->declarationRepository->find($id) ?? throw $this->createNotFoundException();
        $this->denyAccessUnlessGranted(DeclarationVoter::VIEW, $declaration);

        // Réutilise le template de l'email de soumission : mêmes données, même
        // mise en page pensée pour l'impression (dompdf lit les polices via
        // "absolute_path", d'où le chroot sur public/ ci-dessous).
        $html = $this->renderView('emails/notification_submission.html.twig', [
            'declaration' => $declaration,
            'absolute_path' => $this->getParameter('kernel.project_dir').'/public',
        ]);

        $options = new Options();
        $options->set('isRemoteEnabled', false);   // pas de ressources distantes
        $options->set('isPhpEnabled', false);      // pas d'exécution PHP inline
        $options->set('isHtml5ParserEnabled', true);
        $options->set('chroot', $this->getParameter('kernel.project_dir').'/public');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new Response(
            $dompdf->output(),
            Response::HTTP_OK,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => sprintf(
                    'inline; filename="declaration-%s.pdf"',
                    $declaration->getId()
                ),
            ]
        );
    }

    /**
     * Sérialisation JSON commune à list/show/create/update/abandon/submit/changeStatut
     * — évite de recopier la même vingtaine de champs dans chaque action.
     */
    private function serialize(Declaration $declaration): array
    {
        return [
            'id' => (string) $declaration->getId(),
            'statut' => [
                'value' => $declaration->getStatut()->value,
                'label' => $declaration->getStatut()->label(),
            ],
            'dateConstat' => $declaration->getDateConstat()?->format(\DateTimeInterface::ATOM),
            'dateSurvenue' => $declaration->getDateSurvenue()?->format(\DateTimeInterface::ATOM),
            'lieuDifferent' => $declaration->isLieuDifferent(),
            'lieuDifferentDetail' => $declaration->getLieuDifferentDetail(),
            'typeEI' => [
                'value' => $declaration->getTypeEI()->value,
                'label' => $declaration->getTypeEI()->label(),
            ],
            'gravite' => null !== $declaration->getGravite() ? [
                'value' => $declaration->getGravite()->value,
                'label' => $declaration->getGravite()->label(),
            ] : null,
            'isEIGS' => $declaration->isEIGS(),
            'description' => $declaration->getDescription(),
            'consequencesAutres' => $declaration->getConsequencesAutres(),
            'consequencesAutresDetail' => $declaration->getConsequencesAutresDetail(),
            'mesuresImmediatesPatient' => $declaration->getMesuresImmediatesPatient(),
            'mesuresImmediatesPatientDetail' => $declaration->getMesuresImmediatesPatientDetail(),
            'mesuresImmediatesProches' => $declaration->getMesuresImmediatesProches(),
            'autresMesures' => $declaration->getAutresMesures(),
            'declarant' => [
                'id' => (string) $declaration->getDeclarant()->getId(),
                'nom' => $declaration->getDeclarant()->getNom(),
                'prenom' => $declaration->getDeclarant()->getPrenom(),
            ],
            'service' => [
                'id' => (string) $declaration->getService()->getId(),
                'nom' => $declaration->getService()->getNom(),
            ],
            'createdAt' => $declaration->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            'submittedAt' => $declaration->getSubmittedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }
}

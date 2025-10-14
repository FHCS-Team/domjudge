<?php declare(strict_types=1);

namespace App\Controller\Jury;

use App\Controller\BaseController;
use App\Entity\Contest;
use App\Entity\ContestDisplayData;
use App\Entity\Phase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\JsonResponse;

#[IsGranted('ROLE_JURY')]
#[Route(path: '/jury/hackathon')]
class HackathonExportImportController extends BaseController
{
    public function __construct(
        protected readonly EntityManagerInterface $em
    ) {}

    #[Route(path: '/{contestId<\\d+>}/export-display', name: 'jury_hackathon_export_display', methods: ['GET'])]
    public function exportDisplay(Request $request, int $contestId): Response
    {
        $contest = $this->em->getRepository(Contest::class)->find($contestId);
        if (!$contest) {
            return new JsonResponse(['error' => 'Contest not found'], 404);
        }
        $displayData = $this->em->getRepository(ContestDisplayData::class)->findOneBy(['contest' => $contest]);
    // Phases are omitted from export in this deployment
    $data = [
            'contest' => [
                'id' => $contest->getCid(),
                'name' => $contest->getName(),
                'shortname' => $contest->getShortname(),
                'starttime' => $contest->getStarttimeString(),
                'endtime' => $contest->getEndtimeString(),
            ],
            'displayData' => $displayData ? [
                'title' => $displayData->getTitle(),
                'subtitle' => $displayData->getSubtitle(),
                'bannerUrl' => $displayData->getBannerUrl(),
                'description' => $displayData->getDescription(),
                'metaData' => $displayData->getMetaData(),
            ] : null,
        ];

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $filename = 'contest_display_export_' . $contest->getCid() . '.json';
        return new Response($json, 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    #[Route(path: '/{contestId<\d+>}/import-display', name: 'jury_hackathon_import_display', methods: ['POST'])]
    public function importDisplay(Request $request, int $contestId): Response
    {
        $contest = $this->em->getRepository(Contest::class)->find($contestId);
        if (!$contest) {
            $this->addFlash('danger', 'Contest not found.');
            return $this->redirectToRoute('jury_hackathon');
        }
        $file = $request->files->get('import_file');
        if (!$file || !$file->isValid()) {
            $this->addFlash('danger', 'No file uploaded or upload error.');
            return $this->redirectToRoute('jury_hackathon_display', ['contestId' => $contestId]);
        }
        $json = file_get_contents($file->getPathname());
        $data = json_decode($json, true);
        if (!$data) {
            $this->addFlash('danger', 'Invalid JSON file.');
            return $this->redirectToRoute('jury_hackathon_display', ['contestId' => $contestId]);
        }
        // Import display data
        if (!empty($data['displayData'])) {
            $repo = $this->em->getRepository(ContestDisplayData::class);
            $displayData = $repo->findOneBy(['contest' => $contest]) ?? new ContestDisplayData();
            $displayData->setContest($contest);
            $displayData->setTitle($data['displayData']['title'] ?? '');
            $displayData->setSubtitle($data['displayData']['subtitle'] ?? '');
            $displayData->setBannerUrl($data['displayData']['bannerUrl'] ?? null);
            $displayData->setDescription($data['displayData']['description'] ?? '');
            $displayData->setMetaData($data['displayData']['metaData'] ?? []);
            // 'allowPhase' import removed - phases disabled in this deployment
            $this->em->persist($displayData);
        }
        // Phases are not imported in this deployment. Only display data is imported.
        $this->em->flush();
        $this->addFlash('success', 'Import successful.');
        return $this->redirectToRoute('jury_hackathon_display', ['contestId' => $contestId]);
    }
}

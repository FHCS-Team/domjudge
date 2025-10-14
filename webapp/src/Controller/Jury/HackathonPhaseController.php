<?php declare(strict_types=1);

namespace App\Controller\Jury;

use App\Controller\BaseController;
use App\Entity\Contest;
use App\Entity\Phase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_JURY')]
#[Route(path: '/jury/hackathon')]
class HackathonPhaseController extends BaseController
{
    public function __construct(
        protected readonly EntityManagerInterface $em
    ) {}

    #[Route(path: '/{contestId<\\d+>}/phases', name: 'jury_hackathon_phases')]
    public function phasesConfig(Request $request, int $contestId): Response
    {
        $contest = $this->em->getRepository(Contest::class)->find($contestId);
        if (!$contest) {
            $this->addFlash('danger', 'Contest not found.');
            return $this->redirectToRoute('jury_hackathon');
        }

        $phases = $this->em->getRepository(Phase::class)->findBy(['contest' => $contest], ['phase_order' => 'ASC']);

        // TODO: Add form handling for CRUD (create, edit, delete) phases
        // TODO: Add validation for phase start/end times (must be within contest, start < end, no overlap)

        return $this->render('extensions_plugin/hackathon_phases.html.twig', [
            'contest' => $contest,
            'phases' => $phases,
        ]);
    }
}

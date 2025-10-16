<?php declare(strict_types=1);

namespace App\Controller\Team;

use App\Controller\BaseController;
use App\Entity\Judging;
use App\Entity\Language;
use App\Entity\Problem;
use App\Entity\Submission;
use App\Entity\SubmissionSource;
use App\Entity\SubmissionDeliverable;
use App\Entity\Testcase;
use App\Form\Type\SubmitProblemType;
use App\Form\Type\HackathonSubmitType;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Service\SubmissionService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\Query\Expr\Join;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_TEAM')]
#[IsGranted(
    new Expression('user.getTeam() !== null'),
    message: 'You do not have a team associated with your account.'
)]
#[Route(path: '/team')]
class SubmissionController extends BaseController
{
    final public const NEVER_SHOW_COMPILE_OUTPUT = 0;
    final public const ONLY_SHOW_COMPILE_OUTPUT_ON_ERROR = 1;
    final public const ALWAYS_SHOW_COMPILE_OUTPUT = 2;

    public function __construct(
        EntityManagerInterface $em,
        protected readonly SubmissionService $submissionService,
        protected readonly EventLogService $eventLogService,
        DOMJudgeService $dj,
        protected readonly ConfigurationService $config,
        protected readonly FormFactoryInterface $formFactory,
        KernelInterface $kernel,
    ) {
        parent::__construct($em, $eventLogService, $dj, $kernel);
    }

    #[Route(path: '/submit/{problem}', name: 'team_submit')]
    public function createAction(Request $request, ?Problem $problem = null): Response
    {
        $user    = $this->dj->getUser();
        $team    = $user->getTeam();
        $contest = $this->dj->getCurrentContest($user->getTeam()->getTeamid());
        $data = [];
        if ($problem !== null) {
            $data['problem'] = $problem;
        }

        // Use hackathon form if hackathon mode is enabled
        $isHackathon = $contest && $contest->getHackathonEnabled();
        $formType = $isHackathon ? HackathonSubmitType::class : SubmitProblemType::class;
        
        $form = $this->formFactory
            ->createBuilder($formType, $data)
            ->setAction($this->generateUrl($isHackathon ? 'team_hackathon_submit' : 'team_submit'))
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($contest === null) {
                $this->addFlash('danger', 'No active contest');
            } elseif (!$this->dj->checkrole('jury') && !$contest->getFreezeData()->started()) {
                $this->addFlash('danger', 'Contest has not yet started');
            } else {
                /** @var Problem $problem */
                $problem = $form->get('problem')->getData();
                
                // For hackathons, language is optional since submissions can be various formats
                $language = null;
                if ($form->has('language') && $form->get('language')->getData()) {
                    /** @var Language $language */
                    $language = $form->get('language')->getData();
                } else {
                    // Use a default "hackathon" language if none specified
                    $language = $this->em->getRepository(Language::class)->findOneBy(['langid' => 'txt']) 
                             ?: $this->em->getRepository(Language::class)->findOneBy(['allowSubmit' => true]);
                }
                /** @var UploadedFile[]|UploadedFile $files */
                $files = $form->get('code')->getData();
                if (!is_array($files)) {
                    $files = [$files];
                }
                $entryPoint = $form->has('entry_point') ? $form->get('entry_point')->getData() : null;
                
                $submission = $this->submissionService->submitSolution(
                    $team, $this->dj->getUser(), $problem->getProbid(), $contest, $language, $files, SubmissionSource::TEAM_PAGE, null,
                    null, $entryPoint, null, null, $message
                );

                if ($submission && $isHackathon) {
                    // Handle hackathon deliverables
                    $this->handleHackathonDeliverables($submission, $form, $request);
                }

                if ($submission) {
                    $this->addFlash(
                        'success',
                        $isHackathon ? 'Submission and deliverables uploaded successfully!' : 'Submission done! Watch for the verdict in the list below.'
                    );
                } else {
                    $this->addFlash('danger', $message);
                }
                return $this->redirectToRoute('team_index');
            }
        }

        $data = ['form' => $form->createView(), 'problem' => $problem, 'isHackathon' => $isHackathon];
        $data['validFilenameRegex'] = SubmissionService::FILENAME_REGEX;

        if ($request->isXmlHttpRequest()) {
            $template = $isHackathon ? 'team/hackathon_submit_modal.html.twig' : 'team/submit_modal.html.twig';
            return $this->render($template, $data);
        } else {
            $template = $isHackathon ? 'team/hackathon_submit.html.twig' : 'team/submit.html.twig';
            return $this->render($template, $data);
        }
    }

    private function handleHackathonDeliverables(Submission $submission, $form, Request $request): void
    {
        // Get submission method and URLs
        $submissionMethod = $form->get('submission_method')->getData();
        $githubUrl = $form->get('github_url')->getData();
        $demoUrl = $form->get('demo_url')->getData();
        $videoUrl = $form->get('video_url')->getData();
        $deliverableTypes = $form->get('deliverable_types')->getData();
        
        // Handle GitHub URL
        if ($githubUrl && in_array($submissionMethod, ['github', 'files_github', 'github_demo', 'complete'])) {
            $githubDeliverable = new SubmissionDeliverable();
            $githubDeliverable->setSubmission($submission);
            $githubDeliverable->setType('github_repository');
            $githubDeliverable->setFileType('url');
            $githubDeliverable->setUrl($githubUrl);
            $this->em->persist($githubDeliverable);
        }
        
        // Handle Demo URL
        if ($demoUrl && in_array($submissionMethod, ['demo', 'files_demo', 'github_demo', 'complete'])) {
            $demoDeliverable = new SubmissionDeliverable();
            $demoDeliverable->setSubmission($submission);
            $demoDeliverable->setType('live_demo');
            $demoDeliverable->setFileType('url');
            $demoDeliverable->setUrl($demoUrl);
            $this->em->persist($demoDeliverable);
        }
        
        // Handle Video URL (optional)
        if ($videoUrl) {
            $videoDeliverable = new SubmissionDeliverable();
            $videoDeliverable->setSubmission($submission);
            $videoDeliverable->setType('video_demo');
            $videoDeliverable->setFileType('url');
            $videoDeliverable->setUrl($videoUrl);
            $this->em->persist($videoDeliverable);
        }
        
        // Handle deliverable files (if provided)
        $deliverableFiles = $form->get('deliverable_files')->getData();
        
        if ($deliverableFiles && in_array($submissionMethod, ['files', 'files_github', 'files_demo', 'complete'])) {
            foreach ($deliverableFiles as $file) {
                if ($file instanceof UploadedFile) {
                    // Save the file and create deliverable record
                    $filename = $this->saveDeliverableFile($file, $submission);
                    
                    $deliverable = new SubmissionDeliverable();
                    $deliverable->setSubmission($submission);
                    $deliverable->setType(implode(',', $deliverableTypes ?: ['other']));
                    $deliverable->setFileType($file->getClientOriginalExtension() ?: 'unknown');
                    $deliverable->setUrl($filename);
                    
                    $this->em->persist($deliverable);
                }
            }
        }
        
        // Store submission method metadata
        if ($submissionMethod) {
            $metaDeliverable = new SubmissionDeliverable();
            $metaDeliverable->setSubmission($submission);
            $metaDeliverable->setType('submission_method');
            $metaDeliverable->setFileType('metadata');
            $metaDeliverable->setUrl($submissionMethod);
            $this->em->persist($metaDeliverable);
        }
        
        // Store description and deployment instructions if provided
        $description = $form->get('description')->getData();
        $deploymentInstructions = $form->get('deployment_instructions')->getData();
        
        if ($description) {
            $descDeliverable = new SubmissionDeliverable();
            $descDeliverable->setSubmission($submission);
            $descDeliverable->setType('description');
            $descDeliverable->setFileType('text');
            $descDeliverable->setUrl($description);
            $this->em->persist($descDeliverable);
        }
        
        if ($deploymentInstructions) {
            $instrDeliverable = new SubmissionDeliverable();
            $instrDeliverable->setSubmission($submission);
            $instrDeliverable->setType('deployment_instructions');
            $instrDeliverable->setFileType('text');
            $instrDeliverable->setUrl($deploymentInstructions);
            $this->em->persist($instrDeliverable);
        }
        
        $this->em->flush();
    }

    private function saveDeliverableFile(UploadedFile $file, Submission $submission): string
    {
        // Create deliverables directory if it doesn't exist
        $deliverableDir = $this->dj->getDomjudgeWebappDir() . '/deliverables';
        if (!is_dir($deliverableDir)) {
            mkdir($deliverableDir, 0755, true);
        }
        
        // Generate unique filename
        $filename = sprintf(
            'deliverable_%d_%d_%s.%s',
            $submission->getSubmitid(),
            time(),
            uniqid(),
            $file->getClientOriginalExtension() ?: 'bin'
        );
        
        $fullPath = $deliverableDir . '/' . $filename;
        $file->move($deliverableDir, $filename);
        
        // Return relative URL
        return '/deliverables/' . $filename;
    }

    /**
     * @throws NonUniqueResultException
     */
    #[Route(path: '/submission/{submitId<\d+>}', name: 'team_submission')]
    public function viewAction(Request $request, int $submitId): Response
    {
        $verificationRequired = (bool)$this->config->get('verification_required');
        $showCompile          = $this->config->get('show_compile');
        $showSampleOutput     = $this->config->get('show_sample_output');
        $allowDownload        = (bool)$this->config->get('allow_team_submission_download');
        $showTooLateResult    = $this->config->get('show_too_late_result');
        $user                 = $this->dj->getUser();
        $team                 = $user->getTeam();
        $contest              = $this->dj->getCurrentContest($team->getTeamid());
        /** @var Judging|null $judging */
        $judging = $this->em->createQueryBuilder()
            ->from(Judging::class, 'j')
            ->join('j.submission', 's')
            ->join('s.contest_problem', 'cp')
            ->join('cp.problem', 'p')
            ->join('s.language', 'l')
            ->select('j', 's', 'cp', 'p', 'l')
            ->andWhere('j.submission = :submitId')
            ->andWhere('j.valid = 1')
            ->andWhere('s.team = :team')
            ->setParameter('submitId', $submitId)
            ->setParameter('team', $team)
            ->getQuery()
            ->getOneOrNullResult();

        // Update seen status when viewing submission.
        if ($judging && $judging->getSubmission()->getSubmittime() < $contest->getEndtime() &&
            (!$verificationRequired || $judging->getVerified())) {
            $judging->setSeen(true);
            $this->em->flush();
        }

        $runs = [];
        if ($showSampleOutput && $judging && $judging->getResult() !== 'compiler-error') {
            $outputDisplayLimit    = (int)$this->config->get('output_display_limit');
            $outputTruncateMessage = sprintf("\n[output display truncated after %d B]\n", $outputDisplayLimit);

            $queryBuilder = $this->em->createQueryBuilder()
                ->from(Testcase::class, 't')
                ->join('t.content', 'tc')
                ->leftJoin('t.judging_runs', 'jr', Join::WITH, 'jr.judging = :judging')
                ->leftJoin('jr.output', 'jro')
                ->select('t', 'jr', 'tc')
                ->andWhere('t.problem = :problem')
                ->andWhere('t.sample = 1')
                ->setParameter('judging', $judging)
                ->setParameter('problem', $judging->getSubmission()->getProblem())
                ->orderBy('t.ranknumber');

            if ($outputDisplayLimit < 0) {
                $queryBuilder
                    ->addSelect('tc.output AS output_reference')
                    ->addSelect('jro.output_run AS output_run')
                    ->addSelect('jro.output_diff AS output_diff')
                    ->addSelect('jro.output_error AS output_error')
                    ->addSelect('jro.output_system AS output_system')
                    ->addSelect('jro.team_message AS team_message');
            } else {
                $queryBuilder
                    ->addSelect('TRUNCATE(tc.output, :outputDisplayLimit, :outputTruncateMessage) AS output_reference')
                    ->addSelect('TRUNCATE(jro.output_run, :outputDisplayLimit, :outputTruncateMessage) AS output_run')
                    ->addSelect('TRUNCATE(jro.output_diff, :outputDisplayLimit, :outputTruncateMessage) AS output_diff')
                    ->addSelect('TRUNCATE(jro.output_error, :outputDisplayLimit, :outputTruncateMessage) AS output_error')
                    ->addSelect('TRUNCATE(jro.output_system, :outputDisplayLimit, :outputTruncateMessage) AS output_system')
                    ->addSelect('TRUNCATE(jro.team_message, :outputDisplayLimit, :outputTruncateMessage) AS team_message')
                    ->setParameter('outputDisplayLimit', $outputDisplayLimit)
                    ->setParameter('outputTruncateMessage', $outputTruncateMessage);
            }

            $runs = $queryBuilder
                ->getQuery()
                ->getResult();
        }

        $actuallyShowCompile = $showCompile == self::ALWAYS_SHOW_COMPILE_OUTPUT
            || ($showCompile == self::ONLY_SHOW_COMPILE_OUTPUT_ON_ERROR && $judging->getResult() === 'compiler-error');

        $data = [
            'judging' => $judging,
            'verificationRequired' => $verificationRequired,
            'showCompile' => $actuallyShowCompile,
            'allowDownload' => $allowDownload,
            'showSampleOutput' => $showSampleOutput,
            'runs' => $runs,
            'showTooLateResult' => $showTooLateResult,
            'thumbnailSize' => $this->config->get('thumbnail_size'),
        ];
        if ($actuallyShowCompile) {
            $data['size'] = 'xl';
        }

        // Add rubric scores for custom problems
        if ($judging->getSubmission()->getProblem()->isCustomProblem()) {
            $rubricScores = $this->em->getRepository(SubmissionRubricScore::class)->findBy(
                ['submission' => $judging->getSubmission()],
                ['rubric' => 'ASC']
            );
            $data['rubricScores'] = $rubricScores;
            $data['customExecutionMetadata'] = $judging->getSubmission()->getCustomExecutionMetadata();
        }

        if ($request->isXmlHttpRequest()) {
            return $this->render('team/submission_modal.html.twig', $data);
        } else {
            return $this->render('team/submission.html.twig', $data);
        }
    }

    /**
     * @throws NonUniqueResultException
     */
    #[Route(path: '/submission/{submitId<\d+>}/download', name: 'team_submission_download')]
    public function downloadAction(int $submitId): Response
    {
        $allowDownload = (bool)$this->config->get('allow_team_submission_download');
        if (!$allowDownload) {
            throw new NotFoundHttpException('Submission download not allowed');
        }

        $user = $this->dj->getUser();
        $team = $user->getTeam();
        /** @var Submission|null $submission */
        $submission = $this->em->createQueryBuilder()
            ->from(Submission::class, 's')
            ->join('s.files', 'f')
            ->select('s, f')
            ->andWhere('s.submitid = :submitId')
            ->andWhere('s.team = :team')
            ->setParameter('submitId', $submitId)
            ->setParameter('team', $team)
            ->getQuery()
            ->getOneOrNullResult();

        if ($submission === null) {
            throw new NotFoundHttpException(sprintf('Submission with ID \'%s\' not found',
                $submitId));
        }

        if ($this->submissionService->getSubmissionFileCount($submission) === 1) {
            return $this->submissionService->getSubmissionFileResponse($submission);
        }

        return $this->submissionService->getSubmissionZipResponse($submission);
    }
}

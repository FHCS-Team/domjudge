<?php declare(strict_types=1);

namespace App\Controller\Team;

use App\Controller\BaseController;
use App\Entity\Language;
use App\Entity\SubmissionSource;
use App\Entity\Submission;
use App\Entity\Problem;
use App\Entity\ContestProblem;
use App\Service\SubmissionService;
use App\Service\EventLogService;
use App\Service\DOMJudgeService;
use App\Service\ConfigurationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[IsGranted('ROLE_TEAM')]
#[IsGranted(
    new Expression('user.getTeam() !== null'),
    message: 'You do not have a team associated with your account.'
)]
#[Route(path: '/team')]
class HackathonSubmissionController extends BaseController
{
    public function __construct(
        EntityManagerInterface $em,
        protected readonly SubmissionService $submissionService,
        EventLogService $eventLogService,
        DOMJudgeService $dj,
        protected readonly ConfigurationService $config,
        KernelInterface $kernel,
        protected readonly HttpClientInterface $httpClient
    ) {
        parent::__construct($em, $eventLogService, $dj, $kernel);
    }

    #[Route(path: '/hackathon/submissions', name: 'team_hackathon_submit', methods: ['POST'])]
    public function submitAction(Request $request): Response
    {
        $user = $this->dj->getUser();
        $team = $user->getTeam();

        // Prefer explicit field name 'submission_file', fallback to scanning $_FILES and 'code'
        $uploaded = $request->files->get('submission_file');
        if (!$uploaded instanceof UploadedFile) {
            $maybe = $request->files->get('code');
            if ($maybe instanceof UploadedFile) {
                $uploaded = $maybe;
            }
        }
        if (!$uploaded instanceof UploadedFile && !empty($_FILES)) {
            foreach ($_FILES as $field => $info) {
                if (is_array($info['tmp_name'])) {
                    foreach ($info['tmp_name'] as $idx => $tmp) {
                        if (!empty($tmp) && ($info['error'][$idx] ?? UPLOAD_ERR_OK) === UPLOAD_ERR_OK) {
                            $uploaded = new UploadedFile($tmp, $info['name'][$idx], $info['type'][$idx] ?? null, $info['error'][$idx] ?? UPLOAD_ERR_OK, true);
                            break 2;
                        }
                    }
                } else {
                    if (!empty($info['tmp_name']) && ($info['error'] ?? UPLOAD_ERR_OK) === UPLOAD_ERR_OK) {
                        $uploaded = new UploadedFile($info['tmp_name'], $info['name'] ?? basename($info['tmp_name']), $info['type'] ?? null, $info['error'] ?? UPLOAD_ERR_OK, true);
                        break;
                    }
                }
            }
        }

        $problemIdRaw = $request->request->get('problem_id') ?: $request->get('problem_id');

        if (!$uploaded instanceof UploadedFile) {
            $this->addFlash('danger', 'No submission file received.');
            return $this->redirectToRoute('team_index');
        }

        // Resolve problem id into an int or ContestProblem entity as submitSolution expects
        if ($problemIdRaw === null || $problemIdRaw === '') {
            $this->addFlash('danger', 'No problem id provided.');
            return $this->redirectToRoute('team_index');
        }

        $problemParam = null;
        if (is_numeric($problemIdRaw)) {
            $problemParam = (int)$problemIdRaw;
        } else {
            // Try to resolve using Problem.externalid
            $probEntity = $this->em->getRepository(Problem::class)->findOneBy(['externalid' => $problemIdRaw]);
            if ($probEntity) {
                $cp = $this->em->getRepository(ContestProblem::class)->findOneBy(['contest' => $this->dj->getCurrentContest($team->getTeamid()), 'problem' => $probEntity]);
                if ($cp) {
                    $problemParam = $cp;
                }
            }
            if ($problemParam === null) {
                $this->addFlash('danger', 'Problem not found.');
                return $this->redirectToRoute('team_index');
            }
        }

        // Prepare to submit using the canonical SubmissionService
        try {
            // Determine contest
            $contest = $this->dj->getCurrentContest($team->getTeamid());

            // Choose a default hackathon language if none; prefer 'txt'
            $language = $this->em->getRepository(Language::class)->findOneBy(['langid' => 'txt'])
                ?: $this->em->getRepository(Language::class)->findOneBy(['allowSubmit' => true]);

            // If user uploaded a zip, prefer a language that does not filter compiler files
            $originalName = $uploaded->getClientOriginalName() ?: '';
            if (str_ends_with(strtolower($originalName), '.zip')) {
                $lang = $this->em->getRepository(Language::class)->findOneBy(['filterCompilerFiles' => false, 'allowSubmit' => true]);
                if ($lang) {
                    $language = $lang;
                }
            }

            $files = [$uploaded];

            // Determine if we need to force import-invalid because submitted files don't match language extensions
            $forceImportInvalid = false;
            if ($language->getFilterCompilerFiles()) {
                $extensionMatchCount = 0;
                foreach ($files as $file) {
                    $name = strtolower($file->getClientOriginalName() ?: '');
                    foreach ($language->getExtensions() as $extension) {
                        if (str_ends_with($name, '.' . strtolower($extension))) {
                            $extensionMatchCount++;
                            break;
                        }
                    }
                }

                if ($extensionMatchCount === 0) {
                    // Try to find a language that does not filter compiler files
                    $altLang = $this->em->getRepository(Language::class)->findOneBy(['filterCompilerFiles' => false, 'allowSubmit' => true]);
                    if ($altLang) {
                        $language = $altLang;
                    } else {
                        // No suitable language; force import-invalid so submission is stored with an import error
                        $forceImportInvalid = true;
                    }
                }
            }
            $submission = $this->submissionService->submitSolution(
                $team,
                $this->dj->getUser(),
                $problemParam,
                $contest,
                $language,
                $files,
                SubmissionSource::TEAM_PAGE,
                null,
                null,
                null,
                null,
                null,
                $message,
                $forceImportInvalid
            );

            if ($submission instanceof Submission) {
                $this->addFlash('success', sprintf('Submission saved: problem id %s, file: %s', $problemIdRaw ?? '(unknown)', $uploaded->getClientOriginalName()));

                // Forward the submission file to the primitive judgehost (demo: hardcoded endpoint)
                $judgehostUrl = 'http://localhost:12345/submission';
                $judgehostApiKey = '';
                if (!empty($judgehostUrl)) {
                    try {
                        $fields = [
                            'problem_id' => $problemIdRaw,
                            'package_type' => 'file',
                            'submission_file' => DataPart::fromPath($uploaded->getRealPath(), $uploaded->getClientOriginalName()),
                        ];
                        // Prefer explicit team_id from the request, otherwise use the current team's external id
                        $teamIdToSend = $request->request->get('team_id') ?: ($team->getExternalid() ?? $team->getTeamid());
                        if ($teamIdToSend) {
                            $fields['team_id'] = $teamIdToSend;
                        }
                        $formData = new FormDataPart($fields);

                        // Use the configured URL as provided (allows setting full endpoint like http://localhost:3000/submission)
                        $response = $this->httpClient->request('POST', $judgehostUrl, [
                            'headers' => array_merge($formData->getPreparedHeaders()->toArray(), $judgehostApiKey ? ['X-API-Key' => $judgehostApiKey] : []),
                            'body' => $formData->bodyToIterable(),
                        ]);
                        // consume response to trigger exceptions on HTTP errors
                        $status = $response->getStatusCode();
                    } catch (\Throwable $e) {
                        // Logger is not available on this controller; use error_log for demo convenience.
                        error_log('Failed to forward hackathon submission to primitive judgehost: ' . $e->getMessage());
                       // $this->addFlash('warning', 'Submission saved and forwarding to judgehost failed.');
                    }
                }
            } else {
                $this->addFlash('danger', $message ?? 'Submission failed.');
            }
        } catch (\Throwable $e) {
            $this->addFlash('danger', 'Error saving submission: ' . $e->getMessage());
        }

        return $this->redirectToRoute('team_index');
    }
}

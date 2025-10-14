<?php declare(strict_types=1);

namespace App\Controller\ExtensionPlugins;

use App\Entity\Contest;
use App\Entity\Problem;
use App\Entity\Rubric;
use App\Entity\ProblemAttachment;
use App\Entity\ProblemAttachmentContent;
use App\Entity\ContestDisplayData;
use App\Entity\ProblemDisplayData;
use App\Entity\ContestProblem;
use App\Entity\Submission;
use App\Entity\SubmissionDeliverable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\HeaderUtils;
use ZipArchive;

#[Route('/jury/export-import')]
class ExportImportController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em
    ) {}

    #[Route('/export/{contestId}', name: 'jury_export_contest', methods: ['GET'])]
    public function exportContest(Request $request, int $contestId): Response
    {
        $contest = $this->em->getRepository(Contest::class)->find($contestId);
        if (!$contest) {
            throw $this->createNotFoundException('Contest not found');
        }

        $includeFiles = $request->query->getBoolean('include_files', true);

        // Create temporary directory for export
        $tempDir = sys_get_temp_dir() . '/domjudge_export_' . uniqid();
        mkdir($tempDir, 0755, true);

        try {
            // Export contest data
            $exportData = $this->prepareContestExportData($contest);
            
            // Create main export JSON
            file_put_contents($tempDir . '/contest.json', json_encode($exportData, JSON_PRETTY_PRINT));
            
            // Export files if requested
            if ($includeFiles) {
                $this->exportContestFiles($contest, $tempDir);
            }
            
            // Create ZIP archive
            $zipPath = $tempDir . '.zip';
            $this->createZipArchive($tempDir, $zipPath);
            
            // Send ZIP file
            $filePrefix = $includeFiles ? 'contest_with_files' : 'contest_data_only';
            $response = new Response(file_get_contents($zipPath));
            $response->headers->set('Content-Type', 'application/zip');
            $response->headers->set('Content-Disposition', 
                HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, 
                "{$filePrefix}_{$contest->getShortname()}_export.zip"));
            
            // Cleanup
            $this->cleanupDirectory($tempDir);
            unlink($zipPath);
            
            return $response;
            
        } catch (\Exception $e) {
            // Cleanup on error
            if (is_dir($tempDir)) {
                $this->cleanupDirectory($tempDir);
            }
            throw $e;
        }
    }

    #[Route('/import', name: 'jury_import_contest', methods: ['POST'])]
    public function importContest(Request $request): JsonResponse
    {
        /** @var UploadedFile $file */
        $file = $request->files->get('import_file');
        if (!$file || $file->getMimeType() !== 'application/zip') {
            return new JsonResponse(['error' => 'Please upload a valid ZIP file'], 400);
        }

        $tempDir = sys_get_temp_dir() . '/domjudge_import_' . uniqid();
        mkdir($tempDir, 0755, true);

        try {
            // Extract ZIP file
            $zip = new ZipArchive();
            if ($zip->open($file->getRealPath()) !== true) {
                return new JsonResponse(['error' => 'Could not open ZIP file'], 400);
            }
            
            $zip->extractTo($tempDir);
            $zip->close();

            // Read contest data
            $contestDataPath = $tempDir . '/contest.json';
            if (!file_exists($contestDataPath)) {
                return new JsonResponse(['error' => 'Invalid export file: missing contest.json'], 400);
            }

            $contestData = json_decode(file_get_contents($contestDataPath), true);
            if (!$contestData) {
                return new JsonResponse(['error' => 'Invalid contest data format'], 400);
            }

            // Import contest
            $result = $this->importContestData($contestData, $tempDir);
            
            // Import files if they exist
            $filesImported = $this->importContestFiles($contestData, $tempDir, $result['contest_id']);
            
            // Cleanup
            $this->cleanupDirectory($tempDir);
            
            return new JsonResponse([
                'success' => true,
                'message' => 'Contest imported successfully',
                'contest_id' => $result['contest_id'],
                'problems_imported' => $result['problems_count'],
                'rubrics_imported' => $result['rubrics_count'],
                'attachments_imported' => $result['attachments_count'],
                'files_imported' => $filesImported
            ]);

        } catch (\Exception $e) {
            // Cleanup on error
            if (is_dir($tempDir)) {
                $this->cleanupDirectory($tempDir);
            }
            
            // Log the actual error for debugging
            error_log('Import error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            
            return new JsonResponse([
                'error' => 'Import failed: ' . $e->getMessage()
            ], 500);
        }
    }

    private function prepareContestExportData(Contest $contest): array
    {
        $data = [
            'contest' => [
                'name' => $contest->getName(),
                'shortname' => $contest->getShortname(),
                'activatetime_string' => $contest->getActivatetimeString(),
                'starttime_string' => $contest->getStarttimeString(),
                'freezetime_string' => $contest->getFreezetimeString(),
                'endtime_string' => $contest->getEndtimeString(),
                'unfreezetime_string' => $contest->getUnfreezetimeString(),
                'deactivatetime_string' => $contest->getDeactivatetimeString(),
                'hackathon_enabled' => $contest->getHackathonEnabled(),
                'enabled' => $contest->getEnabled(),
                'public' => $contest->getPublic(),
                'process_balloons' => $contest->getProcessBalloons(),
            ],
            'contest_display_data' => null,
            'problems' => [],
            'rubrics' => [],
            'attachments' => []
        ];

        // Export contest display data
        $contestDisplayData = $this->em->getRepository(ContestDisplayData::class)
            ->findOneBy(['contest' => $contest]);
        if ($contestDisplayData) {
            $data['contest_display_data'] = [
                'title' => $contestDisplayData->getTitle(),
                'subtitle' => $contestDisplayData->getSubtitle(),
                'banner_url' => $contestDisplayData->getBannerUrl(),
                'description' => $contestDisplayData->getDescription(),
                'meta_data' => $contestDisplayData->getMetaData(),
            ];
        }

        // Export problems
        $contestProblems = $this->em->getRepository(ContestProblem::class)
            ->findBy(['contest' => $contest]);

        foreach ($contestProblems as $contestProblem) {
            $problem = $contestProblem->getProblem();
            
            $problemData = [
                'contest_problem' => [
                    'shortname' => $contestProblem->getShortname(),
                    'points' => $contestProblem->getPoints(),
                    'allow_submit' => $contestProblem->getAllowSubmit(),
                    'allow_judge' => $contestProblem->getAllowJudge(),
                    'color' => $contestProblem->getColor(),
                ],
                'problem' => [
                    'probid' => $problem->getProbid(),
                    'name' => $problem->getName(),
                    'timelimit' => $problem->getTimelimit(),
                    'memlimit' => $problem->getMemlimit(),
                    'outputlimit' => $problem->getOutputlimit(),
                    'special_run' => $problem->getRunExecutable() ? $problem->getRunExecutable()->getExecid() : null,
                    'special_compare' => $problem->getCompareExecutable() ? $problem->getCompareExecutable()->getExecid() : null,
                    'special_compare_args' => $problem->getSpecialCompareArgs(),
                ],
                'problem_display_data' => null,
                'rubrics' => [],
                'attachments' => []
            ];

            // Export problem display data
            $problemDisplayData = $this->em->getRepository(ProblemDisplayData::class)
                ->findOneBy(['problem' => $problem]);
            if ($problemDisplayData) {
                $problemData['problem_display_data'] = [
                    'display_name' => $problemDisplayData->getDisplayName(),
                    'description' => $problemDisplayData->getDescription(),
                    'image_url' => $problemDisplayData->getImageUrl(),
                    'attachments' => $problemDisplayData->getAttachments(),
                    'meta_data' => $problemDisplayData->getMetaData(),
                ];
            }

            // Export rubrics for this problem
            $rubrics = $this->em->getRepository(Rubric::class)
                ->findBy(['problem' => $problem]);
            
            foreach ($rubrics as $rubric) {
                $rubricData = [
                    'name' => $rubric->getName(),
                    'type' => $rubric->getType(),
                    'weight' => $rubric->getWeight(),
                    'threshold' => $rubric->getThreshold(),
                    'description' => $rubric->getDescription(),
                    'attachments' => []
                ];

                // Export attachments for this rubric
                $attachments = $this->em->getRepository(ProblemAttachment::class)
                    ->findBy(['problem' => $problem, 'rubric' => $rubric]);
                
                foreach ($attachments as $attachment) {
                    $attachmentData = [
                        'name' => $attachment->getName(),
                        'type' => $attachment->getType(),
                        'url' => $attachment->getUrl(),
                        'description' => $attachment->getDescription(),
                        'meta_data' => $attachment->getMetaData(),
                        'mime_type' => $attachment->getMimeType(),
                        'has_file' => $attachment->getContent() !== null,
                        'file_path' => null
                    ];

                    if ($attachment->getContent()) {
                        $filePath = "attachments/problem_{$problem->getProbid()}/rubric_{$rubric->getRubricid()}/{$attachment->getName()}";
                        $attachmentData['file_path'] = $filePath;
                    }

                    $rubricData['attachments'][] = $attachmentData;
                }

                $problemData['rubrics'][] = $rubricData;
            }

            // Export general problem attachments (not rubric-specific)
            $generalAttachments = $this->em->getRepository(ProblemAttachment::class)
                ->findBy(['problem' => $problem, 'rubric' => null]);
            
            foreach ($generalAttachments as $attachment) {
                $attachmentData = [
                    'name' => $attachment->getName(),
                    'type' => $attachment->getType(),
                    'url' => $attachment->getUrl(),
                    'description' => $attachment->getDescription(),
                    'meta_data' => $attachment->getMetaData(),
                    'mime_type' => $attachment->getMimeType(),
                    'has_file' => $attachment->getContent() !== null,
                    'file_path' => null
                ];

                if ($attachment->getContent()) {
                    $filePath = "attachments/problem_{$problem->getProbid()}/general/{$attachment->getName()}";
                    $attachmentData['file_path'] = $filePath;
                }

                $problemData['attachments'][] = $attachmentData;
            }

            $data['problems'][] = $problemData;
        }

        return $data;
    }

    private function exportContestFiles(Contest $contest, string $tempDir): void
    {
        $projectDir = $this->getParameter('kernel.project_dir');
        $filesDir = $tempDir . '/files';
        mkdir($filesDir, 0755, true);
        
        // Export contest banner
        $contestDisplayData = $this->em->getRepository(ContestDisplayData::class)
            ->findOneBy(['contest' => $contest]);
        if ($contestDisplayData && $contestDisplayData->getBannerUrl()) {
            $this->copyFileIfExists(
                $projectDir . '/public' . $contestDisplayData->getBannerUrl(),
                $filesDir . '/contest_banner' . $this->getFileExtension($contestDisplayData->getBannerUrl())
            );
        }

        // Export problem data and files
        $contestProblems = $this->em->getRepository(ContestProblem::class)
            ->findBy(['contest' => $contest]);

        foreach ($contestProblems as $contestProblem) {
            $problem = $contestProblem->getProblem();
            $problemDir = $filesDir . '/problem_' . $problem->getProbid();
            
            // Export problem banner
            $problemDisplayData = $this->em->getRepository(ProblemDisplayData::class)
                ->findOneBy(['problem' => $problem]);
            if ($problemDisplayData && $problemDisplayData->getImageUrl()) {
                mkdir($problemDir, 0755, true);
                $this->copyFileIfExists(
                    $projectDir . '/public' . $problemDisplayData->getImageUrl(),
                    $problemDir . '/banner' . $this->getFileExtension($problemDisplayData->getImageUrl())
                );
            }
            
            // Export problem attachments (from display data)
            if ($problemDisplayData && $problemDisplayData->getAttachments()) {
                foreach ($problemDisplayData->getAttachments() as $index => $attachment) {
                    if (isset($attachment['url']) && !str_starts_with($attachment['url'], 'http')) {
                        mkdir($problemDir . '/attachments', 0755, true);
                        $this->copyFileIfExists(
                            $projectDir . '/public' . $attachment['url'],
                            $problemDir . '/attachments/attachment_' . $index . $this->getFileExtension($attachment['url'])
                        );
                    }
                }
            }
            
            // Export traditional problem attachments
            $attachments = $this->em->getRepository(ProblemAttachment::class)
                ->findBy(['problem' => $problem]);
            
            foreach ($attachments as $attachment) {
                $content = $attachment->getContent();
                if ($content) {
                    $subDir = $attachment->getRubric() 
                        ? $problemDir . "/traditional_attachments/rubric_{$attachment->getRubric()->getRubricid()}"
                        : $problemDir . "/traditional_attachments/general";
                    
                    if (!is_dir($subDir)) {
                        mkdir($subDir, 0755, true);
                    }
                    
                    file_put_contents($subDir . '/' . $attachment->getName(), $content->getContent());
                }
            }
        }
        
        // Export submission deliverable files
        $submissions = $this->em->getRepository(Submission::class)
            ->findBy(['contest' => $contest]);
            
        foreach ($submissions as $submission) {
            $deliverables = $this->em->getRepository(SubmissionDeliverable::class)
                ->findBy(['submission' => $submission]);
                
            foreach ($deliverables as $deliverable) {
                if ($deliverable->getFileType() !== 'url' && $deliverable->getFileType() !== 'text' && $deliverable->getFileType() !== 'metadata') {
                    $deliverableDir = $filesDir . '/submission_' . $submission->getSubmitid() . '/deliverables';
                    mkdir($deliverableDir, 0755, true);
                    
                    $this->copyFileIfExists(
                        $projectDir . '/deliverables/' . $deliverable->getUrl(),
                        $deliverableDir . '/' . basename($deliverable->getUrl())
                    );
                }
            }
        }
    }
    
    private function copyFileIfExists(string $source, string $destination): void
    {
        if (file_exists($source)) {
            $destDir = dirname($destination);
            if (!is_dir($destDir)) {
                mkdir($destDir, 0755, true);
            }
            copy($source, $destination);
        }
    }
    
    private function getFileExtension(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        return pathinfo($path, PATHINFO_EXTENSION) ? '.' . pathinfo($path, PATHINFO_EXTENSION) : '';
    }

    private function createZipArchive(string $sourceDir, string $zipPath): void
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
            throw new \Exception('Could not create ZIP archive');
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDir),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($sourceDir) + 1);
                $zip->addFile($filePath, $relativePath);
            }
        }

        $zip->close();
    }

    private function importContestData(array $contestData, string $tempDir): array
    {
        $this->em->beginTransaction();
        
        try {
            // Create new contest
            $contest = new Contest();
            $contest->setName($contestData['contest']['name'] . ' (Imported)');
            $contest->setShortname($contestData['contest']['shortname'] . '_import_' . time());
            $contest->setActivatetimeString($contestData['contest']['activatetime_string']);
            $contest->setStarttimeString($contestData['contest']['starttime_string']);
            $contest->setFreezetimeString($contestData['contest']['freezetime_string']);
            $contest->setEndtimeString($contestData['contest']['endtime_string']);
            $contest->setUnfreezetimeString($contestData['contest']['unfreezetime_string']);
            $contest->setDeactivatetimeString($contestData['contest']['deactivatetime_string']);
            $contest->setHackathonEnabled($contestData['contest']['hackathon_enabled'] ?? false);
            $contest->setEnabled($contestData['contest']['enabled'] ?? true);
            $contest->setPublic($contestData['contest']['public'] ?? true);
            $contest->setProcessBalloons($contestData['contest']['process_balloons'] ?? true);
            
            $this->em->persist($contest);
            $this->em->flush(); // Get contest ID

            $stats = [
                'contest_id' => $contest->getCid(),
                'problems_count' => 0,
                'rubrics_count' => 0,
                'attachments_count' => 0
            ];

            // Import contest display data
            if ($contestData['contest_display_data']) {
                $displayData = new ContestDisplayData();
                $displayData->setContest($contest);
                $displayData->setTitle($contestData['contest_display_data']['title']);
                $displayData->setSubtitle($contestData['contest_display_data']['subtitle']);
                $displayData->setBannerUrl($contestData['contest_display_data']['banner_url']);
                $displayData->setDescription($contestData['contest_display_data']['description']);
                $displayData->setMetaData($contestData['contest_display_data']['meta_data']);
                // allow_phase removed from export format in this deployment
                
                $this->em->persist($displayData);
            }

            // Import problems
            foreach ($contestData['problems'] as $problemData) {
                $problem = new Problem();
                $problem->setName($problemData['problem']['name']);
                $problem->setTimelimit($problemData['problem']['timelimit']);
                $problem->setMemlimit($problemData['problem']['memlimit']);
                $problem->setOutputlimit($problemData['problem']['outputlimit']);
                $problem->setSpecialCompareArgs($problemData['problem']['special_compare_args']);
                
                // Note: We skip setting executable relationships as they would need to exist in the target system
                // and may have different IDs. These would need to be set manually after import if needed.
                
                $this->em->persist($problem);
                $this->em->flush(); // Get problem ID
                $stats['problems_count']++;

                // Create contest-problem relationship
                $contestProblem = new ContestProblem();
                $contestProblem->setContest($contest);
                $contestProblem->setProblem($problem);
                $contestProblem->setShortname($problemData['contest_problem']['shortname']);
                $contestProblem->setPoints($problemData['contest_problem']['points']);
                $contestProblem->setAllowSubmit($problemData['contest_problem']['allow_submit']);
                $contestProblem->setAllowJudge($problemData['contest_problem']['allow_judge']);
                $contestProblem->setColor($problemData['contest_problem']['color']);
                
                $this->em->persist($contestProblem);

                // Import problem display data
                if ($problemData['problem_display_data']) {
                    $problemDisplay = new ProblemDisplayData();
                    $problemDisplay->setProblem($problem);
                    $problemDisplay->setDisplayName($problemData['problem_display_data']['display_name']);
                    $problemDisplay->setDescription($problemData['problem_display_data']['description']);
                    $problemDisplay->setImageUrl($problemData['problem_display_data']['image_url']);
                    $problemDisplay->setAttachments($problemData['problem_display_data']['attachments']);
                    $problemDisplay->setMetaData($problemData['problem_display_data']['meta_data']);
                    $problemDisplay->setCreatedAt(new \DateTime());
                    $problemDisplay->setUpdatedAt(new \DateTime());
                    
                    $this->em->persist($problemDisplay);
                }

                // Import rubrics
                foreach ($problemData['rubrics'] as $rubricData) {
                    $rubric = new Rubric();
                    $rubric->setProblem($problem);
                    $rubric->setName($rubricData['name']);
                    $rubric->setType($rubricData['type']);
                    $rubric->setWeight($rubricData['weight']);
                    $rubric->setThreshold($rubricData['threshold']);
                    $rubric->setDescription($rubricData['description']);
                    
                    $this->em->persist($rubric);
                    $this->em->flush(); // Get rubric ID
                    $stats['rubrics_count']++;

                    // Import rubric attachments
                    foreach ($rubricData['attachments'] as $attachmentData) {
                        $stats['attachments_count'] += $this->importAttachment(
                            $attachmentData, $problem, $rubric, $tempDir
                        );
                    }
                }

                // Import general problem attachments
                foreach ($problemData['attachments'] as $attachmentData) {
                    $stats['attachments_count'] += $this->importAttachment(
                        $attachmentData, $problem, null, $tempDir
                    );
                }
            }

            $this->em->commit();
            return $stats;

        } catch (\Exception $e) {
            $this->em->rollback();
            throw $e;
        }
    }

    private function importAttachment(array $attachmentData, Problem $problem, ?Rubric $rubric, string $tempDir): int
    {
        $attachment = new ProblemAttachment();
        $attachment->setProblem($problem);
        $attachment->setRubric($rubric);
        $attachment->setName($attachmentData['name']);
        $attachment->setType($attachmentData['type']);
        $attachment->setUrl($attachmentData['url']);
        $attachment->setDescription($attachmentData['description']);
        $attachment->setMetaData($attachmentData['meta_data']);
        $attachment->setMimeType($attachmentData['mime_type']);

        // Import file content if exists
        if ($attachmentData['has_file'] && $attachmentData['file_path']) {
            $filePath = $tempDir . '/' . $attachmentData['file_path'];
            if (file_exists($filePath)) {
                $content = new ProblemAttachmentContent();
                $content->setContent(file_get_contents($filePath));
                $content->setAttachment($attachment);
                $attachment->setContent($content);
                
                $this->em->persist($content);
            }
        }

        $this->em->persist($attachment);
        return 1;
    }

    private function cleanupDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }

        rmdir($dir);
    }

    #[Route('/export-form/{contestId}', name: 'jury_export_form', methods: ['GET'])]
    public function showExportForm(int $contestId): Response
    {
        $contest = $this->em->getRepository(Contest::class)->find($contestId);
        if (!$contest) {
            throw $this->createNotFoundException('Contest not found');
        }

        return $this->render('extensions_plugin/export_import.html.twig', [
            'contest' => $contest,
            'mode' => 'export'
        ]);
    }

    #[Route('/import-form', name: 'jury_import_form', methods: ['GET'])]
    public function showImportForm(): Response
    {
        return $this->render('extensions_plugin/export_import.html.twig', [
            'mode' => 'import'
        ]);
    }
    
    private function importContestFiles(array $contestData, string $tempDir, int $contestId): int
    {
        $filesImported = 0;
        $projectDir = $this->getParameter('kernel.project_dir');
        $filesDir = $tempDir . '/files';
        
        if (!is_dir($filesDir)) {
            return 0; // No files to import
        }
        
        // Import contest banner
        $contestBannerFiles = glob($filesDir . '/contest_banner.*');
        if (!empty($contestBannerFiles)) {
            $contestBannerFile = $contestBannerFiles[0];
            $extension = pathinfo($contestBannerFile, PATHINFO_EXTENSION);
            $newPath = '/uploads/contest_banners/contest_' . $contestId . '_banner.' . $extension;
            $fullPath = $projectDir . '/public' . $newPath;
            
            $uploadDir = dirname($fullPath);
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            if (copy($contestBannerFile, $fullPath)) {
                // Update contest display data with new banner URL
                $contest = $this->em->getRepository(Contest::class)->find($contestId);
                $displayData = $this->em->getRepository(ContestDisplayData::class)
                    ->findOneBy(['contest' => $contest]);
                if ($displayData) {
                    $displayData->setBannerUrl($newPath);
                    $this->em->persist($displayData);
                    $this->em->flush();
                }
                $filesImported++;
            }
        }
        
        // Import problem files
        foreach ($contestData['problems'] as $problemData) {
            $problemName = $problemData['problem']['name'];
            
            // Try to find the problem directory - handle different export formats
            $problemDir = null;
            $possibleDirs = [];
            
            // If probid exists, try that first
            if (isset($problemData['problem']['probid'])) {
                $possibleDirs[] = $filesDir . '/problem_' . $problemData['problem']['probid'];
            }
            
            // Also try with problem name and shortname as fallbacks
            $possibleDirs[] = $filesDir . '/problem_' . $problemName;
            if (isset($problemData['contest_problem']['shortname'])) {
                $possibleDirs[] = $filesDir . '/problem_' . $problemData['contest_problem']['shortname'];
            }
            
            // Find the first directory that exists
            foreach ($possibleDirs as $dir) {
                if (is_dir($dir)) {
                    $problemDir = $dir;
                    break;
                }
            }
            
            if (!$problemDir) {
                continue; // No files for this problem
            }
            
            // Find the imported problem by name (since it's a new import)
            $contest = $this->em->getRepository(Contest::class)->find($contestId);
            $contestProblems = $this->em->getRepository(ContestProblem::class)
                ->findBy(['contest' => $contest]);
                
            $problem = null;
            foreach ($contestProblems as $cp) {
                if ($cp->getProblem()->getName() === $problemName) {
                    $problem = $cp->getProblem();
                    break;
                }
            }
                
            if (!$problem) {
                continue;
            }
            
            // Import problem banner
            $problemBannerFiles = glob($problemDir . '/banner.*');
            if (!empty($problemBannerFiles)) {
                $problemBannerFile = $problemBannerFiles[0];
                $extension = pathinfo($problemBannerFile, PATHINFO_EXTENSION);
                $newPath = '/uploads/problem_images/problem_' . $problem->getProbid() . '_banner.' . $extension;
                $fullPath = $projectDir . '/public' . $newPath;
                
                $uploadDir = dirname($fullPath);
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                if (copy($problemBannerFile, $fullPath)) {
                    // Update problem display data with new image URL
                    $problemDisplay = $this->em->getRepository(ProblemDisplayData::class)
                        ->findOneBy(['problem' => $problem]);
                    if ($problemDisplay) {
                        $problemDisplay->setImageUrl($newPath);
                        $this->em->persist($problemDisplay);
                        $this->em->flush();
                    }
                    $filesImported++;
                }
            }
            
            // Import problem attachments from display data
            $attachmentsDir = $problemDir . '/attachments';
            if (is_dir($attachmentsDir)) {
                $attachmentFiles = glob($attachmentsDir . '/*');
                foreach ($attachmentFiles as $attachmentFile) {
                    $filename = basename($attachmentFile);
                    $newPath = '/uploads/problem_attachments/problem_' . $problem->getProbid() . '_' . $filename;
                    $fullPath = $projectDir . '/public' . $newPath;
                    
                    $uploadDir = dirname($fullPath);
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    
                    if (copy($attachmentFile, $fullPath)) {
                        // Update attachment URLs in problem display data
                        $problemDisplay = $this->em->getRepository(ProblemDisplayData::class)
                            ->findOneBy(['problem' => $problem]);
                        if ($problemDisplay) {
                            $attachments = $problemDisplay->getAttachments() ?? [];
                            foreach ($attachments as &$attachment) {
                                if (basename($attachment['url'] ?? '') === $filename) {
                                    $attachment['url'] = $newPath;
                                }
                            }
                            $problemDisplay->setAttachments($attachments);
                            $this->em->persist($problemDisplay);
                        }
                        $filesImported++;
                    }
                }
                $this->em->flush();
            }
        }
        
        // Note: Submission deliverable files are not imported as they belong to specific submissions
        // that don't exist in the new contest yet. They would be recreated when submissions are made.
        
        return $filesImported;
    }
}
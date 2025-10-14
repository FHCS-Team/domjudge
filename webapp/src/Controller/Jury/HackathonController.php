<?php declare(strict_types=1);

namespace App\Controller\Jury;

use App\Controller\BaseController;
use App\Service\DOMJudgeService;
use App\Entity\Contest;
use App\Entity\ContestDisplayData;
use App\Form\Type\ContestDisplayDataType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;


#[IsGranted('ROLE_JURY')]
#[Route(path: '/jury/hackathon')]
class HackathonController extends BaseController
{
    public function __construct(
        protected readonly EntityManagerInterface $em
    ) {}

    #[Route(path: '', name: 'jury_hackathon')]
    public function index(): Response
    {
        $em = $this->em;
        $contests = $em->createQueryBuilder()
            ->select('c')
            ->from(Contest::class, 'c')
            ->orderBy('c.starttime', 'DESC')
            ->groupBy('c.cid')
            ->getQuery()->getResult();

        // Table fields (copy from ContestController)
        $table_fields = [
            'cid'          => ['title' => 'CID', 'sort' => true],
            'shortname'    => ['title' => 'shortname', 'sort' => true],
            'name'         => ['title' => 'name', 'sort' => true],
            'activatetime' => ['title' => 'activate', 'sort' => true],
            'starttime'    => ['title' => 'start', 'sort' => true, 'default_sort' => true, 'default_sort_order' => 'desc'],
            'endtime'      => ['title' => 'end', 'sort' => true],
        ];

        $contests_table = [];
        foreach ($contests as $contest) {
            $contestdata = [];
            foreach ($table_fields as $k => $v) {
                if (method_exists($contest, 'get' . ucfirst($k))) {
                    $contestdata[$k] = ['value' => $contest->{'get' . ucfirst($k)}()];
                } elseif (property_exists($contest, $k)) {
                    $contestdata[$k] = ['value' => $contest->$k];
                } else {
                    $contestdata[$k] = ['value' => null];
                }
            }
            $contests_table[] = [
                'data' => $contestdata,
                'actions' => [
                    [
                        'icon' => 'edit',
                        'title' => 'Configure display data',
                        'link' => $this->generateUrl('jury_hackathon_display', ['contestId' => $contest->getCid()]),
                    ],
                ],
                'link' => null,
                'cssclass' => '',
            ];
        }

        return $this->render('extensions_plugin/hackathon.html.twig', [
            'contests_table' => $contests_table,
            'table_fields' => $table_fields,
        ]);
    }

    #[Route(path: '/{contestId<\\d+>}/display', name: 'jury_hackathon_display')]
    public function displayConfig(Request $request, int $contestId): Response
    {
        try {
            $contest = $this->em->getRepository(Contest::class)->find($contestId);
            if (!$contest) {
                $this->addFlash('danger', 'Contest not found.');
                return $this->redirectToRoute('jury_hackathon');
            }


            $repo = $this->em->getRepository(ContestDisplayData::class);
            $displayData = $repo->findOneBy(['contest' => $contest]);
            if (!$displayData) {
                $displayData = new ContestDisplayData();
                $displayData->setContest($contest);
                $this->em->persist($displayData);
                $this->em->flush();
            }

            // Phase support has been disabled in this deployment; do not create phases automatically.

            // Metadata storage removed from display data entity in this deployment.
            // Any media uploads will be shown immediately but not stored on the ContestDisplayData entity.

            $form = $this->createForm(ContestDisplayDataType::class, $displayData);
            $form->handleRequest($request);

            $mediaSnippet = null;
            if ($form->isSubmitted()) {
                // Debug: Check what files were uploaded
                $bannerFile = $form->get('bannerFile')->getData();
                if ($bannerFile) {
                    $this->addFlash('info', 'DEBUG - Banner file detected: ' . $bannerFile->getClientOriginalName() . 
                        ', Size: ' . $bannerFile->getSize() . 
                        ', MIME: ' . $bannerFile->getMimeType() . 
                        ', Extension: ' . $bannerFile->guessExtension());
                }
                
                if (!$form->isValid()) {
                    // Add form validation errors to flash messages for debugging
                    foreach ($form->getErrors(true) as $error) {
                        $this->addFlash('danger', 'Form error: ' . $error->getMessage());
                    }
                    // Also check individual field errors
                    if ($form->get('bannerFile')->getErrors()->count() > 0) {
                        foreach ($form->get('bannerFile')->getErrors() as $error) {
                            $this->addFlash('danger', 'Banner file error: ' . $error->getMessage());
                        }
                    }
                    // Don't process uploads if validation failed
                } else {
                    // Handle banner file upload and delete previous banner if needed
                    try {
                        $uploadsDir = $this->getParameter('kernel.project_dir') . '/public/uploads/hackathon_banners';
                        if (!is_dir($uploadsDir) && !@mkdir($uploadsDir, 0775, true)) {
                            throw new \RuntimeException('Failed to create banners upload directory.');
                        }
                        $bannerFile = $form->get('bannerFile')->getData();
                        if ($bannerFile) {
                            // Debug info
                            $this->addFlash('info', 'Banner file received: ' . $bannerFile->getClientOriginalName() . ' (' . $bannerFile->getMimeType() . ')');
                            
                            $prevBanner = $displayData->getBannerUrl();
                            if ($prevBanner && str_starts_with($prevBanner, '/uploads/hackathon_banners/')) {
                                $prevBannerPath = $this->getParameter('kernel.project_dir') . '/public' . $prevBanner;
                                if (is_file($prevBannerPath) && !@unlink($prevBannerPath)) {
                                    $this->addFlash('warning', 'Could not delete previous banner image.');
                                }
                            }
                            $safeName = 'banner_' . $contest->getCid() . '_' . uniqid() . '.' . $bannerFile->guessExtension();
                            $bannerFile->move($uploadsDir, $safeName);
                            $newBannerUrl = '/uploads/hackathon_banners/' . $safeName;
                            $displayData->setBannerUrl($newBannerUrl);
                            $this->addFlash('success', 'Banner uploaded successfully: ' . $newBannerUrl);
                        }
                    } catch (\Throwable $e) {
                        $this->addFlash('danger', 'Banner upload failed: ' . $e->getMessage());
                    }

                // Handle media file upload (image/video)
                try {
                    $mediaFile = $form->get('mediaFile')->getData();
                    if ($mediaFile) {
                        $mediaDir = $this->getParameter('kernel.project_dir') . '/public/uploads/hackathon_media';
                        if (!is_dir($mediaDir) && !@mkdir($mediaDir, 0775, true)) {
                            throw new \RuntimeException('Failed to create media upload directory.');
                        }
                        $safeName = 'media_' . $contest->getCid() . '_' . uniqid() . '.' . $mediaFile->guessExtension();
                        $mime = $mediaFile->getMimeType(); // Get MIME type BEFORE move
                        $mediaFile->move($mediaDir, $safeName);
                        $mediaUrl = '/uploads/hackathon_media/' . $safeName;
                        if (str_starts_with($mime, 'image/')) {
                            $mediaSnippet = '<img src="' . $mediaUrl . '" alt="Media">';
                        } elseif (str_starts_with($mime, 'video/')) {
                            $mediaSnippet = '<video src="' . $mediaUrl . '" controls></video>';
                        } else {
                            $mediaSnippet = $mediaUrl;
                        }
                        // We do not persist media metadata to ContestDisplayData; it's handled in dedicated tables.
                    }
                } catch (\Throwable $e) {
                    $this->addFlash('danger', 'Media upload failed: ' . $e->getMessage());
                }

                try {
                    $this->em->persist($displayData);
                    $this->em->flush();
                    $this->addFlash('success', 'Display data saved.');
                } catch (\Throwable $e) {
                    $this->addFlash('danger', 'Failed to save display data: ' . $e->getMessage());
                }
                // If media was uploaded, show snippet after redirect
                if ($mediaSnippet) {
                    $request->getSession()->set('mediaSnippet', $mediaSnippet);
                }
                return $this->redirectToRoute('jury_hackathon_display', ['contestId' => $contestId]);
                }
            }

            // Show media snippet if available in session
            $session = $request->getSession();
            $mediaSnippet = $mediaSnippet ?? $session->get('mediaSnippet');
            if ($mediaSnippet) {
                $session->remove('mediaSnippet');
            }
            return $this->render('extensions_plugin/hackathon_display.html.twig', [
                'contest' => $contest,
                'form' => $form->createView(),
                'displayData' => $displayData,
                'mediaSnippet' => $mediaSnippet,
            ]);
        } catch (\Throwable $e) {
            $this->addFlash('danger', 'Unexpected error: ' . $e->getMessage());
            return $this->redirectToRoute('jury_hackathon');
        }
    }
    #[Route(path: '/quick-add', name: 'jury_hackathon_quick_add')]
    public function quickAddHackathon(): Response
    {
        // Create a new Contest entity with some default values
        $contest = new Contest();
        $now = time();
        $contest->setName('New Hackathon ' . date('Y-m-d H:i', $now));
        $contest->setShortname('hackathon_' . $now);
        $contest->setStarttime(date('Y-m-d H:i:s', $now + 3600)); // Start in 1 hour
        $contest->setEndtime(date('Y-m-d H:i:s', $now + 3600 * 4)); // End in 4 hours
        $contest->setActivatetime(date('Y-m-d H:i:s', $now));
        $contest->setEnabled(true);
        $contest->setAllowSubmit(true);

        $this->em->persist($contest);
        $this->em->flush();

        $this->addFlash('success', 'Hackathon contest created!');
        // Redirect to the display config page for the new contest
        return $this->redirectToRoute('jury_hackathon_display', ['contestId' => $contest->getCid()]);
    }
    
        #[Route(path: '/{contestId<\d+>}/problems', name: 'jury_hackathon_problems')]
    public function problemsTab(int $contestId): Response
    {
        $contest = $this->em->getRepository(Contest::class)->find($contestId);
        if (!$contest) {
            $this->addFlash('danger', 'Contest not found.');
            return $this->redirectToRoute('jury_hackathon');
        }

        // Fetch problems for this contest
        $problems = $this->em->createQueryBuilder()
            ->select('cp', 'p')
            ->from('App\\Entity\\ContestProblem', 'cp')
            ->leftJoin('cp.problem', 'p')
            ->where('cp.contest = :contest')
            ->setParameter('contest', $contest)
            ->orderBy('cp.shortname', 'ASC')
            ->getQuery()->getResult();

        return $this->render('extensions_plugin/hackathon_problems.html.twig', [
            'contest' => $contest,
            'problems' => $problems,
        ]);
    }

    #[Route(path: '/{contestId<\d+>}/problems/quickadd', name: 'jury_hackathon_quickadd_problem', methods: ['POST'])]
    public function quickAddProblem(Request $request, int $contestId): Response
    {
        $contest = $this->em->getRepository(Contest::class)->find($contestId);
        if (!$contest) {
            $this->addFlash('danger', 'Contest not found.');
            return $this->redirectToRoute('jury_hackathon');
        }

        $shortname = trim($request->request->get('shortname', ''));
        $name = trim($request->request->get('name', ''));
        $attachment = $request->files->get('attachment');

        // Count existing problems for this contest
        $problemCount = $this->em->getRepository(\App\Entity\ContestProblem::class)
            ->count(['contest' => $contest]);

        $autoShortname = 'P' . ($problemCount + 1);
        $autoName = 'New Problem ' . date('Y-m-d H:i');
        $finalShortname = $shortname !== '' ? $shortname : $autoShortname;
        $finalName = $name !== '' ? $name : $autoName;

        $problem = new \App\Entity\Problem();
        $problem->setName($finalName);
        $problem->setTimelimit(2.0);
        $problem->setMemlimit(262144); // 256 MB default
        $this->em->persist($problem);

        $contestProblem = new \App\Entity\ContestProblem();
        $contestProblem->setContest($contest);
        $contestProblem->setProblem($problem);
        $contestProblem->setShortname($finalShortname);
        $this->em->persist($contestProblem);

        // Create ProblemDisplayData
        $displayData = new \App\Entity\ProblemDisplayData();
        $displayData->setProblem($problem);
        $displayData->setDisplayName($finalShortname);
        $descTemplate = '<h2>' . htmlspecialchars($finalName) . '</h2>' .
            "\n<p><strong>Description:</strong><br>Describe the problem statement here. Explain what the task is and any background information.</p>" .
            "\n<p><strong>Input</strong><br>Describe the input format and constraints.</p>" .
            "\n<p><strong>Output</strong><br>Describe the output format and requirements.</p>" .
            "\n<p><strong>Sample Input</strong><br><pre>1 2 3\n4 5 6</pre></p>" .
            "\n<p><strong>Sample Output</strong><br><pre>6\n15</pre></p>";
        $displayData->setDescription($descTemplate);

        // Attachments/metadata are now handled via dedicated tables; attachment upload handled elsewhere.
        $this->em->persist($displayData);
        $this->em->flush();

        $this->addFlash('success', 'Problem added!');
        return $this->redirectToRoute('jury_hackathon_problems', ['contestId' => $contestId]);
    }

    #[Route(path: '/{contestId<\\d+>}/problems/{problemId<\\d+>}/attachments/ajax-add', name: 'jury_hackathon_attachment_ajax_add', methods: ['POST'])]
    public function ajaxAddProblemAttachment(Request $request, int $contestId, int $problemId): Response
    {
        $problem = $this->em->getRepository(\App\Entity\Problem::class)->find($problemId);
        if (!$problem) {
            return $this->json(['success' => false, 'error' => 'Problem not found'], 404);
        }

        $name = trim((string)$request->request->get('attachmentName', '')) ?: $request->files->get('attachmentFile')?->getClientOriginalName() ?? 'attachment';
        $scope = $request->request->get('attachmentScope', 'public');
        $contentType = $request->request->get('attachmentContentType', '');
        $link = trim((string)$request->request->get('attachmentLink', '')) ?: null;

        $attachment = new \App\Entity\ProblemAttachment();
        $attachment->setProblem($problem);
        $attachment->setName($name);
        $attachment->setType($scope);

        // Some installs may not have the `url` column on problem_attachment.
        // Check schema first and only set attachment.url if the column exists.
        $conn = $this->em->getConnection();
        $attachmentHasUrlColumn = false;
        try {
            $cols = $conn->executeQuery('SHOW COLUMNS FROM problem_attachment')->fetchAllAssociative();
            foreach ($cols as $col) {
                if (($col['Field'] ?? null) === 'url') {
                    $attachmentHasUrlColumn = true;
                    break;
                }
            }
        } catch (\Throwable $e) {
            // If inspection fails, be conservative and assume the column does not exist
            $attachmentHasUrlColumn = false;
        }

        if ($link && $attachmentHasUrlColumn) {
            $attachment->setUrl($link);
        }

        // If a file was uploaded, create a content row
        $file = $request->files->get('attachmentFile');
        if ($file instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
            $content = new \App\Entity\ProblemAttachmentContent();
            $content->setContent(file_get_contents($file->getRealPath()));
            $content->setAttachment($attachment);
            $content->setType($contentType ?: null);
            $attachment->setContent($content);
            $attachment->setMimeType($file->getMimeType());
        } elseif ($link) {
            // If link-only, create a content row with URL
            $content = new \App\Entity\ProblemAttachmentContent();
            $content->setContent('');
            $content->setAttachment($attachment);
            $content->setUrl($link);
            $content->setType($contentType ?: null);
            $attachment->setContent($content);
        }

        try {
            $this->em->persist($attachment);
            $this->em->flush();
        } catch (\Throwable $e) {
            return $this->json(['success' => false, 'error' => 'Database error: ' . $e->getMessage()], 500);
        }

        // Construct a safe, relative preview URL for the attachment. Do not rely on a
        // specific route name that may not exist in some installs. The API attachment
        // route is normally mounted under /contests/{cid}/problems/{id}/attachment/{filename}.
        $contestIdForUrl = $problem->getContest() ? $problem->getContest()->getCid() : null;
        $problemIdForUrl = $problem->getExternalid() ?? (string)$problem->getProbid();
        if ($contestIdForUrl) {
            $previewUrl = '/contests/' . rawurlencode((string)$contestIdForUrl) . '/problems/' . rawurlencode($problemIdForUrl) . '/attachment/' . rawurlencode($attachment->getName());
        } else {
            // Fallback: use a minimal filename-based URL under /attachments – this may need
            // adjustment depending on your routing; it's safer than throwing a 500.
            $previewUrl = '/attachments/' . rawurlencode($attachment->getName());
        }

        return $this->json([
            'success' => true,
            'attachment' => [
                'attachmentid' => $attachment->getAttachmentid(),
                'name' => $attachment->getName(),
                'type' => $attachment->getType(),
                'url' => $attachment->getUrl() ?: $previewUrl,
                'contentType' => $contentType,
            ],
        ]);
    }
    #[Route(path: '/{contestId<\\d+>}/problems/{problemId<\\d+>}/edit-display', name: 'jury_hackathon_edit_problem_display')]
    public function editProblemDisplayData(Request $request, int $contestId, int $problemId): Response
    {
        $contest = $this->em->getRepository(Contest::class)->find($contestId);
        if (!$contest) {
            $this->addFlash('danger', 'Contest not found.');
            return $this->redirectToRoute('jury_hackathon');
        }

        $problem = $this->em->getRepository(\App\Entity\Problem::class)->find($problemId);
        if (!$problem) {
            $this->addFlash('danger', 'Problem not found.');
            return $this->redirectToRoute('jury_hackathon_problems', ['contestId' => $contestId]);
        }

        $repo = $this->em->getRepository(\App\Entity\ProblemDisplayData::class);
        $displayData = $repo->findOneBy(['problem' => $problem]);
        if (!$displayData) {
            $displayData = new \App\Entity\ProblemDisplayData();
            $displayData->setProblem($problem);
        }

        $form = $this->createForm(\App\Form\Type\ProblemDisplayDataType::class, $displayData);
        $form->handleRequest($request);

        // Inline Rubric add form
        $rubric = new \App\Entity\Rubric();
        $rubric->setProblem($problem);
        $rubricForm = $this->createForm(\App\Form\Type\RubricType::class, $rubric, [
            'action' => $this->generateUrl('jury_hackathon_edit_problem_display', ['contestId' => $contestId, 'problemId' => $problemId]),
            'method' => 'POST',
        ]);
        $rubricForm->handleRequest($request);

        // Handle rubric form submission
        if ($rubricForm->isSubmitted() && $rubricForm->isValid()) {
            $this->em->persist($rubric);
            $this->em->flush();
            $this->addFlash('success', 'Rubric added.');
            return $this->redirectToRoute('jury_hackathon_edit_problem_display', ['contestId' => $contestId, 'problemId' => $problemId]);
        }

        // Attachment removal and metadata are handled in dedicated controllers/tables in this deployment.
        // Load attachments for preview and listing. Some installs may have an older
        // schema missing newer columns (e.g. `url`). Try the ORM call and fall
        // back to a safe raw query that only selects existing columns.
        try {
            $attachments = $this->em->getRepository(\App\Entity\ProblemAttachment::class)->findBy(['problem' => $problem]);
        } catch (\Throwable $e) {
            // Fallback: inspect table columns and select only those that exist
            $conn = $this->em->getConnection();
            $existing = [];
            try {
                $cols = $conn->executeQuery('SHOW COLUMNS FROM problem_attachment')->fetchAllAssociative();
                foreach ($cols as $col) {
                    $existing[] = $col['Field'];
                }
            } catch (\Throwable $inner) {
                // If we can't inspect columns, return an empty list to avoid crashing the page
                $attachments = [];
            }

            if (!isset($attachments)) {
                $select = ['attachmentid', 'name', 'type', 'description', 'mime_type'];
                if (in_array('url', $existing, true)) {
                    $select[] = 'url';
                }
                $sql = 'SELECT ' . implode(', ', $select) . ' FROM problem_attachment WHERE probid = :probid';
                $rows = $conn->executeQuery($sql, ['probid' => $problem->getProbid()])->fetchAllAssociative();
                $attachments = [];
                foreach ($rows as $row) {
                    $obj = new \stdClass();
                    $obj->attachmentid = (int)($row['attachmentid'] ?? 0);
                    $obj->name = $row['name'] ?? null;
                    $obj->type = $row['type'] ?? null;
                    $obj->description = $row['description'] ?? null;
                    $obj->mimeType = $row['mime_type'] ?? null;
                    $obj->url = $row['url'] ?? null;
                    // contentType is stored in content rows; unavailable here, set null
                    $obj->contentType = null;
                    $attachments[] = $obj;
                }
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            // Handle banner file upload
            $bannerFile = $form->get('bannerFile')->getData();
            if ($bannerFile) {
                try {
                    $uploadsDir = $this->getParameter('kernel.project_dir') . '/public/uploads/problem_banners';
                    if (!is_dir($uploadsDir) && !@mkdir($uploadsDir, 0775, true)) {
                        throw new \RuntimeException('Failed to create banner upload directory.');
                    }
                    
                    // Delete previous banner if exists
                    $prevBanner = $displayData->getImageUrl();
                    if ($prevBanner && str_starts_with($prevBanner, '/uploads/problem_banners/')) {
                        $prevBannerPath = $this->getParameter('kernel.project_dir') . '/public' . $prevBanner;
                        if (is_file($prevBannerPath) && !@unlink($prevBannerPath)) {
                            $this->addFlash('warning', 'Could not delete previous banner image.');
                        }
                    }
                    
                    $safeName = 'banner_' . $problem->getProbid() . '_' . uniqid() . '.' . $bannerFile->guessExtension();
                    $bannerFile->move($uploadsDir, $safeName);
                    $newBannerUrl = '/uploads/problem_banners/' . $safeName;
                    $displayData->setImageUrl($newBannerUrl);
                    $this->addFlash('success', 'Banner uploaded successfully: ' . $newBannerUrl);
                } catch (\Throwable $e) {
                    $this->addFlash('danger', 'Banner upload failed: ' . $e->getMessage());
                }
            }
            
            // Attachment upload only updates the image/banner URL; other attachments are handled by attachment controllers.
            $this->em->persist($displayData);
            $this->em->flush();
            $this->addFlash('success', 'Problem display data saved.');
            return $this->redirectToRoute('jury_hackathon_edit_problem_display', ['contestId' => $contestId, 'problemId' => $problemId]);
        }

        // Fetch rubrics for this problem
        $rubrics = $this->em->getRepository(\App\Entity\Rubric::class)->findBy(['problem' => $problem]);

        return $this->render('extensions_plugin/edit_problem_display.html.twig', [
            'contest' => $contest,
            'problem' => $problem,
            'form' => $form->createView(),
            'displayData' => $displayData,
            'rubrics' => $rubrics,
            'rubricForm' => $rubricForm->createView(),
            'attachments' => $attachments,
        ]);
    }
    #[Route(path: '/{contestId<\d+>}/toggle-hackathon-enabled', name: 'jury_hackathon_toggle_hackathon_enabled', methods: ['POST'])]
    public function toggleHackathonEnabled(Request $request, int $contestId): Response
    {
        $contest = $this->em->getRepository(Contest::class)->find($contestId);
        if (!$contest) {
            if ($request->isXmlHttpRequest()) {
                return $this->json(['success' => false, 'error' => 'Contest not found'], 404);
            }
            throw $this->createNotFoundException('Contest not found');
        }

        $enabled = null;
        
        // Handle AJAX JSON request
        if ($request->isXmlHttpRequest()) {
            $data = json_decode($request->getContent(), true);
            if (isset($data['enabled'])) {
                $enabled = (bool)$data['enabled'];
            }
        } else {
            // Handle form data request
            $enabled = $request->request->has('enabled') ? (bool)$request->request->get('enabled') : null;
        }
        
        if ($enabled === null) {
            if ($request->isXmlHttpRequest()) {
                return $this->json(['success' => false, 'error' => 'Missing enabled field'], 400);
            }
            $this->addFlash('error', 'Missing enabled field');
            return $this->redirectToRoute('jury_hackathon_display', ['contestId' => $contestId]);
        }

        $contest->setHackathonEnabled($enabled);
        $this->em->persist($contest);
        $this->em->flush();

        if ($request->isXmlHttpRequest()) {
            return $this->json(['success' => true]);
        }
        
        return $this->redirectToRoute('jury_hackathon_display', ['contestId' => $contestId]);
    }
}

<?php declare(strict_types=1);

namespace App\Controller\Jury;

use App\Entity\Problem;
use App\Entity\Rubric;
use App\Entity\ProblemAttachment;
use App\Entity\ProblemAttachmentContent;
use App\Form\Type\RubricType;
use App\Form\Type\ProblemAttachmentType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Form\FormError;

#[IsGranted('ROLE_ADMIN')]
#[Route(path: '/jury/problem-rubrics')]
class ProblemRubricController extends AbstractController
{
    public function __construct(
        protected readonly EntityManagerInterface $em
    ) {}

    #[Route(path: '/problem/{problemId}/rubrics', name: 'jury_problem_rubric_list_for_problem')]
    public function listRubricsForProblemAction(int $problemId): Response
    {
        $problem = $this->em->getRepository(Problem::class)->find($problemId);
        if (!$problem) {
            throw $this->createNotFoundException('Problem not found');
        }
        $rubrics = $this->em->getRepository(Rubric::class)->findBy(['problem' => $problem]);
        return $this->render('jury/problem_rubric/list_for_problem.html.twig', [
            'problem' => $problem,
            'rubrics' => $rubrics,
        ]);
    }

    #[Route(path: '/problem/{problemId}/rubrics/add', name: 'jury_problem_rubric_add_for_problem')]
    public function addRubricForProblemAction(Request $request, int $problemId): Response
    {
        $problem = $this->em->getRepository(Problem::class)->find($problemId);
        if (!$problem) {
            throw $this->createNotFoundException('Problem not found');
        }
        $rubric = new Rubric();
        $rubric->setProblem($problem);
        $form = $this->createForm(RubricType::class, $rubric);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($rubric);
            $this->em->flush();
            $this->addFlash('success', 'Rubric added.');
            return $this->redirectToRoute('jury_problem_rubric_list_for_problem', ['problemId' => $problemId]);
        }
        return $this->render('jury/problem_rubric/add_for_problem.html.twig', [
            'form' => $form->createView(),
            'problem' => $problem,
        ]);
    }

    #[Route(path: '/problem/{problemId}/attachments', name: 'jury_problem_attachment_list_for_problem')]
    public function listAttachmentsForProblemAction(int $problemId): Response
    {
        $problem = $this->em->getRepository(Problem::class)->find($problemId);
        if (!$problem) {
            throw $this->createNotFoundException('Problem not found');
        }
        $attachments = $this->em->getRepository(ProblemAttachment::class)->findBy(['problem' => $problem]);
        return $this->render('jury/problem_attachment/list_for_problem.html.twig', [
            'problem' => $problem,
            'attachments' => $attachments,
        ]);
    }

    #[Route(path: '/problem/{problemId}/attachments/add', name: 'jury_problem_attachment_add_for_problem')]
    public function addAttachmentForProblemAction(Request $request, int $problemId): Response
    {
        $problem = $this->em->getRepository(Problem::class)->find($problemId);
        if (!$problem) {
            throw $this->createNotFoundException('Problem not found');
        }
        $attachment = new ProblemAttachment();
        $attachment->setProblem($problem);
        $form = $this->createForm(ProblemAttachmentType::class, $attachment);
        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            $customType = $form->get('type_custom')->getData();
            if ($customType) {
                $attachment->setType($customType);
            }
            /** @var UploadedFile $file */
            $file = $form->get('content')->getData();
            if ($file) {
                $content = new ProblemAttachmentContent();
                $content->setContent(file_get_contents($file->getRealPath()));
                $content->setAttachment($attachment);
                $attachment->setContent($content);
                $attachment->setMimeType($file->getMimeType());
            }

            // Defensive validation - ensure a type is provided
            if (!$attachment->getType() || $attachment->getType() === '') {
                $form->get('type')->addError(new FormError('Please select a type or enter a custom type.'));
            }

            // Ensure mime type is set to satisfy migration NOT NULL constraint
            if (!$attachment->getMimeType()) {
                $attachment->setMimeType('application/octet-stream');
            }

            if ($form->isValid()) {
                try {
                    $this->em->persist($attachment);
                    $this->em->flush();
                } catch (\Doctrine\DBAL\Exception\DriverException $e) {
                    // Friendly guidance for missing-column issues which occur when
                    // migrations haven't been applied. Surface the DB message but
                    // point to the migration command.
                    $this->addFlash('danger', 'Database error while saving attachment: ' . $e->getMessage() . '. Try running doctrine migrations: "bin/console doctrine:migrations:migrate"');
                    return $this->render('jury/problem_attachment/add_for_problem.html.twig', [
                        'form' => $form->createView(),
                        'problem' => $problem,
                    ]);
                }
                $this->addFlash('success', 'Attachment added.');
                return $this->redirectToRoute('jury_problem_attachment_list_for_problem', ['problemId' => $problemId]);
            }
        }
        return $this->render('jury/problem_attachment/add_for_problem.html.twig', [
            'form' => $form->createView(),
            'problem' => $problem,
        ]);
    }

    #[Route(path: '/problem/{problemId}/rubric/{rubricId}/attachments/add', name: 'jury_problem_attachment_add_for_rubric')]
    public function addAttachmentForRubricAction(Request $request, int $problemId, int $rubricId): Response
    {
        $problem = $this->em->getRepository(Problem::class)->find($problemId);
        if (!$problem) {
            throw $this->createNotFoundException('Problem not found');
        }
        $rubric = $this->em->getRepository(Rubric::class)->find($rubricId);
        if (!$rubric) {
            throw $this->createNotFoundException('Rubric not found');
        }
        $attachment = new ProblemAttachment();
        $attachment->setProblem($problem);
        $attachment->setRubric($rubric);
        $form = $this->createForm(ProblemAttachmentType::class, $attachment);
        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            $customType = $form->get('type_custom')->getData();
            if ($customType) {
                $attachment->setType($customType);
            }
            /** @var UploadedFile $file */
            $file = $form->get('content')->getData();
            if ($file) {
                $content = new ProblemAttachmentContent();
                $content->setContent(file_get_contents($file->getRealPath()));
                $content->setAttachment($attachment);
                $attachment->setContent($content);
                $attachment->setMimeType($file->getMimeType());
            }

            // Defensive validation - ensure a type is provided
            if (!$attachment->getType() || $attachment->getType() === '') {
                $form->get('type')->addError(new FormError('Please select a type or enter a custom type.'));
            }

            if (!$attachment->getMimeType()) {
                $attachment->setMimeType('application/octet-stream');
            }

            if ($form->isValid()) {
                try {
                    $this->em->persist($attachment);
                    $this->em->flush();
                } catch (\Doctrine\DBAL\Exception\DriverException $e) {
                    $this->addFlash('danger', 'Database error while saving attachment: ' . $e->getMessage() . '. Try running doctrine migrations: "bin/console doctrine:migrations:migrate"');
                    return $this->render('jury/problem_attachment/add_for_problem.html.twig', [
                        'form' => $form->createView(),
                        'problem' => $problem,
                        'rubric' => $rubric,
                    ]);
                }
                $this->addFlash('success', 'Attachment added to rubric.');
                return $this->redirectToRoute('jury_problem_rubric_list_for_problem', ['problemId' => $problemId]);
            }
        }
        return $this->render('jury/problem_attachment/add_for_problem.html.twig', [
            'form' => $form->createView(),
            'problem' => $problem,
            'rubric' => $rubric,
        ]);
    }

    #[Route(path: '/problem/{problemId}/rubric/{rubricId}/attachments', name: 'jury_rubric_attachments')]
    public function rubricAttachmentsAction(Request $request, int $problemId, int $rubricId): Response
    {
        $problem = $this->em->getRepository(Problem::class)->find($problemId);
        if (!$problem) {
            throw $this->createNotFoundException('Problem not found');
        }
        $rubric = $this->em->getRepository(Rubric::class)->find($rubricId);
        if (!$rubric) {
            throw $this->createNotFoundException('Rubric not found');
        }

        // Create a new attachment bound to this problem and rubric for the add form
        $attachment = new ProblemAttachment();
        $attachment->setProblem($problem);
        $attachment->setRubric($rubric);
        $form = $this->createForm(ProblemAttachmentType::class, $attachment);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $customType = $form->get('type_custom')->getData();
            if ($customType) {
                $attachment->setType($customType);
            }
            /** @var UploadedFile $file */
            $file = $form->get('content')->getData();
            if ($file) {
                $content = new ProblemAttachmentContent();
                $content->setContent(file_get_contents($file->getRealPath()));
                $content->setAttachment($attachment);
                $attachment->setContent($content);
                $attachment->setMimeType($file->getMimeType());
            }

            // Defensive validation - ensure a type is provided
            if (!$attachment->getType() || $attachment->getType() === '') {
                $form->get('type')->addError(new FormError('Please select a type or enter a custom type.'));
            }

            if (!$attachment->getMimeType()) {
                $attachment->setMimeType('application/octet-stream');
            }

            if ($form->isValid()) {
                try {
                    $this->em->persist($attachment);
                    $this->em->flush();
                } catch (\Doctrine\DBAL\Exception\DriverException $e) {
                    $this->addFlash('danger', 'Database error while saving attachment: ' . $e->getMessage() . '. Try running doctrine migrations: "bin/console doctrine:migrations:migrate"');
                    return $this->render('extensions_plugin/rubric_attachments.html.twig', [
                        'form' => $form->createView(),
                        'problem' => $problem,
                        'rubric' => $rubric,
                        'attachments' => $attachments,
                        'contest' => $contest,
                    ]);
                }
                $this->addFlash('success', 'Attachment added to rubric.');
                return $this->redirectToRoute('jury_rubric_attachments', ['problemId' => $problemId, 'rubricId' => $rubricId]);
            }
        }

        $attachments = $this->em->getRepository(ProblemAttachment::class)->findBy(['problem' => $problem, 'rubric' => $rubric]);

        // Determine a contest context if this problem is part of any contest via ContestProblem
        $contest = null;
        try {
            if (method_exists($problem, 'getContestProblems')) {
                $cps = $problem->getContestProblems();
                if ($cps && count($cps) > 0) {
                    $first = $cps->first();
                    if ($first && method_exists($first, 'getContest')) {
                        $contest = $first->getContest();
                    }
                }
            }
        } catch (\Throwable $e) {
            // fall back to null if anything goes wrong
            $contest = null;
        }

        return $this->render('extensions_plugin/rubric_attachments.html.twig', [
            'problem' => $problem,
            'rubric' => $rubric,
            'attachments' => $attachments,
            'attachmentForm' => $form->createView(),
            'contest' => $contest,
        ]);
    }

    #[Route(path: '/problem/{problemId}/rubric/{rubricId}/attachments/{attachmentId}/delete', name: 'jury_rubric_attachment_delete', methods: ['POST'])]
    public function deleteRubricAttachmentAction(int $problemId, int $rubricId, int $attachmentId): Response
    {
        $attachment = $this->em->getRepository(ProblemAttachment::class)->find($attachmentId);
        if (!$attachment) {
            throw $this->createNotFoundException('Attachment not found');
        }
        // remove and flush
        $this->em->remove($attachment);
        $this->em->flush();
        $this->addFlash('success', 'Attachment deleted.');
        return $this->redirectToRoute('jury_rubric_attachments', ['problemId' => $problemId, 'rubricId' => $rubricId]);
    }
}
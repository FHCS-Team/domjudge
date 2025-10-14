<?php declare(strict_types=1);

namespace App\Controller\API;

use App\Entity\Contest;
use App\Entity\Submission;
use App\Entity\SubmissionDeliverable;
use App\Entity\SubmissionRubricScore;
use App\Entity\Rubric;
use App\Service\DOMJudgeService;
use Doctrine\ORM\EntityManagerInterface;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Rest\Route('/api/v1')]
#[IsGranted('ROLE_JURY')]
class HackathonJudgingController extends AbstractFOSRestController
{
    public function __construct(
        protected readonly EntityManagerInterface $em,
        protected readonly DOMJudgeService $dj
    ) {}

    #[Rest\Get('/hackathon/submissions/queue')]
    #[OA\Response(
        response: 200,
        description: 'Returns submissions pending manual judging for hackathon contests',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(
                properties: [
                    new OA\Property('submission_id', type: 'integer'),
                    new OA\Property('problem_id', type: 'integer'),
                    new OA\Property('problem_name', type: 'string'),
                    new OA\Property('team_id', type: 'integer'),
                    new OA\Property('team_name', type: 'string'),
                    new OA\Property('contest_id', type: 'integer'),
                    new OA\Property('contest_name', type: 'string'),
                    new OA\Property('submit_time', type: 'string', format: 'date-time'),
                    new OA\Property('language', type: 'string'),
                    new OA\Property('deliverable_types', type: 'array', items: new OA\Items(type: 'string')),
                    new OA\Property(
                        'deliverables',
                        type: 'array',
                        items: new OA\Items(
                            properties: [
                                new OA\Property('type', type: 'string'),
                                new OA\Property('file_type', type: 'string'),
                                new OA\Property('url', type: 'string'),
                            ]
                        )
                    ),
                    new OA\Property(
                        'rubrics',
                        type: 'array',
                        items: new OA\Items(
                            properties: [
                                new OA\Property('rubric_id', type: 'integer'),
                                new OA\Property('name', type: 'string'),
                                new OA\Property('type', type: 'string'),
                                new OA\Property('weight', type: 'number'),
                                new OA\Property('threshold', type: 'number', nullable: true),
                                new OA\Property('description', type: 'string', nullable: true),
                                new OA\Property('judged', type: 'boolean'),
                                new OA\Property('score', type: 'number', nullable: true),
                            ]
                        )
                    ),
                    new OA\Property('automated_result', type: 'string', nullable: true),
                    new OA\Property('total_rubrics', type: 'integer'),
                    new OA\Property('judged_rubrics', type: 'integer'),
                ]
            )
        )
    )]
    #[OA\Parameter(
        name: 'contest_id',
        description: 'Filter by contest ID',
        in: 'query',
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'problem_id', 
        description: 'Filter by problem ID',
        in: 'query',
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'pending_only',
        description: 'Show only submissions with pending manual judgments',
        in: 'query',
        schema: new OA\Schema(type: 'boolean', default: true)
    )]
    public function getHackathonSubmissionsQueue(Request $request): JsonResponse
    {
        $contestId = $request->query->get('contest_id');
        $problemId = $request->query->get('problem_id');
        $pendingOnly = $request->query->getBoolean('pending_only', true);

        // Build the query to get submissions from hackathon-enabled contests
        $qb = $this->em->createQueryBuilder()
            ->select('s', 'c', 't', 'p', 'l')
            ->from(Submission::class, 's')
            ->join('s.contest', 'c')
            ->join('s.team', 't')
            ->join('s.problem', 'p')
            ->join('s.language', 'l')
            ->where('c.hackathonEnabled = 1')
            ->andWhere('s.valid = 1')
            ->orderBy('s.submittime', 'DESC');

        if ($contestId) {
            $qb->andWhere('c.cid = :contest_id')
               ->setParameter('contest_id', $contestId);
        }

        if ($problemId) {
            $qb->andWhere('p.probid = :problem_id')
               ->setParameter('problem_id', $problemId);
        }

        $submissions = $qb->getQuery()->getResult();

        $result = [];
        foreach ($submissions as $submission) {
            $submissionData = $this->buildSubmissionData($submission, $pendingOnly);
            
            // If pending_only is true, only include submissions with incomplete judging
            if ($pendingOnly && $submissionData['judged_rubrics'] >= $submissionData['total_rubrics']) {
                continue;
            }
            
            $result[] = $submissionData;
        }

        return new JsonResponse($result);
    }

    #[Rest\Get('/hackathon/submissions/{submitId<\d+>}')]
    #[OA\Response(
        response: 200,
        description: 'Get detailed information about a specific hackathon submission',
        content: new OA\JsonContent(
            properties: [
                new OA\Property('submission_id', type: 'integer'),
                new OA\Property('problem_id', type: 'integer'),
                new OA\Property('problem_name', type: 'string'),
                new OA\Property('team_id', type: 'integer'),
                new OA\Property('team_name', type: 'string'),
                new OA\Property('contest_id', type: 'integer'),
                new OA\Property('contest_name', type: 'string'),
                new OA\Property('submit_time', type: 'string', format: 'date-time'),
                new OA\Property('language', type: 'string'),
                new OA\Property('source_files', type: 'array', items: new OA\Items(
                    properties: [
                        new OA\Property('filename', type: 'string'),
                        new OA\Property('content', type: 'string'),
                    ]
                )),
                new OA\Property('deliverable_types', type: 'array', items: new OA\Items(type: 'string')),
                new OA\Property(
                    'deliverables',
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property('type', type: 'string'),
                            new OA\Property('file_type', type: 'string'),
                            new OA\Property('url', type: 'string'),
                        ]
                    )
                ),
                new OA\Property(
                    'rubrics',
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property('rubric_id', type: 'integer'),
                            new OA\Property('name', type: 'string'),
                            new OA\Property('type', type: 'string'),
                            new OA\Property('weight', type: 'number'),
                            new OA\Property('threshold', type: 'number', nullable: true),
                            new OA\Property('description', type: 'string', nullable: true),
                            new OA\Property('judged', type: 'boolean'),
                            new OA\Property('score', type: 'number', nullable: true),
                        ]
                    )
                ),
            ]
        )
    )]
    public function getHackathonSubmission(int $submitId): JsonResponse
    {
        $submission = $this->em->getRepository(Submission::class)->find($submitId);
        
        if (!$submission || !$submission->getContest()->getHackathonEnabled()) {
            return new JsonResponse(['error' => 'Submission not found or not a hackathon submission'], 404);
        }

        $data = $this->buildSubmissionData($submission, false);
        
        // Add source code files for detailed view
        $sourceFiles = [];
        foreach ($submission->getFiles() as $file) {
            $sourceFiles[] = [
                'filename' => $file->getFilename(),
                'content' => $file->getSourcecode(),
            ];
        }
        $data['source_files'] = $sourceFiles;

        return new JsonResponse($data);
    }

    #[Rest\Post('/hackathon/submissions/{submitId<\d+>}/scores')]
    #[OA\Post(
        summary: 'Submit rubric scores for a hackathon submission',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property('judge_name', type: 'string', description: 'Name of the judge'),
                    new OA\Property(
                        'scores',
                        type: 'array',
                        items: new OA\Items(
                            properties: [
                                new OA\Property('rubric_id', type: 'integer'),
                                new OA\Property('score', type: 'number', minimum: 0, maximum: 1),
                                new OA\Property('comments', type: 'string', nullable: true),
                            ]
                        )
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Scores submitted successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property('success', type: 'boolean'),
                        new OA\Property('message', type: 'string'),
                        new OA\Property('scores_updated', type: 'integer'),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Submission not found'),
            new OA\Response(response: 400, description: 'Invalid input'),
        ]
    )]
    public function submitRubricScores(int $submitId, Request $request): JsonResponse
    {
        $submission = $this->em->getRepository(Submission::class)->find($submitId);
        if (!$submission || !$submission->getContest()->getHackathonEnabled()) {
            return new JsonResponse(['error' => 'Submission not found or not a hackathon submission'], 404);
        }

        $data = json_decode($request->getContent(), true);
        if (!$data || !isset($data['judge_name']) || !isset($data['scores'])) {
            return new JsonResponse(['error' => 'Invalid request data'], 400);
        }

        $judgeName = $data['judge_name'];
        $scores = $data['scores'];
        $scoresUpdated = 0;

        foreach ($scores as $scoreData) {
            if (!isset($scoreData['rubric_id']) || !isset($scoreData['score'])) {
                continue;
            }

            $rubric = $this->em->getRepository(Rubric::class)->find($scoreData['rubric_id']);
            if (!$rubric || $rubric->getProblem()->getProbid() !== $submission->getProblem()->getProbid()) {
                continue; // Skip invalid rubrics
            }

            // Find existing score or create new one
            $rubricScore = $this->em->getRepository(SubmissionRubricScore::class)
                ->findOneBy([
                    'submission' => $submission,
                    'rubric' => $rubric
                ]);

            if (!$rubricScore) {
                $rubricScore = new SubmissionRubricScore();
                $rubricScore->setSubmission($submission);
                $rubricScore->setRubric($rubric);
                $this->em->persist($rubricScore);
            }

            $rubricScore->setScore((float)$scoreData['score']);
            $rubricScore->setJudgeName($judgeName);
            $rubricScore->setJudgedAt(new \DateTime());
            $rubricScore->setComments($scoreData['comments'] ?? null);

            $scoresUpdated++;
        }

        $this->em->flush();

        return new JsonResponse([
            'success' => true,
            'message' => "Updated {$scoresUpdated} rubric scores",
            'scores_updated' => $scoresUpdated,
        ]);
    }

    private function buildSubmissionData(Submission $submission, bool $pendingOnly): array
    {
        // Get deliverables for this submission
        $deliverables = $this->em->getRepository(SubmissionDeliverable::class)
            ->findBy(['submission' => $submission]);

        $deliverableData = [];
        $deliverableTypes = [];
        
        foreach ($deliverables as $deliverable) {
            $deliverableData[] = [
                'type' => $deliverable->getType(),
                'file_type' => $deliverable->getFileType(),
                'url' => $deliverable->getUrl(),
            ];
            
            $types = explode(',', $deliverable->getType());
            foreach ($types as $type) {
                if (!in_array(trim($type), $deliverableTypes)) {
                    $deliverableTypes[] = trim($type);
                }
            }
        }

        // Get rubrics for this problem
        $rubrics = $this->em->getRepository(Rubric::class)
            ->findBy(['problem' => $submission->getProblem()]);

        $rubricData = [];
        $totalRubrics = count($rubrics);
        $judgedRubrics = 0;

        foreach ($rubrics as $rubric) {
            // Check if a score exists for this submission+rubric combination
            $rubricScore = $this->em->getRepository(SubmissionRubricScore::class)
                ->findOneBy([
                    'submission' => $submission,
                    'rubric' => $rubric
                ]);
            
            $isJudged = $rubricScore !== null;
            $score = $isJudged ? $rubricScore->getScore() : null;
            $judgeName = $isJudged ? $rubricScore->getJudgeName() : null;
            $judgedAt = $isJudged ? $rubricScore->getJudgedAt() : null;
            $comments = $isJudged ? $rubricScore->getComments() : null;
            
            if ($isJudged) {
                $judgedRubrics++;
            }

            $rubricData[] = [
                'rubric_id' => $rubric->getRubricid(),
                'name' => $rubric->getName(),
                'type' => $rubric->getType(),
                'weight' => $rubric->getWeight(),
                'threshold' => $rubric->getThreshold(),
                'description' => $rubric->getDescription(),
                'judged' => $isJudged,
                'score' => $score,
                'judge_name' => $judgeName,
                'judged_at' => $judgedAt?->format('Y-m-d H:i:s'),
                'comments' => $comments,
            ];
        }

        // Get automated judging result if available
        $automatedResult = null;
        $latestJudging = $submission->getJudgings()->first();
        if ($latestJudging && $latestJudging->getResult()) {
            $automatedResult = $latestJudging->getResult();
        }

        return [
            'submission_id' => $submission->getSubmitid(),
            'problem_id' => $submission->getProblem()->getProbid(),
            'problem_name' => $submission->getProblem()->getName(),
            'team_id' => $submission->getTeam()->getTeamid(),
            'team_name' => $submission->getTeam()->getEffectiveName(),
            'contest_id' => $submission->getContest()->getCid(),
            'contest_name' => $submission->getContest()->getName(),
            'submit_time' => $submission->getSubmittime(),
            'language' => $submission->getLanguage()->getName(),
            'deliverable_types' => $deliverableTypes,
            'deliverables' => $deliverableData,
            'rubrics' => $rubricData,
            'automated_result' => $automatedResult,
            'total_rubrics' => $totalRubrics,
            'judged_rubrics' => $judgedRubrics,
        ];
    }
}
<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\Problem;
use App\Entity\Submission;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;

/**
 * Service for communicating with the custom judgehost for non-traditional problems.
 * Handles problem registration, submission forwarding, and result retrieval.
 */
class CustomJudgehostService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ConfigurationService $config,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * Check if custom judgehost integration is enabled.
     */
    public function isEnabled(): bool
    {
        return (bool)$this->config->get('custom_judgehost_enabled');
    }

    /**
     * Get the custom judgehost base URL.
     */
    private function getBaseUrl(): string
    {
        $url = (string)$this->config->get('custom_judgehost_url');
        return rtrim($url, '/');
    }

    /**
     * Get the API key for authentication.
     */
    private function getApiKey(): string
    {
        return (string)$this->config->get('custom_judgehost_api_key');
    }

    /**
     * Get the timeout in seconds for requests.
     */
    private function getTimeout(): int
    {
        return (int)$this->config->get('custom_judgehost_timeout');
    }

    /**
     * Register a problem with the custom judgehost.
     * Sends the problem package and configuration to the custom judgehost.
     *
     * @param Problem $problem The problem entity
     * @param UploadedFile $packageFile The problem package tarball
     * @param array $customConfig The custom problem configuration from config.json
     * @return array Response from custom judgehost
     * @throws \RuntimeException on communication errors
     */
    public function registerProblem(Problem $problem, UploadedFile $packageFile, array $customConfig): array
    {
        if (!$this->isEnabled()) {
            throw new \RuntimeException('Custom judgehost integration is not enabled');
        }

        $url = $this->getBaseUrl() . '/problems';
        $problemId = $problem->getExternalid() ?? 'problem_' . $problem->getProbid();
        $problemName = $problem->getName();
        $projectType = $customConfig['project_type'] ?? 'unknown';

        $this->logger->info('Registering problem with custom judgehost', [
            'problem_id' => $problemId,
            'problem_name' => $problemName,
            'project_type' => $projectType,
            'url' => $url,
        ]);

        try {
            $response = $this->httpClient->request('POST', $url, [
                'timeout' => $this->getTimeout(),
                'headers' => [
                    'X-API-Key' => $this->getApiKey(),
                ],
                'body' => [
                    'problem_id' => $problemId,
                    'problem_name' => $problemName,
                    'package_type' => 'file',
                    'project_type' => $projectType,
                    'problem_package' => fopen($packageFile->getRealPath(), 'r'),
                ],
            ]);

            $statusCode = $response->getStatusCode();
            $content = $response->toArray();

            if ($statusCode !== 200) {
                $this->logger->error('Custom judgehost rejected problem registration', [
                    'problem_id' => $problemId,
                    'status_code' => $statusCode,
                    'response' => $content,
                ]);
                throw new \RuntimeException('Custom judgehost returned status ' . $statusCode);
            }

            $this->logger->info('Problem registered successfully with custom judgehost', [
                'problem_id' => $problemId,
                'response' => $content,
            ]);

            return $content;

        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Transport error communicating with custom judgehost', [
                'problem_id' => $problemId,
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException('Failed to communicate with custom judgehost: ' . $e->getMessage(), 0, $e);
        } catch (ClientExceptionInterface | ServerExceptionInterface $e) {
            $this->logger->error('HTTP error from custom judgehost', [
                'problem_id' => $problemId,
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException('Custom judgehost returned an error: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Submit a solution to the custom judgehost for evaluation.
     *
     * @param Submission $submission The submission entity
     * @param Problem $problem The problem entity
     * @param array $files Array of submission files (name => content)
     * @return array Response from custom judgehost with submission_id and status
     * @throws \RuntimeException on communication errors
     */
    public function submitForEvaluation(Submission $submission, Problem $problem, array $files): array
    {
        if (!$this->isEnabled()) {
            throw new \RuntimeException('Custom judgehost integration is not enabled');
        }

        $url = $this->getBaseUrl() . '/submissions';
        $problemId = $problem->getExternalid() ?? 'problem_' . $problem->getProbid();

        // Create a temporary tarball with submission files
        $tempDir = sys_get_temp_dir() . '/domjudge_submission_' . uniqid();
        mkdir($tempDir, 0755, true);

        try {
            // Write files to temp directory
            foreach ($files as $filename => $content) {
                file_put_contents($tempDir . '/' . $filename, $content);
            }

            // Create tarball
            $tarballPath = $tempDir . '.tar.gz';
            $tar = new \PharData($tarballPath);
            $tar->buildFromDirectory($tempDir);

            $this->logger->info('Submitting to custom judgehost', [
                'submission_id' => $submission->getSubmitid(),
                'problem_id' => $problemId,
                'url' => $url,
                'file_count' => count($files),
            ]);

            $response = $this->httpClient->request('POST', $url, [
                'timeout' => $this->getTimeout(),
                'headers' => [
                    'X-API-Key' => $this->getApiKey(),
                ],
                'body' => [
                    'problem_id' => $problemId,
                    'package_type' => 'file',
                    'submission_file' => fopen($tarballPath, 'r'),
                ],
            ]);

            $statusCode = $response->getStatusCode();
            $content = $response->toArray();

            if ($statusCode !== 202 && $statusCode !== 200) {
                $this->logger->error('Custom judgehost rejected submission', [
                    'submission_id' => $submission->getSubmitid(),
                    'problem_id' => $problemId,
                    'status_code' => $statusCode,
                    'response' => $content,
                ]);
                throw new \RuntimeException('Custom judgehost returned status ' . $statusCode);
            }

            $this->logger->info('Submission accepted by custom judgehost', [
                'submission_id' => $submission->getSubmitid(),
                'custom_submission_id' => $content['data']['submission_id'] ?? null,
                'response' => $content,
            ]);

            return $content;

        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Transport error submitting to custom judgehost', [
                'submission_id' => $submission->getSubmitid(),
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException('Failed to communicate with custom judgehost: ' . $e->getMessage(), 0, $e);
        } catch (ClientExceptionInterface | ServerExceptionInterface $e) {
            $this->logger->error('HTTP error from custom judgehost', [
                'submission_id' => $submission->getSubmitid(),
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException('Custom judgehost returned an error: ' . $e->getMessage(), 0, $e);
        } finally {
            // Cleanup temporary files
            if (isset($tarballPath) && file_exists($tarballPath)) {
                unlink($tarballPath);
            }
            if (is_dir($tempDir)) {
                array_map('unlink', glob($tempDir . '/*'));
                rmdir($tempDir);
            }
        }
    }

    /**
     * Get the evaluation results for a submission from the custom judgehost.
     *
     * @param string $customSubmissionId The submission ID from custom judgehost
     * @return array|null Results array or null if not yet available
     */
    public function getResults(string $customSubmissionId): ?array
    {
        if (!$this->isEnabled()) {
            throw new \RuntimeException('Custom judgehost integration is not enabled');
        }

        $url = $this->getBaseUrl() . '/api/results/' . $customSubmissionId;

        try {
            $response = $this->httpClient->request('GET', $url, [
                'timeout' => $this->getTimeout(),
                'headers' => [
                    'X-API-Key' => $this->getApiKey(),
                ],
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode === 404) {
                // Submission not found or not yet processed
                return null;
            }

            if ($statusCode !== 200) {
                $this->logger->warning('Custom judgehost returned unexpected status for results', [
                    'custom_submission_id' => $customSubmissionId,
                    'status_code' => $statusCode,
                ]);
                return null;
            }

            $content = $response->toArray();

            if ($content['success'] ?? false) {
                return $content['data'] ?? null;
            }

            return null;

        } catch (TransportExceptionInterface | ClientExceptionInterface | ServerExceptionInterface $e) {
            $this->logger->error('Error fetching results from custom judgehost', [
                'custom_submission_id' => $customSubmissionId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Fetch logs from a custom judgehost URL.
     *
     * @param string $logsUrl The URL to fetch logs from
     * @return string The logs content
     */
    public function fetchLogs(string $logsUrl): string
    {
        try {
            $response = $this->httpClient->request('GET', $logsUrl, [
                'timeout' => $this->getTimeout(),
                'headers' => [
                    'X-API-Key' => $this->getApiKey(),
                ],
            ]);

            return $response->getContent();

        } catch (\Exception $e) {
            $this->logger->error('Error fetching logs from custom judgehost', [
                'url' => $logsUrl,
                'error' => $e->getMessage(),
            ]);
            return 'Error fetching logs: ' . $e->getMessage();
        }
    }

    /**
     * Fetch an artifact from a custom judgehost URL.
     *
     * @param string $artifactUrl The URL to fetch artifact from
     * @return string The artifact content
     */
    public function fetchArtifact(string $artifactUrl): string
    {
        try {
            $response = $this->httpClient->request('GET', $artifactUrl, [
                'timeout' => $this->getTimeout(),
                'headers' => [
                    'X-API-Key' => $this->getApiKey(),
                ],
            ]);

            return $response->getContent();

        } catch (\Exception $e) {
            $this->logger->error('Error fetching artifact from custom judgehost', [
                'url' => $artifactUrl,
                'error' => $e->getMessage(),
            ]);
            return 'Error fetching artifact: ' . $e->getMessage();
        }
    }
}

<?php declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Problem;
use App\Entity\Submission;
use App\Service\ConfigurationService;
use App\Service\CustomJudgehostService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class CustomJudgehostServiceTest extends TestCase
{
    private HttpClientInterface&MockObject $httpClient;
    private ConfigurationService&MockObject $config;
    private LoggerInterface&MockObject $logger;
    private CustomJudgehostService $service;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->config = $this->createMock(ConfigurationService::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->service = new CustomJudgehostService(
            $this->httpClient,
            $this->config,
            $this->logger
        );
    }

    public function testIsEnabledReturnsTrueWhenConfigured(): void
    {
        $this->config->expects($this->once())
            ->method('get')
            ->with('custom_judgehost_enabled')
            ->willReturn('1');

        $this->assertTrue($this->service->isEnabled());
    }

    public function testIsEnabledReturnsFalseWhenDisabled(): void
    {
        $this->config->expects($this->once())
            ->method('get')
            ->with('custom_judgehost_enabled')
            ->willReturn('0');

        $this->assertFalse($this->service->isEnabled());
    }

    public function testRegisterProblemThrowsExceptionWhenDisabled(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Custom judgehost integration is not enabled');

        $this->config->expects($this->once())
            ->method('get')
            ->with('custom_judgehost_enabled')
            ->willReturn('0');

        $problem = $this->createMock(Problem::class);
        $uploadedFile = $this->createMock(UploadedFile::class);

        $this->service->registerProblem($problem, $uploadedFile, ['project_type' => 'test']);
    }

    public function testRegisterProblemSuccessfully(): void
    {
        // Mock configuration to enable custom judgehost
        $configMap = [
            'custom_judgehost_enabled' => '1',
            'custom_judgehost_url' => 'http://localhost:8000',
            'custom_judgehost_api_key' => 'test-api-key',
            'custom_judgehost_timeout' => 300,
        ];
        $this->config->method('get')->willReturnCallback(fn($key) => $configMap[$key] ?? null);

        // Mock problem entity
        $problem = $this->createMock(Problem::class);
        $problem->expects($this->once())
            ->method('getName')
            ->willReturn('Test Problem');
        $problem->expects($this->once())
            ->method('getProbid')
            ->willReturn(1);

        // Mock uploaded file
        $uploadedFile = $this->createMock(UploadedFile::class);
        $uploadedFile->expects($this->once())
            ->method('getRealPath')
            ->willReturn('/tmp/test.zip');

        // Mock HTTP response
        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(200);
        $response->expects($this->once())
            ->method('toArray')
            ->willReturn([
                'success' => true,
                'data' => [
                    'problem_id' => 'test-problem-1',
                    'images' => ['base', 'evaluator'],
                ],
            ]);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'http://localhost:8000/problems',
                $this->callback(function ($options) {
                    return $options['timeout'] === 300
                        && isset($options['headers'])
                        && isset($options['body']);
                })
            )
            ->willReturn($response);

        $this->logger->expects($this->atLeastOnce())
            ->method('info');

        $customConfig = ['project_type' => 'database-optimization'];
        $result = $this->service->registerProblem($problem, $uploadedFile, $customConfig);

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertEquals('test-problem-1', $result['data']['problem_id']);
    }

    public function testSubmitForEvaluationThrowsExceptionWhenDisabled(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Custom judgehost integration is not enabled');

        $this->config->expects($this->once())
            ->method('get')
            ->with('custom_judgehost_enabled')
            ->willReturn('0');

        $submission = $this->createMock(Submission::class);
        $problem = $this->createMock(Problem::class);

        $this->service->submitForEvaluation($submission, $problem, ['file.cpp' => 'code']);
    }

    public function testSubmitForEvaluationSuccessfully(): void
    {
        // Mock configuration
        $configMap = [
            'custom_judgehost_enabled' => '1',
            'custom_judgehost_url' => 'http://localhost:8000',
            'custom_judgehost_api_key' => 'test-api-key',
            'custom_judgehost_timeout' => 300,
        ];
        $this->config->method('get')->willReturnCallback(fn($key) => $configMap[$key] ?? null);

        // Mock problem
        $problem = $this->createMock(Problem::class);
        $problem->expects($this->once())
            ->method('getExternalid')
            ->willReturn('test-problem-1');
        // getProbid() won't be called since getExternalid() returns a value

        // Mock submission
        $submission = $this->createMock(Submission::class);
        $submission->expects($this->atLeastOnce())
            ->method('getSubmitid')
            ->willReturn(123);

        // Mock HTTP response
        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(202);
        $response->expects($this->once())
            ->method('toArray')
            ->willReturn([
                'success' => true,
                'data' => [
                    'submission_id' => 'sub_abc123',
                    'status' => 'queued',
                ],
            ]);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with('POST', 'http://localhost:8000/submissions')
            ->willReturn($response);

        $this->logger->expects($this->atLeastOnce())
            ->method('info');

        $files = ['solution.cpp' => '#include <iostream>'];
        $result = $this->service->submitForEvaluation($submission, $problem, $files);

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertEquals('sub_abc123', $result['data']['submission_id']);
    }    public function testGetResultsReturnsNullWhen404(): void
    {
        // Mock configuration
        $configMap = [
            'custom_judgehost_enabled' => '1',
            'custom_judgehost_url' => 'http://localhost:8000',
            'custom_judgehost_api_key' => 'test-api-key',
            'custom_judgehost_timeout' => 300,
        ];
        $this->config->method('get')->willReturnCallback(fn($key) => $configMap[$key] ?? null);

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(404);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with('GET', 'http://localhost:8000/api/results/sub_123')
            ->willReturn($response);

        $result = $this->service->getResults('sub_123');

        $this->assertNull($result);
    }

    public function testGetResultsReturnsDataWhenSuccessful(): void
    {
        // Mock configuration
        $configMap = [
            'custom_judgehost_enabled' => '1',
            'custom_judgehost_url' => 'http://localhost:8000',
            'custom_judgehost_api_key' => 'test-api-key',
            'custom_judgehost_timeout' => 300,
        ];
        $this->config->method('get')->willReturnCallback(fn($key) => $configMap[$key] ?? null);

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(200);
        $response->expects($this->once())
            ->method('toArray')
            ->willReturn([
                'success' => true,
                'data' => [
                    'status' => 'completed',
                    'overall_score' => 0.85,
                    'rubrics' => [],
                ],
            ]);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with('GET', 'http://localhost:8000/api/results/sub_123')
            ->willReturn($response);

        $result = $this->service->getResults('sub_123');

        $this->assertIsArray($result);
        $this->assertEquals('completed', $result['status']);
        $this->assertEquals(0.85, $result['overall_score']);
    }

    public function testFetchLogsSuccessfully(): void
    {
        $this->config->expects($this->exactly(2))
            ->method('get')
            ->willReturnMap([
                ['custom_judgehost_timeout', 300],
                ['custom_judgehost_api_key', 'test-api-key'],
            ]);

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('getContent')
            ->willReturn('Log line 1\nLog line 2\n');

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with('GET', 'http://example.com/logs/123')
            ->willReturn($response);

        $logs = $this->service->fetchLogs('http://example.com/logs/123');

        $this->assertEquals('Log line 1\nLog line 2\n', $logs);
    }

    public function testFetchArtifactSuccessfully(): void
    {
        $this->config->expects($this->exactly(2))
            ->method('get')
            ->willReturnMap([
                ['custom_judgehost_timeout', 300],
                ['custom_judgehost_api_key', 'test-api-key'],
            ]);

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('getContent')
            ->willReturn('artifact data');

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with('GET', 'http://example.com/artifacts/report.json')
            ->willReturn($response);

        $artifact = $this->service->fetchArtifact('http://example.com/artifacts/report.json');

        $this->assertEquals('artifact data', $artifact);
    }
}

# Custom Judgehost Integration - API Documentation

## Overview

This document describes the API contracts between DOMjudge and the custom judgehost system for evaluating non-traditional programming problems.

---

## Architecture

```
┌─────────────┐         ┌──────────────────┐         ┌─────────────────┐
│             │         │                  │         │                 │
│  DOMjudge   │◄────────┤ Custom Judgehost │────────►│   Docker        │
│  Server     │         │   Orchestrator   │         │   Containers    │
│             │         │                  │         │                 │
└─────────────┘         └──────────────────┘         └─────────────────┘
       ▲                                                      │
       │                                                      │
       │                                                      ▼
       │                                              ┌───────────────┐
       │                                              │  Evaluation   │
       └──────────────────────────────────────────────┤   Hooks       │
                     Results Callback                 │  (Python/JS)  │
                                                      └───────────────┘
```

### Communication Flow

1. **Problem Registration** (DOMjudge → Custom Judgehost)

    - DOMjudge POSTs problem package
    - Custom judgehost builds Docker images
    - Returns image names and status

2. **Submission Evaluation** (DOMjudge → Custom Judgehost)

    - DOMjudge POSTs submission files
    - Custom judgehost queues evaluation job
    - Returns submission ID

3. **Result Reporting** (Custom Judgehost → DOMjudge)
    - Custom judgehost evaluates submission
    - POSTs results with rubric scores
    - DOMjudge updates database and UI

---

## API Endpoints

### 1. Register Problem

**Endpoint:** `POST /problems`  
**Direction:** DOMjudge → Custom Judgehost  
**Authentication:** API Key via `X-API-Key` header

#### Request

**Content-Type:** `multipart/form-data`

**Fields:**

| Field             | Type   | Required | Description                                                   |
| ----------------- | ------ | -------- | ------------------------------------------------------------- |
| `problem_id`      | string | Yes      | Unique identifier for the problem (DOMjudge external ID)      |
| `problem_name`    | string | Yes      | Human-readable problem name                                   |
| `package_type`    | string | Yes      | Always "file" for file uploads                                |
| `project_type`    | string | Yes      | Type of project (e.g., "database-optimization", "nodejs-api") |
| `problem_package` | file   | Yes      | Tarball (.tar.gz) containing problem files                    |

**Example:**

```bash
curl -X POST http://custom-judgehost:8000/problems \
  -H "X-API-Key: your-secret-api-key" \
  -F "problem_id=db-opt-1" \
  -F "problem_name=Database Query Optimizer" \
  -F "package_type=file" \
  -F "project_type=database-optimization" \
  -F "problem_package=@problem-package.tar.gz"
```

**Problem Package Structure:**

```
problem-package/
├── config.json              # Problem configuration
├── Dockerfile.base          # Base container definition
├── Dockerfile.evaluator     # Evaluator container definition
├── evaluation/
│   ├── setup.sh            # Setup script
│   ├── evaluate.py         # Evaluation logic
│   └── rubric.json         # Rubric definitions
├── testcases/
│   ├── test1.sql
│   └── test2.sql
└── README.md
```

**config.json Format:**

```json
{
    "project_type": "database-optimization",
    "version": "1.0",
    "containers": {
        "base": {
            "dockerfile": "Dockerfile.base",
            "build_args": {},
            "environment": {
                "DB_TYPE": "postgresql"
            }
        },
        "evaluator": {
            "dockerfile": "Dockerfile.evaluator",
            "depends_on": ["base"]
        }
    },
    "evaluation": {
        "timeout": 300,
        "memory_limit": "2G",
        "hooks": {
            "pre_evaluation": "evaluation/setup.sh",
            "evaluate": "evaluation/evaluate.py",
            "post_evaluation": "evaluation/cleanup.sh"
        }
    },
    "rubrics": [
        {
            "name": "Query Correctness",
            "weight": 3.0,
            "threshold": 0.7,
            "description": "Correctness of SQL queries"
        },
        {
            "name": "Query Performance",
            "weight": 2.0,
            "threshold": 0.6,
            "description": "Execution time of queries"
        },
        {
            "name": "Code Quality",
            "weight": 1.0,
            "threshold": 0.5,
            "description": "SQL code quality and style"
        }
    ]
}
```

#### Response

**Success (200 OK):**

```json
{
    "success": true,
    "message": "Problem registered successfully",
    "data": {
        "problem_id": "db-opt-1",
        "images": ["problem-db-opt-1-base", "problem-db-opt-1-evaluator"],
        "build_time": 45.2,
        "containers_ready": true
    }
}
```

**Error (400 Bad Request):**

```json
{
    "success": false,
    "error": "Invalid problem package: missing config.json",
    "details": {
        "field": "problem_package",
        "message": "config.json not found in tarball root"
    }
}
```

**Error (500 Internal Server Error):**

```json
{
    "success": false,
    "error": "Failed to build Docker image",
    "details": {
        "container": "base",
        "docker_error": "Build failed at line 10: RUN apt-get update"
    }
}
```

---

### 2. Submit for Evaluation

**Endpoint:** `POST /submissions`  
**Direction:** DOMjudge → Custom Judgehost  
**Authentication:** API Key via `X-API-Key` header

#### Request

**Content-Type:** `multipart/form-data`

**Fields:**

| Field             | Type   | Required | Description                                   |
| ----------------- | ------ | -------- | --------------------------------------------- |
| `problem_id`      | string | Yes      | Problem identifier (must be registered)       |
| `package_type`    | string | Yes      | Always "file" for file uploads                |
| `submission_file` | file   | Yes      | Tarball (.tar.gz) containing submission files |

**Example:**

```bash
curl -X POST http://custom-judgehost:8000/submissions \
  -H "X-API-Key: your-secret-api-key" \
  -F "problem_id=db-opt-1" \
  -F "package_type=file" \
  -F "submission_file=@submission.tar.gz"
```

**Submission Package Structure:**

```
submission/
├── solution.sql         # Main submission file(s)
├── config.sql          # Optional configuration
└── README.md           # Optional documentation
```

#### Response

**Success (202 Accepted):**

```json
{
    "success": true,
    "message": "Submission queued for evaluation",
    "data": {
        "submission_id": "sub_abc123xyz789",
        "problem_id": "db-opt-1",
        "status": "queued",
        "queued_at": "2025-10-15T10:30:45.123Z",
        "estimated_duration": 120
    }
}
```

**Error (404 Not Found):**

```json
{
    "success": false,
    "error": "Problem not found",
    "details": {
        "problem_id": "db-opt-1",
        "message": "Problem must be registered before submission"
    }
}
```

**Error (503 Service Unavailable):**

```json
{
    "success": false,
    "error": "Evaluation queue full",
    "details": {
        "queue_size": 100,
        "message": "Please retry in 60 seconds"
    }
}
```

---

### 3. Get Results (Optional Polling)

**Endpoint:** `GET /api/results/{submission_id}`  
**Direction:** DOMjudge → Custom Judgehost  
**Authentication:** API Key via `X-API-Key` header

#### Request

**Example:**

```bash
curl -X GET http://custom-judgehost:8000/api/results/sub_abc123xyz789 \
  -H "X-API-Key: your-secret-api-key"
```

#### Response

**Success (200 OK) - Evaluation Complete:**

```json
{
    "success": true,
    "data": {
        "submission_id": "sub_abc123xyz789",
        "problem_id": "db-opt-1",
        "status": "completed",
        "overall_score": 0.855,
        "execution_time": 45.2,
        "rubrics": [
            {
                "name": "Query Correctness",
                "score": 0.9,
                "weight": 3.0,
                "feedback": "All test queries returned correct results. Excellent use of indexing."
            },
            {
                "name": "Query Performance",
                "score": 0.85,
                "weight": 2.0,
                "feedback": "Queries executed efficiently. Average execution time: 0.05s"
            },
            {
                "name": "Code Quality",
                "score": 0.8,
                "weight": 1.0,
                "feedback": "Clean SQL code with proper formatting."
            }
        ],
        "logs_url": "http://custom-judgehost:8000/logs/sub_abc123xyz789",
        "artifacts_urls": [
            "http://custom-judgehost:8000/artifacts/sub_abc123xyz789/report.json",
            "http://custom-judgehost:8000/artifacts/sub_abc123xyz789/metrics.csv"
        ],
        "evaluated_at": "2025-10-15T10:32:15.456Z"
    }
}
```

**Success (200 OK) - Still Processing:**

```json
{
    "success": true,
    "data": {
        "submission_id": "sub_abc123xyz789",
        "status": "processing",
        "progress": 0.65,
        "message": "Evaluating testcase 13 of 20"
    }
}
```

**Success (200 OK) - Error During Evaluation:**

```json
{
    "success": true,
    "data": {
        "submission_id": "sub_abc123xyz789",
        "status": "error",
        "error_message": "Submission timed out after 300 seconds",
        "logs_url": "http://custom-judgehost:8000/logs/sub_abc123xyz789"
    }
}
```

**Not Found (404 Not Found):**

```json
{
    "success": false,
    "error": "Submission not found",
    "details": {
        "submission_id": "sub_invalid"
    }
}
```

---

### 4. Submit Results (Callback)

**Endpoint:** `POST /api/judgehosts/add-custom-judging-result`  
**Direction:** Custom Judgehost → DOMjudge  
**Authentication:** Judgehost credentials

#### Request

**Content-Type:** `application/json`

**Body:**

| Field            | Type   | Required | Description                                        |
| ---------------- | ------ | -------- | -------------------------------------------------- |
| `submission_id`  | string | Yes      | Custom judgehost submission ID                     |
| `status`         | string | Yes      | Evaluation status: "completed", "error", "timeout" |
| `overall_score`  | float  | Yes      | Overall score (0.0 to 1.0)                         |
| `rubrics`        | array  | No       | Array of rubric score objects                      |
| `execution_time` | float  | No       | Total execution time in seconds                    |
| `logs_url`       | string | No       | URL to fetch execution logs                        |
| `artifacts_urls` | array  | No       | Array of URLs for generated artifacts              |
| `error_message`  | string | No       | Error message if status is "error"                 |

**Rubric Object:**

| Field      | Type   | Required | Description                           |
| ---------- | ------ | -------- | ------------------------------------- |
| `name`     | string | Yes      | Rubric criterion name                 |
| `score`    | float  | Yes      | Score for this criterion (0.0 to 1.0) |
| `weight`   | float  | Yes      | Weight of this criterion              |
| `feedback` | string | No       | Detailed feedback text                |

**Example:**

```bash
curl -X POST http://domjudge:12345/api/judgehosts/add-custom-judging-result \
  -H "Content-Type: application/json" \
  -H "Authorization: Basic base64(username:password)" \
  -d '{
    "submission_id": "sub_abc123xyz789",
    "status": "completed",
    "overall_score": 0.855,
    "execution_time": 45.2,
    "rubrics": [
      {
        "name": "Query Correctness",
        "score": 0.9,
        "weight": 3.0,
        "feedback": "All test queries returned correct results."
      },
      {
        "name": "Query Performance",
        "score": 0.85,
        "weight": 2.0,
        "feedback": "Queries executed efficiently."
      },
      {
        "name": "Code Quality",
        "score": 0.8,
        "weight": 1.0,
        "feedback": "Clean SQL code."
      }
    ],
    "logs_url": "http://custom-judgehost:8000/logs/sub_abc123xyz789",
    "artifacts_urls": [
      "http://custom-judgehost:8000/artifacts/sub_abc123xyz789/report.json"
    ]
  }'
```

#### Response

**Success (200 OK):**

```json
{
    "success": true,
    "message": "Judging result processed successfully",
    "submission_id": 123,
    "verdict": "correct"
}
```

**Error (400 Bad Request):**

```json
{
    "error": "Field 'status' is mandatory"
}
```

**Error (404 Not Found):**

```json
{
    "error": "Submission with custom_judgehost_submission_id 'sub_invalid' not found"
}
```

---

## Status Mapping

Custom judgehost statuses are mapped to DOMjudge verdicts:

| Custom Status | Score Range | DOMjudge Verdict | Description                     |
| ------------- | ----------- | ---------------- | ------------------------------- |
| `completed`   | ≥ 0.5       | `correct`        | Submission passed               |
| `completed`   | < 0.5       | `wrong-answer`   | Submission failed               |
| `error`       | N/A         | `run-error`      | Runtime error during evaluation |
| `timeout`     | N/A         | `timelimit`      | Evaluation exceeded time limit  |

---

## Data Types

### Supported Project Types

| Project Type            | Description             | Example Problems         |
| ----------------------- | ----------------------- | ------------------------ |
| `database-optimization` | SQL query optimization  | Query performance tuning |
| `nodejs-api`            | Node.js API development | REST API design          |
| `react-frontend`        | React UI development    | Component implementation |
| `system-design`         | System architecture     | Scalability design       |
| `docker-compose`        | Multi-container apps    | Microservices setup      |

### Score Ranges

-   **0.0 - 0.4**: Poor/Failed
-   **0.4 - 0.6**: Needs Improvement
-   **0.6 - 0.8**: Good
-   **0.8 - 1.0**: Excellent

---

## Error Codes

| HTTP Code | Meaning             | Action                       |
| --------- | ------------------- | ---------------------------- |
| 200       | Success             | Continue                     |
| 202       | Accepted/Queued     | Wait for results             |
| 400       | Bad Request         | Fix request parameters       |
| 401       | Unauthorized        | Check API key                |
| 404       | Not Found           | Verify problem/submission ID |
| 500       | Server Error        | Retry or contact admin       |
| 503       | Service Unavailable | Retry with backoff           |

---

## Security

### API Key Authentication

**Header Format:**

```
X-API-Key: your-secret-api-key-here
```

**Best Practices:**

-   Use strong random keys (32+ characters)
-   Rotate keys periodically
-   Store securely (environment variables, secrets manager)
-   Use HTTPS in production

### IP Whitelisting

Configure firewall rules to allow only:

-   DOMjudge server IP → Custom Judgehost
-   Custom Judgehost IP → DOMjudge server

### Request Validation

All requests must include:

-   Valid API key
-   Proper Content-Type header
-   Required fields in correct format
-   Valid JSON (for JSON endpoints)

---

## Rate Limiting

**Recommended Limits:**

| Endpoint                     | Limit        | Window   |
| ---------------------------- | ------------ | -------- |
| `/problems`                  | 10 requests  | per hour |
| `/submissions`               | 100 requests | per hour |
| `/api/results/{id}`          | 600 requests | per hour |
| `/add-custom-judging-result` | 100 requests | per hour |

**Rate Limit Headers:**

```
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 95
X-RateLimit-Reset: 1634300000
```

---

## Webhook Configuration

### Setting Up Callbacks

1. Configure callback URL in DOMjudge:

    ```bash
    docker compose exec mariadb mariadb -u domjudge -pdomjudge domjudge -e "
    UPDATE configuration
    SET value='http://domjudge:12345/api/judgehosts/add-custom-judging-result'
    WHERE name='custom_judgehost_callback_url';
    "
    ```

2. Custom judgehost calls this URL when evaluation completes

3. Include authentication in callback request

---

## Example Implementations

### Python - Custom Judgehost Client

```python
import requests
import json

class DOMJudgeClient:
    def __init__(self, base_url, api_key):
        self.base_url = base_url
        self.api_key = api_key

    def submit_results(self, submission_id, status, score, rubrics):
        url = f"{self.base_url}/api/judgehosts/add-custom-judging-result"
        headers = {
            "Content-Type": "application/json",
            "X-API-Key": self.api_key
        }
        payload = {
            "submission_id": submission_id,
            "status": status,
            "overall_score": score,
            "rubrics": rubrics
        }

        response = requests.post(url, headers=headers, json=payload)
        response.raise_for_status()
        return response.json()

# Usage
client = DOMJudgeClient("http://domjudge:12345", "your-api-key")
result = client.submit_results(
    "sub_abc123",
    "completed",
    0.85,
    [{"name": "Correctness", "score": 0.9, "weight": 1.0}]
)
print(result)
```

### JavaScript - Problem Registration

```javascript
const FormData = require("form-data");
const fs = require("fs");
const axios = require("axios");

async function registerProblem(problemId, packagePath) {
    const form = new FormData();
    form.append("problem_id", problemId);
    form.append("problem_name", "My Problem");
    form.append("package_type", "file");
    form.append("project_type", "nodejs-api");
    form.append("problem_package", fs.createReadStream(packagePath));

    const response = await axios.post(
        "http://custom-judgehost:8000/problems",
        form,
        {
            headers: {
                ...form.getHeaders(),
                "X-API-Key": "your-api-key",
            },
        }
    );

    return response.data;
}

// Usage
registerProblem("api-design-1", "./problem.tar.gz")
    .then((result) => console.log("Registered:", result))
    .catch((error) => console.error("Error:", error));
```

---

## Troubleshooting

### Common Issues

**1. "Problem not found" error:**

-   Ensure problem is registered before submitting
-   Check problem_id matches exactly
-   Verify custom judgehost is running

**2. "Unauthorized" error:**

-   Verify API key is correct
-   Check header format: `X-API-Key: key`
-   Ensure key matches configuration

**3. Results not received:**

-   Check custom judgehost can reach DOMjudge
-   Verify callback URL is accessible
-   Check firewall rules
-   Review custom judgehost logs

**4. Timeout errors:**

-   Increase `custom_judgehost_timeout` in config
-   Optimize evaluation scripts
-   Check container resource limits

---

## Version History

| Version | Date       | Changes                   |
| ------- | ---------- | ------------------------- |
| 1.0     | 2025-10-15 | Initial API specification |

---

## Support

For API questions or issues:

-   Check logs: `docker compose logs domserver`
-   Review test examples in `TESTING_GUIDE.md`
-   Consult implementation: `CustomJudgehostService.php`

**API Specification Complete!** 📚

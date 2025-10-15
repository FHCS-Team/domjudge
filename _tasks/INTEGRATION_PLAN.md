# Custom Judgehost Integration Plan

## Overview

This document outlines the plan to integrate the custom judgehost (for evaluating database optimization, API design, and other non-traditional problems) with the DOMjudge system.

## Current DOMjudge Architecture

### 1. Problem Upload Entrypoints

**API Endpoints:**

-   `POST /api/contests/{cid}/problems` - Upload problem ZIP to contest (requires ROLE_ADMIN)
-   `POST /api/problems` - Upload problem without linking to contest (GeneralInfoController)
-   `POST /api/contests/{cid}/problems/add-data` - Import problems.yaml/problems.json

**UI Entrypoints:**

-   Jury UI: `/jury/import-export` with `ProblemUploadType` form
-   Problem edit page: `/jury/problems/{probId}/edit` with ZIP upload

**Processing:**

-   `ImportProblemService::importZippedProblem()` - Main import logic
-   `ImportProblemService::importProblemFromRequest()` - Request handler
-   Handles standard problem.yaml format
-   Creates Problem, ContestProblem, Testcase entities

### 2. Submission Upload Entrypoints

**API Endpoints:**

-   `POST /api/contests/{cid}/submissions` - Submit via API (requires ROLE_TEAM or ROLE_API_WRITER)
-   `PUT /api/contests/{cid}/submissions/{id}` - Update submission (for external systems)

**UI Entrypoints:**

-   Team UI: `/team/submit/{problem}` - Standard submission form
-   Team UI: `/team/submit/{problem}` - Hackathon submission form (with deliverables)
-   Jury UI: `/jury/submissions/{submitId}/edit` - Edit submission sources

**Processing:**

-   `SubmissionService::submitSolution()` - Main submission logic
-   Creates Submission, SubmissionFile entities
-   Triggers judging workflow
-   For hackathons: handles SubmissionDeliverable entities

### 3. Judging Result Endpoint

**Current Endpoint:**

-   `POST /api/judgehosts/add-judging-run/{hostname}/{judgeTaskId}`

**Current Fields:**

```php
- runresult: string (verdict)
- runtime: float
- output_run: base64 string
- output_diff: base64 string
- output_error: base64 string
- output_system: base64 string
- team_message: base64 string (optional)
- metadata: base64 string (optional)
- testcasedir: string (optional)
- compare_metadata: base64 string (optional)
```

**Purpose:**

-   Receives individual test case run results
-   Used by traditional judgehosts for standard programming problems
-   Processes one test case at a time

### 4. Existing Hackathon/Rubric Support

**Entities:**

-   `Rubric` - Defines scoring criteria
-   `SubmissionRubricScore` - Stores rubric-based scores
-   `SubmissionDeliverable` - Additional files (docs, repos, etc.)

**API:**

-   `POST /api/judgehosts/{hostname}/judgings` - Submit rubric scores (exists but different format)

---

## Custom Judgehost API Contracts

### 1. POST /problems to Custom Judgehost

**When Triggered:** When admin uploads a problem package

**Request Fields:**

```
Content-Type: multipart/form-data

Fields:
- problem_id: string (required) - Unique problem identifier
- problem_name: string (required) - Human-readable name
- package_type: string (required) - "file"
- problem_package: file (required) - Tarball (.tar.gz)
- project_type: string (required) - "database", "nodejs-api", etc.
```

**Custom Package Structure:**

```
problem-package/
├── config.json           # Problem configuration
├── README.md
├── database/            # Container 1
│   ├── Dockerfile
│   └── init-db.sql
└── submission/          # Container 2
    ├── Dockerfile
    ├── entrypoint.sh
    └── hooks/
        ├── pre/
        └── post/
```

**Response (200 OK):**

```json
{
    "success": true,
    "message": "Problem registered",
    "data": {
        "problem_id": "sql-optimization",
        "image_names": {
            "database": "judgehost-sql-optimization-database:latest",
            "submission": "judgehost-sql-optimization-submission:latest"
        },
        "registered_at": "2025-10-15T06:00:00.000Z"
    }
}
```

### 2. POST /submissions to Custom Judgehost

**When Triggered:** When student submits a solution

**Request Fields:**

```
Content-Type: multipart/form-data

Fields:
- problem_id: string (required) - The problem to evaluate
- package_type: string (required) - "file"
- submission_file: file (required) - Tarball (.tar.gz)
```

**Response (202 Accepted):**

```json
{
    "success": true,
    "message": "Submission queued for evaluation",
    "data": {
        "submission_id": "sub_1760508291503a6mfftdq",
        "problem_id": "sql-optimization",
        "status": "queued",
        "enqueued_at": "2025-10-15T06:04:51.000Z",
        "position": 1,
        "estimated_wait_time_seconds": 60
    }
}
```

### 3. Enhanced add-judging-run Endpoint

**Keep Existing:** `POST /api/judgehosts/add-judging-run/{hostname}/{submissionId}`

**Enhanced Request Body (JSON):**

```json
{
    "judge_task_id": null,
    "submission_id": "sub_1760508291503a6mfftdq",
    "problem_id": "sql-optimization",
    "status": "completed", // "completed", "failed", "error"
    "started_at": "2025-10-15T06:04:51.000Z",
    "completed_at": "2025-10-15T06:05:39.595Z",
    "execution_time_seconds": 48.595,
    "total_score": 66.16,
    "max_score": 100,
    "percentage": 66.16,
    "rubrics": [
        {
            "rubric_id": "correctness",
            "name": "Query Result Correctness",
            "rubric_type": "test_cases",
            "score": 50,
            "max_score": 50,
            "percentage": 100,
            "status": "DONE",
            "message": "Query correctness: 3/3 queries passed",
            "details": {
                /* ... */
            }
        }
    ],
    "logs_url": "http://judgehost1:3000/api/results/{submission_id}/logs",
    "artifacts_urls": {
        "metrics": "http://judgehost1:3000/api/results/{submission_id}/metrics"
    },
    "metadata": {
        "judgehost_version": "1.0.0",
        "judgehost_hostname": "judgehost-1",
        "problem_version": "1.0.0",
        "project_type": "database"
    }
}
```

---

## Integration Architecture

### Configuration

Add new configuration options to DOMjudge:

```php
// In database configuration table or config.h
'custom_judgehost_enabled' => bool (default: false)
'custom_judgehost_url' => string (e.g., 'http://custom-judgehost:3000')
'custom_judgehost_api_key' => string (for authentication)
'custom_judgehost_timeout' => int (default: 300 seconds)
'custom_problem_types' => array (e.g., ['database', 'nodejs-api', 'system-design'])
```

### Problem Package Detection Logic

```
When a problem is uploaded:
1. Check if it contains config.json with "project_type" field
2. If project_type is in 'custom_problem_types':
   a. Mark as custom problem (new Problem field: is_custom_problem)
   b. Forward to custom judgehost POST /problems
   c. Store custom judgehost response (image names, etc.)
3. Else:
   a. Process as traditional problem (existing flow)
```

### Submission Routing Logic

```
When a submission is created:
1. Check if problem.is_custom_problem == true
2. If custom:
   a. Create submission ZIP with user files
   b. Forward to custom judgehost POST /submissions
   c. Store custom submission_id mapping
   d. Poll for results or wait for callback
3. Else:
   a. Process with traditional judgehost (existing flow)
```

### Result Processing

```
When custom judgehost calls add-judging-run:
1. Validate submission_id exists
2. Process rubrics:
   - Create/update Rubric entities
   - Create SubmissionRubricScore for each rubric
3. Store metadata in submission
4. Update submission status
5. Trigger scoreboard update
```

---

## Implementation Plan

### Phase 1: Data Model Extensions (Database Schema)

1. **Problem Entity Additions:**

    ```sql
    ALTER TABLE problem ADD COLUMN is_custom_problem BOOLEAN DEFAULT FALSE;
    ALTER TABLE problem ADD COLUMN custom_config JSON;
    ALTER TABLE problem ADD COLUMN custom_judgehost_data JSON;
    ```

2. **Submission Entity Additions:**

    ```sql
    ALTER TABLE submission ADD COLUMN custom_judgehost_submission_id VARCHAR(255);
    ALTER TABLE submission ADD COLUMN custom_execution_metadata JSON;
    ```

3. **Configuration Table:**
    ```sql
    INSERT INTO configuration (name, value, type, description) VALUES
    ('custom_judgehost_enabled', '0', 'bool', 'Enable custom judgehost integration'),
    ('custom_judgehost_url', '', 'string', 'Custom judgehost base URL'),
    ('custom_judgehost_api_key', '', 'string', 'API key for custom judgehost'),
    ('custom_judgehost_timeout', '300', 'int', 'Timeout for custom judgehost requests (seconds)');
    ```

### Phase 2: Service Layer

1. **Create `CustomJudgehostService`:**

    - `registerProblem(Problem $problem, UploadedFile $package): array`
    - `submitForEvaluation(Submission $submission, array $files): array`
    - `getResults(string $customSubmissionId): array`
    - `fetchLogs(string $logsUrl): string`
    - `fetchArtifact(string $artifactUrl): string`

2. **Extend `ImportProblemService`:**

    - Add custom problem detection logic
    - Call CustomJudgehostService when needed

3. **Extend `SubmissionService`:**
    - Add routing logic for custom problems
    - Create submission package format

### Phase 3: API Endpoint Updates

1. **Update `JudgehostController::addJudgingRunAction()`:**

    - Accept JSON body (in addition to form-data)
    - Parse rubrics array
    - Store custom metadata
    - Create/update Rubric and SubmissionRubricScore entities
    - Handle logs_url and artifacts_urls

2. **Keep backward compatibility:**
    - Traditional judgehosts continue using existing format
    - Custom judgehosts use new JSON format

### Phase 4: UI Updates

1. **Problem Upload Page:**

    - Detect custom problem type
    - Show appropriate feedback
    - Display custom judgehost registration status

2. **Submission View:**

    - Display rubric scores
    - Show execution metadata
    - Link to logs and artifacts (if available)

3. **Scoreboard:**
    - Handle custom scoring (rubric-based)
    - Display percentage scores

### Phase 5: Testing & Documentation

1. **Unit Tests:**

    - CustomJudgehostService methods
    - Enhanced add-judging-run parsing

2. **Integration Tests:**

    - Full flow: upload custom problem
    - Full flow: submit to custom problem
    - Full flow: receive and process results

3. **Documentation:**
    - Administrator guide for setting up custom judgehost
    - API documentation updates
    - Problem package format specification

---

## File Changes Required

### New Files

1. `webapp/src/Service/CustomJudgehostService.php`
2. `webapp/src/Entity/CustomProblemConfig.php` (optional, if not using JSON)
3. `webapp/migrations/VersionXXXXXXXX_AddCustomJudgehostSupport.php`
4. `_tasks/CUSTOM_JUDGEHOST_ADMIN_GUIDE.md`

### Modified Files

1. **Entity:**

    - `webapp/src/Entity/Problem.php` - Add custom fields
    - `webapp/src/Entity/Submission.php` - Add custom fields

2. **Services:**

    - `webapp/src/Service/ImportProblemService.php` - Add detection & routing
    - `webapp/src/Service/SubmissionService.php` - Add submission routing

3. **Controllers:**

    - `webapp/src/Controller/API/JudgehostController.php` - Update add-judging-run
    - `webapp/src/Controller/API/ProblemController.php` - Add custom handling
    - `webapp/src/Controller/API/SubmissionController.php` - Add routing

4. **Configuration:**
    - `etc/config.h.in` - Add configuration options
    - Database fixtures for default config values

---

## API Endpoint Summary

### Modified Endpoint

```
POST /api/judgehosts/add-judging-run/{hostname}/{submissionId}

Old (kept for backward compatibility):
- Content-Type: application/x-www-form-urlencoded
- Fields: runresult, runtime, output_*, etc.

New (for custom judgehost):
- Content-Type: application/json
- Body: Full result object with rubrics, metadata, URLs
```

### Custom Judgehost Calls (from DOMjudge)

```
POST {CUSTOM_JUDGEHOST_URL}/problems
- Triggered: When custom problem uploaded
- Sends: Problem package
- Receives: Registration confirmation

POST {CUSTOM_JUDGEHOST_URL}/submissions
- Triggered: When submission to custom problem created
- Sends: Submission package
- Receives: Queuing confirmation

GET {CUSTOM_JUDGEHOST_URL}/api/results/{submission_id}
- Triggered: To check status or poll results
- Receives: Evaluation status/results
```

---

## Security Considerations

1. **Authentication:**

    - Custom judgehost authenticates to DOMserver using HTTP Basic Auth
    - DOMserver authenticates to custom judgehost using API key

2. **Input Validation:**

    - Validate all custom problem configs
    - Sanitize submission packages
    - Validate rubric data structure

3. **Resource Limits:**

    - Timeout for custom judgehost requests
    - Max package size limits
    - Rate limiting on custom submissions

4. **Isolation:**
    - Custom problems run in separate Docker containers
    - No cross-contamination with traditional judging

---

## Rollout Strategy

### Development Environment

1. Set up custom judgehost locally
2. Create sample custom problems (SQL optimization)
3. Test full workflow

### Testing Environment

1. Deploy both systems
2. Run integration tests
3. Performance testing

### Production Rollout

1. Deploy with feature flag disabled
2. Enable for specific contests
3. Monitor logs and performance
4. Gradual rollout to all contests

---

## Monitoring & Logging

### Logs to Add

1. Custom problem registration attempts
2. Submission routing decisions
3. Custom judgehost HTTP calls (request/response)
4. Result processing from custom judgehost
5. Errors and timeouts

### Metrics

1. Custom problem count
2. Custom submissions per minute
3. Average evaluation time
4. Success/failure rates
5. Custom judgehost availability

---

## Fallback & Error Handling

### If Custom Judgehost is Down:

1. Mark submissions as "JUDGING ERROR"
2. Queue for retry
3. Send admin notification
4. Display user-friendly error message

### If Results are Malformed:

1. Log error details
2. Mark submission as "SYSTEM ERROR"
3. Preserve raw response for debugging
4. Notify administrators

### If Custom Problem Registration Fails:

1. Rollback problem creation
2. Display error to admin
3. Suggest manual retry
4. Keep uploaded package for investigation

---

## Future Enhancements

1. **Real-time Updates:**

    - WebSocket support for live judging updates
    - Progress bars for long evaluations

2. **Multi-Judgehost Support:**

    - Load balancing across multiple custom judgehosts
    - Failover support

3. **Advanced Rubrics:**

    - Weighted rubrics
    - Conditional rubrics
    - Team peer evaluation

4. **Artifact Viewing:**

    - In-browser log viewer
    - Metrics visualization
    - Performance graphs

5. **Problem Templates:**
    - Gallery of custom problem templates
    - One-click deployment
    - Version management

---

## Success Criteria

### MVP (Minimum Viable Product)

-   [x] Custom problems can be uploaded
-   [ ] Submissions are routed correctly
-   [ ] Results are processed and displayed
-   [ ] Rubric scores show on scoreboard
-   [ ] Basic error handling works

### Full Release

-   [ ] All entrypoints support custom problems
-   [ ] Comprehensive error handling
-   [ ] Admin configuration UI
-   [ ] Complete documentation
-   [ ] Production-ready monitoring

---

## Timeline Estimate

-   **Phase 1 (Data Model):** 1-2 days
-   **Phase 2 (Service Layer):** 3-4 days
-   **Phase 3 (API Updates):** 2-3 days
-   **Phase 4 (UI Updates):** 2-3 days
-   **Phase 5 (Testing & Docs):** 2-3 days

**Total:** ~2 weeks for full implementation

---

## Questions to Resolve

1. Should custom problem uploads be restricted to admins only?
2. How to handle mixed contests (traditional + custom problems)?
3. Should we support custom judgehost per problem or per contest?
4. What's the max allowed evaluation time?
5. How to handle partial results (e.g., some rubrics complete, others pending)?

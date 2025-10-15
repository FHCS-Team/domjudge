# Custom Judgehost Integration - Implementation Progress

## ✅ Completed Phases

### Phase 1: Data Model Extensions (COMPLETED)

**Migration:** `Version20251015120000.php`

**Database Changes:**

-   ✅ Added to `problem` table:

    -   `is_custom_problem` (TINYINT) - Flag for custom problems
    -   `custom_config` (JSON) - Configuration from problem's config.json
    -   `custom_judgehost_data` (JSON) - Registration response data
    -   `project_type` (VARCHAR) - Project type (database, nodejs-api, etc.)
    -   Indexes on `is_custom_problem` and `project_type`
    -   JSON validation constraints

-   ✅ Added to `submission` table:

    -   `custom_judgehost_submission_id` (VARCHAR) - External submission ID
    -   `custom_execution_metadata` (JSON) - Execution metadata from judgehost
    -   Index on `custom_judgehost_submission_id`
    -   JSON validation constraint

-   ✅ Added configuration entries:
    -   `custom_judgehost_enabled` - Enable/disable integration (default: 0)
    -   `custom_judgehost_url` - Base URL for custom judgehost
    -   `custom_judgehost_api_key` - API key for authentication
    -   `custom_judgehost_timeout` - Timeout in seconds (default: 300)

**Entity Updates:**

-   ✅ Updated `Problem.php` with:

    -   New properties with ORM annotations
    -   Getter/setter methods for all custom fields
    -   `isCustomProblem()` convenience method

-   ✅ Updated `Submission.php` with:
    -   New properties with ORM annotations
    -   Getter/setter methods for custom fields

**Status:** Migration applied successfully to database ✅

---

### Phase 2: CustomJudgehostService (COMPLETED)

**Service File:** `webapp/src/Service/CustomJudgehostService.php`

**Implementation:**

-   ✅ HTTP client service for custom judgehost communication
-   ✅ Configuration integration (checks enabled flag, URL, API key, timeout)
-   ✅ `registerProblem()` - POST problem package to `/problems` endpoint
-   ✅ `submitForEvaluation()` - Create tarball and POST to `/submissions` endpoint
-   ✅ `getResults()` - Poll `/api/results/{id}` for evaluation results
-   ✅ `fetchLogs()` - Retrieve logs from provided URL
-   ✅ `fetchArtifact()` - Retrieve artifacts from provided URLs
-   ✅ Comprehensive error handling and logging
-   ✅ Automatic cleanup of temporary files

**Status:** Service fully implemented ✅

---

### Phase 3: Custom Judging Result Endpoint (COMPLETED)

**Controller:** `webapp/src/Controller/API/JudgehostController.php`

**Endpoint:** `POST /api/judgehosts/add-custom-judging-result`

**Implementation:**

-   ✅ New endpoint that accepts JSON payload (not form-urlencoded)
-   ✅ Validates required fields: `submission_id`, `status`, `overall_score`
-   ✅ Accepts optional fields: `rubrics`, `logs_url`, `artifacts_urls`, `execution_time`, `error_message`
-   ✅ Finds submission by `custom_judgehost_submission_id`
-   ✅ Stores execution metadata in `submission.custom_execution_metadata`
-   ✅ Processes rubrics array:
    -   Creates `Rubric` entities if they don't exist
    -   Creates or updates `SubmissionRubricScore` entities
    -   Stores rubric name, score, weight, and feedback
-   ✅ Updates judging verdict based on status and score:
    -   `completed` + score >= 0.5 → `correct`
    -   `completed` + score < 0.5 → `wrong-answer`
    -   `error` → `run-error`
    -   `timeout` → `timelimit`
-   ✅ Triggers scoreboard refresh
-   ✅ Updates balloons
-   ✅ Fires event log for judging update
-   ✅ Returns JSON response with success status

**Status:** Endpoint fully implemented ✅

---

### Phase 4: Problem Upload Integration (COMPLETED)

**File:** `webapp/src/Service/ImportProblemService.php`

**Changes:**

1. **Constructor Update:**

    - ✅ Injected `CustomJudgehostService` dependency

2. **`importZippedProblem()` Method:**
    - ✅ Detects `config.json` in problem ZIP archive root
    - ✅ Parses `project_type` field from config.json
    - ✅ Marks problem with `is_custom_problem = true`
    - ✅ Stores entire config.json in `custom_config` field
    - ✅ Stores `project_type` in dedicated field
    - ✅ Checks if CustomJudgehostService is enabled
    - ✅ Creates UploadedFile from ZIP for registration
    - ✅ Calls `CustomJudgehostService::registerProblem()`
    - ✅ Stores registration response in `custom_judgehost_data`
    - ✅ Handles registration errors gracefully with warnings
    - ✅ Persists all changes to database

**Workflow:**

1. Problem ZIP uploaded via UI or API
2. `importZippedProblem()` extracts and parses files
3. If `config.json` exists with `project_type`:
    - Problem flagged as custom
    - Configuration stored in database
    - If integration enabled: registers with custom judgehost
    - Registration response stored for reference
4. Normal problem import continues (testcases, etc.)

**Status:** Problem upload integration complete ✅

---

### Phase 5: Submission Routing (COMPLETED)

**File:** `webapp/src/Service/SubmissionService.php`

**Changes:**

1. **Constructor Update:**

    - ✅ Injected `CustomJudgehostService` dependency

2. **`submitSolution()` Method:**
    - ✅ After creating submission and judging entities
    - ✅ Checks if `problem->isCustomProblem()` is true
    - ✅ Checks if `CustomJudgehostService::isEnabled()`
    - ✅ If both true:
        - Gathers all submission file contents into array
        - Calls `CustomJudgehostService::submitForEvaluation()`
        - Stores `custom_judgehost_submission_id` from response
        - Marks judging as started (pending custom result)
        - Skips creation of regular JudgeTasks
        - Logs success with submission IDs
    - ✅ If submission fails:
        - Marks judging as 'judging-error'
        - Logs error details
    - ✅ If not custom problem:
        - Continues with regular judging workflow
        - Creates JudgeTasks normally

**Workflow:**

1. User submits solution to custom problem
2. `submitSolution()` creates Submission and Judging entities
3. Detects custom problem type
4. Forwards submission files to custom judgehost via HTTP
5. Stores external submission ID for tracking
6. Custom judgehost processes asynchronously
7. Results received later via `add-custom-judging-result` endpoint

**Status:** Submission routing complete ✅

---

### Phase 6: UI Updates (COMPLETED)

**Files Modified:**

-   `webapp/src/Controller/Jury/SubmissionController.php`
-   `webapp/src/Controller/Team/SubmissionController.php`
-   `webapp/templates/jury/submission.html.twig`
-   `webapp/templates/team/partials/submission.html.twig`

**Controller Changes:**

1. **Jury Submission Controller:**

    - ✅ Fetches `SubmissionRubricScore` entities for submission
    - ✅ Retrieves `customExecutionMetadata` from submission
    - ✅ Passes data to template via `$twigData`

2. **Team Submission Controller:**
    - ✅ Fetches rubric scores for team view
    - ✅ Retrieves execution metadata
    - ✅ Passes data to template

**Template Features:**

1. **Custom Problem Badge:**

    - ✅ Displays "CUSTOM" badge with rocket icon
    - ✅ Shows `project_type` badge (e.g., "database-optimization")
    - ✅ Visible in problem name section

2. **Rubric Scores Section (Jury View):**

    - ✅ Full table with columns: Criterion, Score, Weight, Weighted Score, Feedback, Judged
    - ✅ Color-coded score badges:
        - Green (≥80%), Blue (≥60%), Warning (≥40%), Red (<40%)
    - ✅ Overall score display with percentage
    - ✅ Execution time and status display
    - ✅ Totals footer with normalized score
    - ✅ Links to execution logs (if available)
    - ✅ Download buttons for artifacts
    - ✅ Error message display (if any)

3. **Rubric Scores Section (Team View):**
    - ✅ Simplified table with: Criterion, Score, Feedback
    - ✅ Overall score prominently displayed
    - ✅ Color-coded badges for scores
    - ✅ Error message display (if any)
    - ✅ Clean, team-friendly interface

**Visual Design:**

-   Uses Bootstrap 5 classes for styling
-   Card-based layout for rubric section
-   Font Awesome icons for visual indicators
-   Responsive table design
-   Collapsible sections where appropriate

**Status:** UI updates complete ✅

---

## 🚧 Pending Phases

### Phase 7: Testing & Documentation (NOT STARTED)

**File:** `webapp/src/Service/CustomJudgehostService.php`

**Implemented Methods:**

1. **`isEnabled()`** - Check if integration is enabled
2. **`registerProblem()`** - Register problem with custom judgehost

    - Sends multipart form-data with problem package
    - Returns registration response with image names
    - Comprehensive error handling and logging

3. **`submitForEvaluation()`** - Submit solution for evaluation

    - Creates temporary tarball from submission files
    - Posts to custom judgehost /submissions endpoint
    - Returns submission ID and queuing status
    - Automatic cleanup of temporary files

4. **`getResults()`** - Poll for evaluation results

    - Fetches results from custom judgehost
    - Returns null if not yet available
    - Handles 404 and error cases

5. **`fetchLogs()`** - Retrieve execution logs

    - Fetches from logs_url provided in results

6. **`fetchArtifact()`** - Retrieve artifacts
    - Fetches metrics, reports, etc. from artifact URLs

**Features:**

-   ✅ Configuration-driven (uses ConfigurationService)
-   ✅ Comprehensive logging (success, errors, debugging)
-   ✅ HTTP client with timeout support
-   ✅ API key authentication via X-API-Key header
-   ✅ Proper exception handling
-   ✅ Temporary file cleanup

**Status:** Service implementation complete ✅

---

## 🚧 In Progress

### Phase 3: Update add-judging-run Endpoint

**Goal:** Modify `JudgehostController::addJudgingRunAction()` to accept new format

**Required Changes:**

1. Accept JSON body (in addition to old form-data)
2. Parse rubrics array from JSON
3. Create/update Rubric entities
4. Create SubmissionRubricScore entities
5. Store execution metadata
6. Handle logs_url and artifacts_urls

**Files to Modify:**

-   `webapp/src/Controller/API/JudgehostController.php`

---

## 📋 Remaining Phases

### Phase 4: Problem Upload Integration

**Goal:** Detect custom problems and forward to custom judgehost

**Required Changes:**

1. Modify `ImportProblemService::importZippedProblem()`

    - Check for config.json in problem ZIP
    - Parse project_type field
    - If custom problem:
        - Set Problem entity flags
        - Call `CustomJudgehostService::registerProblem()`
        - Store registration response

2. Update API endpoints:
    - `ProblemController::addProblemAction()`
    - Handle registration errors gracefully

**Files to Modify:**

-   `webapp/src/Service/ImportProblemService.php`
-   `webapp/src/Controller/API/ProblemController.php`

---

### Phase 5: Submission Routing

**Goal:** Route submissions to custom judgehost for custom problems

**Required Changes:**

1. Modify `SubmissionService::submitSolution()`
    - Check if `problem->isCustomProblem()`
    - If custom:
        - Gather submission files
        - Call `CustomJudgehostService::submitForEvaluation()`
        - Store custom_judgehost_submission_id
        - Skip traditional judging workflow
    - If traditional:
        - Continue existing flow

**Files to Modify:**

-   `webapp/src/Service/SubmissionService.php`

---

### Phase 6: UI Updates

**Goal:** Display rubric scores and custom metadata

**Required Changes:**

1. Submission view page:

    - Show rubric breakdown
    - Display execution metadata
    - Link to logs/artifacts if available

2. Scoreboard:

    - Handle percentage-based scoring
    - Display custom problem indicators

3. Problem admin page:
    - Show custom problem status
    - Display registration info
    - Show project_type

**Files to Modify:**

-   Templates in `webapp/templates/jury/submission.html.twig`
-   Templates in `webapp/templates/team/submission.html.twig`
-   Scoreboard service/templates

---

### Phase 7: Testing & Documentation

**Goal:** Ensure everything works end-to-end

**Tasks:**

1. Create unit tests for CustomJudgehostService
2. Create integration tests:
    - Upload custom problem
    - Submit to custom problem
    - Receive and process results
3. Update administrator documentation
4. Create problem package format specification

---

## Configuration Required

Before using custom judgehost integration, administrators must configure:

```sql
-- Enable the integration
UPDATE configuration SET value = '1' WHERE name = 'custom_judgehost_enabled';

-- Set the custom judgehost URL
UPDATE configuration SET value = 'http://custom-judgehost:3000' WHERE name = 'custom_judgehost_url';

-- Set API key (if required)
UPDATE configuration SET value = 'your-api-key-here' WHERE name = 'custom_judgehost_api_key';

-- Adjust timeout if needed (default: 300 seconds)
UPDATE configuration SET value = '600' WHERE name = 'custom_judgehost_timeout';
```

Or via DOMjudge admin UI once Phase 6 is complete.

---

## Testing the Implementation

### 1. Test Problem Registration

```bash
# Upload a custom problem package with config.json
curl -X POST http://localhost:12345/api/contests/1/problems \
  -H "Authorization: Basic ..." \
  -F "zip=@custom-problem.tar.gz"
```

Expected flow:

1. ImportProblemService detects config.json with project_type
2. Calls CustomJudgehostService::registerProblem()
3. Problem marked as custom in database
4. Registration response stored

### 2. Test Submission

```bash
# Submit a solution to custom problem
curl -X POST http://localhost:12345/api/contests/1/submissions \
  -H "Authorization: Basic ..." \
  -F "problem=custom-problem-id" \
  -F "language=text" \
  -F "code=@solution.tar.gz"
```

Expected flow:

1. SubmissionService detects custom problem
2. Calls CustomJudgehostService::submitForEvaluation()
3. Stores custom_judgehost_submission_id
4. Waits for results callback

### 3. Test Result Reception

```bash
# Custom judgehost POSTs results
curl -X POST http://localhost:12345/api/judgehosts/add-judging-run/custom-host/sub_123 \
  -H "Content-Type: application/json" \
  -H "Authorization: Basic ..." \
  -d '{
    "submission_id": "sub_123",
    "status": "completed",
    "total_score": 85.5,
    "max_score": 100,
    "rubrics": [...]
  }'
```

Expected flow:

1. Endpoint parses JSON body
2. Creates/updates Rubric entities
3. Creates SubmissionRubricScore entries
4. Updates submission status
5. Triggers scoreboard update

---

## Next Steps

**Priority 1:** Phase 3 - Update add-judging-run endpoint

-   This is critical for receiving results from custom judgehost
-   Relatively straightforward modification

**Priority 2:** Phase 4 - Problem upload integration

-   Enables uploading custom problems
-   Uses the CustomJudgehostService we just created

**Priority 3:** Phase 5 - Submission routing

-   Completes the workflow
-   Also uses CustomJudgehostService

**Priority 4:** Phase 6 & 7 - UI and testing

-   Polish and validation
-   User-facing improvements

Would you like to proceed with Phase 3 next?

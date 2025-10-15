# Custom Judgehost Integration - Testing Guide

## Overview

This guide provides comprehensive testing procedures for the custom judgehost integration in DOMjudge.

---

## Prerequisites

### Environment Setup

1. **DOMjudge Installation**

    ```bash
    cd /path/to/domjudge
    docker compose up -d
    ```

2. **Database Migration**

    ```bash
    docker compose exec domserver bin/console doctrine:migrations:migrate
    ```

3. **Configuration**
    ```bash
    # Set configuration via admin panel or database
    docker compose exec mariadb mariadb -u domjudge -pdomjudge domjudge -e "
    UPDATE configuration SET value='1' WHERE name='custom_judgehost_enabled';
    UPDATE configuration SET value='http://custom-judgehost:8000' WHERE name='custom_judgehost_url';
    UPDATE configuration SET value='your-secret-key' WHERE name='custom_judgehost_api_key';
    UPDATE configuration SET value='600' WHERE name='custom_judgehost_timeout';
    "
    ```

---

## Unit Tests

### Running Tests

```bash
# Run all tests
cd webapp
php bin/phpunit

# Run specific test class
php bin/phpunit tests/Unit/Service/CustomJudgehostServiceTest.php

# Run with coverage
php bin/phpunit --coverage-html coverage/
```

### Test Coverage

The `CustomJudgehostServiceTest.php` includes tests for:

-   ✅ `isEnabled()` - Configuration check
-   ✅ `registerProblem()` - Problem registration with success/failure cases
-   ✅ `submitForEvaluation()` - Submission forwarding with error handling
-   ✅ `getResults()` - Result polling with 404/success scenarios
-   ✅ `fetchLogs()` - Log retrieval
-   ✅ `fetchArtifact()` - Artifact download

**Expected Output:**

```
PHPUnit 9.x.x by Sebastian Bergmann and contributors.

..........                                                        10 / 10 (100%)

Time: 00:00.123, Memory: 10.00 MB

OK (10 tests, 25 assertions)
```

---

## Integration Tests

### Test 1: Problem Upload Detection

**Objective:** Verify custom problem detection and registration

**Steps:**

1. Create test problem ZIP:

    ```bash
    cd /tmp
    mkdir test-problem
    cd test-problem

    # Create config.json
    cat > config.json << 'EOF'
    {
      "project_type": "database-optimization",
      "containers": {
        "base": {
          "dockerfile": "Dockerfile.base"
        }
      },
      "rubrics": [
        {
          "name": "Query Correctness",
          "weight": 3.0,
          "threshold": 0.7
        }
      ]
    }
    EOF

    # Create problem.yaml
    cat > problem.yaml << 'EOF'
    name: 'Database Query Optimizer'
    timelimit: 30
    EOF

    # Create ZIP
    zip -r ../test-problem.zip .
    ```

2. Upload via web interface:

    - Login as admin
    - Navigate to "Problems"
    - Click "Import problem"
    - Upload `test-problem.zip`
    - Check for success message

3. Verify in database:
    ```bash
    docker compose exec mariadb mariadb -u domjudge -pdomjudge domjudge -e "
    SELECT probid, name, is_custom_problem, project_type
    FROM problem
    WHERE name LIKE '%Query Optimizer%';
    "
    ```

**Expected Result:**

```
+--------+---------------------------+-------------------+-----------------------+
| probid | name                      | is_custom_problem | project_type          |
+--------+---------------------------+-------------------+-----------------------+
|      1 | Database Query Optimizer  |                 1 | database-optimization |
+--------+---------------------------+-------------------+-----------------------+
```

4. Check custom_config field:
    ```bash
    docker compose exec mariadb mariadb -u domjudge -pdomjudge domjudge -e "
    SELECT JSON_PRETTY(custom_config) FROM problem WHERE probid=1;
    "
    ```

**Expected:** Valid JSON with project_type, containers, rubrics

---

### Test 2: Submission Routing

**Objective:** Verify submissions route to custom judgehost

**Steps:**

1. Submit solution to custom problem:

    ```bash
    # Via API
    curl -X POST http://localhost:12345/api/contests/1/submissions \
      -H "Authorization: Bearer YOUR_TOKEN" \
      -F "problem=1" \
      -F "language=cpp" \
      -F "code[]=@solution.cpp"
    ```

2. Check logs:
    ```bash
    docker compose logs domserver | grep "Routing submission to custom judgehost"
    ```

**Expected Output:**

```
[2025-10-15 10:30:45] app.INFO: Routing submission to custom judgehost {
  "submission_id": 123,
  "problem_id": 1,
  "project_type": "database-optimization"
}
```

3. Verify database:
    ```bash
    docker compose exec mariadb mariadb -u domjudge -pdomjudge domjudge -e "
    SELECT submitid, custom_judgehost_submission_id
    FROM submission
    WHERE submitid=123;
    "
    ```

**Expected:**

```
+-----------+----------------------------------+
| submitid  | custom_judgehost_submission_id   |
+-----------+----------------------------------+
|       123 | sub_abc123xyz                    |
+-----------+----------------------------------+
```

4. Verify no judge tasks created:
    ```bash
    docker compose exec mariadb mariadb -u domjudge -pdomjudge domjudge -e "
    SELECT COUNT(*) as task_count
    FROM judgetask
    WHERE jobid IN (
      SELECT judgingid FROM judging WHERE submitid=123
    );
    "
    ```

**Expected:**

```
+------------+
| task_count |
+------------+
|          0 |
+------------+
```

---

### Test 3: Result Reception

**Objective:** Verify custom judgehost can submit results

**Setup Mock Custom Judgehost:**

```bash
# Create simple result submission script
cat > /tmp/submit_result.sh << 'EOF'
#!/bin/bash
curl -X POST http://localhost:12345/api/judgehosts/add-custom-judging-result \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer JUDGEHOST_TOKEN" \
  -d '{
    "submission_id": "sub_abc123xyz",
    "status": "completed",
    "overall_score": 0.855,
    "rubrics": [
      {
        "name": "Query Correctness",
        "score": 0.9,
        "weight": 3.0,
        "feedback": "All test queries passed"
      },
      {
        "name": "Performance",
        "score": 0.85,
        "weight": 2.0,
        "feedback": "Good optimization"
      },
      {
        "name": "Code Quality",
        "score": 0.8,
        "weight": 1.0,
        "feedback": "Clean structure"
      }
    ],
    "execution_time": 45.2,
    "logs_url": "http://example.com/logs/123",
    "artifacts_urls": [
      "http://example.com/artifacts/report.json"
    ]
  }'
EOF

chmod +x /tmp/submit_result.sh
/tmp/submit_result.sh
```

**Expected Response:**

```json
{
    "success": true,
    "message": "Judging result processed successfully",
    "submission_id": 123,
    "verdict": "correct"
}
```

**Verify Database:**

```bash
# Check rubric entities created
docker compose exec mariadb mariadb -u domjudge -pdomjudge domjudge -e "
SELECT r.rubricid, r.name, r.weight, r.type
FROM rubric r
JOIN problem p ON r.probid = p.probid
WHERE p.probid = 1;
"
```

**Expected:**

```
+-----------+-------------------+--------+-----------+
| rubricid  | name              | weight | type      |
+-----------+-------------------+--------+-----------+
|         1 | Query Correctness |    3.0 | automated |
|         2 | Performance       |    2.0 | automated |
|         3 | Code Quality      |    1.0 | automated |
+-----------+-------------------+--------+-----------+
```

```bash
# Check rubric scores
docker compose exec mariadb mariadb -u domjudge -pdomjudge domjudge -e "
SELECT
  srs.scoreid,
  r.name as rubric_name,
  srs.score,
  srs.judge_name,
  srs.comments
FROM submission_rubric_score srs
JOIN rubric r ON srs.rubricid = r.rubricid
WHERE srs.submitid = 123;
"
```

**Expected:**

```
+---------+-------------------+-------+-------------------+----------------------+
| scoreid | rubric_name       | score | judge_name        | comments             |
+---------+-------------------+-------+-------------------+----------------------+
|       1 | Query Correctness |  0.90 | custom_judgehost  | All test queries...  |
|       2 | Performance       |  0.85 | custom_judgehost  | Good optimization    |
|       3 | Code Quality      |  0.80 | custom_judgehost  | Clean structure      |
+---------+-------------------+-------+-------------------+----------------------+
```

```bash
# Check judging verdict
docker compose exec mariadb mariadb -u domjudge -pdomjudge domjudge -e "
SELECT j.judgingid, j.result, j.starttime, j.endtime
FROM judging j
WHERE j.submitid = 123;
"
```

**Expected:**

```
+------------+---------+---------------------+---------------------+
| judgingid  | result  | starttime           | endtime             |
+------------+---------+---------------------+---------------------+
|          1 | correct | 2025-10-15 10:30:45 | 2025-10-15 10:31:30 |
+------------+---------+---------------------+---------------------+
```

---

### Test 4: UI Display

**Objective:** Verify rubric scores display correctly

**Steps:**

1. **Jury View:**
    - Navigate to submission page: `http://localhost:12345/jury/submissions/123`
    - Verify custom problem badge visible
    - Scroll to "Rubric Evaluation Scores" section
    - Check all rubric rows display with scores
    - Verify overall score badge shows "85.5%"
    - Check color coding (green for 90%, etc.)

**Expected Elements:**

```html
<span class="badge badge-primary">CUSTOM</span>
<span class="badge badge-info">database-optimization</span>

<h4>Rubric Evaluation Scores</h4>
<span class="badge badge-success fs-5">85.5%</span>

<table class="table table-sm table-striped">
    <tr>
        <td><strong>Query Correctness</strong></td>
        <td><span class="badge badge-success">90.0%</span></td>
        ...
    </tr>
</table>
```

2. **Team View:**
    - Login as team
    - Navigate to submission: `http://localhost:12345/team/submission/123`
    - Verify rubric section displays
    - Check simplified table format
    - Verify overall score

**Expected Elements:**

```html
<h4>Evaluation Rubric Scores</h4>
<h5>Overall Score: <span class="badge text-bg-success fs-4">85.5%</span></h5>

<table class="table table-sm table-striped">
    <thead>
        <tr>
            <th>Criterion</th>
            <th>Score</th>
            <th>Feedback</th>
        </tr>
    </thead>
    ...
</table>
```

---

## Error Scenario Tests

### Test 5: Custom Judgehost Unreachable

**Objective:** Verify graceful failure when judgehost is down

**Steps:**

1. Stop custom judgehost or set invalid URL:

    ```bash
    docker compose exec mariadb mariadb -u domjudge -pdomjudge domjudge -e "
    UPDATE configuration
    SET value='http://invalid-host:9999'
    WHERE name='custom_judgehost_url';
    "
    ```

2. Submit to custom problem
3. Check logs for error:
    ```bash
    docker compose logs domserver | grep "Failed to submit to custom judgehost"
    ```

**Expected:**

-   Submission stored in database
-   Judging marked as `judging-error`
-   Error logged but doesn't crash
-   User sees appropriate error message

---

### Test 6: Invalid Result JSON

**Objective:** Verify validation of result payloads

**Steps:**

1. Submit invalid JSON to result endpoint:
    ```bash
    curl -X POST http://localhost:12345/api/judgehosts/add-custom-judging-result \
      -H "Content-Type: application/json" \
      -d '{
        "submission_id": "sub_invalid"
      }'
    ```

**Expected Response:**

```json
{
    "error": "Field 'status' is mandatory"
}
```

2. Submit with missing submission:
    ```bash
    curl -X POST http://localhost:12345/api/judgehosts/add-custom-judging-result \
      -H "Content-Type: application/json" \
      -d '{
        "submission_id": "sub_nonexistent",
        "status": "completed",
        "overall_score": 0.8
      }'
    ```

**Expected Response:**

```json
{
    "error": "Submission with custom_judgehost_submission_id 'sub_nonexistent' not found"
}
```

---

## Performance Tests

### Test 7: Large Problem Package

**Objective:** Verify handling of large problem files

**Steps:**

1. Create 50MB problem package:

    ```bash
    dd if=/dev/urandom of=/tmp/large-file bs=1M count=50
    zip /tmp/large-problem.zip /tmp/large-file config.json
    ```

2. Upload and measure time
3. Check for timeout errors

**Expected:**

-   Upload completes within timeout period
-   No memory errors
-   Proper cleanup of temporary files

---

### Test 8: Concurrent Submissions

**Objective:** Verify handling of multiple simultaneous submissions

**Steps:**

1. Submit 10 solutions concurrently:

    ```bash
    for i in {1..10}; do
      curl -X POST http://localhost:12345/api/contests/1/submissions \
        -H "Authorization: Bearer TOKEN" \
        -F "problem=1" \
        -F "language=cpp" \
        -F "code[]=@solution${i}.cpp" &
    done
    wait
    ```

2. Verify all submissions processed:
    ```bash
    docker compose exec mariadb mariadb -u domjudge -pdomjudge domjudge -e "
    SELECT COUNT(*) FROM submission
    WHERE probid=1
    AND submittime > DATE_SUB(NOW(), INTERVAL 1 MINUTE);
    "
    ```

**Expected:** All 10 submissions stored correctly

---

## Regression Tests

### Test 9: Regular Problems Still Work

**Objective:** Ensure non-custom problems unaffected

**Steps:**

1. Upload regular problem (no config.json)
2. Submit solution
3. Verify normal judging workflow:
    - Judge tasks created
    - Testcases evaluated
    - Results displayed normally

**Database Check:**

```bash
docker compose exec mariadb mariadb -u domjudge -pdomjudge domjudge -e "
SELECT p.probid, p.name, p.is_custom_problem
FROM problem p
WHERE p.name = 'Regular Problem';
"
```

**Expected:**

```
+--------+----------------+-------------------+
| probid | name           | is_custom_problem |
+--------+----------------+-------------------+
|      2 | Regular Problem|                 0 |
+--------+----------------+-------------------+
```

---

## Cleanup

### Reset Test Data

```bash
# Delete test submissions
docker compose exec mariadb mariadb -u domjudge -pdomjudge domjudge -e "
DELETE FROM submission WHERE submitid >= 100;
"

# Delete test problems
docker compose exec mariadb mariadb -u domjudge -pdomjudge domjudge -e "
DELETE FROM problem WHERE probid >= 100;
"

# Delete test rubrics
docker compose exec mariadb mariadb -u domjudge -pdomjudge domjudge -e "
DELETE FROM rubric WHERE rubricid >= 100;
"
```

---

## Test Checklist Summary

### Unit Tests

-   [ ] CustomJudgehostService tests pass
-   [ ] All methods covered
-   [ ] Edge cases tested

### Integration Tests

-   [ ] Problem upload detection works
-   [ ] Custom config stored correctly
-   [ ] Submission routing correct
-   [ ] No judge tasks for custom problems
-   [ ] Results received and processed
-   [ ] Rubrics created in database
-   [ ] Scores stored correctly
-   [ ] Judging verdict updated

### UI Tests

-   [ ] Jury view displays rubrics
-   [ ] Team view displays rubrics
-   [ ] Custom badge visible
-   [ ] Color coding works
-   [ ] Overall score correct
-   [ ] Links to logs/artifacts work

### Error Handling

-   [ ] Unreachable judgehost handled
-   [ ] Invalid JSON rejected
-   [ ] Missing submissions detected
-   [ ] Timeouts handled gracefully

### Performance

-   [ ] Large files handled
-   [ ] Concurrent submissions work
-   [ ] No memory leaks

### Regression

-   [ ] Regular problems unaffected
-   [ ] Normal judging still works
-   [ ] Existing features intact

---

## Continuous Integration

### GitHub Actions Example

```yaml
name: Custom Judgehost Tests

on: [push, pull_request]

jobs:
    test:
        runs-on: ubuntu-latest
        steps:
            - uses: actions/checkout@v3

            - name: Setup PHP
              uses: shivammathur/setup-php@v2
              with:
                  php-version: "8.2"

            - name: Install dependencies
              run: |
                  cd webapp
                  composer install

            - name: Run tests
              run: |
                  cd webapp
                  php bin/phpunit tests/Unit/Service/CustomJudgehostServiceTest.php

            - name: Check coverage
              run: |
                  cd webapp
                  php bin/phpunit --coverage-text
```

---

## Reporting Issues

When reporting test failures, include:

1. **Test name and objective**
2. **Steps to reproduce**
3. **Expected vs actual result**
4. **Relevant logs:**
    ```bash
    docker compose logs domserver > domserver.log
    docker compose logs mariadb > mariadb.log
    ```
5. **Database state:**
    ```bash
    docker compose exec mariadb mysqldump domjudge > dump.sql
    ```
6. **Configuration:**
    ```bash
    docker compose exec mariadb mariadb -u domjudge -pdomjudge domjudge -e "
    SELECT * FROM configuration WHERE name LIKE 'custom%';
    " > config.txt
    ```

---

**Testing Complete!** 🎉

All tests passing indicates the custom judgehost integration is production-ready.

# Custom Problem Package Upload - User Guide

## Quick Start

### 1. Access the Upload Page

**Navigate to:**

```
http://localhost:12345/jury/problems
```

**Then click the green button:**

```
[Add problem package] 🗂️
```

### 2. What You'll See

The upload page has three main sections:

#### A. Information Box (Blue)

Explains what files your package should contain:

-   ✅ config.json (root configuration with containers array - required for custom problems)
-   ✅ Container directories (e.g., database/, submission/):
    -   ✅ Dockerfile (container image definition)
    -   ✅ stage1.config.json (Stage 1: problem build configuration)
    -   ✅ stage2.config.json (Stage 2: submission evaluation configuration)
    -   ✅ hooks/ (evaluation scripts: pre/, post/, periodic/)
    -   ✅ data/ (container-specific test data)
-   ✅ README.md (optional problem description)

#### B. Upload Form

-   **Problem Package (ZIP)\*** - Required file upload
-   **Optional Overrides:**
    -   Problem Name - Override name from config
    -   External ID - Set unique identifier
    -   Time Limit - Override timeout in seconds

#### C. Documentation

-   Example package structure
-   Sample config.json template

---

## Step-by-Step Instructions

### Step 1: Prepare Your Package

Create a ZIP file with this structure:

```
my-problem.zip
└── problem-id/
    ├── config.json              ← Global configuration (required)
    │
    ├── database/                ← Database container
    │   ├── Dockerfile
    │   ├── stage1.config.json
    │   ├── stage2.config.json
    │   ├── hooks/
    │   │   ├── pre/
    │   │   │   ├── 01_initialize.sh
    │   │   │   └── 02_migration.sh
    │   │   └── periodic/
    │   │       └── 01_healthcheck.sh
    │   └── data/
    │       └── baseline_queries.sql
    │
    ├── submission/              ← Submission container
    │   ├── Dockerfile
    │   ├── stage1.config.json
    │   ├── stage2.config.json
    │   ├── hooks/
    │   │   ├── pre/
    │   │   │   ├── 01_setup.sh
    │   │   │   └── 02_migration.sh
    │   │   └── post/
    │   │       ├── 01_test_queries.sh
    │   │       ├── 02_test_concurrency.sh
    │   │       └── 03_evaluate_storage.sh
    │   └── data/
    │
    └── README.md                ← Problem description
```

**Example config.json:**

```json
{
    "problem_id": "sql-optimization",
    "problem_name": "Database Query Optimization Challenge",
    "project_type": "database",
    "time_limit": 1800,

    "containers": [
        {
            "container_id": "database",
            "name": "PostgreSQL Database Server",
            "accepts_submission": false,
            "dockerfile_path": "database/Dockerfile",
            "depends_on": []
        },
        {
            "container_id": "submission",
            "name": "Query Evaluation Container",
            "accepts_submission": true,
            "dockerfile_path": "submission/Dockerfile",
            "depends_on": [
                {
                    "container_id": "database",
                    "condition": "healthy",
                    "timeout": 60
                }
            ]
        }
    ],

    "rubrics": [
        {
            "rubric_id": "correctness",
            "name": "Query Result Correctness",
            "type": "test_cases",
            "max_score": 50,
            "container": "submission"
        },
        {
            "rubric_id": "query_latency",
            "name": "Query Latency Performance",
            "type": "performance_benchmark",
            "max_score": 30,
            "container": "submission"
        }
    ]
}
```

### Step 2: Upload Package

1. Click **"Choose File"** button
2. Select your ZIP file (max 100MB)
3. (Optional) Fill in override fields:
    ```
    Problem Name: My Custom Database Problem
    External ID: my-db-problem-001
    Time Limit: 180
    ```
4. Click **"Upload Problem Package"** button
5. Wait for processing (shows spinning indicator)

### Step 3: View Results

**Success Message (Green Box):**

```
✓ Upload Successful!
Problem package has been uploaded and processed successfully.

Problem ID: #42
Problem Name: Database Query Optimization
External ID: db-opt-001
Problem Type: [Custom Problem] 🚀
              Project Type: database-optimization
Custom Judgehost: [Registered] ✓

[View Problem Details] [Back to Problems List] [Upload Another Package]
```

**Error Message (Red Box):**

```
⚠ Upload Failed
Error: [Detailed error message]

[Try Again] [Back to Problems]
```

---

## What Happens Behind the Scenes

### 1. File Upload

-   Validates ZIP file
-   Checks file size (< 100MB)
-   Verifies file type

### 2. Package Processing

-   Extracts ZIP contents
-   Looks for `config.json` in root
-   Checks for `containers` array in config
-   If found → Marks as **Custom Problem**
-   If not found → Treats as **Standard Problem**

### 3. Custom Problem Registration

For packages with `config.json` and `containers`:

-   Parses problem metadata, containers, and rubrics
-   Calls Custom Judgehost API to register problem
-   Judgehost builds Docker images for all containers
-   Stores judgehost response and problem configuration

### 4. Database Entry

-   Creates Problem entity
-   Stores configuration
-   Links to current contest
-   Applies any user overrides

### 5. Status Reporting

-   Shows upload results
-   Displays problem details
-   Provides navigation options

---

## Custom vs Standard Problems

### Custom Problem (with config.json + containers)

```
✅ Detected automatically (config.json with containers array)
✅ Registered with custom judgehost
✅ Multi-container orchestration (database, submission, tester)
✅ Evaluated using Docker containers with hooks
✅ Rubric-based scoring (automated evaluation)
✅ Custom project types (database, nodejs-api, etc.)
✅ Stage-based execution (build vs evaluation)
```

**Indicator:**

```
Problem Type: [Custom Problem] 🚀
              Project Type: database
Custom Judgehost: [Registered] ✓
```

### Standard Problem (traditional DOMjudge)

```
✅ Regular DOMjudge problem format
✅ Evaluated by standard judges
✅ Traditional test cases with problem.yaml
✅ Standard scoring (pass/fail, points)
✅ Single execution environment
```

**Indicator:**

```
Problem Type: [Standard Problem]
```

---

## Field Descriptions

### Required Fields

**Problem Package (ZIP)\***

-   File type: ZIP archive
-   Max size: 100 MB
-   Must contain problem files
-   Can include any problem type

### Optional Override Fields

**Problem Name**

-   Overrides name from config.json or problem.yaml
-   Example: "Advanced Database Optimization"
-   Useful when name in config is generic

**External ID**

-   Unique identifier for the problem
-   Example: "db-opt-advanced-001"
-   Used in APIs and external systems

**Time Limit (seconds)**

-   Overrides timeout from config.json
-   Example: 120 (2 minutes)
-   Maximum evaluation time

---

## Example Packages

### Example 1: Database Optimization

**Package Contents:**

```
db-optimization.zip
└── sql-optimization/
    ├── config.json
    ├── database/
    │   ├── Dockerfile (PostgreSQL 15)
    │   ├── stage1.config.json
    │   ├── stage2.config.json
    │   ├── hooks/
    │   │   ├── pre/
    │   │   │   ├── 01_initialize.sh
    │   │   │   └── 02_migration.sh
    │   │   └── periodic/
    │   │       └── 01_healthcheck.sh
    │   └── data/
    │       └── baseline_queries.sql
    ├── submission/
    │   ├── Dockerfile (Python 3.11 + psycopg2)
    │   ├── stage1.config.json
    │   ├── stage2.config.json
    │   ├── hooks/
    │   │   ├── pre/
    │   │   │   ├── 01_setup.sh
    │   │   │   └── 02_migration.sh
    │   │   └── post/
    │   │       ├── 01_test_queries.sh
    │   │       ├── 02_test_concurrency.sh
    │   │       └── 03_evaluate_storage.sh
    │   └── data/
    └── README.md
```

**config.json:**

```json
{
    "problem_id": "sql-optimization",
    "problem_name": "Database Query Optimization Challenge",
    "project_type": "database",
    "containers": [
        {
            "container_id": "database",
            "accepts_submission": false,
            "dockerfile_path": "database/Dockerfile"
        },
        {
            "container_id": "submission",
            "accepts_submission": true,
            "dockerfile_path": "submission/Dockerfile",
            "depends_on": [
                { "container_id": "database", "condition": "healthy" }
            ]
        }
    ],
    "rubrics": [
        {
            "rubric_id": "correctness",
            "name": "Query Correctness",
            "type": "test_cases",
            "max_score": 50
        },
        {
            "rubric_id": "query_latency",
            "name": "Performance",
            "type": "performance_benchmark",
            "max_score": 30
        },
        {
            "rubric_id": "resource_efficiency",
            "name": "Storage",
            "type": "resource_usage",
            "max_score": 10
        }
    ]
}
```

### Example 2: Node.js API

**Package Contents:**

```
nodejs-api.zip
└── rest-api-users/
    ├── config.json
    ├── submission/
    │   ├── Dockerfile (Node.js 18)
    │   ├── stage1.config.json
    │   ├── stage2.config.json
    │   └── hooks/
    │       └── post/
    │           ├── 01_security_scan.sh
    │           └── 02_code_quality.sh
    ├── api-tester/
    │   ├── Dockerfile (Node.js 18 + Jest)
    │   ├── stage1.config.json
    │   ├── stage2.config.json
    │   ├── hooks/
    │   │   └── post/
    │   │       ├── 01_test_endpoints.sh
    │   │       └── 02_performance_test.sh
    │   └── data/
    │       └── test_cases.json
    ├── database/
    │   ├── Dockerfile (PostgreSQL 15)
    │   ├── stage1.config.json
    │   └── data/
    │       ├── init.sql
    │       └── seed.sql
    └── README.md
```

**config.json:**

```json
{
    "problem_id": "rest-api-users",
    "problem_name": "RESTful API Development",
    "project_type": "nodejs-api",
    "containers": [
        {
            "container_id": "database",
            "accepts_submission": false,
            "dockerfile_path": "database/Dockerfile"
        },
        {
            "container_id": "submission",
            "accepts_submission": true,
            "dockerfile_path": "submission/Dockerfile",
            "depends_on": [
                { "container_id": "database", "condition": "healthy" }
            ]
        },
        {
            "container_id": "api-tester",
            "accepts_submission": false,
            "dockerfile_path": "api-tester/Dockerfile",
            "depends_on": [
                { "container_id": "submission", "condition": "healthy" }
            ]
        }
    ],
    "rubrics": [
        {
            "rubric_id": "api_correctness",
            "name": "API Correctness",
            "max_score": 30
        },
        { "rubric_id": "security", "name": "Security", "max_score": 20 },
        { "rubric_id": "performance", "name": "Performance", "max_score": 20 },
        { "rubric_id": "code_quality", "name": "Code Quality", "max_score": 20 }
    ]
}
```

---

## Troubleshooting

### Problem: Button Not Visible

**Symptoms:**

-   "Add problem package" button missing
-   Only see "Add new problem" and "Import problem"

**Solutions:**

1. Verify you're logged in as admin
2. Check permissions: `ROLE_ADMIN` required
3. Clear browser cache and refresh

---

### Problem: Upload Page Shows 404

**Symptoms:**

-   Click button → "Page Not Found"

**Solutions:**

```bash
# Clear Symfony cache
docker compose exec domjudge bash -c "cd webapp && php bin/console cache:clear"

# Verify route exists
docker compose exec domjudge bash -c "cd webapp && php bin/console debug:router jury_problem_add_package"
```

---

### Problem: Upload Fails with "No Active Contest"

**Symptoms:**

-   Error: "No active contest selected"

**Solutions:**

1. Create a new contest: `/jury/contests`
2. Activate contest: Click "Make active" button
3. Try upload again

---

### Problem: Custom Problem Not Registered

**Symptoms:**

-   Upload succeeds but shows "Custom Judgehost: Pending"
-   No registration confirmation

**Possible Causes:**

1. Custom judgehost not running
2. Custom judgehost URL not configured
3. Network connectivity issue

**Solutions:**

```bash
# Check configuration
docker compose exec mariadb mariadb -u domjudge -pdomjudge domjudge -e "
SELECT name, value FROM configuration WHERE name LIKE 'custom_judgehost%';
"

# Expected output:
# custom_judgehost_enabled: 1
# custom_judgehost_url: http://custom-judgehost:8000
# custom_judgehost_api_key: (your key)
# custom_judgehost_timeout: 300

# If empty, configure:
# 1. Enable: UPDATE configuration SET value='1' WHERE name='custom_judgehost_enabled';
# 2. Set URL: UPDATE configuration SET value='http://...' WHERE name='custom_judgehost_url';
# 3. Set key: UPDATE configuration SET value='your-key' WHERE name='custom_judgehost_api_key';
```

---

### Problem: File Upload Fails

**Symptoms:**

-   "Please upload a valid ZIP file"
-   Upload doesn't proceed

**Solutions:**

1. Verify file is valid ZIP: `unzip -t yourfile.zip`
2. Check file size: `ls -lh yourfile.zip` (must be < 100MB)
3. Ensure file extension is `.zip`
4. Try re-creating ZIP: `zip -r newfile.zip problem-folder/`

---

## Advanced Usage

### Upload Multiple Problems

After successful upload, click **"Upload Another Package"** to:

-   Stay on upload page
-   Upload additional problems
-   Batch import multiple custom problems

### Update Existing Problem

To update a problem with new package:

1. Upload new package with **same External ID**
2. System will update existing problem
3. Previous version preserved in history

### Test Custom Judgehost Integration

Before uploading production problems:

1. Upload test package (e.g., `/tmp/test-custom-problem.zip`)
2. Verify registration: Check "Custom Judgehost: Registered"
3. View problem details: Click "View Problem Details"
4. Submit test solution to verify evaluation

---

## Tips & Best Practices

### ✅ Do:

-   Test packages locally before upload
-   Use descriptive problem names
-   Include clear problem descriptions in problem.md
-   Document rubric criteria clearly
-   Set appropriate time limits
-   Version your problem packages (use External ID)

### ❌ Don't:

-   Upload packages larger than 100MB (split if needed)
-   Use special characters in filenames
-   Leave config.json empty or invalid
-   Forget to test Docker images locally
-   Skip documentation in problem.md

---

## Quick Command Reference

### Create Test Package

```bash
cd /tmp/test-custom-problem
zip -r ../test-custom-problem.zip *
```

### Verify Package Structure

```bash
unzip -l /tmp/test-custom-problem.zip
```

### Check Upload in Database

```sql
SELECT probid, name, externalid, is_custom_problem, project_type
FROM problem
WHERE is_custom_problem = 1
ORDER BY probid DESC
LIMIT 5;
```

### View Problem Details

```bash
# After upload, note the Problem ID (e.g., #42)
# Visit: http://localhost:12345/jury/problems/42
```

---

## Feature Summary

### What This Feature Does:

✅ Provides web interface for problem package upload  
✅ Automatically detects custom vs standard problems  
✅ Registers custom problems with custom judgehost  
✅ Shows detailed upload status and results  
✅ Supports optional field overrides  
✅ Comprehensive error handling and reporting

### What It Doesn't Do:

❌ Package validation before upload (planned)  
❌ Batch upload multiple files at once (planned)  
❌ Package editing after upload (use update)  
❌ Custom judgehost configuration (use settings page)

---

## Getting Help

### Documentation:

-   **Full Implementation:** `_tasks/PROBLEM_PACKAGE_UPLOAD_FEATURE.md`
-   **Testing Guide:** `_tasks/TESTING_GUIDE.md`
-   **Deployment Guide:** `_tasks/DEPLOYMENT_GUIDE.md`
-   **API Documentation:** `_tasks/API_DOCUMENTATION.md`

### Support:

-   Check application logs: `docker compose logs -f domserver`
-   Review error messages in red error box
-   Verify configuration in database
-   Test with sample package first

---

**Happy Problem Uploading!** 🎉

This feature makes it easy to add custom problems to DOMjudge without command-line access. Upload your packages, let the system detect and register them automatically, and start evaluating custom submissions!

---

**Last Updated:** October 15, 2025  
**Feature Version:** 1.0.0  
**Compatible With:** DOMjudge Contributor Build + Custom Judgehost Integration v1.0

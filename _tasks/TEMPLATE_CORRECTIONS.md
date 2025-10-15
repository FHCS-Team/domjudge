# Problem Package Upload Template - Corrections Applied

**Date:** October 15, 2025  
**Status:** ✅ Corrected based on latest documentation and sample packages

---

## Summary of Changes

The initial implementation of the problem package upload template was based on outdated/incorrect assumptions about the package structure. After reviewing the actual documentation in `_tasks/docs/` and the working sample packages in `_tasks/sample_packages/`, the following corrections were made:

---

## 1. Package Structure - CORRECTED

### ❌ OLD (Incorrect)

```
my-problem.zip
├── config.json
├── Dockerfile.base
├── Dockerfile.evaluator
├── problem.yaml
├── problem.md
└── scripts/
    ├── setup.sh
    ├── execute.py
    └── evaluate.py
```

### ✅ NEW (Correct)

```
problem-package.zip
└── problem-id/
    ├── config.json              # Global configuration
    │
    ├── database/                # Container 1
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
    ├── submission/              # Container 2
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
    └── README.md
```

**Key Differences:**

-   ✅ Multi-container architecture with separate directories per container
-   ✅ Stage configurations (stage1.config.json, stage2.config.json) per container
-   ✅ Organized hooks directory structure (pre/, post/, periodic/)
-   ✅ Container-specific data directories
-   ❌ No single Dockerfile.base or Dockerfile.evaluator
-   ❌ No generic scripts/ directory

---

## 2. config.json Schema - CORRECTED

### ❌ OLD (Incorrect)

```json
{
    "project_type": "database-optimization",
    "name": "Database Query Optimization",
    "rubric": [
        {
            "name": "Performance",
            "max_score": 1.0,
            "weight": 0.4
        }
    ],
    "resources": {
        "memory_limit": "2G",
        "timeout": 120
    }
}
```

### ✅ NEW (Correct)

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
                    "timeout": 60,
                    "retry": 10,
                    "retry_interval": 3
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

**Key Differences:**

-   ✅ Required `containers` array defining all containers with dependencies
-   ✅ Container dependencies with health check conditions
-   ✅ `accepts_submission` flag to identify which container receives student code
-   ✅ `dockerfile_path` pointing to container-specific Dockerfiles
-   ✅ Rubrics with `rubric_id`, `type`, `max_score`, and `container` mapping
-   ❌ No generic `resources` object
-   ❌ No simple `rubric` array with weights

---

## 3. Stage Configuration - NEW CONCEPT

The corrected implementation introduces the concept of **two-stage execution**:

### Stage 1: Problem Image Build

**File:** `stage1.config.json` (per container)

```json
{
    "container_id": "database",
    "accepts_submission": false,
    "network": {
        "enabled": false,
        "internal_only": false
    },
    "resource_limits": {
        "cpu": "2.0",
        "memory": "2G",
        "timeout": 120
    },
    "environment": {
        "POSTGRES_DB": "hackathon_db",
        "POSTGRES_USER": "judge",
        "POSTGRES_PASSWORD": "judgepass"
    }
}
```

**Purpose:**

-   Build problem environment
-   Install dependencies
-   Network disabled for security
-   Prepare base images

### Stage 2: Submission Evaluation

**File:** `stage2.config.json` (per container)

```json
{
    "container_id": "database",
    "accepts_submission": false,
    "network": {
        "enabled": true,
        "internal_only": true,
        "network_name": "sql-optimization-{{submission_id}}",
        "allowed_containers": ["submission"]
    },
    "resource_limits": {
        "cpu": "2.0",
        "memory": "2G",
        "timeout": 1200
    },
    "environment": {
        "POSTGRES_DB": "hackathon_db",
        "POSTGRES_USER": "judge",
        "POSTGRES_PASSWORD": "judgepass"
    },
    "health_check": {
        "command": "pg_isready -U judge -d hackathon_db",
        "interval": 3,
        "timeout": 2,
        "retries": 10,
        "start_period": 10
    }
}
```

**Purpose:**

-   Run with student submission
-   Internal network enabled for container communication
-   Health checks for service containers
-   Execute hooks for evaluation

---

## 4. Hooks System - CORRECTED

### ❌ OLD (Incorrect Understanding)

-   Single scripts directory
-   Generic setup/execute/evaluate scripts
-   No lifecycle management

### ✅ NEW (Correct)

**Hook Types:**

1. **Pre-execution hooks** (`hooks/pre/`)

    - Run before submission starts
    - Examples: `01_initialize.sh`, `02_migration.sh`
    - Sequential execution in lexicographic order

2. **Post-execution hooks** (`hooks/post/`)

    - Run after submission starts
    - Examples: `01_test_queries.sh`, `02_test_concurrency.sh`
    - Evaluate rubrics and write to `/out/rubric_<rubric_id>.json`

3. **Periodic hooks** (`hooks/periodic/`)
    - Run continuously during evaluation
    - Examples: `01_healthcheck.sh`
    - Monitor metrics and health

**Critical Understanding:**

-   Hooks are **executed by judgehost** using `docker exec`
-   Containers don't autonomously execute hooks
-   Judgehost orchestrates when and which hooks run
-   Hooks write evaluation results to `/out/` directory

---

## 5. Container Architecture - CORRECTED

### ❌ OLD (Incorrect)

-   Single monolithic container or unclear separation
-   Dockerfiles: Dockerfile.base, Dockerfile.evaluator

### ✅ NEW (Correct)

-   **Multi-container orchestration** with dependencies
-   Each container has its own:
    -   Dockerfile at specified `dockerfile_path`
    -   Stage configurations (stage1, stage2)
    -   Hooks directory
    -   Data directory

**Example: Database Optimization Problem**

```
Container 1: database
- Role: PostgreSQL server
- accepts_submission: false
- Provides: Database service
- Dependencies: None

Container 2: submission
- Role: Run student queries
- accepts_submission: true
- Provides: Execution environment
- Dependencies: database (condition: healthy)
- Evaluates: All rubrics via post hooks
```

---

## 6. Project Types - CORRECTED

### ❌ OLD (Incorrect)

-   `database-optimization`
-   Generic naming with hyphens

### ✅ NEW (Correct)

-   `database` (SQL optimization, database design)
-   `nodejs-api` (REST API development)
-   `python-ml` (Machine learning)
-   `react-app` (React applications)
-   `full-stack` (Full-stack web apps)
-   `custom` (Generic evaluation)

---

## 7. Rubric Structure - CORRECTED

### ❌ OLD (Incorrect)

```json
"rubric": [
    {
        "name": "Performance",
        "max_score": 1.0,
        "weight": 0.4
    }
]
```

### ✅ NEW (Correct)

```json
"rubrics": [
    {
        "rubric_id": "correctness",
        "name": "Query Result Correctness",
        "type": "test_cases",
        "max_score": 50,
        "container": "submission"
    }
]
```

**Key Differences:**

-   ✅ `rubric_id` - Unique identifier for rubric output files
-   ✅ `type` - Rubric type (test_cases, performance_benchmark, resource_usage)
-   ✅ `max_score` - Maximum points (not normalized to 1.0)
-   ✅ `container` - Which container evaluates this rubric
-   ❌ No `weight` field (calculated from max_scores)

---

## 8. Detection Logic - CLARIFIED

### Custom Problem Detection

**OLD (Incorrect):**

-   Any `config.json` → Custom problem

**NEW (Correct):**

-   `config.json` exists **AND** contains `containers` array → Custom problem
-   Traditional DOMjudge `problem.yaml` → Standard problem

---

## 9. Updated Template Features

The corrected template (`jury/problem_add_package.html.twig`) now includes:

✅ Accurate package structure documentation  
✅ Correct config.json schema with containers array  
✅ Stage configuration explanation  
✅ Hooks system documentation (pre/post/periodic)  
✅ Multi-container architecture examples  
✅ Container dependency examples with health checks  
✅ Proper rubric structure with rubric_id and container mapping  
✅ Correct project type names  
✅ Stage 1 vs Stage 2 execution model  
✅ Hook execution model (docker exec from judgehost)

---

## 10. Updated User Guide

The user guide (`PROBLEM_UPLOAD_USER_GUIDE.md`) was updated to reflect:

✅ Multi-container package structure  
✅ Stage configurations per container  
✅ Hooks organization (pre/post/periodic)  
✅ Correct config.json schema  
✅ Container dependencies and health checks  
✅ Updated examples matching sample packages

---

## Files Modified

1. **`webapp/templates/jury/problem_add_package.html.twig`**

    - Information box updated
    - Example package structure corrected
    - Sample config.json fixed
    - Documentation sections updated
    - Tips and best practices revised

2. **`_tasks/PROBLEM_UPLOAD_USER_GUIDE.md`**
    - Package structure corrected
    - config.json examples updated
    - Examples 1 & 2 rewritten with correct structure
    - Custom vs Standard detection clarified

---

## Reference Documentation

The corrections were based on:

1. **`_tasks/docs/problems/POST_problems.md`**

    - Problem package structure
    - config.json schema
    - Hooks system

2. **`_tasks/docs/data-models/containers/CONFIGURATION_GUIDE.md`**

    - Stage configurations
    - Container execution model
    - Resource limits

3. **`_tasks/docs/data-models/samples/problem_package_name.md`**

    - Complete package examples
    - Directory structure
    - Container organization

4. **`_tasks/sample_packages/db-optimization/`**
    - Working example of correct structure
    - stage1.config.json and stage2.config.json examples
    - Hooks organization
    - config.json with containers array

---

## Testing

To test with the sample package:

```bash
cd /home/vtvinh24/Desktop/Workspace/Capstone/prototypes/domjudge/_tasks/sample_packages/db-optimization

# The package is already built
unzip -l db-problem.zip

# Upload via web interface
# Navigate to: http://localhost:12345/jury/problems
# Click: "Add problem package"
# Upload: db-problem.zip
```

---

## Key Takeaways

1. **Multi-container architecture** is the core concept - not single Dockerfiles
2. **Stage configurations** separate build from evaluation
3. **Hooks are orchestrated** by judgehost via `docker exec`, not autonomous
4. **Container dependencies** enable complex setups (database + submission + tester)
5. **Rubrics map to containers** that evaluate them
6. **Package structure is hierarchical** - problem → containers → stages/hooks/data

---

**Status:** ✅ Template and documentation corrected  
**Cache:** ✅ Cleared  
**Ready for:** Testing with sample packages

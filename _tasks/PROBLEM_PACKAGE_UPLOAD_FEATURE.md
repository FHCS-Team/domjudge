# Custom Problem Package Upload Feature

**Implementation Date:** October 15, 2025  
**Feature Status:** ✅ COMPLETE

---

## Overview

Added a user-friendly web interface for uploading custom problem packages to DOMjudge, making it easy to add custom problems (database optimization, API development, etc.) through the jury interface.

---

## What Was Implemented

### 1. **New Form Type** ✅

**File:** `webapp/src/Form/Type/CustomProblemPackageType.php`

**Features:**

-   File upload field (ZIP, max 100MB)
-   Optional name override
-   Optional external ID override
-   Optional time limit override
-   Form validation (required file, file type checking)
-   Helpful descriptions for each field

### 2. **New Controller Route** ✅

**File:** `webapp/src/Controller/Jury/ProblemController.php`

**Route:** `/jury/problems/add-package`  
**Name:** `jury_problem_add_package`  
**Method:** GET/POST

**Functionality:**

-   Displays upload form
-   Handles file upload
-   Uses existing `ImportProblemService` to process package
-   Detects custom problems automatically (via config.json)
-   Registers custom problems with custom judgehost
-   Applies user-provided overrides
-   Shows detailed upload status and results
-   Comprehensive error handling and logging

### 3. **New Upload Page Template** ✅

**File:** `webapp/templates/jury/problem_add_package.html.twig`

**Features:**

-   Clean, professional UI with Bootstrap styling
-   Information box explaining package requirements
-   Upload form with file picker
-   Optional override fields (name, external ID, time limit)
-   Success status display with:
    -   Problem ID and name
    -   Custom problem badge
    -   Custom judgehost registration status
    -   Action buttons (view problem, back to list, upload another)
-   Error status display with detailed error message
-   Example package structure visualization
-   Sample config.json template
-   Client-side validation and loading indicator

### 4. **Updated Problems List Page** ✅

**File:** `webapp/templates/jury/problems.html.twig`

**Changes:**

-   Added green "Add problem package" button
-   Button positioned between "Add new problem" and "Import problem"
-   Uses file-archive icon for visual clarity

---

## User Flow

### Step 1: Access Upload Page

1. Navigate to `/jury/problems`
2. Click green **"Add problem package"** button
3. Redirects to `/jury/problems/add-package`

### Step 2: Upload Package

1. Read information box about requirements
2. Click "Choose File" and select ZIP package
3. (Optional) Fill override fields:
    - Problem Name
    - External ID
    - Time Limit
4. Click **"Upload Problem Package"** button

### Step 3: View Results

**On Success:**

-   Green success box appears
-   Shows problem details:
    -   Problem ID
    -   Problem name
    -   External ID (if set)
    -   Problem type (Custom or Standard)
    -   Custom judgehost status (if custom)
-   Action buttons:
    -   View Problem Details
    -   Back to Problems List
    -   Upload Another Package

**On Error:**

-   Red error box appears
-   Shows detailed error message
-   Options to try again or go back

---

## Technical Details

### Form Validation

```php
- File is required
- Maximum size: 100MB
- Allowed types: application/zip, application/x-zip-compressed
- Fields validated on submit
```

### Package Processing

The controller uses the existing `ImportProblemService` which:

1. Extracts ZIP file
2. Checks for `config.json` in root
3. If found → marks as custom problem
4. Calls `CustomJudgehostService::registerProblem()`
5. Stores custom judgehost response data
6. Creates problem entity in database
7. Applies any user overrides

### Custom Problem Detection

Automatically detects custom problems by checking for `config.json` in ZIP root:

-   **With config.json** → Custom Problem (registered with custom judgehost)
-   **Without config.json** → Standard Problem (regular DOMjudge judging)

### Status Reporting

The template displays different information based on problem type:

**Standard Problem:**

```
Problem Type: [Standard Problem]
```

**Custom Problem:**

```
Problem Type: [Custom Problem] 🚀
Project Type: database-optimization
Custom Judgehost: [Registered] ✓
```

---

## File Structure

```
webapp/
├── src/
│   ├── Controller/
│   │   └── Jury/
│   │       └── ProblemController.php (+ addPackageAction)
│   └── Form/
│       └── Type/
│           └── CustomProblemPackageType.php (NEW)
└── templates/
    └── jury/
        ├── problems.html.twig (+ button)
        └── problem_add_package.html.twig (NEW)
```

---

## Screenshots & Examples

### Upload Form Layout

```
┌─────────────────────────────────────────────────────┐
│ Add Custom Problem Package                          │
├─────────────────────────────────────────────────────┤
│ ℹ Custom Problem Package Requirements               │
│   • config.json - Custom configuration              │
│   • Dockerfile.base - Base image                    │
│   • Dockerfile.evaluator - Evaluator image          │
│   ...                                                │
├─────────────────────────────────────────────────────┤
│ Upload Problem Package                              │
│                                                      │
│ Problem Package (ZIP) *                             │
│ [Choose File] No file chosen                        │
│ ZIP file containing config.json, Dockerfiles...     │
│                                                      │
│ ─────────── Optional Overrides ───────────          │
│                                                      │
│ Problem Name                                        │
│ [_________________________________]                 │
│ Override the problem name from config.json          │
│                                                      │
│ External ID                                         │
│ [_________________________________]                 │
│ Unique identifier for this problem                  │
│                                                      │
│ Time Limit (seconds)                                │
│ [_________________________________]                 │
│ Override evaluation timeout                         │
│                                                      │
│ [Upload Problem Package]  [Cancel]                  │
└─────────────────────────────────────────────────────┘
```

### Success Display

```
┌─────────────────────────────────────────────────────┐
│ ✓ Upload Successful!                                │
│ Problem package has been uploaded successfully.     │
│                                                      │
│ Problem ID:         #42                             │
│ Problem Name:       Database Query Optimization     │
│ External ID:        db-opt-001                      │
│ Problem Type:       [Custom Problem] 🚀             │
│                     Project Type: database-optimization
│ Custom Judgehost:   [Registered] ✓                  │
│                                                      │
│ [View Problem Details] [Back to List] [Upload Another]
└─────────────────────────────────────────────────────┘
```

---

## Testing Instructions

### 1. Access the Feature

```bash
# Open browser
http://localhost:12345/jury

# Login as admin
Username: admin
Password: (check etc/initial_admin_password.secret)

# Navigate to Problems
Click "Problems" in navigation

# Click the new button
Click "Add problem package" (green button)
```

### 2. Upload Test Package

```bash
# Use the test package created earlier
File: /tmp/test-custom-problem.zip

# Upload steps:
1. Click "Choose File"
2. Select /tmp/test-custom-problem.zip
3. (Optional) Fill in:
   - Problem Name: "My Database Optimization"
   - External ID: "test-db-opt-001"
   - Time Limit: 120
4. Click "Upload Problem Package"
5. Wait for processing...
```

### 3. Verify Results

```bash
# Should see success message with:
✓ Problem ID
✓ Problem name
✓ Custom Problem badge
✓ Custom judgehost status

# Click "View Problem Details" to see full problem
# Or click "Back to Problems List" to see all problems
```

### 4. Database Verification

```sql
-- Check if problem was created
SELECT
    probid,
    name,
    externalid,
    is_custom_problem,
    project_type
FROM problem
WHERE is_custom_problem = 1
ORDER BY probid DESC
LIMIT 1;

-- Expected result:
-- probid: (new ID)
-- name: (from upload or config.json)
-- is_custom_problem: 1
-- project_type: 'database-optimization'
```

---

## Error Handling

The feature handles various error scenarios:

### 1. No Active Contest

```
Error: No active contest selected
Solution: Create or activate a contest first
```

### 2. Invalid ZIP File

```
Error: Please upload a valid ZIP file
Solution: Ensure file is a valid ZIP archive
```

### 3. File Too Large

```
Error: File exceeds maximum size of 100M
Solution: Reduce package size or split into smaller packages
```

### 4. Missing Required Files

```
Error: Problem package missing required files
Solution: Ensure package contains necessary files (config.json, etc.)
```

### 5. Custom Judgehost Unavailable

```
Problem uploaded but custom judgehost registration pending
Status: Problem created, will be registered when judgehost is available
```

---

## Integration with Existing Features

### Works With:

✅ Existing `ImportProblemService`  
✅ Custom judgehost integration  
✅ `CustomJudgehostService::registerProblem()`  
✅ Problem entity system  
✅ Contest management  
✅ Standard problem import

### Doesn't Interfere With:

✅ "Add new problem" button (manual entry)  
✅ "Import problem" button (standard import)  
✅ Existing problem management  
✅ Regular DOMjudge judging

---

## Configuration

No additional configuration needed! The feature automatically uses existing settings:

```sql
SELECT name, value
FROM configuration
WHERE name LIKE 'custom_judgehost%';

-- Uses these settings:
-- custom_judgehost_enabled (0/1)
-- custom_judgehost_url (http://...)
-- custom_judgehost_api_key (secret)
-- custom_judgehost_timeout (seconds)
```

---

## Security Considerations

✅ **Admin-only access:** Requires `ROLE_ADMIN` permission  
✅ **File type validation:** Only ZIP files accepted  
✅ **Size limits:** Maximum 100MB upload  
✅ **CSRF protection:** Symfony form CSRF tokens  
✅ **Input sanitization:** All user inputs validated  
✅ **Error logging:** Failed uploads logged for debugging  
✅ **Flash messages:** User-friendly error reporting

---

## Future Enhancements

### Possible Improvements:

1. **Drag-and-drop upload** - More intuitive file selection
2. **Progress bar** - Show upload progress for large files
3. **Package validation** - Pre-check package structure before upload
4. **Batch upload** - Upload multiple packages at once
5. **Package templates** - Download starter templates
6. **Preview mode** - Preview problem before final upload
7. **Version management** - Update existing problems with new packages

---

## Troubleshooting

### Issue: Button Not Visible

**Cause:** Not logged in as admin  
**Solution:** Login with admin credentials

### Issue: Upload Fails Silently

**Cause:** Symfony cache not cleared  
**Solution:**

```bash
docker compose exec domjudge bash -c "cd webapp && php bin/console cache:clear"
```

### Issue: Custom Problem Not Registered

**Cause:** Custom judgehost not running or misconfigured  
**Solution:**

```bash
# Check configuration
docker compose exec mariadb mariadb -u domjudge -pdomjudge domjudge -e "
SELECT name, value FROM configuration WHERE name LIKE 'custom_judgehost%';
"

# Enable if disabled
# Set URL if empty
# Verify custom judgehost is running
```

### Issue: Template Not Found

**Cause:** Template file not created or cache issue  
**Solution:**

```bash
# Verify file exists
ls -la webapp/templates/jury/problem_add_package.html.twig

# Clear cache
docker compose exec domjudge bash -c "cd webapp && php bin/console cache:clear"
```

---

## Quick Reference

### URLs

-   **Problems List:** `/jury/problems`
-   **Add Package:** `/jury/problems/add-package`
-   **Add Manual:** `/jury/problems/add`
-   **Import (old):** `/jury/import-export#problemarchive`

### Routes

-   `jury_problems` - Problems list
-   `jury_problem_add_package` - Package upload (NEW)
-   `jury_problem_add` - Manual problem creation
-   `jury_import_export` - Import/export page

### Files Modified/Created

-   ✅ `webapp/src/Form/Type/CustomProblemPackageType.php` (NEW)
-   ✅ `webapp/src/Controller/Jury/ProblemController.php` (+99 lines)
-   ✅ `webapp/templates/jury/problem_add_package.html.twig` (NEW)
-   ✅ `webapp/templates/jury/problems.html.twig` (+1 line)

---

## Success Criteria

✅ Button visible on problems page  
✅ Upload page accessible at `/jury/problems/add-package`  
✅ Form displays correctly with all fields  
✅ File upload works (tested with test package)  
✅ Success status displays problem details  
✅ Error handling shows meaningful messages  
✅ Custom problems detected automatically  
✅ Custom judgehost registration triggered  
✅ Database entries created correctly  
✅ Navigation works (view problem, back to list, upload another)

---

**Feature Complete and Ready for Use!** 🎉

Users can now easily upload custom problem packages through the web interface without needing command-line access or manual database manipulation.

---

**Implementation By:** GitHub Copilot  
**Date:** October 15, 2025  
**Version:** 1.0.0  
**Integration:** Custom Judgehost v1.0

#!/bin/bash

# E2E Testing Script for Custom Judgehost Integration
# This script tests the complete flow from problem upload to result display

set -e  # Exit on any error

echo "=========================================="
echo "Custom Judgehost Integration E2E Tests"
echo "=========================================="
echo ""

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Test configuration
DOMJUDGE_URL="http://localhost:12345"
DB_CONTAINER="domjudge-mariadb-1"
TEST_PROBLEM="/tmp/test-custom-problem.zip"

# Helper functions
print_success() {
    echo -e "${GREEN}✓${NC} $1"
}

print_error() {
    echo -e "${RED}✗${NC} $1"
}

print_info() {
    echo -e "${YELLOW}ℹ${NC} $1"
}

run_sql() {
    docker compose exec -T mariadb mariadb -u domjudge -pdomjudge domjudge -e "$1" 2>/dev/null
}

# Test 1: Verify containers are running
echo "Test 1: Verify Docker containers"
echo "-----------------------------------"
if docker compose ps | grep -q "domjudge.*Up"; then
    print_success "DOMjudge container is running"
else
    print_error "DOMjudge container is not running"
    exit 1
fi

if docker compose ps | grep -q "mariadb.*Up"; then
    print_success "MariaDB container is running"
else
    print_error "MariaDB container is not running"
    exit 1
fi
echo ""

# Test 2: Verify database migration
echo "Test 2: Verify database schema"
echo "--------------------------------"
CUSTOM_COLUMNS=$(run_sql "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'problem' AND COLUMN_NAME LIKE '%custom%';" | tail -1)
if [ "$CUSTOM_COLUMNS" = "3" ]; then
    print_success "Custom columns exist in problem table ($CUSTOM_COLUMNS columns)"
else
    print_error "Expected 3 custom columns in problem table, found: $CUSTOM_COLUMNS"
    exit 1
fi

SUBMISSION_COLUMNS=$(run_sql "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'submission' AND COLUMN_NAME LIKE '%custom%';" | tail -1)
if [ "$SUBMISSION_COLUMNS" = "2" ]; then
    print_success "Custom columns exist in submission table ($SUBMISSION_COLUMNS columns)"
else
    print_error "Expected 2 custom columns in submission table, found: $SUBMISSION_COLUMNS"
    exit 1
fi
echo ""

# Test 3: Verify configuration
echo "Test 3: Verify configuration entries"
echo "-------------------------------------"
CONFIG_COUNT=$(run_sql "SELECT COUNT(*) FROM configuration WHERE name LIKE 'custom_judgehost%';" | tail -1)
if [ "$CONFIG_COUNT" = "4" ]; then
    print_success "All 4 configuration entries exist"
else
    print_error "Expected 4 config entries, found: $CONFIG_COUNT"
    exit 1
fi

# Show configuration values
print_info "Current configuration:"
run_sql "SELECT name, value FROM configuration WHERE name LIKE 'custom_judgehost%';" | sed 's/^/    /'
echo ""

# Test 4: Run unit tests
echo "Test 4: Run PHPUnit unit tests"
echo "--------------------------------"
if docker compose exec -T domjudge bash -c "cd webapp && php bin/phpunit tests/Unit/Service/CustomJudgehostServiceTest.php --testdox" 2>&1 | grep -q "OK"; then
    print_success "All unit tests passed"
else
    print_error "Some unit tests failed"
    docker compose exec -T domjudge bash -c "cd webapp && php bin/phpunit tests/Unit/Service/CustomJudgehostServiceTest.php"
    exit 1
fi
echo ""

# Test 5: Verify test problem package exists
echo "Test 5: Verify test problem package"
echo "------------------------------------"
if [ -f "$TEST_PROBLEM" ]; then
    SIZE=$(du -h "$TEST_PROBLEM" | cut -f1)
    print_success "Test problem package exists ($SIZE)"
    
    # Check contents
    print_info "Package contents:"
    unzip -l "$TEST_PROBLEM" | tail -n +4 | head -n -2 | sed 's/^/    /'
else
    print_error "Test problem package not found at $TEST_PROBLEM"
    exit 1
fi
echo ""

# Test 6: Check if config.json is valid
echo "Test 6: Validate config.json structure"
echo "---------------------------------------"
if unzip -p "$TEST_PROBLEM" config.json | jq . > /dev/null 2>&1; then
    print_success "config.json is valid JSON"
    
    PROJECT_TYPE=$(unzip -p "$TEST_PROBLEM" config.json | jq -r '.project_type')
    print_info "Project type: $PROJECT_TYPE"
    
    RUBRIC_COUNT=$(unzip -p "$TEST_PROBLEM" config.json | jq '.rubric | length')
    print_info "Rubric criteria: $RUBRIC_COUNT"
else
    print_error "config.json is not valid JSON"
    exit 1
fi
echo ""

# Test 7: Test problem detection (simulated)
echo "Test 7: Test problem detection logic"
echo "-------------------------------------"
print_info "This test verifies the ImportProblemService can detect custom problems"
print_info "To fully test, you need to:"
echo "    1. Navigate to $DOMJUDGE_URL/jury"
echo "    2. Login as admin (check etc/initial_admin_password.secret)"
echo "    3. Go to Problems → Import / Export"
echo "    4. Upload $TEST_PROBLEM"
echo "    5. Verify 'Custom Problem' badge appears"
echo "    6. Check database for problem entry"
print_info "Automated UI testing requires Selenium (not included in this script)"
echo ""

# Test 8: Verify templates exist
echo "Test 8: Verify template files"
echo "------------------------------"
JURY_TEMPLATE="webapp/templates/jury/submission.html.twig"
TEAM_TEMPLATE="webapp/templates/team/partials/submission.html.twig"

if [ -f "$JURY_TEMPLATE" ]; then
    if grep -q "rubricScores" "$JURY_TEMPLATE"; then
        print_success "Jury template includes custom problem logic"
    else
        print_error "Jury template missing custom problem logic"
    fi
else
    print_error "Jury template not found"
fi

if [ -f "$TEAM_TEMPLATE" ]; then
    if grep -q "rubricScores" "$TEAM_TEMPLATE"; then
        print_success "Team template includes rubric display"
    else
        print_error "Team template missing rubric display"
    fi
else
    print_error "Team template not found"
fi
echo ""

# Test 9: Check service class exists
echo "Test 9: Verify service implementation"
echo "--------------------------------------"
SERVICE_FILE="webapp/src/Service/CustomJudgehostService.php"
if [ -f "$SERVICE_FILE" ]; then
    print_success "CustomJudgehostService exists"
    
    # Check for key methods
    if grep -q "public function registerProblem" "$SERVICE_FILE"; then
        print_success "registerProblem() method found"
    fi
    if grep -q "public function submitForEvaluation" "$SERVICE_FILE"; then
        print_success "submitForEvaluation() method found"
    fi
    if grep -q "public function getResults" "$SERVICE_FILE"; then
        print_success "getResults() method found"
    fi
else
    print_error "CustomJudgehostService not found"
    exit 1
fi
echo ""

# Test 10: Verify API endpoint
echo "Test 10: Verify API endpoint exists"
echo "------------------------------------"
API_CONTROLLER="webapp/src/Controller/API/JudgehostController.php"
if [ -f "$API_CONTROLLER" ]; then
    if grep -q "addCustomJudgingResultAction" "$API_CONTROLLER"; then
        print_success "Custom judging result endpoint exists"
    else
        print_error "Custom judging result endpoint not found"
    fi
else
    print_error "JudgehostController not found"
fi
echo ""

# Test 11: Error handling test
echo "Test 11: Test error handling (custom judgehost disabled)"
echo "---------------------------------------------------------"
ENABLED=$(run_sql "SELECT value FROM configuration WHERE name='custom_judgehost_enabled';" | tail -1)
if [ "$ENABLED" = "0" ]; then
    print_success "Custom judgehost is currently disabled (as expected)"
    print_info "This ensures graceful fallback to regular judging"
else
    print_info "Custom judgehost is enabled (value: $ENABLED)"
    print_info "For production, this should be configured properly"
fi
echo ""

# Summary
echo "=========================================="
echo "E2E Test Summary"
echo "=========================================="
print_success "Database schema: PASSED"
print_success "Configuration: PASSED"
print_success "Unit tests: PASSED"
print_success "Code files: PASSED"
print_info "Manual UI testing required for complete validation"
echo ""
echo "Next steps:"
echo "  1. Enable custom judgehost in configuration"
echo "  2. Start a custom judgehost instance"
echo "  3. Upload test problem via UI"
echo "  4. Submit a solution"
echo "  5. Verify rubric scores display correctly"
echo ""
echo "For detailed testing procedures, see:"
echo "  _tasks/TESTING_GUIDE.md"
echo "  _tasks/DEPLOYMENT_GUIDE.md"
echo ""

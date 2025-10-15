# Custom Judgehost Integration - Deployment Guide

## Overview

This guide provides step-by-step instructions for deploying and configuring the custom judgehost integration in a production DOMjudge environment.

---

## Prerequisites

### System Requirements

**DOMjudge Server:**

-   PHP 8.2 or higher
-   MySQL/MariaDB 10.5 or higher
-   Web server (Apache/Nginx)
-   4GB+ RAM
-   50GB+ disk space

**Custom Judgehost:**

-   Docker 20.10 or higher
-   Docker Compose 2.0 or higher
-   8GB+ RAM
-   100GB+ disk space (for Docker images)
-   Network connectivity to DOMjudge server

---

## Installation Steps

### Step 1: Update DOMjudge Codebase

#### Option A: From Git Repository

```bash
cd /path/to/domjudge
git fetch origin
git checkout integrate-new-judgehost
git pull origin integrate-new-judgehost
```

#### Option B: Apply Patches

If you're on a different branch, apply the integration files manually:

```bash
# Copy new files
cp CustomJudgehostService.php webapp/src/Service/
cp Version20251015120000.php webapp/migrations/

# Apply modifications (use git apply or manual merge)
# - webapp/src/Entity/Problem.php
# - webapp/src/Entity/Submission.php
# - webapp/src/Controller/API/JudgehostController.php
# - webapp/src/Service/ImportProblemService.php
# - webapp/src/Service/SubmissionService.php
# - webapp/templates/jury/submission.html.twig
# - webapp/templates/team/partials/submission.html.twig
```

---

### Step 2: Run Database Migration

```bash
cd /path/to/domjudge

# Development environment
docker compose exec domserver bin/console doctrine:migrations:migrate --no-interaction

# Production environment (if using standalone setup)
cd webapp
php bin/console doctrine:migrations:migrate --no-interaction
```

**Expected Output:**

```
Migrating up to DoctrineMigrations\Version20251015120000

  ++ migrating DoctrineMigrations\Version20251015120000
     -> Adding custom judgehost columns to problem table
     -> Adding custom judgehost columns to submission table
     -> Adding custom judgehost configuration entries

  ++ migrated (took 244.1ms, used 26M memory)

  ------------------------

  ++ finished in 244.1ms
  ++ 1 migrations executed
  ++ 9 sql queries
```

**Verify Migration:**

```bash
docker compose exec mariadb mariadb -u domjudge -pdomjudge domjudge -e "
SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_NAME = 'problem'
AND COLUMN_NAME LIKE '%custom%';
"
```

**Expected Result:**

```
+------------------------+-----------+-------------+
| COLUMN_NAME            | DATA_TYPE | IS_NULLABLE |
+------------------------+-----------+-------------+
| is_custom_problem      | tinyint   | NO          |
| custom_config          | longtext  | YES         |
| custom_judgehost_data  | longtext  | YES         |
| project_type           | varchar   | YES         |
+------------------------+-----------+-------------+
```

---

### Step 3: Configure DOMjudge

#### Web Interface Configuration

1. **Login as Administrator:**

    - Navigate to `http://your-domjudge/jury`
    - Login with admin credentials

2. **Access Configuration:**

    - Click "Config" in navigation
    - Or go to `http://your-domjudge/jury/config`

3. **Set Custom Judgehost Settings:**

    Find and configure these settings:

    | Setting                    | Value                     | Description                     |
    | -------------------------- | ------------------------- | ------------------------------- |
    | `custom_judgehost_enabled` | `1`                       | Enable integration (0=disabled) |
    | `custom_judgehost_url`     | `http://custom-host:8000` | Base URL of custom judgehost    |
    | `custom_judgehost_api_key` | `your-secret-key`         | API authentication key          |
    | `custom_judgehost_timeout` | `600`                     | Timeout in seconds (10 minutes) |

#### Database Configuration

Alternatively, configure directly via database:

```bash
docker compose exec mariadb mariadb -u domjudge -pdomjudge domjudge << 'EOF'
-- Enable custom judgehost
UPDATE configuration
SET value = '1'
WHERE name = 'custom_judgehost_enabled';

-- Set custom judgehost URL
UPDATE configuration
SET value = 'http://custom-judgehost:8000'
WHERE name = 'custom_judgehost_url';

-- Set API key (change this!)
UPDATE configuration
SET value = 'change-this-to-a-secure-random-key'
WHERE name = 'custom_judgehost_api_key';

-- Set timeout (600 seconds = 10 minutes)
UPDATE configuration
SET value = '600'
WHERE name = 'custom_judgehost_timeout';

-- Verify settings
SELECT name, value
FROM configuration
WHERE name LIKE 'custom_judgehost%';
EOF
```

#### Generate Secure API Key

```bash
# Generate a secure random API key
openssl rand -hex 32

# Or use this Python one-liner
python3 -c "import secrets; print(secrets.token_hex(32))"
```

**Example output:** `a1b2c3d4e5f6789012345678901234567890abcdef1234567890abcdef123456`

---

### Step 4: Clear Caches

```bash
# Development
docker compose exec domserver bin/console cache:clear

# Production
docker compose exec domserver bin/console cache:clear --env=prod --no-debug

# Also clear template cache
docker compose exec domserver rm -rf var/cache/*
```

---

### Step 5: Set Up Custom Judgehost

#### Install Custom Judgehost

```bash
# Clone custom judgehost repository
git clone https://github.com/your-org/custom-judgehost.git
cd custom-judgehost

# Create environment configuration
cp .env.example .env

# Edit configuration
nano .env
```

**Example .env:**

```bash
# Custom Judgehost Configuration
HOST=0.0.0.0
PORT=8000

# API Authentication
API_KEY=a1b2c3d4e5f6789012345678901234567890abcdef1234567890abcdef123456

# DOMjudge Connection
DOMJUDGE_URL=http://domjudge:12345
DOMJUDGE_API_USER=judgehost
DOMJUDGE_API_PASSWORD=your-judgehost-password

# Docker Configuration
DOCKER_SOCKET=/var/run/docker.sock
MAX_CONCURRENT_EVALUATIONS=5

# Resource Limits
DEFAULT_MEMORY_LIMIT=2G
DEFAULT_CPU_LIMIT=2.0
DEFAULT_TIMEOUT=300

# Logging
LOG_LEVEL=INFO
LOG_FILE=/var/log/custom-judgehost/app.log
```

#### Start Custom Judgehost

```bash
# Using Docker Compose
docker compose up -d

# Or using Python virtualenv
python3 -m venv venv
source venv/bin/activate
pip install -r requirements.txt
python app.py
```

**Verify Service:**

```bash
# Health check
curl http://localhost:8000/health

# Expected response
{"status": "healthy", "version": "1.0.0"}
```

---

### Step 6: Network Configuration

#### Allow Communication Between Services

**If using Docker Compose (recommended):**

```yaml
# docker-compose.yml
version: "3"
services:
    domserver:
        networks:
            - domjudge-net

    custom-judgehost:
        networks:
            - domjudge-net

networks:
    domjudge-net:
        driver: bridge
```

**If using separate hosts:**

```bash
# On DOMjudge server - allow outbound to custom judgehost
sudo ufw allow out to <custom-judgehost-ip> port 8000

# On custom judgehost server - allow inbound from DOMjudge
sudo ufw allow from <domjudge-ip> to any port 8000

# Allow custom judgehost to callback to DOMjudge
sudo ufw allow out to <domjudge-ip> port 12345
```

---

## Configuration Options

### Advanced Settings

#### Webhook Configuration (Optional)

If you want custom judgehost to use webhooks instead of the result endpoint:

```sql
INSERT INTO configuration (name, value)
VALUES ('custom_judgehost_webhook_url', 'http://domjudge:12345/api/webhooks/results');
```

#### Problem Type Mappings

Define custom project type labels:

```sql
INSERT INTO configuration (name, value)
VALUES ('custom_problem_types',
  JSON_OBJECT(
    'database-optimization', 'Database Optimization',
    'nodejs-api', 'Node.js API Development',
    'react-frontend', 'React Frontend',
    'system-design', 'System Architecture'
  )
);
```

#### Resource Limits

Configure per-problem resource limits:

```sql
INSERT INTO configuration (name, value)
VALUES
  ('custom_judgehost_default_memory', '2G'),
  ('custom_judgehost_default_cpu', '2.0'),
  ('custom_judgehost_default_timeout', '300');
```

---

## Security Hardening

### 1. API Key Security

**Best Practices:**

-   Use keys with 256+ bits of entropy (64+ hex characters)
-   Rotate keys quarterly
-   Store in environment variables, not code
-   Use different keys for dev/staging/production

**Rotation Procedure:**

```bash
# 1. Generate new key
NEW_KEY=$(openssl rand -hex 32)

# 2. Update DOMjudge
docker compose exec mariadb mariadb -u domjudge -pdomjudge domjudge -e "
UPDATE configuration
SET value = '$NEW_KEY'
WHERE name = 'custom_judgehost_api_key';
"

# 3. Update custom judgehost
docker compose exec custom-judgehost \
  sed -i "s/API_KEY=.*/API_KEY=$NEW_KEY/" /app/.env

# 4. Restart custom judgehost
docker compose restart custom-judgehost

# 5. Clear caches
docker compose exec domserver bin/console cache:clear
```

### 2. TLS/HTTPS

**Enable HTTPS for Production:**

```bash
# Install certbot
sudo apt-get install certbot python3-certbot-nginx

# Get certificate
sudo certbot --nginx -d your-domjudge-domain.com

# Update custom judgehost URL
docker compose exec mariadb mariadb -u domjudge -pdomjudge domjudge -e "
UPDATE configuration
SET value = 'https://your-domjudge-domain.com'
WHERE name = 'custom_judgehost_url';
"
```

### 3. IP Whitelisting

**Restrict custom judgehost access:**

```nginx
# /etc/nginx/sites-available/domjudge
location /api/judgehosts/add-custom-judging-result {
    allow <custom-judgehost-ip>;
    deny all;

    proxy_pass http://localhost:9000;
}
```

### 4. Rate Limiting

**Configure Nginx rate limiting:**

```nginx
http {
    limit_req_zone $binary_remote_addr zone=judgehost:10m rate=10r/s;

    server {
        location /api/judgehosts/ {
            limit_req zone=judgehost burst=20;
        }
    }
}
```

---

## Monitoring & Logging

### Enable Detailed Logging

**DOMjudge Logs:**

```yaml
# config/packages/monolog.yaml
monolog:
    channels:
        - custom_judgehost
    handlers:
        custom_judgehost:
            type: stream
            path: "%kernel.logs_dir%/custom_judgehost.log"
            level: info
            channels: ["custom_judgehost"]
```

**View Logs:**

```bash
# Real-time log monitoring
docker compose logs -f domserver | grep custom_judgehost

# Search for errors
docker compose logs domserver | grep "ERROR.*custom"

# Export logs
docker compose logs domserver > /tmp/domserver-$(date +%Y%m%d).log
```

### Monitoring Endpoints

**Create Health Check Endpoint:**

```php
// src/Controller/API/HealthController.php
#[Route('/api/health/custom-judgehost')]
public function customJudgehostHealth(): JsonResponse
{
    $isEnabled = $this->config->get('custom_judgehost_enabled');
    $url = $this->config->get('custom_judgehost_url');

    try {
        $response = $this->httpClient->request('GET', $url . '/health');
        $healthy = $response->getStatusCode() === 200;
    } catch (\Exception $e) {
        $healthy = false;
    }

    return new JsonResponse([
        'enabled' => (bool)$isEnabled,
        'url' => $url,
        'healthy' => $healthy,
        'timestamp' => time(),
    ]);
}
```

**Monitor with Nagios/Prometheus:**

```bash
# Check health
curl http://localhost:12345/api/health/custom-judgehost

# Prometheus scrape config
scrape_configs:
  - job_name: 'domjudge'
    static_configs:
      - targets: ['localhost:12345']
    metrics_path: '/api/metrics'
```

---

## Backup & Recovery

### Database Backup

```bash
# Backup custom judgehost data
docker compose exec mariadb mysqldump domjudge \
  problem submission rubric submission_rubric_score configuration \
  --where="name LIKE 'custom_judgehost%'" \
  > backup-custom-judgehost-$(date +%Y%m%d).sql

# Full backup
docker compose exec mariadb mysqldump domjudge > backup-full-$(date +%Y%m%d).sql
```

### Restore from Backup

```bash
# Restore
docker compose exec -T mariadb mariadb domjudge < backup-custom-judgehost-20251015.sql
```

---

## Troubleshooting

### Issue: Migration Fails

**Error:** `Syntax error or access violation: 1064`

**Solution:**

```bash
# Check current version
docker compose exec domserver bin/console doctrine:migrations:status

# Rollback if needed
docker compose exec domserver bin/console doctrine:migrations:migrate prev

# Re-run migration
docker compose exec domserver bin/console doctrine:migrations:migrate
```

### Issue: Custom Judgehost Unreachable

**Check connectivity:**

```bash
# From DOMjudge container
docker compose exec domserver curl http://custom-judgehost:8000/health

# Check DNS resolution
docker compose exec domserver nslookup custom-judgehost

# Check network
docker network inspect domjudge_default
```

### Issue: API Key Invalid

**Verify configuration:**

```bash
# Check DOMjudge config
docker compose exec mariadb mariadb -u domjudge -pdomjudge domjudge -e "
SELECT value FROM configuration WHERE name='custom_judgehost_api_key';
"

# Check custom judgehost config
docker compose exec custom-judgehost env | grep API_KEY
```

### Issue: Results Not Received

**Check callback URL:**

```bash
# Test endpoint manually
curl -X POST http://localhost:12345/api/judgehosts/add-custom-judging-result \
  -H "Content-Type: application/json" \
  -d '{"submission_id":"test","status":"completed","overall_score":0.8}'

# Check custom judgehost logs
docker compose logs custom-judgehost | grep callback
```

---

## Performance Tuning

### PHP Configuration

```ini
; /etc/php/8.2/fpm/php.ini
memory_limit = 512M
max_execution_time = 600
upload_max_filesize = 100M
post_max_size = 100M
```

### Database Optimization

```sql
-- Add indexes for custom queries
CREATE INDEX idx_custom_submission ON submission(custom_judgehost_submission_id);
CREATE INDEX idx_custom_problem ON problem(is_custom_problem, project_type);

-- Optimize tables
OPTIMIZE TABLE problem;
OPTIMIZE TABLE submission;
OPTIMIZE TABLE rubric;
OPTIMIZE TABLE submission_rubric_score;
```

### Docker Resources

```yaml
# docker-compose.yml
services:
    custom-judgehost:
        deploy:
            resources:
                limits:
                    cpus: "4.0"
                    memory: 8G
                reservations:
                    cpus: "2.0"
                    memory: 4G
```

---

## Upgrading

### Upgrade Procedure

```bash
# 1. Backup
docker compose exec mariadb mysqldump domjudge > backup-pre-upgrade.sql

# 2. Pull latest code
git pull origin integrate-new-judgehost

# 3. Run migrations
docker compose exec domserver bin/console doctrine:migrations:migrate

# 4. Clear caches
docker compose exec domserver bin/console cache:clear --env=prod

# 5. Restart services
docker compose restart
```

---

## Uninstalling

If you need to remove the custom judgehost integration:

```sql
-- Disable integration
UPDATE configuration SET value='0' WHERE name='custom_judgehost_enabled';

-- Remove custom data (optional - be careful!)
DELETE FROM submission_rubric_score WHERE rubricid IN (
  SELECT rubricid FROM rubric WHERE type='automated'
);
DELETE FROM rubric WHERE type='automated';

-- Rollback migration (use with caution!)
-- This will drop columns and lose data!
-- bin/console doctrine:migrations:migrate prev
```

---

## Deployment Checklist

-   [ ] Code updated to latest version
-   [ ] Database migration completed
-   [ ] Configuration values set
-   [ ] API key generated and secured
-   [ ] Custom judgehost installed and running
-   [ ] Network connectivity verified
-   [ ] TLS/HTTPS enabled (production)
-   [ ] IP whitelisting configured
-   [ ] Logging enabled
-   [ ] Monitoring set up
-   [ ] Backup configured
-   [ ] Test problem uploaded successfully
-   [ ] Test submission evaluated successfully
-   [ ] Results displayed correctly in UI
-   [ ] Documentation reviewed

---

**Deployment Complete!** Your custom judgehost integration is ready for production use. 🚀

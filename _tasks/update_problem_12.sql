-- Update problem 12 with custom configuration
UPDATE problem SET 
  custom_config = JSON_OBJECT(
    'problem_id', 'sql-optimization',
    'problem_name', 'Database Query Optimization Challenge',
    'description', 'Optimize slow SQL queries on a 1M+ row dataset using schema changes, indexes, and query rewriting',
    'project_type', 'database',
    'time_limit', 1800,
    'version', '1.0.0',
    'containers', JSON_ARRAY(
      JSON_OBJECT(
        'container_id', 'database',
        'name', 'PostgreSQL Database Server',
        'accepts_submission', false,
        'dockerfile_path', 'database/Dockerfile',
        'depends_on', JSON_ARRAY(),
        'terminate_on_finish', JSON_ARRAY()
      ),
      JSON_OBJECT(
        'container_id', 'submission',
        'name', 'Query Evaluation Container',
        'accepts_submission', true,
        'dockerfile_path', 'submission/Dockerfile',
        'depends_on', JSON_ARRAY(
          JSON_OBJECT(
            'container_id', 'database',
            'condition', 'healthy',
            'timeout', 60,
            'retry', 10,
            'retry_interval', 3
          )
        ),
        'terminate_on_finish', JSON_ARRAY('database')
      )
    ),
    'rubrics', JSON_ARRAY(
      JSON_OBJECT(
        'rubric_id', 'correctness',
        'name', 'Query Result Correctness',
        'type', 'test_cases',
        'max_score', 50,
        'container', 'submission'
      ),
      JSON_OBJECT(
        'rubric_id', 'query_latency',
        'name', 'Query Latency Performance',
        'type', 'performance_benchmark',
        'max_score', 30,
        'container', 'submission'
      ),
      JSON_OBJECT(
        'rubric_id', 'concurrency',
        'name', 'Concurrent Load Performance',
        'type', 'performance_benchmark',
        'max_score', 10,
        'container', 'submission'
      ),
      JSON_OBJECT(
        'rubric_id', 'resource_efficiency',
        'name', 'Storage Efficiency',
        'type', 'resource_usage',
        'max_score', 10,
        'container', 'submission'
      )
    ),
    'hooks_config', JSON_OBJECT(
      'periodic_interval_seconds', 10,
      'hook_timeout_seconds', 30
    )
  ),
  package_path = '/opt/domjudge/domserver/webapp/var/problems/p12/',
  package_type = 'file',
  package_source = '/opt/domjudge/sample_packages/db-optimization.zip'
WHERE probid = 12;

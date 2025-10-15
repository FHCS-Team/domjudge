-- Query 2: Distinct device types for Vietnamese users with flag (last 7 days)
SELECT distinct d.device_type
FROM events e
JOIN devices d ON d.device_id = e.device_id
JOIN users u ON u.user_id = e.user_id
WHERE u.country = 'VN'
  AND (e.payload->>'flag') = 'true'
  AND e.event_ts BETWEEN now() - interval '7 days' AND now();

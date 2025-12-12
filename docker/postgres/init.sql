-- PostgreSQL Initialization Script for OBSOLIO AI

-- Enable required extensions
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pg_stat_statements";
CREATE EXTENSION IF NOT EXISTS "pg_trgm";  -- For text search
CREATE EXTENSION IF NOT EXISTS "btree_gin"; -- For composite indexes
CREATE EXTENSION IF NOT EXISTS "btree_gist"; -- For exclusion constraints

-- Create application users
DO
$$
BEGIN
    -- Application user with full access
    IF NOT EXISTS (SELECT FROM pg_catalog.pg_roles WHERE rolname = 'OBSOLIO_user') THEN
        CREATE USER OBSOLIO_user WITH PASSWORD 'changeme';
    END IF;

    -- Read-only user for reporting/analytics
    IF NOT EXISTS (SELECT FROM pg_catalog.pg_roles WHERE rolname = 'OBSOLIO_readonly') THEN
        CREATE USER OBSOLIO_readonly WITH PASSWORD 'changeme';
    END IF;
END
$$;

-- Grant permissions
GRANT CONNECT ON DATABASE OBSOLIO_ai TO OBSOLIO_user;
GRANT CONNECT ON DATABASE OBSOLIO_ai TO OBSOLIO_readonly;

-- Set default privileges for future tables
ALTER DEFAULT PRIVILEGES IN SCHEMA public
    GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO OBSOLIO_user;

ALTER DEFAULT PRIVILEGES IN SCHEMA public
    GRANT SELECT ON TABLES TO OBSOLIO_readonly;

ALTER DEFAULT PRIVILEGES IN SCHEMA public
    GRANT USAGE, SELECT ON SEQUENCES TO OBSOLIO_user;

-- Connection pooling optimizations
ALTER DATABASE OBSOLIO_ai SET max_parallel_workers_per_gather = 2;
ALTER DATABASE OBSOLIO_ai SET work_mem = '16MB';
ALTER DATABASE OBSOLIO_ai SET maintenance_work_mem = '64MB';

-- Query optimization settings
ALTER DATABASE OBSOLIO_ai SET random_page_cost = 1.1;  -- For SSD
ALTER DATABASE OBSOLIO_ai SET effective_cache_size = '1GB';
ALTER DATABASE OBSOLIO_ai SET default_statistics_target = 100;

-- Set timezone
ALTER DATABASE OBSOLIO_ai SET timezone TO 'UTC';

-- Logging for development
ALTER DATABASE OBSOLIO_ai SET log_statement = 'none';
ALTER DATABASE OBSOLIO_ai SET log_min_duration_statement = 1000;

-- Create monitoring views for connection pooling
CREATE OR REPLACE VIEW connection_stats AS
SELECT
    datname,
    usename,
    application_name,
    client_addr,
    state,
    COUNT(*) as connection_count,
    MAX(state_change) as last_activity
FROM pg_stat_activity
WHERE datname IS NOT NULL
GROUP BY datname, usename, application_name, client_addr, state;

CREATE OR REPLACE VIEW slow_queries AS
SELECT
    query,
    calls,
    total_exec_time,
    mean_exec_time,
    max_exec_time,
    stddev_exec_time
FROM pg_stat_statements
WHERE mean_exec_time > 1000  -- Queries slower than 1 second
ORDER BY mean_exec_time DESC
LIMIT 50;

CREATE OR REPLACE VIEW table_bloat AS
SELECT
    schemaname,
    tablename,
    pg_size_pretty(pg_total_relation_size(schemaname||'.'||tablename)) AS total_size,
    pg_size_pretty(pg_relation_size(schemaname||'.'||tablename)) AS table_size,
    n_dead_tup,
    n_live_tup,
    ROUND(100 * n_dead_tup / NULLIF(n_live_tup + n_dead_tup, 0), 2) AS dead_ratio
FROM pg_stat_user_tables
WHERE n_dead_tup > 0
ORDER BY n_dead_tup DESC;

-- Grant access to monitoring views
GRANT SELECT ON connection_stats TO OBSOLIO_user, OBSOLIO_readonly;
GRANT SELECT ON slow_queries TO OBSOLIO_user, OBSOLIO_readonly;
GRANT SELECT ON table_bloat TO OBSOLIO_user, OBSOLIO_readonly;

-- Print success message
DO $$
BEGIN
    RAISE NOTICE 'PostgreSQL initialization complete for OBSOLIO AI';
    RAISE NOTICE 'Extensions enabled: uuid-ossp, pg_stat_statements, pg_trgm, btree_gin, btree_gist';
    RAISE NOTICE 'Users created: OBSOLIO_user, OBSOLIO_readonly';
    RAISE NOTICE 'Monitoring views created: connection_stats, slow_queries, table_bloat';
END $$;

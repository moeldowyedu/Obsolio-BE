-- ============================================================
-- SAFE DELETE for Pricing Tables
-- ============================================================
-- This script safely deletes all data from subscription_plans
-- and billing_cycles tables while handling foreign key constraints
-- ============================================================

-- Start transaction for safety
BEGIN;

-- Show current counts before deletion
SELECT
    'BEFORE DELETE' as status,
    (SELECT COUNT(*) FROM subscription_plans) as subscription_plans_count,
    (SELECT COUNT(*) FROM billing_cycles) as billing_cycles_count;

-- ============================================================
-- STEP 1: Delete subscription_plans first (has foreign key)
-- ============================================================

-- Option A: Simple delete (PostgreSQL will handle FK cascade if set)
DELETE FROM subscription_plans;

-- ============================================================
-- STEP 2: Delete billing_cycles (parent table)
-- ============================================================

DELETE FROM billing_cycles;

-- ============================================================
-- STEP 3: Reset auto-increment sequence for billing_cycles
-- ============================================================

-- PostgreSQL version
ALTER SEQUENCE billing_cycles_id_seq RESTART WITH 1;

-- MySQL version (uncomment if using MySQL)
-- ALTER TABLE billing_cycles AUTO_INCREMENT = 1;

-- ============================================================
-- STEP 4: Verify deletion
-- ============================================================

-- Show current counts after deletion
SELECT
    'AFTER DELETE' as status,
    (SELECT COUNT(*) FROM subscription_plans) as subscription_plans_count,
    (SELECT COUNT(*) FROM billing_cycles) as billing_cycles_count;

-- ============================================================
-- STEP 5: Commit the transaction
-- ============================================================

COMMIT;

-- If something goes wrong, you can run: ROLLBACK;

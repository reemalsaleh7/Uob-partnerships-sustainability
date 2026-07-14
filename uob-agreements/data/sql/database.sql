-- ==========================================================
-- University Partnerships & Initiatives System
-- Database Creation Script
--
-- Database: PostgreSQL 17
--
-- Purpose:
-- Creates a clean database for development.
-- ==========================================================

DO $$
DECLARE
    db_name text := 'UOB_Partnership_and_Initiative';
BEGIN
    PERFORM pg_terminate_backend(pid)
    FROM pg_stat_activity
    WHERE datname = db_name
      AND pid <> pg_backend_pid();
END $$;

DROP DATABASE IF EXISTS "UOB_Partnership_and_Initiative";

CREATE DATABASE "UOB_Partnership_and_Initiative"
    WITH
    OWNER = postgres
    ENCODING = 'UTF8'
    LC_COLLATE = 'C'
    LC_CTYPE = 'C'
    TEMPLATE = template0;
-- ==========================================================
-- University Partnerships & Initiatives System
-- Database Creation Script
--
-- Database: PostgreSQL 17
--
-- Purpose:
-- Creates a clean database for development.
-- ==========================================================

DROP DATABASE IF EXISTS UOB_Partnership_and_Initiative;

CREATE DATABASE UOB_Partnership_and_Initiative
    WITH
    OWNER = postgres
    ENCODING = 'UTF8'
    LC_COLLATE = 'C'
    LC_CTYPE = 'C'
    TEMPLATE = template0;
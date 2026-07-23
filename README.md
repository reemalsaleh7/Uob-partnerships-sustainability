# University of Bahrain Partnerships & Sustainability

A web platform for the University of Bahrain to manage and display partnerships, agreements, initiatives, and sustainability-related activities.

## Local database configuration

Copy `config/database.local.example.php` to `config/database.local.php` and set
the PostgreSQL password. The local file is ignored by Git. Production may use
the `UOB_DB_HOST`, `UOB_DB_PORT`, `UOB_DB_NAME`, `UOB_DB_USER`, and
`UOB_DB_PASSWORD` environment variables instead.

Before releasing Agreement changes, run:

```powershell
& "C:\xampp\php\php.exe" .\scripts\run_agreement_acceptance_suite.php
```

## Project Description

This portal provides a centralized platform to organize and present University of Bahrain partnerships and sustainability impact. It allows users to browse agreements, view initiatives, explore SDGs, and access partnership information in a structured and user-friendly way.

## Main Features
- Agreements Management
- Sustainability Initiatives
- SDGs Integration
- Partnership Network
- Search & Filters
- Admin Dashboard
- Notifications
- Bilingual Support (Arabic & English)
- AI-powered SDG Suggestions
- And more...

## Technologies Used

- PHP
- HTML
- CSS
- JavaScript
- CSV / JSON data files

## Team Members

# Project Team

- **Reem**
- **Fatima Ebrahim**
- **Fatima Ali**
- **Khadija **

## Notes

This repository is developed as part of the University of Bahrain training/project work.

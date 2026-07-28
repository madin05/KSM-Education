# KSM Education - Journal & Opinion Management System

![HTML5](https://img.shields.io/badge/HTML5-E34F26?style=for-the-badge&logo=html5&logoColor=white)
![CSS3](https://img.shields.io/badge/CSS3-1572B6?style=for-the-badge&logo=css3&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black)
![PHP](https://img.shields.io/badge/PHP-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-4479A1?style=for-the-badge&logo=mysql&logoColor=white)

Web-based application for managing academic journals and opinion articles for educational institutions.

## Features
- Journal and opinion article management (CRUD)
- File upload system (PDF documents and cover images)
- Admin dashboard with statistics
- Article search functionality
- PDF document preview and download
- Responsive design


## User Roles
### Admin Features
- Manage journals and opinions (add, edit, delete)
- Upload and manage files
- View statistics and analytics
- User management
- Content moderation

### User Features
- Browse and search articles
- View article details
- Download PDF documents
- Responsive interface


## Tech Stack
- **Frontend**: HTML5, CSS3, Vanilla JavaScript
- **Backend**: PHP
- **Database**: MySQL
- **Server**: Apache (XAMPP)
- **Icons**: Feather Icons
- **PDF**: PDF.js

## Requirements
- PHP
- MySQL
- Apache web server
- PDO MySQL extension

## Database setup and migrations

1. Import `database/journal_system2.sql` for a new installation.
2. Apply migrations in filename order. Phase 1 starts with:

   ```powershell
   Get-Content -Raw database/migrations/001_phase1_foundation.sql | C:\xampp\mysql\bin\mysql.exe -u root journal_system
   ```

Migration `001_phase1_foundation.sql` is idempotent and may be rerun safely. It
adds article ownership/review workflow, token wallets and immutable ledger,
purchase requests, comments, and the JWT blacklist. Existing articles are
backfilled as `published` to preserve current public content.

Before using authentication endpoints, copy `.env.example` to `.env`, set the
database credentials, and replace `JWT_SECRET` with a random value of at least
32 characters.

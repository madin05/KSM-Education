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

```
ksmaja
├─ .htaccess
├─ api
│  ├─ auth
│  │  ├─ api_auth_login.php
│  │  ├─ api_auth_logout.php
│  │  ├─ api_auth_me.php
│  │  └─ api_auth_middleware.php
│  ├─ journals
│  │  ├─ create_journal.php
│  │  ├─ delete_journal.php
│  │  ├─ edit_journal.php
│  │  ├─ get_journal.php
│  │  ├─ list_journals.php
│  │  └─ update_journal.php
│  ├─ opinions
│  │  ├─ create_opinion.php
│  │  ├─ delete_opinion.php
│  │  ├─ edit_opinion.php
│  │  ├─ get_opinion.php
│  │  ├─ list_opinions.php
│  │  └─ update_opinion.php
│  ├─ stats
│  │  └─ get_stats.php
│  ├─ sync
│  │  ├─ sync_pull.php
│  │  └─ sync_push.php
│  ├─ tracking
│  │  └─ track_visitor.php
│  ├─ upload
│  │  ├─ delete_upload.php
│  │  └─ upload.php
│  └─ utils
│     ├─ serve_pdf.php
│     └─ update_views.php
├─ archive
│  ├─ css
│  │  ├─ explore_jurnal_user.css
│  │  ├─ header.css
│  │  ├─ index_responsive.css
│  │  ├─ journal.css
│  │  ├─ login_admin.css
│  │  ├─ login_user.css
│  │  ├─ opinions.css
│  │  ├─ preview-jurnal.css
│  │  └─ social.css
│  ├─ html
│  │  ├─ dashboard_admin.html
│  │  ├─ dashboard_user.html
│  │  ├─ explore_jurnal_admin.html
│  │  ├─ explore_jurnal_user.html
│  │  ├─ explore_opini_admin.html
│  │  ├─ explore_opini_user.html
│  │  ├─ journals.html
│  │  ├─ journals_user.html
│  │  ├─ login_user.html
│  │  ├─ opinions.html
│  │  └─ opinions_user.html
│  └─ js
│     ├─ api.js
│     ├─ dashboard_user.js
│     ├─ dual_upload_handler.js
│     ├─ explore_jurnal_user.js
│     ├─ file_upload.js
│     ├─ jurnal.js
│     ├─ login.js
│     ├─ login_user.js
│     ├─ migration.js
│     ├─ mobile_menu.js
│     ├─ opinions.js
│     ├─ opinions_manager.js
│     ├─ pagination.js
│     ├─ pdf_text_extractor.js
│     ├─ script.js
│     ├─ social.js
│     ├─ statistic.js
│     ├─ storage.js
│     ├─ tags_manager.js
│     └─ upload_tabs.js
├─ assets
│  ├─ css
│  │  ├─ admin
│  │  │  ├─ header-admin.css
│  │  │  ├─ journal-crud.css
│  │  │  ├─ modals.css
│  │  │  ├─ navigation-admin.css
│  │  │  ├─ pagination.css
│  │  │  ├─ upload-tabs.css
│  │  │  └─ upload.css
│  │  ├─ admin-main.css
│  │  ├─ core
│  │  │  ├─ articles-detail.css
│  │  │  ├─ auth.css
│  │  │  ├─ breadcrumb.css
│  │  │  ├─ cards.css
│  │  │  ├─ components.css
│  │  │  ├─ explore-journal-content.css
│  │  │  ├─ filters.css
│  │  │  ├─ forms.css
│  │  │  ├─ journal-list.css
│  │  │  ├─ main-layout.css
│  │  │  ├─ navigation.css
│  │  │  ├─ preview-modal.css
│  │  │  ├─ reset.css
│  │  │  ├─ responsive.css
│  │  │  ├─ search-modal.css
│  │  │  ├─ share-modal.css
│  │  │  ├─ states.css
│  │  │  └─ statistics.css
│  │  ├─ user
│  │  │  ├─ articles-section.css
│  │  │  ├─ categories.css
│  │  │  ├─ header-user.css
│  │  │  ├─ login-page.css
│  │  │  ├─ navigation-user.css
│  │  │  ├─ newsletter.css
│  │  │  ├─ opinions.css
│  │  │  └─ user-profile.css
│  │  └─ user-main.css
│  ├─ images
│  │  ├─ icons
│  │  │  ├─ android-chrome-192x192.png
│  │  │  ├─ android-chrome-512x512.png
│  │  │  ├─ apple-touch-icon.png
│  │  │  ├─ favicon-16x16.png
│  │  │  ├─ favicon-32x32.png
│  │  │  └─ favicon.ico
│  │  └─ main_logo.png
│  └─ js
│     ├─ admin
│     │  ├─ auth
│     │  │  └─ admin-login-managerr.js
│     │  ├─ dual-upload-handler.js
│     │  ├─ edit-journal.js
│     │  ├─ file-upload-manager.js
│     │  ├─ init-admin.js.js
│     │  ├─ journal-manager.js
│     │  ├─ managers
│     │  │  ├─ author-manager.js
│     │  │  ├─ pengurus-manager.js
│     │  │  └─ tags-manager.js
│     │  ├─ opinion-control.js
│     │  ├─ opinion-manager.js
│     │  ├─ opinions-upload-manager.js
│     │  ├─ pagination-manager.js
│     │  └─ utils
│     │     ├─ data-transformer.js
│     │     └─ migration-helper.deprecate.js
│     ├─ api.js
│     ├─ config.js
│     ├─ explore-journal-user.js
│     ├─ login-handler.js
│     ├─ modules
│     │  ├─ dropdown.js
│     │  ├─ mobile-menu.js
│     │  ├─ modal.js
│     │  ├─ pdf-text-extractor.js
│     │  ├─ preview.js
│     │  ├─ search.js
│     │  ├─ share.js
│     │  ├─ social-share.js
│     │  ├─ tags-manager.js
│     │  ├─ toast.js
│     │  └─ upload-tabs.js
│     ├─ renderers
│     │  └─ card-renderer.js
│     ├─ statistic.js
│     ├─ storage.js
│     ├─ user
│     │  ├─ dashboard.js
│     │  ├─ explore-journal.js
│     │  ├─ journals.js
│     │  └─ profile.js
│     └─ utils.js
├─ config
│  ├─ app.php
│  └─ database.example.php
├─ database
├─ index.html
├─ index.php
├─ README.md
└─ views
   ├─ admin
   │  ├─ components
   │  │  ├─ footer-scripts.html
   │  │  ├─ header.html
   │  │  ├─ journal-list.html
   │  │  ├─ modals.html
   │  │  ├─ statistics.html
   │  │  ├─ upload-journal.html
   │  │  ├─ upload-opinion.html
   │  │  └─ upload-section.html
   │  ├─ dashboard.php
   │  ├─ explore-journals.php
   │  ├─ explore-opinions.php
   │  ├─ journals.php
   │  └─ opinions.php
   ├─ auth
   │  └─ login.html
   ├─ shared
   │  └─ components
   │     ├─ article-detail.html
   │     ├─ breadcrumb.html
   │     └─ search-modal.html
   └─ user
      ├─ components
      │  ├─ articles-section.html
      │  ├─ categories.html
      │  ├─ footer-scripts-dashboard.html
      │  ├─ header.html
      │  ├─ newsletter.html
      │  └─ statistics.html
      ├─ dashboard.php
      ├─ explore-journals.php
      ├─ explore-opinions.php
      ├─ journals.php
      └─ opinions.php

```
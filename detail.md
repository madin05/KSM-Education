Act as a Lead Full-Stack Engineer specializing in PHP, HTML5, and CSS Architecture. My PHP Admin Dashboard and its sub-pages (dashboard_admin.php, moderation pages, content management, pagination, etc.) experienced a severe visual regression/layout collapse during a recent refactor.

Examine all global admin files (e.g., admin_header.php, admin_nav.php, admin_sidebar.php, admin.css, dashboard_admin.php) and execute a comprehensive repair and refactor based on the specification below.

---

### CRITICAL PHASE 1: STRUCTURAL HTML & DOM DIAGNOSTIC (PRIORITY FIX)

1. **Tag Closure & DOM Nesting Audit:**
   - Scan all shared/included files (`admin_header.php`, `admin_nav.php`, `admin_footer.php`) and main views for unclosed or misplaced HTML tags—specifically wrapper `<div>`, `<main>`, `<section>`, or `<header>` elements.
   - Ensure every opening wrapper tag in the header has a matching closing tag in the footer or view file.
   - Fix any broken container hierarchy that is causing global content to bleed out into the root body level or break the document grid.

2. **Preserve Backend Integrity:**
   - DO NOT alter, remove, or comment out any PHP logic, `session_start()`, authentication checks, database queries (`mysqli`/`PDO`), variable declarations, or JS script listeners.

---

### CRITICAL PHASE 2: ADMIN LAYOUT & GRID RESTORATION

Re-establish a robust, clean, and responsive CSS layout architecture using modern Flexbox and CSS Grid:

1. **Main Admin Shell & Layout Structure:**
   ```css
   .admin-wrapper {
     display: flex;
     flex-direction: column;
     min-height: 100vh;
     background-color: #f8fafc; /* Clean light gray background */
     color: #1e293b;
     font-family: system-ui, -apple-system, sans-serif;
   }

   .admin-main-content {
     flex: 1;
     max-width: 1400px;
     width: 100%;
     margin: 0 auto;
     padding: 2rem 1.5rem;
   };

Admin Navbar & Navigation Controls:

Restore top navigation header styling: Clean white background (#ffffff), subtle bottom border (1px solid #e2e8f0), and soft box-shadow.

Style dropdown menus ("KONTEN", "MODERASI", "OPERASIONAL"):

Pill/Button trigger inputs with clean light gray/white borders (border: 1px solid #cbd5e1).

Align brand logo, Home link button, user status ("Administrator"), and Logout button cleanly using display: flex; align-items: center; justify-content: space-between;.

Ensure the "LOGOUT" link is clearly styled as an action link or badge (color: #dc2626 or #2c3e50).

Operational Summary Cards Grid ("RINGKASAN OPERASIONAL"):

Fix the broken grid layout for status cards ("MENUNGGU REVIEW", "PESAN KONTAK BARU", "TOP-UP TOKEN", "PENGUNJUNG HARI INI", "ARTIKEL TERBIT", "PENGGUNA TERDAFTAR").

Apply a robust CSS Grid wrapper:

CSS
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1.25rem;
  margin-bottom: 2.5rem;
}

.stat-card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 1.25rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
  display: flex;
  align-items: center;
  gap: 1rem;
}
Typography & Section Headers:

Section Headings ("RINGKASAN OPERASIONAL", "STATISTIK", "ARTIKEL TERBIT"):

Font size: 1.125rem / 1.25rem, bold/semi-bold (font-weight: 700).

Color: Use dark slate/navy accent (#2c3e50 or #0f172a).

Spacing: margin-bottom: 1rem.

Tables, Lists & Pagination Components:

Ensure data tables and article lists have full width (width: 100%) with overflow-x protection (overflow-x: auto) for responsive scrolling.

Restructure pagination container:

Center-align pagination controls using Flexbox (display: flex; justify-content: center; gap: 0.5rem; margin-top: 2rem;).

Style active page buttons with #2c3e50 background and white text.

PHASE 3: OUTPUT REQUIREMENTS
Identify and state clearly which file contained the unclosed HTML tag or broken CSS syntax that caused the global regression.

Provide the corrected code for the affected header/navigation PHP file.

Provide the clean, restored CSS styles ready to be applied to


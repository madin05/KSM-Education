<?php
/**
 * KSM Education Landing Page
 * Revised premium hybrid educational portal landing page with split academic/news columns.
 */
session_start();

// Enable error logging but disable screen outputs to keep page clean
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Determine language
$lang = isset($_GET['lang']) && $_GET['lang'] === 'en' ? 'en' : 'id';

// Translations Dictionary
$translations = [
    'id' => [
        'nav_home' => 'Home',
        'nav_journals' => 'Jurnal',
        'nav_articles' => 'Opini & Berita',
        'nav_about' => 'About',
        'hero_title' => 'Temukan <span>Keunggulan Akademik</span> & Berita Edukasi Terbaru',
        'hero_subtitle' => 'Platform terpercaya untuk publikasi, indeksasi, dan penyebaran hasil penelitian teoretis serta praktis bagi akademisi global.',
        'search_placeholder' => 'Cari berdasarkan kata kunci...',
        'search_opt_journals' => 'Jurnal',
        'search_opt_articles' => 'Artikel',
        'search_btn' => 'Cari',
        'cta_upload' => 'Upload Karya Anda',
        'cta_news' => 'Baca Berita Terbaru',
        'stat_journals' => 'Jurnal Ilmiah',
        'stat_news' => 'Berita & Opini',
        'stat_readers' => 'Pembaca Global',
        'title_journals' => 'Jurnal Akademik Terbaru',
        'title_news' => 'Opini & Berita Pilihan',
        'btn_view_all' => 'Lihat Semua',
        'read_more' => 'Baca Selengkapnya',
        'read_time' => 'min baca',
        'views_count' => 'dilihat',
        'guidelines_title' => 'Author Guidelines',
        'guidelines_subtitle' => 'Panduan lengkap tata cara penulisan dan pengiriman naskah artikel ilmiah di KSM Education.',
        'g1_title' => 'Format Naskah',
        'g1_desc' => 'Naskah wajib disusun rapi menggunakan font Montserrat/Inter, mencantumkan abstrak dalam Bahasa Indonesia dan Bahasa Inggris.',
        'g2_title' => 'Sistem Token & Upload',
        'g2_desc' => 'Proses pengunggahan naskah jurnal mandiri memerlukan 1 token. Pembelian token dapat dilakukan langsung melalui dasbor akun Anda.',
        'g3_title' => 'Proses Penilaian',
        'g3_desc' => 'Setiap jurnal yang diunggah akan melalui peninjauan administratif dan verifikasi sebelum terpublikasi secara terbuka.',
        'submit_title' => 'Siap mempublikasikan hasil penelitian Anda?',
        'submit_desc' => 'Kirimkan naskah jurnal ilmiah terbaik Anda sekarang dan raih pembaca serta sitasi dari jejaring akademisi global.',
        'submit_btn' => 'Mulai Mengunggah'
    ],
    'en' => [
        'nav_home' => 'Home',
        'nav_journals' => 'Journals',
        'nav_articles' => 'News & Articles',
        'nav_about' => 'About',
        'hero_title' => 'Discover <span>Academic Excellence</span> & Latest Educational News',
        'hero_subtitle' => 'A trusted platform for publication, indexation, and dissemination of theoretical and practical research findings for global academics.',
        'search_placeholder' => 'Search by keywords...',
        'search_opt_journals' => 'Journals',
        'search_opt_articles' => 'Articles',
        'search_btn' => 'Search',
        'cta_upload' => 'Upload Your Work',
        'cta_news' => 'Read Latest News',
        'stat_journals' => 'Academic Journals',
        'stat_news' => 'News & Articles',
        'stat_readers' => 'Global Readers',
        'title_journals' => 'Latest Academic Journals',
        'title_news' => 'Featured News & Articles',
        'btn_view_all' => 'View All',
        'read_more' => 'Read More',
        'read_time' => 'min read',
        'views_count' => 'views',
        'guidelines_title' => 'Author Guidelines',
        'guidelines_subtitle' => 'Comprehensive guide for writing and submitting scientific articles to KSM Education.',
        'g1_title' => 'Manuscript Format',
        'g1_desc' => 'Manuscripts must be neatly structured using Montserrat/Inter, include abstracts in both Indonesian and English.',
        'g2_title' => 'Token & Upload System',
        'g2_desc' => 'The self-uploading process requires 1 token. Token purchase can be done directly through your account dashboard.',
        'g3_title' => 'Review Process',
        'g3_desc' => 'Each uploaded journal will go through administrative review and verification before being published openly.',
        'submit_title' => 'Ready to share your research with the world?',
        'submit_desc' => 'Submit your best scientific paper today and gain readers and citations from global academic networks.',
        'submit_btn' => 'Start Uploading'
    ]
];

$t = $translations[$lang];

// Default counts
$base_journals_count = 15280; 
$base_news_count = 5120;
$base_readers_count = 24950;
$latest_journals = [];
$latest_opinions = [];

// Determine if user is logged in
$is_logged_in = isset($_SESSION['user_id']);
$user_name = $is_logged_in ? $_SESSION['name'] : '';
$user_avatar_char = $is_logged_in ? strtoupper(substr($user_name, 0, 1)) : '';

try {
    // Database connection using services/db.php
    require_once __DIR__ . '/services/db.php';
    
    // Override application/json header set by db.php
    header('Content-Type: text/html; charset=utf-8');

    // Fetch dynamic counts
    $stmtCountJ = $pdo->query("SELECT COUNT(*) as total FROM journals");
    $countJ = (int)$stmtCountJ->fetch()['total'];
    
    $stmtCountO = $pdo->query("SELECT COUNT(*) as total FROM opinions");
    $countO = (int)$stmtCountO->fetch()['total'];

    // Real dynamic stats + base stats
    $base_journals_count += $countJ;
    $base_news_count += $countO;
    $base_readers_count += ($countJ * 15 + $countO * 10);

    // Fetch latest 4 journals
    $stmtJ = $pdo->query("
        SELECT j.id, j.title, j.abstract, j.authors, j.created_at, COALESCE(j.views, 0) as views 
        FROM journals j 
        ORDER BY j.created_at DESC 
        LIMIT 4
    ");
    $latest_journals = $stmtJ->fetchAll();

    // Fetch latest 4 opinions (News & Articles)
    $stmtO = $pdo->query("
        SELECT o.id, o.title, o.content, o.created_at, COALESCE(o.views, 0) as views, u.url as cover_url 
        FROM opinions o 
        LEFT JOIN uploads u ON o.cover_upload_id = u.id 
        ORDER BY o.created_at DESC 
        LIMIT 4
    ");
    $latest_opinions = $stmtO->fetchAll();
} catch (Exception $e) {
    // Fallback silent
}

// Compute APP_ROOT prefix
$app_root = '';
$_db_abs = realpath(__DIR__ . '/services/db.php');
if ($_db_abs && isset($_SERVER['DOCUMENT_ROOT'])) {
    $_doc_root = realpath($_SERVER['DOCUMENT_ROOT']);
    if ($_doc_root) {
        $_rel = str_replace($_doc_root, '', $_db_abs);
        $_rel = str_replace('\\', '/', $_rel);
        $app_root = dirname(dirname($_rel));
        if ($app_root === '/' || $app_root === '.') {
            $app_root = '';
        }
    }
}

// Format the journals list
$rendered_journals = [];
if (!empty($latest_journals)) {
    foreach ($latest_journals as $art) {
        $authors = [];
        if ($art['authors']) {
            try {
                $decoded = json_decode($art['authors'], true);
                if (is_array($decoded)) {
                    $authors = $decoded;
                } else {
                    $authors = [$art['authors']];
                }
            } catch (Exception $ex) {
                $authors = [$art['authors']];
            }
        }
        
        $rendered_journals[] = [
            'id' => $art['id'],
            'title' => $art['title'],
            'abstract' => $art['abstract'],
            'authors' => !empty($authors) ? implode(', ', $authors) : 'KSM Author',
            'date' => date('d M Y', strtotime($art['created_at'])),
            'views' => $art['views']
        ];
    }
}

// Fallback mock journals if empty
if (count($rendered_journals) < 2) {
    $rendered_journals = array_merge($rendered_journals, [
        [
            'id' => '#',
            'title' => 'Analisis Kebijakan Moneter Terhadap Pertumbuhan Ekonomi Digital di Asia Tenggara',
            'abstract' => 'Penelitian ini menganalisis dampak implementasi kebijakan moneter bank sentral terhadap stabilitas pasar uang dan pertumbuhan ekonomi berbasis digital di kawasan Asia Tenggara dalam dekade terakhir.',
            'authors' => 'Dr. M. Arif Syahrudin, Prof. Dr. Hendra Wijaya',
            'date' => '15 Jul 2026',
            'views' => 1420
        ],
        [
            'id' => '#',
            'title' => 'Penerapan Deep Learning Untuk Klasifikasi Citra Medis Deteksi Kanker Paru',
            'abstract' => 'Penelitian ini mengajukan arsitektur convolutional neural network (CNN) yang dioptimasi untuk mendeteksi nodul paru-paru pada citra CT scan dengan tingkat akurasi mencapai 98.4%.',
            'authors' => 'Rian Adisukma, Dr. Sarah Fitriani',
            'date' => '14 Jul 2026',
            'views' => 985
        ],
        [
            'id' => '#',
            'title' => 'Strategi Manajemen Pembelajaran Jarak Jauh di Era Pasca-Pandemi Global',
            'abstract' => 'Evaluasi komprehensif mengenai efektivitas model hybrid learning pada institusi pendidikan tinggi Indonesia, serta pengembangan kurikulum yang adaptif bagi mahasiswa vokasi.',
            'authors' => 'Dewi Lestari M.Pd., Budi Santoso',
            'date' => '10 Jul 2026',
            'views' => 654
        ]
    ]);
}

// Format the opinions list
$rendered_opinions = [];
if (!empty($latest_opinions)) {
    foreach ($latest_opinions as $op) {
        $cover = $op['cover_url'];
        if ($cover && $app_root && strpos($cover, $app_root) !== 0) {
            $cover = $app_root . '/' . ltrim($cover, '/');
        }
        if (!$cover) {
            $cover = 'https://images.unsplash.com/photo-1506784983877-45594efa4cbe?w=400&h=300&fit=crop';
        }

        // Dynamic reading time calculation
        $words = str_word_count(strip_tags($op['content']));
        $read_time = max(1, ceil($words / 200));

        $rendered_opinions[] = [
            'id' => $op['id'],
            'title' => $op['title'],
            'summary' => substr(strip_tags($op['content']), 0, 140) . '...',
            'cover' => $cover,
            'read_time' => $read_time,
            'date' => date('d M Y', strtotime($op['created_at']))
        ];
    }
}

// Fallback mock opinions if empty
if (count($rendered_opinions) < 2) {
    $rendered_opinions = array_merge($rendered_opinions, [
        [
            'id' => '#',
            'title' => 'Menakar Potensi AI Generatif dalam Sistem Pendidikan Menengah Atas',
            'summary' => 'Kajian mendalam tentang pemanfaatan ChatGPT dan tool AI generatif lainnya untuk membantu guru menyusun rencana pembelajaran personal.',
            'cover' => 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?w=400&h=300&fit=crop',
            'read_time' => 4,
            'date' => '16 Jul 2026'
        ],
        [
            'id' => '#',
            'title' => 'Pentingnya Literasi Digital di Kalangan Siswa Sekolah Dasar',
            'summary' => 'Artikel opini mengenai ancaman paparan informasi digital tanpa filter pada anak-anak usia dini dan pentingnya kurikulum literasi internet sehat.',
            'cover' => 'https://images.unsplash.com/photo-1503676260728-1c00da094a0b?w=400&h=300&fit=crop',
            'read_time' => 3,
            'date' => '12 Jul 2026'
        ],
        [
            'id' => '#',
            'title' => 'Kurikulum Merdeka: Antara Fleksibilitas dan Kesiapan Infrastruktur',
            'summary' => 'Pembahasan kritis mengenai kesiapan sekolah-sekolah di daerah terpencil dalam mengadopsi struktur pembelajaran berbasis proyek.',
            'cover' => 'https://images.unsplash.com/photo-1427504494785-3a9ca7044f45?w=400&h=300&fit=crop',
            'read_time' => 5,
            'date' => '08 Jul 2026'
        ]
    ]);
}
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KSM Education — Portal Publikasi Jurnal & Opini Ilmiah</title>
    <!-- Preconnect for fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="styles/fonts.css">
    <link rel="stylesheet" href="styles/landing.css?v=202607161225">
    <link rel="shortcut icon" type="image/x-icon" href="assets/favicon.ico" />
    <script src="https://unpkg.com/feather-icons"></script>
</head>
<body id="home">

    <!-- Top Navigation Bar -->
    <header class="landing-header">
        <div class="landing-nav-container">
            <a href="index.php?lang=<?php echo $lang; ?>" class="landing-logo">
                <img src="assets/main_logo.png" alt="KSM Education Logo">
            </a>
            
            <nav class="landing-nav-links" id="landingNavMenu">
                <a href="#home"><?php echo $t['nav_home']; ?></a>
                <a href="user/journals_user.php"><?php echo $t['nav_journals']; ?></a>
                <a href="user/opinions_user.php"><?php echo $t['nav_articles']; ?></a>
                <a href="#about"><?php echo $t['nav_about']; ?></a>
            </nav>

            <div class="landing-nav-right">
                <!-- Sleek Language Switcher -->
                <div class="lang-switcher">
                    <button class="lang-btn" id="langBtn">
                        🌐 <?php echo $lang === 'en' ? 'EN' : 'ID'; ?> 
                        <i data-feather="chevron-down" style="width: 14px; height: 14px;"></i>
                    </button>
                    <div class="lang-dropdown" id="langDropdown">
                        <button class="lang-item" onclick="changeLanguage('id')">Bahasa (ID)</button>
                        <button class="lang-item" onclick="changeLanguage('en')">English (EN)</button>
                    </div>
                </div>

                <div class="landing-nav-auth">
                    <?php if ($is_logged_in): ?>
                        <a href="user/dashboard_user.php" class="landing-nav-user">
                            <div class="avatar"><?php echo $user_avatar_char; ?></div>
                            <span class="name"><?php echo htmlspecialchars($user_name); ?></span>
                        </a>
                    <?php else: ?>
                        <a href="user/login_user.php" class="btn-signin">Sign In</a>
                        <a href="user/register_user.php" class="btn-register">Register</a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Hamburger Button for Mobile -->
            <button class="landing-mobile-menu-btn" id="mobileMenuBtn" aria-label="Menu">
                <i data-feather="menu"></i>
            </button>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="landing-hero">
        <div class="hero-deco-1"></div>
        <div class="hero-deco-2"></div>
        
        <div class="landing-hero-content">
            <h1><?php echo $t['hero_title']; ?></h1>
            <p style="color: var(--text-muted); font-size: 18px; line-height: 1.6; max-width: 680px; margin: 0 auto;">
                <?php echo $t['hero_subtitle']; ?>
            </p>
            
            <!-- Search Bar Box with Dropdown filter beside it -->
            <div class="landing-search-container">
                <form id="searchForm" action="user/journals_user.php" method="GET" class="landing-search-box">
                    <!-- Dropdown Filter Selection -->
                    <div class="search-filter-wrapper">
                        <select id="searchFilter" class="search-filter-select">
                            <option value="journals" selected><?php echo $t['search_opt_journals']; ?></option>
                            <option value="articles"><?php echo $t['search_opt_articles']; ?></option>
                        </select>
                        <i data-feather="chevron-down" class="caret-icon"></i>
                    </div>

                    <i data-feather="search" class="search-icon"></i>
                    <input type="text" name="search" placeholder="<?php echo $t['search_placeholder']; ?>" autocomplete="off">
                    <button type="submit" class="btn-search-submit"><?php echo $t['search_btn']; ?></button>
                </form>
            </div>

            <!-- CTA Buttons -->
            <div class="landing-hero-ctas">
                <a href="<?php echo $is_logged_in ? 'user/dashboard_user.php?action=upload' : 'user/login_user.php'; ?>" class="btn-hero-primary">
                    <i data-feather="upload"></i>
                    <?php echo $t['cta_upload']; ?>
                </a>
                <a href="user/opinions_user.php" class="btn-hero-secondary">
                    <?php echo $t['cta_news']; ?>
                </a>
            </div>
        </div>
    </section>

    <!-- Compact Statistics Bar -->
    <section class="landing-stats-bar">
        <div class="landing-stats-container">
            <div class="landing-stat-item">
                <div class="landing-stat-icon-wrapper">
                    <i data-feather="file-text"></i>
                </div>
                <div class="landing-stat-info">
                    <span class="landing-stat-val"><?php echo number_format($base_journals_count); ?>+</span>
                    <span class="landing-stat-lbl"><?php echo $t['stat_journals']; ?></span>
                </div>
            </div>
            
            <div class="landing-stat-item">
                <div class="landing-stat-icon-wrapper">
                    <i data-feather="globe"></i>
                </div>
                <div class="landing-stat-info">
                    <span class="landing-stat-val"><?php echo number_format($base_news_count); ?>+</span>
                    <span class="landing-stat-lbl"><?php echo $t['stat_news']; ?></span>
                </div>
            </div>
            
            <div class="landing-stat-item">
                <div class="landing-stat-icon-wrapper">
                    <i data-feather="users"></i>
                </div>
                <div class="landing-stat-info">
                    <span class="landing-stat-val"><?php echo number_format($base_readers_count); ?>+</span>
                    <span class="landing-stat-lbl"><?php echo $t['stat_readers']; ?></span>
                </div>
            </div>
        </div>
    </section>

    <!-- Split Content Area (The Core) -->
    <section class="landing-split-section">
        <div class="split-grid">
            
            <!-- Left Column: Latest Academic Journals (Text Heavy, Formal) -->
            <div class="split-left-column">
                <div class="column-header">
                    <h2>
                        <i data-feather="file-text"></i>
                        <?php echo $t['title_journals']; ?>
                    </h2>
                    <a href="user/journals_user.php" class="btn-view-all">
                        <?php echo $t['btn_view_all']; ?>
                        <i data-feather="arrow-right" style="width: 14px; height: 14px;"></i>
                    </a>
                </div>

                <div class="academic-cards-container">
                    <?php foreach ($rendered_journals as $journal): ?>
                        <?php 
                        $journal_link = ($journal['id'] === '#') ? 'user/journals_user.php' : 'user/explore_jurnal_user.php?id=' . $journal['id'] . '&type=jurnal';
                        ?>
                        <a href="<?php echo $journal_link; ?>" class="academic-card">
                            <div class="academic-icon-wrapper">
                                <i data-feather="book-open"></i>
                            </div>
                            <div class="academic-content">
                                <h3 class="academic-title"><?php echo htmlspecialchars($journal['title']); ?></h3>
                                <div class="academic-authors">
                                    <?php echo htmlspecialchars($journal['authors']); ?>
                                </div>
                                <div class="academic-meta">
                                    <span>
                                        <i data-feather="calendar"></i>
                                        <?php echo htmlspecialchars($journal['date']); ?>
                                    </span>
                                    <span>
                                        <i data-feather="eye"></i>
                                        <?php echo number_format($journal['views']); ?> <?php echo $t['views_count']; ?>
                                    </span>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Right Column: Featured News & Articles (Visual Heavy, Engaging) -->
            <div class="split-right-column">
                <div class="column-header">
                    <h2>
                        <i data-feather="image"></i>
                        <?php echo $t['title_news']; ?>
                    </h2>
                    <a href="user/opinions_user.php" class="btn-view-all">
                        <?php echo $t['btn_view_all']; ?>
                        <i data-feather="arrow-right" style="width: 14px; height: 14px;"></i>
                    </a>
                </div>

                <div class="news-cards-container">
                    <?php foreach ($rendered_opinions as $opinion): ?>
                        <?php 
                        $opinion_link = ($opinion['id'] === '#') ? 'user/opinions_user.php' : 'user/explore_opini_user.php?id=' . $opinion['id'] . '&type=opini';
                        ?>
                        <a href="<?php echo $opinion_link; ?>" class="news-card">
                            <div class="news-thumbnail">
                                <img src="<?php echo htmlspecialchars($opinion['cover']); ?>" alt="Thumbnail News">
                            </div>
                            <div class="news-content">
                                <h3 class="news-title"><?php echo htmlspecialchars($opinion['title']); ?></h3>
                                <p class="news-summary"><?php echo htmlspecialchars($opinion['summary']); ?></p>
                                <div class="news-meta">
                                    <span>
                                        <i data-feather="calendar" style="width: 12px; height: 12px; margin-right: 4px; vertical-align: middle;"></i>
                                        <?php echo htmlspecialchars($opinion['date']); ?>
                                    </span>
                                    <span class="news-readtime">
                                        <?php echo $opinion['read_time']; ?> <?php echo $t['read_time']; ?>
                                    </span>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>
    </section>

    <!-- Author Guidelines Section -->
    <section id="guidelines" class="landing-content" style="background-color: var(--bg-slate); border-radius: 20px; padding: 60px 40px; margin-top: 40px; margin-bottom: 80px; border: 1px solid var(--border-color);">
        <div class="landing-section-header" style="margin-bottom: 40px; text-align: center;">
            <h2 style="font-size: 28px; font-weight: 800; color: var(--primary-navy); margin-bottom: 12px;"><?php echo $t['guidelines_title']; ?></h2>
            <p style="color: var(--text-muted); font-size: 15px;"><?php echo $t['guidelines_subtitle']; ?></p>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 30px;">
            <div style="background: white; padding: 24px; border-radius: 12px; border: 1px solid var(--border-color); box-shadow: var(--shadow-sm);">
                <div style="background: rgba(14, 165, 233, 0.1); width: 44px; height: 44px; border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-bottom: 16px; color: var(--accent-cyan);">
                    <i data-feather="layout"></i>
                </div>
                <h4 style="font-size: 16px; font-weight: 700; color: var(--primary-navy); margin-bottom: 8px;"><?php echo $t['g1_title']; ?></h4>
                <p style="font-size: 13px; color: var(--text-muted); line-height: 1.6;"><?php echo $t['g1_desc']; ?></p>
            </div>
            
            <div style="background: white; padding: 24px; border-radius: 12px; border: 1px solid var(--border-color); box-shadow: var(--shadow-sm);">
                <div style="background: rgba(14, 165, 233, 0.1); width: 44px; height: 44px; border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-bottom: 16px; color: var(--accent-cyan);">
                    <i data-feather="key"></i>
                </div>
                <h4 style="font-size: 16px; font-weight: 700; color: var(--primary-navy); margin-bottom: 8px;"><?php echo $t['g2_title']; ?></h4>
                <p style="font-size: 13px; color: var(--text-muted); line-height: 1.6;"><?php echo $t['g2_desc']; ?></p>
            </div>

            <div style="background: white; padding: 24px; border-radius: 12px; border: 1px solid var(--border-color); box-shadow: var(--shadow-sm);">
                <div style="background: rgba(14, 165, 233, 0.1); width: 44px; height: 44px; border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-bottom: 16px; color: var(--accent-cyan);">
                    <i data-feather="check-circle"></i>
                </div>
                <h4 style="font-size: 16px; font-weight: 700; color: var(--primary-navy); margin-bottom: 8px;"><?php echo $t['g3_title']; ?></h4>
                <p style="font-size: 13px; color: var(--text-muted); line-height: 1.6;"><?php echo $t['g3_desc']; ?></p>
            </div>
        </div>
    </section>

    <!-- Submission CTA Section -->
    <section class="landing-submission">
        <div class="landing-submission-banner">
            <div class="submission-deco"></div>
            <div class="landing-submission-content">
                <h2><?php echo $t['submit_title']; ?></h2>
                <p><?php echo $t['submit_desc']; ?></p>
                <a href="<?php echo $is_logged_in ? 'user/dashboard_user.php?action=upload' : 'user/login_user.php'; ?>" class="btn-submission-upload">
                    <i data-feather="upload-cloud"></i>
                    <?php echo $t['submit_btn']; ?>
                </a>
            </div>
        </div>
    </section>

    <!-- Footer Section -->
    <footer id="about" class="landing-footer">
        <div class="landing-footer-grid">
            <div class="footer-brand-col">
                <img src="assets/main_logo.png" alt="KSM Education Logo">
                <p>Platform publikasi dan database artikel ilmiah terpercaya untuk mendukung perkembangan ilmu pengetahuan dan inovasi riset.</p>
                <div class="footer-socials">
                    <a href="#"><i data-feather="instagram"></i></a>
                    <a href="#"><i data-feather="twitter"></i></a>
                    <a href="#"><i data-feather="facebook"></i></a>
                    <a href="#"><i data-feather="youtube"></i></a>
                </div>
            </div>

            <div class="footer-links-col">
                <h4>Quick Menu</h4>
                <ul>
                    <li><a href="#home">Home</a></li>
                    <li><a href="user/journals_user.php">Daftar Jurnal</a></li>
                    <li><a href="user/opinions_user.php">Opini & Berita</a></li>
                    <li><a href="#guidelines">Panduan Penulis</a></li>
                </ul>
            </div>

            <div class="footer-contact-col">
                <h4>Hubungi Kami</h4>
                <div class="footer-contact-item">
                    <i data-feather="mail"></i>
                    <span>ksmedu2025@google.com</span>
                </div>
                <div class="footer-contact-item">
                    <i data-feather="phone"></i>
                    <span>+6285814991897</span>
                </div>
                <div class="footer-contact-item">
                    <i data-feather="map-pin"></i>
                    <span>Jakarta, Indonesia</span>
                </div>
            </div>
        </div>

        <div class="landing-footer-bottom">
            <p>&copy; 2025 KSM Education. All rights reserved.</p>
            
            <!-- Secondary Language Selector in Footer -->
            <div class="landing-footer-bottom-links" style="display: flex; align-items: center; gap: 20px;">
                <div style="position: relative; display: inline-block;">
                    <button class="lang-btn" id="langBtnFooter" style="color: rgba(255, 255, 255, 0.7); border-color: rgba(255, 255, 255, 0.2); background: transparent;">
                        🌐 <?php echo $lang === 'en' ? 'EN' : 'ID'; ?> 
                        <i data-feather="chevron-down" style="width: 14px; height: 14px; vertical-align: middle;"></i>
                    </button>
                    <div class="lang-dropdown" id="langDropdownFooter" style="bottom: 100%; top: auto; right: 0; margin-bottom: 6px; margin-top: 0;">
                        <button class="lang-item" onclick="changeLanguage('id')">Bahasa (ID)</button>
                        <button class="lang-item" onclick="changeLanguage('en')">English (EN)</button>
                    </div>
                </div>
                <a href="#">Privacy Policy</a>
                <a href="#">Terms of Service</a>
            </div>
        </div>
    </footer>

    <!-- Interactive Scripts -->
    <script src="js/landing.js?v=202607161225"></script>
</body>
</html>

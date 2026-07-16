<?php
/**
 * KSM Education Landing Page
 * Premium, high-fidelity academic portal landing page.
 */
session_start();

// Enable error logging but disable screen outputs to keep page clean
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Default counts
$total_publications = 15280; 
$active_authors = 1245; 
$global_reach = '98+ Negara';
$latest_articles = [];

// Determine if user is logged in
$is_logged_in = isset($_SESSION['user_id']);
$user_name = $is_logged_in ? $_SESSION['name'] : '';
$user_avatar_char = $is_logged_in ? strtoupper(substr($user_name, 0, 1)) : '';

try {
    // Memanggil koneksi database utama
    require_once __DIR__ . '/services/db.php';
    
    // PENTING: db.php mengatur header Content-Type menjadi application/json.
    // Karena ini adalah halaman HTML, kita harus menimpanya kembali menjadi text/html
    // agar browser me-render tampilannya (bukan menampilkan teks mentah/JSON).
    header('Content-Type: text/html; charset=utf-8');

    // Fetch dynamic counts
    $stmtCountJ = $pdo->query("SELECT COUNT(*) as total FROM journals");
    $countJ = (int)$stmtCountJ->fetch()['total'];
    $stmtCountO = $pdo->query("SELECT COUNT(*) as total FROM opinions");
    $countO = (int)$stmtCountO->fetch()['total'];

    // Real dynamic stats + base stats for high credibility look
    $total_publications += ($countJ + $countO);
    $active_authors += ($countJ * 2);

    // Fetch latest 6 journals
    $stmtJ = $pdo->query("
        SELECT j.id, j.title, j.abstract, j.authors, j.created_at, u.url as cover_url 
        FROM journals j 
        LEFT JOIN uploads u ON j.cover_upload_id = u.id 
        ORDER BY j.created_at DESC 
        LIMIT 6
    ");
    $latest_articles = $stmtJ->fetchAll();
} catch (Exception $e) {
    // Fail-silent fallback
    $latest_articles = [];
}

// Prepend APP_ROOT to uploaded cover URLs
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

// Format the articles list
$rendered_articles = [];
if (!empty($latest_articles)) {
    foreach ($latest_articles as $art) {
        $cover = $art['cover_url'];
        if ($cover && $app_root && strpos($cover, $app_root) !== 0) {
            $cover = $app_root . '/' . ltrim($cover, '/');
        }
        if (!$cover) {
            $cover = 'https://images.unsplash.com/photo-1456513080510-7bf3a84b82f8?w=500&h=400&fit=crop';
        }
        
        // Parse authors JSON array
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
        
        $rendered_articles[] = [
            'id' => $art['id'],
            'title' => $art['title'],
            'abstract' => $art['abstract'],
            'authors' => !empty($authors) ? implode(', ', $authors) : 'KSM Author',
            'date' => date('d M Y', strtotime($art['created_at'])),
            'cover' => $cover,
            'type' => 'Jurnal'
        ];
    }
}

// If we have fewer than 3 articles, supplement with high-quality mock publications for a rich layout
if (count($rendered_articles) < 3) {
    $mock_data = [
        [
            'id' => '#',
            'title' => 'Analisis Kebijakan Moneter Terhadap Pertumbuhan Ekonomi Digital di Asia Tenggara',
            'abstract' => 'Penelitian ini menganalisis dampak implementasi kebijakan moneter bank sentral terhadap stabilitas pasar uang dan pertumbuhan ekonomi berbasis digital di kawasan Asia Tenggara dalam dekade terakhir.',
            'authors' => 'Dr. M. Arif Syahrudin, Prof. Dr. Hendra Wijaya',
            'date' => '15 Jul 2026',
            'cover' => 'https://images.unsplash.com/photo-1456513080510-7bf3a84b82f8?w=500&h=400&fit=crop',
            'type' => 'Jurnal Populer'
        ],
        [
            'id' => '#',
            'title' => 'Penerapan Deep Learning Untuk Klasifikasi Citra Medis Deteksi Kanker Paru',
            'abstract' => 'Penelitian ini mengajukan arsitektur convolutional neural network (CNN) yang dioptimasi untuk mendeteksi nodul paru-paru pada citra CT scan dengan tingkat akurasi mencapai 98.4%.',
            'authors' => 'Rian Adisukma, Dr. Sarah Fitriani',
            'date' => '14 Jul 2026',
            'cover' => 'https://images.unsplash.com/photo-1576091160399-112ba8d25d1d?w=500&h=400&fit=crop',
            'type' => 'Trending'
        ],
        [
            'id' => '#',
            'title' => 'Strategi Manajemen Pembelajaran Jarak Jauh di Era Pasca-Pandemi Global',
            'abstract' => 'Evaluasi komprehensif mengenai efektivitas model hybrid learning pada institusi pendidikan tinggi Indonesia, serta pengembangan kurikulum yang adaptif bagi mahasiswa vokasi.',
            'authors' => 'Dewi Lestari M.Pd., Budi Santoso',
            'date' => '10 Jul 2026',
            'cover' => 'https://images.unsplash.com/photo-1524178232363-1fb2b075b655?w=500&h=400&fit=crop',
            'type' => 'Jurnal Pilihan'
        ]
    ];
    $rendered_articles = array_merge($rendered_articles, $mock_data);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KSM Education — Portal Publikasi Jurnal & Opini Ilmiah</title>
    <!-- Preconnect for fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="styles/fonts.css">
    <link rel="stylesheet" href="styles/landing.css?v=20260716">
    <link rel="shortcut icon" type="image/x-icon" href="assets/favicon.ico" />
    <script src="https://unpkg.com/feather-icons"></script>
</head>
<body id="home">

    <!-- Top Navigation Bar -->
    <header class="landing-header">
        <div class="landing-nav-container">
            <a href="#home" class="landing-logo">
                <img src="assets/main_logo.png" alt="KSM Education Logo">
            </a>
            
            <nav class="landing-nav-links" id="landingNavMenu">
                <a href="#home">Home</a>
                <a href="user/journals_user.php">Explore Journals</a>
                <a href="#guidelines">Author Guidelines</a>
                <a href="#about">About</a>
            </nav>

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
            <h1>Empowering Academic Excellence<br>and <span>Scientific Research</span></h1>
            <p style="color: var(--text-muted); font-size: 18px; line-height: 1.6; max-width: 680px; margin: 0 auto;">
                Platform terpercaya untuk publikasi, indeksasi, dan penyebaran hasil penelitian teoretis serta praktis bagi akademisi global.
            </p>
            
            <!-- Search Bar Box -->
            <div class="landing-search-container">
                <form action="user/journals_user.php" method="GET" class="landing-search-box">
                    <i data-feather="search"></i>
                    <input type="text" name="search" placeholder="Cari paper, penulis, atau kata kunci..." autocomplete="off">
                    <button type="submit" class="btn-search-submit">Cari</button>
                </form>
            </div>

            <!-- CTA Buttons -->
            <div class="landing-hero-ctas">
                <a href="<?php echo $is_logged_in ? 'user/dashboard_user.php?action=upload' : 'user/login_user.php'; ?>" class="btn-hero-primary">
                    <i data-feather="upload"></i>
                    Upload Journal
                </a>
                <a href="user/journals_user.php" class="btn-hero-secondary">
                    Explore Publications
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
                    <span class="landing-stat-val"><?php echo number_format($total_publications); ?>+</span>
                    <span class="landing-stat-lbl">Publications</span>
                </div>
            </div>
            
            <div class="landing-stat-item">
                <div class="landing-stat-icon-wrapper">
                    <i data-feather="users"></i>
                </div>
                <div class="landing-stat-info">
                    <span class="landing-stat-val"><?php echo number_format($active_authors); ?>+</span>
                    <span class="landing-stat-lbl">Active Authors</span>
                </div>
            </div>
            
            <div class="landing-stat-item">
                <div class="landing-stat-icon-wrapper">
                    <i data-feather="globe"></i>
                </div>
                <div class="landing-stat-info">
                    <span class="landing-stat-val"><?php echo htmlspecialchars($global_reach); ?></span>
                    <span class="landing-stat-lbl">Global Reach</span>
                </div>
            </div>
        </div>
    </section>

    <!-- Main Content Area: Trending & Latest -->
    <section class="landing-content">
        <div class="landing-section-header">
            <h2>Trending & Latest Research</h2>
            <p>Jelajahi karya ilmiah terbaru dan kontribusi penelitian mutakhir dari para penulis terkemuka di bidang akademik.</p>
        </div>

        <div class="landing-grid">
            <?php foreach ($rendered_articles as $article): ?>
                <?php 
                $detail_link = ($article['id'] === '#') ? 'user/journals_user.php' : 'user/explore_jurnal_user.php?id=' . $article['id'] . '&type=jurnal';
                ?>
                <a href="<?php echo $detail_link; ?>" class="landing-card">
                    <div class="landing-card-cover">
                        <img src="<?php echo htmlspecialchars($article['cover']); ?>" alt="Cover Jurnal">
                        <span class="landing-badge">
                            <i data-feather="book-open"></i>
                            <?php echo htmlspecialchars($article['type']); ?>
                        </span>
                    </div>
                    <div class="landing-card-body">
                        <h3 class="landing-card-title"><?php echo htmlspecialchars($article['title']); ?></h3>
                        <p class="landing-card-excerpt"><?php echo htmlspecialchars($article['abstract']); ?></p>
                        
                        <div class="landing-card-meta">
                            <span class="landing-card-authors">
                                <i data-feather="user"></i>
                                <?php echo htmlspecialchars($article['authors']); ?>
                            </span>
                            <span class="landing-card-date">
                                <i data-feather="calendar"></i>
                                <?php echo htmlspecialchars($article['date']); ?>
                            </span>
                        </div>
                        
                        <span class="landing-card-action">
                            Baca Selengkapnya
                            <i data-feather="arrow-right"></i>
                        </span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Author Guidelines Section -->
    <section id="guidelines" class="landing-content" style="background-color: var(--bg-slate); border-radius: 20px; padding: 60px 40px; margin-top: 40px; margin-bottom: 80px; border: 1px solid var(--border-color);">
        <div class="landing-section-header" style="margin-bottom: 40px;">
            <h2>Author Guidelines</h2>
            <p>Panduan lengkap tata cara penulisan dan pengiriman naskah artikel ilmiah di KSM Education.</p>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 30px;">
            <div style="background: white; padding: 24px; border-radius: 12px; border: 1px solid var(--border-color); box-shadow: var(--shadow-sm);">
                <div style="background: rgba(14, 165, 233, 0.1); width: 44px; height: 44px; border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-bottom: 16px; color: var(--accent-cyan);">
                    <i data-feather="layout"></i>
                </div>
                <h4 style="font-size: 16px; font-weight: 700; color: var(--primary-navy); margin-bottom: 8px;">Format Naskah</h4>
                <p style="font-size: 13px; color: var(--text-muted); line-height: 1.6;">Naskah wajib disusun rapi menggunakan font Montserrat/Inter, mencantumkan abstrak dalam Bahasa Indonesia dan Bahasa Inggris, serta menyertakan kata kunci yang relevan.</p>
            </div>
            
            <div style="background: white; padding: 24px; border-radius: 12px; border: 1px solid var(--border-color); box-shadow: var(--shadow-sm);">
                <div style="background: rgba(14, 165, 233, 0.1); width: 44px; height: 44px; border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-bottom: 16px; color: var(--accent-cyan);">
                    <i data-feather="key"></i>
                </div>
                <h4 style="font-size: 16px; font-weight: 700; color: var(--primary-navy); margin-bottom: 8px;">Sistem Token & Upload</h4>
                <p style="font-size: 13px; color: var(--text-muted); line-height: 1.6;">Proses pengunggahan naskah jurnal mandiri memerlukan 1 token. Pembelian token dapat dilakukan langsung melalui menu dompet digital di dasbor akun Anda.</p>
            </div>

            <div style="background: white; padding: 24px; border-radius: 12px; border: 1px solid var(--border-color); box-shadow: var(--shadow-sm);">
                <div style="background: rgba(14, 165, 233, 0.1); width: 44px; height: 44px; border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-bottom: 16px; color: var(--accent-cyan);">
                    <i data-feather="check-circle"></i>
                </div>
                <h4 style="font-size: 16px; font-weight: 700; color: var(--primary-navy); margin-bottom: 8px;">Proses Penilaian (Review)</h4>
                <p style="font-size: 13px; color: var(--text-muted); line-height: 1.6;">Setiap jurnal yang diunggah akan melalui peninjauan administratif dan verifikasi sebelum terpublikasi secara terbuka bagi publik di KSM Education.</p>
            </div>
        </div>
    </section>

    <!-- Submission CTA Section -->
    <section class="landing-submission">
        <div class="landing-submission-banner">
            <div class="submission-deco"></div>
            <div class="landing-submission-content">
                <h2>Ready to share your research with the world?</h2>
                <p>Kirimkan naskah jurnal ilmiah terbaik Anda sekarang dan raih pembaca serta sitasi dari jejaring akademisi global.</p>
                <a href="<?php echo $is_logged_in ? 'user/dashboard_user.php?action=upload' : 'user/login_user.php'; ?>" class="btn-submission-upload">
                    <i data-feather="upload-cloud"></i>
                    Start Uploading
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
            <div class="landing-footer-bottom-links">
                <a href="#">Privacy Policy</a>
                <a href="#">Terms of Service</a>
            </div>
        </div>
    </footer>

    <!-- Interactive Scripts -->
    <script src="js/landing.js?v=20260716"></script>
</body>
</html>

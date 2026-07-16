<?php
/**
 * services/landing_controller.php
 * Controller logic for the KSM Education Landing Page.
 * Handles language selection, translations, database connection, statistics, and articles lists.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
        'submit_btn' => 'Mulai Mengunggah',
        'footer_about_desc' => 'Platform publikasi dan database artikel ilmiah terpercaya untuk mendukung perkembangan ilmu pengetahuan dan inovasi riset.',
        'footer_quick_menu' => 'Menu Cepat',
        'footer_contact_us' => 'Hubungi Kami',
        'footer_contact_address' => 'Jakarta, Indonesia'
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
        'submit_btn' => 'Start Uploading',
        'footer_about_desc' => 'A trusted publication platform and scientific article database to support the development of science and research innovation.',
        'footer_quick_menu' => 'Quick Menu',
        'footer_contact_us' => 'Contact Us',
        'footer_contact_address' => 'Jakarta, Indonesia'
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
    require_once __DIR__ . '/db.php';
    
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
$_db_abs = realpath(__DIR__ . '/db.php');
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

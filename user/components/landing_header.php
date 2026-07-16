<?php
/**
 * user/components/landing_header.php
 * Header component for the landing page.
 */
$lang = $lang ?? 'id';
$t = $t ?? [];
$is_logged_in = $is_logged_in ?? false;
$user_avatar_char = $user_avatar_char ?? '';
$user_name = $user_name ?? '';
?>
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

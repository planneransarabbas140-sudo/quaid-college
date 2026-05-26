<?php
/**
 * Shared navbar for public marketing pages.
 * Set $public_nav_active before include: home|about|programs|admissions|news|contact|campuses
 */
if (!isset($campus_stats)) {
    require_once __DIR__ . '/site-stats.php';
}
$public_nav_active = $public_nav_active ?? '';
$public_brand_subtitle = $public_brand_subtitle ?? 'Group of Colleges';
$public_campus_key = $public_campus_key ?? '';

if (!function_exists('qgc_nav_active')) {
    function qgc_nav_active(string $page): string {
        global $public_nav_active;
        return $public_nav_active === $page ? ' active' : '';
    }
}

if (!function_exists('qgc_campus_nav_current')) {
    function qgc_campus_nav_current(string $key): string {
        global $public_campus_key;
        return $public_campus_key === $key ? ' is-current' : '';
    }
}
?>
<nav class="navbar navbar-expand-lg navbar-campus">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-3" href="index.php">
            <span class="brand-mark"><img src="assets/images/qgc-logo-nav.png" alt="Quaid-e-Azam Group of Colleges logo" loading="eager" fetchpriority="high" decoding="sync" width="48" height="48"></span>
            <span>
                <span class="brand-title">Quaid-e-Azam</span>
                <span class="brand-subtitle"><?= htmlspecialchars($public_brand_subtitle) ?></span>
            </span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#siteNav" aria-controls="siteNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="siteNav">
            <div class="navbar-nav mx-auto align-items-lg-center gap-lg-2">
                <a class="nav-link-campus<?= qgc_nav_active('home') ?>" href="index.php">Home</a>
                <a class="nav-link-campus<?= qgc_nav_active('about') ?>" href="about.php">About</a>
                <div class="campus-dropdown">
                    <?php
                    $campus_nav_href = !empty($public_campus_key) && isset($campus_stats[$public_campus_key])
                        ? $campus_stats[$public_campus_key]['url']
                        : 'campus-rajanpur.php';
                    ?>
                    <a class="nav-link-campus<?= qgc_nav_active('campuses') ?>" href="<?= htmlspecialchars($campus_nav_href) ?>">Campuses <i class="fa-solid fa-chevron-down ms-1" style="font-size:.7rem;"></i></a>
                    <div class="campus-dropdown-menu">
                        <a href="campus-rajanpur.php" class="<?= trim(qgc_campus_nav_current('rajanpur')) ?>"><span class="campus-item-title">Misbah Campus <span class="campus-type main">MAIN</span></span><span class="campus-item-meta">Rajanpur — Main Campus</span></a>
                        <a href="campus-fazilpur.php" class="<?= trim(qgc_campus_nav_current('fazilpur')) ?>"><span class="campus-item-title">Hamid Campus <span class="campus-type">SUB</span></span><span class="campus-item-meta">Fazilpur — Sub Campus</span></a>
                        <a href="campus-kotmithan.php" class="<?= trim(qgc_campus_nav_current('kotmithan')) ?>"><span class="campus-item-title">Abul Rehman Campus <span class="campus-type">SUB</span></span><span class="campus-item-meta">Kot Mithan — Sub Campus</span></a>
                    </div>
                </div>
                <a class="nav-link-campus<?= qgc_nav_active('programs') ?>" href="programs.php">Programs</a>
                <a class="nav-link-campus<?= qgc_nav_active('admissions') ?>" href="modules/admissions/apply.php">Admissions</a>
                <a class="nav-link-campus<?= qgc_nav_active('news') ?>" href="news.php">News</a>
                <a class="nav-link-campus<?= qgc_nav_active('contact') ?>" href="contact.php">Contact</a>
            </div>
            <?php if (!empty($public_nav_search)): ?>
            <button type="button" class="nav-search-btn me-lg-2 mt-3 mt-lg-0" onclick="typeof toggleSearch === 'function' && toggleSearch()" aria-label="Search site">
                <i class="fa-solid fa-search"></i>
            </button>
            <?php endif; ?>
            <a class="portal-btn mt-3 mt-lg-0" href="modules/auth/login.php"><i class="fa-solid fa-lock"></i><span>Portal Login</span></a>
        </div>
    </div>
</nav>

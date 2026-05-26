<?php
$page_title = 'News';
require_once __DIR__ . '/includes/site-stats.php';
$public_nav_active = 'news';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> | Quaid-e-Azam Group of Colleges</title>
    <link rel="icon" type="image/png" href="assets/images/qgc-logo-nav.png">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/public-site.css?v=4" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { margin:0; font-family:Poppins,sans-serif; background:var(--light); color:var(--navy); }
        a { text-decoration:none; }
        .home-btn { display:inline-flex; align-items:center; justify-content:center; gap:8px; border-radius:999px; padding:11px 20px; background:var(--teal); color:var(--white); font-weight:800; transition:300ms ease; }
        .home-btn:hover { color:var(--white); transform:translateY(-2px); background:#33c8b1; }
        .coming-soon { min-height:calc(100vh - 274px); display:grid; place-items:center; padding:90px 20px; background:radial-gradient(circle at 15% 15%, rgba(42,181,160,.14), transparent 30%), linear-gradient(135deg,#f8fafc,#eef3f8); }
        .soon-card { max-width:760px; width:100%; text-align:center; background:var(--white); border-radius:14px; padding:64px 34px; box-shadow:0 24px 70px rgba(26,47,74,.12); border-top:4px solid var(--teal); }
        .soon-card .badge { background:rgba(245,166,35,.15); color:var(--navy); border:1px solid rgba(245,166,35,.35); }
        .soon-card h1 { margin:18px 0 10px; font-size:clamp(2.4rem,6vw,4.8rem); font-weight:900; color:var(--navy); }
        .soon-card p { color:var(--muted); font-size:1.08rem; margin-bottom:28px; }
        .news-actions { display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)); gap:14px; margin:30px 0 28px; text-align:left; }
        .news-action-card { border:1px solid #dce3ef; border-radius:12px; padding:18px; color:var(--navy); background:#fff; transition:300ms ease; }
        .news-action-card:hover { transform:translateY(-4px); border-color:var(--teal); box-shadow:0 16px 38px rgba(26,47,74,.12); color:var(--navy); }
        .news-action-card i { width:38px; height:38px; border-radius:10px; display:inline-grid; place-items:center; background:rgba(42,181,160,.13); color:var(--teal); margin-bottom:12px; }
        .news-action-card strong { display:block; font-size:.92rem; margin-bottom:5px; }
        .news-action-card span { display:block; color:var(--muted); font-size:.78rem; line-height:1.55; }
        @media (max-width:991px) { .news-actions { grid-template-columns:1fr; } }
    </style>
</head>
<body class="modern-ui">
    <?php require __DIR__ . '/includes/public-nav.php'; ?>
    <main class="coming-soon">
        <section class="soon-card">
            <span class="badge rounded-pill px-3 py-2"><?= $page_title ?></span>
            <h1>News Desk</h1>
            <p>This page is under development. Until the full news archive is published, use these quick links for the most requested updates.</p>
            <div class="news-actions">
                <a class="news-action-card" href="modules/admissions/apply.php">
                    <i class="fa-solid fa-graduation-cap"></i>
                    <strong>Admissions 2026</strong>
                    <span>Apply online for FA, FSc, ICS, BS, ADP, B.Ed and NAVTTC programs.</span>
                </a>
                <a class="news-action-card" href="modules/examination/index.php">
                    <i class="fa-solid fa-calendar-check"></i>
                    <strong>Exam Schedule</strong>
                    <span>View examination notices and schedule updates from the college office.</span>
                </a>
                <a class="news-action-card" href="modules/student/index.php">
                    <i class="fa-solid fa-list-check"></i>
                    <strong>Merit Lists</strong>
                    <span>Check student updates, merit lists, and batch announcements.</span>
                </a>
            </div>
            <a href="index.php" class="home-btn"><i class="fa-solid fa-arrow-left"></i> Back to Home</a>
        </section>
    </main>
    <?php require __DIR__ . '/includes/public-footer.php'; ?>
</body>
</html>

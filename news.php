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
    <link href="assets/css/public-site.css?v=5" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { margin:0; font-family:Poppins,sans-serif; background:#f8fafc; color:var(--navy); }
        a { text-decoration:none; }
        .section-kicker { color:var(--teal); font-weight:900; text-transform:uppercase; font-size:.76rem; letter-spacing:.12em; margin-bottom:10px; }
        .section-title { font-size:clamp(2rem,4vw,3.25rem); font-weight:900; margin:0; }
        .lead-text { color:var(--muted); line-height:1.85; }
        .news-grid { margin-top:28px; }
        .notice-list { display:grid; gap:12px; margin-top:22px; }
        .notice-list a { display:flex; justify-content:space-between; gap:16px; align-items:center; border:1px solid rgba(15,45,72,.1); border-radius:14px; padding:14px 16px; color:var(--navy); background:#fff; }
        .notice-list a:hover { border-color:rgba(42,181,160,.45); box-shadow:0 14px 36px rgba(15,45,72,.08); }
        .notice-list span { color:var(--muted); font-size:.8rem; font-weight:800; white-space:nowrap; }
        @media (max-width:575px) { .notice-list a { display:block; } .notice-list span { display:block; margin-top:8px; } }
    </style>
</head>
<body class="modern-ui">
    <?php require __DIR__ . '/includes/public-nav.php'; ?>
    <main>
        <header class="premium-news-hero">
            <div class="container">
                <div class="section-kicker">News and Notices</div>
                <h1>College updates, admissions alerts, and student notices in one place.</h1>
                <p>Follow important academic announcements, admissions activity, exam updates, campus events, and student support notices from Quaid-e-Azam Group of Colleges.</p>
                <div class="announcement-strip">
                    <strong>Latest</strong>
                    <span>Admissions guidance for the 2026 batch is open across Rajanpur, Fazilpur, and Kot Mithan campuses.</span>
                </div>
            </div>
        </header>

        <section class="premium-news-shell">
            <div class="container">
                <div class="row align-items-end g-4">
                    <div class="col-lg-7">
                        <div class="section-kicker">Featured Desk</div>
                        <h2 class="section-title">Important updates.</h2>
                    </div>
                    <div class="col-lg-5">
                        <p class="lead-text mb-0">Quick access to the notices students and parents ask for most often, with a cleaner archive layout ready for future dynamic posts.</p>
                    </div>
                </div>

                <div class="row g-4 news-grid">
                    <div class="col-lg-7">
                        <article class="premium-feature-card">
                            <span class="badge rounded-pill px-3 py-2">Admissions</span>
                            <h2 class="mt-3 mb-3">Admissions 2026 inquiry support is live.</h2>
                            <p>Students can apply online or contact the nearest campus office for eligibility, document checklist, fee guidance, and program availability.</p>
                            <div class="news-filter-bar">
                                <span>FA / FSc</span>
                                <span>ICS / I.Com</span>
                                <span>BS / ADP</span>
                                <span>NAVTTC</span>
                            </div>
                            <div class="d-flex flex-wrap gap-3 mt-4">
                                <a href="modules/admissions/apply.php" class="btn-teal">Apply Online <i class="fa-solid fa-arrow-right"></i></a>
                                <a href="programs.php" class="btn-outline-darkish">Explore Programs</a>
                            </div>
                        </article>
                    </div>
                    <div class="col-lg-5">
                        <div class="premium-feature-card">
                            <span class="badge rounded-pill px-3 py-2">Notice Board</span>
                            <div class="notice-list">
                                <a href="modules/examination/index.php"><strong>Exam schedule and results desk</strong><span>Academic</span></a>
                                <a href="modules/student/index.php"><strong>Student records and merit updates</strong><span>Students</span></a>
                                <a href="contact.php"><strong>Campus office contact timings</strong><span>Support</span></a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-4 news-grid">
                    <div class="col-md-6 col-xl-4">
                        <article class="premium-news-card">
                            <span class="badge rounded-pill px-3 py-2">Campus Life</span>
                            <div class="news-meta"><span><i class="fa-regular fa-calendar"></i> May 2026</span><span>All Campuses</span></div>
                            <h3>Student support counters active for new admissions.</h3>
                            <p>Dedicated teams are available to guide students through program selection, documentation, and admission form submission.</p>
                            <a href="contact.php" class="program-apply">Contact Campus <i class="fa-solid fa-arrow-right"></i></a>
                        </article>
                    </div>
                    <div class="col-md-6 col-xl-4">
                        <article class="premium-news-card">
                            <span class="badge rounded-pill px-3 py-2">Academics</span>
                            <div class="news-meta"><span><i class="fa-regular fa-calendar"></i> May 2026</span><span>Exam Desk</span></div>
                            <h3>Regular test planning continues for board classes.</h3>
                            <p>Intermediate students receive structured preparation cycles with performance review and academic follow-up.</p>
                            <a href="programs.php" class="program-apply">View Programs <i class="fa-solid fa-arrow-right"></i></a>
                        </article>
                    </div>
                    <div class="col-md-6 col-xl-4">
                        <article class="premium-news-card">
                            <span class="badge rounded-pill px-3 py-2">Skills</span>
                            <div class="news-meta"><span><i class="fa-regular fa-calendar"></i> May 2026</span><span>NAVTTC</span></div>
                            <h3>Short skill programs open for digital career tracks.</h3>
                            <p>Students can explore web development, mobile apps, digital marketing, UI/UX, graphics, and AI-assisted work skills.</p>
                            <a href="programs.php#program-list" class="program-apply">Find Skills <i class="fa-solid fa-arrow-right"></i></a>
                        </article>
                    </div>
                </div>
            </div>
        </section>
    </main>
    <?php require __DIR__ . '/includes/public-footer.php'; ?>
</body>
</html>

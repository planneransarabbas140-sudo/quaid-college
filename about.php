<?php
$page_title = 'About';
require_once __DIR__ . '/includes/site-stats.php';
$public_nav_active = 'about';
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
        * { box-sizing:border-box; }
        body { margin:0; font-family:Poppins,sans-serif; color:var(--navy); background:#fff; }
        a { text-decoration:none; }
        .hero { padding:96px 0 72px; color:#fff; background:radial-gradient(circle at 14% 20%, rgba(42,181,160,.28), transparent 36%), linear-gradient(135deg,var(--navy2),var(--navy)); overflow:hidden; transform-style:preserve-3d; }
        .eyebrow { display:inline-flex; align-items:center; gap:8px; color:var(--teal); font-size:.78rem; font-weight:900; letter-spacing:.12em; text-transform:uppercase; margin-bottom:18px; }
        .hero h1 { font-size:clamp(2.2rem,4.8vw,3.6rem); line-height:1.08; font-weight:900; letter-spacing:0; margin:0 0 22px; max-width:820px; }
        .hero p { color:rgba(255,255,255,.76); font-size:1.04rem; line-height:1.8; max-width:720px; margin:0; }
        section { padding:86px 0; }
        .section-title { font-size:clamp(2rem,4vw,3.2rem); font-weight:900; margin:0; }
        .section-kicker { color:var(--teal); font-weight:900; text-transform:uppercase; font-size:.76rem; letter-spacing:.12em; margin-bottom:10px; }
        .lead-text { color:var(--muted); line-height:1.85; }
        .value-card, .campus-card, .leader-card { height:100%; background:#fff; border:1px solid var(--line); border-radius:12px; padding:26px; box-shadow:0 18px 50px rgba(26,47,74,.07); transition:300ms ease; }
        .value-card:hover, .campus-card:hover, .leader-card:hover { transform:translateY(-5px); border-color:rgba(42,181,160,.45); box-shadow:0 24px 70px rgba(26,47,74,.12); }
        .icon-box { width:48px; height:48px; border-radius:12px; display:grid; place-items:center; background:rgba(42,181,160,.13); color:var(--teal); font-size:1.2rem; margin-bottom:18px; }
        .value-card h3, .campus-card h3, .leader-card h3 { font-size:1.08rem; font-weight:900; margin-bottom:10px; }
        .value-card p, .campus-card p, .leader-card p { color:var(--muted); line-height:1.75; margin:0; font-size:.92rem; }
        .band { background:var(--light); }
        .campus-badge { display:inline-flex; border-radius:999px; padding:5px 10px; background:rgba(42,181,160,.12); color:var(--teal); font-size:.68rem; font-weight:900; letter-spacing:.06em; margin-bottom:12px; }
        .campus-badge.main { background:rgba(245,166,35,.2); color:#9a6308; }
        .leader-img { width:92px; height:92px; border-radius:50%; object-fit:cover; object-position:center top; border:3px solid var(--teal); box-shadow:0 10px 26px rgba(0,0,0,.15); margin-bottom:18px; }
        .timeline-item { display:flex; gap:18px; padding:20px 0; border-bottom:1px solid var(--line); }
        .timeline-year { flex:0 0 82px; color:var(--teal); font-weight:900; }
        .timeline-item h4 { font-size:1rem; font-weight:900; margin:0 0 6px; }
        .timeline-item p { margin:0; color:var(--muted); line-height:1.7; }
        .cta { background:linear-gradient(135deg,var(--navy),#0c3547); color:#fff; padding:64px 0; }
        .cta h2 { font-weight:900; margin:0 0 10px; }
        @media (max-width:991px) { .hero { padding:80px 0; } }
        @media (max-width:575px) {
            .hero { padding:48px 0 40px; }
            .hero h1 { font-size:clamp(1.6rem,6.5vw,2.4rem); max-width:100%; }
            .hero p { font-size:0.95rem; max-width:100%; }
            .hero .d-flex { flex-direction:column; align-items:stretch; }
            .hero .d-flex .btn-teal { width:100%; justify-content:center; padding:12px 14px; }
            .leader-img { width:72px; height:72px; }
            .timeline-item { flex-direction:column; gap:8px; }
            .timeline-year { flex:0 0 auto; }
        }
        @media (max-width:768px) {
            .row.g-4,
            .row.g-5 { --bs-gutter-y: 1rem; }
            .value-card,
            .campus-card,
            .leader-card { min-height:auto; }
            .lead-text { margin-top:4px; }
            .leader-card { text-align:left; }
            .leader-card:first-child { text-align:center; }
            .timeline-item {
                padding:16px 0;
                gap:10px;
            }
            .timeline-year {
                display:inline-flex;
                width:max-content;
                padding:5px 10px;
                border-radius:999px;
                background:rgba(42,181,160,.12);
            }
            .timeline-item h4 {
                font-size:.96rem;
                line-height:1.35;
            }
            .timeline-item p {
                font-size:.88rem;
                line-height:1.65;
            }
            .cta .text-lg-end {
                text-align:left !important;
            }
        }
    </style>
</head>
<body class="modern-ui">
    <?php require __DIR__ . '/includes/public-nav.php'; ?>

    <header class="hero">
        <div class="container">
            <div class="eyebrow"><i class="fa-solid fa-star"></i> About QGC</div>
            <h1>Building disciplined, future-ready students in South Punjab.</h1>
            <p>Quaid-e-Azam Group of Colleges provides affordable, structured, and career-focused education through intermediate, degree, professional, and NAVTTC skill programs across Rajanpur, Fazilpur, and Kot Mithan.</p>
            <div class="d-flex flex-wrap gap-3 mt-4">
                <a href="modules/admissions/apply.php" class="btn-teal">Apply Now <i class="fa-solid fa-arrow-right"></i></a>
                <a href="contact.php" class="btn-teal" style="background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.22);">Contact Office</a>
            </div>
        </div>
    </header>

    <section>
        <div class="container">
            <div class="row align-items-center g-5">
                <div class="col-lg-5">
                    <div class="section-kicker">Who We Are</div>
                    <h2 class="section-title">A group of campuses with one academic standard.</h2>
                </div>
                <div class="col-lg-7">
                    <p class="lead-text mb-0">QGC is designed for families who want strong academic discipline, accessible campuses, experienced faculty, and a clear route from classroom learning to professional opportunity. Our academic model combines board-focused preparation, degree pathways, technology programs, and skill-based training for the changing job market.</p>
                </div>
            </div>
            <div class="row g-4 mt-4">
                <div class="col-md-4"><div class="value-card"><div class="icon-box"><i class="fa-solid fa-book-open"></i></div><h3>Academic Discipline</h3><p>Structured classes, regular testing, and performance tracking help students stay focused and accountable.</p></div></div>
                <div class="col-md-4"><div class="value-card"><div class="icon-box"><i class="fa-solid fa-laptop-code"></i></div><h3>Modern Programs</h3><p>From FA/FSc and BS programs to web development, AI, UI/UX, and digital marketing, students learn relevant skills.</p></div></div>
                <div class="col-md-4"><div class="value-card"><div class="icon-box"><i class="fa-solid fa-people-group"></i></div><h3>Student Support</h3><p>Faculty, administration, and campus leadership work together to guide students through admissions, academics, and growth.</p></div></div>
            </div>
        </div>
    </section>

    <section class="band">
        <div class="container">
            <div class="text-center mb-5">
                <div class="section-kicker">Campuses</div>
                <h2 class="section-title">Our Campus Network</h2>
            </div>
            <div class="row g-4">
                <div class="col-lg-4"><a class="campus-card d-block" href="campus-rajanpur.php"><span class="campus-badge main">MAIN CAMPUS</span><h3>Misbah Campus</h3><p>Rajanpur campus serving as the central academic hub of Quaid-e-Azam Group of Colleges.</p></a></div>
                <div class="col-lg-4"><a class="campus-card d-block" href="campus-fazilpur.php"><span class="campus-badge">SUB CAMPUS</span><h3>Hamid Campus</h3><p>Fazilpur campus offering intermediate, degree, and NAVTTC programs close to local families.</p></a></div>
                <div class="col-lg-4"><a class="campus-card d-block" href="campus-kotmithan.php"><span class="campus-badge">SUB CAMPUS</span><h3>Abul Rehman Campus</h3><p>Kotmithan campus focused on accessible education, discipline, and practical student development.</p></a></div>
            </div>
        </div>
    </section>

    <section>
        <div class="container">
            <div class="row g-4 align-items-stretch">
                <div class="col-lg-4"><div class="leader-card"><img src="assets/images/saif%20shb%20%281%29.png" class="leader-img" alt="Ch. Saif Ullah"><h3>Ch. Saif Ullah</h3><p><strong>Chairman & Founder</strong><br>His vision is to provide disciplined, accessible, and purposeful education for students across South Punjab.</p></div></div>
                <div class="col-lg-8"><div class="leader-card"><div class="section-kicker">Our Journey</div><div class="timeline-item"><div class="timeline-year">2015</div><div><h4>Misbah Campus Rajanpur established</h4><p>The foundation campus opened with a mission to raise educational standards in the region.</p></div></div><div class="timeline-item"><div class="timeline-year">2019</div><div><h4>Hamid Campus Fazilpur launched</h4><p>QGC expanded access to intermediate, degree, and skill programs for Fazilpur students.</p></div></div><div class="timeline-item"><div class="timeline-year">2020</div><div><h4>Abul Rehman Campus Kotmithan added</h4><p>The campus network grew to serve more families with the same academic discipline and standards.</p></div></div></div></div>
            </div>
        </div>
    </section>

    <section class="cta">
        <div class="container">
            <div class="row align-items-center g-4">
                <div class="col-lg-8"><h2>Ready to become part of QGC?</h2><p class="mb-0" style="color:rgba(255,255,255,.72);">Admissions are open for the 2026 batch across intermediate, degree, and NAVTTC programs.</p></div>
                <div class="col-lg-4 text-lg-end"><a href="modules/admissions/apply.php" class="btn-teal">Apply Online</a></div>
            </div>
        </div>
    </section>

    <?php require __DIR__ . '/includes/public-footer.php'; ?>
</body>
</html>

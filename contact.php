<?php
$page_title = 'Contact';
require_once __DIR__ . '/includes/site-stats.php';
$public_nav_active = 'contact';
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
        * { box-sizing:border-box; }
        body { margin:0; font-family:Poppins,sans-serif; color:var(--navy); background:#fff; }
        a { text-decoration:none; }
        .hero { padding:100px 0 78px; color:#fff; background:radial-gradient(circle at 14% 20%, rgba(42,181,160,.28), transparent 36%), linear-gradient(135deg,var(--navy2),var(--navy)); overflow:hidden; transform-style:preserve-3d; }
        .hero-metrics { display:flex; flex-wrap:wrap; gap:20px; margin-top:28px; }
        .hero-metric { border:1px solid rgba(255,255,255,.14); background:rgba(255,255,255,.08); border-radius:10px; padding:14px 18px; min-width:120px; }
        .hero-metric strong { display:block; color:#fff; font-size:1.35rem; line-height:1; }
        .hero-metric span { display:block; margin-top:6px; color:rgba(255,255,255,.65); font-size:.74rem; font-weight:700; }
        .eyebrow { display:inline-flex; align-items:center; gap:8px; color:var(--teal); font-size:.78rem; font-weight:900; letter-spacing:.12em; text-transform:uppercase; margin-bottom:18px; }
        .hero h1 { font-size:clamp(2.45rem,5.2vw,4.85rem); line-height:1.03; font-weight:900; margin:0 0 20px; }
        .hero p { color:rgba(255,255,255,.76); font-size:1.04rem; line-height:1.8; max-width:720px; }
        section { padding:86px 0; }
        .section-title { font-size:clamp(2rem,4vw,3.2rem); font-weight:900; margin:0; }
        .section-kicker { color:var(--teal); font-weight:900; text-transform:uppercase; font-size:.76rem; letter-spacing:.12em; margin-bottom:10px; }
        .contact-card, .office-card, .form-card, .map-card { background:#fff; border:1px solid var(--line); border-radius:12px; padding:26px; box-shadow:0 18px 50px rgba(26,47,74,.07); height:100%; }
        .contact-card { transition:300ms ease; }
        .contact-card:hover, .office-card:hover { transform:translateY(-4px); border-color:rgba(42,181,160,.45); box-shadow:0 24px 70px rgba(26,47,74,.12); }
        .icon-box { width:48px; height:48px; border-radius:12px; display:grid; place-items:center; background:rgba(42,181,160,.13); color:var(--teal); font-size:1.2rem; margin-bottom:18px; }
        .contact-card h3, .office-card h3 { font-size:1.06rem; font-weight:900; margin-bottom:8px; }
        .contact-card p, .office-card p { color:var(--muted); line-height:1.75; margin:0; font-size:.92rem; }
        .band { background:var(--light); }
        .office-card .badge { border-radius:999px; padding:6px 10px; background:rgba(42,181,160,.13); color:var(--teal); font-weight:900; font-size:.66rem; letter-spacing:.06em; }
        .office-card.main .badge { background:rgba(245,166,35,.2); color:#9a6308; }
        .contact-row { display:flex; gap:12px; align-items:flex-start; padding:11px 0; border-bottom:1px solid var(--line); }
        .contact-row:last-child { border-bottom:0; }
        .contact-row i { color:var(--teal); width:20px; margin-top:4px; }
        .contact-row a, .contact-row span { color:var(--navy); font-weight:700; }
        .form-control, .form-select { min-height:48px; border:1px solid var(--line); border-radius:8px; font-weight:600; }
        .form-control:focus, .form-select:focus { border-color:var(--teal); box-shadow:0 0 0 .2rem rgba(42,181,160,.12); }
        textarea.form-control { min-height:130px; }
        .map-card { min-height:420px; display:grid; place-items:center; text-align:center; color:#fff; background:linear-gradient(135deg,var(--navy),#0c3547); overflow:hidden; position:relative; }
        .map-card::before { content:""; position:absolute; inset:0; background-image:linear-gradient(rgba(255,255,255,.06) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.06) 1px, transparent 1px); background-size:46px 46px; opacity:.55; }
        .map-card > div { position:relative; z-index:2; }
        .map-card > div > i { font-size:3.2rem; color:var(--teal); margin-bottom:18px; }
        .socials { display:flex; gap:10px; flex-wrap:wrap; }
        .socials a { width:42px; height:42px; border-radius:50%; display:grid; place-items:center; background:rgba(42,181,160,.12); color:var(--teal); transition:300ms ease; }
        .socials a i { font-size:1rem; margin:0; color:inherit; }
        .socials a:hover { background:var(--teal); color:#fff; transform:translateY(-3px); }
        .cta { background:linear-gradient(135deg,var(--navy),#0c3547); color:#fff; padding:64px 0; }
        .cta h2 { font-weight:900; margin:0 0 10px; }
        @media (max-width:991px) { .hero { padding:80px 0; } }
        @media (max-width:575px) {
            .hero { padding:48px 0 38px; }
            .hero h1 { font-size:clamp(1.6rem,6.5vw,2.4rem); }
            .hero p { font-size:0.95rem; max-width:100%; }
            .d-flex.flex-wrap { flex-direction:column; gap:12px; }
            .d-flex.flex-wrap .btn-teal, .d-flex.flex-wrap .btn-outline-lightish { width:100%; justify-content:center; }
            .hero-metrics { flex-direction:column; }
            .hero-metric { width:100%; }
            .map-card { min-height:260px; }
        }
    </style>
</head>
<body class="modern-ui">
    <?php require __DIR__ . '/includes/public-nav.php'; ?>

    <header class="hero">
        <div class="container">
            <div class="eyebrow"><i class="fa-solid fa-phone-volume"></i> Contact QGC</div>
            <h1>Talk to our admissions and campus teams.</h1>
            <p>Whether you need admission guidance, program information, campus visit support, or student office help, our team is ready to guide you.</p>
            <div class="d-flex flex-wrap gap-3 mt-4">
                <a href="tel:<?= preg_replace('/\s+/', '', $site_phone) ?>" class="btn-teal"><i class="fa-solid fa-phone"></i> Call Now</a>
                <a href="https://wa.me/923338879961" class="btn-outline-lightish"><i class="fa-brands fa-whatsapp"></i> WhatsApp</a>
            </div>
            <div class="hero-metrics">
                <div class="hero-metric"><strong><?= $total_campuses ?></strong><span>Campuses</span></div>
                <div class="hero-metric"><strong><?= $total_students ?></strong><span>Network Students</span></div>
                <div class="hero-metric"><strong><?= $site_phone ?></strong><span>Admissions Hotline</span></div>
            </div>
        </div>
    </header>

    <section>
        <div class="container">
            <div class="row g-4">
                <div class="col-md-4"><a class="contact-card d-block" href="tel:+923338879961"><div class="icon-box"><i class="fa-solid fa-phone"></i></div><h3>Call Admissions</h3><p>Speak with our admission office for eligibility, fee structure, and campus guidance.</p></a></div>
                <div class="col-md-4"><a class="contact-card d-block" href="mailto:info@qgc.edu.pk"><div class="icon-box"><i class="fa-solid fa-envelope"></i></div><h3>Email Us</h3><p>Send questions about programs, admissions, documents, or student services.</p></a></div>
                <div class="col-md-4"><a class="contact-card d-block" href="https://wa.me/923338879961"><div class="icon-box"><i class="fa-brands fa-whatsapp"></i></div><h3>WhatsApp Support</h3><p>Get quick updates and admissions support through the official WhatsApp channel.</p></a></div>
            </div>
        </div>
    </section>

    <section class="band">
        <div class="container">
            <div class="row align-items-end mb-5">
                <div class="col-lg-7"><div class="section-kicker">Campus Offices</div><h2 class="section-title">Visit the nearest campus</h2></div>
                <div class="col-lg-5"><p class="mb-0" style="color:var(--muted);line-height:1.75;">Choose your city and contact the campus office directly for admissions, program counselling, and fee information.</p></div>
            </div>
            <div class="row g-4">
                <?php foreach ($campus_stats as $key => $c):
                    $mainClass = $key === 'rajanpur' ? ' main' : '';
                    $badgeLabel = $key === 'rajanpur' ? 'MAIN CAMPUS' : 'SUB CAMPUS';
                ?>
                <div class="col-lg-4">
                    <a href="<?= htmlspecialchars($c['url']) ?>" class="office-card<?= $mainClass ?> d-block text-decoration-none" style="color:inherit;">
                        <span class="badge"><?= $badgeLabel ?></span>
                        <h3 class="mt-3"><?= htmlspecialchars($c['name']) ?></h3>
                        <p><?= htmlspecialchars($c['address']) ?></p>
                        <div class="contact-row"><i class="fa-solid fa-phone"></i><span><?= htmlspecialchars($c['phone']) ?></span></div>
                        <div class="contact-row"><i class="fa-solid fa-envelope"></i><span><?= htmlspecialchars($c['email']) ?></span></div>
                        <div class="contact-row"><i class="fa-solid fa-users"></i><span><?= htmlspecialchars($c['students']) ?> students · <?= htmlspecialchars($c['programs']) ?> programs</span></div>
                        <div class="contact-row mt-2" style="color:var(--teal);font-weight:800;font-size:.85rem;">View campus page <i class="fa-solid fa-arrow-right ms-1"></i></div>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section>
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-7">
                    <div class="form-card">
                        <div class="section-kicker">Send Enquiry</div>
                        <h2 class="section-title mb-4">How can we help?</h2>
                        <form action="mailto:info@qgc.edu.pk" method="post" enctype="text/plain">
                            <div class="row g-3">
                                <div class="col-md-6"><input class="form-control" name="name" placeholder="Full name" required></div>
                                <div class="col-md-6"><input class="form-control" name="phone" placeholder="Phone / WhatsApp" required></div>
                                <div class="col-md-6"><select class="form-select" name="campus"><option>Misbah Campus - Rajanpur</option><option>Hamid Campus - Fazilpur</option><option>Abul Rehman Campus - Kotmithan</option></select></div>
                                <div class="col-md-6"><select class="form-select" name="interest"><option>Admissions</option><option>Programs</option><option>Fee Information</option><option>Campus Visit</option><option>Student Support</option></select></div>
                                <div class="col-12"><textarea class="form-control" name="message" placeholder="Write your message"></textarea></div>
                                <div class="col-12"><button class="btn-teal" type="submit">Send Message <i class="fa-solid fa-paper-plane"></i></button></div>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="map-card">
                        <div>
                            <i class="fa-solid fa-location-dot"></i>
                            <h3 class="fw-black">Google Maps</h3>
                            <p class="mb-4" style="color:rgba(255,255,255,.72);">Interactive campus map coming soon.</p>
                            <div class="socials justify-content-center">
                                <a href="https://facebook.com/qgcollege"><i class="fa-brands fa-facebook-f"></i></a>
                                <a href="https://youtube.com/@qgcollege"><i class="fa-brands fa-youtube"></i></a>
                                <a href="https://wa.me/923338879961"><i class="fa-brands fa-whatsapp"></i></a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="cta">
        <div class="container">
            <div class="row align-items-center g-4">
                <div class="col-lg-8"><h2>Admissions are open for 2026.</h2><p class="mb-0" style="color:rgba(255,255,255,.72);">Apply online or contact the nearest campus to reserve your seat.</p></div>
                <div class="col-lg-4 text-lg-end"><a href="modules/admissions/apply.php" class="btn-teal">Apply Online</a></div>
            </div>
        </div>
    </section>

    <?php require __DIR__ . '/includes/public-footer.php'; ?>
</body>
</html>

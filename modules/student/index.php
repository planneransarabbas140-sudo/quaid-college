<?php $page_title = 'Student Updates'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> | Quaid-e-Azam Group of Colleges</title>
    <link rel="icon" type="image/png" href="../../assets/images/qgc-logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --navy:#1a2f4a; --teal:#2ab5a0; --gold:#f5a623; --light:#f4f6fb; --white:#fff; --muted:#667085; --line:#dce3ef; }
        * { box-sizing:border-box; }
        body { margin:0; font-family:Poppins,sans-serif; color:var(--navy); background:var(--light); }
        a { text-decoration:none; }
        .navbar-campus { position:sticky; top:0; z-index:1000; background:rgba(26,47,74,.98); box-shadow:0 10px 30px rgba(0,0,0,.12); }
        .brand-mark { width:52px; height:52px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; background:#fff; padding:4px; box-shadow:0 8px 22px rgba(0,0,0,.16); overflow:hidden; flex:0 0 52px; }
        .brand-mark img { width:100%; height:100%; object-fit:contain; display:block; }
        .brand-title { color:#fff; font-weight:900; line-height:1.08; }
        .brand-subtitle { display:block; color:var(--teal); font-size:.66rem; font-weight:800; letter-spacing:.08em; text-transform:uppercase; }
        .nav-link-campus { color:rgba(255,255,255,.88); font-size:.86rem; font-weight:700; padding:22px 8px; display:inline-flex; align-items:center; gap:6px; white-space:nowrap; line-height:1; }
        .nav-link-campus:hover, .nav-link-campus.active { color:var(--teal); }
        .portal-btn, .btn-teal { display:inline-flex; align-items:center; justify-content:center; gap:8px; border-radius:999px; padding:11px 20px; background:var(--teal); color:#fff; font-weight:900; transition:300ms ease; white-space:nowrap; }
        .portal-btn:hover, .btn-teal:hover { color:#fff; transform:translateY(-2px); background:#33c8b1; box-shadow:0 16px 34px rgba(42,181,160,.26); }
        .navbar-toggler { border-color:rgba(42,181,160,.45); }
        .navbar-toggler-icon { background-image:url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba(42,181,160,1)' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e"); }
        .hero { padding:88px 0 72px; background:linear-gradient(135deg,#0c1b2d,var(--navy)); color:#fff; }
        .eyebrow { display:inline-flex; align-items:center; gap:8px; color:var(--teal); font-size:.78rem; font-weight:900; letter-spacing:.12em; text-transform:uppercase; margin-bottom:16px; }
        .hero h1 { font-size:clamp(2.3rem,5vw,4.5rem); line-height:1.04; font-weight:900; margin:0 0 18px; }
        .hero p { color:rgba(255,255,255,.74); max-width:700px; line-height:1.8; margin:0; }
        section { padding:68px 0; }
        .update-card { height:100%; background:#fff; border:1px solid var(--line); border-radius:12px; padding:26px; box-shadow:0 18px 48px rgba(26,47,74,.08); transition:280ms ease; }
        .update-card:hover { transform:translateY(-5px); border-color:rgba(42,181,160,.5); box-shadow:0 24px 70px rgba(26,47,74,.12); }
        .icon-box { width:48px; height:48px; border-radius:12px; display:grid; place-items:center; background:rgba(42,181,160,.13); color:var(--teal); font-size:1.16rem; margin-bottom:18px; }
        .update-card h2 { font-size:1.08rem; font-weight:900; margin-bottom:10px; }
        .update-card p { color:var(--muted); font-size:.9rem; line-height:1.72; margin-bottom:18px; }
        .status-pill { display:inline-flex; border-radius:999px; padding:6px 10px; font-size:.68rem; font-weight:900; color:#9a6308; background:rgba(245,166,35,.18); border:1px solid rgba(245,166,35,.32); }
        .notice { background:#fff; border-left:4px solid var(--teal); border-radius:10px; padding:22px; color:var(--muted); box-shadow:0 16px 40px rgba(26,47,74,.07); }
        footer { border-top:3px solid var(--teal); background:var(--navy); color:rgba(255,255,255,.72); padding:34px 0 0; }
        footer h3 { color:#fff; font-weight:900; }
        .footer-bottom { margin-top:24px; padding:18px 0; text-align:center; border-top:1px solid rgba(255,255,255,.1); }
        @media (max-width:991px) { .nav-link-campus { justify-content:flex-start; padding:10px 0; } .hero { padding:72px 0 58px; } }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-campus">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-3" href="../../index.php"><span class="brand-mark"><img src="../../assets/images/qgc-logo.png" alt="Quaid-e-Azam Group of Colleges logo"></span><span><span class="brand-title">Quaid-e-Azam</span><span class="brand-subtitle">Misbah Campus - Rajanpur</span></span></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#siteNav"><span class="navbar-toggler-icon"></span></button>
            <div class="collapse navbar-collapse" id="siteNav">
                <div class="navbar-nav mx-auto align-items-lg-center gap-lg-2">
                    <a class="nav-link-campus" href="../../index.php">Home</a>
                    <a class="nav-link-campus" href="../../about.php">About</a>
                    <a class="nav-link-campus" href="../../programs.php">Programs</a>
                    <a class="nav-link-campus" href="../admissions/apply.php">Admissions</a>
                    <a class="nav-link-campus" href="../../news.php">News</a>
                    <a class="nav-link-campus" href="../../contact.php">Contact</a>
                </div>
                <a class="portal-btn mt-3 mt-lg-0" href="../auth/login.php"><i class="fa-solid fa-user-graduate"></i> Student Portal</a>
            </div>
        </div>
    </nav>

    <header class="hero">
        <div class="container">
            <div class="eyebrow"><i class="fa-solid fa-list-check"></i> Student Updates</div>
            <h1>Merit lists, exam notices, and student updates.</h1>
            <p>This page collects the most requested student announcements. For private records, fee status, marks, attendance, and profile details, students should use the secure portal.</p>
            <div class="d-flex flex-wrap gap-3 mt-4">
                <a href="../auth/login.php" class="btn-teal">Open Student Portal <i class="fa-solid fa-arrow-right"></i></a>
                <a href="../admissions/apply.php" class="btn-teal" style="background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.22);">Apply for Admission</a>
            </div>
        </div>
    </header>

    <main>
        <section>
            <div class="container">
                <div class="row g-4">
                    <div class="col-md-4">
                        <article class="update-card">
                            <div class="icon-box"><i class="fa-solid fa-award"></i></div>
                            <span class="status-pill">Coming Soon</span>
                            <h2 class="mt-3">Merit Lists 2026</h2>
                            <p>Batch 2026 merit lists will be published after admission form review and campus verification.</p>
                            <a class="btn-teal" href="../admissions/apply.php">Apply Now</a>
                        </article>
                    </div>
                    <div class="col-md-4">
                        <article class="update-card">
                            <div class="icon-box"><i class="fa-solid fa-calendar-check"></i></div>
                            <span class="status-pill">Portal Access</span>
                            <h2 class="mt-3">Exam Schedule</h2>
                            <p>Students can check exam notices, schedules, and academic updates through the college office or portal.</p>
                            <a class="btn-teal" href="../examination/index.php">Exam Module</a>
                        </article>
                    </div>
                    <div class="col-md-4">
                        <article class="update-card">
                            <div class="icon-box"><i class="fa-solid fa-id-card"></i></div>
                            <span class="status-pill">Secure Area</span>
                            <h2 class="mt-3">Student Records</h2>
                            <p>Profiles, attendance, fee records, and ID cards are protected and require student or staff login.</p>
                            <a class="btn-teal" href="../auth/login.php">Login</a>
                        </article>
                    </div>
                </div>
                <div class="notice mt-4">
                    <strong>Office note:</strong> For urgent merit list or admission confirmation queries, contact your nearest QGC campus office with your admission form details.
                </div>
            </div>
        </section>
    </main>

    <footer>
        <div class="container">
            <h3>Quaid-e-Azam Group of Colleges</h3>
            <p class="mb-0">Disciplined, career-focused education across Rajanpur, Fazilpur, and Kotmithan.</p>
            <div class="footer-bottom">&copy; 2026 Quaid-e-Azam Group of Colleges. All Rights Reserved.</div>
        </div>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

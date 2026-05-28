<?php
$page_title = 'Programs';
require_once __DIR__ . '/includes/site-stats.php';
$public_nav_active = 'programs';
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
        .hero { position:relative; overflow:hidden; padding:94px 0 74px; color:#fff; background:linear-gradient(135deg,rgba(12,27,45,.94),rgba(26,47,74,.88)), url('assets/images/image1.png') center/cover; transform-style:preserve-3d; }
        .hero:after { content:""; position:absolute; inset:auto 0 0; height:8px; background:linear-gradient(90deg,var(--teal),var(--gold)); }
        .hero .container { position:relative; z-index:1; }
        .eyebrow { display:inline-flex; align-items:center; gap:8px; color:var(--teal); font-size:.78rem; font-weight:900; letter-spacing:.12em; text-transform:uppercase; margin-bottom:18px; }
        .hero h1 { max-width:780px; font-size:clamp(2.35rem,5vw,4.7rem); line-height:1.04; font-weight:900; letter-spacing:0; margin:0 0 20px; }
        .hero p { color:rgba(255,255,255,.78); font-size:1.02rem; line-height:1.8; max-width:720px; margin:0; }
        .hero-stat-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:14px; margin-top:34px; max-width:780px; }
        .hero-stat { border:1px solid rgba(255,255,255,.14); background:rgba(255,255,255,.08); border-radius:10px; padding:16px; backdrop-filter:blur(12px); }
        .hero-stat strong { display:block; color:#fff; font-size:1.55rem; line-height:1; }
        .hero-stat span { display:block; color:rgba(255,255,255,.68); font-size:.76rem; font-weight:700; margin-top:8px; }
        section { padding:78px 0; }
        .section-kicker { color:var(--teal); font-weight:900; text-transform:uppercase; font-size:.76rem; letter-spacing:.12em; margin-bottom:10px; }
        .section-title { font-size:clamp(2rem,4vw,3.2rem); font-weight:900; margin:0; }
        .lead-text { color:var(--muted); line-height:1.85; }
        .band { background:var(--light); }
        .program-tabs { display:flex; gap:10px; flex-wrap:wrap; margin-top:30px; }
        .program-tab { border:1px solid var(--line); background:#fff; color:var(--navy); border-radius:999px; padding:10px 16px; font-weight:900; font-size:.84rem; transition:220ms ease; }
        .program-tab:hover, .program-tab.active { background:var(--navy); color:#fff; border-color:var(--navy); }
        .program-grid { margin-top:30px; }
        .program-card { height:100%; border:1px solid var(--line); background:#fff; border-radius:12px; padding:24px; box-shadow:0 18px 48px rgba(26,47,74,.07); transition:280ms ease; display:flex; flex-direction:column; }
        .program-card:hover { transform:translateY(-5px); border-color:rgba(42,181,160,.5); box-shadow:0 24px 70px rgba(26,47,74,.12); }
        .program-icon { width:48px; height:48px; border-radius:12px; display:grid; place-items:center; background:rgba(42,181,160,.13); color:var(--teal); font-size:1.16rem; margin-bottom:18px; }
        .program-card h3 { font-size:1.08rem; font-weight:900; margin:0 0 10px; }
        .program-card p { color:var(--muted); font-size:.9rem; line-height:1.72; margin:0 0 18px; }
        .program-tags { display:flex; flex-wrap:wrap; gap:8px; }
        .program-tags span { border-radius:999px; background:var(--soft); color:#217d71; padding:6px 10px; font-size:.68rem; font-weight:900; }
        .program-apply { display:inline-flex; align-items:center; gap:8px; margin-top:18px; color:var(--teal); font-weight:900; font-size:.86rem; transition:220ms ease; }
        .program-apply:hover { gap:12px; color:var(--navy); }
        .track-card { height:100%; background:#fff; border:1px solid var(--line); border-radius:12px; padding:26px; box-shadow:0 18px 50px rgba(26,47,74,.07); }
        .track-card h3 { font-size:1.06rem; font-weight:900; margin:0 0 12px; display:flex; align-items:center; gap:10px; }
        .track-card h3 i { color:var(--teal); }
        .track-card ul { list-style:none; padding:0; margin:0; display:grid; gap:10px; }
        .track-card li { color:var(--muted); font-weight:600; display:flex; gap:9px; line-height:1.55; }
        .track-card li i { color:var(--teal); margin-top:4px; font-size:.76rem; }
        .campus-table { overflow:hidden; border-radius:12px; border:1px solid var(--line); box-shadow:0 18px 50px rgba(26,47,74,.07); background:#fff; }
        .campus-table table { margin:0; }
        .campus-table th { background:var(--navy); color:#fff; font-size:.78rem; padding:16px; }
        .campus-table td { padding:16px; color:var(--muted); font-weight:600; vertical-align:middle; }
        .campus-table a { color:var(--navy); font-weight:900; }
        .check { color:var(--teal); font-size:1.05rem; }
        .step-card { height:100%; border:1px solid var(--line); border-radius:12px; padding:24px; background:#fff; }
        .step-num { width:42px; height:42px; border-radius:12px; display:grid; place-items:center; background:var(--navy); color:#fff; font-weight:900; margin-bottom:16px; }
        .step-card h3 { font-size:1rem; font-weight:900; margin-bottom:8px; }
        .step-card p { color:var(--muted); line-height:1.7; margin:0; font-size:.9rem; }
        .cta { background:linear-gradient(135deg,var(--navy),#0c3547); color:#fff; padding:62px 0; }
        .cta h2 { font-weight:900; margin:0 0 10px; }
        @media (max-width:991px) { .hero { padding:76px 0 62px; } .hero-stat-grid { grid-template-columns:1fr; } section { padding:58px 0; } }
        @media (max-width:575px) { .program-tabs { display:grid; grid-template-columns:1fr; } .program-tab { width:100%; } .campus-table { overflow-x:auto; } }
    </style>
</head>
<body class="modern-ui programs-page">
    <?php require __DIR__ . '/includes/public-nav.php'; ?>

    <header class="hero">
        <div class="container">
            <div class="eyebrow"><i class="fa-solid fa-layer-group"></i> Academic Programs</div>
            <h1>Choose a focused study path with real academic support.</h1>
            <p>Explore intermediate, degree, professional education, and NAVTTC skill programs offered across Quaid-e-Azam Group of Colleges campuses.</p>
            <div class="d-flex flex-wrap gap-3 mt-4">
                <a href="contact.php" class="btn-teal">Admissions Guidance <i class="fa-solid fa-arrow-right"></i></a>
                <a href="#program-list" class="btn-outline-lightish">View Programs</a>
            </div>
            <div class="hero-stat-grid">
                <div class="hero-stat"><strong><?= $programs_offered ?></strong><span>Academic and skill programs</span></div>
                <div class="hero-stat"><strong><?= $total_campuses ?></strong><span>Campus locations</span></div>
                <div class="hero-stat"><strong><?= $total_students ?></strong><span>Students across the network</span></div>
            </div>
        </div>
    </header>

    <main>
        <section id="program-list">
            <div class="container">
                <div class="row align-items-end g-4">
                    <div class="col-lg-7">
                        <div class="section-kicker">Program Finder</div>
                        <h2 class="section-title">Find the right program.</h2>
                    </div>
                    <div class="col-lg-5">
                        <p class="lead-text mb-0">Search by subject, career direction, duration, or category, then narrow the list with quick filters.</p>
                    </div>
                </div>

                <div class="premium-search-panel">
                    <label for="programSearch">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input id="programSearch" type="search" placeholder="Search programs, skills, affiliations, or career tracks" autocomplete="off">
                    </label>
                    <span class="premium-filter-count" id="programMatchCount">12 programs found</span>
                </div>

                <div class="premium-program-guide">
                    <div class="premium-guide-card featured">
                        <strong>Best for focused admissions planning</strong>
                        <span>Compare intermediate, BS, professional, and skill pathways before visiting the campus office.</span>
                    </div>
                    <div class="premium-guide-card">
                        <strong>Pre-medical and engineering</strong>
                        <span>Structured board preparation with regular testing and faculty guidance.</span>
                    </div>
                    <div class="premium-guide-card">
                        <strong>Digital and NAVTTC skills</strong>
                        <span>Short practical courses for job-ready creative and technology skills.</span>
                    </div>
                </div>

                <div class="program-tabs" role="tablist" aria-label="Program categories">
                    <button class="program-tab active" type="button" data-filter="all">All Programs</button>
                    <button class="program-tab" type="button" data-filter="intermediate">Intermediate</button>
                    <button class="program-tab" type="button" data-filter="degree">Degree</button>
                    <button class="program-tab" type="button" data-filter="professional">Professional</button>
                    <button class="program-tab" type="button" data-filter="skill">NAVTTC Skills</button>
                </div>

                <div class="row g-4 program-grid">
                    <div class="col-md-6 col-xl-4 program-item" data-category="intermediate">
                        <article class="program-card">
                            <div class="program-icon"><i class="fa-solid fa-flask"></i></div>
                            <h3>FSc Pre-Medical</h3>
                            <p>Biology, Chemistry, and Physics pathway for students aiming at medical, dental, pharmacy, and health sciences careers.</p>
                            <div class="program-tags"><span>2 Years</span><span>BISE DG Khan</span><span>Intermediate</span></div>
                            <a href="<?= program_apply_url('FSc Pre-Medical') ?>" class="program-apply">Apply Now <i class="fa-solid fa-arrow-right"></i></a>
                        </article>
                    </div>
                    <div class="col-md-6 col-xl-4 program-item" data-category="intermediate">
                        <article class="program-card">
                            <div class="program-icon"><i class="fa-solid fa-calculator"></i></div>
                            <h3>FSc Pre-Engineering</h3>
                            <p>Mathematics, Physics, and Chemistry track for engineering, architecture, technology, and analytical careers.</p>
                            <div class="program-tags"><span>2 Years</span><span>BISE DG Khan</span><span>Intermediate</span></div>
                            <a href="<?= program_apply_url('FSc Pre-Engineering') ?>" class="program-apply">Apply Now <i class="fa-solid fa-arrow-right"></i></a>
                        </article>
                    </div>
                    <div class="col-md-6 col-xl-4 program-item" data-category="intermediate">
                        <article class="program-card">
                            <div class="program-icon"><i class="fa-solid fa-desktop"></i></div>
                            <h3>ICS Computer Science</h3>
                            <p>Computer Science with Mathematics and Statistics for students preparing for software, IT, and data-related fields.</p>
                            <div class="program-tags"><span>2 Years</span><span>BISE DG Khan</span><span>IT Track</span></div>
                            <a href="<?= program_apply_url('ICS Computer Science') ?>" class="program-apply">Apply Now <i class="fa-solid fa-arrow-right"></i></a>
                        </article>
                    </div>
                    <div class="col-md-6 col-xl-4 program-item" data-category="intermediate">
                        <article class="program-card">
                            <div class="program-icon"><i class="fa-solid fa-chart-line"></i></div>
                            <h3>I.Com Commerce</h3>
                            <p>Commerce, accounting, and business foundation for future studies in finance, management, and entrepreneurship.</p>
                            <div class="program-tags"><span>2 Years</span><span>BISE DG Khan</span><span>Commerce</span></div>
                            <a href="<?= program_apply_url('I.Com Commerce') ?>" class="program-apply">Apply Now <i class="fa-solid fa-arrow-right"></i></a>
                        </article>
                    </div>
                    <div class="col-md-6 col-xl-4 program-item" data-category="intermediate">
                        <article class="program-card">
                            <div class="program-icon"><i class="fa-solid fa-book-open"></i></div>
                            <h3>FA Arts</h3>
                            <p>Humanities and social sciences route for students pursuing law, journalism, public service, and teaching.</p>
                            <div class="program-tags"><span>2 Years</span><span>BISE DG Khan</span><span>Arts</span></div>
                            <a href="<?= program_apply_url('FA Arts') ?>" class="program-apply">Apply Now <i class="fa-solid fa-arrow-right"></i></a>
                        </article>
                    </div>
                    <div class="col-md-6 col-xl-4 program-item" data-category="degree">
                        <article class="program-card">
                            <div class="program-icon"><i class="fa-solid fa-graduation-cap"></i></div>
                            <h3>ADP Arts and ADP Science</h3>
                            <p>Two-year associate degree options for students seeking university-affiliated undergraduate pathways.</p>
                            <div class="program-tags"><span>2 Years</span><span>IUB Affiliated</span><span>Degree</span></div>
                            <a href="<?= program_apply_url('ADP Arts and ADP Science') ?>" class="program-apply">Apply Now <i class="fa-solid fa-arrow-right"></i></a>
                        </article>
                    </div>
                    <div class="col-md-6 col-xl-4 program-item" data-category="degree">
                        <article class="program-card">
                            <div class="program-icon"><i class="fa-solid fa-laptop-code"></i></div>
                            <h3>BSCS Computer Science</h3>
                            <p>Four-year computer science degree covering programming, software development, databases, and computing foundations.</p>
                            <div class="program-tags"><span>4 Years</span><span>IUB Affiliated</span><span>Computing</span></div>
                            <a href="<?= program_apply_url('BSCS Computer Science') ?>" class="program-apply">Apply Now <i class="fa-solid fa-arrow-right"></i></a>
                        </article>
                    </div>
                    <div class="col-md-6 col-xl-4 program-item" data-category="degree">
                        <article class="program-card">
                            <div class="program-icon"><i class="fa-solid fa-network-wired"></i></div>
                            <h3>BS Information Technology</h3>
                            <p>Systems, networks, web technologies, and applied IT skills for modern technology workplaces.</p>
                            <div class="program-tags"><span>4 Years</span><span>IUB Affiliated</span><span>IT</span></div>
                            <a href="<?= program_apply_url('BS Information Technology') ?>" class="program-apply">Apply Now <i class="fa-solid fa-arrow-right"></i></a>
                        </article>
                    </div>
                    <div class="col-md-6 col-xl-4 program-item" data-category="degree">
                        <article class="program-card">
                            <div class="program-icon"><i class="fa-solid fa-microscope"></i></div>
                            <h3>BS Zoology, Mathematics, Urdu, Chemistry</h3>
                            <p>Discipline-specific BS programs for students moving toward research, teaching, industry, or higher studies.</p>
                            <div class="program-tags"><span>4 Years</span><span>IUB Affiliated</span><span>BS Programs</span></div>
                            <a href="<?= program_apply_url('BS Zoology, Mathematics, Urdu, Chemistry') ?>" class="program-apply">Apply Now <i class="fa-solid fa-arrow-right"></i></a>
                        </article>
                    </div>
                    <div class="col-md-6 col-xl-4 program-item" data-category="professional">
                        <article class="program-card">
                            <div class="program-icon"><i class="fa-solid fa-chalkboard-user"></i></div>
                            <h3>B.Ed Education</h3>
                            <p>Professional teacher education pathway for students and working educators pursuing classroom and school careers.</p>
                            <div class="program-tags"><span>Professional</span><span>Education</span><span>Career Track</span></div>
                            <a href="<?= program_apply_url('B.Ed Education') ?>" class="program-apply">Apply Now <i class="fa-solid fa-arrow-right"></i></a>
                        </article>
                    </div>
                    <div class="col-md-6 col-xl-4 program-item" data-category="skill">
                        <article class="program-card">
                            <div class="program-icon"><i class="fa-solid fa-code"></i></div>
                            <h3>Web Development and Mobile Apps</h3>
                            <p>NAVTTC short courses for practical development skills, project work, and entry-level digital careers.</p>
                            <div class="program-tags"><span>3 to 6 Months</span><span>Govt Certified</span><span>NAVTTC</span></div>
                            <a href="<?= program_apply_url('NAVTTC - Web Development and Mobile Apps') ?>" class="program-apply">Apply Now <i class="fa-solid fa-arrow-right"></i></a>
                        </article>
                    </div>
                    <div class="col-md-6 col-xl-4 program-item" data-category="skill">
                        <article class="program-card">
                            <div class="program-icon"><i class="fa-solid fa-wand-magic-sparkles"></i></div>
                            <h3>Digital Marketing, UI/UX, Graphics, AI</h3>
                            <p>Market-ready skill courses in creative design, online marketing, computer office management, and AI-assisted work.</p>
                            <div class="program-tags"><span>3 to 6 Months</span><span>Govt Certified</span><span>NAVTTC</span></div>
                            <a href="<?= program_apply_url('NAVTTC - Digital Marketing, UI/UX, Graphics, AI') ?>" class="program-apply">Apply Now <i class="fa-solid fa-arrow-right"></i></a>
                        </article>
                    </div>
                </div>
                <div id="programNoResults" class="program-no-results d-none">
                    No programs matched your search. Try a broader keyword like science, computer, degree, or skill.
                </div>
            </div>
        </section>

        <section class="band">
            <div class="container">
                <div class="row g-4 align-items-stretch">
                    <div class="col-lg-4">
                        <div class="track-card">
                            <h3><i class="fa-solid fa-user-check"></i> Academic Support</h3>
                            <ul>
                                <li><i class="fa-solid fa-check"></i> Regular tests and performance review</li>
                                <li><i class="fa-solid fa-check"></i> Board and university exam preparation</li>
                                <li><i class="fa-solid fa-check"></i> Guidance from experienced faculty</li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="track-card">
                            <h3><i class="fa-solid fa-building-columns"></i> Affiliation</h3>
                            <ul>
                                <li><i class="fa-solid fa-check"></i> Intermediate programs under BISE DG Khan</li>
                                <li><i class="fa-solid fa-check"></i> Degree programs affiliated with IUB</li>
                                <li><i class="fa-solid fa-check"></i> NAVTTC short courses with certification</li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="track-card">
                            <h3><i class="fa-solid fa-location-dot"></i> Campus Access</h3>
                            <ul>
                                <li><i class="fa-solid fa-check"></i> Misbah Campus, Rajanpur</li>
                                <li><i class="fa-solid fa-check"></i> Hamid Campus, Fazilpur</li>
                                <li><i class="fa-solid fa-check"></i> Abul Rehman Campus, Kotmithan</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section>
            <div class="container">
                <div class="text-center mb-5">
                    <div class="section-kicker">Campus Availability</div>
                    <h2 class="section-title">Programs by campus.</h2>
                </div>
                <div class="campus-table">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Campus</th>
                                <th>Intermediate</th>
                                <th>Degree</th>
                                <th>B.Ed</th>
                                <th>NAVTTC Skills</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>Misbah Campus, Rajanpur</strong></td>
                                <td><i class="fa-solid fa-circle-check check"></i></td>
                                <td><i class="fa-solid fa-circle-check check"></i></td>
                                <td><i class="fa-solid fa-circle-check check"></i></td>
                                <td><i class="fa-solid fa-circle-check check"></i></td>
                                <td><a href="campus-rajanpur.php#programs">View campus</a></td>
                            </tr>
                            <tr>
                                <td><strong>Hamid Campus, Fazilpur</strong></td>
                                <td><i class="fa-solid fa-circle-check check"></i></td>
                                <td><i class="fa-solid fa-circle-check check"></i></td>
                                <td><i class="fa-solid fa-circle-check check"></i></td>
                                <td><i class="fa-solid fa-circle-check check"></i></td>
                                <td><a href="campus-fazilpur.php#programs">View campus</a></td>
                            </tr>
                            <tr>
                                <td><strong>Abul Rehman Campus, Kotmithan</strong></td>
                                <td><i class="fa-solid fa-circle-check check"></i></td>
                                <td><i class="fa-solid fa-circle-check check"></i></td>
                                <td><i class="fa-solid fa-circle-check check"></i></td>
                                <td><i class="fa-solid fa-circle-check check"></i></td>
                                <td><a href="campus-kotmithan.php#programs">View campus</a></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="band">
            <div class="container">
                <div class="row align-items-end g-4 mb-4">
                    <div class="col-lg-7">
                        <div class="section-kicker">Admissions Process</div>
                        <h2 class="section-title">Apply in three steps.</h2>
                    </div>
                    <div class="col-lg-5">
                        <p class="lead-text mb-0">Students can apply online or visit the nearest campus office for guidance on eligibility, documents, and fee details.</p>
                    </div>
                </div>
                <div class="row g-4">
                    <div class="col-md-4"><div class="step-card"><div class="step-num">1</div><h3>Select your program</h3><p>Choose the study track that matches your matric, intermediate, or career plan.</p></div></div>
                    <div class="col-md-4"><div class="step-card"><div class="step-num">2</div><h3>Submit admission form</h3><p>Complete the online form and provide accurate academic and contact information.</p></div></div>
                    <div class="col-md-4"><div class="step-card"><div class="step-num">3</div><h3>Visit campus office</h3><p>Bring documents for verification, fee guidance, and final enrollment confirmation.</p></div></div>
                </div>
            </div>
        </section>

        <section class="cta">
            <div class="container">
                <div class="row align-items-center g-4">
                    <div class="col-lg-8">
                        <h2>Admissions are open for the 2026 batch.</h2>
                        <p class="mb-0" style="color:rgba(255,255,255,.72);">Start your application or speak with the campus office to confirm program availability.</p>
                    </div>
                    <div class="col-lg-4 text-lg-end d-flex gap-3 justify-content-lg-end flex-wrap">
                        <a href="modules/admissions/apply.php" class="btn-teal">Apply Now</a>
                        <a href="contact.php" class="btn-outline-lightish">Contact Office</a>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <?php require __DIR__ . '/includes/public-footer.php'; ?>
</body>
</html>

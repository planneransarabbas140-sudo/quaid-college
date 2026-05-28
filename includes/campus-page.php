<?php
/**
 * Shared campus detail page template.
 * Set $campus_key before including (rajanpur|fazilpur|kotmithan).
 */
$campus_key = $campus_key ?? 'rajanpur';
require_once __DIR__ . '/site-stats.php';
require_once __DIR__ . '/campus-data.php';

$c = qgc_get_campus($campus_key);
$public_nav_active = 'campuses';
$public_campus_key = $campus_key;
$public_brand_subtitle = $c['name'] . ' — ' . $c['city'];
$public_footer_campus_url = $c['url'];

$page_url = $c['url'];
$program_counts = qgc_program_filter_counts($c['program_list']);
$leader_count = count($c['leaders']);
$leader_wrap_class = $leader_count >= 3 ? 'leader-wrap leader-wrap--cols-3' : 'leader-wrap';
$is_sub = ($c['badge'] ?? '') === 'SUB CAMPUS';
$hero_bg = htmlspecialchars($c['image'] ?? 'assets/images/image1.png', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($c['name']) ?> <?= htmlspecialchars($c['city']) ?> | Quaid-e-Azam Group of Colleges</title>
    <meta name="description" content="<?= htmlspecialchars($c['meta_desc'] ?? '') ?>">
    <link rel="icon" type="image/png" href="assets/images/qgc-logo-nav.png">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/public-site.css?v=5" rel="stylesheet">
    <link href="assets/css/campus.css?v=3" rel="stylesheet">
</head>
<body class="campus-page modern-ui">
    <?php require __DIR__ . '/public-nav.php'; ?>

    <section class="hero-campus hero-slide" id="campus" style="background-image: url('<?= $hero_bg ?>');">
        <div class="container">
            <div class="hero-layout">
                <div class="reveal">
                    <span class="gold-badge<?= $is_sub ? ' sub-campus' : '' ?>"><?= htmlspecialchars($c['badge']) ?></span>
                    <h1 class="hero-title"><?= htmlspecialchars($c['name']) ?></h1>
                    <div class="location-pill"><i class="fa-solid fa-location-dot"></i><?= htmlspecialchars($c['city']) ?></div>
                    <p class="hero-copy">Established <?= htmlspecialchars($c['established']) ?> · <?= htmlspecialchars($c['students']) ?> students</p>
                    <div class="hero-stats">
                        <div class="stat-card"><strong><?= htmlspecialchars($c['students']) ?></strong><span>Students</span></div>
                        <div class="stat-card"><strong><?= htmlspecialchars($c['faculty']) ?></strong><span>Faculty</span></div>
                        <div class="stat-card"><strong><?= htmlspecialchars($c['programs']) ?></strong><span>Programs</span></div>
                    </div>
                    <div class="hero-pills">
                        <?php foreach ($c['hero_pills'] as $pill): ?>
                        <span class="program-pill"><?= htmlspecialchars($pill) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <div class="hero-actions">
                        <a href="modules/admissions/apply.php" class="btn-campus btn-teal">Apply Now <i class="fa-solid fa-arrow-right"></i></a>
                        <a href="<?= htmlspecialchars($page_url) ?>#programs" class="btn-campus btn-outline-white"><i class="fa-solid fa-graduation-cap"></i> View Programs</a>
                        <a href="<?= htmlspecialchars($page_url) ?>#video" class="btn-campus btn-outline-white"><i class="fa-solid fa-play"></i> Watch Video</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="campus-other-strip">
        <div class="container">
            <h3>Explore our campuses</h3>
            <div class="campus-other-links">
                <?php foreach ($campus_stats as $key => $cs):
                    $is_current = ($key === $campus_key);
                ?>
                <a href="<?= htmlspecialchars($cs['url']) ?>" class="<?= $is_current ? 'is-current' : '' ?>">
                    <i class="fa-solid fa-building"></i>
                    <?= htmlspecialchars($cs['name']) ?> — <?= htmlspecialchars($cs['city']) ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <section id="video">
        <div class="container">
            <div class="row align-items-center g-5">
                <div class="col-lg-7 reveal">
                    <div class="video-box">
                        <video class="campus-video" controls preload="metadata" playsinline poster="<?= htmlspecialchars($c['video_poster']) ?>">
                            <source src="<?= htmlspecialchars($c['video']) ?>" type="video/mp4">
                            <p style="color:#fff;padding:20px;">Video not supported.
                                <a href="<?= htmlspecialchars($c['video']) ?>" style="color:var(--teal)">Download</a>
                            </p>
                        </video>
                    </div>
                </div>
                <div class="col-lg-5 reveal">
                    <div class="welcome-copy">
                        <h2>Welcome to <?= htmlspecialchars($c['name']) ?></h2>
                        <p>Focused academics. Practical skills. Local access.</p>
                        <ul class="check-list">
                            <?php foreach ($c['highlights'] as $item): ?>
                            <li><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($item) ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <div class="stat-pill-row">
                            <span class="stat-pill">Est. <?= htmlspecialchars($c['established']) ?></span>
                            <span class="stat-pill"><?= htmlspecialchars($c['students']) ?> Students</span>
                            <span class="stat-pill"><?= htmlspecialchars($c['faculty']) ?> Faculty</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="campus-story-lab" id="student-life">
        <div class="container">
            <div class="campus-story-grid">
                <article class="campus-story-card media reveal" style="background-image: url('<?= htmlspecialchars($c['video_poster']) ?>');">
                    <span class="premium-bento-kicker"><i class="fa-solid fa-camera"></i> Campus Story</span>
                    <h2>Life at <?= htmlspecialchars($c['name']) ?></h2>
                    <p>Campus life built around confidence, discipline, and progress.</p>
                    <div class="premium-bento-meta">
                        <span><?= htmlspecialchars($c['students']) ?> students</span>
                        <span><?= htmlspecialchars($c['faculty']) ?> faculty</span>
                        <span><?= htmlspecialchars($c['programs']) ?> programs</span>
                    </div>
                </article>
                <article class="campus-story-card reveal">
                    <span class="premium-bento-kicker"><i class="fa-solid fa-compass"></i> Student Support Model</span>
                    <h2>Guidance. Discipline. Progress.</h2>
                    <p>Support from admission to exam preparation.</p>
                    <div class="campus-story-list">
                        <?php foreach ($c['highlights'] as $item): ?>
                        <span><i class="fa-solid fa-circle-check"></i><?= htmlspecialchars($item) ?></span>
                        <?php endforeach; ?>
                    </div>
                </article>
            </div>
        </div>
    </section>

    <section class="leadership" id="leadership">
        <div class="container">
            <div class="section-heading reveal">
                <h2>Our Leadership</h2>
                <div class="teal-line"></div>
            </div>
            <div class="<?= $leader_wrap_class ?>">
                <?php foreach ($c['leaders'] as $leader):
                    $badge_class = trim($leader['badge_class'] ?? '');
                    $photo_class = trim($leader['photo_class'] ?? '');
                ?>
                <div class="leader-card reveal">
                    <div class="leader-photo<?= $photo_class ? ' ' . htmlspecialchars($photo_class) : '' ?>">
                        <img src="<?= htmlspecialchars($leader['image']) ?>" alt="<?= htmlspecialchars($leader['name']) ?>">
                    </div>
                    <span class="leader-badge<?= $badge_class ? ' ' . htmlspecialchars($badge_class) : '' ?>"><?= htmlspecialchars($leader['badge']) ?></span>
                    <h3><?= htmlspecialchars($leader['name']) ?></h3>
                    <div class="role"><?= htmlspecialchars($leader['role']) ?></div>
                    <blockquote>&ldquo;<?= htmlspecialchars($leader['quote']) ?>&rdquo;</blockquote>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section id="programs">
        <div class="container">
            <div class="section-heading reveal">
                <h2>Programs We Offer</h2>
                <p>Choose your path to success</p>
                <div class="teal-line"></div>
            </div>
            <div class="campus-program-tools reveal">
                <input type="search" id="campusProgramSearch" placeholder="Search this campus programs" autocomplete="off">
                <span class="campus-program-count" id="campusProgramCount"><?= (int)$program_counts['all'] ?> programs</span>
            </div>
            <div class="filter-tabs reveal">
                <button type="button" class="filter-btn active" data-filter="all">All (<?= (int)$program_counts['all'] ?>)</button>
                <?php if (!empty($c['show_matric_filter']) && $program_counts['matric'] > 0): ?>
                <button type="button" class="filter-btn" data-filter="matric">Matric (<?= (int)$program_counts['matric'] ?>)</button>
                <?php endif; ?>
                <button type="button" class="filter-btn" data-filter="intermediate">Intermediate (<?= (int)$program_counts['intermediate'] ?>)</button>
                <button type="button" class="filter-btn" data-filter="degree">Degree (<?= (int)$program_counts['degree'] ?>)</button>
                <button type="button" class="filter-btn" data-filter="navttc">NAVTTC (<?= (int)$program_counts['navttc'] ?>)</button>
            </div>
            <div class="row g-4 program-grid">
                <?php foreach ($c['program_list'] as $program):
                    $category_label = strtoupper($program['cat']);
                ?>
                <div class="col-xl-3 col-md-6 program-item reveal" data-category="<?= htmlspecialchars($program['cat']) ?>">
                    <div class="program-card">
                        <div class="program-icon"><?= $program['icon'] ?></div>
                        <h3><?= htmlspecialchars($program['name']) ?></h3>
                        <span class="badge-soft badge-duration"><?= htmlspecialchars($program['duration']) ?></span>
                        <span class="badge-soft badge-category"><?= htmlspecialchars($category_label) ?></span>
                        <a href="<?= htmlspecialchars(program_apply_url($program['name'])) ?>" class="learn-btn">Apply Now</a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="campus-program-empty hidden" id="campusProgramEmpty">No campus programs matched your search.</div>
        </div>
    </section>

    <section class="admission-cta" id="admissions">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-7 reveal">
                    <h2>Ready to Join <?= htmlspecialchars($c['name']) ?>?</h2>
                    <p>Admissions open for the upcoming batch.</p>
                </div>
                <div class="col-lg-5 d-flex justify-content-lg-end gap-3 flex-wrap cta-actions reveal">
                    <a href="modules/admissions/apply.php" class="btn-campus btn-gold">Apply Now</a>
                    <a href="contact.php" class="btn-campus btn-outline-white">Contact Office</a>
                </div>
            </div>
        </div>
    </section>

    <section class="contact-section" id="contact">
        <div class="container">
            <div class="row g-5 align-items-center">
                <div class="col-lg-5 reveal">
                    <div class="welcome-copy">
                        <h2>Get In Touch</h2>
                        <p>Program guidance, eligibility, fees, and visits.</p>
                    </div>
                    <div class="info-list">
                        <div class="info-row"><i class="fa-solid fa-location-dot"></i><span><?= htmlspecialchars($c['address']) ?></span></div>
                        <div class="info-row"><i class="fa-solid fa-phone"></i><span><a href="<?= htmlspecialchars(qgc_tel_href($c['phone'])) ?>"><?= htmlspecialchars($c['phone']) ?></a></span></div>
                        <div class="info-row"><i class="fa-solid fa-envelope"></i><span><a href="mailto:<?= htmlspecialchars($c['email']) ?>"><?= htmlspecialchars($c['email']) ?></a></span></div>
                        <div class="info-row"><i class="fa-solid fa-clock"></i><span>Mon–Sat: 8:00 AM – 4:00 PM</span></div>
                        <div class="info-row"><i class="fa-solid fa-globe"></i><span>www.qgc.edu.pk</span></div>
                    </div>
                    <div class="social-row">
                        <a href="https://facebook.com/qgcollege" aria-label="Facebook"><i class="fa-brands fa-facebook-f"></i></a>
                        <a href="https://youtube.com/@qgcollege" aria-label="YouTube"><i class="fa-brands fa-youtube"></i></a>
                        <a href="https://wa.me/923338879961" aria-label="WhatsApp"><i class="fa-brands fa-whatsapp"></i></a>
                    </div>
                </div>
                <div class="col-lg-7 reveal">
                    <iframe class="campus-map" src="<?= htmlspecialchars($c['map_embed']) ?>" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="<?= htmlspecialchars($c['name']) ?> map"></iframe>
                </div>
            </div>
        </div>
    </section>

    <?php require __DIR__ . '/public-footer.php'; ?>
    <script src="assets/js/campus.js?v=2"></script>
</body>
</html>

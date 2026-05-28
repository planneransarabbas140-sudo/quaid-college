<?php
if (!isset($campus_stats)) {
    require_once __DIR__ . '/site-stats.php';
}
$public_footer_campus_url = $public_footer_campus_url ?? 'campus-rajanpur.php';
$public_footer_portals = $public_footer_portals ?? false;
?>
<footer class="public-footer">
    <div class="container">
        <div class="footer-cta-panel">
            <div>
                <span class="footer-kicker">Admissions 2026</span>
                <h3>Ready to choose your campus and program?</h3>
                <p>Speak with the admissions office or start the online form. Our team will guide you through eligibility, documents, and fee details.</p>
            </div>
            <div class="footer-cta-actions">
                <a href="contact.php" class="btn-outline-lightish">Talk to Office</a>
            </div>
        </div>
        <div class="row g-4">
            <div class="col-lg-4">
                <h3>Quaid-e-Azam Group of Colleges</h3>
                <p>Disciplined, career-focused education across Rajanpur, Fazilpur, and Kot Mithan since <?= (int)($established_year ?? 2015) ?>.</p>
                <div class="footer-proof-grid">
                    <span><strong><?= (int)($total_students ?? 0) ?>+</strong> Students</span>
                    <span><strong><?= (int)($total_faculty ?? 0) ?>+</strong> Faculty</span>
                    <span><strong><?= (int)($programs_offered ?? 0) ?>+</strong> Programs</span>
                </div>
                <?php if ($public_footer_portals): ?>
                <div class="mt-3 d-flex flex-wrap gap-2">
                    <a href="modules/auth/login.php" class="portal-btn" style="font-size:.78rem;padding:8px 14px;">Student Portal</a>
                </div>
                <?php endif; ?>
            </div>
            <div class="col-lg-4">
                <h4>Quick Links</h4>
                <ul class="footer-links">
                    <li><a href="index.php">Home</a></li>
                    <li><a href="about.php">About</a></li>
                    <li><a href="<?= htmlspecialchars($public_footer_campus_url) ?>">Campuses</a></li>
                    <li><a href="programs.php">Programs</a></li>
                    <li><a href="modules/admissions/apply.php">Admissions</a></li>
                    <li><a href="news.php">News</a></li>
                    <li><a href="contact.php">Contact</a></li>
                    <?php if ($public_footer_portals): ?>
                    <li><a href="modules/lms/index.php">LMS</a></li>
                    <li><a href="modules/examination/index.php">Examinations</a></li>
                    <?php endif; ?>
                </ul>
            </div>
            <div class="col-lg-4">
                <h4>Campus Offices</h4>
                <ul class="footer-links">
                    <?php foreach ($campus_stats as $cs): ?>
                    <li>
                        <a href="<?= htmlspecialchars($cs['url']) ?>"><?= htmlspecialchars($cs['name']) ?> — <?= htmlspecialchars($cs['city']) ?></a>
                        <span style="display:block;color:rgba(255,255,255,.45);font-size:.78rem;font-weight:500;margin-top:2px;"><?= htmlspecialchars($cs['email']) ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">&copy; <?= date('Y') ?> Quaid-e-Azam Group of Colleges. All Rights Reserved.</div>
    </div>
</footer>
<script defer src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script defer src="assets/js/responsive.js?v=3"></script>
<script defer src="assets/js/public-premium.js?v=1"></script>

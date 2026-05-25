<?php
if (!isset($campus_stats)) {
    require_once __DIR__ . '/site-stats.php';
}
$public_footer_campus_url = $public_footer_campus_url ?? 'campus-rajanpur.php';
$public_footer_portals = $public_footer_portals ?? false;
?>
<footer class="public-footer">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-4">
                <h3>Quaid-e-Azam Group of Colleges</h3>
                <p>Disciplined, career-focused education across Rajanpur, Fazilpur, and Kot Mithan since <?= (int)($established_year ?? 2015) ?>.</p>
                <?php if ($public_footer_portals): ?>
                <div class="mt-3 d-flex flex-wrap gap-2">
                    <a href="modules/auth/login.php" class="portal-btn" style="font-size:.78rem;padding:8px 14px;">Student Portal</a>
                    <a href="modules/fee_management/index.php" class="portal-btn" style="font-size:.78rem;padding:8px 14px;background:rgba(255,255,255,.12);">Fee Portal</a>
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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/responsive.js?v=3"></script>

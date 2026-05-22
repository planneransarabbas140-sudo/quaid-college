<?php
// File: modules/ptm/index.php
require_once '../../config/db.php';

if (!isLoggedIn()) {
    redirect('../../index.php');
}

$page_title = "Parent-Teacher Meetings";
include '../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <h2 class="page-title mb-0">Parent-Teacher Meetings (PTM)</h2>
        <button class="btn btn-primary"><i class="fas fa-calendar-check"></i> Schedule PTM</button>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-body text-center p-5">
                <i class="fas fa-handshake fa-4x text-muted mb-3"></i>
                <h4>PTM Scheduler</h4>
                <p class="text-muted">The PTM scheduling and notification system is currently under construction.</p>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

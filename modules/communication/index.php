<?php
/**
 * File: modules/communication/index.php
 * Communication & Notification System for Quaid-e-Azam Group of Colleges
 */
require_once '../../config/db.php';

if (!isLoggedIn()) {
    redirect('../../index.php');
}
requireRole(['admin', 'owner']);

$db = (new Database())->getConnection();

// --- ENSURE TABLES EXIST ---
$db->exec("CREATE TABLE IF NOT EXISTS message_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(100),
    type VARCHAR(50),
    subject VARCHAR(200),
    message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$db->exec("CREATE TABLE IF NOT EXISTS communication_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_type VARCHAR(50),
    sent_to VARCHAR(200),
    subject VARCHAR(200),
    message TEXT,
    recipients_count INT DEFAULT 0,
    status ENUM('Sent','Failed','Pending') DEFAULT 'Sent',
    sent_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Insert default templates if they don't exist
$template_count = $db->query("SELECT COUNT(*) FROM message_templates")->fetchColumn();
if ($template_count == 0) {
    $db->exec("INSERT IGNORE INTO message_templates (title, type, subject, message) VALUES
    ('Fee Reminder', 'WhatsApp', 'Fee Reminder', 'Assalam-o-Alaikum,\nQuaid-e-Azam Group of Colleges\nDear Parent,\nThis is a reminder that fee for [Month] is due. Please submit before [Date] to avoid late charges.\nFee Office: +923338879961\nJazakAllah'),
    ('Absent Alert', 'WhatsApp', 'Absence Alert', 'Assalam-o-Alaikum,\nQuaid-e-Azam Group of Colleges\nDear Parent of [Student Name],\nYour child was ABSENT today [Date] in [Class].\nPlease ensure regular attendance.\nPrincipal: Mr. Zafar Iqbal'),
    ('Exam Schedule', 'WhatsApp', 'Exam Schedule Notice', 'Assalam-o-Alaikum,\nQuaid-e-Azam Group of Colleges\nDear Student/Parent,\nExaminations will begin from [Date].\nPlease prepare accordingly.\nBest of luck!\nPrincipal: Mr. Zafar Iqbal'),
    ('Holiday Notice', 'WhatsApp', 'Holiday Notice', 'Assalam-o-Alaikum,\nQuaid-e-Azam Group of Colleges\nDear Students/Parents,\nCollege will remain CLOSED on [Date] due to [Reason].\nCollege will reopen on [Date].\nThank you'),
    ('Result Published', 'WhatsApp', 'Results Published', 'Assalam-o-Alaikum,\nQuaid-e-Azam Group of Colleges\nDear Student,\nYour [Exam] results have been published.\nPlease login to student portal to view your result.\nwww.qac.edu.pk'),
    ('Meeting Notice', 'WhatsApp', 'PTM Meeting Notice', 'Assalam-o-Alaikum,\nQuaid-e-Azam Group of Colleges\nDear Parent,\nParent Teacher Meeting (PTM) is scheduled on [Date] at [Time].\nVenue: [Campus] Campus\nYour presence is requested.\nPrincipal: Mr. Zafar Iqbal')");
}

function personalizeCommunicationMessage($message, $recipient) {
    $studentName = trim(($recipient['first_name'] ?? '') . ' ' . ($recipient['last_name'] ?? ''));
    return str_replace(
        ['[Student Name]', '[Class]', '[Date]'],
        [$studentName, $recipient['class'] ?? '', date('d M Y')],
        $message
    );
}

function sendCollegeEmail($to, $subject, $message) {
    $to = trim($to);
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $safeSubject = trim($subject) !== '' ? preg_replace('/[\r\n]+/', ' ', trim($subject)) : 'Quaid-e-Azam Group of Colleges Notice';
    $body = trim($message) . "\n\nQuaid-e-Azam Group of Colleges";
    $headers = [
        'From: Quaid-e-Azam Group of Colleges <no-reply@qgc.edu.pk>',
        'Reply-To: no-reply@qgc.edu.pk',
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'X-Mailer: PHP/' . phpversion()
    ];

    return @mail($to, $safeSubject, $body, implode("\r\n", $headers));
}

// --- HANDLE POST ACTIONS ---
$whatsapp_links = [];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action'])) {
    try {
        switch ($_POST['action']) {
            case 'send_message':
                $type = $_POST['message_type'];
                $send_to = $_POST['send_to'];
                $subject = sanitizeInput($_POST['subject'] ?? '');
                $message = $_POST['message'];
                $recipients = [];

                // Recipient Logic
                if ($send_to === 'All Students') {
                    $recipients = $db->query("SELECT first_name, last_name, class, guardian_phone, guardian_phone as whatsapp_number, guardian_email, email FROM students WHERE status = 'Active'")->fetchAll();
                } elseif ($send_to === 'Specific Class') {
                    $stmt = $db->prepare("SELECT first_name, last_name, class, guardian_phone, guardian_phone as whatsapp_number, guardian_email, email FROM students WHERE class = ? AND status = 'Active'");
                    $stmt->execute([$_POST['target_class']]);
                    $recipients = $stmt->fetchAll();
                } elseif ($send_to === 'Specific Student') {
                    $stmt = $db->prepare("SELECT first_name, last_name, class, guardian_phone, guardian_phone as whatsapp_number, guardian_email, email FROM students WHERE id = ?");
                    $stmt->execute([$_POST['target_student']]);
                    $recipients = $stmt->fetchAll();
                } elseif ($send_to === 'All Parents') {
                    $recipients = $db->query("SELECT first_name, last_name, class, guardian_phone, guardian_phone as whatsapp_number, guardian_email, email FROM students WHERE status = 'Active'")->fetchAll();
                } elseif ($send_to === 'All Staff') {
                    $recipients = $db->query("SELECT full_name as first_name, '' as last_name, 'Staff' as class, phone as guardian_phone, phone as whatsapp_number, email as guardian_email, email FROM staff WHERE COALESCE(status, 'Active') = 'Active'")->fetchAll();
                }

                $count = count($recipients);
                $log_status = 'Sent';
                $log_count = $count;

                if ($type === 'WhatsApp') {
                    foreach ($recipients as $r) {
                        // Use whatsapp_number first, then guardian_phone
                        $raw_phone = !empty($r['whatsapp_number']) ? $r['whatsapp_number'] : $r['guardian_phone'];
                        if (empty($raw_phone)) continue;

                        $phone = '92' . ltrim($raw_phone, '0');
                        $encoded_msg = urlencode(personalizeCommunicationMessage($message, $r));
                        $whatsapp_links[] = [
                            'name' => $r['first_name'] . ' ' . $r['last_name'],
                            'class' => $r['class'] ?? '',
                            'link' => "https://wa.me/{$phone}?text={$encoded_msg}"
                        ];
                    }
                } elseif ($type === 'Email') {
                    $sentEmails = 0;
                    $skippedEmails = 0;

                    foreach ($recipients as $r) {
                        $emailTo = trim($r['guardian_email'] ?: ($r['email'] ?? ''));
                        if ($emailTo === '' || !filter_var($emailTo, FILTER_VALIDATE_EMAIL)) {
                            $skippedEmails++;
                            continue;
                        }

                        $sent = sendCollegeEmail($emailTo, $subject, personalizeCommunicationMessage($message, $r));
                        $sent ? $sentEmails++ : $skippedEmails++;
                    }

                    $log_count = $sentEmails;
                    $log_status = $sentEmails > 0 ? 'Sent' : 'Failed';
                }

                // Log the message
                $sql = "INSERT INTO communication_logs (message_type, sent_to, subject, message, recipients_count, status, sent_by) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $db->prepare($sql)->execute([$type, $send_to, $subject, $message, $log_count, $log_status, $_SESSION['user_id']]);
                
                if ($type === 'Email') {
                    if ($sentEmails > 0) {
                        setFlashMessage('success', "Email sent to {$sentEmails} recipient(s). {$skippedEmails} skipped or failed.");
                    } else {
                        setFlashMessage('error', "Email was not sent. Please configure SMTP/sendmail in XAMPP PHP settings, then try again.");
                    }
                    redirect('index.php');
                } elseif (empty($whatsapp_links)) {
                    setFlashMessage('success', "Message sent to $count recipients successfully!");
                    redirect('index.php');
                }
                break;

            case 'add_template':
            case 'edit_template':
                $title = sanitizeInput($_POST['title']);
                $t_type = $_POST['type'];
                $t_sub = sanitizeInput($_POST['subject']);
                $t_msg = $_POST['message'];

                if ($_POST['action'] === 'add_template') {
                    $db->prepare("INSERT INTO message_templates (title, type, subject, message) VALUES (?, ?, ?, ?)")
                       ->execute([$title, $t_type, $t_sub, $t_msg]);
                    setFlashMessage('success', "Template added!");
                } else {
                    $db->prepare("UPDATE message_templates SET title=?, type=?, subject=?, message=? WHERE id=?")
                       ->execute([$title, $t_type, $t_sub, $t_msg, $_POST['id']]);
                    setFlashMessage('success', "Template updated!");
                }
                redirect('index.php#templates');
                break;

            case 'delete_template':
                $db->prepare("DELETE FROM message_templates WHERE id=?")->execute([$_POST['id']]);
                setFlashMessage('success', "Template deleted!");
                redirect('index.php#templates');
                break;
        }
    } catch (Exception $e) {
        setFlashMessage('error', "Error: " . $e->getMessage());
        redirect('index.php');
    }
}

// --- FETCH DATA ---
$total_sent = $db->query("SELECT SUM(recipients_count) FROM communication_logs")->fetchColumn() ?? 0;
$wa_sent = $db->query("SELECT COUNT(*) FROM communication_logs WHERE message_type = 'WhatsApp'")->fetchColumn() ?? 0;
$email_sent = $db->query("SELECT COUNT(*) FROM communication_logs WHERE message_type = 'Email'")->fetchColumn() ?? 0;
$sms_sent = $db->query("SELECT COUNT(*) FROM communication_logs WHERE message_type = 'SMS'")->fetchColumn() ?? 0;

$logs = $db->query("SELECT cl.*, u.username as sender FROM communication_logs cl LEFT JOIN users u ON cl.sent_by = u.id ORDER BY cl.created_at DESC LIMIT 50")->fetchAll();
$templates = $db->query("SELECT * FROM message_templates ORDER BY title ASC")->fetchAll();
$students = $db->query("SELECT id,
                        COALESCE(NULLIF(roll_number, ''), NULLIF(registration_number, ''), CONCAT('STD-', id)) as student_code,
                        first_name, last_name, class
                        FROM students
                        WHERE status = 'Active'
                        ORDER BY first_name ASC")->fetchAll();
$classes = $db->query("SELECT DISTINCT class FROM students ORDER BY class ASC")->fetchAll(PDO::FETCH_COLUMN);

$page_title = "Communication System";
include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb" style="font-size:.82rem;font-family:'Space Mono',monospace;">
                    <li class="breadcrumb-item"><a href="../../dashboard.php" style="color:#4ec2b5;text-decoration:none;"><i class="fas fa-home me-1"></i>Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Communication</li>
                </ol>
            </nav>
            <h2 class="page-title mb-0"><i class="fas fa-comments me-2" style="color: var(--teal);"></i>Communication System</h2>
        </div>
    </div>

    <?php displayFlashMessage(); ?>

    <!-- Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-4 d-flex align-items-center">
                    <div class="stats-icon bg-primary bg-opacity-10 text-primary rounded-3 p-3 me-3">
                        <i class="fas fa-paper-plane fa-2x"></i>
                    </div>
                    <div>
                        <p class="text-muted mb-0 small text-uppercase fw-bold">Sent (Total)</p>
                        <h3 class="mb-0 fw-bold"><?= $total_sent ?></h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-4 d-flex align-items-center">
                    <div class="stats-icon bg-success bg-opacity-10 text-success rounded-3 p-3 me-3">
                        <i class="fab fa-whatsapp fa-2x"></i>
                    </div>
                    <div>
                        <p class="text-muted mb-0 small text-uppercase fw-bold">WhatsApp</p>
                        <h3 class="mb-0 fw-bold"><?= $wa_sent ?></h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-4 d-flex align-items-center">
                    <div class="stats-icon bg-info bg-opacity-10 text-info rounded-3 p-3 me-3">
                        <i class="fas fa-envelope fa-2x"></i>
                    </div>
                    <div>
                        <p class="text-muted mb-0 small text-uppercase fw-bold">Email</p>
                        <h3 class="mb-0 fw-bold"><?= $email_sent ?></h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-4 d-flex align-items-center">
                    <div class="stats-icon bg-warning bg-opacity-10 text-warning rounded-3 p-3 me-3">
                        <i class="fas fa-sms fa-2x"></i>
                    </div>
                    <div>
                        <p class="text-muted mb-0 small text-uppercase fw-bold">SMS</p>
                        <h3 class="mb-0 fw-bold"><?= $sms_sent ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($whatsapp_links)): ?>
    <!-- WhatsApp Link Generator Results -->
    <div class="alert alert-success border-0 shadow-sm rounded-4 p-4 mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold mb-0"><i class="fab fa-whatsapp me-2"></i>WhatsApp messages ready — <?= count($whatsapp_links) ?> recipients</h5>
            <button onclick="openAll()" class="btn btn-success btn-sm rounded-pill px-3">
                <i class="fab fa-whatsapp me-1"></i> Open All Chats
            </button>
        </div>
        <p class="small text-dark mb-4">Click each button to send the message via WhatsApp. Use the "Open All" button to open chats sequentially (with a small delay to avoid browser blocks).</p>
        <div class="d-flex flex-wrap gap-2">
            <?php foreach ($whatsapp_links as $wl): ?>
                <a href="<?= $wl['link'] ?>" target="_blank" class="btn btn-sm btn-success rounded-pill px-3">
                    <i class="fab fa-whatsapp me-1"></i> <?= htmlspecialchars($wl['name']) ?> 
                    <?php if(!empty($wl['class'])): ?><small class="opacity-75">(<?= $wl['class'] ?>)</small><?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
        <div class="mt-4 pt-3 border-top">
            <a href="index.php" class="btn btn-navy btn-sm">Done & Back</a>
        </div>
    </div>
    <?php endif; ?>

    <!-- Quick Actions -->
    <div class="row g-3 mb-4 no-print">
        <div class="col-md-4">
            <button class="btn btn-warning w-100 py-3 rounded-4 shadow-sm fw-bold d-flex align-items-center justify-content-center gap-2" onclick="loadQuickTemplate('Fee Reminder')">
                <i class="fas fa-money-bill-wave"></i> Send Fee Reminder to All
            </button>
        </div>
        <div class="col-md-4">
            <button class="btn btn-danger w-100 py-3 rounded-4 shadow-sm fw-bold d-flex align-items-center justify-content-center gap-2" onclick="loadQuickTemplate('Absent Alert')">
                <i class="fas fa-user-times"></i> Send Absent Alerts
            </button>
        </div>
        <div class="col-md-4">
            <button class="btn btn-info w-100 py-3 rounded-4 shadow-sm fw-bold d-flex align-items-center justify-content-center gap-2" onclick="loadQuickTemplate('Exam Schedule')">
                <i class="fas fa-book"></i> Send Exam Notice
            </button>
        </div>
    </div>

    <!-- Main Tabs -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-header bg-white border-0 pt-4 px-4">
            <ul class="nav nav-tabs card-header-tabs" id="commTabs" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active fw-bold small" id="send-tab" data-bs-toggle="tab" data-bs-target="#send-pane" type="button" role="tab">SEND MESSAGE</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link fw-bold small" id="history-tab" data-bs-toggle="tab" data-bs-target="#history-pane" type="button" role="tab">MESSAGE HISTORY</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link fw-bold small" id="templates-tab" data-bs-toggle="tab" data-bs-target="#templates-pane" type="button" role="tab">TEMPLATES</button>
                </li>
            </ul>
        </div>
        <div class="card-body p-4">
            <div class="tab-content">
                
                <!-- Send Message Tab -->
                <div class="tab-pane fade show active" id="send-pane" role="tabpanel">
                    <form action="" method="POST" id="mainSendForm">
                        <input type="hidden" name="action" value="send_message">
                        <div class="row g-4">
                            <div class="col-md-4">
                                <div class="mb-4">
                                    <label class="form-label fw-bold">Message Type</label>
                                    <div class="message-type-options">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="message_type" id="typeWA" value="WhatsApp" checked onchange="toggleSubject(false)">
                                            <label class="form-check-label" for="typeWA">WhatsApp <i class="fas fa-check-circle text-success small"></i></label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="message_type" id="typeEmail" value="Email" onchange="toggleSubject(true)">
                                            <label class="form-check-label" for="typeEmail">Email <i class="fas fa-check-circle text-success small"></i></label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="message_type" id="typeSMS" value="SMS" onchange="toggleSubject(false)">
                                            <label class="form-check-label" for="typeSMS">Announcement <i class="fas fa-check-circle text-success small"></i></label>
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-4">
                                    <label class="form-label fw-bold">Send To</label>
                                    <select name="send_to" id="send_to" class="form-select" onchange="toggleRecipients(this.value)" required>
                                        <option value="All Students">All Students</option>
                                        <option value="Specific Class">Specific Class</option>
                                        <option value="Specific Student">Specific Student</option>
                                        <option value="All Staff">All Staff</option>
                                        <option value="All Parents">All Parents</option>
                                    </select>
                                </div>
                                <div id="classFilter" class="mb-4 d-none">
                                    <label class="form-label fw-bold small">Select Class</label>
                                    <select name="target_class" class="form-select">
                                        <?php foreach ($classes as $c): ?>
                                            <option value="<?= $c ?>"><?= $c ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div id="studentFilter" class="mb-4 d-none">
                                    <label class="form-label fw-bold small">Select Student</label>
                                    <select name="target_student" class="form-select select2">
                                        <?php foreach ($students as $s): ?>
                                            <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['student_code']) ?> - <?= htmlspecialchars($s['first_name'].' '.$s['last_name']) ?> (<?= htmlspecialchars($s['class'] ?? '') ?>)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-4">
                                    <label class="form-label fw-bold">Quick Templates</label>
                                    <select id="templatePicker" class="form-select" onchange="applyTemplate(this.value)">
                                        <option value="">Select Template...</option>
                                        <?php foreach ($templates as $t): ?>
                                            <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['title']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-8">
                                <div id="subjectGroup" class="mb-4 d-none">
                                    <label class="form-label fw-bold">Email Subject</label>
                                    <input type="text" name="subject" id="msg_subject" class="form-control" placeholder="Enter email subject">
                                </div>
                                <div class="mb-4">
                                    <label class="form-label fw-bold">Message Content</label>
                                    <textarea name="message" id="msg_content" class="form-control" rows="10" placeholder="Type your message here..." required></textarea>
                                    <div class="mt-2 text-muted small">
                                        Use placeholders: [Student Name], [Class], [Date], [Month], [Exam], [Reason]
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="scheduleSend">
                                        <label class="form-check-label" for="scheduleSend">Schedule for later</label>
                                    </div>
                                    <button type="submit" class="btn btn-navy px-5 py-2 fw-bold rounded-pill">
                                        <i class="fas fa-paper-plane me-2"></i> SEND NOW
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- History Tab -->
                <div class="tab-pane fade" id="history-pane" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle datatable">
                            <thead class="bg-light">
                                <tr>
                                    <th>Date & Time</th>
                                    <th>Type</th>
                                    <th>Target</th>
                                    <th>Recipients</th>
                                    <th>Message Preview</th>
                                    <th>Sent By</th>
                                    <th class="text-end">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td><?= date('d M, h:i A', strtotime($log['created_at'])) ?></td>
                                    <td>
                                        <?php if($log['message_type'] == 'WhatsApp'): ?>
                                            <span class="badge bg-success-subtle text-success"><i class="fab fa-whatsapp me-1"></i>WhatsApp</span>
                                        <?php elseif($log['message_type'] == 'Email'): ?>
                                            <span class="badge bg-info-subtle text-info"><i class="fas fa-envelope me-1"></i>Email</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning-subtle text-warning"><i class="fas fa-sms me-1"></i>SMS</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $log['sent_to'] ?></td>
                                    <td class="fw-bold"><?= $log['recipients_count'] ?></td>
                                    <td><small class="text-truncate d-inline-block" style="max-width: 250px;"><?= htmlspecialchars(substr($log['message'], 0, 80)) ?>...</small></td>
                                    <td><?= htmlspecialchars($log['sender']) ?></td>
                                    <td class="text-end">
                                        <span class="badge bg-success">Sent</span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Templates Tab -->
                <div class="tab-pane fade" id="templates-pane" role="tabpanel">
                    <div class="d-flex justify-content-between mb-4">
                        <h5 class="fw-bold mb-0">Message Templates</h5>
                        <button class="btn btn-primary btn-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#templateModal">
                            <i class="fas fa-plus me-1"></i> Create Template
                        </button>
                    </div>
                    <div class="row g-3">
                        <?php foreach ($templates as $t): ?>
                        <div class="col-md-4">
                            <div class="card border rounded-4 h-100 hover-shadow transition">
                                <div class="card-body p-4">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h6 class="fw-bold text-navy mb-0"><?= htmlspecialchars($t['title']) ?></h6>
                                        <span class="badge bg-light text-dark small"><?= $t['type'] ?></span>
                                    </div>
                                    <p class="text-muted small mb-4" style="height: 60px; overflow: hidden;"><?= nl2br(htmlspecialchars($t['message'])) ?></p>
                                    <div class="d-flex gap-2">
                                        <button class="btn btn-sm btn-outline-info flex-grow-1" onclick='editTemplate(<?= json_encode($t) ?>)'>Edit</button>
                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteTemplate(<?= $t['id'] ?>)"><i class="fas fa-trash"></i></button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- Template Modal -->
<div class="modal fade" id="templateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-navy text-white">
                <h5 class="modal-title" id="templateModalTitle">Create Message Template</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST" id="templateForm">
                <input type="hidden" name="action" id="templateAction" value="add_template">
                <input type="hidden" name="id" id="template_id">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Template Title</label>
                        <input type="text" name="title" id="t_title" class="form-control" required placeholder="e.g. Fee Reminder">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Message Type</label>
                        <select name="type" id="t_type" class="form-select">
                            <option value="WhatsApp">WhatsApp</option>
                            <option value="Email">Email</option>
                            <option value="SMS">SMS</option>
                            <option value="Announcement">Announcement</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Email Subject (Optional)</label>
                        <input type="text" name="subject" id="t_subject" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Message Body</label>
                        <textarea name="message" id="t_message" class="form-control" rows="5" required></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4" id="templateSubmitBtn">Save Template</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Template Modal -->
<div class="modal fade" id="deleteTemplateModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form action="" method="POST">
                <input type="hidden" name="action" value="delete_template">
                <input type="hidden" name="id" id="delete_t_id">
                <div class="modal-body p-4 text-center">
                    <i class="fas fa-trash fa-3x text-danger mb-3"></i>
                    <h6>Delete Template?</h6>
                </div>
                <div class="modal-footer border-0 justify-content-center">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">No</button>
                    <button type="submit" class="btn btn-danger">Yes, Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    :root {
        --teal: #4ec2b5;
        --navy: #0f2d48;
    }
    .bg-navy { background-color: var(--navy) !important; }
    .text-navy { color: var(--navy) !important; }
    .btn-navy { background-color: var(--navy); color: white; }
    .btn-navy:hover { background-color: #1a3a5a; color: white; }
    
    .stats-icon {
        width: 60px;
        height: 60px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .page-title { font-family: 'Playfair Display', serif; font-weight: 700; color: var(--navy); }
    
    .nav-tabs .nav-link {
        color: #64748b;
        border: none;
        padding: 1rem 1.5rem;
        border-bottom: 2px solid transparent;
    }
    .nav-tabs .nav-link.active {
        color: var(--teal);
        background: transparent;
        border-bottom-color: var(--teal);
    }

    .message-type-options {
        display: grid;
        grid-template-columns: 1fr;
        gap: .75rem;
        max-width: 100%;
    }

    .message-type-options .form-check {
        position: relative;
        min-height: 48px;
        display: flex;
        align-items: center;
        margin: 0;
        padding: 10px 14px 10px 42px;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        background: #fff;
        overflow: hidden;
        transition: all 0.25s ease;
    }

    .message-type-options .form-check:has(.form-check-input:checked) {
        border-color: var(--teal);
        background: rgba(78, 194, 181, 0.08);
        box-shadow: 0 10px 24px rgba(78, 194, 181, 0.12);
    }

    .message-type-options .form-check-input {
        position: absolute;
        left: 16px;
        top: 50%;
        margin: 0;
        transform: translateY(-50%);
    }

    .message-type-options .form-check-label {
        width: 100%;
        color: #334155;
        font-weight: 700;
        line-height: 1.25;
        white-space: normal;
        overflow-wrap: anywhere;
    }
    
    .transition { transition: all 0.3s ease; }
    .hover-shadow:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.05) !important;
    }

    /* Modal Fixes */
    .modal { z-index: 99999 !important; }
    .modal-backdrop { z-index: 99998 !important; }
    .modal-dialog { z-index: 100000 !important; }
</style>

<script>
const templatesData = <?= json_encode($templates) ?>;

function toggleRecipients(val) {
    document.getElementById('classFilter').classList.toggle('d-none', val !== 'Specific Class');
    document.getElementById('studentFilter').classList.toggle('d-none', val !== 'Specific Student');
}

function toggleSubject(show) {
    document.getElementById('subjectGroup').classList.toggle('d-none', !show);
}

function applyTemplate(id) {
    if (!id) return;
    const template = templatesData.find(t => t.id == id);
    if (template) {
        document.getElementById('msg_content').value = template.message;
        if (template.subject) {
            document.getElementById('msg_subject').value = template.subject;
            toggleSubject(true);
            document.getElementById('typeEmail').checked = true;
        } else {
            toggleSubject(false);
            document.getElementById('typeWA').checked = true;
        }
    }
}

function loadQuickTemplate(title) {
    const template = templatesData.find(t => t.title === title);
    if (template) {
        document.getElementById('msg_content').value = template.message;
        document.getElementById('msg_subject').value = template.subject || '';
        document.getElementById('send_to').value = 'All Students';
        toggleRecipients('All Students');
        
        if (template.subject) {
            document.getElementById('typeEmail').checked = true;
            toggleSubject(true);
        } else {
            document.getElementById('typeWA').checked = true;
            toggleSubject(false);
        }
        
        window.scrollTo({ top: document.getElementById('mainSendForm').offsetTop - 100, behavior: 'smooth' });
    }
}

function editTemplate(t) {
    document.getElementById('templateModalTitle').innerText = 'Edit Template';
    document.getElementById('templateAction').value = 'edit_template';
    document.getElementById('template_id').value = t.id;
    document.getElementById('t_title').value = t.title;
    document.getElementById('t_type').value = t.type;
    document.getElementById('t_subject').value = t.subject;
    document.getElementById('t_message').value = t.message;
    document.getElementById('templateSubmitBtn').innerText = 'Update Template';
    new bootstrap.Modal(document.getElementById('templateModal')).show();
}

function deleteTemplate(id) {
    document.getElementById('delete_t_id').value = id;
    new bootstrap.Modal(document.getElementById('deleteTemplateModal')).show();
}

// Handle URL hash for tabs
// Handle URL hash for tabs
window.addEventListener('DOMContentLoaded', () => {
    const hash = window.location.hash;
    if (hash) {
        const tabEl = document.querySelector(`button[data-bs-target="${hash}-pane"]`);
        if (tabEl) bootstrap.Tab.getOrCreateInstance(tabEl).show();
    }
});

function openAll(){
    var links = <?= json_encode(array_column($whatsapp_links, 'link')) ?>;
    links.forEach(function(link, i){
        setTimeout(function(){
            window.open(link, '_blank');
        }, i * 1000); // 1 second delay to prevent popup blocks
    });
}
</script>

<?php include '../../includes/footer.php'; ?>

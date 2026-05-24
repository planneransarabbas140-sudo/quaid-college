<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/shared_functions.php';
startSecureSession();
$db = (new Database())->getConnection();
if (!isLoggedIn()) {
    redirect('../../auth/login.php');
}
$user_id = getUserId();

$campuses = [];
$classes = [];
$sections = [];
$templates = [];
$exams = [];
$selected_group = trim((string)($_GET['group'] ?? 'all_students'));
$selected_class = trim((string)($_GET['class'] ?? ''));
$selected_section = trim((string)($_GET['section'] ?? ''));
$selected_campus = trim((string)($_GET['campus'] ?? ''));
$valid_groups = ['all_students', 'staff', 'fee_defaulters', 'absentees', 'late', 'failed'];
if (!in_array($selected_group, $valid_groups, true)) {
    $selected_group = 'all_students';
}

try {
    $campuses = getAllCampuses($db);
    $classes = getAllClasses($db, $selected_campus);
    $sections = $selected_class !== '' ? getSectionsByClass($db, $selected_class) : [];

    if (tableExists($db, 'message_templates')) {
        $stmt = $db->query("SELECT id, name, template FROM message_templates ORDER BY name");
        $templates = $stmt->fetchAll();
    }

    if (tableExists($db, 'exam_schedule')) {
        $stmt = $db->query("SELECT id, name FROM exam_schedule ORDER BY id DESC");
        $exams = $stmt->fetchAll();
    }
} catch (Exception $e) {
    // ignore
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>WhatsApp Center</title>
    <link rel="stylesheet" href="./whatsapp-center.css">
</head>
<body>
<?php if (file_exists(__DIR__ . '/../../includes/header.php')) include_once __DIR__ . '/../../includes/header.php'; ?>
<div class="wc-container">
    <aside class="wc-sidebar">
        <h3>Communication</h3>
        <ul>
            <li><a href="../../vouchers.php">Vouchers</a></li>
            <li><a href="index.php#birthdays">Birthdays</a></li>
            <li class="active"><a href="whatsapp-center.php">WhatsApp Center</a></li>
        </ul>
    </aside>
    <main class="wc-main">
        <header class="wc-tabs">
            <a class="tab" href="../../vouchers.php">Vouchers</a>
            <a class="tab" href="index.php#birthdays">Birthdays</a>
            <a class="tab" href="index.php#pdf-generator">PDF Generator</a>
            <a class="tab" href="index.php#data-extractor">Data Extractor</a>
            <a class="tab active" href="whatsapp-center.php">WhatsApp Center</a>
        </header>

        <section class="wc-controls">
            <div class="form-row">
                <label>Target Group</label>
                <select id="target_group">
                    <option value="all_students" <?php echo $selected_group === 'all_students' ? 'selected' : ''; ?>>All Students</option>
                    <option value="staff" <?php echo $selected_group === 'staff' ? 'selected' : ''; ?>>Staff</option>
                    <option value="fee_defaulters" <?php echo $selected_group === 'fee_defaulters' ? 'selected' : ''; ?>>Fee Defaulters</option>
                    <option value="absentees" <?php echo $selected_group === 'absentees' ? 'selected' : ''; ?>>Absentees</option>
                    <option value="late" <?php echo $selected_group === 'late' ? 'selected' : ''; ?>>Late Students</option>
                    <option value="failed" <?php echo $selected_group === 'failed' ? 'selected' : ''; ?>>Failed Students</option>
                </select>
            </div>

            <div class="form-row">
                <label>Class</label>
                <select id="filter_class">
                    <option value="">-- All --</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?php echo htmlspecialchars($c['id']); ?>" <?php echo $selected_class === (string)$c['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['class_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-row">
                <label>Section</label>
                <select id="filter_section" <?php echo $selected_class === '' ? 'disabled' : ''; ?>>
                    <option value=""><?php echo $selected_class === '' ? 'Select class first' : '-- All --'; ?></option>
                    <?php foreach ($sections as $s): ?>
                        <option value="<?php echo htmlspecialchars($s['id']); ?>" <?php echo $selected_section === (string)$s['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($s['section_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-row">
                <label>Campus</label>
                <select id="filter_campus">
                    <option value="">-- All --</option>
                    <?php foreach ($campuses as $camp): ?>
                        <option value="<?php echo (int)$camp['id']; ?>" <?php echo $selected_campus === (string)$camp['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($camp['campus_name'] ?? $camp['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-row" id="attendance_date_row" style="display:none;">
                <label>Attendance Date</label>
                <input type="date" id="attendance_date" />
            </div>

            <div class="form-row" id="exam_row" style="display:none;">
                <label>Exam</label>
                <select id="exam_id">
                    <option value="">-- Select Exam --</option>
                    <?php foreach ($exams as $ex): ?>
                        <option value="<?php echo (int)$ex['id']; ?>"><?php echo htmlspecialchars($ex['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-row actions">
                <button id="preview_btn" class="btn">Preview Recipients</button>
            </div>
        </section>

        <section class="wc-preview">
            <div class="preview-header">
                <h4>Recipients Preview</h4>
                <div class="preview-actions">
                    <button id="select_all">Select All</button>
                    <button id="deselect_all">Deselect All</button>
                    <button id="send_selected" class="primary">Send Selected</button>
                    <button id="send_all" class="primary">Send All</button>
                </div>
            </div>
            <div id="spinner" class="spinner" style="display:none;"></div>
            <div class="table-wrap">
                <table id="recipients_table">
                    <thead>
                        <tr>
                            <th></th>
                            <th>Name</th>
                            <th>Guardian Phone</th>
                            <th>Class</th>
                            <th>Info</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </section>

        <section class="wc-message">
            <h4>Message Composer</h4>
            <div class="composer-row">
                <label>Template</label>
                <select id="template_select">
                    <option value="">-- Select Template --</option>
                    <?php foreach ($templates as $t): ?>
                        <option data-template="<?php echo htmlspecialchars($t['template'], ENT_QUOTES); ?>"><?php echo htmlspecialchars($t['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="composer-row">
                <label>Message</label>
                <textarea id="message_text" rows="5" placeholder="Write your message... Use variables like {student_name}, {guardian_name}, {class}, {amount_due}, {date}"></textarea>
            </div>
            <div class="composer-actions">
                <button type="button" id="save_draft" class="btn">Save Draft</button>
                <button type="button" id="clear_draft" class="btn">Clear Draft</button>
                <a href="index.php" class="btn back-btn">Back</a>
            </div>
        </section>
    </main>
</div>

<script src="./whatsapp-center.js"></script>
</body>
</html>

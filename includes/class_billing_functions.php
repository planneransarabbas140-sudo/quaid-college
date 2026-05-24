<?php
// Class Billing Rules — Functions
// Version: 1.0
// Last updated: 2026-05-23
// Depends on: students.class, fee_structure.class
// Used by: settings-class-billing-rules.php, (future) fee generation hook

require_once __DIR__ . '/../config/db.php';

// Returns all active classes for dropdown
function getClassListForBillingRules($conn) {
    try {
        $classes = [];

        if (tableExists($conn, 'students') && columnExists($conn, 'students', 'class')) {
            $stmt = $conn->prepare("
                SELECT DISTINCT class
                FROM students
                WHERE class IS NOT NULL AND class <> ''
                ORDER BY class ASC
            ");
            $stmt->execute();
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $c) {
                $c = trim((string)$c);
                if ($c !== '') {
                    $classes[$c] = true;
                }
            }
        }

        if (tableExists($conn, 'fee_structure') && columnExists($conn, 'fee_structure', 'class')) {
            $stmt = $conn->prepare("
                SELECT DISTINCT class
                FROM fee_structure
                WHERE class IS NOT NULL AND class <> ''
                  AND (is_active = 1 OR is_active IS NULL)
                ORDER BY class ASC
            ");
            $stmt->execute();
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $c) {
                $c = trim((string)$c);
                if ($c !== '') {
                    $classes[$c] = true;
                }
            }
        }

        $list = array_keys($classes);
        natcasesort($list);
        return array_values($list);
    } catch (Exception $e) {
        error_log('Class Billing Rules DB Error: ' . $e->getMessage());
        return [];
    }
}

// Returns all saved billing rules joined with class name
function getSavedClassBillingRules($conn) {
    if (!tableExists($conn, 'class_billing_rules')) {
        return [];
    }

    try {
        $stmt = $conn->prepare("
            SELECT id, class_name, billing_frequency, billing_type, rule_note, created_at, updated_at
            FROM class_billing_rules
            ORDER BY class_name ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Exception $e) {
        error_log('Class Billing Rules DB Error: ' . $e->getMessage());
        return [];
    }
}

// Returns one rule by ID (for edit pre-fill)
function getBillingRuleById($conn, $rule_id) {
    if (!tableExists($conn, 'class_billing_rules')) {
        return null;
    }

    $rule_id = (int)$rule_id;
    if ($rule_id <= 0) {
        return null;
    }

    try {
        $stmt = $conn->prepare("SELECT * FROM class_billing_rules WHERE id = ? LIMIT 1");
        $stmt->execute([$rule_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    } catch (Exception $e) {
        error_log('Class Billing Rules DB Error: ' . $e->getMessage());
        return null;
    }
}

// Returns existing rule for a class (to prevent duplicates)
function getExistingRuleForClass($conn, $class_id) {
    if (!tableExists($conn, 'class_billing_rules')) {
        return null;
    }

    $className = trim((string)$class_id);
    if ($className === '') {
        return null;
    }

    try {
        $stmt = $conn->prepare("SELECT * FROM class_billing_rules WHERE class_name = ? LIMIT 1");
        $stmt->execute([$className]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    } catch (Exception $e) {
        error_log('Class Billing Rules DB Error: ' . $e->getMessage());
        return null;
    }
}

// Inserts new rule — returns inserted ID or false
function insertClassBillingRule($conn, $class_id, $frequency, $type, $note) {
    if (!tableExists($conn, 'class_billing_rules')) {
        return false;
    }

    $className = trim((string)$class_id);
    if ($className === '') {
        return false;
    }

    $allowedFrequencies = ['monthly', 'quarterly', 'yearly', 'one-time'];
    $allowedTypes = ['individual', 'family'];
    if (!in_array($frequency, $allowedFrequencies, true) || !in_array($type, $allowedTypes, true)) {
        return false;
    }

    $note = trim(strip_tags((string)$note));
    if (mb_strlen($note) > 255) {
        $note = mb_substr($note, 0, 255);
    }

    try {
        $stmt = $conn->prepare("
            INSERT INTO class_billing_rules (class_name, billing_frequency, billing_type, rule_note)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$className, $frequency, $type, ($note === '' ? null : $note)]);
        return (int)$conn->lastInsertId();
    } catch (Exception $e) {
        error_log('Class Billing Rules DB Error: ' . $e->getMessage());
        return false;
    }
}

// Updates existing rule — returns true or false
function updateClassBillingRule($conn, $rule_id, $class_id, $frequency, $type, $note) {
    if (!tableExists($conn, 'class_billing_rules')) {
        return false;
    }

    $rule_id = (int)$rule_id;
    $className = trim((string)$class_id);
    if ($rule_id <= 0 || $className === '') {
        return false;
    }

    $allowedFrequencies = ['monthly', 'quarterly', 'yearly', 'one-time'];
    $allowedTypes = ['individual', 'family'];
    if (!in_array($frequency, $allowedFrequencies, true) || !in_array($type, $allowedTypes, true)) {
        return false;
    }

    $note = trim(strip_tags((string)$note));
    if (mb_strlen($note) > 255) {
        $note = mb_substr($note, 0, 255);
    }

    try {
        $stmt = $conn->prepare("
            UPDATE class_billing_rules
            SET class_name = ?, billing_frequency = ?, billing_type = ?, rule_note = ?
            WHERE id = ?
        ");
        return $stmt->execute([$className, $frequency, $type, ($note === '' ? null : $note), $rule_id]);
    } catch (Exception $e) {
        error_log('Class Billing Rules DB Error: ' . $e->getMessage());
        return false;
    }
}

// Deletes rule by ID — returns true or false
function deleteClassBillingRule($conn, $rule_id) {
    if (!tableExists($conn, 'class_billing_rules')) {
        return false;
    }

    $rule_id = (int)$rule_id;
    if ($rule_id <= 0) {
        return false;
    }

    // Safe delete pattern: check existence first
    $existing = getBillingRuleById($conn, $rule_id);
    if (!$existing) {
        return false;
    }

    try {
        $stmt = $conn->prepare("DELETE FROM class_billing_rules WHERE id = ? LIMIT 1");
        return $stmt->execute([$rule_id]);
    } catch (Exception $e) {
        error_log('Class Billing Rules DB Error: ' . $e->getMessage());
        return false;
    }
}

// Used by fee generation: returns rule for a class or null if none
function getBillingRuleForClass($conn, $class_id) {
    return getExistingRuleForClass($conn, $class_id);
}

// Validates POST input before save/update
function validateBillingRuleInput($data) {
    $errors = [];

    $class_id = trim((string)($data['class_id'] ?? ''));
    if ($class_id === '') {
        $errors[] = 'Please select a class.';
    }

    $allowedFrequencies = ['monthly', 'quarterly', 'yearly', 'one-time'];
    $billing_frequency = (string)($data['billing_frequency'] ?? '');
    if (!in_array($billing_frequency, $allowedFrequencies, true)) {
        $errors[] = 'Please select a valid billing frequency.';
    }

    $allowedTypes = ['individual', 'family'];
    $billing_type = (string)($data['billing_type'] ?? '');
    if (!in_array($billing_type, $allowedTypes, true)) {
        $errors[] = 'Please select a valid billing type.';
    }

    $rule_note = trim(strip_tags((string)($data['rule_note'] ?? '')));
    if (mb_strlen($rule_note) > 255) {
        $errors[] = 'Rule note must be 255 characters or less.';
    }

    return [
        'valid' => empty($errors),
        'errors' => $errors,
        'clean' => [
            'class_id' => $class_id,
            'billing_frequency' => $billing_frequency,
            'billing_type' => $billing_type,
            'rule_note' => $rule_note,
        ],
    ];
}


<?php
// MASTER TABLE MAP - Single Source of Truth
// Generated: 2026-05-24
// Rule: ALL modules must use ONLY these tables/functions for these entities.
//
// Schema note:
// This project currently stores classes, sections, subjects, and sessions in
// operational tables instead of dedicated lookup tables. Do not create parallel
// lookup data in modules. Use includes/shared_functions.php so every module
// reads the same derived source.
//
// TODO: If normalized lookup tables are added later, migrate these text-column
// sources behind shared_functions.php first, then update this map.

$MASTER_TABLES = [
    'school_info'   => 'campuses',
    'sessions'      => 'config:getCurrentAcademicYear',
    'campuses'      => 'campuses',
    'classes'       => 'students.class',
    'sections'      => 'students.section',
    'class_section' => 'students.class + students.section',
    'students'      => 'students',
    'families'      => 'students.guardian_phone',
    'staff'         => 'staff',
    'subjects'      => 'exam_schedule.subject',
    'fee_heads'     => 'fee_structure',
    'exams'         => 'exam_schedule',
    'attendance'    => 'student_attendance',
    'marks'         => 'exam_marks',
    'fee_challan'   => 'fee_collections',
    'fee_payments'  => 'fee_collections',
    'vouchers'      => 'vouchers',
    'voucher_items' => 'voucher_items',
    'result_cards'  => 'result_cards',
    'grading'       => 'grading_system',
    'income'        => 'income',
    'expenses'      => 'expenses',
];
?>

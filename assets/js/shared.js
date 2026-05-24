// Shared backend UI helpers for cascading dropdowns and common formatting.
(function (window, $) {
    'use strict';

    var endpoint = (window.BASE_URL || '') + 'ajax/shared-ajax.php';

    function option(value, label) {
        return '<option value="' + String(value || '').replace(/"/g, '&quot;') + '">' + String(label || '') + '</option>';
    }

    window.bindClassSectionCascade = function (classSelectId, sectionSelectId) {
        var $classSelect = $('#' + classSelectId);
        var $sectionSelect = $('#' + sectionSelectId);
        if (!$classSelect.length || !$sectionSelect.length) return;

        $classSelect.on('change', function () {
            var classId = $(this).val();
            $sectionSelect.html(option('', classId ? 'Loading...' : 'Select Section (choose class first)')).prop('disabled', true);
            if (!classId) return;

            $.getJSON(endpoint, { action: 'get_sections', class_id: classId }, function (res) {
                $sectionSelect.html(option('', 'All Sections'));
                if (res.success && res.data && res.data.length) {
                    $.each(res.data, function (_, sec) {
                        $sectionSelect.append(option(sec.id, sec.section_name));
                    });
                } else {
                    $sectionSelect.html(option('', 'No sections found'));
                }
                $sectionSelect.prop('disabled', false);
            }).fail(function () {
                $sectionSelect.html(option('', 'Unable to load sections')).prop('disabled', false);
            });
        });
    };

    window.bindClassStudentCascade = function (classSelectId, studentSelectId, sectionSelectId) {
        var $classSelect = $('#' + classSelectId);
        var $studentSelect = $('#' + studentSelectId);
        var $sectionSelect = sectionSelectId ? $('#' + sectionSelectId) : $();
        if (!$classSelect.length || !$studentSelect.length) return;

        function loadStudents() {
            var classId = $classSelect.val();
            var sectionId = $sectionSelect.length ? $sectionSelect.val() : '';
            $studentSelect.html(option('', classId ? 'Loading...' : 'Select Student')).prop('disabled', true);
            if (!classId) return;
            $.getJSON(endpoint, { action: 'get_students', class_id: classId, section_id: sectionId }, function (res) {
                $studentSelect.html(option('', 'Select Student'));
                if (res.success && res.data && res.data.length) {
                    $.each(res.data, function (_, student) {
                        var label = (student.name || ((student.first_name || '') + ' ' + (student.last_name || '')).trim());
                        if (student.roll_number) label += ' (' + student.roll_number + ')';
                        $studentSelect.append(option(student.id, label));
                    });
                } else {
                    $studentSelect.html(option('', 'No students found'));
                }
                $studentSelect.prop('disabled', false);
            }).fail(function () {
                $studentSelect.html(option('', 'Unable to load students')).prop('disabled', false);
            });
        }

        $classSelect.on('change', loadStudents);
        if ($sectionSelect.length) $sectionSelect.on('change', loadStudents);
    };

    window.bindClassSubjectCascade = function (classSelectId, subjectContainerId) {
        $('#' + classSelectId).on('change', function () {
            var classId = $(this).val();
            var $target = $('#' + subjectContainerId);
            if (!$target.length || !classId) return;
            $target.html('<div class="text-muted">Loading subjects...</div>');
            $.getJSON(endpoint, { action: 'get_subjects', class_id: classId }, function (res) {
                if (!res.success || !res.data.length) {
                    $target.html('<div class="text-muted">No subjects found.</div>');
                    return;
                }
                var html = '<div class="list-group">';
                $.each(res.data, function (_, subject) {
                    html += '<div class="list-group-item d-flex justify-content-between"><span>' + subject.subject_name + '</span><span>' + (subject.total_marks || 100) + '</span></div>';
                });
                $target.html(html + '</div>');
            });
        });
    };

    window.bindClassFeeHeadsCascade = function (classSelectId, feeTableContainerId) {
        $('#' + classSelectId).on('change', function () {
            var classId = $(this).val();
            var $target = $('#' + feeTableContainerId);
            if (!$target.length || !classId) return;
            $target.html('<div class="text-muted">Loading fee heads...</div>');
            $.getJSON(endpoint, { action: 'get_fee_heads', class_id: classId }, function (res) {
                if (!res.success || !res.data.length) {
                    $target.html('<div class="text-muted">No fee heads configured.</div>');
                    return;
                }
                var html = '<table class="table table-sm"><thead><tr><th>Fee Head</th><th class="text-end">Amount</th></tr></thead><tbody>';
                $.each(res.data, function (_, fee) {
                    html += '<tr><td>' + fee.fee_head_name + '</td><td class="text-end">' + window.formatCurrency(fee.amount) + '</td></tr>';
                });
                $target.html(html + '</tbody></table>');
            });
        });
    };

    window.initStudentSearch = function (inputId, resultContainerId, onSelect) {
        var timer;
        $('#' + inputId).on('input', function () {
            var query = $(this).val();
            var $results = $('#' + resultContainerId);
            clearTimeout(timer);
            if (query.length < 2) {
                $results.empty();
                return;
            }
            timer = setTimeout(function () {
                $.getJSON(endpoint, { action: 'search_students', q: query }, function (res) {
                    $results.empty();
                    if (!res.success || !res.data.length) return;
                    $.each(res.data, function (_, student) {
                        var label = student.name || ((student.first_name || '') + ' ' + (student.last_name || '')).trim();
                        $('<button type="button" class="list-group-item list-group-item-action"></button>')
                            .text(label)
                            .on('click', function () { onSelect(student); $results.empty(); })
                            .appendTo($results);
                    });
                });
            }, 250);
        });
    };

    window.showAlert = function (type, message, containerId) {
        var map = { success: 'alert-success', error: 'alert-danger', warning: 'alert-warning', info: 'alert-info' };
        var html = '<div class="alert ' + (map[type] || map.info) + ' alert-dismissible fade show" role="alert">' + message + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        $('#' + containerId).html(html);
        setTimeout(function () { $('#' + containerId + ' .alert').alert('close'); }, 4000);
    };

    window.confirmAction = function (message, onConfirm) {
        if (window.confirm(message)) onConfirm();
    };

    window.showLoading = function (containerId) {
        $('#' + containerId).html('<div class="text-center text-muted py-3"><span class="spinner-border spinner-border-sm me-2"></span>Loading...</div>');
    };

    window.hideLoading = function (containerId) {
        $('#' + containerId).find('.spinner-border').closest('.text-center').remove();
    };

    window.formatCurrency = function (amount) {
        return 'Rs. ' + parseFloat(amount || 0).toLocaleString('en-PK', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
    };
})(window, window.jQuery);

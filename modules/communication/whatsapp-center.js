/* WhatsApp Center frontend logic */
document.addEventListener('DOMContentLoaded', function(){
    const previewBtn = document.getElementById('preview_btn');
    const spinner = document.getElementById('spinner');
    const tbody = document.querySelector('#recipients_table tbody');
    const selectAllBtn = document.getElementById('select_all');
    const deselectAllBtn = document.getElementById('deselect_all');
    const sendSelectedBtn = document.getElementById('send_selected');
    const sendAllBtn = document.getElementById('send_all');
    const targetSelect = document.getElementById('target_group');
    const classSelect = document.getElementById('filter_class');
    const sectionSelect = document.getElementById('filter_section');
    const campusSelect = document.getElementById('filter_campus');
    const attendanceRow = document.getElementById('attendance_date_row');
    const examRow = document.getElementById('exam_row');
    const messageText = document.getElementById('message_text');
    const draftKey = 'qgc_whatsapp_center_draft';

    function showSpinner(show){ spinner.style.display = show ? 'block' : 'none'; }

    function setFilterState(){
        const val = targetSelect.value;
        const isStaff = val === 'staff';
        attendanceRow.style.display = (val==='absentees' || val==='late') ? 'block' : 'none';
        examRow.style.display = (val==='failed') ? 'block' : 'none';

        classSelect.disabled = isStaff;
        sectionSelect.disabled = isStaff || !classSelect.value;
        campusSelect.disabled = false;

        if (isStaff) {
            classSelect.value = '';
            sectionSelect.innerHTML = '<option value="">Not required for staff</option>';
        } else if (!classSelect.value) {
            sectionSelect.innerHTML = '<option value="">Select class first</option>';
        }
    }

    targetSelect.addEventListener('change', function(){
        setFilterState();
        tbody.innerHTML = '';
    });

    classSelect.addEventListener('change', function(){
        loadSectionsForClass(this.value);
        tbody.innerHTML = '';
    });

    campusSelect.addEventListener('change', function(){
        tbody.innerHTML = '';
    });

    sectionSelect.addEventListener('change', function(){
        tbody.innerHTML = '';
    });

    function loadSectionsForClass(className){
        if (targetSelect.value === 'staff') {
            setFilterState();
            return;
        }
        sectionSelect.disabled = true;
        sectionSelect.innerHTML = '<option value="">Loading...</option>';
        if (!className) {
            sectionSelect.innerHTML = '<option value="">Select class first</option>';
            return;
        }
        fetch('../../ajax/shared-ajax.php?action=get_sections&class_id=' + encodeURIComponent(className), {
            headers: {'Accept':'application/json'}
        }).then(r => r.json()).then(res => {
            sectionSelect.innerHTML = '<option value="">-- All --</option>';
            if (res.success && Array.isArray(res.data) && res.data.length) {
                res.data.forEach(sec => {
                    const opt = document.createElement('option');
                    opt.value = sec.id || sec.section_name;
                    opt.textContent = sec.section_name || sec.id;
                    sectionSelect.appendChild(opt);
                });
            } else {
                sectionSelect.innerHTML = '<option value="">No sections found</option>';
            }
            sectionSelect.disabled = false;
        }).catch(() => {
            sectionSelect.innerHTML = '<option value="">Unable to load sections</option>';
            sectionSelect.disabled = false;
        });
    }

    previewBtn.addEventListener('click', function(){
        loadRecipients(false);
    });

    function loadRecipients(selectAllAfter){
        const payload = {
            group: document.getElementById('target_group').value,
            class: document.getElementById('filter_class').value,
            section: document.getElementById('filter_section').value,
            campus: document.getElementById('filter_campus').value,
            attendance_date: document.getElementById('attendance_date').value,
            exam_id: document.getElementById('exam_id').value
        };
        showSpinner(true);
        fetch('./get-recipients.php', {
            method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(payload)
        }).then(r=>r.json()).then(res=>{
            showSpinner(false);
            if (!res.success) { alert(res.message || 'Error fetching recipients'); return; }
            tbody.innerHTML = '';
            if (!res.data.length) {
                tbody.innerHTML = '<tr><td colspan="5" class="empty-cell">No recipients found for the selected filters.</td></tr>';
                return;
            }
            res.data.forEach((row, idx)=>{
                const tr = document.createElement('tr');
                const name = (row.first_name? row.first_name : '') + (row.last_name? ' '+row.last_name:'');
                const phone = row.guardian_phone || row.phone || '';
                const info = (row.amount_due? ('Due: '+row.amount_due) : (row.attendance_date? ('Date: '+row.attendance_date) : (row.subject? ('Subject: '+row.subject+' Marks:'+row.obtained_marks+'/'+row.total_marks) : '')));
                tr.innerHTML = `<td><input type="checkbox" class="rcpt_cb" data-idx="${idx}"></td>
                    <td>${escapeHtml(name)}</td>
                    <td>${escapeHtml(phone)}</td>
                    <td>${escapeHtml(row.class || '')} ${escapeHtml(row.section || '')}</td>
                    <td>${escapeHtml(info)}</td>`;
                tr.dataset.payload = JSON.stringify(row);
                tbody.appendChild(tr);
            });
            if (selectAllAfter) selectAll();
        }).catch(err=>{ showSpinner(false); alert('Network error'); });
    }

    function escapeHtml(s){ if (!s) return ''; return s.toString().replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

    selectAllBtn.addEventListener('click', selectAll);
    deselectAllBtn.addEventListener('click', deselectAll);
    function selectAll(){ document.querySelectorAll('.rcpt_cb').forEach(cb=>cb.checked=true); }
    function deselectAll(){ document.querySelectorAll('.rcpt_cb').forEach(cb=>cb.checked=false); }

    document.getElementById('template_select').addEventListener('change', function(){
        const opt = this.selectedOptions[0];
        if (!opt) return; const tpl = opt.dataset.template || '';
        messageText.value = tpl;
    });

    document.getElementById('save_draft').addEventListener('click', function(){
        const draft = {
            group: targetSelect.value,
            class: classSelect.value,
            section: sectionSelect.value,
            campus: campusSelect.value,
            message: messageText.value,
            saved_at: new Date().toISOString()
        };
        localStorage.setItem(draftKey, JSON.stringify(draft));
        alert('Draft saved.');
    });

    document.getElementById('clear_draft').addEventListener('click', function(){
        localStorage.removeItem(draftKey);
        messageText.value = '';
        alert('Draft cleared.');
    });

    function gatherSelectedRecipients(all){
        const rows = Array.from(document.querySelectorAll('#recipients_table tbody tr'));
        const selected = [];
        rows.forEach((tr, i)=>{
            const cb = tr.querySelector('.rcpt_cb');
            if (!cb) return;
            if (all || cb.checked) {
                const payload = JSON.parse(tr.dataset.payload || '{}');
                selected.push(payload);
            }
        });
        return selected;
    }

    function replaceVars(message, row){
        const student_name = ((row.first_name||'') + ' ' + (row.last_name||'')).trim();
        const vars = {
            '{student_name}': student_name,
            '{guardian_name}': row.guardian_name || '',
            '{class}': row.class || '',
            '{section}': row.section || '',
            '{amount_due}': row.amount_due || '',
            '{date}': row.attendance_date || ''
        };
        let out = message;
        Object.keys(vars).forEach(k=>{ out = out.replaceAll(k, vars[k]); });
        return out;
    }

    function normalizePhone(phoneRaw){
        const DEFAULT_CC = '92'; // default country code (Pakistan). Change if needed.
        if (!phoneRaw) return '';
        let p = phoneRaw.toString().trim().replace(/[^0-9+]/g,'');
        if (!p) return '';
        if (p.startsWith('+')) p = p.slice(1);
        if (p.startsWith('0')) p = DEFAULT_CC + p.slice(1);
        if (p.length === 10) p = DEFAULT_CC + p; // assume local 10-digit number
        return p;
    }

    async function sendBatch(recipients){
        const messageTpl = messageText.value || '';
        if (!messageTpl) { alert('Please enter message'); return; }
        const delays = 800; // ms between tabs
        for (const r of recipients) {
            const phoneRaw = r.guardian_phone || r.phone || '';
            const phone = normalizePhone(phoneRaw);
            if (!phone) continue; // skip if no valid phone
            const msgText = replaceVars(messageTpl, r);
            const msg = encodeURIComponent(msgText);
            const url = `https://wa.me/${phone}?text=${msg}`;
            window.open(url, '_blank');
            // log via AJAX
            fetch('./send-log.php', {
                method:'POST', headers:{'Content-Type':'application/json'},
                body: JSON.stringify({ recipients: [{ name: (r.first_name||'') + ' ' + (r.last_name||''), guardian_phone: phone }], message: msgText, recipient_type: document.getElementById('target_group').value })
            }).catch(()=>{});
            await new Promise(res=>setTimeout(res, delays));
        }
        alert('Send initiated for ' + recipients.length + ' recipients.');
    }

    sendSelectedBtn.addEventListener('click', function(){
        const sel = gatherSelectedRecipients(false);
        if (!sel.length) { alert('No recipients selected'); return; }
        sendBatch(sel);
    });

    sendAllBtn.addEventListener('click', function(){
        const sel = gatherSelectedRecipients(true);
        if (!sel.length) { alert('No recipients to send'); return; }
        if (!confirm('Open WhatsApp links for all recipients? This will open multiple tabs.')) return;
        sendBatch(sel);
    });

    try {
        const draft = JSON.parse(localStorage.getItem(draftKey) || 'null');
        if (draft && draft.message && !messageText.value) {
            messageText.value = draft.message;
        }
    } catch (e) {}

    setFilterState();
});

document.addEventListener('DOMContentLoaded', function () {
    const printBtn = document.getElementById('sgPrintBtn');
    if (printBtn) {
        printBtn.addEventListener('click', function (e) {
            e.preventDefault();
            window.print();
        });
    }

    const admissionsJson = document.getElementById('sgAdmissionsData');
    const flowJson = document.getElementById('sgFlowData');
    if (!admissionsJson || !flowJson || typeof Chart === 'undefined') {
        return;
    }

    const admissionsData = JSON.parse(admissionsJson.textContent || '[]');
    const flowData = JSON.parse(flowJson.textContent || '[]');

    const months = admissionsData.map((item) => item.month);
    const admissions = admissionsData.map((item) => item.admissions);
    const withdrawals = admissionsData.map((item) => item.withdrawals);

    const flowMonths = flowData.map((item) => item.month);
    const feeCollected = flowData.map((item) => item.fee);
    const income = flowData.map((item) => item.income);
    const expense = flowData.map((item) => item.expense);

    const admissionsCtx = document.getElementById('admissionsWithdrawalsChart');
    if (admissionsCtx) {
        new Chart(admissionsCtx, {
            type: 'line',
            data: {
                labels: months,
                datasets: [
                    {
                        label: 'Admissions',
                        data: admissions,
                        borderColor: '#0d6efd',
                        backgroundColor: 'rgba(13, 110, 253, 0.12)',
                        tension: 0.35,
                        fill: true,
                        pointRadius: 4,
                        pointBackgroundColor: '#0d6efd',
                    },
                    {
                        label: 'Withdrawals',
                        data: withdrawals,
                        borderColor: '#dc3545',
                        backgroundColor: 'rgba(220, 53, 69, 0.12)',
                        tension: 0.35,
                        fill: true,
                        pointRadius: 4,
                        pointBackgroundColor: '#dc3545',
                    }
                ]
            },
            options: {
                plugins: {
                    legend: { display: true },
                    tooltip: { mode: 'index', intersect: false }
                },
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        grid: { display: false }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(0,0,0,0.05)' }
                    }
                }
            }
        });
    }

    const flowCtx = document.getElementById('flowChart');
    if (flowCtx) {
        new Chart(flowCtx, {
            type: 'line',
            data: {
                labels: flowMonths,
                datasets: [
                    {
                        label: 'Fees Collected',
                        data: feeCollected,
                        borderColor: '#198754',
                        backgroundColor: 'rgba(25, 135, 84, 0.12)',
                        tension: 0.35,
                        fill: true,
                        pointRadius: 3,
                    },
                    {
                        label: 'Income',
                        data: income,
                        borderColor: '#0d6efd',
                        backgroundColor: 'rgba(13, 110, 253, 0.12)',
                        tension: 0.35,
                        fill: true,
                        pointRadius: 3,
                    },
                    {
                        label: 'Expenses',
                        data: expense,
                        borderColor: '#dc3545',
                        backgroundColor: 'rgba(220, 53, 69, 0.12)',
                        tension: 0.35,
                        fill: true,
                        pointRadius: 3,
                    }
                ]
            },
            options: {
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: { mode: 'index', intersect: false }
                },
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        grid: { display: false }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(0,0,0,0.05)' }
                    }
                }
            }
        });
    }
});

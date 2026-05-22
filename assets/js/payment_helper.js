// payment_helper.js
// Handles showing/hiding Transaction ID field based on selected payment method

function setupPaymentMethod(selectId, fieldId) {
    const select = document.getElementById(selectId);
    const field  = document.getElementById(fieldId);

    if (!select || !field) return;

    const cashOnlyMethods = ['Cash'];

    function toggleTransactionField() {
        const isCash = cashOnlyMethods.includes(select.value);
        const input = field.querySelector('input[name="transaction_id"]');

        if (isCash) {
            field.style.display = 'none';
            if (input) {
                input.removeAttribute('required');
                input.value = 'N/A';
            }
        } else {
            field.style.display = 'block';
            if (input) {
                input.setAttribute('required', 'required');
                input.value = '';
                input.placeholder = 'Enter Transaction / Reference ID';
            }
        }
    }

    select.addEventListener('change', toggleTransactionField);
    toggleTransactionField(); // run immediately on page load
}

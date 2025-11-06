// dashboard.js

document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('confirm-modal');
    if (!modal) return;

    const titleEl = document.getElementById('modal-title');
    const messageEl = document.getElementById('modal-message');
    const confirmBtn = document.getElementById('modal-btn-confirm');
    const cancelBtn = document.getElementById('modal-btn-cancel');
    const formsToConfirm = document.querySelectorAll('.js-confirm-form');
    let formToSubmit = null;

    const showModal = (form) => {
        formToSubmit = form;
        const msg = form.getAttribute('data-message') || 'Are you sure?';
        const actionType = form.getAttribute('data-action-type') || 'cancel';

        // Set message
        if (messageEl) {
            messageEl.textContent = msg;
        }

        // Style confirm button based on action
        confirmBtn.classList.remove('is-danger', 'is-complete');
        if (actionType === 'cancel') {
            titleEl.textContent = 'Cancel Appointment';
            confirmBtn.textContent = 'Confirm';
            confirmBtn.classList.add('is-danger');
        } else if (actionType === 'complete') {
            titleEl.textContent = 'Complete Appointment';
            confirmBtn.textContent = 'Confirm';
            confirmBtn.classList.add('is-complete');
        } else {
            // Default fallback
            titleEl.textContent = 'Confirm Action';
            confirmBtn.textContent = 'Confirm';
            confirmBtn.classList.add('is-danger');
        }

        modal.setAttribute('aria-hidden', 'false');
        modal.classList.add('is-visible');
        confirmBtn.focus(); // Focus the confirm button
    };

    const hideModal = () => {
        modal.setAttribute('aria-hidden', 'true');
        modal.classList.remove('is-visible');
        formToSubmit = null;
    };

    formsToConfirm.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault(); // Stop the form submission
            showModal(form); // Show modal instead
        });
    });

    confirmBtn.addEventListener('click', () => {
        if (formToSubmit) {
            formToSubmit.submit(); // Manually submit the original form
        }
        hideModal();
    });

    cancelBtn.addEventListener('click', hideModal);

    // Close on backdrop click
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            hideModal();
        }
    });

    // Close on Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modal.classList.contains('is-visible')) {
            hideModal();
        }
    });
});
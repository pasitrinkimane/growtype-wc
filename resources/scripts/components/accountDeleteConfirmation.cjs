const DELETE_ACCOUNT_SELECTOR = '.btn-remove-account[data-delete-account-confirmation]';
const initializedDocuments = new WeakSet();

function shouldProceedWithAccountDeletion(message, confirmAction) {
    return confirmAction(message) === true;
}

function clearAccountBrowserStorage(doc) {
    const view = doc && doc.defaultView
        ? doc.defaultView
        : (typeof window !== 'undefined' ? window : null);

    for (const storageName of ['localStorage', 'sessionStorage']) {
        try {
            if (view && view[storageName] && typeof view[storageName].clear === 'function') {
                view[storageName].clear();
            }
        } catch (error) {
            // Storage can be unavailable in privacy mode; server-side cleanup still runs.
        }
    }
}

function submitConfirmedAccountDeletion(deleteButton, doc) {
    const action = deleteButton.getAttribute('href');
    if (!action || !doc.body || typeof doc.createElement !== 'function') {
        return false;
    }

    const form = doc.createElement('form');
    const confirmationInput = doc.createElement('input');

    form.method = 'post';
    form.action = action;
    form.style.display = 'none';
    confirmationInput.type = 'hidden';
    confirmationInput.name = 'delete_account_confirmed';
    confirmationInput.value = '1';
    form.appendChild(confirmationInput);
    doc.body.appendChild(form);
    clearAccountBrowserStorage(doc);
    form.submit();

    return true;
}

function accountDeleteConfirmation(doc = document, confirmAction = window.confirm.bind(window)) {
    if (!doc || initializedDocuments.has(doc)) {
        return;
    }

    doc.addEventListener('click', (event) => {
        const target = event.target;
        const deleteButton = target && typeof target.closest === 'function'
            ? target.closest(DELETE_ACCOUNT_SELECTOR)
            : null;

        if (!deleteButton) {
            return;
        }

        event.preventDefault();
        const message = deleteButton.getAttribute('data-delete-account-confirmation');
        if (!message || !shouldProceedWithAccountDeletion(message, confirmAction)) {
            return;
        }

        submitConfirmedAccountDeletion(deleteButton, doc);
    });

    initializedDocuments.add(doc);
}

module.exports = {
    DELETE_ACCOUNT_SELECTOR,
    accountDeleteConfirmation,
    clearAccountBrowserStorage,
    shouldProceedWithAccountDeletion,
    submitConfirmedAccountDeletion,
};

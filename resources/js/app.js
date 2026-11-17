document.addEventListener('DOMContentLoaded', () => {
    const root = document.documentElement;
    const themeToggle = document.getElementById('fm-theme-toggle');

    if (themeToggle) {
        themeToggle.addEventListener('click', () => {
            const nextTheme = root.dataset.theme === 'dark' ? 'light' : 'dark';
            root.dataset.theme = nextTheme;

            try {
                localStorage.setItem('fm-theme', nextTheme);
            } catch (error) {
                // Theme remains usable for the current page session.
            }
        });
    }

    const sidebar = document.getElementById('fm-sidebar');
    const overlay = document.getElementById('fm-sidebar-overlay');
    const openButton = document.getElementById('fm-menu-toggle');
    const closeButton = document.getElementById('fm-sidebar-close');

    const closeSidebar = () => {
        sidebar?.classList.remove('is-open');
        overlay?.classList.remove('is-visible');
        openButton?.setAttribute('aria-expanded', 'false');
    };

    const openSidebar = () => {
        sidebar?.classList.add('is-open');
        overlay?.classList.add('is-visible');
        openButton?.setAttribute('aria-expanded', 'true');
    };

    openButton?.addEventListener('click', () => {
        if (sidebar?.classList.contains('is-open')) {
            closeSidebar();
        } else {
            openSidebar();
        }
    });

    closeButton?.addEventListener('click', closeSidebar);
    overlay?.addEventListener('click', closeSidebar);

    const modal = document.querySelector('[data-fm-modal]');
    let modalTimer = null;
    let previousFocus = null;

    const modalTitle = modal?.querySelector('#fm-modal-title');
    const modalMessage = modal?.querySelector('#fm-modal-message');
    const modalActions = modal?.querySelector('.fm-modal-actions');
    const modalIcon = modal?.querySelector('[data-modal-icon]');

    const icons = {
        success: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 4 4L19 6"/></svg>',
        error: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="m15 9-6 6m0-6 6 6"/></svg>',
        validation: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4m0 4h.01"/><path d="M10.3 3.9 2.8 17a2 2 0 0 0 1.7 3h15a2 2 0 0 0 1.7-3l-7.5-13.1a2 2 0 0 0-3.4 0Z"/></svg>',
        warning: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4m0 4h.01"/><path d="M10.3 3.9 2.8 17a2 2 0 0 0 1.7 3h15a2 2 0 0 0 1.7-3l-7.5-13.1a2 2 0 0 0-3.4 0Z"/></svg>',
        info: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 11v5m0-8h.01"/></svg>'
    };

    const closeModal = () => {
        if (!modal) return;

        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('fm-modal-open');

        if (modalTimer) {
            window.clearTimeout(modalTimer);
            modalTimer = null;
        }

        if (previousFocus && typeof previousFocus.focus === 'function') {
            previousFocus.focus();
        }
    };

    const openModal = ({ type = 'info', title = 'Information', message = '', onConfirm = null } = {}) => {
        if (!modal) return;

        if (modalTimer) {
            window.clearTimeout(modalTimer);
            modalTimer = null;
        }

        previousFocus = document.activeElement;
        modal.dataset.modalType = type;

        if (modalTitle) modalTitle.textContent = title;

        if (modalMessage) {
            modalMessage.textContent = message;
        }

        if (modalActions) {
            modalActions.replaceChildren();

            if (type === 'confirmation') {
                const cancelButton = document.createElement('button');
                cancelButton.type = 'button';
                cancelButton.className = 'fm-btn fm-btn-secondary';
                cancelButton.textContent = 'Cancel';
                cancelButton.addEventListener('click', closeModal);

                const confirmButton = document.createElement('button');
                confirmButton.type = 'button';
                confirmButton.className = 'fm-btn fm-btn-primary';
                confirmButton.textContent = 'Confirm';
                confirmButton.dataset.fmModalConfirm = 'true';
                confirmButton.addEventListener('click', () => {
                    closeModal();
                    if (typeof onConfirm === 'function') onConfirm();
                });

                modalActions.append(cancelButton, confirmButton);
            } else {
                const closeButton = document.createElement('button');
                closeButton.type = 'button';
                closeButton.className = 'fm-btn fm-btn-primary';
                closeButton.textContent = 'Close';
                closeButton.addEventListener('click', closeModal);
                modalActions.append(closeButton);
            }
        }

        if (modalIcon) {
            modalIcon.innerHTML = icons[type] || icons.info;
        }

        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('fm-modal-open');

        const confirmButton = modal.querySelector('[data-fm-modal-confirm]');
        if (confirmButton) {
            confirmButton.focus();
        } else {
            modal.querySelector('[data-fm-modal-close]')?.focus();
        }

        if (type === 'success') {
            modalTimer = window.setTimeout(closeModal, 3000);
        }
    };

    modal?.querySelectorAll('[data-fm-modal-close]').forEach((button) => {
        button.addEventListener('click', closeModal);
    });

    modal?.addEventListener('click', (event) => {
        if (event.target === modal) closeModal();
    });

    document.querySelectorAll('[data-fm-modal-trigger]').forEach((trigger) => {
        trigger.addEventListener('click', () => {
            openModal({
                type: trigger.dataset.modalType || 'info',
                title: trigger.dataset.modalTitle || 'Information',
                message: trigger.dataset.modalMessage || ''
            });
        });
    });

    document.addEventListener('submit', (event) => {
        const form = event.target;

        if (!(form instanceof HTMLFormElement) || !form.matches('[data-fm-confirm]')) {
            return;
        }

        event.preventDefault();

        openModal({
            type: 'confirmation',
            title: form.dataset.confirmTitle || 'Confirm action',
            message: form.dataset.confirmMessage || 'Are you sure you want to continue?',
            onConfirm: () => form.submit()
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeSidebar();

            if (modal?.classList.contains('is-open')) {
                closeModal();
            }
        }
    });

    window.addEventListener('resize', () => {
        if (window.innerWidth > 900) {
            closeSidebar();
        }
    });

    if (modal?.dataset.autoOpen === 'true') {
        const type = modal.dataset.modalType || 'info';

        if (modalIcon) {
            modalIcon.innerHTML = icons[type] || icons.info;
        }

        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('fm-modal-open');

        const confirmButton = modal.querySelector('[data-fm-modal-confirm]');
        if (confirmButton) {
            confirmButton.focus();
        } else {
            modal.querySelector('[data-fm-modal-close]')?.focus();
        }

        if (type === 'success') {
            modalTimer = window.setTimeout(closeModal, 3000);
        }
    }
});
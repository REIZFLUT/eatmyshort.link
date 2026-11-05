// The stylesheet is linked in base.html.twig: a CSS import would become a data: URL in the
// import map, which the Content-Security-Policy (rightly) blocks.
import { initPasskeys } from './js/passkey.js';

// Copy buttons: <button data-copy="text"> or data-copy-target="#input">
document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-copy], [data-copy-target]');
    if (!button) return;
    const target = button.dataset.copyTarget ? document.querySelector(button.dataset.copyTarget) : null;
    const text = target ? target.value ?? target.textContent : button.dataset.copy;
    try {
        await navigator.clipboard.writeText(text);
        const label = button.textContent;
        button.textContent = button.dataset.copiedLabel || '✓';
        setTimeout(() => { button.textContent = label; }, 1500);
    } catch {
        target?.select?.();
    }
});

// Confirm before destructive submits: <form data-confirm="Really?">
document.addEventListener('submit', (event) => {
    const message = event.target.dataset?.confirm;
    if (message && !window.confirm(message)) {
        event.preventDefault();
    }
});

// Collection rows (localised targets): add from data-prototype, remove per row.
document.addEventListener('click', (event) => {
    const add = event.target.closest('[data-collection-add]');
    if (add) {
        const holder = document.querySelector(add.dataset.collectionAdd);
        const index = Number(holder.dataset.index || holder.children.length);
        const wrapper = document.createElement('div');
        wrapper.innerHTML = holder.dataset.prototype.replace(/__name__/g, String(index)).trim();
        holder.append(...wrapper.childNodes);
        holder.dataset.index = String(index + 1);
        holder.lastElementChild?.querySelector('input')?.focus();
        return;
    }
    const remove = event.target.closest('[data-collection-remove]');
    if (remove) {
        remove.closest('.collection-row')?.remove();
    }
});

// Auto-submit selects in filter bars: <select data-autosubmit>
document.addEventListener('change', (event) => {
    if (event.target.matches('[data-autosubmit]')) {
        event.target.form?.requestSubmit();
    }
});

initPasskeys();

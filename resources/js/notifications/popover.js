/**
 * 通知ポップオーバー (S-A-05).
 *
 * TopBar のベル([data-notification-popover-trigger])クリックで /api/v1/notifications を
 * fetch し、ポップオーバーに動的表示する。全件/未読タブ・全件既読・行クリック既読+遷移・
 * 未読バッジの動的増減に対応する。Sanctum Cookie 認証 + CSRF。
 */

const API_BASE = '/api/v1/notifications';

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

function apiHeaders() {
    return {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken(),
        'X-Requested-With': 'XMLHttpRequest',
    };
}

let csrfReady = false;
async function ensureCsrfCookie() {
    if (csrfReady) return;
    await fetch('/sanctum/csrf-cookie', { credentials: 'same-origin' });
    csrfReady = true;
}

async function fetchNotifications() {
    const res = await fetch(API_BASE, {
        headers: apiHeaders(),
        credentials: 'same-origin',
    });
    if (!res.ok) return null;
    return res.json();
}

async function markRead(id) {
    await ensureCsrfCookie();
    const res = await fetch(`${API_BASE}/${id}/read`, {
        method: 'POST',
        headers: apiHeaders(),
        credentials: 'same-origin',
    });
    return res.ok ? res.json() : null;
}

async function markAllRead() {
    await ensureCsrfCookie();
    const res = await fetch(`${API_BASE}/read-all`, {
        method: 'POST',
        headers: apiHeaders(),
        credentials: 'same-origin',
    });
    return res.ok ? res.json() : null;
}

function relativeTime(iso) {
    if (!iso) return '';
    const diffMs = Date.now() - new Date(iso).getTime();
    const min = Math.floor(diffMs / 60000);
    if (min < 1) return 'たった今';
    if (min < 60) return `${min}分前`;
    const hr = Math.floor(min / 60);
    if (hr < 24) return `${hr}時間前`;
    return `${Math.floor(hr / 24)}日前`;
}

export function initNotificationPopover() {
    const root = document.querySelector('[data-notification-popover-root]');
    if (!root) return;

    const trigger = root.querySelector('[data-notification-popover-trigger]');
    const panel = root.querySelector('[data-notification-popover-panel]');
    const badge = root.querySelector('[data-notification-popover-badge]');
    const list = root.querySelector('[data-notification-popover-list]');
    const items = root.querySelector('[data-notification-popover-items]');
    const empty = root.querySelector('[data-notification-popover-empty]');
    const loading = root.querySelector('[data-notification-popover-loading]');
    const markAllBtn = root.querySelector('[data-notification-popover-mark-all]');
    const tabs = root.querySelectorAll('[data-notification-popover-tab]');
    const unreadCountEl = root.querySelector('[data-notification-popover-unread-count]');
    const rowTemplate = root.querySelector('[data-notification-popover-row-template]');

    if (!trigger || !panel || !rowTemplate) return;

    let currentTab = 'all';
    let cache = [];

    function setBadge(count) {
        if (!badge) return;
        badge.textContent = count > 99 ? '99+' : String(count);
        badge.classList.toggle('hidden', count <= 0);
        if (unreadCountEl) unreadCountEl.textContent = String(count);
    }

    function render() {
        const rows = currentTab === 'unread' ? cache.filter((n) => !n.read) : cache;
        items.innerHTML = '';

        if (rows.length === 0) {
            empty?.classList.remove('hidden');
            list?.classList.add('hidden');
            return;
        }
        empty?.classList.add('hidden');
        list?.classList.remove('hidden');

        rows.forEach((n) => {
            const node = rowTemplate.content.firstElementChild.cloneNode(true);
            node.dataset.unread = n.read ? '0' : '1';
            const dot = node.querySelector('[data-notification-popover-row-dot]');
            if (dot) dot.classList.toggle('hidden', n.read);
            const title = node.querySelector('[data-notification-popover-row-title]');
            if (title) title.textContent = n.title ?? '通知';
            const message = node.querySelector('[data-notification-popover-row-message]');
            if (message) message.textContent = n.message ?? '';
            const time = node.querySelector('[data-notification-popover-row-time]');
            if (time) time.textContent = relativeTime(n.created_at);

            node.addEventListener('click', async (e) => {
                e.preventDefault();
                const result = await markRead(n.id);
                if (result) setBadge(result.unread_count);
                if (n.action_url) {
                    window.location.href = n.action_url;
                }
            });

            items.appendChild(node);
        });
    }

    async function reload() {
        loading?.classList.remove('hidden');
        const data = await fetchNotifications();
        loading?.classList.add('hidden');
        if (!data) return;
        cache = data.notifications ?? [];
        setBadge(data.unread_count ?? 0);
        render();
    }

    function togglePanel(open) {
        const willOpen = open ?? panel.classList.contains('hidden');
        panel.classList.toggle('hidden', !willOpen);
        trigger.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
        if (willOpen) reload();
    }

    trigger.addEventListener('click', async (e) => {
        e.preventDefault();
        await ensureCsrfCookie();
        togglePanel();
    });

    // パネル外クリックで閉じる
    document.addEventListener('click', (e) => {
        if (!root.contains(e.target)) togglePanel(false);
    });

    tabs.forEach((tab) => {
        tab.addEventListener('click', (e) => {
            e.preventDefault();
            currentTab = tab.dataset.notificationPopoverTab === 'unread' ? 'unread' : 'all';
            tabs.forEach((t) => t.setAttribute('aria-selected', t === tab ? 'true' : 'false'));
            render();
        });
    });

    markAllBtn?.addEventListener('click', async (e) => {
        e.preventDefault();
        const result = await markAllRead();
        if (result) {
            cache = cache.map((n) => ({ ...n, read: true }));
            setBadge(result.unread_count ?? 0);
            render();
        }
    });
}

document.addEventListener('DOMContentLoaded', initNotificationPopover);

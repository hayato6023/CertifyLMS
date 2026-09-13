/**
 * 通知ポップオーバー (S-A-05 着手中).
 *
 * TopBar のベルをクリックしたら /api/v1/notifications を fetch して
 * ポップオーバーに動的表示する。全件/未読タブ・全件既読・行クリック既読+遷移を扱う予定。
 *
 * NOTE: 実装途中。API 取得の骨組みまで。以下 TODO。
 *  - [ ] ベルクリックの開閉トグル (data-notification-bell / data-notification-popover)
 *  - [ ] タブ切替 (全件 / 未読) と未読バッジの動的増減
 *  - [ ] 行クリックで read API → action_url へ遷移
 *  - [ ] 全件既読ボタン
 *  - [ ] 空状態表示
 */

const API_BASE = '/api/v1/notifications';

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

async function ensureCsrfCookie() {
    // Sanctum Cookie 認証: 先に csrf-cookie を取得する
    await fetch('/sanctum/csrf-cookie', { credentials: 'same-origin' });
}

async function fetchNotifications() {
    const res = await fetch(API_BASE, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
    });
    if (!res.ok) return null;
    return res.json();
}

// TODO: markAsRead / markAllAsRead / render / タブ / バッジ更新

export function initNotificationPopover() {
    const bell = document.querySelector('[data-notification-bell]');
    if (!bell) return;

    // TODO: クリックで ensureCsrfCookie() → fetchNotifications() → render()
    // 現状は API 取得の疎通確認のみ（描画は未実装）。
    void ensureCsrfCookie;
    void fetchNotifications;
    void csrfToken;
}

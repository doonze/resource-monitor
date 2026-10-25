/**
 * alertsBanner()  —  Alpine component for the dismissible alerts banner.
 *
 * Alerts are read from alert_state via /api/alerts_api.php — they persist
 * whether or not the dashboard was open when the alert fired.
 *
 * Dismissals are stored in the database (alert_dismissals table) keyed by
 * alert_id + first_fired_at.  If an alert resolves and re-fires its
 * first_fired_at resets, so the new firing always appears on the banner.
 *
 * No sessionStorage or localStorage — state lives in the DB.
 */
function alertsBanner() {
    return {
        alerts:     [],     // active alerts from server (already filtered + de-dismissed)
        dismissing: {},     // { alert_id: true } optimistic hide while POST is in-flight
        collapsed:  false,
        loading:    true,
        _timer:     null,

        // ── init ─────────────────────────────────────────────────────────────
        init() {
            this.fetchAlerts();
            this._timer = setInterval(() => { if (!document.hidden) this.fetchAlerts(); }, 60_000);
            // Fetch immediately when the user returns to this tab.
            document.addEventListener('visibilitychange', () => {
                if (!document.hidden) this.fetchAlerts();
            });
        },

        // ── data ─────────────────────────────────────────────────────────────
        async fetchAlerts() {
            try {
                const r    = await fetch('../api/alerts_api.php');
                const data = await r.json();
                this.alerts  = data.alerts ?? [];
                // Clear any optimistic dismissals that the server has now confirmed
                for (const id in this.dismissing) {
                    if (!this.alerts.find(a => a.alert_id === id)) {
                        delete this.dismissing[id];
                    }
                }
            } catch (e) {
                // Network hiccup — keep showing whatever we had
            } finally {
                this.loading = false;
            }
        },

        // ── computed ─────────────────────────────────────────────────────────
        get visibleAlerts() {
            // Exclude any optimistically-dismissed alerts (POST in-flight)
            return this.alerts.filter(a => !this.dismissing[a.alert_id]);
        },

        get hasCritical() {
            return this.visibleAlerts.some(a => a.severity === 'critical');
        },

        get hasAlerts() {
            return this.visibleAlerts.length > 0;
        },

        // ── actions ──────────────────────────────────────────────────────────
        async dismiss(alert) {
            // Optimistically hide immediately
            this.dismissing = { ...this.dismissing, [alert.alert_id]: true };

            try {
                await fetch('../api/alerts_api.php', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body:    JSON.stringify({
                        action:        'dismiss',
                        alert_id:      alert.alert_id,
                        first_fired_at: alert.first_fired_at,
                    }),
                });
            } catch (e) {
                // If the POST fails the alert will reappear on next poll — acceptable
            }
        },

        async dismissAll() {
            const toGo = this.visibleAlerts;
            if (!toGo.length) return;

            // Optimistically hide all of them
            const patch = {};
            toGo.forEach(a => { patch[a.alert_id] = true; });
            this.dismissing = { ...this.dismissing, ...patch };

            try {
                await fetch('../api/alerts_api.php', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body:    JSON.stringify({
                        action: 'dismiss_all',
                        alerts: toGo.map(a => ({
                            alert_id:       a.alert_id,
                            first_fired_at: a.first_fired_at,
                        })),
                    }),
                });
            } catch (e) {}
        },

        // ── styling ──────────────────────────────────────────────────────────
        bannerBg() {
            if (this.hasCritical)
                return 'bg-red-50 dark:bg-red-950/60 border-red-400';
            return 'bg-amber-50 dark:bg-amber-950/60 border-amber-400';
        },

        rowBg(alert) {
            if (alert.severity === 'critical')
                return 'bg-red-100 dark:bg-red-900/40 border-red-300 dark:border-red-700';
            return 'bg-amber-100 dark:bg-amber-900/40 border-amber-300 dark:border-amber-700';
        },

        titleColor(alert) {
            return alert.severity === 'critical'
                ? 'text-red-800 dark:text-red-200'
                : 'text-amber-800 dark:text-amber-200';
        },

        msgColor(alert) {
            return alert.severity === 'critical'
                ? 'text-red-700 dark:text-red-300'
                : 'text-amber-700 dark:text-amber-300';
        },

        dismissBtnClass(alert) {
            return alert.severity === 'critical'
                ? 'text-red-400 hover:text-red-600 dark:text-red-500 dark:hover:text-red-300'
                : 'text-amber-400 hover:text-amber-600 dark:text-amber-500 dark:hover:text-amber-300';
        },

        // ── time helper ──────────────────────────────────────────────────────
        firingDuration(alert) {
            const fired = new Date(alert.first_fired_at.replace(' ', 'T'));
            const mins  = Math.round((Date.now() - fired.getTime()) / 60_000);
            if (mins < 60)  return `${mins}m`;
            return `${Math.floor(mins / 60)}h ${mins % 60}m`;
        },
    };
}

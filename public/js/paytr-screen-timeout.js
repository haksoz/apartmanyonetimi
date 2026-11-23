(() => {
    const dialog = document.getElementById('paytr-timeout');
    const form = document.getElementById('payment-exit-form');
    if (!dialog || !form) return;
    const duration = Number(dialog.dataset.durationSeconds) * 1000;
    const responseDuration = Number(dialog.dataset.responseSeconds) * 1000;
    const providerDeadline = Date.parse(dialog.dataset.providerDeadline);
    const key = `paytr-screen-deadline-${dialog.dataset.attemptId}`;
    let deadline = Date.now() + duration;
    // Refreshing this tab must not silently grant another three minutes.
    try {
        const saved = Number(sessionStorage.getItem(key));
        if (saved > 0 && Number.isFinite(saved)) deadline = saved;
    } catch (_) {}
    let stopped = false;
    let busy = false;
    let timer;
    let previousFocus;
    const error = dialog.querySelector('[data-card-timeout-error]');
    const buttons = dialog.querySelectorAll('button');
    const persist = () => { try { sessionStorage.setItem(key, String(deadline)); } catch (_) {} };
    const stop = () => {
        stopped = true;
        clearTimeout(timer);
        if (dialog.open) dialog.close();
        try { sessionStorage.removeItem(key); } catch (_) {}
    };
    window.addEventListener('payment-screen-exited', stop);
    window.addEventListener('pagehide', () => { stopped = true; clearTimeout(timer); });
    const result = url => {
        form.dataset.exitSent = '1';
        window.dispatchEvent(new Event('payment-screen-exited'));
        window.location.replace(url || dialog.dataset.resultUrl);
    };
    const track = async type => {
        const data = new FormData(form);
        data.set('type', type);
        const response = await fetch(dialog.dataset.eventUrl, {method: 'POST', body: data,
            headers: {'Accept': 'application/json'}, credentials: 'same-origin', signal: AbortSignal.timeout(10000)});
        if (stopped) return false;
        if (response.status === 409) { result((await response.json()).redirect); return false; }
        if (!response.ok) throw new Error('timer_failed');
        return true;
    };
    const setBusy = value => { busy = value; buttons.forEach(button => { button.disabled = value; }); };
    const close = async automatic => {
        if (busy || stopped) return;
        setBusy(true);
        clearTimeout(timer);
        error.hidden = true;
        // The shared exit action records abandonment; it never fabricates a PayTR failure.
        const closed = await window.payCardExit(automatic ? 'timeout' : 'timeout_dismissed');
        if (!closed && !stopped) {
            error.textContent = 'Çıkış kaydedilemedi. Bağlantınızı kontrol edip tekrar deneyin.';
            error.hidden = false;
            setBusy(false);
        }
    };
    const schedule = () => {
        clearTimeout(timer);
        if (stopped || busy) return;
        const now = Date.now();
        if (now >= providerDeadline) { result(); return; }
        if (now >= deadline && !dialog.open) {
            previousFocus = document.activeElement;
            dialog.showModal();
            track('card_timeout_prompt').catch(() => {});
        }
        if (dialog.open) {
            const remaining = Math.max(0, deadline + responseDuration - now);
            dialog.querySelector('[data-card-countdown]').textContent = Math.ceil(remaining / 1000);
            if (remaining === 0) { close(true); return; }
        }
        timer = setTimeout(schedule, 1000);
    };
    dialog.addEventListener('cancel', event => event.preventDefault());
    dialog.querySelector('[data-card-timeout-close]').addEventListener('click', () => close(false));
    dialog.querySelector('[data-card-timeout-renew]').addEventListener('click', async () => {
        if (stopped || busy) return;
        if (Date.now() >= deadline + responseDuration) { close(true); return; }
        setBusy(true);
        clearTimeout(timer);
        error.hidden = true;
        try {
            if (!await track('card_timeout_renewed') || stopped) return;
            deadline = Date.now() + duration;
            persist();
            dialog.close();
            previousFocus?.focus();
        } catch (_) {
            if (stopped) return;
            error.textContent = 'Süre uzatılamadı. Bağlantınızı kontrol edip tekrar deneyin.';
            error.hidden = false;
        } finally {
            setBusy(false);
            schedule();
        }
    });
    document.addEventListener('visibilitychange', () => { if (!document.hidden) schedule(); });
    window.addEventListener('pageshow', schedule);
    persist();
    schedule();
})();

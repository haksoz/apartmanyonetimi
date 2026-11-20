(() => {
    const timers = new WeakMap();
    const visible = modal => !modal.hidden && !modal.classList.contains('hidden');
    const closeCheckout = (modal, automatic = false) => {
        const state = timers.get(modal);
        if (!state || state.stopped) return;
        stop(modal);
        payTrack(modal, automatic ? 'timeout_closed' : 'timeout_dismissed');
        window.location.assign(modal.dataset.checkoutCloseUrl);
    };
    const startResponseCountdown = (modal, state) => {
        clearTimeout(state.responseTimer);
        state.responseDeadline = Date.now() + Number(state.dialog.dataset?.responseSeconds || 20) * 1000;
        const tick = () => {
            if (state.stopped || state.renewing || !state.dialog.open) return;
            const remaining = Math.max(0, state.responseDeadline - Date.now());
            state.dialog.querySelector('[data-timeout-countdown]').textContent = Math.ceil(remaining / 1000);
            if (remaining === 0) { closeCheckout(modal, true); return; }
            state.responseTimer = setTimeout(tick, Math.min(1000, remaining));
        };
        tick();
    };
    const schedule = (modal, state) => {
        clearTimeout(state.timer);
        if (!state.stopped) state.timer = setTimeout(() => expire(modal), Math.max(0, state.deadline - Date.now()));
    };
    const expire = modal => {
        const state = timers.get(modal);
        if (!state || state.stopped || state.dialog.open || !visible(modal)) return;
        clearTimeout(state.timer);
        state.focus = document.activeElement;
        modal.querySelector('[data-pay-content]').inert = true;
        state.dialog.showModal();
        startResponseCountdown(modal, state);
        payTrack(modal, 'timed_out').then(() => payTrack(modal, 'timeout_prompt')).catch(() => {});
    };
    const ensure = modal => {
        const state = timers.get(modal);
        if (!state || state.stopped) return true;
        if (state.dialog.open) {
            if (!state.renewing && Date.now() >= state.responseDeadline) closeCheckout(modal, true);
            return false;
        }
        if (Date.now() >= state.deadline) { expire(modal); return false; }
        return true;
    };
    const touch = modal => {
        const state = timers.get(modal);
        if (!state || state.stopped || state.dialog.open) return;
        state.deadline = Date.now() + state.duration;
        schedule(modal, state);
    };
    const stop = modal => {
        const state = timers.get(modal);
        if (state) { state.stopped = true; clearTimeout(state.timer); clearTimeout(state.responseTimer); }
    };
    window.payCheckoutTimers = {expire, ensure, touch, stop};

    document.querySelectorAll('[data-pay-modal]').forEach(modal => {
        const dialog = modal.querySelector('[data-checkout-timeout]');
        if (!dialog) return;
        const state = {dialog, duration: Number(modal.dataset.timeoutSeconds) * 1000,
            deadline: Date.parse(modal.dataset.timeoutDeadline), stopped: false, lastActivity: 0};
        timers.set(modal, state);
        dialog.addEventListener('cancel', event => event.preventDefault());
        dialog.querySelector('[data-timeout-renew]').addEventListener('click', async event => {
            const button = event.currentTarget;
            const error = dialog.querySelector('[data-timeout-error]');
            button.disabled = true;
            state.renewing = true;
            clearTimeout(state.responseTimer);
            error.hidden = true;
            try {
                await payTrack(modal, 'renewed');
                if (state.stopped) return;
                dialog.close();
                modal.querySelector('[data-pay-content]').inert = false;
                touch(modal);
                state.focus?.focus();
            } catch (_) {
                if (state.stopped) return;
                error.textContent = 'Süre uzatılamadı. Bağlantınızı kontrol edip tekrar deneyin.';
                error.hidden = false;
                state.renewing = false;
                startResponseCountdown(modal, state);
            } finally { state.renewing = false; button.disabled = false; }
        });
        dialog.querySelector('[data-timeout-close]').addEventListener('click', () => {
            closeCheckout(modal);
        });
        ['input', 'change', 'pointerdown', 'keydown'].forEach(type => modal.addEventListener(type, event => {
            if (dialog.contains(event.target) || !visible(modal) || !ensure(modal)) return;
            if (Date.now() - state.lastActivity >= 15000) {
                state.lastActivity = Date.now();
                payTrack(modal, 'activity').catch(() => {});
            }
        }));
        document.addEventListener('visibilitychange', () => { if (!document.hidden) ensure(modal); });
        window.addEventListener('pageshow', () => ensure(modal));
        schedule(modal, state);
    });
})();

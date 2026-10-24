(function () {
    'use strict';
    var root = document.getElementById('seller-account-cards');
    if (!root) return;
    var config = JSON.parse(document.getElementById('seller-account-config').textContent);
    var messages = config.messages;
    var locale = {cn: 'zh-CN', hk: 'zh-HK', sa: 'ar-SA', bd: 'bn-BD', jp: 'ja-JP', kr: 'ko-KR', in: 'id-ID', be: 'be-BY'}[config.locale] || config.locale;
    var numberFormat;
    try { numberFormat = new Intl.NumberFormat(locale); }
    catch (error) { locale = 'en'; numberFormat = new Intl.NumberFormat('en'); }
    var claiming = false, historyPage = 1, historyLastPage = 1, historyBusy = false;
    function el(id) { return document.getElementById(id); }
    function text(id, value) { el(id).textContent = value; }
    function money(amount, currency) { return new Intl.NumberFormat(locale, {style: 'currency', currency: currency, currencyDisplay: 'narrowSymbol'}).format(Number(amount)); }
    function node(tag, className, value) {
        var item = document.createElement(tag);
        item.className = className || '';
        if (value !== undefined) item.textContent = value;
        return item;
    }
    async function request(url, method) {
        var controller = new AbortController();
        var timeout = setTimeout(function () { controller.abort(); }, 15000);
        try {
            var response = await fetch(url, {
                method: method || 'GET', credentials: 'same-origin', signal: controller.signal,
                headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': config.csrf}
            });
            if (response.status === 401 || response.status === 419 || response.redirected) throw new Error(messages.sessionError);
            if (!response.ok) throw new Error(method === 'POST' ? messages.claimError : messages.loadError);
            var data = await response.json();
            if (!data.success) throw new Error(messages.loadError);
            return data;
        } finally { clearTimeout(timeout); }
    }
    function showError(prefix, error) {
        var box = el(prefix + '-error');
        box.querySelector('span').textContent = error.message === messages.sessionError ? messages.sessionError : messages.loadError;
        box.hidden = false;
    }
    async function loadReputation() {
        el('reputation-loading').hidden = false;
        el('reputation-error').hidden = true;
        try {
            var data = (await request(config.reputation)).data;
            var score = Number(data.score), max = Number(data.max_score), healthy = score >= 90;
            text('reputation-score', numberFormat.format(score));
            text('reputation-max', '/ ' + numberFormat.format(max));
            var arc = el('reputation-arc');
            arc.style.strokeDashoffset = 339.3 * (1 - Math.min(1, Math.max(0, score / max)));
            arc.setAttribute('class', healthy ? 'text-success' : 'text-warning');
            var badge = el('reputation-health');
            badge.textContent = healthy ? messages.healthy : messages.attention;
            badge.className = 'badge badge-inline ' + (healthy ? 'badge-soft-success' : 'badge-soft-warning');
            el('reputation-support').hidden = healthy;
            el('reputation-content').hidden = false;
        } catch (error) { showError('reputation', error); }
        finally { el('reputation-loading').hidden = true; }
    }
    function renderRewards(data) {
        text('reward-completed', numberFormat.format(data.completed_orders));
        text('reward-total', money(data.total_claimed_amount, data.currency));
        var container = el('reward-milestones');
        container.textContent = '';
        data.milestones.forEach(function (tier) {
            var row = node('div', 'reward-tier');
            var heading = node('div', 'reward-tier-heading');
            heading.appendChild(node('span', 'fw-600', numberFormat.format(tier.target_orders) + ' ' + messages.orders));
            heading.appendChild(node('strong', 'text-primary fs-18', money(tier.amount, data.currency)));
            row.appendChild(heading);
            var progress = node('div', 'progress');
            var percent = Math.min(100, Math.max(0, Number(tier.progress_percent)));
            progress.setAttribute('role', 'progressbar');
            progress.setAttribute('aria-label', heading.textContent);
            progress.setAttribute('aria-valuemin', '0');
            progress.setAttribute('aria-valuemax', tier.progress_target);
            progress.setAttribute('aria-valuenow', tier.progress_current);
            var bar = node('div', 'progress-bar ' + (tier.status === 'claimed' ? 'bg-success' : 'bg-primary'));
            bar.style.width = percent + '%';
            progress.appendChild(bar);
            row.appendChild(progress);
            var footer = node('div', 'reward-tier-footer');
            footer.appendChild(node('span', 'small text-muted', numberFormat.format(tier.progress_current) + ' / ' + numberFormat.format(tier.progress_target) + ' · ' + messages.remaining + ': ' + numberFormat.format(tier.remaining_orders)));
            if (tier.status === 'claimed') {
                footer.appendChild(node('span', 'badge badge-inline badge-soft-success', messages.claimed));
            } else if (tier.can_claim) {
                var button = node('button', 'btn btn-primary btn-sm', messages.claim);
                button.type = 'button';
                button.disabled = claiming;
                button.addEventListener('click', function () { claim(tier.key, button); });
                footer.appendChild(button);
            } else {
                footer.appendChild(node('span', 'badge badge-inline badge-soft-secondary', messages.inProgress));
            }
            row.appendChild(footer);
            container.appendChild(row);
        });
        if (!data.milestones.length) container.appendChild(node('p', 'text-muted mb-0', messages.empty));
    }
    async function loadRewards() {
        el('rewards-loading').hidden = false;
        el('rewards-error').hidden = true;
        try {
            renderRewards((await request(config.progress)).data);
            el('rewards-content').hidden = false;
        } catch (error) { showError('rewards', error); }
        finally { el('rewards-loading').hidden = true; }
    }
    async function claim(key, button) {
        if (claiming) return;
        claiming = true;
        root.querySelectorAll('.reward-tier button').forEach(function (item) { item.disabled = true; });
        button.textContent = messages.loading;
        var feedback = el('reward-feedback');
        feedback.textContent = '';
        try {
            await request(config.claim.replace('__milestone__', encodeURIComponent(key)), 'POST');
            feedback.className = 'small mb-0 mt-3 text-success';
            feedback.textContent = messages.claimSuccess;
            await loadRewards();
        } catch (error) {
            feedback.className = 'small mb-0 mt-3 text-danger';
            feedback.textContent = error.message === messages.sessionError ? messages.sessionError : messages.claimError;
            // A timed-out request may already have credited the wallet; refresh before retrying.
            await loadRewards();
        } finally {
            claiming = false;
            root.querySelectorAll('.reward-tier button').forEach(function (item) { item.disabled = false; item.textContent = messages.claim; });
        }
    }
    async function loadHistory(page) {
        if (historyBusy) return;
        historyBusy = true;
        el('reputation-history-jump').classList.add('d-none');
        text('reputation-history-status', messages.loading);
        el('reputation-history-prev').disabled = true;
        el('reputation-history-next').disabled = true;
        el('reputation-history-list').textContent = '';
        try {
            var response = await request(config.history + '?per_page=5&page=' + page);
            historyPage = response.meta.current_page;
            historyLastPage = response.meta.last_page;
            el('reputation-history-jump').classList.toggle('d-none', historyLastPage <= 1);
            window.configurePageJump(el('reputation-history-jump').querySelector('[data-page-jump]'), historyPage, historyLastPage, loadHistory);
            response.data.forEach(function (log) {
                var item = node('li');
                var heading = node('div', 'history-score');
                var change = Number(log.change);
                heading.appendChild(node('strong', change >= 0 ? 'text-success' : 'text-danger', (change > 0 ? '+' : '') + numberFormat.format(change)));
                heading.appendChild(node('span', 'text-muted', numberFormat.format(log.old_score) + ' → ' + numberFormat.format(log.new_score)));
                item.appendChild(heading);
                item.appendChild(node('div', 'mt-1', log.reason || ''));
                var time = node('time', 'small text-muted', log.created_at || '');
                item.appendChild(time);
                el('reputation-history-list').appendChild(item);
            });
            text('reputation-history-status', response.data.length ? '' : messages.empty);
            text('reputation-history-page', numberFormat.format(historyPage) + ' / ' + numberFormat.format(historyLastPage));
        } catch (error) { text('reputation-history-status', error.message === messages.sessionError ? messages.sessionError : messages.loadError); }
        finally {
            historyBusy = false;
            el('reputation-history-prev').disabled = historyPage <= 1;
            el('reputation-history-next').disabled = historyPage >= historyLastPage;
        }
    }
    el('reputation-history-toggle').addEventListener('click', function () {
        var history = el('reputation-history');
        history.hidden = !history.hidden;
        this.setAttribute('aria-expanded', String(!history.hidden));
        if (!history.hidden) loadHistory(1);
    });
    el('reputation-history-prev').addEventListener('click', function () { loadHistory(historyPage - 1); });
    el('reputation-history-next').addEventListener('click', function () { loadHistory(historyPage + 1); });
    el('reputation-retry').addEventListener('click', loadReputation);
    el('rewards-retry').addEventListener('click', loadRewards);
    loadReputation();
    loadRewards();
}());

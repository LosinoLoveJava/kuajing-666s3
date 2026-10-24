<style>
    .seller-account-cards .card { height: 100%; }
    .seller-account-cards .card-header { gap: 12px; min-height: 66px; flex-wrap: wrap; }
    .seller-account-cards .card-header h2 { font-size: 15px; overflow-wrap: anywhere; }
    .seller-account-cards .account-icon { display: inline-flex; align-items: center; justify-content: center; width: 34px; height: 34px; border-radius: 10px; font-size: 23px; flex-shrink: 0; }
    .seller-account-cards .account-heading { display: flex; align-items: center; gap: 10px; min-width: 0; flex: 1 1 170px; }
    .seller-account-cards [hidden] { display: none !important; }
    .seller-account-cards .reputation-card-body { display: flex; flex-direction: column; }
    .seller-account-cards #reputation-content { flex: 1; display: flex; align-items: center; justify-content: center; }
    .seller-account-cards .reputation-summary { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 12px; padding: 16px 0; text-align: center; width: 100%; }
    .seller-account-cards .reputation-ring { position: relative; width: 176px; height: 176px; flex-shrink: 0; }
    .seller-account-cards .reputation-ring svg { width: 100%; height: 100%; transform: rotate(-90deg); }
    .seller-account-cards .reputation-ring circle { fill: none; stroke-width: 8px; }
    .seller-account-cards .reputation-value { position: absolute; inset: 0; display: flex; flex-direction: column; justify-content: center; align-items: center; }
    .seller-account-cards .reputation-value strong { font-size: 48px; line-height: 1.2; }
    .seller-account-cards .reputation-value span { font-size: 14px; }
    .seller-account-cards .reputation-copy { min-width: 0; font-size: 14px; }
    .seller-account-cards .reputation-copy .badge { font-size: 13px; }
    .seller-account-cards .account-error { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; }
    .seller-account-cards .reward-overview { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 12px; margin-bottom: 20px; }
    .seller-account-cards .reward-overview strong { font-size: 26px; line-height: 1.4; display: block; }
    .seller-account-cards .reward-tier + .reward-tier { border-top: 1px solid #ebedf2; margin-top: 16px; padding-top: 16px; }
    .seller-account-cards .reward-tier-heading { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 8px; margin-bottom: 10px; }
    .seller-account-cards .reward-tier .progress { height: 7px; border-radius: 8px; }
    .seller-account-cards .reward-tier-footer { display: flex; align-items: center; flex-wrap: wrap; justify-content: space-between; gap: 10px; margin-top: 10px; }
    .seller-account-cards .reputation-log { list-style: none; padding: 0; margin: 0; }
    .seller-account-cards .reputation-log li { border-top: 1px solid #ebedf2; padding: 12px 0; overflow-wrap: anywhere; }
    .seller-account-cards .history-score { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 8px; }
    @media (max-width: 575.98px) {
        .seller-account-cards .card-body { padding: 18px; }
        .seller-account-cards .reputation-ring { width: 144px; height: 144px; }
        .seller-account-cards .reputation-value strong { font-size: 42px; }
    }
</style>
<div class="row seller-account-cards" id="seller-account-cards">
    <div class="col-12 col-lg-6 mb-4">
        <section class="card mb-0" aria-labelledby="seller-reputation-title">
            <div class="card-header">
                <div class="account-heading">
                    <span class="account-icon bg-soft-primary text-primary" aria-hidden="true"><i class="las la-shield-alt"></i></span>
                    <h2 class="mb-0 fw-600" id="seller-reputation-title">{{ translate('Reputation score') }}</h2>
                </div>
                <button type="button" class="btn btn-link btn-sm p-0" id="reputation-history-toggle" aria-expanded="false" aria-controls="reputation-history">{{ translate('Adjustment history') }}</button>
            </div>
            <div class="card-body reputation-card-body">
                <p class="text-muted mb-0" id="reputation-loading" role="status">{{ translate('Loading...') }}</p>
                <div class="alert alert-light account-error" id="reputation-error" role="alert" hidden><span></span><button type="button" class="btn btn-outline-primary btn-sm" id="reputation-retry">{{ translate('Retry') }}</button></div>
                <div id="reputation-content" hidden>
                    <div class="reputation-summary">
                        <div class="reputation-ring" role="img" aria-labelledby="seller-reputation-title reputation-score reputation-max">
                            <svg viewBox="0 0 128 128" aria-hidden="true"><circle cx="64" cy="64" r="54" stroke="#edf0f5"/><circle id="reputation-arc" cx="64" cy="64" r="54" stroke="currentColor" stroke-linecap="round" stroke-dasharray="339.3" stroke-dashoffset="339.3"/></svg>
                            <div class="reputation-value"><strong id="reputation-score"></strong><span class="text-muted" id="reputation-max"></span></div>
                        </div>
                        <div class="reputation-copy">
                            <span class="badge badge-inline" id="reputation-health"></span>
                            <p class="text-muted mt-3 mb-2">{{ translate('Keep your reputation score at 90 or above.') }}</p>
                            <a href="{{ route('seller.support_ticket.index') }}" class="text-primary" id="reputation-support" hidden>{{ translate('Contact support') }} <i class="las la-angle-right" aria-hidden="true"></i></a>
                        </div>
                    </div>
                </div>
                <div id="reputation-history" hidden>
                    <p class="text-muted" id="reputation-history-status" role="status"></p>
                    <ul class="reputation-log" id="reputation-history-list"></ul>
                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="reputation-history-prev" disabled>{{ translate('Previous') }}</button>
                        <span class="text-muted" id="reputation-history-page"></span>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="reputation-history-next" disabled>{{ translate('Next') }}</button>
                    </div>
                    <div id="reputation-history-jump" class="d-none">@include('partials.page_jump')</div>
                </div>
            </div>
        </section>
    </div>
    <div class="col-12 col-lg-6 mb-4">
        <section class="card mb-0" aria-labelledby="seller-rewards-title">
            <div class="card-header">
                <div class="account-heading">
                    <span class="account-icon bg-soft-primary text-primary" aria-hidden="true"><i class="las la-gift"></i></span>
                    <h2 class="mb-0 fw-600" id="seller-rewards-title">{{ translate('Rewards progress') }}</h2>
                </div>
                <span class="badge badge-inline badge-soft-primary">{{ translate('Lifetime') }}</span>
            </div>
            <div class="card-body">
                <p class="text-muted mb-0" id="rewards-loading" role="status">{{ translate('Loading...') }}</p>
                <div class="alert alert-light account-error" id="rewards-error" role="alert" hidden><span></span><button type="button" class="btn btn-outline-primary btn-sm" id="rewards-retry">{{ translate('Retry') }}</button></div>
                <div id="rewards-content" hidden>
                    <div class="reward-overview">
                        <div><span class="text-muted">{{ translate('Completed orders') }}</span><strong id="reward-completed" class="text-primary"></strong></div>
                        <div><span class="text-muted">{{ translate('Total claimed') }}</span><strong id="reward-total" class="fs-20"></strong></div>
                    </div>
                    <div id="reward-milestones"></div>
                </div>
                <p class="small mb-0 mt-3" id="reward-feedback" role="status" aria-live="polite"></p>
            </div>
        </section>
    </div>
</div>
@php
$sellerAccountConfig = [
    'reputation' => route('seller.dashboard.reputation', [], false),
    'history' => route('seller.dashboard.reputation.history', [], false),
    'progress' => route('seller.dashboard.rewards.progress', [], false),
    'claim' => route('seller.dashboard.rewards.claim', ['milestone' => '__milestone__'], false),
    'csrf' => csrf_token(),
    'locale' => str_replace('_', '-', app()->getLocale()),
    'messages' => [
        'healthy' => translate('Healthy'), 'attention' => translate('Needs attention'),
        'orders' => translate('Orders'), 'remaining' => translate('Remaining orders'),
        'claim' => translate('Claim reward'), 'claimed' => translate('Claimed'),
        'inProgress' => translate('In progress'), 'loading' => translate('Loading...'),
        'empty' => translate('No data found'), 'loadError' => translate('Unable to load data. Please try again.'),
        'claimError' => translate('Unable to claim reward. Please try again.'),
        'claimSuccess' => translate('Reward claimed successfully.'), 'sessionError' => translate('Please log in again.'),
    ],
];
@endphp
<script type="application/json" id="seller-account-config">@json($sellerAccountConfig)</script>
<noscript><p class="alert alert-info">{{ translate('Please enable JavaScript to view reputation and rewards.') }}</p></noscript>

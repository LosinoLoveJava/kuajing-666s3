<div class="page-jump d-flex flex-nowrap align-items-center {{ $pageJumpClass ?? '' }}" data-page-jump>
    <label class="mb-0">{{ __('pagination.page') }} <input type="number" min="1" step="1" class="form-control form-control-sm d-inline-block page-jump-input" inputmode="numeric" required></label>
    <span data-page-jump-total></span>
    <button type="button" class="{{ $pageJumpButtonClass ?? 'btn btn-soft-primary btn-sm' }}" data-page-jump-button>{{ __('pagination.go') }}</button>
</div>

<div class="modal fade" id="wallet-review-modal" tabindex="-1" role="dialog" aria-labelledby="wallet-review-title" aria-hidden="true">
    <div class="modal-dialog" role="document"><form class="modal-content" id="wallet-review-form">
        @csrf
        <input type="hidden" name="id"><input type="hidden" name="status">
        <div class="modal-header"><h5 class="modal-title" id="wallet-review-title"></h5><button type="button" class="close" data-dismiss="modal" aria-label="{{ translate('Close') }}"></button></div>
        <div class="modal-body">
            <p id="wallet-review-account"></p>
            <p class="text-muted">{{ translate('Approval credits the requested amount once. Rejection leaves the balance unchanged.') }}</p>
            <label for="wallet-review-remarks">{{ translate('Remarks') }}</label>
            <textarea id="wallet-review-remarks" name="remarks" class="form-control" maxlength="2000" rows="3"></textarea>
            <div class="alert alert-danger mt-3 d-none" id="wallet-review-error" role="alert"></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-dismiss="modal">{{ translate('Cancel') }}</button><button type="submit" class="btn btn-primary">{{ translate('Confirm') }}</button></div>
    </form></div>
</div>
<script>
$(function () {
    $(document).on('click', '.wallet-review-button', function () {
        var form = document.getElementById('wallet-review-form');
        form.reset();
        form.elements.id.value = this.dataset.id;
        form.elements.status.value = this.dataset.status;
        form.elements.remarks.required = this.dataset.status === '2';
        $('#wallet-review-title').text(this.dataset.status === '1' ? @json(translate('Approve recharge')) : @json(translate('Reject recharge')));
        $('#wallet-review-account').text(this.dataset.account);
        $('#wallet-review-error').addClass('d-none');
        $('#wallet-review-modal').modal('show');
    });
    $('#wallet-review-form').on('submit', function (event) {
        event.preventDefault();
        var form = $(this), button = form.find('button[type="submit"]');
        if (button.prop('disabled')) return;
        button.prop('disabled', true);
        $.post(@json(route('offline_recharge_request.approved')), form.serialize())
            .done(function () { window.location.reload(); })
            .fail(function (xhr) {
                $('#wallet-review-error').removeClass('d-none').text((xhr.responseJSON || {}).message || @json(translate('Unable to review this request. Refresh the page and try again.')));
                button.prop('disabled', false);
            });
    });
});
</script>

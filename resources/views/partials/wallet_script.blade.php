<script>
$(function () {
    @if($errors->any())
        @if(old('wallet_form') === 'withdraw')
            show_request_modal();
        @elseif(old('wallet_form') === 'recharge')
            show_make_wallet_recharge_modal(1);
        @endif
    @endif
    $(document).on('submit', '#request_modal form, #offline_wallet_recharge_modal form', function () {
        var form = this;
        if (form.dataset.submitting === '1') return false;
        form.dataset.submitting = '1';
        $(form).find('button[type="submit"]').prop('disabled', true);
    });
});
</script>

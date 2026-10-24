(function () {
    'use strict';

    // Frontend-only copy: [sentence with {terms} link, title, validation].
    var dictionary = {
        en: ['By checking the box below, the Seller confirms that: They have read and understood these {terms}.', 'Seller Registration Terms and Conditions', 'Please accept the Seller Registration Terms and Conditions.'],
        cn: ['勾选下方复选框，即表示卖家确认：已阅读并理解本{terms}。', '卖家注册条款与条件', '请同意卖家注册条款与条件。'],
        hk: ['勾選下方核取方塊，即表示賣家確認：已閱讀並理解本{terms}。', '賣家註冊條款與條件', '請同意賣家註冊條款與條件。'],
        sa: ['بتحديد المربع أدناه، يؤكد البائع أنه قرأ وفهم {terms}.', 'شروط وأحكام تسجيل البائع', 'يرجى الموافقة على شروط وأحكام تسجيل البائع.'],
        bd: ['নিচের ঘরে টিক দিয়ে বিক্রেতা নিশ্চিত করছেন যে তিনি এই {terms} পড়েছেন ও বুঝেছেন।', 'বিক্রেতা নিবন্ধনের শর্তাবলি', 'অনুগ্রহ করে বিক্রেতা নিবন্ধনের শর্তাবলিতে সম্মতি দিন।'],
        jp: ['下のチェックボックスを選択することで、出店者は本{terms}を読み、理解したことを確認します。', '出店者登録利用規約', '出店者登録利用規約に同意してください。'],
        kr: ['아래 확인란을 선택하면 판매자는 본 {terms}을 읽고 이해했음을 확인합니다.', '판매자 등록 이용약관', '판매자 등록 이용약관에 동의해 주세요.'],
        in: ['Dengan mencentang kotak di bawah, Penjual mengonfirmasi bahwa mereka telah membaca dan memahami {terms} ini.', 'Syarat dan Ketentuan Pendaftaran Penjual', 'Harap setujui Syarat dan Ketentuan Pendaftaran Penjual.'],
        vi: ['Bằng cách đánh dấu vào ô bên dưới, Người bán xác nhận đã đọc và hiểu {terms} này.', 'Điều khoản và Điều kiện Đăng ký Người bán', 'Vui lòng đồng ý với Điều khoản và Điều kiện Đăng ký Người bán.'],
        ms: ['Dengan menandakan kotak di bawah, Penjual mengesahkan bahawa mereka telah membaca dan memahami {terms} ini.', 'Terma dan Syarat Pendaftaran Penjual', 'Sila bersetuju dengan Terma dan Syarat Pendaftaran Penjual.'],
        be: ['Паставіўшы птушку ў полі ніжэй, прадавец пацвярджае, што прачытаў і зразумеў гэтыя {terms}.', 'Умовы рэгістрацыі прадаўца', 'Калі ласка, пагадзіцеся з умовамі рэгістрацыі прадаўца.'],
        th: ['เมื่อทำเครื่องหมายในช่องด้านล่าง ผู้ขายยืนยันว่าได้อ่านและเข้าใจ{terms}นี้แล้ว', 'ข้อกำหนดและเงื่อนไขการลงทะเบียนผู้ขาย', 'โปรดยอมรับข้อกำหนดและเงื่อนไขการลงทะเบียนผู้ขาย']
    };
    var locale = new URLSearchParams(window.location.search).get('lang') || document.documentElement.lang || 'en';
    locale = locale.toLowerCase().replace(/_/g, '-');
    var aliases = {zh: 'cn', 'zh-cn': 'cn', 'zh-hans': 'cn', 'zh-tw': 'hk', 'zh-hk': 'hk', 'zh-hant': 'hk', ar: 'sa', bn: 'bd', ja: 'jp', ko: 'kr', id: 'in'};
    locale = aliases[locale] || aliases[locale.split('-')[0]] || locale.split('-')[0];
    var copy = dictionary[locale] || dictionary.en;
    var container = document.querySelector('[data-seller-terms-copy]');
    var checkbox = document.getElementById('seller-terms');
    if (container) {
        var link = container.querySelector('a');
        link.querySelector('strong').textContent = copy[1];
        var target = new URL(link.href, window.location.href);
        target.searchParams.set('lang', locale);
        link.href = target.href;
        var parts = copy[0].split('{terms}');
        container.textContent = '';
        container.appendChild(document.createTextNode(parts[0]));
        container.appendChild(link);
        container.appendChild(document.createTextNode(parts[1]));
    }
    if (checkbox) {
        checkbox.setAttribute('aria-label', copy[0].replace('{terms}', copy[1]));
        function validate() { checkbox.setCustomValidity(checkbox.checked ? '' : copy[2]); }
        checkbox.addEventListener('change', validate);
        // Existing registration scripts may submit programmatically; also guard the submit event.
        checkbox.form.addEventListener('submit', function (event) {
            validate();
            if (!checkbox.checked) { event.preventDefault(); event.stopImmediatePropagation(); checkbox.reportValidity(); }
        }, true);
        validate();
    }
    var title = document.querySelector('[data-seller-terms-title]');
    if (title) {
        var isChinese = locale === 'cn' || locale === 'hk';
        var bodyLocale = isChinese ? 'cn' : 'en';
        title.textContent = dictionary[bodyLocale][1];
        document.title = title.textContent;
        document.documentElement.lang = isChinese ? 'zh-CN' : 'en';
        document.querySelectorAll('[data-seller-terms-body]').forEach(function (article) {
            article.hidden = article.getAttribute('data-seller-terms-body') !== bodyLocale;
        });
    }
})();

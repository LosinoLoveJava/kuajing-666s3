(function (window, $) {
    'use strict';
    if (!/^tr(?:-|$)/i.test(document.documentElement.lang)) return;
    var local = window.AIZ.local;
    var plugins = window.AIZ_TR_PLUGINS;
    local.no_files_found = 'Dosya bulunamadı';
    local.upload_failed = 'Dosya yüklenemedi. Lütfen tekrar deneyin veya yöneticiyle iletişime geçin.';
    local.summernote_language = 'tr-TR';
    local.minimum_limit_reached = 'Minimum sınıra ulaşıldı';
    local.maximum_limit_reached = 'Maksimum sınıra ulaşıldı';
    local.uppy = plugins.uppy;
    local.calendar = {
        applyLabel: 'Seç', cancelLabel: 'Temizle', customRangeLabel: 'Özel Aralık', weekLabel: 'Hf',
        daysOfWeek: ['Pz', 'Pt', 'Sa', 'Ça', 'Pe', 'Cu', 'Ct'],
        monthNames: ['Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'],
        firstDay: 1
    };
    local.date_ranges = {'Today':'Bugün', 'Yesterday':'Dün', 'Last 7 Days':'Son 7 Gün', 'Last 30 Days':'Son 30 Gün', 'This Month':'Bu Ay', 'Last Month':'Geçen Ay'};
    local.tagify = {empty:'Boş etiket', exceed:'Etiket sınırına ulaşıldı', pattern:'Geçersiz biçim', duplicate:'Bu etiket zaten eklendi', notAllowed:'Bu etikete izin verilmiyor'};
    if ($.summernote) $.summernote.lang['tr-TR'] = plugins.summernote;
    if ($.fn.selectpicker) $.extend($.fn.selectpicker.Constructor.DEFAULTS, {
        noneSelectedText:'Seçim yapılmadı', noneResultsText:'“{0}” için sonuç bulunamadı',
        countSelectedText:'{1} seçenekten {0} tanesi seçildi', maxOptionsText:['En fazla {n} seçenek seçebilirsiniz', 'Bu gruptan en fazla {n} seçenek seçebilirsiniz'],
        selectAllText:'Tümünü seç', deselectAllText:'Seçimi temizle', multipleSeparator:', '
    });
    $(document).on('init.trCopy reInit.trCopy afterChange.trCopy breakpoint.trCopy', '.aiz-carousel', function () {
        var carousel = this;
        setTimeout(function () {
            $(carousel).find('.slick-prev').attr('aria-label', 'Önceki');
            $(carousel).find('.slick-next').attr('aria-label', 'Sonraki');
            $(carousel).find('.slick-dots button[aria-label]').each(function () {
                var match = this.getAttribute('aria-label').match(/^(\d+) of (\d+)$/);
                if (match) this.setAttribute('aria-label', match[2]+' slayttan '+match[1]+'. slayt');
            });
        }, 0);
    });
    if (window.moment) window.moment.defineLocale('tr', {
        months:local.calendar.monthNames,
        monthsShort:['Oca','Şub','Mar','Nis','May','Haz','Tem','Ağu','Eyl','Eki','Kas','Ara'],
        weekdays:['Pazar','Pazartesi','Salı','Çarşamba','Perşembe','Cuma','Cumartesi'],
        weekdaysShort:['Paz','Pzt','Sal','Çar','Per','Cum','Cmt'], weekdaysMin:local.calendar.daysOfWeek,
        longDateFormat:{LT:'HH:mm', LTS:'HH:mm:ss', L:'DD.MM.YYYY', LL:'D MMMM YYYY', LLL:'D MMMM YYYY HH:mm', LLLL:'dddd, D MMMM YYYY HH:mm'},
        calendar:{sameDay:'[Bugün saat] LT', nextDay:'[Yarın saat] LT', nextWeek:'dddd [saat] LT', lastDay:'[Dün saat] LT', lastWeek:'[Geçen] dddd [saat] LT', sameElse:'L'},
        relativeTime:{future:'%s sonra',past:'%s önce',s:'birkaç saniye',ss:'%d saniye',m:'bir dakika',mm:'%d dakika',h:'bir saat',hh:'%d saat',d:'bir gün',dd:'%d gün',M:'bir ay',MM:'%d ay',y:'bir yıl',yy:'%d yıl'},
        week:{dow:1,doy:7}
    });

    // Native constraint messages otherwise follow the browser's language, not the selected site language.
    document.addEventListener('invalid', function (event) {
        var input = event.target, valid = input.validity;
        if (!valid || (valid.customError && !input.dataset.turkishValidity)) return;
        if (input.dataset.turkishValidity) input.setCustomValidity('');
        var message = valid.valueMissing ? (input.type === 'checkbox' ? 'Lütfen bu kutuyu işaretleyin.' : 'Lütfen bu alanı doldurun.')
            : valid.typeMismatch ? (input.type === 'email' ? 'Lütfen geçerli bir e-posta adresi girin.' : 'Lütfen geçerli bir adres girin.')
            : valid.tooShort ? 'Lütfen en az '+input.minLength+' karakter girin.'
            : valid.tooLong ? 'Lütfen en fazla '+input.maxLength+' karakter girin.'
            : valid.rangeUnderflow ? 'Değer en az '+input.min+' olmalıdır.'
            : valid.rangeOverflow ? 'Değer en fazla '+input.max+' olmalıdır.'
            : valid.stepMismatch ? 'Lütfen izin verilen aralıkta bir değer girin.'
            : valid.patternMismatch || valid.badInput ? 'Lütfen geçerli bir değer girin.' : '';
        if (message) { input.setCustomValidity(message); input.dataset.turkishValidity = '1'; }
    }, true);
    ['input','change'].forEach(function (type) {
        document.addEventListener(type, function (event) {
            var input = event.target;
            if (input.dataset && input.dataset.turkishValidity) {
                input.setCustomValidity(''); delete input.dataset.turkishValidity;
            }
        }, true);
    });
}(window, jQuery));

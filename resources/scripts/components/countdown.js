function countdown() {

    document.addEventListener('filterProductsByOrder', initCountdown)

    initCountdown();

    function initCountdown() {
        if (jQuery(".auction-time-countdown").length > 0 && $.SAcountdown !== 'undefined') {
            jQuery(".auction-time-countdown").each(function (index) {
                var time = jQuery(this).data('time');
                var format = jQuery(this).data('format');
                var compact = false;

                if (format == '') {
                    format = 'yowdHMS';
                }

                /**
                 * Check if defined data
                 */
                if (typeof data === 'undefined') {
                    var data = {
                        started: 'Started',
                        checking: 'Started',
                        compact_counter: jQuery(this).data('compact-counter') ?? 'yes',
                    }
                }

                if (data.compact_counter == 'yes') {
                    compact = true;
                } else {
                    compact = false;
                }

                var etext = '';
                if (jQuery(this).hasClass('future')) {
                    var etext = '<div class="started">' + data.started + '</div>';
                } else {
                    var etext = '<div class="over">' + data.checking + '</div>';
                }

                // if (!jQuery(' body').hasClass('logged-in')) {
                //     time = $.SAcountdown.UTCDate(-(new Date().getTimezoneOffset()), new Date(time * 1000));
                // }

                jQuery(this).SAcountdown({
                    until: time,
                    format: format,
                    compact: compact,
                    expiryText: etext
                });
            });
        }
    }
}

export {countdown};






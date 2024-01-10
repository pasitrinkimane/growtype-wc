function countdown() {

    window.growtype_wc.countdown = {
        started: 'Started',
        checking: 'Checking',
        over: 'Ended'
    }

    document.addEventListener('filterProductsByOrder', initCountdown)

    $(document).ready(function () {
        initCountdown();
    });

    function initCountdown() {
        if (jQuery(".auction-time-countdown").length > 0 && $.SAcountdown !== 'undefined') {
            jQuery(".auction-time-countdown").each(function (index) {
                let until = jQuery(this).attr('data-time');
                let format = jQuery(this).attr('data-format') ?? 'yowdHMS';
                let compact = jQuery(this).attr('data-compact') === 'true' ? true : false;
                let expiryText = '';

                if (jQuery(this).hasClass('future')) {
                    expiryText = '<span class="value started">' + window.growtype_wc.countdown.started + '</span>';
                } else {
                    expiryText = '<span class="value over">' + window.growtype_wc.countdown.over + '</span>';
                }

                if (cookieCustom.getCookie('growtype_wc_countdown_time') !== null) {
                    until = convertStringToSeconds(cookieCustom.getCookie('growtype_wc_countdown_time'));

                    if (until === 0) {
                        $('.auction-time-countdown').html(expiryText)
                        return;
                    }
                }

                jQuery(this).SAcountdown({
                    until: until,
                    format: format,
                    compact: compact,
                    expiryText: expiryText,
                    onTick: function (event) {
                        if (event[6] >= 0) {
                            cookieCustom.setCookie('growtype_wc_countdown_time', event);
                        }
                    },
                });
            });
        }
    }

    function convertStringToSeconds(timeString) {
        const timeArray = timeString.split(',').map(Number);

        const secondsInYear = 365 * 24 * 60 * 60;
        const secondsInMonth = 30 * 24 * 60 * 60;
        const secondsInWeek = 7 * 24 * 60 * 60;
        const secondsInDay = 24 * 60 * 60;
        const secondsInHour = 60 * 60;
        const secondsInMinute = 60;

        const totalSeconds =
            timeArray[0] * secondsInYear +
            timeArray[1] * secondsInMonth +
            timeArray[2] * secondsInWeek +
            timeArray[3] * secondsInDay +
            timeArray[4] * secondsInHour +
            timeArray[5] * secondsInMinute +
            timeArray[6];

        return totalSeconds;
    }
}

export {countdown};






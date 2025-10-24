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
            jQuery(".auction-time-countdown").each(function (index, element) {
                let id = jQuery(this).attr('id');
                let until = jQuery(this).attr('data-time');
                let format = jQuery(this).attr('data-format') ?? 'yowdHMS';
                let compact = jQuery(this).attr('data-compact') === 'true' ? true : false;
                let labels = jQuery(this).attr('data-labels');
                let description = jQuery(this).attr('data-description');
                let expiryText = '';
                let cookieName = id;

                if (jQuery(this).hasClass('future')) {
                    expiryText = '<span class="value started">' + window.growtype_wc.countdown.started + '</span>';
                } else {
                    expiryText = '<span class="value over">' + window.growtype_wc.countdown.over + '</span>';
                }

                if (cookieCustom.getCookie(cookieName) !== null) {
                    until = growtypeWcConvertStringToSeconds(cookieCustom.getCookie(cookieName));

                    if (until === 0) {
                        $(element).html(expiryText);
                        return;
                    }
                }

                let params = {
                    until: until,
                    format: format,
                    compact: compact,
                    expiryText: expiryText,
                    onTick: function (event) {
                        if (event[6] >= 0) {
                            cookieCustom.setCookie(cookieName, event);
                        }
                    },
                    onExpiry: function () {
                        $(this).trigger('countdownExpired');
                    }
                };

                if (typeof description !== 'undefined') {
                    if (description.length > 0) {
                        params.description = description;
                    }
                }

                if (typeof labels !== 'undefined') {
                    if (labels === '-') {
                        jQuery(this).addClass('labels-disabled');
                        params.labels = ['', '', '', '', '', '', ''];
                        params.labels1 = ['', '', '', '', '', '', ''];
                    } else if (labels.length > 0) {
                        params.labels = labels.split(',');
                        params.labels1 = labels.split(',');
                    }
                }

                jQuery(this).SAcountdown(params);
            });
        }
    }

    function growtypeWcConvertStringToSeconds(timeString) {

        if (timeString === null) {
            return 0;
        }

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

    window.growtypeWcConvertStringToSeconds = growtypeWcConvertStringToSeconds;
}

export {countdown};






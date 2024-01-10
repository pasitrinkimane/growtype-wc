function justpurchased() {
    if ($('.popup-justpurchased').length > 0 && cookieCustom.getCookie('growtype_wc_justpurchased') === null) {
        setTimeout(function () {
            $('.popup-justpurchased').slideDown();

            setTimeout(function () {
                $('.popup-justpurchased').slideUp();
                cookieCustom.setCookie('growtype_wc_justpurchased', true);
            }, 5000);
        }, 2000);
    }
}

export {justpurchased};






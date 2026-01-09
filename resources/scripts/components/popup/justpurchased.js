function justpurchased() {
    if ($('.popup-justpurchased').length > 0 && growtypeCookie.getCookie('growtype_wc_justpurchased') === null) {
        setTimeout(function () {
            $('.popup-justpurchased').slideDown();

            setTimeout(function () {
                $('.popup-justpurchased').slideUp();
                growtypeCookie.setCookie('growtype_wc_justpurchased', true);
            }, 7000);
        }, 4000);
    }
}

export {justpurchased};






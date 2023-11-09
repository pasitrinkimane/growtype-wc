function message() {
    $('.woocommerce-error .btn-close, .woocommerce-info .btn-close, .woocommerce-message .btn-close').click(function () {
        $(this).parent().fadeOut();
    })
}

export {message};

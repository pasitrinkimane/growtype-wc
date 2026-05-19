<?php

class Growtype_Wc_Payment_Form_Modal
{
    /**
     * Register hooks for the payment form modal.
     */
    public static function init()
    {
        add_action('wp_footer', [self::class, 'render_payment_form_modal']);
        add_action('wp_ajax_gwc_payment_form_modal', [self::class, 'ajax_render']);
        add_action('wp_ajax_nopriv_gwc_payment_form_modal', [self::class, 'ajax_render']);
    }

    /**
     * AJAX handler — returns the modal shell HTML for JS to inject when
     * the server-rendered version is absent (e.g. page with AJAX-injected button).
     */
    public static function ajax_render(): void
    {
        ob_start();
        self::render();
        $html = ob_get_clean();
        wp_send_json_success(['html' => $html]);
    }

    /**
     * Render the GWC Checkout Payment Form Modal in the footer dynamically.
     */
    public static function render_payment_form_modal()
    {
        $is_checkout_or_cart = false;
        if (function_exists('is_checkout') && is_checkout()) {
            $is_checkout_or_cart = true;
        }
        if (function_exists('is_cart') && is_cart()) {
            $is_checkout_or_cart = true;
        }

        if (!Growtype_Wc_Payment::$should_render_modal && !$is_checkout_or_cart) {
            return;
        }

        self::render();
    }

    /**
     * Render the GWC Checkout Payment Form Modal HTML.
     */
    public static function render()
    {
        ?>
        <div class="modal fade gwc-payment-form-modal" id="gwcPaymentFormModal" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-md" role="document">
                <div class="modal-content">
                    <div class="modal-header gwc-hf-modal-header">
                        <div class="gwc-hf-header-title-wrap">
                            <div class="gwc-hf-secure-badge">
                                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                                </svg>
                                Secure Checkout
                            </div>
                            <h5 class="modal-title">Pay with Card</h5>
                        </div>

                        <div class="gwc-hf-trust-badges">
                            <div class="gwc-hf-trust-item" style="display:flex; align-items:center;">
                                <svg width="32" height="20" viewBox="0 0 32 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <rect width="32" height="20" rx="3" fill="#1A1F71"></rect>
                                    <path d="M11.9733 13.0645L13.1256 6.33125H14.9702L13.8179 13.0645H11.9733ZM19.7428 6.47167C19.3496 6.29417 18.7303 6.11125 17.9547 6.11125C16.1408 6.11125 14.8569 7.02708 14.846 8.32958C14.8368 9.29417 15.7534 9.83333 16.4526 10.1583C17.1691 10.4908 17.4111 10.7021 17.408 11.0117C17.4034 11.4871 16.808 11.6967 16.2713 11.6967C15.4851 11.6967 15.0113 11.4954 14.6544 11.3417L14.3983 12.545C14.7506 12.6975 15.3998 12.8333 16.0751 12.8333C17.9908 12.8333 19.2555 11.9325 19.2662 10.5183C19.2743 9.35125 18.5036 8.8475 17.5147 8.3975C16.8837 8.10542 16.6644 7.92542 16.6669 7.64125C16.6669 7.32042 17.0494 6.96917 17.8863 6.96917C18.5724 6.96917 19.0189 7.11208 19.3879 7.2625L19.7428 6.47167ZM23.8208 6.33125C23.4184 6.33125 23.084 6.55167 22.9284 6.90375L20.1983 13.0645H22.1373L22.5229 12.0621H24.8931L25.12 13.0645H26.8334L25.3404 6.33125H23.8208ZM23.0768 10.6121L23.708 8.98375L24.0723 10.6121H23.0768ZM10.5905 6.33125L8.78317 10.9329L8.59107 10.0246C8.25413 8.94833 7.23431 7.7475 6.13677 7.19917L7.84236 13.0633H9.79198L12.6896 6.33125H10.5905ZM6.42945 6.33125H3.14154L3.10955 6.48125C5.59737 7.08042 7.23888 8.54417 7.91502 10.2871L7.26593 7.21417C7.15941 6.64333 6.8458 6.36 6.42945 6.33125Z" fill="white"></path>
                                </svg>
                            </div>
                            <div class="gwc-hf-trust-item" style="display:flex; align-items:center;">
                                <svg width="32" height="20" viewBox="0 0 32 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <rect width="32" height="20" rx="3" fill="#222222"></rect>
                                    <path d="M12.4444 10C12.4444 11.5165 11.7588 12.8719 10.6875 13.7845C9.88086 14.4714 8.84157 14.8889 7.70833 14.8889C5.51239 14.8889 3.72517 13.2081 3.52479 11.0556H3.33333V8.94444H3.52479C3.72517 6.79194 5.51239 5.11111 7.70833 5.11111C8.84157 5.11111 9.88086 5.52864 10.6875 6.21553C11.7588 7.12814 12.4444 8.48353 12.4444 10Z" fill="#EB001B"></path>
                                    <path d="M22.0833 10C22.0833 8.48353 21.3977 7.12814 20.3264 6.21553C19.5197 5.52864 18.4804 5.11111 17.3472 5.11111C15.1513 5.11111 13.364 6.79194 13.1636 8.94444H12.9722V11.0556H13.1636C13.364 13.2081 15.1513 14.8889 17.3472 14.8889C18.4804 14.8889 19.5197 14.4714 20.3264 13.7845C21.3977 12.8719 22.0833 11.5165 22.0833 10Z" fill="#F79E1B"></path>
                                    <path d="M13.1636 10C13.1636 8.48353 13.8492 7.12814 14.9205 6.21553C15.6599 5.58614 16.6074 5.20764 17.6364 5.13283C16.5651 4.22022 15.1613 3.66667 13.625 3.66667C11.4291 3.66667 9.64183 5.3475 9.44145 7.5H9.25V12.5H9.44145C9.64183 14.6525 11.4291 16.3333 13.625 16.3333C15.1613 16.3333 16.5651 15.7798 17.6364 14.8672C16.6074 14.7924 15.6599 14.4139 14.9205 13.7845C13.8492 12.8719 13.1636 11.5165 13.1636 10Z" fill="#FF5F00"></path>
                                </svg>
                            </div>
                            <div class="gwc-hf-trust-item" style="display:flex; align-items:center;">
                                <svg width="32" height="20" viewBox="0 0 32 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <rect width="32" height="20" rx="3" fill="#0070BA"></rect>
                                    <path d="M22.5 7.5L20 12.5H23L25.5 7.5H22.5ZM17.5 7.5L15 12.5H18L20.5 7.5H17.5ZM12.5 7.5L10 12.5H13L15.5 7.5H12.5ZM7.5 7.5L5 12.5H8L10.5 7.5H7.5Z" fill="white"></path>
                                </svg>
                            </div>
                            <div class="gwc-hf-trust-divider"></div>
                            <div class="gwc-hf-trust-item pci-badge" style="display:flex; align-items:center;">
                                <svg width="40" height="12" viewBox="0 0 40 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M3.5 0H0V12H3.5V0ZM8.5 0H5V12H8.5V0ZM13.5 0H10V12H13.5V0Z" fill="#888"></path>
                                    <text x="15" y="10" fill="#888" font-family="sans-serif" font-size="8" font-weight="bold">PCI DSS</text>
                                </svg>
                            </div>
                        </div>

                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body modal-body-payment-form"></div>
                </div>
            </div>
        </div>
        <?php
    }
}

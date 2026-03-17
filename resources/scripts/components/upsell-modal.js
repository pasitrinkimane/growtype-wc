class GrowtypeWcUpsellModal {
    constructor(modalElement) {
        this.modalElement = modalElement;
        this.queue = this.parseQueue(modalElement.dataset.upsellIds || '[]');
        this.originalQueue = [...this.queue];
        this.ajaxAction = modalElement.dataset.ajaxAction || '';
        this.ajaxGetItemAction = modalElement.dataset.ajaxGetItemAction || '';
        this.ajaxNonce = modalElement.dataset.ajaxNonce || (window.growtype_wc_ajax ? window.growtype_wc_ajax.nonce : '');
        this.currentIndex = 0;
        this.isLoading = false;
        this.isAdvancing = false;
        this.programmaticHide = false;
        this.autoShow = modalElement.dataset.autoShow !== 'false';
        this.autoShowDelay = parseInt(modalElement.dataset.autoShowDelay, 10) || 0;
        this.bootstrapModal = this.resolveBootstrapModal();

        if (!this.queue.length) {
            this.modalElement.remove();
            return;
        }

        this.cacheElements();
        this.bindEvents();

        if (this.autoShow) {
            setTimeout(() => {
                this.loadCurrent();
                this.show();
            }, this.autoShowDelay);
        }
    }

    parseQueue(rawQueue) {
        try {
            const parsedQueue = JSON.parse(rawQueue);
            return Array.isArray(parsedQueue) ? parsedQueue : [];
        } catch (error) {
            console.error('GrowtypeWcUpsellModal: Failed to parse queue', error);
            return [];
        }
    }

    resolveBootstrapModal() {
        if (window.bootstrap && typeof window.bootstrap.Modal === 'function') {
            return window.bootstrap.Modal.getOrCreateInstance(this.modalElement);
        }

        return null;
    }

    cacheElements() {
        this.loaderElement = this.modalElement.querySelector('.gwc-upsell-loader');
        this.contentElement = this.modalElement.querySelector('.modal-body');
        this.featuredElement = this.modalElement.querySelector('.gwc-upsell-featured');
        this.titleElement = this.modalElement.querySelector('.modal-title');
        this.shortDescriptionElement = this.modalElement.querySelector('.gwc-upsell-short-description');
        this.descriptionElement = this.modalElement.querySelector('.gwc-upsell-description');
        this.offerBoxElement = this.modalElement.querySelector('.gwc-upsell-offer-box');
        this.priceElement = this.modalElement.querySelector('.gwc-upsell-price-html');
        this.extraDetailsElement = this.modalElement.querySelector('.gwc-upsell-extra-details');
        this.paymentElement = this.modalElement.querySelector('.gwc-upsell-payment');
        this.promoElement = this.modalElement.querySelector('.gwc-upsell-promo');
        this.discountElement = this.modalElement.querySelector('.gwc-upsell-discount');
        this.closeButton = this.modalElement.querySelector('.gwc-upsell-close');
        this.skipButton = this.modalElement.querySelector('.gwc-upsell-skip');
    }

    bindEvents() {
        this.modalElement.addEventListener('show.bs.modal', () => {
            if (!this.autoShow) {
                this.prepareManualOpen();
            }
        });

        if (this.closeButton) {
            this.closeButton.addEventListener('click', (event) => {
                event.preventDefault();
                this.dismissCurrent();
            });
        }

        if (this.skipButton) {
            this.skipButton.addEventListener('click', (event) => {
                event.preventDefault();
                this.dismissCurrent();
            });
        }

        this.modalElement.addEventListener('hide.bs.modal', (event) => {
            if (this.programmaticHide || !this.queue.length) {
                return;
            }

            event.preventDefault();
            this.dismissCurrent();
        });
    }

    prepareManualOpen() {
        if (this.isLoading) {
            return;
        }

        if (!this.queue.length && this.originalQueue.length) {
            this.queue = [...this.originalQueue];
            this.currentIndex = 0;
        }

        if (this.queue.length && !this.titleElement?.textContent?.trim()) {
            this.loadCurrent();
        }
    }

    loadCurrent() {
        if (this.isLoading) return;

        const productId = this.queue[this.currentIndex];
        if (!productId) {
            this.finish();
            return;
        }

        this.setLoading(true);

        jQuery.ajax({
            url: window.growtype_wc_ajax ? window.growtype_wc_ajax.url : '',
            method: 'GET',
            data: {
                action: this.ajaxGetItemAction,
                nonce: this.ajaxNonce,
                product_id: productId,
                current_url: window.location.href
            }
        }).done((response) => {
            if (response.success && response.data) {
                this.renderCurrent(response.data);
            } else {
                console.error('GrowtypeWcUpsellModal: Failed to fetch upsell item', response);
                this.dismissCurrent();
            }
        }).fail((error) => {
            console.error('GrowtypeWcUpsellModal: AJAX error', error);
            this.dismissCurrent();
        }).always(() => {
            this.setLoading(false);
        });
    }

    setLoading(isLoading) {
        this.isLoading = isLoading;
        if (this.loaderElement) {
            this.loaderElement.style.display = isLoading ? 'flex' : 'none';
        }
        if (this.contentElement) {
            this.contentElement.style.opacity = isLoading ? '0.3' : '1';
            this.contentElement.style.pointerEvents = isLoading ? 'none' : 'auto';
        }
    }

    renderCurrent(data) {
        if (!data) return;

        if (this.featuredElement) {
            this.featuredElement.style.backgroundImage = data.image_url ? `url('${data.image_url}')` : 'none';
        }

        if (this.titleElement) {
            this.titleElement.textContent = data.title || '';
        }

        if (this.shortDescriptionElement) {
            this.shortDescriptionElement.innerHTML = data.short_description || '';
        }

        if (this.descriptionElement) {
            this.descriptionElement.innerHTML = data.description || '';
        }

        if (this.offerBoxElement) {
            this.offerBoxElement.style.display = data.description ? 'block' : 'none';
        }

        if (this.priceElement) {
            this.priceElement.innerHTML = data.price_html || '';
        }

        if (this.extraDetailsElement) {
            this.extraDetailsElement.innerHTML = data.extra_details_html || '';
        }

        if (this.paymentElement) {
            this.paymentElement.innerHTML = data.button_html || '';
        }

        if (this.promoElement) {
            this.promoElement.innerHTML = data.promo_label_html || '';
            this.promoElement.style.display = data.promo_label_html ? 'inline-block' : 'none';
        }

        if (this.discountElement) {
            this.discountElement.innerHTML = data.discount_label_html || '';
            this.discountElement.style.display = data.discount_label_html ? 'inline-block' : 'none';
        }

        if (this.skipButton) {
            this.skipButton.style.display = this.queue.length > 1 ? 'inline-block' : 'none';
        }

        if (typeof window.growtypeWcPaymentButton === 'function') {
            window.setTimeout(() => {
                window.growtypeWcPaymentButton();
            }, 50);
        } else {
            jQuery(document).trigger('growtypeModalLoaded');
        }

        this.initializeEmbeddedContent();
    }

    show() {
        if (this.bootstrapModal) {
            this.bootstrapModal.show();
            return;
        }

        if (window.jQuery && typeof window.jQuery.fn.modal === 'function') {
            window.jQuery(this.modalElement).modal('show');
            return;
        }

        this.modalElement.classList.add('show');
        this.modalElement.style.display = 'block';
        this.modalElement.removeAttribute('aria-hidden');
    }

    hide() {
        if (this.bootstrapModal) {
            this.bootstrapModal.hide();
            return;
        }

        if (window.jQuery && typeof window.jQuery.fn.modal === 'function') {
            window.jQuery(this.modalElement).modal('hide');
            return;
        }

        this.modalElement.classList.remove('show');
        this.modalElement.style.display = 'none';
        this.modalElement.setAttribute('aria-hidden', 'true');
    }

    dismissCurrent() {
        if (this.isAdvancing || !this.queue.length) {
            return;
        }

        const productId = this.queue[this.currentIndex];
        if (!productId) {
            this.finish();
            return;
        }

        this.isAdvancing = true;

        jQuery.ajax({
            url: window.growtype_wc_ajax ? window.growtype_wc_ajax.url : '',
            method: 'POST',
            data: {
                action: this.ajaxAction,
                nonce: this.ajaxNonce,
                product_id: productId,
            }
        })
.always(() => {
            this.isAdvancing = false;
        }).done(() => {
            this.queue.splice(this.currentIndex, 1);

            if (this.queue.length) {
                this.loadCurrent();
                this.show();
            } else {
                this.finish();
            }
        }).fail((error) => {
            console.error('GrowtypeWcUpsellModal: Failed to dismiss upsell', error);
        });
    }

    finish() {
        this.queue = [];
        this.programmaticHide = true;
        this.hide();

        if (!this.autoShow) {
            window.setTimeout(() => {
                this.programmaticHide = false;
            }, 150);
            return;
        }

        window.setTimeout(() => {
            this.modalElement.remove();
            this.programmaticHide = false;
        }, 150);
    }

    initializeEmbeddedContent() {
        const $scope = jQuery(this.modalElement);

        // Trigger AJAX load for post containers and carousels
        $scope.find('.growtype-post-container-wrapper, .growtype-carousel-wrapper').each((index, wrapper) => {
            document.dispatchEvent(new CustomEvent('growtypePostAjaxLoadContent', {
                detail: {
                    wrapper
                }
            }));
        });

        if (typeof jQuery.fn.slick !== 'function') {
            return;
        }

        $scope.find('.growtype-carousel-wrapper').each((index, carouselWrapper) => {
            const $carouselWrapper = jQuery(carouselWrapper);
            const $postsContainer = $carouselWrapper.find('.growtype-post-container').first();

            if (!$postsContainer.length || $postsContainer.hasClass('slick-initialized')) {
                return;
            }

            // Small delay to ensure content from growtypePostAjaxLoadContent has settled if it was fast
            window.setTimeout(() => {
                if ($postsContainer.hasClass('slick-initialized')) return;

            const columns = parseInt($postsContainer.attr('data-columns'), 10) || 5;
            const dotsAttr = String($carouselWrapper.data('dots'));
            const dots = dotsAttr === 'true' || dotsAttr === '1';

            $postsContainer.slick({
                infinite: false,
                slidesToShow: Math.max(1, Math.min(columns, 5)),
                slidesToScroll: 1,
                arrows: false,
                dots,
                adaptiveHeight: false,
                mobileFirst: false,
                responsive: [
                    {
                        breakpoint: 992,
                        settings: {
                            slidesToShow: Math.max(1, Math.min(columns, 4))
                        }
                    },
                    {
                        breakpoint: 768,
                        settings: {
                            slidesToShow: Math.max(1, Math.min(columns, 3))
                        }
                    },
                    {
                        breakpoint: 576,
                        settings: {
                            slidesToShow: 2
                        }
                    }
                ]
            });
            }, 100);
        });
    }
}

function upsellModal() {
    const modalElements = document.querySelectorAll('[data-gwc-upsell-modal="true"]');

    if (!modalElements.length) {
        return;
    }

    modalElements.forEach((modalElement) => {
        if (modalElement.dataset.initialized === 'true') {
            return;
        }

        modalElement.dataset.initialized = 'true';
        new GrowtypeWcUpsellModal(modalElement);
    });
}

export { upsellModal };

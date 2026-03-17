<?php

class Growtype_Wc_Upsell
{
    const META_KEY = '_growtype_wc_upsell';
    const RETURN_URL_META_KEY = '_growtype_return_after_payment_url';
    const QUEUE_META_KEY = 'growtype_wc_upsell_queue';
    const DISMISSED_META_KEY = 'growtype_wc_upsell_dismissed';
    const MODAL_ID = 'growtypeWcUpsellModal';
    const DISMISS_AJAX_ACTION = 'growtype_wc_dismiss_upsell';
    const GET_ITEM_AJAX_ACTION = 'growtype_wc_get_upsell_item';

    public function __construct()
    {
        $this->load_partials();
    }

    public function load_partials()
    {
        include('partials/class-growtype-wc-upsell-return-url.php');
        include('partials/class-growtype-wc-upsell-catalog.php');
        include('partials/class-growtype-wc-upsell-queue.php');
        include('partials/class-growtype-wc-upsell-modal.php');
        include('partials/class-growtype-wc-upsell-crud.php');

        Growtype_Wc_Upsell_Return_Url::init();
        Growtype_Wc_Upsell_Catalog::init();
        Growtype_Wc_Upsell_Queue::init();
        Growtype_Wc_Upsell_Modal::init();
        Growtype_Wc_Upsell_Crud::init();
    }
}
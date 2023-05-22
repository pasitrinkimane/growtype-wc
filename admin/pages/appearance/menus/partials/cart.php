<?php

if (!class_exists('Growtype_Wc_Nav_Cart')) {
    class Growtype_Wc_Nav_Cart
    {
        public function add_nav_menu_meta_boxes()
        {
            add_meta_box(
                'growtype_wc_nav_link',
                __('Growtype Wc'),
                array ($this, 'nav_menu_link'),
                'nav-menus',
                'side',
                'low'
            );
        }

        public function nav_menu_link()
        { ?>
            <div id="growtype-wc-cart" class="posttypediv">
                <div id="tabs-panel-wishlist-login" class="tabs-panel tabs-panel-active">
                    <ul id="wishlist-login-checklist" class="categorychecklist form-no-clear">
                        <li>
                            <label class="menu-item-title">
                                <input type="checkbox" class="menu-item-checkbox" name="menu-item[-1][menu-item-object-id]" value="-1"> Cart
                            </label>
                            <input type="hidden" class="menu-item-type" name="menu-item[-1][menu-item-type]" value="custom">
                            <input type="hidden" class="menu-item-title" name="menu-item[-1][menu-item-title]" value="Cart">
                            <input type="hidden" class="menu-item-url" name="menu-item[-1][menu-item-url]" value="#<?= Growtype_Wc_Admin_Appearance_Menus::NAV_CART_KEY ?>#">
                            <input type="hidden" class="menu-item-classes" name="menu-item[-1][menu-item-classes]" value="growtype-wc-cart">
                        </li>
                    </ul>
                </div>
                <p class="button-controls">
                    <span class="add-to-menu">
        				<input type="submit" class="button-secondary submit-add-to-menu right" value="Add to Menu" name="add-post-type-menu-item" id="submit-growtype-wc-cart">
        				<span class="spinner"></span>
        			</span>
                </p>
            </div>
        <?php }
    }
}

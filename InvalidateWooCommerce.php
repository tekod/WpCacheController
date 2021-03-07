<?php namespace Tekod\WpCacheController;

/**
 * Common task: register group action for monitoring any change in WooCommerce.
 */
class InvalidateWooCommerce {


    protected static $ActionName= 'WpCacheController_InvalidateWooCommerce';


    /**
     * Register custom action.
     */
    public static function Register() {

        $ActionsList= [
            'create_product_cat', 'edit_product_cat', 'delete_product_cat',                                     // monitor categories
            'create_product_tag', 'edit_product_tag', 'delete_product_tag',                                     // monitor tags
            'woocommerce_attribute_added', 'woocommerce_attribute_updated', 'woocommerce_attribute_deleted',    // monitor attributes
            'woocommerce_new_product', 'woocommerce_update_product', 'woocommerce_delete_product',              // monitor product

            // internal custom action to detect product deletion, "woocommerce_delete_product" doesn't work when deleting from WordPress dashboard
            'WpCacheController_CaptureWooCommerceDelete',

            // following actions no need to monitor - they are covered by monitoring product change
            // woocommerce_new_product_variation, woocommerce_update_product_variation, woocommerce_delete_product_variation,   // product variations
            // woocommerce_updated_product_price, woocommerce_updated_product_sales,                                            // monitor prices
            // woocommerce_updated_product_stock,                                                                               // monitor stock
        ];

        // register that custom action
        CacheController::GetInstance()->RegisterGroupAction(static::$ActionName, $ActionsList);

        // workaround to detect deleting woocommerce product
        static::HandleDeletingProduct();
    }


    /**
     * Custom handling case: deleting product.
     */
    protected static function HandleDeletingProduct() {

        add_action('wp_trash_post', [__CLASS__, 'TriggerWooCommerceAction'], 99);
        add_action('delete_post', [__CLASS__, 'TriggerWooCommerceAction'], 99);
    }


    /**
     * Hook listener for "wp_trash_post" and "delete_post" actions.
     *
     * @param int $PostID
     */
    public static function TriggerWooCommerceAction($PostID) {

        if (get_post_type($PostID) === 'product') {
            do_action('WpCacheController_CaptureWooCommerceDelete');
        }
    }

}


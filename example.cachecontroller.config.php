<?php
// configuration record
return [
    // list of caching profiles
	'Profiles'=>  [

        // "woo" profile can handle changes on woocommerce products
        'woo'=> [
            'Actions'=> [       // invalidate on these actions
                'WpCacheController_InvalidateWooCommerce',   // custom action: capture all WooCommerce product changes
            ],
            'TTL'=> 30*86400,	// cache expire (in seconds)
            'Enabled'=> true,	// enable caching (this can be disabled on dashboard settings)
            'Logging'=> true,	// allow logging (this can be disabled on dashboard settings)
        ],

        // configure profile "Footer" to handle content of footer section of web pages
        'Footer' => [
            'Actions' => [      //  track changes on ACF fields, CF7 forms and menus
                'acf/save_post', 'wpcf7_save_contact_form', 'wp_update_nav_menu'
            ],
            'TTL'=> 86400,      // cache expire (in seconds)
            'Enabled'=> false,	// this profile is disabled, dashboard settings cannot afect it
            'Logging'=> false,	// logging is disabled, dashboard settings cannot afect it
        ],

        // profile "Album" handle content for custom-post-type "album"
        'Album' => [
            'Actions' => [
                'save_post_album',      // trigger on saving post type
            ],
            'TTL'=>     86400,  // cache expire (in seconds)
            'Enabled'=> true,	// enable caching (this can be disabled on dashboard settings)
            'Logging'=> false,	// logging is disabled, dashboard settings cannot afect it
        ],
    ],

    // register these custom actions
    'CustomActions'=> [
        \Tekod\WpCacheController\InvalidateWooCommerce::class,
     ],

    // enable internal autoloader
    'Autoloader'=> true,

    // directory where to store data
    'Dir'=> wp_get_upload_dir()['basedir']."/WpCacheController",
];

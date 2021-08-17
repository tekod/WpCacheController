<?php
// configuration record
return [
    // list of caching profiles
	'Profiles'=>  [

        // "Woo" profile can handle changes on woocommerce products
        'Woo'=> [
            'Actions'=> [       // invalidate on these actions
                'WpCacheController_InvalidateWooCommerce',   // custom action: capture all WooCommerce product changes
            ],
            'TTL'    => 30*86400,// cache expire (in seconds)
            'Enabled'=> true,    // enable caching (this can be disabled on dashboard settings)
            'Logging'=> true,    // allow logging (this can be disabled on dashboard settings)
        ],

        // configure profile "Footer" to handle content of footer section of web pages
        'Footer'=> [
            'Actions'=> [      //  track changes on ACF fields, CF7 forms and menus
                'acf/save_post', 'wpcf7_save_contact_form', 'wp_update_nav_menu'
            ],
            'TTL'    => 86400,  // cache expire (in seconds)
            'Enabled'=> false,	// this profile is disabled, dashboard settings cannot affect it
            'Logging'=> false,	// logging is disabled, dashboard settings cannot affect it
        ],

        // profile "Album" handle content for custom-post-type "album"
        'Album'=> [
            'Actions'=> [
                'save_post_album',      // trigger on saving post type
            ],
            //'TTL'    => 86400,  // this setting can be omitted (default is 86400)
            //'Enabled'=> true,	// this setting can be omitted (default is true)
            //'Logging'=> true,	// this setting can be omitted (default is true)
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

     // cache content file extension
    'FileExt'=> 'php',
];

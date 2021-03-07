<?php namespace Tekod\WpCacheController;


/**
 * Dashboard, settings page.
 */
class Dashboard {


    // configuration
    protected static $Config;

    // form actions
    protected static $ActionSettings= 'wpcachecontroller_settings';
    protected static $ActionClearCache= 'wpcachecontroller_clearcache';

    // "options" table identifier
    protected static $OptionName= 'WpCacheController_Settings';


    /**
     * Register custom action.
     *
     * @param array $Config
     */
    public static function Register($Config) {

        // save config
        static::$Config= $Config;

        // register admin page
        add_action('admin_menu', function () {
            $PageId= add_options_page('CacheController settings', 'CacheController', 'manage_options', 'cachecontroller-config', [__CLASS__,'DisplaySettingPage']);
            add_action( "load-$PageId", [__CLASS__, 'PrepareAdminNotices']);
        });

        // register handlers
        add_action('admin_post_'.static::$ActionSettings, [__CLASS__, 'OnPostSettings']);
        add_action('admin_post_'.static::$ActionClearCache, [__CLASS__, 'OnPostClearCache']);
    }


    /**
     * Schedule rendering admin notices.
     */
    public static function PrepareAdminNotices() {

        add_action('admin_notices', [__CLASS__, 'RenderAdminNotice']);
    }


    /**
     * Display notices on top of admin page.
     */
    public static function RenderAdminNotice() {

        $Message= get_transient('WpCacheControllerAdminMsg');
        delete_transient('WpCacheControllerAdminMsg');

        switch ($Message) {
            case 'updated': echo '<div class="updated"><p>Settings saved.</p></div>'; return;
            case 'cleared': echo '<div class="updated"><p>Cache is cleared.</p></div>'; return;
        }
    }


    /**
     * Render admin page
     */
    public static function DisplaySettingPage() {

        $Settings= CacheController::GetSettings();
        $StatsHTML= static::RenderStatsReport();
        $RedirectURL= urlencode($_SERVER['REQUEST_URI']);

        $LogDir= str_replace('\\', '/', substr(static::$Config['Dir'], strlen($_SERVER['DOCUMENT_ROOT'])));
        $LogFile= $LogDir.'/Log.txt?ts='.time();    // timestamp added to avoid browser caching

        ?>
        <style>
            blockquote td {border-bottom: 1px solid #e2e2e2;}
        </style>
        <script>
            function LoadStats() {
                jQuery('#ccLogs').show();
                jQuery.get("<?php echo $LogFile; ?>", function(data) { jQuery('#ccLogs').val(data);});
            }
        </script>
        <h1>CacheController</h1>
        <hr style="margin-right:6em">
        <div style="padding:2em 0 2em 2em; font-weight:bold; position:relative;">
            <h2>Clear cache</h2>
            <form action="admin-post.php" method="post">
                <?php submit_button('Clear now'); ?>
                <input type="hidden" name="action" value="<?php echo static::$ActionClearCache; ?>">
                <?php wp_nonce_field(static::$ActionClearCache, static::$OptionName.'_nonce', false); ?>
                <input type="hidden" name="_wp_http_referer" value="<?php echo $RedirectURL; ?>">
                <div style="color:gray;font-weight:normal;margin-top:-1em">This button will invalidate all cached entries forcing further requests to rebuild them.</div>
            </form>
        </div>
        <hr style="margin-right:6em">
        <div style="padding:0 0 2em 2em; margin-top:3em; font-weight:bold; position:relative;">
            <h2>Settings</h2>
            <form action="admin-post.php" method="post" style="border:1px solid #ddd; background-color:#f8f8f8; padding:1em 2em; width:30em">
                <label style="display:block; padding:1em 0">
                    <input type="checkbox" name="WpCacheController_Enabled" id="enabled" value="1" <?php echo $Settings['Enabled'] ? 'checked="checked"' : ''; ?>/> Enable caching
                </label>
                <hr>
                <label style="display:block; padding:1em 0">
                    <input type="checkbox" name="WpCacheController_Logging" id="logging" value="1" <?php echo $Settings['Logging'] ? 'checked="checked"' : ''; ?>/> Enable logging events and statistics
                </label>
                <hr>
                <label style="display:block; padding:1em 0">
                    <input type="checkbox" name="WpCacheController_Widget" id="widget" value="1" <?php echo $Settings['Widget'] ? 'checked="checked"' : ''; ?>/> Show widget on frontend
                </label>
                <hr>
                <?php submit_button(); ?>
                <input type="hidden" name="action" value="<?php echo static::$ActionSettings; ?>">
                <?php wp_nonce_field(static::$ActionSettings, static::$OptionName.'_nonce', false); ?>
                <input type="hidden" name="_wp_http_referer" value="<?php echo $RedirectURL; ?>">
            </form>
            <div style="position:absolute; left:50%; top:0; width:45em">
                <h2 style="margin-top:0">Statistics</h2>
                <blockquote style="border:1px solid #ddd; background-color:#f8f8f8; margin:0; padding:1em 2em; line-height:2em">
                <?php echo $StatsHTML; ?>
                </blockquote>
            </div>
        </div>
        <hr style="margin:0 6em 0 0">
        <div style="margin:3em 2em 2em 2em">
            <h2>Diagnostic Logs</h2>
            <input type="button" class="button-primary migrate-db-button" style="margin:1em 0 0" value="Load log entries" onclick="LoadStats()">
            <br>
            <pre style="white-space:pre-line;">
                <textarea id="ccLogs" style="width:100%; height:40em; padding-left:1em; font-size:90%; display:none;"></textarea>
            </pre>
        </div>
        <?php
    }


    /**
     * Prepare statistics report.
     *
     * @return string
     */
    protected static function RenderStatsReport() {

        $Path= static::$Config['Dir'].'/Stats.txt';
        $Statistics= is_file($Path) ? file_get_contents($Path) : '';
        if (!$Statistics) {
            return '<span style="color:silver">No data.</span>';
        }
        list($Header, $Numbers)= explode("\n\n", $Statistics);
        $Header= str_replace(':  ', ': &nbsp; ', $Header);
        $Table= str_replace("\n", '</td></tr><tr><td>', $Numbers);
        $Table= str_replace(':  ', ':</td><td>', $Table);
        $Table= '<table style="width:100%; margin-top:1em;" cellspacing="0"><tr><th width="50%"><th></th></tr><tr><td>'.$Table.'</td></tr></table>';
        return $Header.$Table;
    }



    /**
     * Handle saving settings.
     */
    public static function OnPostSettings() {

        // validation
        if (!wp_verify_nonce($_POST[static::$OptionName.'_nonce'], static::$ActionSettings)) {
            die( 'Invalid nonce.'.var_export($_POST, true));
        }
        if (!isset($_POST['_wp_http_referer'])) {
            die( 'Missing target.' );
        }

        // update settings
        $Settings= [
            'Enabled'=> boolval($_POST['WpCacheController_Enabled'] ?? ''),
            'Logging'=> boolval($_POST['WpCacheController_Logging'] ?? ''),
            'Widget'=> boolval($_POST['WpCacheController_Widget'] ?? ''),
        ];
        update_option(static::$OptionName, serialize($Settings));

        // prepare confirmation message
        set_transient('WpCacheControllerAdminMsg', 'updated');

        // redirect to viewing context
        wp_safe_redirect(urldecode($_POST['_wp_http_referer']));
        die();
    }


    /**
     * Handle clearing cache.
     */
    public static function OnPostClearCache() {

        // validation
        if (!wp_verify_nonce($_POST[static::$OptionName.'_nonce'], static::$ActionClearCache)) {
            die( 'Invalid nonce.'.var_export($_POST, true));
        }
        if (!isset($_POST['_wp_http_referer'])) {
            die( 'Missing target.' );
        }

        // execute clearing caches
        CacheController::GetInstance()->InvalidateAllProfiles();

        // prepare confirmation message
        set_transient('WpCacheControllerAdminMsg', 'cleared');

        // redirect to viewing context
        wp_safe_redirect(urldecode($_POST['_wp_http_referer']));
        die();
    }

}


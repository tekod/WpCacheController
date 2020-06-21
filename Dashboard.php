<?php namespace FWS\CacheController;

/**
 * Dashboard, settings page.
 */
class Dashboard {


    // configuration
    protected static $Config;


    /**
     * Register custom action.
     */
    public static function Register($Config) {

        // save config
        static::$Config= $Config;

        // register admin page
        add_action('admin_menu', function () {
            add_options_page('CacheController settings', 'CacheController', 'manage_options', 'cachecontroller-config', [__CLASS__,'DisplaySettingPage']);
        });

        // register settings form
        add_action('admin_init', function() {
            add_settings_section("section", "", null, "fsCacheController-options");
            register_setting("section", "fsCacheController_Enabled");
            register_setting("section", "fsCacheController_Logging");
        });
    }


    /**
     * Render admin page
     */
    public static function DisplaySettingPage() {

        $Settings= CacheController::GetSettings();
        $StatsHTML= static::RenderStatsReport();
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
            <div style="padding:0 40em 2em 2em; margin-top:4em; font-weight:bold; position:relative;">
            <h3>Settings</h3>
            <form action="options.php" method="post" style="border:1px solid #ddd; background-color:#f8f8f8; padding:1em 2em; width:30em">
            <?php settings_fields("section"); ?>
            <label style="display:block; padding:1em 0">
                <input type="checkbox" name="fsCacheController_Enabled" id="enabled" value="1" <?php echo $Settings['Enabled'] ? 'checked="checked"' : ''; ?>/> Enable caching
            </label>
            <hr>
            <label style="display:block; padding:1em 0">
                <input type="checkbox" name="fsCacheController_Logging" id="logging" value="1" <?php echo $Settings['Logging'] ? 'checked="checked"' : ''; ?>/> Enable logging events and statistics
            </label>
            <hr>
            <?php submit_button(); ?>
            </form>
            <div style="position:absolute; left:50%; top:0; width:45em">
                <h3 style="margin-top:0">Statistics</h3>
                <blockquote style="border:1px solid #ddd; background-color:#f8f8f8; margin:0; padding:1em 2em; line-height:2em">
                <?php echo $StatsHTML; ?>
                </blockquote>
            </div>
        </div>
        <hr style="margin:4em 6em 0 0">
        <div style="margin:4em 2em 2em 2em">
            <h3>Diagnostic Logs</h3>
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

}

?>
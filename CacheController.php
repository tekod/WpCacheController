<?php namespace Tekod\WpCacheController;


/**
 * Controller of all caching dealers.
 */
class CacheController {

    // storage for settings
    protected $Settings;

    // collection of profiles
    protected $Profiles= [];

    // collection of group actions
    protected $GroupActions= [];

    // path to directory for data
    protected $StorageDir;

    // global permission to store logs
    protected $LogEnabled;

    // filename of master-tag
    protected $MasterTag= '.master.tag';

    // buffer for statistics
    protected $Stats= [];

    // buffer for in-closure stats
    protected $InClosureStats= [0,0];

    // singleton instance
    protected static $Instance;


    /**
     * Instantiate and initialize main object.
     *
     * @param string $ConfigPath
     */
    public static function Init($ConfigPath) {

        // load config (dynamic inclusion)
        $Config= include $ConfigPath;

        // add missing config options
        $Config += [
            'Dir'        => wp_get_upload_dir()['basedir']."/WpCacheController",
            'Autoloader'  => false,
			'Profiles'     => [],
            'CustomActions' => [],
        ];

        // instantiate this class
        static::$Instance= new static($Config);

        // register custom actions, this cannot be done in constructor because it need $Instance variable
        foreach($Config['CustomActions'] as $Class) {
            $Class::Register();
        }

        // register admin settings page
        if (is_admin()) {
          Dashboard::Register($Config);
        }
    }


    /**
     * Return singleton instance of this object.
     *
     * @return self
     */
    public static function GetInstance() {

        return static::$Instance;
    }


    /**
     * Constructor.
     *
     * @param array $Config
     */
    protected function __construct($Config) {

        // load settings
        $this->Settings= $this->GetSettings();

        // init log storage
        $this->InitLog($Config);

        // register autoloader
        if ($Config['Autoloader']) {
            spl_autoload_register(array($this, 'Autoloader'), true, false);
        }

        // init profiles
        foreach($Config['Profiles'] as $Name => $Profile) {
        	$this->SetProfile($Name, $Profile['Actions'], $Profile['TTL'], $Profile['Logging'], $Profile['Enabled']);
		}

        // skip if not enabled
        if (!$this->Settings['Enabled']) {
            return;
        }

        // monitor all actions
        add_action('all', array($this, 'OnAllAction'));

        // dispatch event
        do_action('WpCacheController-Init');

        // hook on shutdown
        register_shutdown_function(array($this, 'OnShutdown'));
    }


    /**
     * External scripts can use this method to add their profile configuration.
     *
     * @param string $Name  name of profile
     * @param array|string $InvalidatingActions  array of actions (or CSV list)
     * @param int $TTL  time-to-live in seconds
     * @param bool $Logging  enable logging
     * @param bool $Enabled  enable caching content
     * @return self
     */
    public function SetProfile($Name, $InvalidatingActions, $TTL=86400, $Logging=false, $Enabled=true) {

        // convert to array
        if (is_string($InvalidatingActions)) {
            $InvalidatingActions= explode(',', $InvalidatingActions);
        }

        // sanitize array
        $InvalidatingActions= array_filter(array_map('trim', $InvalidatingActions));

        // append to collection
        $this->Profiles[$Name]= [
            'Name'=> $Name,
            'InvalidatingActions'=> $InvalidatingActions,
            'TTL'=> $TTL,
            'Logging'=> $Logging && $this->Settings['Logging'],
            'Enabled'=> $Enabled && $this->Settings['Enabled'],
        ];

        // chaining
        return $this;
    }


    /**
     * Return specified dealer object.
     *
     * @param string $Name  name of profile
     * @return Dealer
     */
    public function GetDealer($Name) {

        // prevent fatal error
        if (!isset($this->Profiles[$Name])) {
            wp_die('WpCacheController profile "'.$Name.'" not found.');
        }

        // build dealer
        if (!isset($this->Profiles[$Name]['Dealer'])) {
            $this->Profiles[$Name]['Dealer']= new Dealer($this, $this->Profiles[$Name]);
        }

        // return dealer object
        return $this->Profiles[$Name]['Dealer'];
    }


    /**
     * Synonym for "GetDealer" but in static context.
     *
     * @param string $Name
     * @return Dealer
     */
    public static function Profile($Name) {

        return static::$Instance->GetDealer($Name);
    }


    /**
     * Retrieve settings.
     *
     * @return array
     */
    public static function GetSettings() {

        $SettingsDump= get_option('WpCacheController_Settings');
        $Settings= @unserialize($SettingsDump);
        if (!is_array($Settings)) {
            $Settings= [];
        }
        return $Settings + [
            'Enabled'=> false,
            'Logging'=> false,
            'Widget' => false,
        ];
    }


    /**
     * This method is listener of "all" hook action.
     */
    public function OnAllAction() {

        // get current action
        $HookName= current_filter();
        $HookNameParts= explode(' (', $HookName);
        $SearchName= isset($HookNameParts[1]) ? $HookNameParts[0] : $HookName;

        // find profiles containing that action and execute invalidation
        foreach($this->Profiles as $Name => $Profile) {
            if (in_array($SearchName, $Profile['InvalidatingActions'])) {
                $this->GetDealer($Name)->Invalidate($HookName);
            }
        }

        // search in group actions and trigger custom action if found
        foreach($this->GroupActions as $Name => $List) {
            if (in_array($HookName, $List)) {
                //$this->Log("[GroupAction: {$Name}]  triggered by action: {$HookName}");
                do_action("$Name ($HookName)");
            }
        }
    }


    /**
     * Clear all caches.
     */
    public function InvalidateAllProfiles() {

        $Profiles= array_keys($this->Profiles);
        foreach($Profiles as $Profile) {
            $this->GetDealer($Profile)->Invalidate('Clearing cache');
        }
    }


    /**
     * Group listening for common actions.
     *
     * @param string $NewActionName
     * @param array|string $ActionsList
     * @return self
     */
    public function RegisterGroupAction($NewActionName, $ActionsList) {

        // convert to array
        if (is_string($ActionsList)) {
            $ActionsList= explode(',', $ActionsList);
        }

        // sanitize array
        $ActionsList= array_filter(array_map('trim', $ActionsList));

        // store in collection
        $this->GroupActions[$NewActionName]= $ActionsList;

        // chaining
        return $this;
    }


    /**
     * Autoloading handler.
     *
     * @param string $Class
     * @return null|boolean
     */
    protected function Autoloader($Class) {

        $Parts= array_filter(explode('\\', $Class));
        if (isset($Parts[1]) && $Parts[0].'\\'.$Parts[1] <> __NAMESPACE__) {
            return null;
        }
        unset($Parts[0], $Parts[1]);
        $Path= __DIR__.'/'.implode('/', $Parts).'.php';
        if (!is_file($Path)) {
            return null;
        }
        // dynamic inclusion
        require $Path;
        return true;
    }


    /**
     * Initialize logging system.
     *
     * @param array $Config
     */
    protected function InitLog($Config) {

        $this->LogEnabled= $this->Settings['Logging'];
        $this->StorageDir= $Config['Dir'];

        // ensure directory existence
        if (!is_dir($this->StorageDir)) {
            mkdir($this->StorageDir, 0777, true);
            //touch($this->StorageDir.'/'.$this->MasterTag);
        }

        // ensure logfile existence
        $Path= $this->StorageDir.'/Log.txt';
        touch($Path);

        // trim log file if it become too big
        $FileSize= filesize($Path);
        $Limit= strpos(home_url(), '.local') === false
            ? 250*1024       // 250 kb on servers
            :  50*1024;      // 50 kb on development environment
        if ($FileSize > $Limit) {
            $Dump= $FileSize > 2*1024*1024
                ? ''            // 2 Mb is too big to fit in memory, just empty file
                : '  .  .  .  . . . ......'.substr(file_get_contents($Path), -($Limit / 2));
            file_put_contents($Path, $Dump);
        }
    }


    /**
     * Store log messages.
     *
     * @param string $Message
     */
    public function Log($Message) {

        if (!$this->LogEnabled) {
            return;
        }
        $Path= $this->StorageDir.'/Log.txt';
        $Message= "\r\n".date('r').'  '.$Message;
        file_put_contents($Path, $Message, FILE_APPEND);
    }


    /**
     * Return storage directory.
     *
     * @return string
     */
    public function GetStorageDir() {

        return $this->StorageDir;
    }


    /**
     * Return path for master-tag for specified profile.
     *
     * @param string $ProfileName
     * @return string
     */
    public function GetMasterTagPath($ProfileName) {

        return $this->StorageDir.'/'.$ProfileName.'/'.$this->MasterTag;
    }


    /**
     * Add event to statistics.
     *
     * @param string $EventName
     */
    public function AddStatistics($EventName) {

        $this->Stats[$EventName]= ($this->Stats[$EventName] ?? 0) + 1;
    }


    /**
     * Add event to in-closure statistics.
     *
     * @param float $Time
     * @param int $QueryCount
     */
    public function AddInClosureStat($Time, $QueryCount) {

        $this->InClosureStats[0] += $Time;
        $this->InClosureStats[1] += $QueryCount;
    }


    /**
     * Shutdown handler. Used to update statistics log.
     */
    public function OnShutdown() {

    	// update
        if ($this->LogEnabled && !empty($this->Stats)) {
            $this->UpdateStats();
        }

		// don't go further if a fatal has occurred
		$LastError= error_get_last();
		if ($LastError !== null && in_array($LastError['type'], array(E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR))) {
			return;
		}

		// display widget
        if ($this->Settings['Widget']													// show if widget enabled
			&& !wp_is_json_request() && (!defined('DOING_AJAX') || !DOING_AJAX)	// hide in AJAX requests
			&& (!defined('DOING_CRON') || !DOING_CRON)							// hide in cron requests
			&& current_user_can('manage_options')								// only admin can see it
			&& !is_admin() 																// hide on dashboard pages
		) {
            $this->ShowWidget();
        }
    }


    /**
     * Display widget.
     */
    protected function ShowWidget() {
        ?>
            <div id="WpCacheController-Widget" onclick="this.style.width= this.style.width === '0px' ? '25em' : '0px';"
				 style="position:fixed; padding:2px 2em; right:-3.8em; top:12rem; width:0;
                	background-color:#fec; transition: all ease 1s; border:2px solid #000; border-left:6px double #000;
                	z-index:9999; cursor:pointer; font-size:12px; line-height:14px; white-space:nowrap">
                <b style="font-style:italic; color:gray; display:block; margin:2px 0 2px -1em;">WpCacheController</b>
                Cache hits: <?php echo intval($this->Stats['Cache Hit'] ?? 0); ?><br>
                Time in cache misses: <?php echo number_format($this->InClosureStats[0], 4); ?> s.<br>
                Queries in cache misses: <?php echo intval($this->InClosureStats[1]); ?>
            </div>
        <?php
    }


    /**
     * Update stats log record.
     */
    protected function UpdateStats() {

        $Path= $this->StorageDir.'/Stats.txt';
        // load and parse file
        $Lines= explode("\n", file_get_contents($Path));
        $Parsed= [];
        foreach($Lines as $Line) {
            $Parts= explode(':', $Line, 2);
            if (count($Parts) > 1) {
                $Parsed[$Parts[0]]= trim(end($Parts));
            }
        }
        // update values
        $Period= $Parsed['Period'] ?: date('r').' - 0';
        $Stats= $Parsed;
        foreach($this->Stats as $k => $v) {	// transfer values from buffer
            $Stats[$k]= isset($Parsed[$k]) ? intval($Parsed[$k]) + $v : $v;
        }
        // prepare report
        array_walk($Stats, function(&$v, $k){$v= "$k:  $v";});
        natcasesort($Stats);
        unset($Stats['Period']);
        array_unshift($Stats, 'Period:  '.trim(explode(' - ', $Period)[0]).' - '.date('r'), '');
        // save
        file_put_contents($Path, implode("\n", $Stats));
    }

}

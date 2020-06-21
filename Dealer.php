<?php namespace FWS\CacheController;


/**
 *  Caching dealer, dedicated for specified profile.
 */
class Dealer {

    // profile settings
    protected $Controller;
    protected $Name;
    protected $InvalidatingActions;
    protected $TTL;
    protected $Logging;
    protected $Enabled;

    // regex list of allowed chars for entity keys
    protected $ValidNameChars= 'A-Za-z0-9~_!&= \|\.\-\+';



    /**
     * Constructor.
     *
     * @param ForwardSlash\CacheController\CacheController $Controller
     * @param array $Config
     */
    public function __construct($Controller, $Config) {

        $this->Controller= $Controller;
        $this->Name= $Config['Name'];
        $this->InvalidatingActions= $Config['InvalidatingActions'];
        $this->TTL= $Config['TTL'];
        $this->Logging= $Config['Logging'];
        $this->Enabled= $Config['Enabled'];
    }


    /**
     * Retrieve and echo content from cache.
     * Closure must echo content and return nothing.
     *
     * @param string $Identifier
     * @param closure $Closure
	 * @param closure $OnCacheHit
     * @return null
     */
    public function Output($Identifier, $Closure, $OnCacheHit=null) {

        // modification of id
        $Identifier= apply_filters('fsCacheController_Id', $Identifier, $this->Name);

        // should bypass caching
        if (!$this->Enabled) {
            $this->Log('Cache Disabled  "'.$this->Name.'" / '.$Identifier);
            call_user_func($Closure);
            return;
        }

        // try to fetch from storage
        $Content= $this->Fetch($Identifier);

        // execute closure if not found
        if ($Content === false) {
            ob_start();
            call_user_func($Closure);
            $Content= ob_get_clean();
            $this->Save($Identifier, $Content);
        } else if ($OnCacheHit) {
        	// otherwise execute on-cache-hit closure
			call_user_func($OnCacheHit, $Content);
		}

        // show content (no return)
        echo $Content;
    }


    /**
     * Retrieve arbitrary content from cache.
     * Closure must return data that need to be stored in cache and echo nothing.
     *
     * @param string $Identifier
     * @param closure $Closure
	 * @param closure $OnCacheHit
     * @return mixed
     */
    public function Get($Identifier, $Closure, $OnCacheHit=null) {

        // modification of id
        $Identifier= apply_filters('fsCacheController_Id', $Identifier, $this->Name);

        // should bypass caching
        if (!$this->Enabled) {
            $this->Log('Cache Disabled  "'.$this->Name.'" / '.$Identifier);
            return call_user_func($Closure);
        }

        // try to fetch from storage
        $Content= $this->Fetch($Identifier);

        // execute closure if not found
        if ($Content === false) {
            $Content= call_user_func($Closure);
            $this->Save($Identifier, $Content);
        } else if ($OnCacheHit) {
        	// otherwise execute on-cache-hit closure
			call_user_func($OnCacheHit, $Content);
		}

        // return content
        return $Content;
    }


    /**
     * Clear all cached content of this profile.
     *
     * @param string $ActionName
     */
    public function Invalidate($ActionName) {

        // log event
        $this->Event('Invalidation', '"'.$this->Name.'", action: '.$ActionName);

        // just update timestamp of master tag file
        $Path= $this->Controller->GetMasterTagPath($this->Name);
        @touch($Path);
    }


    /**
     * Retrieve content for storage.
     *
     * @param string $Key
     * @return mixed|false
     */
    protected function Fetch($Key) {

        // find file path for specified key
        $Path= $this->GetFilePath($Key);

        // check file existance
        if (!is_readable($Path)) {
            $this->Event('Cache Miss (new entry)', '"'.$this->Name.'" / '.$Key);
            return false;
        }
        // validate TTL
        if ($this->IsExpired($Path, $Key)) {
            return false;
        }

        // load file
        $Dump= $this->LoadFile($Path);

        // decode entry
        $Entry= unserialize($Dump);
        if ($Entry === false) {
            $this->Event('Cache Miss (invalid entry)', '"'.$this->Name.'" / '.$Key);
            return false;
        }

        // log success
        $this->Event('Cache Hit', '"'.$this->Name.'" / '.$Key);

        // return content
        return $Entry;
    }


    /**
     * Store content in storage.
     *
     * @param string $Key
     * @param mixed $Content
     * @return bool
     */
    protected function Save($Key, $Content) {

        // find file path for specified key
        $Path= $this->GetFilePath($Key);

        // ensure that directory exist
        $Dir= dirname($Path);
        if (!is_dir($Dir)) {
            mkdir($Dir, 0777, true);
            touch($Dir.'/.master.tag');
			file_put_contents($Dir.'/.htaccess', 'deny from all');
        }

        // save file
        $Dump= "<?php return ".var_export(serialize($Content), true)."; ?>";
		touch($Path);       // must "touch" because WPEngine will preserve timestamp if file content is same
        return file_put_contents($Path, $Dump) !== false;
    }


    /**
     * Calculate filename for given identifier.
     *
     * @param string $Key
     * @return bool|string
     */
    protected function GetFilePath($Key) {

        return $this->IsValidKey($Key)
            ? $this->Controller->GetStorageDir()."/{$this->Name}/{$Key}.php"
            : false;
    }


    /**
     * Validate key.
     * Key must contain only allowed characters and must be shorter then 128 bytes.
     *
     * @param string $Key
     * @return bool
     */
    protected function IsValidKey($Key) {

        // do not allow to long keys
        if (strlen($Key) > 128) {
            $this->Log('Error: key too long:  "'.$this->Name.'" / '.$Key);
            trigger_error('fsCacheController: key "'.$Key.'" is too long.', E_USER_ERROR);
            return false;
        }

        // check is there any forbidden char
        preg_replace('/[^'.$this->ValidNameChars.']/', '', $Key, -1, $Count);
        if ($Count > 0) {
            $this->Log('Error: key contains invalid chars:  "'.$this->Name.'" / '.$Key);
            trigger_error('fsCacheController: key "'.$Key.'" contains invalid characters.', E_USER_ERROR);
            return false;
        }

        // success
        return true;
    }


    /**
     * Check is file expired
     *
     * @param string $Path
     * @param string $Key
     * @return boolean
     */
    protected function IsExpired($Path, $Key) {

        // both 'time' and 'filemtime' returns GMT timestamps, unaffected by timezone
        $FileTimestamp= @filemtime($Path);

        // compare with file modification time
        if ($FileTimestamp + $this->TTL < time()) {
            $this->Event('Cache Miss (expired TTL)', '"'.$this->Name.'" / '.$Key);
            return true;
        }

        // return true if master tag has newer timestamp
        $MasterTagFile= $this->Controller->GetMasterTagPath($this->Name);
        $MasterTag= @filemtime($MasterTagFile);
        if (!$MasterTag) {
            touch($MasterTagFile);
            return false;
        }
        if ($FileTimestamp < $MasterTag) {
            $this->Event('Cache Miss (invalidated)', '"'.$this->Name.'" / '.$Key);
            return true;
        }

        // it is not expired
        return false;
    }


    /**
     * Fetch content of file.
     */
    protected function LoadFile($FilePath) {

        // load file
        $Entry= @include $FilePath;

        // return entry
        return $Entry;
    }


    /**
     * Store log messages.
     *
     * @param string $Message
     */
    protected function Log($Message) {

        if ($this->Logging) {
            $this->Controller->Log($Message);
        }
    }


    protected function Event($EventName, $Message) {

        $this->Log("$EventName:  $Message");
        $this->Controller->AddStatistics($EventName);
    }


    /**
     *
     */
    protected function GarbageCollector() {

        // prođi kroz sve zapise i pobriši zastarele po TTL-u
    }


}


?>

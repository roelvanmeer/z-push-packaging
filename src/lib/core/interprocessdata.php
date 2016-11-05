<?php
/***********************************************
* File      :   interprocessdata.php
* Project   :   Z-Push
* Descr     :   Class takes care of interprocess
*               communicaton for different purposes
*               using a backend implementing IIpcBackend
*
* Created   :   20.10.2011
*
* Copyright 2007 - 2016 Zarafa Deutschland GmbH
*
* This program is free software: you can redistribute it and/or modify
* it under the terms of the GNU Affero General Public License, version 3,
* as published by the Free Software Foundation with the following additional
* term according to sec. 7:
*
* According to sec. 7 of the GNU Affero General Public License, version 3,
* the terms of the AGPL are supplemented with the following terms:
*
* "Zarafa" is a registered trademark of Zarafa B.V.
* "Z-Push" is a registered trademark of Zarafa Deutschland GmbH
* The licensing of the Program under the AGPL does not imply a trademark license.
* Therefore any rights, title and interest in our trademarks remain entirely with us.
*
* However, if you propagate an unmodified version of the Program you are
* allowed to use the term "Z-Push" to indicate that you distribute the Program.
* Furthermore you may use our trademarks where it is necessary to indicate
* the intended purpose of a product or service provided you use it in accordance
* with honest practices in industrial or commercial matters.
* If you want to propagate modified versions of the Program under the name "Z-Push",
* you may only do so if you have a written permission by Zarafa Deutschland GmbH
* (to acquire a permission please contact Zarafa at trademark@zarafa.com).
*
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU Affero General Public License for more details.
*
* You should have received a copy of the GNU Affero General Public License
* along with this program.  If not, see <http://www.gnu.org/licenses/>.
*
* Consult LICENSE file for details
************************************************/

abstract class InterProcessData {
    const CLEANUPTIME = 1;

    // Defines which IPC provider to load, first has preference
    // if IPC_PROVIDER in the main config  is set, that class will be loaded
    static private $providerLoadOrder = array(
        'IpcSharedMemoryProvider' => 'backend/ipcsharedmemory/ipcsharedmemoryprovider.php',
        'IpcMemcachedProvider'    => 'backend/ipcmemcached/ipcmemcachedprovider.php',
    );
    static protected $devid;
    static protected $pid;
    static protected $user;
    static protected $start;
    protected $type;
    protected $allocate;
    protected $provider_class;

    /**
     * @var IIpcProvider
     */
    private $ipcProvider;

    /**
     * Constructor
     *
     * @access public
     */
    public function __construct() {
        if (!isset($this->type) || !isset($this->allocate))
            throw new FatalNotImplementedException(sprintf("Class InterProcessData can not be initialized. Subclass %s did not initialize type and allocable memory.", get_class($this)));

        $this->provider_class = defined('IPC_PROVIDER') ? IPC_PROVIDER : false;
        if (!$this->provider_class) {
            foreach(self::$providerLoadOrder as $provider => $file) {
                if (file_exists(REAL_BASE_PATH . $file) && class_exists($provider)) {
                    $this->provider_class = $provider;
                    break;
                }
            }
        }

        try {
            if (!$this->provider_class) {
                throw new Exception("No IPC provider available");
            }
            // ZP-987: use an own mutex + storage key for each device on non-shared-memory IPC
            // this method is not suitable for the TopCollector atm
            if (!($this instanceof TopCollector) && $this->provider_class !== 'IpcSharedMemoryProvider') {
                $this->type = Request::GetDeviceID(). "-". $this->type;
            }
            $this->ipcProvider = new $this->provider_class($this->type, $this->allocate, get_class($this));
            ZLog::Write(LOGLEVEL_DEBUG, sprintf("%s initialised with IPC provider '%s' with type '%s'", get_class($this), $this->provider_class, $this->type));

        }
        catch (Exception $e) {
            // ipcProvider could not initialise
            ZLog::Write(LOGLEVEL_ERROR, sprintf("%s could not initialise IPC provider '%s': %s", get_class($this), $this->provider_class, $e->getMessage()));
        }

    }

    /**
     * Initializes internal parameters.
     *
     * @access protected
     * @return boolean
     */
    protected function initializeParams() {
        if (!isset(self::$devid)) {
            self::$devid = Request::GetDeviceID();
            self::$pid = @getmypid();
            self::$user = Request::GetAuthUser();
            self::$start = time();
        }
        return true;
    }

    /**
     * Reinitializes the IPC data by removing, detaching and re-allocating it.
     *
     * @access public
     * @return boolean
     */
    public function ReInitIPC() {
        return $this->ipcProvider ? $this->ipcProvider->ReInitIPC() : false;
    }

    /**
     * Cleans up the IPC data block.
     *
     * @access public
     * @return boolean
     */
    public function Clean() {
        return $this->ipcProvider ? $this->ipcProvider->Clean() : false;
    }

    /**
     * Indicates if the IPC is active.
     *
     * @access public
     * @return boolean
     */
    public function IsActive() {
        return $this->ipcProvider ? $this->ipcProvider->IsActive() : false;
    }

    /**
     * Blocks the class mutex.
     * Method blocks until mutex is available!
     * ATTENTION: make sure that you *always* release a blocked mutex!
     *
     * @access protected
     * @return boolean
     */
    protected function blockMutex() {
        return $this->ipcProvider ? $this->ipcProvider->BlockMutex() : false;
    }

    /**
     * Releases the class mutex.
     * After the release other processes are able to block the mutex themselves.
     *
     * @access protected
     * @return boolean
     */
    protected function releaseMutex() {
        return $this->ipcProvider ? $this->ipcProvider->ReleaseMutex() : false;
    }

    /**
     * Indicates if the requested variable is available in IPC data.
     *
     * @param int   $id     int indicating the variable
     *
     * @access protected
     * @return boolean
     */
    protected function hasData($id = 2) {
        return $this->ipcProvider ? $this->ipcProvider->HasData($id) : false;
    }

    /**
     * Returns the requested variable from IPC data.
     *
     * @param int   $id     int indicating the variable
     *
     * @access protected
     * @return mixed
     */
    protected function getData($id = 2) {
        return $this->ipcProvider ? $this->ipcProvider->GetData($id) : null;
    }

    /**
     * Writes the transmitted variable to IPC data.
     * Subclasses may never use an id < 2!
     *
     * @param mixed $data   data which should be saved into IPC data
     * @param int   $id     int indicating the variable (bigger than 2!)
     *
     * @access protected
     * @return boolean
     */
    protected function setData($data, $id = 2) {
        return $this->ipcProvider ? $this->ipcProvider->SetData($data, $id) : false;
    }
}

<?php
/***********************************************
* File      :   request.php
* Project   :   Z-Push
* Descr     :   This class checks and processes
*               all incoming data of the request.
*
* Created   :   01.10.2007
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

class Request {
    const UNKNOWN = "unknown";

    /**
     * self::filterEvilInput() options
     */
    const LETTERS_ONLY = 1;
    const HEX_ONLY = 2;
    const WORDCHAR_ONLY = 3;
    const NUMBERS_ONLY = 4;
    const NUMBERSDOT_ONLY = 5;
    const HEX_EXTENDED = 6;
    const ISO8601 = 7;

    /**
     * Command parameters for base64 encoded requests (AS >= 12.1)
     */
    const COMMANDPARAM_ATTACHMENTNAME = 0;
    const COMMANDPARAM_COLLECTIONID = 1; //deprecated
    const COMMANDPARAM_COLLECTIONNAME = 2; //deprecated
    const COMMANDPARAM_ITEMID = 3;
    const COMMANDPARAM_LONGID = 4;
    const COMMANDPARAM_PARENTID = 5; //deprecated
    const COMMANDPARAM_OCCURRENCE = 6;
    const COMMANDPARAM_OPTIONS = 7; //used by SmartReply, SmartForward, SendMail, ItemOperations
    const COMMANDPARAM_USER = 8; //used by any command
    //possible bitflags for COMMANDPARAM_OPTIONS
    const COMMANDPARAM_OPTIONS_SAVEINSENT = 0x01;
    const COMMANDPARAM_OPTIONS_ACCEPTMULTIPART = 0x02;

    static private $input;
    static private $output;
    static private $headers;
    static private $command;
    static private $method;
    static private $remoteAddr;
    static private $getUser;
    static private $devid;
    static private $devtype;
    static private $authUser;
    static private $authDomain;
    static private $authPassword;
    static private $asProtocolVersion;
    static private $policykey;
    static private $useragent;
    static private $attachmentName;
    static private $collectionId;
    static private $itemId;
    static private $longId; // TODO
    static private $occurence; //TODO
    static private $saveInSent;
    static private $acceptMultipart;
    static private $koeVersion;
    static private $koeBuild;
    static private $koeBuildDate;
    static private $expectedConnectionTimeout;

    /**
     * Initializes request data
     *
     * @access public
     * @return
     */
    static public function Initialize() {
        // try to open stdin & stdout
        self::$input = fopen("php://input", "r");
        self::$output = fopen("php://output", "w+");

        // Parse the standard GET parameters
        if(isset($_GET["Cmd"]))
            self::$command = self::filterEvilInput($_GET["Cmd"], self::LETTERS_ONLY);

        // getUser is unfiltered, as everything is allowed.. even "/", "\" or ".."
        if(isset($_GET["User"])) {
            self::$getUser = strtolower($_GET["User"]);
            if(defined('USE_FULLEMAIL_FOR_LOGIN') && ! USE_FULLEMAIL_FOR_LOGIN) {
                self::$getUser = Utils::GetLocalPartFromEmail(self::$getUser);
            }
        }
        if(isset($_GET["DeviceId"]))
            self::$devid = strtolower(self::filterEvilInput($_GET["DeviceId"], self::WORDCHAR_ONLY));
        if(isset($_GET["DeviceType"]))
            self::$devtype = self::filterEvilInput($_GET["DeviceType"], self::LETTERS_ONLY);
        if (isset($_GET["AttachmentName"]))
            self::$attachmentName = self::filterEvilInput($_GET["AttachmentName"], self::HEX_EXTENDED);
        if (isset($_GET["CollectionId"]))
            self::$collectionId = self::filterEvilInput($_GET["CollectionId"], self::HEX_ONLY);
        if (isset($_GET["ItemId"]))
            self::$itemId = self::filterEvilInput($_GET["ItemId"], self::HEX_ONLY);
        if (isset($_GET["SaveInSent"]) && $_GET["SaveInSent"] == "T")
            self::$saveInSent = true;

        if(isset($_SERVER["REQUEST_METHOD"]))
            self::$method = self::filterEvilInput($_SERVER["REQUEST_METHOD"], self::LETTERS_ONLY);
        // TODO check IPv6 addresses
        if(isset($_SERVER["REMOTE_ADDR"]))
            self::$remoteAddr = self::filterEvilInput($_SERVER["REMOTE_ADDR"], self::NUMBERSDOT_ONLY);

        // in protocol version > 14 mobile send these inputs as encoded query string
        if (!isset(self::$command) && !empty($_SERVER['QUERY_STRING']) && Utils::IsBase64String($_SERVER['QUERY_STRING'])) {
            $query = Utils::DecodeBase64URI($_SERVER['QUERY_STRING']);
            if (!isset(self::$command) && isset($query['Command']))
                self::$command = Utils::GetCommandFromCode($query['Command']);

            if (!isset(self::$getUser) && isset($query[self::COMMANDPARAM_USER])) {
                self::$getUser = strtolower($query[self::COMMANDPARAM_USER]);
                if(defined('USE_FULLEMAIL_FOR_LOGIN') && ! USE_FULLEMAIL_FOR_LOGIN) {
                    self::$getUser = Utils::GetLocalPartFromEmail(self::$getUser);
                }
            }

            if (!isset(self::$devid) && isset($query['DevID']))
                self::$devid = strtolower(self::filterEvilInput($query['DevID'], self::WORDCHAR_ONLY));

            if (!isset(self::$devtype) && isset($query['DevType']))
                self::$devtype = self::filterEvilInput($query['DevType'], self::LETTERS_ONLY);

            if (isset($query['PolKey']))
                self::$policykey = (int) self::filterEvilInput($query['PolKey'], self::NUMBERS_ONLY);

            if (isset($query['ProtVer']))
                self::$asProtocolVersion = self::filterEvilInput($query['ProtVer'], self::NUMBERS_ONLY) / 10;

            if (isset($query[self::COMMANDPARAM_ATTACHMENTNAME]))
                self::$attachmentName = self::filterEvilInput($query[self::COMMANDPARAM_ATTACHMENTNAME], self::HEX_EXTENDED);

            if (isset($query[self::COMMANDPARAM_COLLECTIONID]))
                self::$collectionId = self::filterEvilInput($query[self::COMMANDPARAM_COLLECTIONID], self::HEX_ONLY);

            if (isset($query[self::COMMANDPARAM_ITEMID]))
                self::$itemId = self::filterEvilInput($query[self::COMMANDPARAM_ITEMID], self::HEX_ONLY);

            if (isset($query[self::COMMANDPARAM_OPTIONS]) && (ord($query[self::COMMANDPARAM_OPTIONS]) & self::COMMANDPARAM_OPTIONS_SAVEINSENT))
                self::$saveInSent = true;

            if (isset($query[self::COMMANDPARAM_OPTIONS]) && (ord($query[self::COMMANDPARAM_OPTIONS]) & self::COMMANDPARAM_OPTIONS_ACCEPTMULTIPART))
                self::$acceptMultipart = true;
        }

        // in base64 encoded query string user is not necessarily set
        if (!isset(self::$getUser) && isset($_SERVER['PHP_AUTH_USER'])) {
            list(self::$getUser,) = Utils::SplitDomainUser(strtolower($_SERVER['PHP_AUTH_USER']));
            if(defined('USE_FULLEMAIL_FOR_LOGIN') && ! USE_FULLEMAIL_FOR_LOGIN) {
                self::$getUser = Utils::GetLocalPartFromEmail(self::$getUser);
            }
        }

        // authUser & authPassword are unfiltered!
        // split username & domain if received as one
        if (isset($_SERVER['PHP_AUTH_USER'])) {
            list(self::$authUser, self::$authDomain) = Utils::SplitDomainUser($_SERVER['PHP_AUTH_USER']);
            self::$authPassword = (isset($_SERVER['PHP_AUTH_PW']))?$_SERVER['PHP_AUTH_PW'] : "";
        }
        if(defined('USE_FULLEMAIL_FOR_LOGIN') && ! USE_FULLEMAIL_FOR_LOGIN) {
            self::$authUser = Utils::GetLocalPartFromEmail(self::$authUser);
        }
    }

    /**
     * Reads and processes the request headers
     *
     * @access public
     * @return
     */
    static public function ProcessHeaders() {
        self::$headers = array_change_key_case(apache_request_headers(), CASE_LOWER);
        self::$useragent = (isset(self::$headers["user-agent"]))? self::$headers["user-agent"] : self::UNKNOWN;
        if (!isset(self::$asProtocolVersion))
            self::$asProtocolVersion = (isset(self::$headers["ms-asprotocolversion"]))? self::filterEvilInput(self::$headers["ms-asprotocolversion"], self::NUMBERSDOT_ONLY) : ZPush::GetLatestSupportedASVersion();

        //if policykey is not yet set, try to set it from the header
        //the policy key might be set in Request::Initialize from the base64 encoded query
        if (!isset(self::$policykey)) {
            if (isset(self::$headers["x-ms-policykey"]))
                self::$policykey = (int) self::filterEvilInput(self::$headers["x-ms-policykey"], self::NUMBERS_ONLY);
            else
                self::$policykey = 0;
        }

        if (!empty($_SERVER['QUERY_STRING']) && Utils::IsBase64String($_SERVER['QUERY_STRING'])) {
            ZLog::Write(LOGLEVEL_DEBUG, "Using data from base64 encoded query string");
            if (isset(self::$policykey))
                self::$headers["x-ms-policykey"] = self::$policykey;

            if (isset(self::$asProtocolVersion))
                self::$headers["ms-asprotocolversion"] = self::$asProtocolVersion;
        }

        if (!isset(self::$acceptMultipart) && isset(self::$headers["ms-asacceptmultipart"]) && strtoupper(self::$headers["ms-asacceptmultipart"]) == "T") {
            self::$acceptMultipart = true;
        }

        ZLog::Write(LOGLEVEL_DEBUG, sprintf("Request::ProcessHeaders() ASVersion: %s", self::$asProtocolVersion));

        if (isset(self::$headers["x-push-plugin"])) {
            list($version, $build, $buildDate) = explode("/", self::$headers["x-push-plugin"]);
            self::$koeVersion = self::filterEvilInput($version, self::NUMBERSDOT_ONLY);
            self::$koeBuild = self::filterEvilInput($build, self::HEX_ONLY);
            self::$koeBuildDate = strtotime(self::filterEvilInput($buildDate, self::ISO8601));
        }

        if (defined('USE_X_FORWARDED_FOR_HEADER') && USE_X_FORWARDED_FOR_HEADER == true && isset(self::$headers["x-forwarded-for"])) {
            $forwardedIP = self::filterEvilInput(self::$headers["x-forwarded-for"], self::NUMBERSDOT_ONLY);
            if ($forwardedIP) {
                ZLog::Write(LOGLEVEL_DEBUG, sprintf("'X-Forwarded-for' indicates remote IP: %s - connect is coming from IP: %s", $forwardedIP, self::$remoteAddr));
                self::$remoteAddr = $forwardedIP;
            }
        }

        // Mobile devices send Authorization header using UTF-8 charset. Outlook sends it using ISO-8859-1 encoding.
        // For the successful authentication the user and password must be UTF-8 encoded. Try to determine which
        // charset was sent by the client and convert it to UTF-8. See https://jira.z-hub.io/browse/ZP-864.
        if (isset($_SERVER['PHP_AUTH_USER'])) {
            $encoding = mb_detect_encoding(self::$authUser, "UTF-8, ISO-8859-1");
            if (!$encoding) {
                $encoding = mb_detect_encoding(self::$authUser, Utils::GetAvailableCharacterEncodings());
                if ($encoding) {
                    ZLog::Write(LOGLEVEL_WARN,
                            sprintf("Request->ProcessHeaders(): mb_detect_encoding detected '%s' charset. This charset is not in the default detect list. Please report it to Z-Push developers.",
                                    $encoding));
                }
                else {
                    ZLog::Write(LOGLEVEL_ERROR, "Request->ProcessHeaders(): mb_detect_encoding failed to detect the Authorization header charset. It's possible that user won't be able to login.");
                }
            }
            if ($encoding && strtolower($encoding) != "utf-8") {
                ZLog::Write(LOGLEVEL_DEBUG, sprintf("Request->ProcessHeaders(): mb_detect_encoding detected '%s' charset. Authorization header will be converted to UTF-8 from it.", $encoding));
                self::$authUser = mb_convert_encoding(self::$authUser, "UTF-8", $encoding);
                self::$authPassword = mb_convert_encoding(self::$authPassword, "UTF-8", $encoding);
            }
        }
    }

    /**
     * @access public
     * @return boolean      data sent or not
     */
    static public function HasAuthenticationInfo() {
        return (self::$authUser != "" && self::$authPassword != "");
    }


    /**----------------------------------------------------------------------------------------------------------
     * Getter & Checker
     */

    /**
     * Returns the input stream
     *
     * @access public
     * @return handle/boolean      false if not available
     */
    static public function GetInputStream() {
        if (isset(self::$input))
            return self::$input;
        else
            return false;
    }

    /**
     * Returns the output stream
     *
     * @access public
     * @return handle/boolean      false if not available
     */
    static public function GetOutputStream() {
        if (isset(self::$output))
            return self::$output;
        else
            return false;
    }

    /**
     * Returns the request method
     *
     * @access public
     * @return string
     */
    static public function GetMethod() {
        if (isset(self::$method))
            return self::$method;
        else
            return self::UNKNOWN;
    }

    /**
     * Returns the value of the user parameter of the querystring
     *
     * @access public
     * @return string/boolean       false if not available
     */
    static public function GetGETUser() {
        if (isset(self::$getUser))
            return self::$getUser;
        else
            return self::UNKNOWN;
    }

    /**
     * Returns the value of the ItemId parameter of the querystring
     *
     * @access public
     * @return string/boolean       false if not available
     */
    static public function GetGETItemId() {
        if (isset(self::$itemId))
            return self::$itemId;
        else
            return false;
        }

    /**
     * Returns the value of the CollectionId parameter of the querystring
     *
     * @access public
     * @return string/boolean       false if not available
     */
    static public function GetGETCollectionId() {
        if (isset(self::$collectionId))
            return self::$collectionId;
        else
            return false;
    }

    /**
     * Returns if the SaveInSent parameter of the querystring is set
     *
     * @access public
     * @return boolean
     */
    static public function GetGETSaveInSent() {
        if (isset(self::$saveInSent))
            return self::$saveInSent;
        else
            return true;
    }

    /**
    * Returns if the AcceptMultipart parameter of the querystring is set
    *
    * @access public
    * @return boolean
    */
    static public function GetGETAcceptMultipart() {
        if (isset(self::$acceptMultipart))
            return self::$acceptMultipart;
        else
            return false;
    }

    /**
     * Returns the value of the AttachmentName parameter of the querystring
     *
     * @access public
     * @return string/boolean       false if not available
     */
    static public function GetGETAttachmentName() {
        if (isset(self::$attachmentName))
            return self::$attachmentName;
        else
            return false;
    }

    /**
     * Returns the authenticated user
     *
     * @access public
     * @return string/boolean       false if not available
     */
    static public function GetAuthUser() {
        if (isset(self::$authUser))
            return self::$authUser;
        else
            return false;
    }

    /**
     * Returns the authenticated domain for the user
     *
     * @access public
     * @return string/boolean       false if not available
     */
    static public function GetAuthDomain() {
        if (isset(self::$authDomain))
            return self::$authDomain;
        else
            return false;
    }

    /**
     * Returns the transmitted password
     *
     * @access public
     * @return string/boolean       false if not available
     */
    static public function GetAuthPassword() {
        if (isset(self::$authPassword))
            return self::$authPassword;
        else
            return false;
    }

    /**
     * Returns the RemoteAddress
     *
     * @access public
     * @return string
     */
    static public function GetRemoteAddr() {
        if (isset(self::$remoteAddr))
            return self::$remoteAddr;
        else
            return "UNKNOWN";
    }

    /**
     * Returns the command to be executed
     *
     * @access public
     * @return string/boolean       false if not available
     */
    static public function GetCommand() {
        if (isset(self::$command))
            return self::$command;
        else
            return false;
    }

    /**
     * Returns the command code which is being executed
     *
     * @access public
     * @return string/boolean       false if not available
     */
    static public function GetCommandCode() {
        if (isset(self::$command))
            return Utils::GetCodeFromCommand(self::$command);
        else
            return false;
    }

    /**
     * Returns the device id transmitted
     *
     * @access public
     * @return string/boolean       false if not available
     */
    static public function GetDeviceID() {
        if (isset(self::$devid))
            return self::$devid;
        else
            return false;
    }

    /**
     * Returns the device type if transmitted
     *
     * @access public
     * @return string/boolean       false if not available
     */
    static public function GetDeviceType() {
        if (isset(self::$devtype))
            return self::$devtype;
        else
            return false;
    }

    /**
     * Returns the value of supported AS protocol from the headers
     *
     * @access public
     * @return string/boolean       false if not available
     */
    static public function GetProtocolVersion() {
        if (isset(self::$asProtocolVersion))
            return self::$asProtocolVersion;
        else
            return false;
    }

    /**
     * Returns the user agent sent in the headers
     *
     * @access public
     * @return string/boolean       false if not available
     */
    static public function GetUserAgent() {
        if (isset(self::$useragent))
            return self::$useragent;
        else
            return self::UNKNOWN;
    }

    /**
     * Returns policy key sent by the device
     *
     * @access public
     * @return int/boolean       false if not available
     */
    static public function GetPolicyKey() {
        if (isset(self::$policykey))
            return self::$policykey;
        else
            return false;
    }

    /**
     * Indicates if a policy key was sent by the device
     *
     * @access public
     * @return boolean
     */
    static public function WasPolicyKeySent() {
        return isset(self::$headers["x-ms-policykey"]);
    }

    /**
     * Indicates if Z-Push was called with a POST request
     *
     * @access public
     * @return boolean
     */
    static public function IsMethodPOST() {
        return (self::$method == "POST");
    }

    /**
     * Indicates if Z-Push was called with a GET request
     *
     * @access public
     * @return boolean
     */
    static public function IsMethodGET() {
        return (self::$method == "GET");
    }

    /**
     * Indicates if Z-Push was called with a OPTIONS request
     *
     * @access public
     * @return boolean
     */
    static public function IsMethodOPTIONS() {
        return (self::$method == "OPTIONS");
    }

    /**
     * Sometimes strange device ids are sumbitted
     * No device information should be saved when this happens
     *
     * @access public
     * @return boolean       false if invalid
     */
    static public function IsValidDeviceID() {
        if (self::GetDeviceID() === "validate" || self::GetDeviceID() === "webservice")
            return false;
        else
            return true;
    }

    /**
     * Returns the amount of data sent in this request (from the headers)
     *
     * @access public
     * @return int
     */
    static public function GetContentLength() {
        return (isset(self::$headers["content-length"]))? (int) self::$headers["content-length"] : 0;
    }

    /**
     * Returns the amount of seconds this request is able to be kept open without the client
     * closing it. This depends on the vendor.
     *
     * @access public
     * @return boolean
     */
    static public function GetExpectedConnectionTimeout() {
        // Different vendors implement different connection timeouts.
        // In order to optimize processing, we return a specific time for the major
        // classes currently known (feedback welcome).
        // The amount of time returned is somehow lower than the max timeout so we have
        // time for processing.

        if (!isset(self::$expectedConnectionTimeout)) {
            // Apple and Windows Phone have higher timeouts (4min = 240sec)
            if (stripos(SYNC_TIMEOUT_LONG_DEVICETYPES, self::GetDeviceType()) !== false) {
                self::$expectedConnectionTimeout = 210;
            }
            // Samsung devices have a intermediate timeout (90sec)
            else if (stripos(SYNC_TIMEOUT_MEDIUM_DEVICETYPES, self::GetDeviceType()) !== false) {
                self::$expectedConnectionTimeout = 85;
            }
            else {
                // for all other devices, a timeout of 30 seconds is expected
                self::$expectedConnectionTimeout = 28;
            }
        }
        return self::$expectedConnectionTimeout;
    }

    /**
     * Indicates if the maximum timeout for the devicetype of this request is
     * almost reached.
     *
     * @access public
     * @return boolean
     */
    static public function IsRequestTimeoutReached() {
        return (time() - $_SERVER["REQUEST_TIME"]) >= self::GetExpectedConnectionTimeout();
    }

    /**
     * Checks the device type if it expects the globalobjid in meeting requests encoded as hex.
     * If it's not the case, globalobjid will be base64 encoded.
     *
     * WindowsOutlook and iOS device since 9.3 (?) version expect globalobjid to be hex encoded.
     * @see https://jira.z-hub.io/projects/ZP/issues/ZP-1013
     *
     * @access public
     * @return boolean
     */
    static public function IsGlobalObjIdHexClient() {
        switch (self::GetDeviceType()) {
            case "WindowsOutlook":
                ZLog::Write(LOGLEVEL_DEBUG, "Request->IsGlobalObjIdHexClient(): WindowsOutlook");
                return true;
            case "iPod":
            case "iPad":
            case "iPhone":
                $matches = array();
                if (preg_match("/^Apple-.*?\/(\d{4})\./", self::GetUserAgent(), $matches) && isset($matches[1]) && $matches[1] >= 1305) {
                    ZLog::Write(LOGLEVEL_DEBUG, sprintf("Request->IsGlobalObjIdHexClient(): %s->%s", self::GetDeviceType(), self::GetUserAgent()));
                    return true;
                }
        }
        return false;
    }
    /**----------------------------------------------------------------------------------------------------------
     * Private stuff
     */

    /**
     * Replaces all not allowed characters in a string
     *
     * @param string    $input          the input string
     * @param int       $filter         one of the predefined filters: LETTERS_ONLY, HEX_ONLY, WORDCHAR_ONLY, NUMBERS_ONLY, NUMBERSDOT_ONLY
     * @param char      $replacevalue   (opt) a character the filtered characters should be replaced with
     *
     * @access public
     * @return string
     */
    static private function filterEvilInput($input, $filter, $replacevalue = '') {
        $re = false;
        if ($filter == self::LETTERS_ONLY)            $re = "/[^A-Za-z]/";
        else if ($filter == self::HEX_ONLY)           $re = "/[^A-Fa-f0-9]/";
        else if ($filter == self::WORDCHAR_ONLY)      $re = "/[^A-Za-z0-9]/";
        else if ($filter == self::NUMBERS_ONLY)       $re = "/[^0-9]/";
        else if ($filter == self::NUMBERSDOT_ONLY)    $re = "/[^0-9\.]/";
        else if ($filter == self::HEX_EXTENDED)       $re = "/[^A-Fa-f0-9\:]/";
        else if ($filter == self::ISO8601)            $re = "/[^\d{8}T\d{6}Z]/";

        return ($re) ? preg_replace($re, $replacevalue, $input) : '';
    }

    /**
     * Returns base64 encoded "php://input"
     * With POST request (our case), you can open and read
     * multiple times "php://input"
     *
     * @access public
     * @return string - base64 encoded wbxml
     */
    public static function GetInputAsBase64() {
        $input = fopen('php://input', 'r');
        $wbxml = base64_encode(stream_get_contents($input));
        fclose($input);
        return $wbxml;
    }

    /**
     * Indicates if the request contained the KOE stats header.
     *
     * @access public
     * @return boolean
     */
    static public function HasKoeStats() {
        return isset(self::$koeVersion) && isset(self::$koeBuild) && isset(self::$koeBuildDate);
    }

    /**
     * Returns the version number of the KOE informed by the stats header.
     *
     * @access public
     * @return string
     */
    static public function GetKoeVersion() {
        if (isset(self::$koeVersion))
            return self::$koeVersion;
        else
            return self::UNKNOWN;
    }

    /**
     * Returns the build of the KOE informed by the stats header.
     *
     * @access public
     * @return string
     */
    static public function GetKoeBuild() {
        if (isset(self::$koeBuild))
            return self::$koeBuild;
        else
            return self::UNKNOWN;
    }

    /**
     * Returns the build date of the KOE informed by the stats header.
     *
     * @access public
     * @return string
     */
    static public function GetKoeBuildDate() {
        if (isset(self::$koeBuildDate))
            return self::$koeBuildDate;
        else
            return self::UNKNOWN;
    }

    /**
     * Returns whether it is an Outlook client.
     *
     * @access public
     * @return boolean
     */
    static public function IsOutlook() {
        return (self::GetDeviceType() == "WindowsOutlook");
    }
}

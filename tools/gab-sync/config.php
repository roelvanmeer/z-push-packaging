<?php
/***********************************************
* File      :   config.php
* Project   :   Z-Push - tools - GAB sync
* Descr     :   Configuration file.
*
* Created   :   28.01.2016
*
* Copyright 2016 Zarafa Deutschland GmbH
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
* ************************************************/

// The field to be hashed that is unique and never changes
// in the entire lifetime of the GAB entry.
define('HASHFIELD', 'account');
define('AMOUNT_OF_CHUNKS', 10);

// SyncWorker implementation to be used
define('SYNCWORKER', 'Kopano');

// Unique id to find a contact from the GAB (value to be supplied by -u on the command line)
// Zarafa supports: 'account' and 'smtpAddress' (email)
define('UNIQUEID', 'account');

// Server connection settings
// Depending on your setup, it might be advisable to change the lines below to one defined with your
// default socket location.
// Normally "default:" points to the default setting ("file:///var/run/kopano/server.sock")
// Examples: define("SERVER", "default:");
//           define("SERVER", "http://localhost:236/kopano");
//           define("SERVER", "https://localhost:237/kopano");
//           define("SERVER", "file:///var/run/kopano/server.sock");
// If you are using ZCP >= 7.2.0, set it to the zarafa location, e.g.
//           define("SERVER", "http://localhost:236/zarafa");
//           define("SERVER", "https://localhost:237/zarafa");
//           define("SERVER", "file:///var/run/zarafad/server.sock");
// For ZCP versions prior to 7.2.0 the socket location is different (http(s) sockets are the same):
//           define("SERVER", "file:///var/run/zarafa");

define('SERVER', 'default:');

define('USERNAME', '');
define('PASSWORD', '');
define('CERTIFICATE', null);
define('CERTIFICATE_PASSWORD', null);

// Store where the hidden folder is located.
// For the public folder, use SYSTEM
// to use another store, use the same as USERNAME
// or another store where USERNAME has full access to.
define('HIDDEN_FOLDERSTORE', 'SYSTEM');

/// Do not change (unless you know exactly what you do)
define('HIDDEN_FOLDERNAME', 'Z-Push-KOE-GAB');

<?php

/**
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
require_once dirname(__DIR__).'/vendor/autoload.php';

use fkooman\Ini\IniReader;
use fkooman\Rest\Plugin\Authentication\Bearer\BearerAuthentication;
use fkooman\RemoteStorage\DbTokenValidator;
use fkooman\RemoteStorage\RemoteStorage;
use fkooman\RemoteStorage\RemoteStorageResourceServer;
use fkooman\RemoteStorage\RemoteStorageService;
use fkooman\RemoteStorage\MetadataStorage;
use fkooman\RemoteStorage\DocumentStorage;
use fkooman\Tpl\Twig\TwigTemplateManager;
use fkooman\OAuth\OAuthServer;
use fkooman\OAuth\Storage\UnregisteredClientStorage;
use fkooman\OAuth\Storage\PdoCodeTokenStorage;
use fkooman\Rest\Plugin\Authentication\Form\FormAuthentication;
use fkooman\Http\Request;

$iniReader = IniReader::fromFile(
    dirname(__DIR__).'/config/server.ini'
);

$db = new PDO(
    $iniReader->v('Db', 'dsn'),
    $iniReader->v('Db', 'username', false),
    $iniReader->v('Db', 'password', false)
);

$templateManager = new TwigTemplateManager(
    array(
        dirname(__DIR__).'/views',
        dirname(__DIR__).'/config/views',
    ),
    null
);

$pdoCodeTokenStorage = new PdoCodeTokenStorage($db);

$server = new OAuthServer(
    $templateManager,
    new UnregisteredClientStorage(),    // we do not have client registration
    new RemoteStorageResourceServer(),  // we only have one resource server
    $pdoCodeTokenStorage,               // we do not have codes, only...
    $pdoCodeTokenStorage                // ...tokens
);

$md = new MetadataStorage($db);
$document = new DocumentStorage(
    $iniReader->v('storageDir')
);
$remoteStorage = new RemoteStorage($md, $document);

$userAuth = new FormAuthentication(
    function ($userId) use ($iniReader) {
        $userList = $iniReader->v('Users');
        if (!array_key_exists($userId, $userList)) {
            return false;
        }

        return $userList[$userId];
    },
    $templateManager,
    array('realm' => 'OAuth AS')
);

$apiAuth = new BearerAuthentication(
    new DbTokenValidator($db),
    array(
        'realm' => 'remoteStorage API',
    )
);

$service = new RemoteStorageService(
    $templateManager,
    $server,
    $remoteStorage,
    $userAuth,
    $apiAuth,
    array(
        'disable_token_endpoint',
        'disable_introspect_endpoint',
    )
);

$request = new Request($_SERVER);
$service->run($request)->send();

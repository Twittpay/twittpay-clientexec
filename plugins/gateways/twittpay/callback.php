<?php

/**
 * TwittPay - callback entry point
 *
 * The gateway and the returning customer both land here. It boots Clientexec and
 * hands the request to PlugintwittpayCallback::processCallback().
 */

$_GET['fuse']   = 'billing';
$_GET['action'] = 'gatewaycallback';
$_GET['plugin'] = 'twittpay';

chdir('../../..');

require_once dirname(__FILE__) . '/../../../library/front.php';

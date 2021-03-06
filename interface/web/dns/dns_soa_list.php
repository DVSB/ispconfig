<?php
require_once('../../lib/config.inc.php');
require_once('../../lib/app.inc.php');

/******************************************
* Begin Form configuration
******************************************/

$list_def_file = "list/dns_soa.list.php";

/******************************************
* End Form configuration
******************************************/

//* Check permissions for module
$app->auth->check_module_permissions('dns');

$app->uses('listform_actions');
// $app->listform_actions->SQLExtWhere = "dns_soa.access = 'REJECT'";

$app->listform_actions->SQLOrderBy = 'ORDER BY dns_soa.origin';
$app->listform_actions->onLoad();


?>
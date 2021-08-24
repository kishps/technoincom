<?php
define('PUBLIC_AJAX_MODE', true);
define('STOP_STATISTICS', true);
define('NO_AGENT_CHECK', true);

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/local/componets/table/report_class.php');
header('Content-Type: application/json');

?>
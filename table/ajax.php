<?php
define('PUBLIC_AJAX_MODE', true);
define('STOP_STATISTICS', true);
define('NO_AGENT_CHECK', true);

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');
require($_SERVER['DOCUMENT_ROOT'] . '/local/components/sp_csn/table/report_class.php');
header('Content-Type: application/json');

switch ($_REQUEST['action']) {
    case 'getTasks':
        echo json_encode(\CSN\Tasks::getTasks($_REQUEST['filter']));
    break;
    default:
        echo json_encode('bad request');
    break;        
}

<?php
define('PUBLIC_AJAX_MODE', true);
define('STOP_STATISTICS', true);
define('NO_AGENT_CHECK', true);

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

header('Content-Type: application/json');
$date_from  = $_REQUEST['F_DATE_FROM'];
$date_to = $_REQUEST['F_DATE_TO'];

$data = \CSN\Tasks::getData( ['F_DATE_FROM'=> $date_from,'F_DATE_TO'=> $date_to]);

echo json_encode($data);
?>


<?/*$APPLICATION->IncludeComponent(
    'sp_csn:report_fact_otgruz',
    '',
    []
);*/?>



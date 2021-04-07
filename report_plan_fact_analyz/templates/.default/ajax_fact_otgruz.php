<?php
define('PUBLIC_AJAX_MODE', true);
define('STOP_STATISTICS', true);
define('NO_AGENT_CHECK', true);

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

header('Content-Type: application/json');

?>

<?$APPLICATION->IncludeComponent(
    'sp_csn:report_fact_otgruz',
    '',
    []
);?>



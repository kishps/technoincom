<? require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

if (!$USER->IsAdmin()) {
    echo 'Недостаточно прав';
    exit;
}

$APPLICATION->IncludeComponent(
    'sp_csn:report_products_group',
    '',
    [
        'flagTest' => true,
    ]
);

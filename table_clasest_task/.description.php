<?
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

use Bitrix\Main\Localization\Loc as Loc;

Loc::loadMessages(__FILE__);

$arComponentDescription = array(
	"NAME" => "Отчет",
	"DESCRIPTION" => "Построение отчета по задачам {ЗАКРЫВАЕМ ЗАДАЧУ ТОЛЬКО ПОСЛЕ ПОЛУЧЕНИЯ ЗАКАЗА ПО РАСЧЕТУ}",
	"ICON" => '/images/icon.gif',
	"SORT" => 20,
	"PATH" => array(
		"ID" => 'mscoder',
		"NAME" => Loc::getMessage('LOCATION_GROUP'),
		"SORT" => 10,
		"CHILD" => array(
			"ID" => 'standard',
			"NAME" => Loc::getMessage('LOCATION_DIR'),
			"SORT" => 10
		)
	),
);

?>
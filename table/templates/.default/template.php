<? if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die(); ?>
<?

use Bitrix\Main\Localization\Loc as Loc;

Loc::loadMessages(__FILE__);
$this->setFrameMode(true);
CJSCore::Init(array("jquery2", "amcharts4_theme_animated", "amcharts4", "amcharts4_maps"));
?>
<style type="text/css">
	.start {
		background-color: #c5ff9d;
	}
</style>

<div id="report"></div>

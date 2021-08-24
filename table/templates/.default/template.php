<? if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die(); ?>
<?

use Bitrix\Main\Localization\Loc as Loc;
Loc::loadMessages(__FILE__);
$this->setFrameMode(true);
CJSCore::Init(array("jquery2", "amcharts4_theme_animated", "amcharts4", "amcharts4_maps"));
?>


<div id="report"></div>

<script>
BX.ajax.get(		//загружем при помощи AJAX готовую верстку анкет детей
						"/local/components/sp_csn/table/ajax.php", //адрес 
						{
							sessid: BX.bitrix_sessid(),  //отправляем id сессии
								//отправляем список id
						},
						function (response) { //при удачном AJAX запросе...
							$("#report").html(response);
						}
					);

</script>
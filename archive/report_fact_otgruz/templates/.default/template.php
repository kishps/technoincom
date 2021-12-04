
<?php
define('PUBLIC_AJAX_MODE', true);
define('STOP_STATISTICS', true);
define('NO_AGENT_CHECK', true);

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

header('Content-Type: application/json');

?>


	<? if (isset($arResult['DATA'])) : ?>
		<?= json_encode($arResult) ?>
	<? endif ?>

    <?/* if ($arResult['ERROR_MSG']) : ?>
            <? foreach ($arResult['ERROR_MSG'] as $value) : ?>
                <?= $value ?>
            <? endforeach ?>
    <? endif */?>


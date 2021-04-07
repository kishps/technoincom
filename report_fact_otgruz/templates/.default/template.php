



	<?if (isset($arResult['DATA'])): ?>
		<?=json_encode($arResult)?>
	<?endif ?>

    <?if ($arResult['ERROR_MSG']): ?>
            <?foreach ($arResult['ERROR_MSG'] as $value): ?>
                <?= $value ?>
            <?endforeach ?>
    <?endif ?>


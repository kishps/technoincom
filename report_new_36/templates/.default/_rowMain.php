<?if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();?>

<?foreach ($arResult['DATA_TITLES'] as $key_items => $valueNotUsed): ?>
    <td>
        <?if (!$value['items'][ $key_items ]['count']): ?>
            <?// Нет данных для показателя $key_items на дату $key_time ?>
            <div>-</div>
        <?else: ?>
            <?if ($key_items == 'f_prihod_ds' || $key_items == 'q_prihod_ds_whithNDS' || $key_items == 'c_production_summ' || $key_items == 'a_orders_shipped_summ'|| $key_items == 'g_summ_for_deal' || $key_items == 'e_planned_shipments'): ?>
            <?//SP_Log::consoleLog($arResult['DATA'], 'TEST');?>
                <?// Сумма ?>
                <div><?= ($value['items'][ $key_items ]['price']) ? number_format($value['items'][ $key_items ]['price'], 2, ',', '&nbsp;') : '*' ?></div>
            <?else: ?>
                <?// Количество ?>
                <div><?= $value['items'][ $key_items ]['count'] ?></div>
            <?endif ?>
        <?endif ?>

        <?if (false and $value[ $key_2 ]['items']): ?>
            <div class="detail">
                <?foreach ($value[ $key_2 ]['items'] as $value_3): ?>
                <?//SP_Log::consoleLog($value_3, 'TEST');?>
                    <?
                        $price = (float) trim($value_3['PRICE']);
                        $price = ($price) ? number_format($price, 2, ',', '&nbsp;') : '*';

                        $code = trim($value_3['CODE']);
                        if (!strlen($code)) {
                            $code = '***';
                        }
                    ?>
                    <div><?= $price ?>&nbsp;руб<br><a href="<?= $value_3['LINK'] ?>" target="_blank"><?= $code ?></a></div>
                <?endforeach ?>
            </div>
        <?endif ?>
    </td>
<?endforeach ?>

<?if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

CJSCore::Init(array("jquery"));
//SP_Log::consoleLog($arResult['DATA'], '$arResult');

// ###########################

// см. bitrix\components\bitrix\report.view\templates\.default\template.php

$F_DATE_TYPE = $arResult['FILTER']['F_DATE_TYPE'];

$classFilterDate = '';
$ar = [
    'interval' => 'filter-date-interval-after filter-date-interval-before',
    'before'   => 'filter-date-interval-before',
    'after'    => 'filter-date-interval-after',
];
if (in_array($F_DATE_TYPE, $ar)) {
    $classFilterDate = $ar[ $F_DATE_TYPE ];
}

$this->addExternalJS('/local/vendor/jquery/jquery-3.4.1.min.js');

?>
  
<? $this->SetViewTarget('sidebar_tools_1', 100); ?>

<div class="sidebar-block">
    <div class="sidebar-block-inner">
        <div class="filter-block-title report-filter-block-title">
            Фильтр
        </div>

        <form>
            <div class="filter-block filter-field-date-combobox filter-field-date-combobox-interval">

                <?// ### period ( ?>
                <div class="filter-field">

                    <label for="task-interval-filter" class="filter-field-title">Отчет за период</label>
                    <select class="filter-dropdown" style="margin-bottom: 0;" onchange="OnTaskIntervalChange(this)" id="task-interval-filter" name="F_DATE_TYPE">
                        <?foreach ($arResult['PERIODS'] as $key => $value): ?>
                            <option value="<?= $key ?>" <?= ($key == $F_DATE_TYPE) ? 'selected' : ''?>><?= $value ?></option>
                        <?endforeach ?>
                    </select>

                    <span class="filter-date-interval <?= $classFilterDate ?>">
                        <span class="filter-date-interval-from">
                            <input
                                type="text"
                                class="filter-date-interval-from"
                                name="F_DATE_FROM"
                                id="REPORT_INTERVAL_F_DATE_FROM"
                                value="<?= $arResult['FILTER']['F_DATE_FROM'] ?>"
                            />
                            <a class="filter-date-interval-calendar" href="" title="Выбрать дату в календаре" id="filter-date-interval-calendar-from">
                            <img border="0" src="/bitrix/js/main/core/images/calendar-icon.gif" alt="Выбрать дату в календаре"></a>
                        </span>

                        <span class="filter-date-interval-hellip">&hellip;</span>

                        <span class="filter-date-interval-to">
                            <input
                                type="text"
                                class="filter-date-interval-to"
                                name="F_DATE_TO"
                                id ="REPORT_INTERVAL_F_DATE_TO"
                                value="<?= $arResult['FILTER']['F_DATE_TO'] ?>"
                            />
                            <a href="" class="filter-date-interval-calendar" title="Выбрать дату в календаре" id="filter-date-interval-calendar-to">
                            <img border="0" src="/bitrix/js/main/core/images/calendar-icon.gif" alt="Выбрать дату в календаре"></a>
                        </span>
                    </span>
                    
                    <span class="filter-day-interval <?= ($F_DATE_TYPE == 'days') ? 'filter-day-interval-selected' : '' ?>">
                        <input
                            type="text"
                            size="5"
                            class="filter-date-days"
                            value="<?= $arResult['FILTER']['F_DATE_DAYS'] ?>"
                            name="F_DATE_DAYS"
                        />
                        дн.
                    </span>
                </div>
                <?// ### period ) ?>

                <div class="filter-field">
                    <label for="filter-user" class="filter-field-title">Сотрудник</label>
                    <?$F_USER = $arResult['FILTER']['F_USER']; ?>
                    <select class="filter-dropdown" id="filter-user" name="F_USER">
                        <option value="" <?= (!$F_USER) ? 'selected' : ''?> disabled>Выберите сотрудника</option>
                        <option value="all" <?= ('all' === $F_USER) ? 'selected' : ''?>>Все сотрудники отдела</option>
                        <?foreach ($arResult['USERS'] as $key => $value): ?>
                            <option value="<?= $key ?>" <?= ($key == $F_USER) ? 'selected' : ''?>><?= $value['NAME'] ?> <?= $value['LAST_NAME'] ?></option>
                        <?endforeach ?>
                    </select>
                </div>

                

                    <div class="filter-field">
                        <label class="filter-field-title">Какие столбцы показывать</label>
                        
                        	<div class="fakeSelect_item_all">
                                <input type="checkbox" name="param_filter_forTable[]" checked="true" value="all" id="12">
                                <label for="12" style="cursor: pointer;">Показывать все</label>
                            </div>
                            <div class="fakeSelect_item">
                                <input type="checkbox" name="param_filter_forTable[]" value="call_outgoing" id="1"
                                    <? if (in_array('Звонки исходящие', $arResult['DATA_TITLES'])) {
                                        echo "checked='true'";
                                    } ?>
                                >
                                <label for="1" style="cursor: pointer;">Звонки исходящие</label>
                            </div>
                            <div class="fakeSelect_item">
                                <input type="checkbox" name="param_filter_forTable[]" value="call_outgoing_2min" id="2" <? if (in_array('Звонки исходящие (Более 2х мин)', $arResult['DATA_TITLES'])) {
                                        echo "checked='true'";
                                    } ?>>
                                <label for="2" style="cursor: pointer;">Звонки исходящие (Более 2х мин)</label>
                            </div>
                            <div class="fakeSelect_item">
                                <input type="checkbox" name="param_filter_forTable[]" value="email_sent" id="3"<? if (in_array('E-mail отправленные', $arResult['DATA_TITLES'])) {
                                        echo "checked='true'";
                                    } ?>>
                                <label for="3" style="cursor: pointer;">E-mail отправленные</label>
                            </div>
                            <div class="fakeSelect_item">
                                <input type="checkbox" name="param_filter_forTable[]" value="conversion_company" id="4"<? if (in_array('Конверсия Компаний из ПКБ в АКБ', $arResult['DATA_TITLES'])) {
                                        echo "checked='true'";
                                    } ?>>
                                <label for="4" style="cursor: pointer;">Конверсия Компаний из ПКБ в АКБ</label>
                            </div>
                            <div class="fakeSelect_item">
                                <input type="checkbox" name="param_filter_forTable[]" value="calc_deal" id="5"<? if (in_array('Открыто Сделок в расчет ПТО', $arResult['DATA_TITLES'])) {
                                        echo "checked='true'";
                                    } ?>>
                                <label for="5" style="cursor: pointer;">Открыто Сделок в расчет ПТО</label>
                            </div>
                            <div class="fakeSelect_item">
                                <input type="checkbox" name="param_filter_forTable[]" value="deal_failed" id="6"<? if (in_array('Забраковано Сделок', $arResult['DATA_TITLES'])) {
                                        echo "checked='true'";
                                    } ?>>
                                <label for="6" style="cursor: pointer;">Забраковано Сделок</label>
                            </div>
                            <div class="fakeSelect_item">
                                <input type="checkbox" name="param_filter_forTable[]" value="product_testing" id="7"<? if (in_array('Открыто Испытаний', $arResult['DATA_TITLES'])) {
                                        echo "checked='true'";
                                    } ?>>
                                <label for="7" style="cursor: pointer;">Открыто Испытаний</label>
                            </div>
                            <div class="fakeSelect_item">
                                <input type="checkbox" name="param_filter_forTable[]" value="product_testing_complete" id="8"<? if (in_array('Завершено Испытаний', $arResult['DATA_TITLES'])) {
                                        echo "checked='true'";
                                    } ?>>
                                <label for="8" style="cursor: pointer;">Завершено Испытаний</label>
                            </div>
                            <div class="fakeSelect_item">
                                <input type="checkbox" name="param_filter_forTable[]" value="production" id="9"<? if (in_array('Запущен БП Производства Заказа', $arResult['DATA_TITLES'])) {
                                        echo "checked='true'";
                                    } ?>>
                                <label for="9" style="cursor: pointer;">Запущен БП Производства Заказа</label>
                            </div>
                            <div class="fakeSelect_item">
                                <input type="checkbox" name="param_filter_forTable[]" value="orders_shipped" id="10"<? if (in_array('Отгружено Заказов', $arResult['DATA_TITLES'])) {
                                        echo "checked='true'";
                                    } ?>>
                                <label for="10" style="cursor: pointer;">Отгружено Заказов</label>
                            </div>
                            <div class="fakeSelect_item">
                                <input type="checkbox" name="param_filter_forTable[]" value="prihod_ds" id="11"<? if (in_array('Приход ДС (руб без НДС)', $arResult['DATA_TITLES'])) {
                                        echo "checked='true'";
                                    } ?>>
                                <label for="11" style="cursor: pointer;">Приход ДС (руб без НДС)</label>
                            </div>
                            <div class="fakeSelect_item">
                                <input type="checkbox" name="param_filter_forTable[]" value="prihod_ds_whithNDS" id="12"<? if (in_array('Приход ДС (руб с НДС)', $arResult['DATA_TITLES'])) {
                                        echo "checked='true'";
                                    } ?>>
                                <label for="12" style="cursor: pointer;">Приход ДС (руб с НДС)</label>
                            </div>
                            <div class="fakeSelect_item">
                                <input type="checkbox" name="param_filter_forTable[]" value="summ_for_deal" id="13"<? if (in_array('Ожидаемые поступления без НДС', $arResult['DATA_TITLES'])) {
                                        echo "checked='true'";
                                    } ?>>
                                <label for="13" style="cursor: pointer;">Ожидаемые поступления без НДС</label>
                            </div>
 							<div class="fakeSelect_item">
                                <input type="checkbox" name="param_filter_forTable[]" value="planned_shipments" id="14"<? if (in_array('Планируемые отгрузки', $arResult['DATA_TITLES'])) {
                                        echo "checked='true'";
                                    } ?>>
                                <label for="14" style="cursor: pointer;">Планируемые отгрузки</label>
                            </div>
                       
                    </div>
    

                <div class="filter-field-buttons">
                    <input type="submit" value="Применить" class="filter-submit" name="F_SET_FILTER">
                    <input type="submit" value="Экспорт" class="filter-submit" name="EXPORT_TO_XLS">
                </div>

            </div><?// filter-block ?>
        </form>
    </div><?// sidebar-block-inner?>
</div><?// sidebar-block ?>

<? $this->EndViewTarget(); ?>

<div class="sp-report">
    <?if ($arResult['ERROR_MSG']): ?>
        <div class="m-error">
            <?foreach ($arResult['ERROR_MSG'] as $value): ?>
                <div><?= $value ?></div>
            <?endforeach ?>
        </div>
    <?endif ?>

    <?if (isset($arResult['DATA'])): ?>
        <?/*
            if ($F_USER !== 'all') {
                $value    = $arResult['USERS'][ $F_USER ];
                $USER_STR = "{$value['NAME']} {$value['LAST_NAME']}";
            } else {
                $USER_STR = 'Все сотрудники отдела';
            }
        */?>
        <div class="m-header">Период: <?= $arResult['PERIOD_STR'] ?></div>
        <div class="m-header">Сотрудник: <?= $arResult['USER_STR'] ?></div>

        <div style="text-align: right">
            <?if ($arResult['DATA']): ?>
                <button class="js-btn-detail-unfold">Развернуть все</button>
                <button class="js-btn-detail-fold">Свернуть все</button>
            <?endif ?>
            <button class="js-btn-filter-hide">Скрыть фильтр</button>
            <button class="js-btn-filter-show" hidden>Показать фильтр</button>
        </div>
        <br>

        <?if (!$arResult['DATA']): ?>
            <div>Нет данных</div>
            
        <?else:?>
            <div class="m-data">
                <?if (0): ?>
                    <table>
                        <tr>
                            <th>Дата</th>
                            <?foreach ($arResult['DATA'] as $key => $value): ?>
                                <td><?= date('d.m.y', $key) ?></td>
                            <?endforeach ?>
                        </tr>
                        <?foreach ($arResult['DATA_TITLES'] as $key => $value): ?>
                            <tr>
                                <th><?= $value ?></th>
                                <?foreach ($arResult['DATA'] as $value_2): ?>
                                    <td><?= ($value_2[ $key ]) ? $value_2[ $key ] : '-' ?></td>
                                <?endforeach ?>
                            </tr>
                        <?endforeach ?>
                    </table>
                <?else: ?>
                    <table class="m-date-left">
                        <tr>
                            <th>Дата</th>
                            <?foreach ($arResult['DATA_TITLES'] as $key => $value): ?>
                                <?// Названия показателей ?>
                                <th><?= $value ?></th>
                            <?endforeach ?>
                        </tr>

                        <tr class="row-total">
                            <td>ИТОГО</td>
                            <?
                                $value = $arResult['DATA']['total'];
                                
                                require __dir__ .'/_rowMain.php';
                            ?>
                        </tr>

                        <?// $result[ time ]['items']['call_outgoing']['count'] ?>
                        <?foreach ($arResult['DATA'] as $key_time => $value): ?>
                            <?
                                if ($key_time == 'total') {
                                    continue;
                                } 
                            ?>
                            <tr class="row-main" data-date="<?= date('d.m.Y', $key_time) ?>"><?// data-date для "скрыть/показать детализацию" ?>
                                <td><?= date('d.m.Y', $key_time) ?></td>

                                <? require __dir__ .'/_rowMain.php' ?>
                            </tr>

                            <?// ### Детализация по каждой сделке ( ?>
                            <?/*
                                $result[ time ]['detail'][ deal_id ]['call_outgoing']['count']
                                $result[ time ]['detail'][ deal_id ]['product_testing']['items'][ element_id ]['title']
                            */?>
                            <?foreach ($value['detail'] as $key_deal_id => $value_2): // ['call_outgoing']['count'] ?>
                                <tr class="row-detail" data-date="<?= date('d.m.Y', $key_time) ?>">
                                    <td>
                                        <?// Сделка (название, ссылка) ?>
                                        <?if ($key_deal_id): ?>
                                            <a href="/crm/deal/details/<?=$key_deal_id ?>/" target="_blank"><?= $arResult['DEAL_INFO'][ $key_deal_id ]['title'] ?></a>
                                        <?else:?>
                                            Сделка не указана
                                        <?endif ?>
                                    </td>

                                    <?foreach ($arResult['DATA_TITLES'] as $key_items => $valueNotUsed): ?>
                                        <td>
                                        	<?//SP_Log::consoleLog($arResult['DATA_TITLES'], 'TEST');?>
                                            <?if (!$value_2[ $key_items ]['count']): ?>
                                                <?// Для данного показателя нет данных для сделки $key_deal_id в день $key_time ?>
                                                <div>-</div>
                                            <?else: ?>
                                                <?if (!isset($value_2[ $key_items ]['items'])): ?>
                                                    <?// Только количество (звонки, e-mail, ...) ?>
                                                    <div><?= $value_2[ $key_items ]['count'] ?></div>
                                                <?else: ?>
                                                    <?// Элементы (название, ссылка, сумма) ?>
                                                    <?foreach ($value_2[ $key_items ]['items'] as $key_element_id_NotUsed => $value_3): // ['title'=>'', 'price'=>0, 'link'=>''] ?>
                                                        <div>
                                                            <?
                                                                $title = trim($value_3['title']);
                                                                $title = (strlen($title)) ? $title : '***';
                                                            ?>
                                                            <?if ($value_3['link']): ?>
                                                            	<?if ($key_items == 'orders_shipped'):?>
	                                                                <a href="<?= $value_3['link'] ?>" target="_blank"><?= $title ?></a>
	                                                                <div>1</div>
	                                                            <?else:?>
	                                                            	<a href="<?= $value_3['link'] ?>" target="_blank"><?= $title ?></a>
	                                                                <div><?= ($value_3['price']) ? number_format($value_3['price'], 2, ',', '&nbsp;') : '*' ?></div>
	                                                            <?endif?>
                                                            <?else: ?>
                                                                <?= $title ?>
                                                            <?endif ?>
                                                        </div>
                                                    <?endforeach ?>
                                                <?endif ?>
                                            <?endif ?>
                                        </td>
                                    <?endforeach ?>
                                </tr>
                            <?endforeach ?>
                            <?// ### Детализация по каждой сделке ) ?>
                        <?endforeach ?>
                    </table>
                <?endif ?>

                <?if (!empty($arResult['DATA_LIMIT'])): ?>
                    <br>
                    <div>(первые <?= $arResult['DATA_LIMIT'] ?> записей)</div>
                    <br>
                <?endif ?>
            </div><?// m-data ?>

            <?if ($arResult['REPORT_ERROR_MSG']): ?>
                <div class="m-error" style="margin-top: 30px">
                    <?foreach ($arResult['REPORT_ERROR_MSG'] as $value): ?>
                        <div><?= $value ?></div>
                    <?endforeach ?>
                </div>
            <?endif ?>

        <?endif ?>

        <div class="footer-info"><i><?= date('H:i:s') ?></i></div>
    <?endif ?>

</div>

<?if (!empty($_SESSION['SP_flagDebug']) and $arResult['DEBUG_MSG']): ?>
    <div class="block-debug" style="overflow-x: scroll">
        <?foreach ($arResult['DEBUG_MSG'] as $value): ?>
            <div>
                <?if (isset($value['label'])): ?>
                    <div><?= $value['label'] ?>:</div>
                <?endif ?>
                <?if (is_array($value['msg'])): ?>
                    <div><pre><?= print_r($value['msg'], 1) ?></pre></div>
                <?else: ?>
                    <?if (!empty($value['params']['pre'])): ?>
                        <div><pre><?= $value['msg'] ?></pre></div>
                    <?else: ?>
                        <div><?= $value['msg'] ?></div>
                    <?endif ?>
                <?endif ?>
            </div>
        <?endforeach ?>
    </div>
<?endif ?>


<?if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

CUtil::InitJSCore(array('ajax', 'popup'));

$APPLICATION->AddHeadScript('/bitrix/js/crm/crm.js');
$APPLICATION->SetAdditionalCSS('/bitrix/js/crm/css/crm.css');

$this->addExternalJS('/local/vendor/jquery/jquery-3.4.1.min.js');

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
?>

<!-- filter -->
<?$this->SetViewTarget("sidebar_tools_1", 100);?>

<div id="report-chfilter-examples-custom" style="display: none;">

	<div class="filter-field filter-field-company chfilter-field-\Bitrix\Crm\Company" callback="crmCompanySelector">
		<label for="F_CHOICE_COMPANY" class="filter-field-title">Выбор Компании</label>
		<span class="webform-field-textbox-inner">
			<input id="F_CHOICE_COMPANY" type="text" class="webform-field-textbox" caller="true" />
			<input type="hidden" name="F_CHOICE_COMPANY" value="" autocomplete="off"/>
			<a href="" class="webform-field-textbox-clear"></a>
		</span>
	</div>

	<div class="filter-field filter-field-employee chfilter-field-employee chfilter-field-\Bitrix\Main\User" callback="RTFilter_chooseUser">
		<label for="user-email" class="filter-field-title">Ответственный "равно"</label>
		<span class="webform-field-textbox-inner">
			<input id="F_CONTACT" type="text" class="webform-field-textbox" caller="true" autocomplete="off"/>
			<input type="hidden" name="F[CONTACT]" value=""/>
			<a href="" class="webform-field-textbox-clear"></a>
		</span>
	</div>

</div>

<div class="sidebar-block">
    <div class="sidebar-block-inner">
        <div class="filter-block-title report-filter-block-title">
            Фильтр
        </div>
        <form action="" id="report-rewrite-filter">
        	<div class="filter-block filter-field-date-combobox filter-field-date-combobox-interval">
        		<div class="filter-field" id="report-filter-chfilter">

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
        		<div class="filter-field-buttons">
        		    <input type="submit" value="Применить" class="filter-submit" name="F_SET_FILTER">
        		    <input type="submit" value="Экспорт" class="filter-submit" name="EXPORT_TO_XLS">
        		</div>

        	</div>
        </form>
    </div>
</div>
<?$this->EndViewTarget();?>

<div class="sp-report">
	<?if(isset($arResult['PERIOD_STR'])):?>
		<div class="m-header">Период: <?=$arResult['PERIOD_STR'];?></div>
	<?endif;?>
	<?if(isset($arResult['FILTER_COMPANY']['NAME'])):?>
		<div class="m-header">Компания: <?=$arResult['FILTER_COMPANY']['NAME'];?></div>
	<?endif;?>
	<?if(isset($arResult['FILTER_CONTACT']['NAME'])):?>
		<div class="m-header">Сотрудник: <?=$arResult['FILTER_CONTACT']['NAME'];?></div>
	<?endif;?>
	
    <?if ($arResult['ERROR_MSG']): ?>
        <div class="m-error">
            <?foreach ($arResult['ERROR_MSG'] as $value): ?>
                <div><?= $value ?></div>
            <?endforeach ?>
        </div>
    <?endif ?>

	<?if (!$arResult['DATA']): ?>
        <div>Нет данных</div>
    <?else:?>

    <div class="m-data">
    	<table class="m-date-left">
    		<tr>
    			<th>ID</th>
    			<th>Компания</th>
    			<th>Сумма с НДС</th>
    			<th>Сумма без НДС</th>
    			<th>Менеджер</th>
    			<th>Факт. Дата оплаты</th>
    		</tr>
    		<tr class="row-total">
    			<td><?=$arResult['COUNT_ROWS'];?></td>
    			<td></td>
    			<td><?=number_format($arResult["SUMM_ITOGO_S_NDS"], '2', '.', ' ');?></td>
    			<td><?=number_format($arResult["SUMM_ITOGO_BEZ_NDS"], '2', '.', ' ');?></td>
    			<td></td>
    			<td></td>
    		</tr>
    		<?$i = 0;?>
			<?foreach($arResult['DATA'] as $idCell => $cell):?>
				<tr>
					<td><a href="<?=$cell['URL_DEAL']?>" target="_blank"><?=$cell['ID_SDELKA'];?></a></td>
					<td><a href="<?=$cell['URL_COMPANY']?>" target="_blank"><?=$arResult['COMPANY_LIST'][$cell['ID_COMPANY']];?></a></td>
					<td><?=number_format($cell['SUMM_S_NDS'], '2', '.', ' ');?></td>
					<td><?=number_format($cell['SUMM_BEZ_NDS'], '2', '.', ' ');?></td>
					<td><?=$arResult['USER_LIST'][$cell['ID_MANAGER']];?></td>
					<td><?=$cell['DATE'];?></td>
				</tr>
				<?$i++;?>
			<?endforeach;?>
    	</table>
    </div>
    <?endif;?>
</div>

<?

// prepare info
$info = array();

$field = isset($chFilter['field']) ? $chFilter['field'] : null;
// Try to obtain qualified field name (e.g. 'COMPANY_BY.COMPANY_TYPE_BY.STATUS_ID')
$name = isset($chFilter['name']) ? $chFilter['name'] : ($field ? $field->GetName() : '');
$info[] = array(
	'TITLE' => "Компания",
	'COMPARE' => "равно",
	'NAME' => "F_CHOICE_COMPANY",
	'ID' => "F_CHOICE_COMPANY",
	'VALUE' => ['id' => $arResult['FILTER_COMPANY']['ID'], 'name' => $arResult['FILTER_COMPANY']['NAME']],
	'FIELD_NAME' => "COMPANY_BY",
	'FIELD_TYPE' => "\\Bitrix\\Crm\\Company",
	'IS_UF' => false,
	'UF_ID' => '',
	'UF_NAME' => ''
);

$info[] = array(
	'TITLE' => "Ответственный",
	'COMPARE' => "равно",
	'NAME' => "F[CONTACT]",
	'ID' => "F_CONTACT",
	'VALUE' => ['id' => $arResult['FILTER_CONTACT']['ID'], 'name' => $arResult['FILTER_CONTACT']['NAME']],
	'FIELD_NAME' => "COMPANY_BY",
	'FIELD_TYPE' => "\\Bitrix\\Main\\User",
	'IS_UF' => false,
	'UF_ID' => '',
	'UF_NAME' => ''
);

?>

<script type="text/javascript">

	var crmCompanyElements = <? echo CUtil::PhpToJsObject($arResult['COMPANIES']); ?>;
	var crmContactElements = <? echo CUtil::PhpToJsObject($arResult['CONTACTS']); ?>;

	var crmCompanyDialogID = '';
	var crmContactDialogID = '';

	var crmCompanySelector_LAST_CALLER = null;
	var crmContactSelector_LAST_CALLER = null;

	function openCrmEntityDialog(name, typeName, elements, caller, onClose)
	{
		var dlgID = CRM.Set(caller,
			name,
			typeName, //subName for dlgID
			elements,
			false,
			false,
			[typeName],
			{
				'company': '<?=CUtil::JSEscape(GetMessage('CRM_FF_COMPANY'))?>',
				'contact': '<?=CUtil::JSEscape(GetMessage('CRM_FF_CONTACT'))?>',
				'lead': '<?=CUtil::JSEscape(GetMessage('CRM_FF_LEAD'))?>',
				'ok': '<?=CUtil::JSEscape(GetMessage('CRM_FF_OK'))?>',
				'cancel': '<?=CUtil::JSEscape(GetMessage('CRM_FF_CANCEL'))?>',
				'close': '<?=CUtil::JSEscape(GetMessage('CRM_FF_CLOSE'))?>',
				'wait': '<?=CUtil::JSEscape(GetMessage('CRM_FF_SEARCH'))?>',
				'noresult': '<?=CUtil::JSEscape(GetMessage('CRM_FF_NO_RESULT'))?>',
				'add' : '<?=CUtil::JSEscape(GetMessage('CRM_FF_CHOISE'))?>',
				'edit' : '<?=CUtil::JSEscape(GetMessage('CRM_FF_CHANGE'))?>',
				'search' : '<?=CUtil::JSEscape(GetMessage('CRM_FF_SEARCH'))?>',
				'last' : '<?=CUtil::JSEscape(GetMessage('CRM_FF_LAST'))?>'
			},
			true
		);

		var dlg = obCrm[dlgID];
		dlg.AddOnSaveListener(onClose);
		dlg.Open();

		return dlgID;
	}

	function crmCompanySelector(caller)
	{
		crmCompanySelector_LAST_CALLER = caller;
		crmCompanyDialogID =  openCrmEntityDialog('company', 'company', crmCompanyElements, crmCompanySelector_LAST_CALLER, onCrmCompanyDialogClose);
	}

	function onCrmCompanyDialogClose(arElements)
	{
		if(!arElements || typeof(arElements['company']) == 'undefined')
		{
			return;
		}

		var element = arElements['company']['0'];
		
		if(element)
		{
			crmCompanySelectorCatch({ 'id':element['id'], 'name':element['title'] });
		}
		else
		{
			crmCompanySelectorCatch(null);
		}

		obCrm[crmCompanyDialogID].RemoveOnSaveListener(onCrmCompanyDialogClose);
	}

	function crmCompanySelectorCatch(item)
	{
		if(item && BX.type.isNotEmptyString(item['name']))
		{
			crmCompanySelector_LAST_CALLER.value = item['name'] + ' [' + item['id'] + ']';
		}
		else
		{
			crmCompanySelector_LAST_CALLER.value = '';
		}

		var h = BX.findNextSibling(crmCompanySelector_LAST_CALLER, { 'tag':'input', 'attr':{ 'type':'hidden' } });
		h.value = item ? item['id'] : '';
	}

	function crmCompanySelectorClear(e)
	{
		crmCompanySelector_LAST_CALLER = BX.findChild(this.parentNode, { 'tag':'input', 'class':'webform-field-textbox'});

		BX.PreventDefault(e);
		crmCompanySelectorCatch(null);
	}
	function RTFilter_chooseUser(control)
	{
		if (this.parentNode)
		{
			var elem = this;
		}
		else
		{
			var elem = BX.findChild(control, {tag:'input', attr: {type:'text'}}, true);
		}

		singlePopup = BX.PopupWindowManager.create("single-employee-popup-"+Math.random(), elem, {
			offsetTop : 1,
			autoHide : true,
			content : BX("Single_"+elem.id+"_selector_content")
		});

		if (singlePopup.popupContainer.style.display != "block")
		{
			singlePopup.show();
		}

		RTFilter_chooseUser_LAST_CALLER = elem;
		
	}

	function RTFilter_chooseUserCatch(user)
	{
		
		var inp = RTFilter_chooseUser_LAST_CALLER;
		var hid = BX.findNextSibling(inp, {tag:'input',attr:{type:'hidden'}});
		var x = BX.findNextSibling(inp, {tag:'a'});

		hid.value = user.id;

		if (parseInt(user.id) > 0)
		{
			inp.value = user.name;
			x.style.display = 'inline';
		}
		else
		{
			inp.value = '';
			x.style.display = 'none';
		}

		try
		{
			singlePopup.close();
		}
		catch (e) {}
	}

	function RTFilter_chooseUserCatchFix()
	{
		var inp = RTFilter_chooseUser_LAST_CALLER;
		var hid = BX.findNextSibling(inp, {tag:'input',attr:{type:'hidden'}});

		if (inp.value.length < 1 && parseInt(hid.value) > 0)
		{
			var fobj = window['O_Single_' + inp.id];
			inp.value = fobj.arSelected[hid.value].name;
		}
	}
	function RTFilter_chooseUserClear(e)
	{
		RTFilter_chooseUser_LAST_CALLER = BX.findChild(this.parentNode, {tag:'input',attr:{type:'text'}});

		BX.PreventDefault(e);
		RTFilter_chooseUserCatch({id:''});
	}

	function RTFilter_chooseGroup(control)
	{
		if (this.parentNode)
		{
			var elem = this;
		}
		else
		{
			var elem = BX.findChild(control, {tag:'input', attr: {type:'text'}}, true);
		}
		
		var popup = window['filterGroupsPopup_'+elem.id];
		popup.searchInput = elem;
		popup.popupWindow.setBindElement(elem);
		popup.show();

		RTFilter_chooseGroup_LAST_CALLER = elem;
	}

	function RTFilter_chooseGroupCatch(group)
	{
		if (group.length < 1) return;

		group = group[0];

		var inp = RTFilter_chooseGroup_LAST_CALLER;
		var hid = BX.findNextSibling(inp, {tag:'input',attr:{type:'hidden'}});
		var x = BX.findNextSibling(inp, {tag:'a'});

		hid.value = group.id;

		if (parseInt(group.id) > 0)
		{
			inp.value = group.title;
			x.style.display = 'inline';
		}
		else
		{
			inp.value = '';
			x.style.display = 'none';
		}

		try
		{
			var popup = window['filterGroupsPopup_'+inp.id];
			popup.popupWindow.close();
		}
		catch (e) {}
	}

	function RTFilter_chooseGroupClear(e)
	{
		RTFilter_chooseGroup_LAST_CALLER = BX.findChild(this.parentNode, {tag:'input',attr:{type:'text'}});

		BX.PreventDefault(e);
		RTFilter_chooseGroupCatch([{id:0}]);
	}



	BX.ready(function()
	{
		
		var info = <?=CUtil::PhpToJSObject($info)?>;
		var cpControl, fieldType, tipicalControl, isUF, ufId, ufName, cpSelector, selectorIndex;


		for (var i in info)
		{
			
			if (!info.hasOwnProperty(i))
				continue;

			cpControl = null;
			fieldType = info[i].FIELD_TYPE;
			// insert value control
			// search in `examples-custom` by name or type
			// then search in `examples` by type
			cpControl = BX.clone(
				BX.findChild(
					BX('report-chfilter-examples-custom'),
					{className: 'chfilter-field-' + info[i].FIELD_NAME}
				)
				||
				BX.findChild(
					BX('report-chfilter-examples-custom'),
					{className: 'chfilter-field-' + fieldType}
				)
				||
				BX.findChild(
					BX('report-chfilter-examples'),
					{className: 'chfilter-field-' + fieldType}
				),
				true
			);


			//global replace %ID%, %NAME%, %TITLE% and etc.
			cpControl.innerHTML = cpControl.innerHTML.replace(/%((?!VALUE)[A-Z]+)%/gi,
				function(str, p1, offset, s)
				{
					var n = p1.toUpperCase();
					return typeof(info[i][n]) != 'undefined' ? BX.util.htmlspecialchars(info[i][n]) : str;
				});
			tipicalControl = true;
			isUF = !!info[i]["IS_UF"];
			if (isUF)
			{
				ufId = info[i]["UF_ID"];
				ufName = info[i]["UF_NAME"];
				if (fieldType === 'enum' ||fieldType === 'crm' || fieldType === 'crm_status'
					|| fieldType === 'iblock_element' || fieldType === 'iblock_section'
					|| fieldType === 'money')
				{
					tipicalControl = false;
				}
			}
			if (tipicalControl)
			{
				if (cpControl.getAttribute('callback') != null)
				{
					// set last caller
					var callerName = cpControl.getAttribute('callback') + '_LAST_CALLER';
					var callerObj = BX.findChild(cpControl, {attr:'caller'}, true);
					window[callerName] = callerObj;
					
					// set value
					var cbFuncName = cpControl.getAttribute('callback') + 'Catch';
					window[cbFuncName](info[i].VALUE);
				}
				else
				{
					cpControl.innerHTML = cpControl.innerHTML.replace('%VALUE%', BX.util.htmlspecialchars(info[i].VALUE));
				}
				BX('report-filter-chfilter').appendChild(cpControl);
			}
			else
			{
				BX('report-filter-chfilter').appendChild(cpControl);
				var filterFieldSelector = BX.Report.FilterFieldSelectorManager.getSelector(ufId, ufName);
				if (filterFieldSelector)
				{
					cpSelector = filterFieldSelector.makeFilterField(cpControl, null, info[i]["NAME"]);
					if (cpSelector)
					{
						selectorIndex = cpSelector.getAttribute("ufSelectorIndex");
						filterFieldSelector.setFilterValue(selectorIndex, info[i]["VALUE"]);
					}
				}
			}

		}

		window.setTimeout(
			function()
			{
				var i, temp, deal, company, contact;

				// Company
				i = 0; temp = []; company = [];
				temp[i++] = BX.findChildren(BX('report-rewrite-filter'), { 'class':'chfilter-field-\\Bitrix\\Crm\\Company' }, true);
				for (i in temp) if (temp[i]) company = company.concat(temp[i]);
				if(company)
				{

					
					for (i in company)
					{
						BX.bind(
							BX.findChild(company[i], { 'tag':'input', 'class':'webform-field-textbox' }, true),
							'click',
							function(e)
							{
								if(!e)
								{
									e = window.event;
								}

								crmCompanySelector(this);
								BX.PreventDefault(e);
							}
						);
						BX.bind(BX.findChild(company[i], { 'tag':'a', 'class':'webform-field-textbox-clear' }, true), 'click', crmCompanySelectorClear);
					}
				}

			},
		500);
	});
</script>

<script>
	window.setTimeout(
			function()
			{
				// User controls
				var controls = BX.findChildren(BX('report-rewrite-filter'), {'class': /chfilter-field-(\\Bitrix\\Main\\User|employee)/}, true);

				if (controls != null)
				{
					for (i in controls)
					{

						var inp = BX.findChild(controls[i], {tag:'input', attr:{type:'text'}}, true);

						var x = BX.findNextSibling(inp, {tag:'a'});
						BX.bind(inp, 'click', RTFilter_chooseUser);
						BX.bind(inp, 'blur', RTFilter_chooseUserCatchFix);
						BX.bind(x, 'click', RTFilter_chooseUserClear);
					}
				}


			}, 500);
</script>

</script>
<?

$name = $APPLICATION->IncludeComponent(
	"bitrix:intranet.user.selector.new",
	".default",
	array(
		"MULTIPLE" => "N",
		"NAME" => "Single_" . 'F_CONTACT',
		"INPUT_NAME" => 'F[CONTACT]',
		"VALUE" => "",
		"POPUP" => "Y",
		"ON_SELECT" => "RTFilter_chooseUserCatch",
		"NAME_TEMPLATE" => ""
	),
	null,
	array("HIDE_ICONS" => "Y")
);

?>
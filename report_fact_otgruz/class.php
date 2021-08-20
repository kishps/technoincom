<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Exception;
use DateTime;
use DateInterval;

class Report extends CBitrixComponent
{

    private static $arLists_cache_time = 36000000;
    private static $arLists            = [];
    private static $limit = 500 + 1;

    private static $arDealInfo = [];

    private static $arrUSERS = [];
    private static $arrProductGroups = [];
    private static $arrFilter = [];
    private static $arError = [];
    private static $arDebug = [];


    public static function getFromRequest($fieldName)
    {
        /*  getFromRequest('fieldName');
            getFromRequest(['fieldName_1', 'fieldName_2', ]);
        */

        if (is_array($fieldName)) {
            $result = [];
            foreach ($fieldName as $value) {
                $result[$value] = (isset($_REQUEST[$value])) ? $_REQUEST[$value] : null;
            }
        } else {
            $result = (isset($_REQUEST[$fieldName])) ? $_REQUEST[$fieldName] : null;
        }

        return $result;
    } // function


    public function onPrepareComponentParams($arParams)
    {
        return $result;
    } // function

    public function executeComponent()
    {
        $arParams = &$this->arParams;
        $arResult = &$this->arResult;

        /*Bitrix\Main\Loader::registerAutoLoadClasses(null, [
            '\SP_Log'    => '/local/classes/sp/SP_Log.php',
            '\SP\Config' => '/local/classes/sp/Config.php',
            '\SP\Helper' => '/local/classes/sp/Helper.php',

            '\SP\Report\Test' => $this->GetPath() .'/include/Test.php',
        ]);*/

        //self::fLog(date('H:i:s', filemtime(__file__)), 'filetime');




        $arResult = [
            'ERROR_MSG' => [],
        ];



        $arLists = self::get_arLists();
        self::$arrUSERS = $arLists['users'];

        $arResult['DEAL_CATEGORYS'] = [
            700 => 'СОПРОВОЖДЕНИЕ',
            699 => 'СОБСТВЕННАЯ'
        ];

        //CModule::IncludeModule("iblock");
        $db_enum_list = CIBlockProperty::GetPropertyEnum("GRUPPA_TOVAROV", array(), array('IBLOCK_ID' => 17));
        while ($ar_enum_list = $db_enum_list->GetNext()) {
            $arResult['PRODUCT_GROUPS'][$ar_enum_list['ID']] = $ar_enum_list['VALUE'];
            self::$arrProductGroups[$ar_enum_list['ID']] = $ar_enum_list['VALUE'];
        }


        $params = self::getFromRequest([
            'F_DATE_FROM',
            'F_DATE_TO',
        ]);

        //   self::fLog(self::$arrProductGroups, '$arrProductGroups');

        // ### Обработка данных формы (
        $dateFormat = 'd.m.Y';

        // Период "c"
        if ($dateFrom_TMP = DateTime::createFromFormat($dateFormat, $params['F_DATE_FROM'])) {
            $arResult['FILTER']['F_DATE_FROM'] = $dateFrom_TMP->format($dateFormat);
        }

        // Период "по"
        if ($dateTo_TMP = DateTime::createFromFormat($dateFormat, $params['F_DATE_TO'])) {
            $arResult['FILTER']['F_DATE_TO'] = $dateTo_TMP->format($dateFormat);
        }

        if ($arResult['ERROR_MSG']) {
            $this->IncludeComponentTemplate();
            return;
        }
        // ### Обработка данных формы )

        // ### Период (
        $dateFrom = null;
        $dateTo   = null;
        if ($dateFrom_TMP) {
            $dateFrom = $dateFrom_TMP;
        }
        if ($dateTo_TMP) {
            $dateTo = $dateTo_TMP;
        }

        // ### Период )

        $arResult['DATA'] = self::fReport([
            'dateFrom' => $dateFrom,
            'dateTo'   => $dateTo,
            
        ]);

        if (count($arResult['DATA']) >= self::$limit) {
            $limit = self::$limit - 1;
            $arResult['DATA'] = array_slice($arResult['DATA'], 0, $limit, true);
            $arResult['DATA_LIMIT'] = $limit;
        }

        $arResult['DEAL_INFO'] = self::$arDealInfo;

        $arResult['REPORT_ERROR_MSG'] = self::$arError;

        $arResult['DEBUG_MSG'] = self::$arDebug;

		$arResult['USERS'] = self::$arrUSERS;

        $this->IncludeComponentTemplate();
    } // function

    public static function get_arLists($flagClearCache = false)
    {
        if (!$flagClearCache and self::$arLists) {
            return self::$arLists;
        }

        if (!$flagClearCache) {
            $flagClearCache = (\SP\Helper::getFromRequest('clear_cache') == 'Y');
        }

        $arLists = [];

        // Проверим кеш
        $cache_id  = 'ar_lists__report';
        $cache_dir = '/sp_lists_cache/' . $cache_id;

        $obCache = new \CPHPCache;

        if ($flagClearCache) {
            $obCache->CleanDir($cache_dir); // Очистка кеша
        }

        if ($obCache->InitCache(self::$arLists_cache_time, $cache_id, $cache_dir)) {
            // Берем из кеша
            $arLists = $obCache->GetVars();
        } elseif ($obCache->StartDataCache()) {
            // Создаем списки

            $arLists = [
                'users' => [],
            ];

            $res = \CUser::GetList(
                $t_by       = 'last_name',
                $t_order    = 'asc',
                $t_filter   = ['ACTIVE' => 'Y', 'UF_DEPARTMENT' => 3],
                $t_arParams = ['FIELDS' => ['ID', 'NAME', 'LAST_NAME','PERSONAL_PHOTO']]
            );
            while ($ar = $res->Fetch()) {
                $arLists['users'][$ar['ID']] = $ar;
				$arrFile = CFile::ResizeImageGet($ar['PERSONAL_PHOTO'], array('width'=>40, 'height'=>40), BX_RESIZE_IMAGE_EXACT, true);
				$arLists['users'][$ar['ID']]['PHOTO'] = $arrFile;
            }

            //print_r($arLists);

            $arLists['date'] = date('Y-m-d H:i:s');

            // Запишем в кеш
            global $CACHE_MANAGER;
            $CACHE_MANAGER->StartTagCache($cache_dir);
            //$CACHE_MANAGER->RegisterTag( 'iblock_id_'. \SP\Config::get('iblock_catalog_id') );
            $CACHE_MANAGER->EndTagCache();
            $obCache->EndDataCache($arLists);
        } //

        self::$arLists = $arLists;

        return self::$arLists;
    } // function

    public static function fReport($params)
    {
        /*  fReport([
                'dateFrom' => $dateFrom,
                'dateTo'   => $dateTo,
                'user_id'  => $arResult['FILTER']['F_USER'],
            ]);
        */
        $result = [];

        self::$arDealInfo = [];



        //self::fLog($params, 'fReport $params');

        // ### 1 # Менеджер # ИБ 17 "Запуск Заказа в производство" # orders_shipped_summ
        self::fReport_hlp_get_tasks($result, $params);



        self::fReport_hlp_getDealInfo($params);

        ksort($result);

        // $result[ time ]['detail'][ deal_id ]
        foreach ($result as $key => $value) {
            if ($result[$key]['detail']) {
                ksort($result[$key]['detail']);
            }
        }



        // Итоги по каждому показателю за весь выбранный период
        self::fReport_hlp_get_total($result, $params);

        return $result;
    } // function




    public static function fReport_hlp_AddElement($params)
    {
        /*  self::fReport_hlp_AddElement([
                            'result'  => &$result,
                            'time'    => $time,
                            'deal_id' => $deal_id,
                            'ASSIGNED_BY_ID' => $arTask["UF_AUTO_841972304973"],
                            'PRODUCT_GROUP'=> $arTask["UF_AUTO_213360623899"],
                            'CATEGORY' => $arTask["UF_AUTO_732134480270"],
                            'BP_ID'=> $arTask["UF_AUTO_333119548596"],
                            'SUMM_FOR_DEAL' => $arTask["UF_AUTO_779960634145"],
                            'TASK_ID' => $arTask["ID"],
                            'TASK_TITLE' = $arTask['TITLE']
                        ]);

  
        */
        $result = &$params['result'];
        $time   = $params['time'];
        $assigned_id = $params['ASSIGNED_BY_ID'];
        $task_id = $params['TASK_ID'];

        $arrDeal = self::fReport_hlp_getDealInfo($params['deal_id']);

        if (!in_array($assigned_id, array_keys(self::$arrUSERS))) return;

		//если имя сотрудника пустое или строка с пробелом, заполняем
        if ($result['PLAN'][$assigned_id]['NAME'] == " " || !$result['PLAN'][$assigned_id]['NAME']) {
            $userFO = self::$arrUSERS[$assigned_id]['NAME'] . " " . self::$arrUSERS[$assigned_id]['LAST_NAME'];
            $result['PLAN'][$assigned_id]['NAME'] = $userFO;
			$result['PLAN'][$assigned_id]['ID'] = $assigned_id;
			$arrFile = CFile::ResizeImageGet(self::$arrUSERS[$assigned_id]['PERSONAL_PHOTO'], array('width'=>40, 'height'=>40), BX_RESIZE_IMAGE_EXACT, true);
			$result['PLAN'][$assigned_id]['PHOTO'] = $arrFile['src'];
        }

        $result['PLAN'][$assigned_id]['TASKS'][$task_id] = [
            'ASSIGNED_ID' => $assigned_id,
            'TITLE' => $params['TASK_TITLE'],
            'ID' => $task_id,
            'LINK' => '/workgroups/group/21/tasks/task/view/' . $task_id . '/',
            'DEAL_ID' => $params['deal_id'],
            'DEAL_TITLE' => $arrDeal['TITLE'],
            'DEAL_LINK' => '/crm/deal/details/' . $params['deal_id'] . '/',
            'DATE_CLOSED' => $time,
            /*'PRODUCT_GROUP'=> self::$arrProductGroups[$params['PRODUCT_GROUP']],*/
            'PRODUCT_GROUP' => $params['PRODUCT_GROUP'],
            'SUMM_FOR_DEAL' => $params['SUMM_FOR_DEAL'],

        ];
        $result['PLAN'][$assigned_id]['TOTAL'] = $result['PLAN'][$assigned_id]['TOTAL'] * 1 + $params['SUMM_FOR_DEAL'] * 1;
        $result['PLAN'][$assigned_id]['TOTAL_GROUPS'][$params['PRODUCT_GROUP']] = $result['PLAN'][$assigned_id]['TOTAL_GROUPS'][$params['PRODUCT_GROUP']] * 1 + $params['SUMM_FOR_DEAL'] * 1;

        $result['PLAN']['ALL']['TOTAL'] = $result['PLAN']['ALL']['TOTAL'] * 1 + $params['SUMM_FOR_DEAL'] * 1;
		 $result['PLAN']['ALL']['TOTAL_TASKS'] = $result['PLAN']['ALL']['TOTAL_TASKS'] * 1 + 1;
        $result['PLAN']['ALL']['TOTAL_GROUPS'][$params['PRODUCT_GROUP']] = $result['PLAN']['ALL']['TOTAL_GROUPS'][$params['PRODUCT_GROUP']] * 1 + $params['SUMM_FOR_DEAL'] * 1;
    } // function



    public static function fReport_hlp_AddElement_SUPPORT($params)
    {
        /*  self::fReport_hlp_AddElement_SUPPORT([
                            'result'  => &$result,
                            'time'    => $time,
                            'deal_id' => $deal_id,
                            'ASSIGNED_BY_ID' => $arTask["UF_AUTO_841972304973"],
                            'PRODUCT_GROUP'=> $arTask["UF_AUTO_213360623899"],
                            'CATEGORY' => $arTask["UF_AUTO_732134480270"],
                            'BP_ID'=> $arTask["UF_AUTO_333119548596"],
                            'SUMM_FOR_DEAL' => $arTask["UF_AUTO_779960634145"],
                            'TASK_ID' => $arTask["ID"],
                            'TASK_TITLE' = $arTask['TITLE']
                        ]);

  
        */
        $result = &$params['result'];
        $time   = $params['time'];
        $assigned_id = $params['ASSIGNED_BY_ID'];
        $task_id = $params['TASK_ID'];

        $arrDeal = self::fReport_hlp_getDealInfo($params['deal_id']);

        if (!in_array($assigned_id, array_keys(self::$arrUSERS))) return;

		//если имя сотрудника пустое или строка с пробелом, заполняем
        if ($result['SUPPORT'][$assigned_id]['NAME'] == " " || !$result['SUPPORT'][$assigned_id]['NAME']) {
            $userFO = self::$arrUSERS[$assigned_id]['NAME'] . " " . self::$arrUSERS[$assigned_id]['LAST_NAME'];
            $result['SUPPORT'][$assigned_id]['NAME'] = $userFO;
			$result['SUPPORT'][$assigned_id]['ID'] = $assigned_id;
			$arrFile = CFile::ResizeImageGet(self::$arrUSERS[$assigned_id]['PERSONAL_PHOTO'], array('width'=>40, 'height'=>40), BX_RESIZE_IMAGE_EXACT, true);
			$result['SUPPORT'][$assigned_id]['PHOTO'] = $arrFile['src'];
        }

        $result['SUPPORT'][$assigned_id]['TASKS'][$task_id] = [
            'ASSIGNED_ID' => $assigned_id,
            'TITLE' => $params['TASK_TITLE'],
            'ID' => $task_id,
            'LINK' => '/workgroups/group/21/tasks/task/view/' . $task_id . '/',
            'DEAL_ID' => $params['deal_id'],
            'DEAL_TITLE' => $arrDeal['TITLE'],
            'DEAL_LINK' => '/crm/deal/details/' . $params['deal_id'] . '/',
            'DATE_CLOSED' => $time,
            /*'PRODUCT_GROUP'=> self::$arrProductGroups[$params['PRODUCT_GROUP']],*/
            'PRODUCT_GROUP' => $params['PRODUCT_GROUP'],
            'SUMM_FOR_DEAL' => $params['SUMM_FOR_DEAL'],

        ];
        $result['SUPPORT'][$assigned_id]['TOTAL'] = $result['SUPPORT'][$assigned_id]['TOTAL'] * 1 + $params['SUMM_FOR_DEAL'] * 1;
        $result['SUPPORT'][$assigned_id]['TOTAL_GROUPS'][$params['PRODUCT_GROUP']] = $result['SUPPORT'][$assigned_id]['TOTAL_GROUPS'][$params['PRODUCT_GROUP']] * 1 + $params['SUMM_FOR_DEAL'] * 1;

        $result['SUPPORT']['ALL']['TOTAL'] = $result['SUPPORT']['ALL']['TOTAL'] * 1 + $params['SUMM_FOR_DEAL'] * 1;
		 $result['SUPPORT']['ALL']['TOTAL_TASKS'] = $result['SUPPORT']['ALL']['TOTAL_TASKS'] * 1 + 1;
        $result['SUPPORT']['ALL']['TOTAL_GROUPS'][$params['PRODUCT_GROUP']] = $result['SUPPORT']['ALL']['TOTAL_GROUPS'][$params['PRODUCT_GROUP']] * 1 + $params['SUMM_FOR_DEAL'] * 1;
    } // function


    public static function fReport_hlp_AddElement_TOTALS_OWN($params)
    {
        /*  self::fReport_hlp_AddElement([
                            'result'  => &$result,
                            'deal_id' => $deal_id,
                            'ASSIGNED_BY_ID' => $arTask["UF_AUTO_841972304973"],
                            'SUMM_FOR_DEAL' => $arTask["UF_AUTO_779960634145"],
                        ]);
        */
        $result = &$params['result'];
        $assigned_id = $params['ASSIGNED_BY_ID'];
        $arrDeal = self::fReport_hlp_getDealInfo($params['deal_id']);

        
        $result['REPORT_TOTALS'][$assigned_id]['DEALS_OWN'][$params['deal_id']] = [
            'DEAL_ID' => $params['deal_id'],
            'DEAL_TITLE' => $arrDeal['TITLE'],
            'DEAL_LINK' => '/crm/deal/details/' . $params['deal_id'] . '/',
        ];
        $result['REPORT_TOTALS'][$assigned_id]['DEALS_OWN'][$params['deal_id']]['SUMM_FOR_DEAL'] = $result['REPORT_TOTALS'][$assigned_id]['DEALS_OWN'][$params['deal_id']]['SUMM_FOR_DEAL'] * 1 + $params['SUMM_FOR_DEAL'] * 1;
        $result['REPORT_TOTALS'][$assigned_id]['DEALS_TOTAL'] = $result['REPORT_TOTALS'][$assigned_id]['DEALS_TOTAL'] * 1 + $params['SUMM_FOR_DEAL'] * 1;
        $result['REPORT_TOTALS'][$assigned_id]['DEALS_OWN_TOTAL'] = $result['REPORT_TOTALS'][$assigned_id]['DEALS_OWN_TOTAL'] * 1 + $params['SUMM_FOR_DEAL'] * 1;

        $result['REPORT_TOTALS']['ALL']['DEALS_OWN_TOTAL'] = $result['REPORT_TOTALS']['ALL']['DEALS_OWN_TOTAL']*1+ $params['SUMM_FOR_DEAL'] * 1;
        $result['REPORT_TOTALS']['ALL']['DEALS_TOTAL'] = $result['REPORT_TOTALS']['ALL']['DEALS_TOTAL'] * 1 + $params['SUMM_FOR_DEAL'] * 1;

    } // function


    public static function fReport_hlp_AddElement_TOTALS_SUPPORT($params)
    {
        /*  self::fReport_hlp_AddElement([
                            'result'  => &$result,
                            'deal_id' => $deal_id,
                            'ASSIGNED_BY_ID' => $arTask["UF_AUTO_841972304973"],
                            'SUMM_FOR_DEAL' => $arTask["UF_AUTO_779960634145"],
                        ]);
        */
        $result = &$params['result'];
        $assigned_id = $params['ASSIGNED_BY_ID'];
        $arrDeal = self::fReport_hlp_getDealInfo($params['deal_id']);

        $result['REPORT_TOTALS'][$assigned_id]['DEALS_SUPPORT_TOTAL'] = $result['REPORT_TOTALS'][$assigned_id]['DEALS_SUPPORT_TOTAL'] * 1 + $params['SUMM_FOR_DEAL'] * 1;
        $result['REPORT_TOTALS'][$assigned_id]['DEALS_SUPPORT'][$params['deal_id']] = [
            'DEAL_ID' => $params['deal_id'],
            'DEAL_TITLE' => $arrDeal['TITLE'],
            'DEAL_LINK' => '/crm/deal/details/' . $params['deal_id'] . '/',
        ];
        $result['REPORT_TOTALS'][$assigned_id]['DEALS_SUPPORT'][$params['deal_id']]['SUMM_FOR_DEAL'] = $result['REPORT_TOTALS'][$assigned_id]['DEALS_SUPPORT'][$params['deal_id']]['SUMM_FOR_DEAL'] * 1 + $params['SUMM_FOR_DEAL'] * 1;
        $result['REPORT_TOTALS'][$assigned_id]['DEALS_TOTAL'] = $result['REPORT_TOTALS'][$assigned_id]['DEALS_TOTAL'] * 1 + $params['SUMM_FOR_DEAL'] * 1;

        $result['REPORT_TOTALS']['ALL']['DEALS_SUPPORT_TOTAL'] = $result['REPORT_TOTALS']['ALL']['DEALS_SUPPORT_TOTAL']*1+ $params['SUMM_FOR_DEAL'] * 1;
        $result['REPORT_TOTALS']['ALL']['DEALS_TOTAL'] = $result['REPORT_TOTALS']['ALL']['DEALS_TOTAL'] * 1 + $params['SUMM_FOR_DEAL'] * 1;
    } // function


    // ###################################


    public static function fReport_hlp_get_tasks(&$result, $params)
    {

        // ### 

        // Выбираем все задачи Отгрузка заказа КЛИЕНТУ
        if (!CModule::IncludeModule("tasks")) return;
        if ($params['dateFrom'] && $params['dateTo']) {
            $t_arFilter = array(
                "TITLE" => "%Отгрузка заказа КЛИЕНТУ%",
                '>=CLOSED_DATE' => $params['dateFrom']->format('d.m.Y'),
                '<=CLOSED_DATE' => $params['dateTo']->format('d.m.Y'),
                //'UF_AUTO_732134480270' => 699,
            );
        } else {
            $t_arFilter = array(
                "TITLE" => "%Отгрузка заказа КЛИЕНТУ%",
                //'UF_AUTO_732134480270' => 699,
            );
        }


        //UF_AUTO_841972304973 - 'ID Ответственный Менеджер'
        //UF_AUTO_213360623899 - 'Группа Товаров'
        //UF_AUTO_732134480270 - 'Категория Сделки'
        //UF_AUTO_333119548596 - 'ID бизнес-процесса'
        //UF_AUTO_779960634145 - 'Сумма сделки'

        $res = CTasks::GetList(
            array("UF_AUTO_841972304973" => "ASC"),
            $t_arFilter,
            array('UF_CRM_TASK', 'DESCRIPTION', 'CLOSED_DATE', "TITLE", 'ID', 'UF_AUTO_841972304973', 'UF_AUTO_213360623899', 'UF_AUTO_732134480270', 'UF_AUTO_333119548596', 'UF_AUTO_779960634145')
        );

        while ($arTask = $res->GetNext()) {
            // разбиваем description на <tr> временное решение когда начнут заполняться поля закомментировать
            /*$arrTrTableDescriptonTask = explode('[/TR]', $arTask['DESCRIPTION']);
                        foreach ($arrTrTableDescriptonTask as $trItem) {
                        if (strrpos($trItem, 'Стоимость Заказа по Спецификации без НДС')>0) $tdItem = explode('[/TD]', $trItem);  //// разбиваем <tr> на <td>
                        }
                        $arTask['SUMMA_SDELKI_BEZ_NDS'] = str_replace('[TD]','',trim($tdItem[1],'|RUB')) * 1;*/
            //



            foreach ($arTask['UF_CRM_TASK'] as $crm) {
                if (strrpos($crm, 'D_') === 0) {
                    $arTask['UF_CRM_TASK'] = str_replace('D_', '', $crm);
                }
            }

            //узнаем ответственного по сделке, к которой приявязана задача
            $rsDeal =  CCrmDeal::GetListEx(
                $arOrder = array('CLOSEDATE' => 'asc'),
                array('ID' => $arTask['UF_CRM_TASK']),
                $arGroupBy = false,
                $arNavStartParams = false,
                $arSelectFields = array('ASSIGNED_BY_ID')
            );
            $ar = $rsDeal->GetNext();


            //дата закрытия задачи
            $timeTMP = MakeTimeStamp($arTask["CLOSED_DATE"]);
            if (!$timeTMP) {
                continue; // На всякий
            }
            $timeTMP = MakeTimeStamp($arTask['CLOSED_DATE']);
            $time = new DateTime();
            $time->setTimestamp($timeTMP);
            $time->setTime(0, 0);
            $time = $time->getTimestamp();

            //id сделки
            $deal_id = (int) $arTask['UF_CRM_TASK']; // 0 - не указана

            if ($arTask["UF_AUTO_732134480270"] == 'СОБСТВЕННАЯ') {
                self::fReport_hlp_AddElement([
                    'result'  => &$result,
                    'time'    => $arTask['CLOSED_DATE'],
                    'deal_id' => $deal_id,
                    'ASSIGNED_BY_ID' => $ar['ASSIGNED_BY_ID'],
                    //'ASSIGNED_BY_ID' => $arTask["UF_AUTO_841972304973"], //потом расскоментировать
                    'PRODUCT_GROUP' => $arTask["UF_AUTO_213360623899"],
                    'CATEGORY' => $arTask["UF_AUTO_732134480270"],
                    'BP_ID' => $arTask["UF_AUTO_333119548596"],
                    'SUMM_FOR_DEAL' => $arTask["UF_AUTO_779960634145"]*1,
                    //'SUMM_FOR_DEAL' => $arTask["SUMMA_SDELKI_BEZ_NDS"],
                    'TASK_ID' => $arTask["ID"],
                    'TASK_TITLE' => $arTask['TITLE']
                ]);

                self::fReport_hlp_AddElement_TOTALS_OWN([
                    'result'  => &$result,
                    'deal_id' => $deal_id,
                    'ASSIGNED_BY_ID' => $ar['ASSIGNED_BY_ID'],
                    'SUMM_FOR_DEAL' => $arTask["UF_AUTO_779960634145"],
                ]);
            } elseif ($arTask["UF_AUTO_732134480270"] == 'СОПРОВОЖДЕНИЕ') {

                self::fReport_hlp_AddElement_SUPPORT([
                    'result'  => &$result,
                    'time'    => $arTask['CLOSED_DATE'],
                    'deal_id' => $deal_id,
                    'ASSIGNED_BY_ID' => $ar['ASSIGNED_BY_ID'],
                    //'ASSIGNED_BY_ID' => $arTask["UF_AUTO_841972304973"], //потом расскоментировать
                    'PRODUCT_GROUP' => $arTask["UF_AUTO_213360623899"],
                    'CATEGORY' => $arTask["UF_AUTO_732134480270"],
                    'BP_ID' => $arTask["UF_AUTO_333119548596"],
                    'SUMM_FOR_DEAL' => $arTask["UF_AUTO_779960634145"]*1,
                    //'SUMM_FOR_DEAL' => $arTask["SUMMA_SDELKI_BEZ_NDS"],
                    'TASK_ID' => $arTask["ID"],
                    'TASK_TITLE' => $arTask['TITLE']
                ]);

                self::fReport_hlp_AddElement_TOTALS_SUPPORT([
                    'result'  => &$result,
                    'deal_id' => $deal_id,
                    'ASSIGNED_BY_ID' => $ar['ASSIGNED_BY_ID'],
                    'SUMM_FOR_DEAL' => $arTask["UF_AUTO_779960634145"],
                ]);
            }

            // $result[ $time ]['detail'][ $deal_id ][ $resultKey ]['items'][ $arTask['ID'] ] = [
            //'title' => '1',
            //'price' => "1",
            //'link'  => "/company/personal/user/22/tasks/task/view/{$arTask['ID']}/",

        }

        if ($params['dateFrom'] && $params['dateTo']) {
            $t_arFilter = array(
                "TITLE" => "%ВЛОЖИТE СКАН НАКЛАДНОЙ ОТПРАВИТЕЛЯ В ТЕЛО ЗАДАЧИ%",
                '>=CLOSED_DATE' => $params['dateFrom']->format('d.m.Y'),
                '<=CLOSED_DATE' => $params['dateTo']->format('d.m.Y'),
                //'UF_AUTO_732134480270' => 699,
            );
        } else {
            $t_arFilter = array(
                "TITLE" => "%ВЛОЖИТE СКАН НАКЛАДНОЙ ОТПРАВИТЕЛЯ В ТЕЛО ЗАДАЧИ%",
                //'UF_AUTO_732134480270' => 699,
            );
        }


        //UF_AUTO_841972304973 - 'ID Ответственный Менеджер'
        //UF_AUTO_213360623899 - 'Группа Товаров'
        //UF_AUTO_732134480270 - 'Категория Сделки'
        //UF_AUTO_333119548596 - 'ID бизнес-процесса'
        //UF_AUTO_779960634145 - 'Сумма сделки'

        $res = CTasks::GetList(
            array("UF_AUTO_841972304973" => "ASC"),
            $t_arFilter,
            array('UF_CRM_TASK', 'DESCRIPTION', 'CLOSED_DATE', "TITLE", 'ID', 'UF_AUTO_841972304973', 'UF_AUTO_213360623899', 'UF_AUTO_732134480270', 'UF_AUTO_333119548596', 'UF_AUTO_779960634145')
        );

        while ($arTask = $res->GetNext()) {
            // разбиваем description на <tr> временное решение когда начнут заполняться поля закомментировать
            /*$arrTrTableDescriptonTask = explode('[/TR]', $arTask['DESCRIPTION']);
                        foreach ($arrTrTableDescriptonTask as $trItem) {
                        if (strrpos($trItem, 'Стоимость Заказа по Спецификации без НДС')>0) $tdItem = explode('[/TD]', $trItem);  //// разбиваем <tr> на <td>
                        }
                        $arTask['SUMMA_SDELKI_BEZ_NDS'] = str_replace('[TD]','',trim($tdItem[1],'|RUB')) * 1;*/
            //



            foreach ($arTask['UF_CRM_TASK'] as $crm) {
                if (strrpos($crm, 'D_') === 0) {
                    $arTask['UF_CRM_TASK'] = str_replace('D_', '', $crm);
                }
            }

            //узнаем ответственного по сделке, к которой приявязана задача
            $rsDeal =  CCrmDeal::GetListEx(
                $arOrder = array('CLOSEDATE' => 'asc'),
                array('ID' => $arTask['UF_CRM_TASK']),
                $arGroupBy = false,
                $arNavStartParams = false,
                $arSelectFields = array('ASSIGNED_BY_ID')
            );
            $ar = $rsDeal->GetNext();


            //дата закрытия задачи
            $timeTMP = MakeTimeStamp($arTask["CLOSED_DATE"]);
            if (!$timeTMP) {
                continue; // На всякий
            }
            $timeTMP = MakeTimeStamp($arTask['CLOSED_DATE']);
            $time = new DateTime();
            $time->setTimestamp($timeTMP);
            $time->setTime(0, 0);
            $time = $time->getTimestamp();

            //id сделки
            $deal_id = (int) $arTask['UF_CRM_TASK']; // 0 - не указана

            if ($arTask["UF_AUTO_732134480270"] == 'СОБСТВЕННАЯ') {
                self::fReport_hlp_AddElement([
                    'result'  => &$result,
                    'time'    => $arTask['CLOSED_DATE'],
                    'deal_id' => $deal_id,
                    'ASSIGNED_BY_ID' => $ar['ASSIGNED_BY_ID'],
                    //'ASSIGNED_BY_ID' => $arTask["UF_AUTO_841972304973"], //потом расскоментировать
                    'PRODUCT_GROUP' => $arTask["UF_AUTO_213360623899"],
                    'CATEGORY' => $arTask["UF_AUTO_732134480270"],
                    'BP_ID' => $arTask["UF_AUTO_333119548596"],
                    'SUMM_FOR_DEAL' => $arTask["UF_AUTO_779960634145"]*1,
                    //'SUMM_FOR_DEAL' => $arTask["SUMMA_SDELKI_BEZ_NDS"],
                    'TASK_ID' => $arTask["ID"],
                    'TASK_TITLE' => $arTask['TITLE']
                ]);

                self::fReport_hlp_AddElement_TOTALS_OWN([
                    'result'  => &$result,
                    'deal_id' => $deal_id,
                    'ASSIGNED_BY_ID' => $ar['ASSIGNED_BY_ID'],
                    'SUMM_FOR_DEAL' => $arTask["UF_AUTO_779960634145"],
                ]);
            } elseif ($arTask["UF_AUTO_732134480270"] == 'СОПРОВОЖДЕНИЕ') {

                self::fReport_hlp_AddElement_SUPPORT([
                    'result'  => &$result,
                    'time'    => $arTask['CLOSED_DATE'],
                    'deal_id' => $deal_id,
                    'ASSIGNED_BY_ID' => $ar['ASSIGNED_BY_ID'],
                    //'ASSIGNED_BY_ID' => $arTask["UF_AUTO_841972304973"], //потом расскоментировать
                    'PRODUCT_GROUP' => $arTask["UF_AUTO_213360623899"],
                    'CATEGORY' => $arTask["UF_AUTO_732134480270"],
                    'BP_ID' => $arTask["UF_AUTO_333119548596"],
                    'SUMM_FOR_DEAL' => $arTask["UF_AUTO_779960634145"]*1,
                    //'SUMM_FOR_DEAL' => $arTask["SUMMA_SDELKI_BEZ_NDS"],
                    'TASK_ID' => $arTask["ID"],
                    'TASK_TITLE' => $arTask['TITLE']
                ]);

                self::fReport_hlp_AddElement_TOTALS_SUPPORT([
                    'result'  => &$result,
                    'deal_id' => $deal_id,
                    'ASSIGNED_BY_ID' => $ar['ASSIGNED_BY_ID'],
                    'SUMM_FOR_DEAL' => $arTask["UF_AUTO_779960634145"],
                ]);
            }

            // $result[ $time ]['detail'][ $deal_id ][ $resultKey ]['items'][ $arTask['ID'] ] = [
            //'title' => '1',
            //'price' => "1",
            //'link'  => "/company/personal/user/22/tasks/task/view/{$arTask['ID']}/",

        }
    } // function



    public static function fReport_hlp_get_total(&$result, $params)
    {
        // Итог за выбранный период



    } // function

    public static function fReport_hlp_getDealInfo($deal_id)
    {

        $arFilter = array(
            'ID' => $deal_id,
        );

        $rsDeal =  CCrmDeal::GetListEx(
            $arOrder = array('ID' => 'asc'),
            $arFilter,
            $arGroupBy = false,
            $arNavStartParams = false,
            $arSelectFields = array('ID', 'TITLE')
        );
        if ($ar = $rsDeal->GetNext()) {
            return $ar;
        }
    } // function

    // #######################################################################



} // class

<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Exception;
use DateTime;
use DateInterval;
use Bitrix\Highloadblock as HL; 
use Bitrix\Main\Entity;

class Report extends CBitrixComponent
{

    private static $arLists_cache_time = 36000000;
    private static $arLists  = [];
    private static $limit = 500 + 1;

    private static $arDealInfo = [];

    private static $arrUSERS = [];
    private static $arrProductGroups = [];
    private static $arrFilter = [];
    private static $arError = [];
    private static $arDebug = [];




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

        $arResult['USERS'] = self::get_arLists();

        $arResult['DATA'] = self::fReport();

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
                $t_arParams = ['FIELDS' => ['ID', 'NAME', 'LAST_NAME']]
            );
            while ($ar = $res->Fetch()) {
                $arLists['users'][$ar['ID']] = $ar;
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


    public static function fReport()
    {
        /*  fReport([
                'dateFrom' => $dateFrom,
                'dateTo'   => $dateTo,
                'user_id'  => $arResult['FILTER']['F_USER'],
            ]);
        */
        $result = [];
        //self::fLog($params, 'fReport $params');

        // ### 1 # Менеджер # ИБ 17 "Запуск Заказа в производство" # orders_shipped_summ
        self::fReport_hlp_get_plan($result, $params);

        return $result;
    } // function




    public static function fReport_hlp_AddElement($params)
    {
        /*  self::fReport_hlp_AddElement([
                    'result'  => &$result,
                    'PLAN_TIME'=>$arData['UF_PLAN_TIME'],
                    'PLAN_USER'=>$arData['UF_PLAN_USER'],
                    'PLAN_TIME_TYPE'=>$arData['UF_PLAN_TIME_TYPE'],
                    'PLAN_USERID'=>$arData['UF_PLAN_USERID'],
                ]);

  
        */
        $result = &$params['result'];

        $result['PLAN'][$params['PLAN_TIME_TYPE']][$params['PLAN_TIME']][$params['PLAN_USERID']] = [
            'PLAN_TIME' => $params['PLAN_TIME'],
            'PLAN_USER' => $params['PLAN_USER'],
            'PLAN_TIME_TYPE' => $params['PLAN_TIME_TYPE'],
            'PLAN_USERID' => $params['PLAN_USERID'],
        ];
        
    } // function



    // ###################################


    public static function fReport_hlp_get_plan(&$result, $params)
    {

        // ### 

        CModule::IncludeModule('highloadblock');

        
        $hlbl = 2; // Указываем ID нашего highloadblock блока к которому будет делать запросы.
        $hlblock = HL\HighloadBlockTable::getById($hlbl)->fetch(); 
        
        $entity = HL\HighloadBlockTable::compileEntity($hlblock); 
        $entity_data_class = $entity->getDataClass(); 
        
        $rsData = $entity_data_class::getList(array(
           "select" => array("*"),
           "order" => array("ID" => "ASC"),
           "filter" => array()  // Задаем параметры фильтра выборки
        ));
        
        while($arData = $rsData->Fetch()){


                self::fReport_hlp_AddElement([
                    'result'  => &$result,
                    'PLAN_TIME'=>$arData['UF_PLAN_TIME'],
                    'PLAN_USER'=>$arData['UF_PLAN_USER'],
                    'PLAN_TIME_TYPE'=>$arData['UF_PLAN_TIME_TYPE'],
                    'PLAN_USERID'=>$arData['UF_PLAN_USERID'],
                ]);

        }
    } // function




  

    // #######################################################################



} // class

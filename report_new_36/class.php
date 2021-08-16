<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Exception;
use DateTime;
use DateInterval;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style;
//CModule::IncludeModule("tasks");

class Report extends CBitrixComponent
{

    private static $arLists_cache_time = 36000000;
    private static $arLists            = [];

    private static $limit = 500 + 1;

    private static $itemKeys = [
        'h_call_outgoing',
        'i_call_outgoing_2min',
        'j_email_sent',
        'k_conversion_company',
        'l_calc_deal',
        'm_deal_failed',
        'o_product_testing',
        'p_product_testing_complete',
        'c_production',
        'c_production_summ',
        'a_orders_shipped',
        'a_orders_shipped_summ',
        'f_prihod_ds',
        'q_prihod_ds_whithNDS',
        'g_summ_for_deal',
        'e_planned_shipments'
    ];
    private static $itemTemplate       = [];
    private static $itemDetailTemplate = [];
    private static $arDealInfo = [];
    private static $responsibleIdForDeal = [];

    private static $param_filter_forTable = [];

    private static $arError = [];
    private static $arDebug = [];

    public function onPrepareComponentParams($arParams)
    {
        $result = [
            'flagTest' => isset($arParams['flagTest']) ? $arParams['flagTest'] : false,
        ];

        return $result;
    } // function

    public function executeComponent()
    {
        $arParams = &$this->arParams;
        $arResult = &$this->arResult;

        Bitrix\Main\Loader::registerAutoLoadClasses(null, [
            '\SP_Log'    => '/local/classes/sp/SP_Log.php',
            '\SP\Config' => '/local/classes/sp/Config.php',
            '\SP\Helper' => '/local/classes/sp/Helper.php',

            '\SP\Report\Test' => $this->GetPath() . '/include/Test.php',
        ]);

        //self::fLog(date('H:i:s', filemtime(__file__)), 'filetime');

        if ($arParams['flagTest']) {
            \SP\Report\Test::run($this);
            return;
        }

        if (\SP\Helper::getFromRequest('debug') == '123') {
            $_SESSION['SP_flagDebug'] = true;
        }

        $arResult = [
            'ERROR_MSG' => [],
        ];

        $arResult['PERIODS'] = [
            'month'     => 'этот месяц',
            'month_ago' => 'прошл. месяц',
            'month_next' => 'след. месяц',
            //'week'      => 'эта неделя',
            //'week_ago'  => 'прошл. неделя',
            //'days'      => 'за послед.',
            //'after'     => 'позже',
            //'before'    => 'раньше',
            'interval'  => 'интервал',
            'all'       => 'за все время',
        ];

        $arResult['DATA_TITLES'] = [
            'h_call_outgoing'            => 'Звонки исходящие',
            'i_call_outgoing_2min'       => 'Звонки исходящие (Более 2х мин)',
            'j_email_sent'               => 'E-mail отправленные',
            'k_conversion_company'       => 'Конверсия Компаний из ПКБ в АКБ',
            'l_calc_deal'                => 'Открыто Сделок в расчет ПТО',
            'm_deal_failed'              => 'Забраковано Сделок',
            'o_product_testing'          => 'Открыто Испытаний',
            'p_product_testing_complete' => 'Завершено Испытаний',
            'c_production'               => 'Запущен БП Производства Заказа',
            'c_production_summ'          => 'Cумма запущенных в пр-во заказов (руб без НДС)',
            'a_orders_shipped'           => 'Отгружено Заказов',
            'a_orders_shipped_summ'      => 'Сумма отгруженных заказов (руб без НДС)',
            'f_prihod_ds'                => 'Приход ДС (руб без НДС)',
            'q_prihod_ds_whithNDS'       => 'Приход ДС (руб с НДС)',
            'g_summ_for_deal'       => 'Ожидаемые поступления без НДС',    //добавили title нового столбца
            'e_planned_shipments'       => 'Планируемые отгрузки',
        ];

        $order = array('e_planned_shipments','a_orders_shipped_summ','a_orders_shipped','g_summ_for_deal','f_prihod_ds','c_production','c_production_summ');

        uksort($arResult['DATA_TITLES'], function($key1, $key2) use ($order) {
            return (array_search($key1, $order) > array_search($key2, $order));
        });

        //SP_Log::consoleLog(self::$itemKeys, 'itemKeys');


        $arLists = self::get_arLists();
        $arResult['USERS'] = $arLists['users'];

        $arResult['FILTER'] = [
            'F_DATE_TYPE' => 'week',
            'F_DATE_FROM' => '',
            'F_DATE_TO'   => '',
            'F_DATE_DAYS' => '',
            'F_USER'      => '',
        ];

        $params = \SP\Helper::getFromRequest([
            'F_SET_FILTER',
            'EXPORT_TO_XLS',

            'F_DATE_TYPE',
            'F_DATE_FROM',
            'F_DATE_TO',
            'F_DATE_DAYS',
            'F_USER',
            'param_filter_forTable'
        ]);


        

        self::$param_filter_forTable = $params['param_filter_forTable'];
      //self::fLog(self::$param_filter_forTable, 'self::$param_filter_forTable');


        $param_filter_forTable = $arResult['DATA_TITLES'];
        if (!in_array("all", $params['param_filter_forTable'])) {
            $param_filter_forTable = array();
            foreach ($params['param_filter_forTable'] as $key => $value) {
                if (array_key_exists($value, $arResult['DATA_TITLES'])) {
                    $param_filter_forTable[$value] = $arResult['DATA_TITLES'][$value];
                    if ($value == 'c_production' || $value == 'a_orders_shipped') {
                        $name = $value . '_summ';
                        $param_filter_forTable[$name] = $arResult['DATA_TITLES'][$name];
                    }
                }
            }
        }

        $arResult['DATA_TITLES'] = $param_filter_forTable;



        if (!$params['F_SET_FILTER'] and !$params['EXPORT_TO_XLS']) {
            $this->IncludeComponentTemplate();
            return;
        }

        // ### Обработка данных формы (
        $dateFormat = 'd.m.Y';

        // Тип периода
        if (isset($arResult['PERIODS'][$params['F_DATE_TYPE']])) {
            $arResult['FILTER']['F_DATE_TYPE'] = $params['F_DATE_TYPE'];
        }

        // Период "c"
        if ($dateFrom_TMP = DateTime::createFromFormat($dateFormat, $params['F_DATE_FROM'])) {
            $arResult['FILTER']['F_DATE_FROM'] = $dateFrom_TMP->format($dateFormat);
        }

        // Период "по"
        if ($dateTo_TMP = DateTime::createFromFormat($dateFormat, $params['F_DATE_TO'])) {
            $arResult['FILTER']['F_DATE_TO'] = $dateTo_TMP->format($dateFormat);
        }

        // Последние N дней
        $days_TMP = (int) $params['F_DATE_DAYS'];
        if ($days_TMP > 0) {
            $arResult['FILTER']['F_DATE_DAYS'] = $days_TMP;
        }

        // Пользователь
        if ($params['F_USER'] === 'all' or isset($arLists['users'][$params['F_USER']])) {
            $arResult['FILTER']['F_USER'] = $params['F_USER'];
        } else {
            $arResult['ERROR_MSG'][] = 'Выберите сотрудника';
        }

        if ($arResult['ERROR_MSG']) {
            $this->IncludeComponentTemplate();
            return;
        }
        // ### Обработка данных формы )

        // ### Период (
        $dateFrom = null;
        $dateTo   = null;

        switch ($arResult['FILTER']['F_DATE_TYPE']) {
            case 'month':
                $dateFrom = new DateTime('first day of this month');
                $dateTo   = new DateTime('last day of this month');
                break;
            case 'month_ago':
                $dateFrom = new DateTime('first day of previous month');
                $dateTo   = new DateTime('last day of previous month');
                break;
            case 'month_next':
                $dateFrom = new DateTime('first day of next month');
                $dateTo   = new DateTime('last day of next month');
                break;
            case 'week':
            case 'week_ago':
                $dateFrom = new DateTime();
                $dayOfWeek = $dateFrom->format('N');
                if ($days = $dayOfWeek - 1) {
                    $dateFrom->sub(new DateInterval("P{$days}D"));
                }

                if ($arResult['FILTER']['F_DATE_TYPE'] == 'week_ago') {
                    $dateFrom->sub(new DateInterval('P7D'));

                    $dateTo = new DateTime();
                    $dateTo->setTimestamp($dateFrom->getTimestamp());
                    $dateTo->add(new DateInterval('P6D'));
                }
                break;
            case 'days':
                // Последние N дней
                $dateFrom = new DateTime();
                if ($arResult['FILTER']['F_DATE_DAYS'] > 1) {
                    $days = $arResult['FILTER']['F_DATE_DAYS'] - 1;
                    $dateInterval = new DateInterval("P{$days}D");
                    $dateFrom->sub($dateInterval);
                }
                break;
            case 'after':
                if ($dateTo_TMP) {
                    $dateFrom = $dateTo_TMP;
                    $dateFrom->add(new DateInterval('P1D'));
                }
                break;
            case 'before':
                if ($dateFrom_TMP) {
                    $dateTo = $dateFrom_TMP;
                    $dateTo->sub(new DateInterval('P1D'));
                }
                break;
            case 'interval':
                if ($dateFrom_TMP) {
                    $dateFrom = $dateFrom_TMP;
                }
                if ($dateTo_TMP) {
                    $dateTo = $dateTo_TMP;
                }
                break;
            case 'all':
                break;
        } //

        $arResult['PERIOD_STR'] = 'за все время';

        if ($dateFrom or $dateTo) {
            $arResult['PERIOD_STR'] = '';

            if ($dateFrom) {
                $dateFrom->setTime(0, 0);
                //self::fLog($dateFrom->format('Y-m-d H:i:s'), '$dateFrom');

                $arResult['PERIOD_STR'] = 'c ' . $dateFrom->format($dateFormat);
            }

            if ($dateTo) {
                $dateTo->setTime(23, 59, 59);
                //self::fLog($dateTo->format('Y-m-d H:i:s'), '$dateTo');

                if ($arResult['PERIOD_STR']) {
                    $arResult['PERIOD_STR'] .= ' ';
                }
                $arResult['PERIOD_STR'] .= 'по ' . $dateTo->format($dateFormat);
            }
        } //
        // ### Период )

        if ($arResult['FILTER']['F_USER'] !== 'all') {
            $user_id              = $arResult['FILTER']['F_USER'];
            $user                 = $arLists['users'][$user_id];
            $arResult['USER_STR'] = "{$user['NAME']} {$user['LAST_NAME']}";
        } else {
            $user_id              = array_keys($arLists['users']);
            $arResult['USER_STR'] = 'Все сотрудники отдела';
        }
        //$user_id = ($arResult['FILTER']['F_USER'] === 'all') ? array_keys($arLists['users']) : $arResult['FILTER']['F_USER'];

        $arResult['DATA'] = self::fReport([
            'dateFrom' => $dateFrom,
            'dateTo'   => $dateTo,
            'user_id'  => $user_id,
        ]);

        if (count($arResult['DATA']) >= self::$limit) {
            $limit = self::$limit - 1;
            $arResult['DATA'] = array_slice($arResult['DATA'], 0, $limit, true);
            $arResult['DATA_LIMIT'] = $limit;
        }

        $arResult['DEAL_INFO'] = self::$arDealInfo;

        if ($params['EXPORT_TO_XLS']) {
            $res = self::fExport($arResult);

            if ($res['error_msg']) {
                self::$arError[] = $res['error_msg'];
            } else {
                self::$arDebug[] = [
                    'msg'   => $res['fileName'],
                    'label' => 'fExport: fileName',
                ];
            }
        } //

        $arResult['REPORT_ERROR_MSG'] = self::$arError;

        $arResult['DEBUG_MSG'] = self::$arDebug;

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



        // ### 1 # Активность по звонкам (осуществлено исходящих) # call_outgoing
        \Bitrix\Main\Loader::includeModule('voximplant');
        self::fReport_hlp_get_call_outgoing($result, $params);

        // ### 2 # Активность по звонкам (более 2 мин) # call_outgoing_2min
        self::fReport_hlp_get_call_outgoing_2min($result, $params);

        // ### 3 # Активность по e-mail (исходящие) # email_sent
        \Bitrix\Main\Loader::includeModule('mail');
        self::fReport_hlp_get_email_sent($result, $params);

        // Детализация: Активность по звонкам и email
        self::fReport_hlp_get_DetailСallAndEmail($result, $params);

        // ### 5 # Открыто Сделок в расчет ПТО # ИБ 14 "Расчет заказа Клиента" # calc_deal
        \Bitrix\Main\Loader::includeModule('iblock');
        self::fReport_hlp_get_calc_deal($result, $params);

        // ### 6 # Забраковано Сделок # deal_failed
        \Bitrix\Main\Loader::includeModule('crm');
        self::fReport_hlp_get_deal_failed($result, $params);

        // ### 7 # Открыто Испытаний # ИБ 25 "Процедура испытания изделия" # product_testing
        self::fReport_hlp_get_product_testing($result, $params);

        // ### 8 # Завершено Испытаний # Бизнес-процесс "Запуск процедуры испытания Изделия" # product_testing_complete
        \Bitrix\Main\Loader::includeModule('bizproc');
        self::fReport_hlp_get_product_testing_complete($result, $params);

        // ### 9 # Запущен БП Производства Заказа # ИБ 17 "Запуск Заказа в производство" # production
        self::fReport_hlp_get_production($result, $params);

        // ### 11 # Приход ДС (руб без НДС) # ИБ 53 "Поступления" # prihod_ds
        self::fReport_hlp_get_prihod_ds($result, $params);

        // ### 12 # Приход ДС (руб c НДС) # ИБ 53 "Поступления" # prihod_ds_whithNDS
        self::fReport_hlp_get_prihod_ds_whithNDS($result, $params);

        // ### 13 # Отгружено Заказов # orders_shipped
        self::fReport_hlp_get_orders_shipped($result, $params);

        // ### 14 # Cумма запущенных в пр-во заказов # ИБ 17 "Запуск Заказа в производство" # production_summ
        self::fReport_hlp_get_production_summ($result, $params);

        // ### 15 # Cумма отгруженных в пр-во заказов # ИБ 17 "Запуск Заказа в производство" # orders_shipped_summ
        self::fReport_hlp_get_orders_shipped_summ($result, $params);

        // ### 16 # Cумма отгруженных в пр-во заказов # ИБ 17 "Запуск Заказа в производство" # orders_shipped_summ
        self::fReport_hlp_get_conversion_company($result, $params);

        // ### 17 # Сумма сделки без НДС # ИБ 17 "Запуск Заказа в производство" # orders_shipped_summ
        self::fReport_hlp_get_summ_for_deal($result, $params);

        // ### 18 # Планируемые отгрузки # ИБ 17 "Запуск Заказа в производство" # orders_shipped_summ
        self::fReport_hlp_get_planned_shipments($result, $params);

        ksort($result);

        // $result[ time ]['detail'][ deal_id ]
        foreach ($result as $key => $value) {
            if ($result[$key]['detail']) {
                ksort($result[$key]['detail']);
            }
        }

        self::fReport_hlp_getDealInfo();

        // Итоги по каждому показателю за весь выбранный период
        self::fReport_hlp_get_total($result);

        return $result;
    } // function

    public static function fReport_hlp_queryCommon($params)
    {
        /*  Общее для всех query
            self::fReport_hlp_queryCommon([
                'result'    => &$result,
                'resultKey' => 'l_calc_deal',
                'query'     => $query,
                'fieldDate' => 'DATE',              // Название поля с датой
                'dateFrom'  => $params['dateFrom'],
                'dateTo'    => $params['dateTo'],
            ]);
        */
        if(!in_array($params["resultKey"], self::$param_filter_forTable)){
            return;
        }       
      //self::fLog('no return',$params["resultKey"]);
        $query = $params['query'];

        $query
            ->setSelect([
                'DATE_FOR_GROUP',
                new \Bitrix\Main\Entity\ExpressionField('CNT', 'COUNT(*)'),
            ])
            ->registerRuntimeField(new \Bitrix\Main\Entity\ExpressionField('DATE_FOR_GROUP', "DATE({$params['fieldDate']})"))
            ->setGroup(['DATE_FOR_GROUP'])
            ->setOrder(['DATE_FOR_GROUP' => 'ASC'])
            ->setLimit(self::$limit);

        self::fReport_hlp_queryDate([
            'query'     => $query,
            'fieldDate' => $params['fieldDate'],
            'dateFrom'  => $params['dateFrom'],
            'dateTo'    => $params['dateTo'],
        ]);

      //self::fLog_2($query->getQuery(), "query {$params['resultKey']}", ['pre' => true]);

        $res = $query->exec();
        while ($ar = $res->fetch()) {
            $time = $ar['DATE_FOR_GROUP']->getTimestamp();
            self::fReport_hlp_AddElement([
                'result' => &$params['result'],
                'time'   => $time,
            ]);

            $params['result'][$time]['items'][$params['resultKey']]['count'] = $ar['CNT'];
        } //

    } // function

    public static function fReport_hlp_queryDate($params)
    {
        /*  Фильтр по дате
            self::fReport_hlp_queryDate([
                'query'     => $query,
                'fieldDate' => 'DATE',              // Название поля с датой
                'dateFrom'  => $params['dateFrom'],
                'dateTo'    => $params['dateTo'],
            ]);
        */

        $query = $params['query'];

        if ($params['dateFrom']) {
            $time = $params['dateFrom']->getTimestamp();
            $time = \Bitrix\Main\Type\DateTime::createFromTimestamp($time);
            $query->addFilter(">={$params['fieldDate']}", $time);
        }

        if ($params['dateTo']) {
            $time = $params['dateTo']->getTimestamp();
            $time = \Bitrix\Main\Type\DateTime::createFromTimestamp($time);
            $query->addFilter("<={$params['fieldDate']}", $time);
        }
    } // function

    public static function fReport_hlp_CIBlockElement_FilterDate($params)
    {
        /*  Фильтр по дате для ИБ
            self::fReport_hlp_CIBlockElement_FilterDate([
                'dateFrom' => $params['dateFrom'],
                'dateTo'   => $params['dateTo'],
            ]);
        */

        $result = [];

        if ($params['dateFrom']) {
            $result['>=DATE_CREATE'] = ConvertTimeStamp($params['dateFrom']->getTimestamp(), 'FULL'); //$params['dateFrom']->format('Y-m-d H:i:s');
        }

        if ($params['dateTo']) {
            $result['<=DATE_CREATE'] = ConvertTimeStamp($params['dateTo']->getTimestamp(), 'FULL'); //$params['dateTo']->format('Y-m-d H:i:s');
        }

        return $result;
    } // function

    public static function fReport_hlp_AddElement($params)
    {
        /*  fReport_hlp_AddElement([
                'result'     => &$result,
                'time'       => $time,

                'deal_id'    => $ar['deal_ID'],
            ])

            $result[ time ]['items']['h_call_outgoing']['count']
            $result[ time ]['detail'][ deal_id ]['h_call_outgoing']['count']
            $result[ time ]['detail'][ deal_id ]['o_product_testing']['items'][ element_id ]['title']

            $result[ time ] = [
                'time' => '2019-01-01',     // Для наглядности
                'items' => [
                    'h_call_outgoing' => [
                        'count' => 0,
                    ],
                    ...
                    'f_prihod_ds' => [
                        'count' => 0,
                        'price' => 0,
                    ],
                ],
                'detail' => [
                    deal_id => [
                        'h_call_outgoing' => [
                            'count' => 0,
                        ],
                        ...,
                        'o_product_testing' => [
                            'count' => 0,
                            'items' => [
                                element_id => [
                                    'title' => '',
                                    'price' => 0,
                                    'link'  => '',
                                ],
                                ...
                            ],
                        ],
                    ],
                    ...
                    0 => [], // Сделка не указана
                ],
                'debug_info' => [],
            ]
        */
        
        $result = &$params['result'];
        $time   = $params['time'];

      //self::fLog($params);

        if (!self::$itemTemplate) {
            $value = ['count' => 0];

            self::$itemTemplate = [
                'time'      => '',
                'items'     => array_fill_keys(self::$itemKeys, $value),
                'detail'    => [],
                'debugInfo' => []
            ];
        }

        if (!isset($result[$time])) {
            $result[$time] = self::$itemTemplate;
            $result[$time]['time'] = date('Y-m-d', $time);
        }

        if (isset($params['deal_id'])) {
            if (!self::$itemDetailTemplate) {
                $value = ['count' => 0];

                self::$itemDetailTemplate = array_fill_keys(self::$itemKeys, $value);
            }

            if (!isset($result[$time]['detail'][$params['deal_id']])) {
                $result[$time]['detail'][$params['deal_id']] = self::$itemDetailTemplate;
                self::$arDealInfo[$params['deal_id']] = [
                    'title' => '',
                ];
            }
        }
    } // function

    // ###################################

    public static function fReport_hlp_get_call_outgoing(&$result, $params)
    {
        // ### 1 # Активность по звонкам (осуществлено исходящих) # call_outgoing
        if(!in_array('h_call_outgoing', self::$param_filter_forTable)){
            return;
        } 
      //self::fLog('no return','h_call_outgoing');


        $query = new \Bitrix\Main\Entity\Query(\Bitrix\Voximplant\StatisticTable::getEntity());
        $query->setFilter([
            '=PORTAL_USER_ID' => $params['user_id'],
            '=INCOMING'       => 1, // Исходящие
        ]);
        self::fReport_hlp_queryCommon([
            'result'    => &$result,
            'resultKey' => 'h_call_outgoing',
            'query'     => $query,
            'fieldDate' => 'CALL_START_DATE',
            'dateFrom'  => $params['dateFrom'],
            'dateTo'    => $params['dateTo'],
        ]);
    } // function

    public static function fReport_hlp_get_call_outgoing_2min(&$result, $params)
    {
        // ### 2 # Активность по звонкам (более 2 мин) # call_outgoing_2min

        $query = new \Bitrix\Main\Entity\Query(\Bitrix\Voximplant\StatisticTable::getEntity());
        $query->setFilter([
            '=PORTAL_USER_ID' => $params['user_id'],
            '=INCOMING'       => 1, // Исходящие
            '>CALL_DURATION' => (60 * 2),
        ]);
        self::fReport_hlp_queryCommon([
            'result'    => &$result,
            'resultKey' => 'i_call_outgoing_2min',
            'query'     => $query,
            'fieldDate' => 'CALL_START_DATE',
            'dateFrom'  => $params['dateFrom'],
            'dateTo'    => $params['dateTo'],
        ]);
    } // function

    public static function fReport_hlp_get_DetailСallAndEmail(&$result, $params)
    {
        // Детализация: Активность по звонкам и email
        

        global $DB;

        $where = '';
        if ($params['dateFrom']) {
            $date  = $params['dateFrom']->format('Y-m-d H:i:s');
            $where = " AND T_A.START_TIME >= '{$date}'";
        }
        if ($params['dateTo']) {
            $date   = $params['dateTo']->format('Y-m-d H:i:s');
            $where .= " AND T_A.START_TIME <= '{$date}'";
        }

        if (!is_array($params['user_id'])) {
            $user_id = (int) $params['user_id'];            // На всякий
            $where .= " AND T_A.AUTHOR_ID = {$user_id}";
        } else {
            $str = implode(', ', $params['user_id']);
            $where .= " AND T_A.AUTHOR_ID IN ({$str})";
        }

        $query = "SELECT
                T_TB.ENTITY_ID  AS deal_ID,        # ID сделки
                T_A.ID          AS act_ID,
                T_A.TYPE_ID     AS act_TYPE_ID,    # Call=2 Email=4
                T_A.START_TIME  AS act_START_TIME,
                T_A.END_TIME    AS act_STOP_TIME
            FROM 
                b_crm_timeline_bind AS T_TB,
                b_crm_timeline      AS T_T,
                b_crm_act           AS T_A
            WHERE 
                    T_TB.ENTITY_TYPE_ID           = 2          # Deal
                AND T_TB.OWNER_ID                 = T_T.ID     # reference to b_crm_timeline
                AND T_T.ASSOCIATED_ENTITY_TYPE_ID = 6          # Activity
                AND T_T.ASSOCIATED_ENTITY_ID      = T_A.ID     # reference to b_crm_act

                AND T_A.TYPE_ID IN (2, 4)                      # Call=2 Email=4
                AND T_A.DIRECTION                 = 2          # Outgoing=2
                AND (T_A.TYPE_ID != 2 OR T_A.ORIGIN_ID != '')  # Звонок состоялся (не просто запланирован, а есть запись звонка)
                {$where}
                #AND T_A.AUTHOR_ID                = {$user_id} # User
                #AND T_A.START_TIME              >= '2019-08-01'
                #AND T_A.START_TIME              <= '2019-08-01 23:59:59'
            ORDER BY 
                T_A.START_TIME
            #LIMIT 100
        ";
      //self::fLog_2($query, "query fReport_hlp_get_DetailСallAndEmail", ['pre' => true]);

        $res = $DB->Query($query);

        $arTotal = []; // "Итого" за каждую дату по каждому показателю посчитаем чтобы потом выяснить сколько событий "вне" сделок
        $arResultKey = [
            'h_call_outgoing',
            'i_call_outgoing_2min',
            'j_email_sent',
        ];
        $isFiltered = false;
        foreach ($arResultKey as $resKeyfil) {
             if (in_array($resKeyfil, self::$param_filter_forTable)) $isFiltered = true;
        }
        if(!$isFiltered){
            return;
        } 
      //self::fLog('no return','call_email');
        //$arDebug = [];

        while ($ar = $res->Fetch()) {
            //$arDebug[] = $ar;

            $time = new DateTime($ar['act_START_TIME']);
            $time->setTime(0, 0);
            $time = $time->getTimestamp();

            $timeStart = new DateTime($ar['act_START_TIME']);
            $timeStop  = new DateTime($ar['act_STOP_TIME']);
            $duration = $timeStop->getTimestamp() - $timeStart->getTimestamp();

            $deal_id = (int) $ar['deal_ID'];




            self::fReport_hlp_AddElement([
                'result'  => &$result,
                'time'    => $time,
                'deal_id' => $deal_id,
            ]);

            /*
                $result[ time ]['items']['h_call_outgoing']['count']
                $result[ time ]['detail'][ deal_id ]['h_call_outgoing']['count']
            */

            $arResultKeyTMP = [];
            if ($ar['act_TYPE_ID'] == 2) {
                // Call
                $arResultKeyTMP[] = 'h_call_outgoing';

                if ($duration > (60 * 2)) {
                    $arResultKeyTMP[] = 'i_call_outgoing_2min';
                }
            } else {
                // Email
                $arResultKeyTMP[] = 'j_email_sent';
            }

            //$result[ $time ]['items'][ $resultKey ]['count']++;
            foreach ($arResultKeyTMP as $resultKey) {
                if(!in_array($resultKey, self::$param_filter_forTable)){
                    continue;
                } 
              //self::fLog('no return',$resultKey);
                $result[$time]['detail'][$deal_id][$resultKey]['count']++;

                if (!isset($arTotal[$time])) {
                    $arTotal[$time] = array_fill_keys($arResultKey, 0);
                }
                $arTotal[$time][$resultKey]++;
            }
        } //

        foreach ($result as $time => $value) {
            foreach ($arResultKey as $resultKey) {
                if(!in_array($resultKey, self::$param_filter_forTable)){
                    continue;
                } 
              //self::fLog('no return',$resultKey);
                $count_1 = $result[$time]['items'][$resultKey]['count'];
                $count_2 = (!empty($arTotal[$time][$resultKey])) ? $arTotal[$time][$resultKey] : 0;

                if ($count_1 < $count_2) {
                    //self::$arError[] = "fReport_hlp_get_DetailСallAndEmail: ". date('Y-m-d', $time) ." {$resultKey}: count_1 < count_2";

                } elseif ($count_1 > $count_2) {
                    // Событие "вне" сделки
                    $deal_id = 0;
                    self::fReport_hlp_AddElement([
                        'result'  => &$result,
                        'time'    => $time,
                        'deal_id' => $deal_id,
                    ]);

                    $result[$time]['detail'][$deal_id][$resultKey]['count'] = $count_1 - $count_2;
                } //
            } //
        } //
        //self::fLog($arDebug, '$arDebug');
    } // function

    public static function fReport_hlp_get_email_sent(&$result, $params)
    {
        // ### 3 # Активность по e-mail (исходящие) # email_sent
        if(!in_array('j_email_sent', self::$param_filter_forTable)){
            return;
        } 
      //self::fLog('no return','j_email_sent');



        if (!is_array($params['user_id'])) {
            $mailboxes = \Bitrix\Mail\MailboxTable::getUserMailboxes($params['user_id']);
        } else {
            $mailboxes = [];
            foreach ($params['user_id'] as $user_id) {
                $mailboxesTMP = \Bitrix\Mail\MailboxTable::getUserMailboxes($user_id);
                foreach ($mailboxesTMP as $key => $value) {
                    if (!isset($mailboxes[$key])) {
                        $mailboxes[$key] = $value;
                    }
                }
            }
        }
        //self::fLog($mailboxes, '$mailboxes');

        if ($mailboxes) {
            // Есть почтовые ящики

            $mailboxFilter = [
                'LOGIC' => 'OR',
            ];

            foreach ($mailboxes as $item) {
                // Массив с хэшами названий папок (только тип "Отправленные")
                $arDIR_MD5 = array_map(
                    'md5',
                    (array) $item['OPTIONS']['imap'][\Bitrix\Mail\Helper\MessageFolder::OUTCOME]
                );

                $mailboxFilter[] = [
                    '=MMU.MAILBOX_ID' => $item['ID'],
                    '@MMU.DIR_MD5'    => $arDIR_MD5,
                ];
            } //
            //self::fLog($mailboxFilter, '$mailboxFilter');

            $query = new \Bitrix\Main\Entity\Query(\Bitrix\Mail\MailMessageTable::getEntity());
            $query
                // Связь с MailMessageUidTable
                ->registerRuntimeField('MMU', [
                    'data_type' => '\Bitrix\Mail\MailMessageUidTable',
                    'reference' => ['=this.ID' => 'ref.MESSAGE_ID'],
                ])
                ->setFilter([
                    $mailboxFilter,
                ]);

            self::fReport_hlp_queryCommon([
                'result'    => &$result,
                'resultKey' => 'j_email_sent',
                'query'     => $query,
                'fieldDate' => 'FIELD_DATE',
                'dateFrom'  => $params['dateFrom'],
                'dateTo'    => $params['dateTo'],
            ]);
        } //
    } // function

    public static function fReport_hlp_get_calc_deal(&$result, $params)
    {
        // ### 5 # Открыто Сделок в расчет ПТО # ИБ 14 "Расчет заказа Клиента" # calc_deal
        if(!in_array('l_calc_deal', self::$param_filter_forTable)){
            return;
        } 
      //self::fLog('no return','l_calc_deal');
        if (0) {
            $query = new \Bitrix\Main\Entity\Query(\Bitrix\Iblock\ElementTable::getEntity());
            $query->setFilter([
                'IBLOCK_ID'  => 14,                 // Расчет заказа Клиента
                'CREATED_BY' => $params['user_id'],
            ]);
            self::fReport_hlp_queryCommon([
                'result'    => &$result,
                'resultKey' => 'l_calc_deal',
                'query'     => $query,
                'fieldDate' => 'DATE_CREATE',
                'dateFrom'  => $params['dateFrom'],
                'dateTo'    => $params['dateTo'],
            ]);
        } else {
            $ar = [
                'resultKey'    => 'l_calc_deal',
                'IBLOCK_ID'    => 14,
                'PROP_PRICE'   => '', // Цены нет
                'PROP_TITLE'   => 'NAIMENOVANIE_ZAKAZA_NAPRIMER_FUTEROVKA_KOVSHA',
                'PROP_DEAL_ID' => 'NE_ZAPOLNYAT',
            ];
            $params = array_merge($params, $ar);
            self::fReport_hlp_get_product_testing_hlp($result, $params);
        } //
    } // function

    public static function fReport_hlp_get_deal_failed(&$result, $params)
    {
        // ### 6 # Забраковано Сделок # deal_failed
        if(!in_array("m_deal_failed", self::$param_filter_forTable)){
            return;
        } 
      //self::fLog('no return','m_deal_failed');

        if (0) {
            $query = new \Bitrix\Main\Entity\Query(\Bitrix\Crm\Timeline\Entity\TimelineTable::getEntity());
            $query->setFilter([
                '=TYPE_CATEGORY_ID'          => \Bitrix\Crm\Timeline\TimelineMarkType::FAILED, // =5
                '=ASSOCIATED_ENTITY_TYPE_ID' => \CCrmOwnerType::Deal, // =2
                '=AUTHOR_ID'                 => $params['user_id'],
            ]);
            self::fReport_hlp_queryCommon([
                'result'    => &$result,
                'resultKey' => 'm_deal_failed',
                'query'     => $query,
                'fieldDate' => 'CREATED',
                'dateFrom'  => $params['dateFrom'],
                'dateTo'    => $params['dateTo'],
            ]);
        } else {
            $resultKey = 'm_deal_failed';
            $fieldDate = 'CREATED';

            $query = new \Bitrix\Main\Entity\Query(\Bitrix\Crm\Timeline\Entity\TimelineTable::getEntity());
            $query
                ->setSelect([
                    'ID',
                    $fieldDate,
                    'ASSOCIATED_ENTITY_ID', // deal_id
                ])
                ->setFilter([
                    '=TYPE_CATEGORY_ID'          => \Bitrix\Crm\Timeline\TimelineMarkType::FAILED, // =5
                    '=ASSOCIATED_ENTITY_TYPE_ID' => \CCrmOwnerType::Deal, // =2
                    '=AUTHOR_ID'                 => $params['user_id'],
                ]);

            self::fReport_hlp_queryDate([
                'query'     => $query,
                'fieldDate' => $fieldDate,
                'dateFrom'  => $params['dateFrom'],
                'dateTo'    => $params['dateTo'],
            ]);

          //self::fLog_2($query->getQuery(), "query {$resultKey}", ['pre' => true]);

            $res = $query->exec();

            while ($ar = $res->fetch()) {
                //self::fLog($ar);
                $time = $ar[$fieldDate];
                $time->setTime(0, 0);
                $time = $time->getTimestamp();

                $deal_id = (int) $ar['ASSOCIATED_ENTITY_ID'];

                self::fReport_hlp_AddElement([
                    'result'  => &$result,
                    'time'    => $time,
                    'deal_id' => $deal_id,
                ]);

                $result[$time]['items'][$resultKey]['count']++;
                $result[$time]['detail'][$deal_id][$resultKey]['count']++;

                if ($result[$time]['detail'][$deal_id][$resultKey]['count'] > 1) {
                    //self::$arError[] = date('Y-m-d', $time);
                }
            } //
        } //
    } // function

    public static function fReport_hlp_get_product_testing(&$result, $params)
    {
        // ### 7 # Открыто Испытаний # ИБ 25 "Процедура испытания изделия" # product_testing
        if(!in_array('o_product_testing', self::$param_filter_forTable)){
            return;
        } 
      //self::fLog('no return','o_product_testing');
        if (0) {
            $query = new \Bitrix\Main\Entity\Query(\Bitrix\Iblock\ElementTable::getEntity());
            $query->setFilter([
                'IBLOCK_ID'  => 25,                 // Процедура испытания изделия
                'CREATED_BY' => $params['user_id'],
            ]);
            self::fReport_hlp_queryCommon([
                'result'    => &$result,
                'resultKey' => 'o_product_testing',
                'query'     => $query,
                'fieldDate' => 'DATE_CREATE',
                'dateFrom'  => $params['dateFrom'],
                'dateTo'    => $params['dateTo'],
            ]);
        } else {
            $ar = [
                'resultKey'    => 'o_product_testing',
                'IBLOCK_ID'    => 25,
                'PROP_PRICE'   => 'STOIMOST_ISPYTANIYA_RUB',
                'PROP_TITLE'   => 'NOMER_ZAKAZA',
                'PROP_DEAL_ID' => 'NOVOE_POLE',
            ];
            $params = array_merge($params, $ar);
            self::fReport_hlp_get_product_testing_hlp($result, $params);
        } //
    } // function

    public static function fReport_hlp_get_product_testing_hlp(&$result, $params)
    {
        $resultKey  = $params['resultKey'];
        $IBLOCK_ID  = $params['IBLOCK_ID'];

        $t_arFilter = [
            'IBLOCK_ID'       => $IBLOCK_ID,
            'CREATED_USER_ID' => $params['user_id'],
        ];

        if ($resultKey == "k_conversion_company") {
            unset($t_arFilter['CREATED_USER_ID']);
            $t_arFilter['PROPERTY_MENEDZHER_SDELKI_VALUE'] = $params['user_id'];
        }

        $res = self::fReport_hlp_CIBlockElement_FilterDate([
            'dateFrom' => $params['dateFrom'],
            'dateTo'   => $params['dateTo'],
        ]);
        $t_arFilter = array_merge($t_arFilter, $res);
        //self::fLog($t_arFilter, '$t_arFilter');

        $t_arSelectFields = [
            'ID',
            'DATE_CREATE',
            //"PROPERTY_{$params['PROP_PRICE']}",
            "PROPERTY_{$params['PROP_TITLE']}",
            "PROPERTY_{$params['PROP_DEAL_ID']}",
        ];
        if ($params['PROP_PRICE']) {
            $t_arSelectFields[] = "PROPERTY_{$params['PROP_PRICE']}";
        }

        $res = \CIBlockElement::GetList(
            $t_arOrder          = ['DATE_CREATE' => 'ASC'],
            $t_arFilter,
            $t_arGroupBy        = false,
            $t_arNavStartParams = false,
            $t_arSelectFields
        );

        while ($ar = $res->fetch()) {

            /*$varvarDUMP = json_encode($ar);
            echo "<script> console.log(\"ar)\")</script>";
            echo "<script> console.log({$varvarDUMP})</script>";*/




            $deal_id = (int) $ar["PROPERTY_{$params['PROP_DEAL_ID']}_VALUE"]; // 0 - не указана



            if ($resultKey == 'c_production_summ' || $resultKey == 'g_summ_for_deal' || $resultKey == 'e_planned_shipments') {

                $timeTMP = MakeTimeStamp($ar['DATE_CREATE']);
                $time = new DateTime();
                $time->setTimestamp($timeTMP);
                $time->setTime(0, 0);
                $time = $time->getTimestamp();

                self::fReport_hlp_AddElement([
                    'result'  => &$result,
                    'time'    => $time,
                    'deal_id' => $deal_id,
                ]);

                /*
                    $result[ time ]['items']['h_call_outgoing']['count']
                    $result[ time ]['detail'][ deal_id ]['h_call_outgoing']['count']
                    $result[ time ]['detail'][ deal_id ]['o_product_testing']['items'][ element_id ]['title']
                */

                $result[$time]['items'][$resultKey]['count']++;
                $result[$time]['detail'][$deal_id][$resultKey]['count']++;


                $str = str_replace(' ', '', $ar["PROPERTY_{$params['PROP_PRICE']}_VALUE"]);
                $arTMP = explode('|', $str);
                $arTMP1 = explode(',', $str);

                if ($arTMP1[1] != '00') {
                    $arTMP1[0] = floatval($arTMP1[0]) + ($arTMP1[1] * pow(10, -2));
                }

                $price = $arTMP1[0];
                $title = number_format($arTMP1[0], 2, ',', ' ');

                $result[$time]['items'][$resultKey]['price'] += $price;
                $result[$time]['detail'][$deal_id][$resultKey]['items'][$ar['ID']] = [
                    'title' => $title,
                ];
            } else if ($resultKey == 'k_conversion_company') {

                $query_company = new \Bitrix\Main\Entity\Query(\Bitrix\Crm\CompanyTable::getEntity());
                $query_company
                    ->setSelect(['ID', 'TITLE'])
                    ->setFilter([
                        'ID' => intval($ar["PROPERTY_{$params['PROP_TITLE']}_VALUE"]),
                    ]);

                $res_company = $query_company->exec();

                while ($company = $res_company->fetch()) {
                    $title_company = $company['TITLE'];
                }



                $res1 = CTasks::GetList(
                    array("TITLE" => "ASC"),
                    array("TITLE" => "%Отгрузка заказа КЛИЕНТУ", 'STATUS' => 5, 'UF_CRM_TASK' => 'D_' . $ar['PROPERTY_SDELKA_VALUE']),
                    array('UF_CRM_TASK', 'TITLE', "RESPONSIBLE_ID", 'ID', 'CLOSED_DATE', 'DESCRIPTION')
                );
                $taskArr = [];
                while ($arTask1 = $res1->GetNext()) {
                    $taskArr[] = $arTask1;
                }


                if (!$taskArr) continue; //если нет закрытых задач "Отгрузка товаров клиенту" по первой сделке компании

                 function cmp_function($a, $b)
                {

                    return (date($a['CLOSED_DATE']) > date($b['CLOSED_DATE']));
                }
                uasort($taskArr, 'cmp_function');


				//$timeTMP = MakeTimeStamp($ar['DATE_CREATE']);
                $timeTMP = MakeTimeStamp($taskArr[0]['CLOSED_DATE']);
                $time = new DateTime();
                $time->setTimestamp($timeTMP);
                $time->setTime(0, 0);
                $time = $time->getTimestamp();

                self::fReport_hlp_AddElement([
                    'result'  => &$result,
                    'time'    => $time,
                    'deal_id' => $deal_id,
                ]);

                /*
                    $result[ time ]['items']['h_call_outgoing']['count']
                    $result[ time ]['detail'][ deal_id ]['h_call_outgoing']['count']
                    $result[ time ]['detail'][ deal_id ]['o_product_testing']['items'][ element_id ]['title']
                */

                $result[$time]['items'][$resultKey]['count']++;
                $result[$time]['detail'][$deal_id][$resultKey]['count']++;



                $result[$time]['detail'][$deal_id][$resultKey]['items'][$ar['ID']] = [
                    'title' => $title_company,
                    'price' => ($params['PROP_PRICE']) ? $ar["PROPERTY_{$params['PROP_PRICE']}_VALUE"] : 0,
                    'link'  => "/crm/company/details/" . $ar["PROPERTY_{$params['PROP_TITLE']}_VALUE"] . "/",
                ];
            } else {


                $timeTMP = MakeTimeStamp($ar['DATE_CREATE']);
                $time = new DateTime();
                $time->setTimestamp($timeTMP);
                $time->setTime(0, 0);
                $time = $time->getTimestamp();

                self::fReport_hlp_AddElement([
                    'result'  => &$result,
                    'time'    => $time,
                    'deal_id' => $deal_id,
                ]);

                /*
                    $result[ time ]['items']['h_call_outgoing']['count']
                    $result[ time ]['detail'][ deal_id ]['h_call_outgoing']['count']
                    $result[ time ]['detail'][ deal_id ]['o_product_testing']['items'][ element_id ]['title']
                */

                $result[$time]['items'][$resultKey]['count']++;
                $result[$time]['detail'][$deal_id][$resultKey]['count']++;


                $result[$time]['detail'][$deal_id][$resultKey]['items'][$ar['ID']] = [
                    'title' => $ar["PROPERTY_{$params['PROP_TITLE']}_VALUE"],
                    'price' => ($params['PROP_PRICE']) ? $ar["PROPERTY_{$params['PROP_PRICE']}_VALUE"] : 0,
                    'link'  => "/bizproc/processes/{$IBLOCK_ID}/element/0/{$ar['ID']}/",
                ];
            }
        } //
    } // function

    public static function fReport_hlp_get_product_testing_complete(&$result, $params)
    {
        // ### 8 # Завершено Испытаний # Бизнес-процесс "Запуск процедуры испытания Изделия" # product_testing_complete

        // 25 ИБ "Процедура испытания изделия"
        // 67 Шаблон "Запуск процедуры испытания Изделия"
        // Инфоблок не указан т.к. его нет в WorkflowStateTable. Но это и не нужно - шаблон 67 привязан к ИБ 25.

        if(!in_array('p_product_testing_complete', self::$param_filter_forTable)){
            return;
        } 

        if (0) {
            $query = new \Bitrix\Main\Entity\Query(Bitrix\Bizproc\WorkflowStateTable::getEntity());
            $query->setFilter([
                '=MODULE_ID'            => 'lists',
                '=ENTITY'               => 'BizprocDocument',
                '=WORKFLOW_TEMPLATE_ID' => 67,                  // Шаблон "Запуск процедуры испытания Изделия"
                '=STATE'                => 'Completed',
                '=STARTED_BY'           => $params['user_id'],
            ]);
            self::fReport_hlp_queryCommon([
                'result'    => &$result,
                'resultKey' => 'p_product_testing_complete',
                'query'     => $query,
                'fieldDate' => 'MODIFIED',
                'dateFrom'  => $params['dateFrom'],
                'dateTo'    => $params['dateTo'],
            ]);
        } else {
            $resultKey = 'p_product_testing_complete';
            $fieldDate = 'MODIFIED';
            $IBLOCK_ID = 25;

            $arWorkflow  = []; // Список бизнес-процессов: ID, дата, id элемента ИБ к которому привязан бизнес-процесс
            $arIbElement = []; // Список элементов ИБ

            $query = new \Bitrix\Main\Entity\Query(Bitrix\Bizproc\WorkflowStateTable::getEntity());
            $query
                ->setSelect([
                    'ID',
                    $fieldDate,
                    'DOCUMENT_ID',
                ])
                ->setFilter([
                    '=MODULE_ID'            => 'lists',
                    '=ENTITY'               => 'BizprocDocument',
                    '=WORKFLOW_TEMPLATE_ID' => 67,                  // Шаблон "Запуск процедуры испытания Изделия"
                    '=STATE'                => 'Completed',
                    '=STARTED_BY'           => $params['user_id'],
                ])
                ->setOrder([$fieldDate => 'ASC']);

            self::fReport_hlp_queryDate([
                'query'     => $query,
                'fieldDate' => $fieldDate,
                'dateFrom'  => $params['dateFrom'],
                'dateTo'    => $params['dateTo'],
            ]);

          //self::fLog_2($query->getQuery(), "query {$resultKey}", ['pre' => true]);

            $res = $query->exec();

            while ($ar = $res->fetch()) {
                //self::fLog($ar);
                $time = $ar[$fieldDate];
                $time->setTime(0, 0);
                $time = $time->getTimestamp();

                self::fReport_hlp_AddElement([
                    'result' => &$result,
                    'time'   => $time,
                ]);

                $result[$time]['items'][$resultKey]['count']++;

                $ibElementId = $ar['DOCUMENT_ID'];

                $arWorkflow[$ar['ID']] = [
                    'time'        => $time,
                    'ibElementId' => $ibElementId,
                ];
                if (!$ibElementId) {
                    // Может быть не заполнен?
                    self::$arError[] = "fReport_hlp_get_product_testing_complete: бизнес-процесс {$ar['ID']}: не указан DOCUMENT_ID";
                } else {
                    $arIbElement[$ibElementId] = [];
                }
            } //

            if ($arIbElement) {
                // Получим данные элементов ИБ (название, цена, сделка) для детализации

                $PROP_PRICE   = 'STOIMOST_ISPYTANIYA_RUB';
                $PROP_TITLE   = 'NOMER_ZAKAZA';
                $PROP_DEAL_ID = 'NOVOE_POLE';

                $t_arSelectFields = [
                    'ID',
                    "PROPERTY_{$PROP_PRICE}",
                    "PROPERTY_{$PROP_TITLE}",
                    "PROPERTY_{$PROP_DEAL_ID}",
                ];

                $res = \CIBlockElement::GetList(
                    $t_arOrder          = ['ID' => 'ASC'],
                    $t_arFilter         = ['ID' => array_keys($arIbElement)],
                    $t_arGroupBy        = false,
                    $t_arNavStartParams = false,
                    $t_arSelectFields
                );
                while ($ar = $res->Fetch()) {
                    $arIbElement[$ar['ID']] = [
                        'PRICE'   => $ar["PROPERTY_{$PROP_PRICE}_VALUE"],
                        'TITLE'   => $ar["PROPERTY_{$PROP_TITLE}_VALUE"],
                        'DEAL_ID' => $ar["PROPERTY_{$PROP_DEAL_ID}_VALUE"], // Может быть не заполнено?
                    ];
                }
                //self::fLog($arIbElement, '$arIbElement');
            } //

            // Детализация
            foreach ($arWorkflow as $key_workflow_id => $value) { // ['time'=> , 'ibElementId'=> ]
                $time      = $value['time'];
                $ibElement = false;
                $deal_id   = 0;

                if ($value['ibElementId']) {
                    if (empty($arIbElement[$value['ibElementId']])) {
                        self::$arError[] = "fReport_hlp_get_product_testing_complete: бизнес-процесс {$key_workflow_id}: не найден DOCUMENT_ID {$value['ibElementId']}";
                    } else {
                        $ibElement = $arIbElement[$value['ibElementId']];
                        $deal_id   = (int) $ibElement['DEAL_ID'];
                    }
                }

                self::fReport_hlp_AddElement([
                    'result'  => &$result,
                    'time'    => $time,
                    'deal_id' => $deal_id,
                ]);

                $result[$time]['detail'][$deal_id][$resultKey]['count']++;

                if ($ibElement) {
                    $ar = [
                        'title' => $ibElement['TITLE'],
                        'price' => $ibElement['PRICE'],
                        'link'  => "/bizproc/processes/{$IBLOCK_ID}/element/0/{$value['ibElementId']}/",
                    ];
                } else {
                    $ar = [
                        'title' => "Бизнес-процесс {$key_workflow_id}",
                        'price' => '',
                        'link'  => '',
                    ];
                }
                //$result[ time ]['detail'][ deal_id ]['o_product_testing']['items'][ element_id ]['title']
                $result[$time]['detail'][$deal_id][$resultKey]['items'][$key_workflow_id] = $ar;
            } //
        } //
    } // function

    public static function fReport_hlp_get_production(&$result, $params)
    {
        // ### 9 # Запущен БП Производства Заказа # ИБ 17 "Запуск Заказа в производство" # production

        if(!in_array('c_production', self::$param_filter_forTable)){
            return;
        } 
      //self::fLog('no return','c_production');
        if (0) {
            $query = new \Bitrix\Main\Entity\Query(\Bitrix\Iblock\ElementTable::getEntity());
            $query->setFilter([
                'IBLOCK_ID'  => 17,                 // Запуск Заказа в производство
                'CREATED_BY' => $params['user_id'],
            ]);
            self::fReport_hlp_queryCommon([
                'result'    => &$result,
                'resultKey' => 'c_production',
                'query'     => $query,
                'fieldDate' => 'DATE_CREATE',
                'dateFrom'  => $params['dateFrom'],
                'dateTo'    => $params['dateTo'],
            ]);
        } else {
            $ar = [
                'resultKey'    => 'c_production',
                'IBLOCK_ID'    => 17,
                'PROP_PRICE'   => 'SUMMA_SDELKI_BEZ_NDS_PO_SPETSIFIKATSII',
                'PROP_TITLE'   => 'KOD_ZAKAZA',
                'PROP_DEAL_ID' => 'PRIVYAZKA',
            ];
            $params = array_merge($params, $ar);

            self::fReport_hlp_get_product_testing_hlp($result, $params);
        }
    } // function

    public static function fReport_hlp_get_prihod_ds(&$result, $params)
    {
        // ### 11 # Приход ДС (руб без НДС) # ИБ 53 "Поступления" # prihod_ds

        // Названия у "Поступления" нет (не используется)

        $resultKey    = 'f_prihod_ds';
        if(!in_array($resultKey, self::$param_filter_forTable)){
            return;
        } 
        $IBLOCK_ID    = 53;
        $PROP_DATE    = 'FAKTICHESKAYA_DATA_OPLATY';
        $PROP_PRICE   = 'SUMMA_BEZ_NDS';
        $PROP_DEAL_ID = 'SDELKA';

        $t_arFilter = [
            'IBLOCK_ID'           => $IBLOCK_ID,
            '=PROPERTY_MENEDZHER' => $params['user_id'],
        ];
        if ($params['dateFrom']) {
            $t_arFilter[">=PROPERTY_{$PROP_DATE}"] = $params['dateFrom']->format('Y-m-d'); //$params['dateFrom']->format('Y-m-d H:i:s');
        }
        if ($params['dateTo']) {
            $t_arFilter["<=PROPERTY_{$PROP_DATE}"] = $params['dateTo']->format('Y-m-d'); // $params['dateTo']->format('Y-m-d H:i:s');
        }
        //self::fLog($t_arFilter, '$t_arFilter');

        $t_arSelectFields = [
            'ID',
            "PROPERTY_{$PROP_DATE}",
            "PROPERTY_{$PROP_PRICE}",
            "PROPERTY_{$PROP_DEAL_ID}",
        ];

        $res = \CIBlockElement::GetList(
            $t_arOrder          = ["PROPERTY_{$PROP_DATE}" => 'ASC'],
            $t_arFilter,
            $t_arGroupBy        = false,
            $t_arNavStartParams = false,
            $t_arSelectFields
        );

        while ($ar = $res->fetch()) {
            //self::fLog($ar);
            $timeTMP = MakeTimeStamp($ar["PROPERTY_{$PROP_DATE}_VALUE"]);
            if (!$timeTMP) {
                continue; // На всякий
            }
            $time = new DateTime();
            $time->setTimestamp($timeTMP);
            $time->setTime(0, 0);
            $time = $time->getTimestamp();

            $deal_id = (int) $ar["PROPERTY_{$PROP_DEAL_ID}_VALUE"];

            self::fReport_hlp_AddElement([
                'result'  => &$result,
                'time'    => $time,
                'deal_id' => $deal_id,
            ]);

            $arTMP = explode('|', $ar["PROPERTY_{$PROP_PRICE}_VALUE"]);

            if (!$arTMP[1]) {
                $arTMP[1] = "RUB";
            }

            $price = (!empty($arTMP[1])) ? CCurrencyRates::ConvertCurrency($arTMP[0], $arTMP[1], 'RUB') : 0;

            /*
                $result[ time ]['items']['h_call_outgoing']['count']
                $result[ time ]['detail'][ deal_id ]['h_call_outgoing']['count']
                $result[ time ]['detail'][ deal_id ]['o_product_testing']['items'][ element_id ]['title']
            */

            $result[$time]['items'][$resultKey]['count']++;
            $result[$time]['items'][$resultKey]['price'] += $price;

            $result[$time]['detail'][$deal_id][$resultKey]['count']++;

            $result[$time]['detail'][$deal_id][$resultKey]['items'][$ar['ID']] = [
                'title' => '',
                'price' => $price,
                'link'  => "/bizproc/processes/{$IBLOCK_ID}/element/0/{$ar['ID']}/",
            ];
        } //
    } // function

    public static function fReport_hlp_get_prihod_ds_whithNDS(&$result, $params)
    {
        // ### 12 # Приход ДС (c НДС) # ИБ 53 "Поступления" # prihod_ds_whithNDS

        // Названия у "Поступления" нет (не используется)

        $resultKey    = 'q_prihod_ds_whithNDS';
        if(!in_array($resultKey, self::$param_filter_forTable)){
            return;
        } 
        $IBLOCK_ID    = 53;
        $PROP_DATE    = 'FAKTICHESKAYA_DATA_OPLATY';
        $PROP_PRICE   = 'SUMMA_S_NDS';
        $PROP_DEAL_ID = 'SDELKA';

        $t_arFilter = [
            'IBLOCK_ID'           => $IBLOCK_ID,
            '=PROPERTY_MENEDZHER' => $params['user_id'],
        ];
        if ($params['dateFrom']) {
            $t_arFilter[">=PROPERTY_{$PROP_DATE}"] = $params['dateFrom']->format('Y-m-d'); //$params['dateFrom']->format('Y-m-d H:i:s');
        }
        if ($params['dateTo']) {
            $t_arFilter["<=PROPERTY_{$PROP_DATE}"] = $params['dateTo']->format('Y-m-d'); // $params['dateTo']->format('Y-m-d H:i:s');
        }
        //self::fLog($t_arFilter, '$t_arFilter');

        $t_arSelectFields = [
            'ID',
            "PROPERTY_{$PROP_DATE}",
            "PROPERTY_{$PROP_PRICE}",
            "PROPERTY_{$PROP_DEAL_ID}",
        ];

        $res = \CIBlockElement::GetList(
            $t_arOrder          = ["PROPERTY_{$PROP_DATE}" => 'ASC'],
            $t_arFilter,
            $t_arGroupBy        = false,
            $t_arNavStartParams = false,
            $t_arSelectFields
        );

        while ($ar = $res->fetch()) {
            //self::fLog($ar);
            $timeTMP = MakeTimeStamp($ar["PROPERTY_{$PROP_DATE}_VALUE"]);
            if (!$timeTMP) {
                continue; // На всякий
            }
            $time = new DateTime();
            $time->setTimestamp($timeTMP);
            $time->setTime(0, 0);
            $time = $time->getTimestamp();

            $deal_id = (int) $ar["PROPERTY_{$PROP_DEAL_ID}_VALUE"];

            self::fReport_hlp_AddElement([
                'result'  => &$result,
                'time'    => $time,
                'deal_id' => $deal_id,
            ]);

            $arTMP = explode('|', $ar["PROPERTY_{$PROP_PRICE}_VALUE"]);
            $price = (!empty($arTMP[1])) ? CCurrencyRates::ConvertCurrency($arTMP[0], $arTMP[1], 'RUB') : 0;

            /*
                $result[ time ]['items']['h_call_outgoing']['count']
                $result[ time ]['detail'][ deal_id ]['h_call_outgoing']['count']
                $result[ time ]['detail'][ deal_id ]['o_product_testing']['items'][ element_id ]['title']
            */

            $result[$time]['items'][$resultKey]['count']++;
            $result[$time]['items'][$resultKey]['price'] += $price;

            $result[$time]['detail'][$deal_id][$resultKey]['count']++;

            $result[$time]['detail'][$deal_id][$resultKey]['items'][$ar['ID']] = [
                'title' => '',
                'price' => $price,
                'link'  => "/bizproc/processes/{$IBLOCK_ID}/element/0/{$ar['ID']}/",
            ];
        } //
    } // function

    public static function fReport_hlp_get_orders_shipped(&$result, $params)
    {
        // ### 13 # Открыто Испытаний # ИБ 17 "Запуск Заказа в производство" # orders_shipped

        //self::fLog($params, '$params');

        $resultKey    = 'a_orders_shipped';
        if(!in_array($resultKey, self::$param_filter_forTable)){
            return;
        }
      //self::fLog('no return','a_orders_shipped');
        // Выбираем все задачи пользователя с ID = 22
        if (!CModule::IncludeModule("tasks")) return;

        if (is_null($params['dateTo'])) {
            $t_arFilter = array("TITLE" => "%произвести заказ%", "RESPONSIBLE_ID" => "22", '>=CLOSED_DATE' => ($params['dateFrom'])?$params['dateFrom']->format('d.m.Y'):'');
        } else {
            $t_arFilter = array(
                "TITLE" => "%произвести заказ%",
                "RESPONSIBLE_ID" => "22",
                '>=CLOSED_DATE' => $params['dateFrom']->format('d.m.Y'),
                '<=CLOSED_DATE' => $params['dateTo']->format('d.m.Y')
            );
        }

        $res = CTasks::GetList(
            array("TITLE" => "ASC"),
            $t_arFilter,
            array('UF_CRM_TASK', 'CLOSED_DATE', 'DESCRIPTION', 'ID')
        );

        while ($arTask = $res->GetNext()) {

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
            if ($ar = $rsDeal->GetNext()) {
                self::$responsibleIdForDeal[$ar['ID']] = $ar['ASSIGNED_BY_ID'];
            }

            if (!is_array($params['user_id']) && ($params['user_id'])) {
                if ($params['user_id'] != self::$responsibleIdForDeal[$arTask['UF_CRM_TASK']]) continue;
            }

            unset($tdItem);

            $timeTMP = MakeTimeStamp($arTask["CLOSED_DATE"]);

            if (!$timeTMP) {
                continue; // На всякий
            }

            $timeTMP = MakeTimeStamp($arTask['CLOSED_DATE']);
            $time = new DateTime();
            $time->setTimestamp($timeTMP);
            $time->setTime(0, 0);
            $time = $time->getTimestamp();

            $deal_id = (int) $arTask['UF_CRM_TASK']; // 0 - не указана

            self::fReport_hlp_AddElement([
                'result'  => &$result,
                'time'    => $time,
                'deal_id' => $deal_id,
            ]);

            $result[$time]['items'][$resultKey]['count']++;
            $result[$time]['detail'][$deal_id][$resultKey]['count']++;

            $result[$time]['detail'][$deal_id][$resultKey]['items'][$arTask['ID']] = [
                'title' => '1',
                //'price' => "1",
                //'link'  => "/company/personal/user/22/tasks/task/view/{$arTask['ID']}/",
            ];
        }


        /* if (is_null($params['dateTo'])) {
           $t_arFilter = [
            'IBLOCK_ID'       => 17,
            'CREATED_USER_ID' => $params['user_id'],
            '>='.'PROPERTY_DATA_OTGRUZKI' => $params['dateFrom']->format('Y-m-d'),
         ]; 
        } else {
            $t_arFilter = [
            'IBLOCK_ID'       => 17,
            'CREATED_USER_ID' => $params['user_id'],
            '>='.'PROPERTY_DATA_OTGRUZKI' => $params['dateFrom']->format('Y-m-d'),
            '<='.'PROPERTY_DATA_OTGRUZKI' => $params['dateTo']->format('Y-m-d'),
         ];
        }


        //self::fLog($t_arFilter, '$t_arFilter');

        $t_arSelectFields = [
            'ID',
            'PROPERTY_DATA_OTGRUZKI',
            'PROPERTY_PRIVYAZKA'
        ];


         $res = \CIBlockElement::GetList( array(), $t_arFilter, false, false, $t_arSelectFields);

         while ($ar = $res->fetch()) {

             //self::fLog($ar, "$arr");
             $timeTMP = MakeTimeStamp($ar["PROPERTY_DATA_OTGRUZKI_VALUE"]);
            if (!$timeTMP) {
                continue; // На всякий
            }
             $timeTMP = MakeTimeStamp($ar['PROPERTY_DATA_OTGRUZKI_VALUE']);
             $time = new DateTime();
             $time->setTimestamp($timeTMP);
             $time->setTime(0, 0);
             $time = $time->getTimestamp();

             $deal_id = (int) $ar['PROPERTY_PRIVYAZKA_VALUE']; // 0 - не указана

            self::fReport_hlp_AddElement([
                'result'  => &$result,
                'time'    => $time,
                'deal_id' => $deal_id,
            ]);

            /*
                $result[ time ]['items']['h_call_outgoing']['count']
                $result[ time ]['detail'][ deal_id ]['h_call_outgoing']['count']
                $result[ time ]['detail'][ deal_id ]['o_product_testing']['items'][ element_id ]['title']


            $result[ $time ]['items'][ $resultKey ]['count']++;
            $result[ $time ]['detail'][ $deal_id ][ $resultKey ]['count']++;

            $result[ $time ]['detail'][ $deal_id ][ $resultKey ]['items'][ $ar['ID'] ] = [
                'title' => "",
                'price' => "1",
                'link'  => "/bizproc/processes/17/element/0/{$ar['ID']}/",//"bizproc/processes/{$IBLOCK_ID}/element/0/{$ar['PROPERTY_PRIVYAZKA_VALUE']}/"
            ];
         };*/
    } // function

    public static function fReport_hlp_get_production_summ(&$result, $params)
    {

        // ### 14 # Запущен БП Производства Заказа # ИБ 17 "Запуск Заказа в производство" # production_summ
        if(!in_array('c_production_summ', self::$param_filter_forTable)){
            return;
        } 
      //self::fLog('no return','c_production_summ');
        if (0) {
            $query = new \Bitrix\Main\Entity\Query(\Bitrix\Iblock\ElementTable::getEntity());
            $query->setFilter([
                'IBLOCK_ID'  => 17,                 // Запуск Заказа в производство
                'CREATED_BY' => $params['user_id'],
            ]);
            self::fReport_hlp_queryCommon([
                'result'    => &$result,
                'resultKey' => 'c_production_summ',
                'query'     => $query,
                'fieldDate' => 'DATE_CREATE',
                'dateFrom'  => $params['dateFrom'],
                'dateTo'    => $params['dateTo'],
            ]);
        } else {
            $ar = [
                'resultKey'    => 'c_production_summ',
                'IBLOCK_ID'    => 17,
                'PROP_PRICE'   => 'SUMMA_SDELKI_BEZ_NDS_PO_SPETSIFIKATSII',
                'PROP_DEAL_ID' => 'PRIVYAZKA',
            ];
            $params = array_merge($params, $ar);

            self::fReport_hlp_get_product_testing_hlp($result, $params);
        }
    } // function 

    public static function fReport_hlp_get_orders_shipped_summ(&$result, $params)
    {

        // ### 15 # Запущен БП Производства Заказа # ИБ 17 "Запуск Заказа в производство" # production_summ

        $resultKey    = 'a_orders_shipped_summ';
        if(!in_array($resultKey, self::$param_filter_forTable)){
            return;
        } 
      //self::fLog('no return','a_orders_shipped_summ');
        /*$varvarDUMP = json_encode($params);
        echo "<script> console.log(\"params\")</script>";
        echo "<script> console.log({$varvarDUMP})</script>";*/

        // Выбираем все задачи пользователя с ID = 22
        if (!CModule::IncludeModule("tasks")) return;

        if (is_null($params['dateTo'])) {
            $t_arFilter = array("TITLE" => "%произвести заказ%", "RESPONSIBLE_ID" => "22", '>=CLOSED_DATE' => ($params['dateFrom'])?$params['dateFrom']->format('d.m.Y'):'');
        } else {
            $t_arFilter = array(
                "TITLE" => "%произвести заказ%",
                "RESPONSIBLE_ID" => "22",
                '>=CLOSED_DATE' => $params['dateFrom']->format('d.m.Y'),
                '<=CLOSED_DATE' => $params['dateTo']->format('d.m.Y')
            );
        }

        $res = CTasks::GetList(
            array("TITLE" => "ASC"),
            $t_arFilter,
            array('UF_CRM_TASK', 'CLOSED_DATE', 'DESCRIPTION', 'ID')
        );

        while ($arTask = $res->GetNext()) {
            //echo "Task name: ".$arTask["TITLE"]."<br>";
            // разбиваем description на <tr>
            $arrTrTableDescriptonTask = explode('[/TR]', $arTask['DESCRIPTION']);
            foreach ($arrTrTableDescriptonTask as $trItem) {
                if (strrpos($trItem, 'Стоимость Заказа по Спецификации без НДС') > 0)
                    $tdItem = explode('[/TD]', $trItem);  //// разбиваем <tr> на <td>
            }
            $arTask['SUMMA_SDELKI_BEZ_NDS'] = str_replace('[TD]', '', trim($tdItem[1], '|RUB')) * 1;
            foreach ($arTask['UF_CRM_TASK'] as $crm) {
                if (strrpos($crm, 'D_') === 0) {
                    $arTask['UF_CRM_TASK'] = str_replace('D_', '', $crm);
                }
            }

            if (!is_array($params['user_id']) && ($params['user_id'])) {
                if ($params['user_id'] != self::$responsibleIdForDeal[$arTask['UF_CRM_TASK']]) continue;
            }

            unset($tdItem);

            $timeTMP = MakeTimeStamp($arTask["CLOSED_DATE"]);

            if (!$timeTMP) {
                continue; // На всякий
            }

            $timeTMP = MakeTimeStamp($arTask['CLOSED_DATE']);
            $time = new DateTime();
            $time->setTimestamp($timeTMP);
            $time->setTime(0, 0);
            $time = $time->getTimestamp();

            $deal_id = (int) $arTask['UF_CRM_TASK']; // 0 - не указана

            self::fReport_hlp_AddElement([
                'result'  => &$result,
                'time'    => $time,
                'deal_id' => $deal_id,
            ]);

            $result[$time]['items'][$resultKey]['count']++;
            $result[$time]['detail'][$deal_id][$resultKey]['count']++;

            $str = str_replace(' ', '', $arTask["SUMMA_SDELKI_BEZ_NDS"]);
            $arTMP = explode('|', $str);
            $arTMP1 = explode(',', $str);

            if ($arTMP1[1] != '00') {
                $arTMP1[0] = floatval($arTMP1[0]) + ($arTMP1[1] * pow(10, -2));
            }

            $price = $arTMP1[0];
            $title = number_format($arTMP1[0], 2, ',', ' ');

            $result[$time]['items'][$resultKey]['price'] += $price;
            $result[$time]['detail'][$deal_id][$resultKey]['items'][$arTask['ID']] = [
                'title' => $title,
                'link'  => "/company/personal/user/22/tasks/task/view/{$arTask['ID']}/",
            ];
        }




        // if (is_null($params['dateTo'])) {
        //    $t_arFilter = [
        //     'IBLOCK_ID'       => 17,
        //     'CREATED_USER_ID' => $params['user_id'],
        //     '>='.'PROPERTY_DATA_OTGRUZKI' => $params['dateFrom']->format('Y-m-d'),
        //  ]; 
        // } else {
        //     $t_arFilter = [
        //     'IBLOCK_ID'       => 17,
        //     'CREATED_USER_ID' => $params['user_id'],
        //     '>='.'PROPERTY_DATA_OTGRUZKI' => $params['dateFrom']->format('Y-m-d'),
        //     '<='.'PROPERTY_DATA_OTGRUZKI' => $params['dateTo']->format('Y-m-d'),
        //  ];
        // }

        // //self::fLog($t_arFilter, '$t_arFilter');

        // $t_arSelectFields = [
        //     'ID',
        //     'PROPERTY_DATA_OTGRUZKI',
        //     'PROPERTY_PRIVYAZKA',
        //     'PROPERTY_SUMMA_SDELKI_BEZ_NDS_PO_SPETSIFIKATSII',
        // ];


        //  $res = \CIBlockElement::GetList( array(), $t_arFilter, false, false, $t_arSelectFields);

        //  while ($ar = $res->fetch()) {
        //     $timeTMP = MakeTimeStamp($ar["PROPERTY_DATA_OTGRUZKI_VALUE"]);

        //     if (!$timeTMP) {
        //         continue; // На всякий
        //     }

        //     $timeTMP = MakeTimeStamp($ar['PROPERTY_DATA_OTGRUZKI_VALUE']);
        //     $time = new DateTime();
        //     $time->setTimestamp($timeTMP);
        //     $time->setTime(0, 0);
        //     $time = $time->getTimestamp();

        //     $deal_id = (int) $ar['PROPERTY_PRIVYAZKA_VALUE']; // 0 - не указана

        //     self::fReport_hlp_AddElement([
        //         'result'  => &$result,
        //         'time'    => $time,
        //         'deal_id' => $deal_id,
        //     ]);

        //     $result[ $time ]['items'][ $resultKey ]['count']++;
        //     $result[ $time ]['detail'][ $deal_id ][ $resultKey ]['count']++;

        //     $str = str_replace(' ', '', $ar["PROPERTY_SUMMA_SDELKI_BEZ_NDS_PO_SPETSIFIKATSII_VALUE"]);
        //     $arTMP = explode('|', $str);
        //     $arTMP1 = explode(',', $str);

        //     if ($arTMP1[1] != '00') {
        //         $arTMP1[0] = floatval($arTMP1[0])+($arTMP1[1]*pow(10, -2));
        //     }

        //     $price = $arTMP1[0];
        //     $title = number_format($arTMP1[0], 2, ',', ' ');

        //     $result[ $time ]['items'][ $resultKey ]['price'] += $price;
        //     $result[ $time ]['detail'][ $deal_id ][ $resultKey ]['items'][ $ar['ID'] ] = [
        //         'title' => $title,
        //     ];
        //  };

    } // function


    public static function fReport_hlp_get_summ_for_deal(&$result, $params)
    {

        // ### 17 # Сумма сделки без НДС # ИБ 17 "Запуск Заказа в производство" # summ_for_deal

        $resultKey    = 'g_summ_for_deal';
        if(!in_array($resultKey, self::$param_filter_forTable)){
            return;
        } 
        if (is_null($params['dateTo'])) {
            $t_arFilter = [
                'IBLOCK_ID'       => 17,
                'CREATED_USER_ID' => $params['user_id'],
                '>=' . 'PROPERTY_DATA_DATY_PRIKHODA_DS_PO_POSTAVKE_ISKHODYA_IZ_DATY' => ($params['dateFrom'])?$params['dateFrom']->format('d.m.Y'):'',
            ];
        } else {
            $t_arFilter = [
                'IBLOCK_ID'       => 17,
                'CREATED_USER_ID' => $params['user_id'],
                '>=' . 'PROPERTY_DATA_DATY_PRIKHODA_DS_PO_POSTAVKE_ISKHODYA_IZ_DATY' => $params['dateFrom']->format('Y-m-d'),
                '<=' . 'PROPERTY_DATA_DATY_PRIKHODA_DS_PO_POSTAVKE_ISKHODYA_IZ_DATY' => $params['dateTo']->format('Y-m-d'),
            ];
        }

        //self::fLog($t_arFilter, '$t_arFilter');

        $t_arSelectFields = [
            'ID',
            'PROPERTY_DATA_DATY_PRIKHODA_DS_PO_POSTAVKE_ISKHODYA_IZ_DATY',
            'PROPERTY_PRIVYAZKA',
            'PROPERTY_SUMMA_SDELKI_BEZ_NDS_PO_SPETSIFIKATSII',
            'PROPERTY_PREDOPLATA_PO_SPETSIFIKATSII',
        ];


        $res = \CIBlockElement::GetList(array(), $t_arFilter, false, false, $t_arSelectFields);

        while ($ar = $res->fetch()) {
            $timeTMP = MakeTimeStamp($ar["PROPERTY_DATA_DATY_PRIKHODA_DS_PO_POSTAVKE_ISKHODYA_IZ_DATY_VALUE"]);

            if (!$timeTMP) {
                continue; // На всякий
            }

            $timeTMP = MakeTimeStamp($ar['PROPERTY_DATA_DATY_PRIKHODA_DS_PO_POSTAVKE_ISKHODYA_IZ_DATY_VALUE']);
            $time = new DateTime();
            $time->setTimestamp($timeTMP);
            $time->setTime(0, 0);
            $time = $time->getTimestamp();

            $deal_id = (int) $ar['PROPERTY_PRIVYAZKA_VALUE']; // 0 - не указана

            self::fReport_hlp_AddElement([
                'result'  => &$result,
                'time'    => $time,
                'deal_id' => $deal_id,
            ]);

            $result[$time]['items'][$resultKey]['count']++;
            $result[$time]['detail'][$deal_id][$resultKey]['count']++;

            $str = str_replace(' ', '', ($ar["PROPERTY_SUMMA_SDELKI_BEZ_NDS_PO_SPETSIFIKATSII_VALUE"] * 1 - $ar["PROPERTY_PREDOPLATA_PO_SPETSIFIKATSII_VALUE"] * 1));
            //self::fLog($str, '$str');
            $arTMP = explode('|', $str);
            $arTMP1 = explode(',', $str);

            if ($arTMP1[1] != '00') {
                $arTMP1[0] = floatval($arTMP1[0]) + ($arTMP1[1] * pow(10, -2));
            }

            $price = $arTMP1[0];
            $title = number_format($arTMP1[0], 2, ',', ' ');

            $result[$time]['items'][$resultKey]['price'] += $price;
            $result[$time]['detail'][$deal_id][$resultKey]['items'][$ar['ID']] = [
                'title' => $title,
            ];
        };
    } // function


    public static function fReport_hlp_get_planned_shipments(&$result, $params)
    {

        // ### 18 # Планируемые отгрузки # ИБ 17 "Запуск Заказа в производство" # planned_shipments

        $resultKey    = 'e_planned_shipments';
        if(!in_array($resultKey, self::$param_filter_forTable)){
            return;
        } 
      //self::fLog('no return','e_planned_shipments');
        if (is_null($params['dateTo'])) {
            $t_arFilter = [
                'IBLOCK_ID'       => 17,
                'CREATED_USER_ID' => $params['user_id'],
                '>=' . 'PROPERTY_NA_KAKUYU_DATU_SOGLASOVAN_SROK_PROIZVODSTVA' => ($params['dateFrom'])?$params['dateFrom']->format('d.m.Y'):'',
            ];
        } else {
            $t_arFilter = [
                'IBLOCK_ID'       => 17,
                'CREATED_USER_ID' => $params['user_id'],
                '>=' . 'PROPERTY_NA_KAKUYU_DATU_SOGLASOVAN_SROK_PROIZVODSTVA' => $params['dateFrom']->format('Y-m-d'),
                '<=' . 'PROPERTY_NA_KAKUYU_DATU_SOGLASOVAN_SROK_PROIZVODSTVA' => $params['dateTo']->format('Y-m-d'),
            ];
        }

        //self::fLog($t_arFilter, '$t_arFilter');

        $t_arSelectFields = [
            'ID',
            'PROPERTY_NA_KAKUYU_DATU_SOGLASOVAN_SROK_PROIZVODSTVA',
            'PROPERTY_PRIVYAZKA',
            'PROPERTY_SUMMA_SDELKI_BEZ_NDS_PO_SPETSIFIKATSII',

        ];


        $res = \CIBlockElement::GetList(array(), $t_arFilter, false, false, $t_arSelectFields);

        while ($ar = $res->fetch()) {
            
            $timeTMP = MakeTimeStamp($ar["PROPERTY_NA_KAKUYU_DATU_SOGLASOVAN_SROK_PROIZVODSTVA_VALUE"]);

            if (!$timeTMP) {
                continue; // На всякий
            }

            $timeTMP = MakeTimeStamp($ar['PROPERTY_NA_KAKUYU_DATU_SOGLASOVAN_SROK_PROIZVODSTVA_VALUE']);
            $time = new DateTime();
            $time->setTimestamp($timeTMP);
            $time->setTime(0, 0);
            $time = $time->getTimestamp();

            $deal_id = (int) $ar['PROPERTY_PRIVYAZKA_VALUE']; // 0 - не указана

            self::fReport_hlp_AddElement([
                'result'  => &$result,
                'time'    => $time,
                'deal_id' => $deal_id,
            ]);

            $result[$time]['items'][$resultKey]['count']++;
            $result[$time]['detail'][$deal_id][$resultKey]['count']++;

            $str = str_replace(' ', '', ($ar["PROPERTY_SUMMA_SDELKI_BEZ_NDS_PO_SPETSIFIKATSII_VALUE"] * 1));
            //self::fLog($str, '$str');
            $arTMP = explode('|', $str);
            $arTMP1 = explode(',', $str);

            if ($arTMP1[1] != '00') {
                $arTMP1[0] = floatval($arTMP1[0]) + ($arTMP1[1] * pow(10, -2));
            }

            $price = $arTMP1[0];
            $title = number_format($arTMP1[0], 2, ',', ' ');

            $result[$time]['items'][$resultKey]['price'] += $price;
            $result[$time]['detail'][$deal_id][$resultKey]['items'][$ar['ID']] = [
                'title' => $title,
            ];
        };
    } // function



    public static function fReport_hlp_get_conversion_company(&$result, $params)
    {
        if(!in_array('k_conversion_company', self::$param_filter_forTable)){
            return;
        } 
        $ar = [
            'resultKey'    => 'k_conversion_company',
            'IBLOCK_ID'    => 55,
            'PROP_PRICE'   => '', // Цены нет
            'PROP_TITLE'   => 'KOMPANIYA',
            'PROP_DEAL_ID' => 'SDELKA',
        ];



        $params = array_merge($params, $ar);
        self::fReport_hlp_get_product_testing_hlp($result, $params);
    }

    public static function fReport_hlp_get_total(&$result)
    {
        // Итог за выбранный период

        // $result[ time ]['items']['h_call_outgoing']['count']

        $value = ['count' => 0];
        $total['items'] = array_fill_keys(self::$itemKeys, $value);
        $total['items']['f_prihod_ds']['price'] = 0;
        $total['items']['q_prihod_ds_whithNDS']['price'] = 0;
        $total['items']['c_production_summ']['price'] = 0;
        $total['items']['g_summ_for_deal']['price'] = 0;
        $total['items']['e_planned_shipments']['price'] = 0;

        foreach ($result as $value) {
            foreach ($value['items'] as $resultKey => $value_2) {
                $total['items'][$resultKey]['count'] += $value_2['count'];
                if ($resultKey == 'f_prihod_ds') {
                    $total['items'][$resultKey]['price'] += $value_2['price'];
                }
                if ($resultKey == 'q_prihod_ds_whithNDS') {
                    $total['items'][$resultKey]['price'] += $value_2['price'];
                }
                if ($resultKey == 'c_production_summ') {
                    $total['items'][$resultKey]['price'] += $value_2['price'];
                }
                if ($resultKey == 'a_orders_shipped_summ') {
                    $total['items'][$resultKey]['price'] += $value_2['price'];
                }
                if ($resultKey == 'g_summ_for_deal') {
                    $total['items'][$resultKey]['price'] += $value_2['price'];
                }
                if ($resultKey == 'e_planned_shipments') {
                    $total['items'][$resultKey]['price'] += $value_2['price'];
                }
            }
        } //

        $result['total'] = $total;
    } // function

    public static function fReport_hlp_getDealInfo()
    {
        if (!self::$arDealInfo) {
            return;
        }

        $query = new \Bitrix\Main\Entity\Query(\Bitrix\Crm\DealTable::getEntity());
        $query
            ->setSelect(['ID', 'TITLE'])
            ->setFilter([
                '@ID' => array_keys(self::$arDealInfo),
            ]);

        $res = $query->exec();

        //self::fLog_2($query->getQuery(), "query {$resultKey}");

        while ($ar = $res->fetch()) {
            self::$arDealInfo[$ar['ID']]['title'] = $ar['TITLE'];
        }
    } // function

    public function fExport($arResult)
    {
        require_once $_SERVER['DOCUMENT_ROOT'] . '/local/composer/vendor/autoload.php';

        $result = [
            'error_msg' => '',
            'fileName'  => '',
        ];

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

        $font = $spreadsheet->getDefaultStyle()->getFont();
        $font->setName('Times New Roman');
        $font->setSize(10);

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Отчет');

        // Заголовок "Период"
        $sheet->setCellValueByColumnAndRow(1, 1, "Период: {$arResult['PERIOD_STR']}");
        $sheet->getRowDimension(1)->setRowHeight(40);
        $range = self::colRowToStr(1, 1);
        $style = self::getStyle([
            'font_size' => 14,
            'v_align'   => 'center',
        ]);
        $sheet->getStyle($range)->applyFromArray($style);

        // Заголовок "Сотрудник"
        $sheet->setCellValueByColumnAndRow(1, 2, "Сотрудник: {$arResult['USER_STR']}");
        $sheet->getRowDimension(2)->setRowHeight(40);
        $range = self::colRowToStr(1, 2);
        $sheet->getStyle($range)->applyFromArray($style);

        $startCol = 1;
        $startRow = 4;

        $col = $startCol;
        $row = $startRow;

        if (!$arResult['DATA']) {
            $sheet->setCellValueByColumnAndRow($col, $row, 'Нет данных');
        } else {
            $sheet->setCellValueByColumnAndRow($col, $row, 'Дата');
            $col++;

            // Названия показателей
            foreach ($arResult['DATA_TITLES'] as $value) {
                $sheet->setCellValueByColumnAndRow($col, $row, $value);
                $col++;
            }
            $lastCol = $col - 1;
            $col     = $startCol;
            $row++;

            // Итого
            $sheet->setCellValueByColumnAndRow($col, $row, 'ИТОГО');
            $col++;

            $context = [
                'arResult' => $arResult,
                'sheet'    => $sheet,
                'col'      => $col,
                'row'      => $row,
                'dataRow'  => $arResult['DATA']['total'],
            ];
            self::fExport_hlp($context);
            $col = $startCol;
            $row++;

            // Данные (
            foreach ($arResult['DATA'] as $key_time => $value) {
                if ($key_time == 'total') {
                    continue;
                }

                // Дата
                $range = self::colRowToStr($startCol, $row, $lastCol, $row);
                $style = self::getStyle(['fill' => 'eeeeee']);
                $sheet->getStyle($range)->applyFromArray($style);

                $sheet->setCellValueByColumnAndRow($col, $row, date('d.m.Y', $key_time));
                $col++;

                // Значения
                $context = [
                    'arResult' => $arResult,
                    'sheet'    => $sheet,
                    'col'      => $col,
                    'row'      => $row,
                    'dataRow'  => $value,
                ];
                self::fExport_hlp($context);
                $col = $startCol;
                $row++;

                /*
                    $result[ time ]['detail'][ deal_id ]['h_call_outgoing']['count']
                    $result[ time ]['detail'][ deal_id ]['o_product_testing']['items'][ element_id ]['title']
                */

                // Детализация по каждой сделке (
                foreach ($value['detail'] as $key_deal_id => $value_2) { // ['h_call_outgoing']['count']
                    // Сделка
                    $valueTMP = ($key_deal_id) ? $arResult['DEAL_INFO'][$key_deal_id]['title'] : 'Сделка не указана';
                    $sheet->setCellValueByColumnAndRow($col, $row, $valueTMP);
                    $col++;

                    foreach ($arResult['DATA_TITLES'] as $key_items => $valueNotUsed) {
                        if (!$value_2[$key_items]['count']) {
                            // Для данного показателя нет данных для сделки $key_deal_id в день $key_time
                            $valueTMP = '-';
                        } else {
                            if (!isset($value_2[$key_items]['items'])) {
                                // Только количество (звонки, e-mail, ...)
                                $valueTMP = $value_2[$key_items]['count'];
                            } else {
                                // Элементы (название, ссылка, сумма)
                                foreach ($value_2[$key_items]['items'] as $key_element_id_NotUsed => $value_3) { // ['title'=>'', 'price'=>0, 'link'=>'']
                                    $title = trim($value_3['title']);
                                    $title = (strlen($title)) ? $title : '***';

                                    if ($value_3['link']) {

                                        if ($key_items == 'a_orders_shipped') {
                                            $valueTMP = $value_3['price'];
                                        } else {
                                            $price = ($value_3['price']) ? number_format($value_3['price'], 2, ',', ' ') : '*';
                                            $valueTMP = "{$title}\n{$price}";
                                        }
                                    } else {
                                        $valueTMP = $title;
                                    }
                                }
                            }
                        }
                        $sheet->setCellValueByColumnAndRow($col, $row, $valueTMP);
                        $col++;
                    }
                    $col = $startCol;
                    $row++;
                }
                // Детализация по каждой сделке )
            }
            // Данные )

            $lastRow = $row - 1;

            // ### Оформление

            // Сетка
            $range = self::colRowToStr($startCol, $startRow, $lastCol, $lastRow);
            $style = self::getStyle(['borders' => true]);
            $sheet->getStyle($range)->applyFromArray($style);

            // Ширина колонок
            self::setColumnWidth($sheet, $startCol, 40);
            $col = $startCol + 1;
            foreach ($arResult['DATA_TITLES'] as $value) {
                self::setColumnWidth($sheet, $col, 15);
                $col++;
            }

            // Шапка
            $range = self::colRowToStr($startCol, $startRow, $lastCol, $startRow);
            $style = self::getStyle([
                'v_align'  => 'center',
                'fill'     => 'cccccc',
                'wrapText' => true,
            ]);
            $sheet->getStyle($range)->applyFromArray($style);

            $range = self::colRowToStr($startCol, $startRow + 1, $lastCol, $startRow + 1);
            $style = self::getStyle(['fill' => 'dddddd']);
            $sheet->getStyle($range)->applyFromArray($style);

            // Выравнивание по центру, перенос слов
            $range = self::colRowToStr($startCol + 1, $startRow, $lastCol, $lastRow);
            $style = self::getStyle([
                'align'   => 'center',
                'v_align' => 'center',
                //'wrapText' => true,
            ]);
            $sheet->getStyle($range)->applyFromArray($style);

            $range = self::colRowToStr($startCol, $startRow, $startCol, $lastRow);
            $style = self::getStyle([
                'v_align' => 'center',
            ]);
            $sheet->getStyle($range)->applyFromArray($style);

            // Высота авто
            $sheet->getRowDimension($startRow)->setRowHeight(40);
            for ($row = $startRow + 1; $row <= $lastRow; $row++) {
                $sheet->getRowDimension($row)->setRowHeight(30); // -1
            }
        } // $arResult['DATA']

        if (0) {
            // Сохранение файла на диск
            try {
                $res = self::getFileName([
                    'prefix'    => 'report',
                    'extension' => 'xls',
                ]);
                if ($res['error_msg']) throw new Exception($res['error_msg']);

                //$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
                $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xls($spreadsheet);
                $writer->save($res['fileAbsolutePath']);

                if (!file_exists($res['fileAbsolutePath'])) throw new Exception('Ошибка создания файла');

                $result['fileName'] = $res['url'];
            } catch (Exception $e) {
                $result['error_msg'] = $e->getMessage();
            }
        } else {
            // В браузер
            ob_end_clean();
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="report.xlsx"');
            header('Cache-Control: max-age=0');

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save('php://output');
            exit;
        } //

        return $result;
    } // function

    public function fExport_hlp($context)
    {
        extract($context);

        foreach ($arResult['DATA_TITLES'] as $key_items => $valueNotUsed) {
            if (!$dataRow['items'][$key_items]['count']) {
                // Нет данных для показателя $key_items на дату $key_time
                $valueTMP = '-';
            } else {
                if ($key_items != 'f_prihod_ds' || $key_items != 'q_prihod_ds_whithNDS') {
                    // Количество
                    $valueTMP = $dataRow['items'][$key_items]['count'];
                } else {
                    // Сумма
                    //$valueTMP = ($dataRow['items'][ $key_items ]['price']) ? number_format($dataRow['items'][ $key_items ]['price'], 2, ',', '') : '*';

                    $valueTMP = $dataRow['items'][$key_items]['price'];
                    if (!$valueTMP) {
                        $valueTMP = '*';
                    } else {
                        $valueTMP = number_format($valueTMP, 2, ',', ' ');
                        /*
                        $range = self::colRowToStr($col, $row);
                        $style = self::getStyle(['number_2_digit' => true]);
                        $sheet->getStyle($range)->applyFromArray($style);
                        */
                    }
                }
            }
            $sheet->setCellValueByColumnAndRow($col, $row, $valueTMP);
            $col++;
        } //
    } // function

    public static function colRowToStr($col, $row, $col_2 = null, $row_2 = null)
    {
        $result = Coordinate::stringFromColumnIndex($col) . $row;
        if ($col_2) {
            $result .= ':' . Coordinate::stringFromColumnIndex($col_2) . $row_2;
        }

        return $result;
    } //

    private static function setColumnWidth($sheet, $col, $width)
    {
        $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($col))->setWidth($width);
    }

    public static function getStyle($params)
    {
        $style = [];

        if (!empty($params['borders'])) {
            $style['borders'] = [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                ],
            ];
        }

        if (isset($params['align'])) {
            $value = null;
            switch ($params['align']) {
                case 'left':
                    $value = Style\Alignment::HORIZONTAL_LEFT;
                    break;
                case 'right':
                    $value = Style\Alignment::HORIZONTAL_RIGHT;
                    break;
                case 'center':
                    $value = Style\Alignment::HORIZONTAL_CENTER;
                    break;
            }
            $style['alignment']['horizontal'] = $value;
        }

        if (isset($params['v_align'])) {
            $value = null;
            switch ($params['v_align']) {
                case 'top':
                    $value = Style\Alignment::VERTICAL_TOP;
                    break;
                case 'center':
                    $value = Style\Alignment::VERTICAL_CENTER;
                    break;
                case 'bottom':
                    $value = Style\Alignment::VERTICAL_BOTTOM;
                    break;
            }
            $style['alignment']['vertical'] = $value;
        }

        if (!empty($params['number_2_digit'])) {
            $style['numberFormat']['formatCode'] = '# ##0.00';
        }

        if (!empty($params['wrapText'])) {
            $style['alignment']['wrapText'] = true;
        }

        if (!empty($params['textRotation'])) {
            $style['alignment']['textRotation'] = 90;
        }

        if (!empty($params['bold'])) {
            $style['font']['bold'] = true;
        }

        if (isset($params['font_size'])) {
            $style['font']['size'] = $params['font_size'];
        }

        if (isset($params['fill'])) {
            $style['fill']['fillType'] = Style\Fill::FILL_SOLID;
            $style['fill']['startColor'] = ['rgb' => $params['fill']];
        }

        // https://phpoffice.github.io/PhpSpreadsheet/master/PhpOffice/PhpSpreadsheet/Style/Alignment.html

        return $style;
    } // function

    public function getFileName($arParams)
    {
        /*  getFileName([
                'prefix'    => 'file_one',
                'extension' => 'txt',
            ])
        */

        $result = [
            'error_msg'         => '',
            'url'               => '',
            'fileAbsolutePath'  => '',
        ];

        $prefix    = $arParams['prefix'];
        $extension = $arParams['extension'];

        $time    = time();
        $path    = '/tmp/' . date('Y-m-d', $time);
        $pathAbs = $_SERVER['DOCUMENT_ROOT'] . $path;

        try {
            if (!file_exists($pathAbs)) {
                //self::clearTmp(); // Один раз в день очищаем папку
                if (!mkdir($pathAbs)) {
                    throw new Exception("Ошибка создания папки {$path}");
                }
            }

            $str              = date('Y-m-d_His', $time) . '_' . uniqid();
            $fileName         = "{$prefix}_{$str}.{$extension}";
            $fileNameWithPath = '/tmp/' . date('Y-m-d') . '/' . $fileName;

            $result['url']              = $fileNameWithPath;
            $result['fileAbsolutePath'] = $_SERVER['DOCUMENT_ROOT'] . $fileNameWithPath;
        } catch (Exception $e) {
            $result['error_msg'] = $e->getMessage();
        } //

        return $result;
    } // function

    // #######################################################################

    private static function fLog($msg, $label = null)
    {
        \SP_Log::consoleLog($msg, $label);
        //\SP_Log::fLog($msg, $label, ['prefix'=>'sp_report']);
    } //

    private static function fLog_2($msg, $label = null, $params = null)
    {
        //\SP_Log::fLog($msg, $label, ['prefix'=>'sp_report']);
        self::$arDebug[] = [
            'msg'    => $msg,
            'label'  => $label,
            'params' => $params,
        ];
    } //

} // class
<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

use Exception;
use DateTime;
use DateInterval;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style;

class Report extends CBitrixComponent {

    private static $arLists_cache_time = 36000000;
    private static $arLists            = [];

    private static $limit = 500 + 1;

    private static $itemKeys = [

        'b_summ_for_deal',
        'a_manager',
        'c_products_group',
        'd_deal_category'
    ];
    private static $itemTemplate       = [];
    private static $itemDetailTemplate = [];
    private static $arDealInfo = [];
    
    private static $arError = [];
    private static $arDebug = [];

    public function onPrepareComponentParams($arParams) {
        $result = [
            'flagTest' => isset($arParams['flagTest']) ? $arParams['flagTest'] : false,
        ];

        return $result;
    } // function

    public function executeComponent() {
        $arParams = &$this->arParams;
        $arResult = &$this->arResult;

        Bitrix\Main\Loader::registerAutoLoadClasses(null, [
            '\SP_Log'    => '/local/classes/sp/SP_Log.php',
            '\SP\Config' => '/local/classes/sp/Config.php',
            '\SP\Helper' => '/local/classes/sp/Helper.php',

            '\SP\Report\Test' => $this->GetPath() .'/include/Test.php',
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
            'week'      => 'эта неделя',
            'week_ago'  => 'прошл. неделя',
            'days'      => 'за послед.',
            'after'     => 'позже',
            'before'    => 'раньше',
            'interval'  => 'интервал',

        ];

        $arResult['DATA_TITLES'] = [
            'd_deal_category'            => 'Категория Сделки',
            'c_products_group'           => 'Группа товаров',
            'a_manager'               => 'Менеджер',
			'b_summ_for_deal'       => 'Сумма сделки без НДС(по Спецификации)',    //добавили title нового столбца
            
        ];

        $arLists = self::get_arLists();

        $arResult['USERS'] = $arLists['users'];
        
        $arResult['DEAL_CATEGORYS'] = [
            700 => 'СОПРОВОЖДЕНИЕ',
            699 => 'СОБСТВЕННАЯ'
        ];

        //CModule::IncludeModule("iblock");
        $db_enum_list = CIBlockProperty::GetPropertyEnum("GRUPPA_TOVAROV", Array(), Array('IBLOCK_ID'=>17));
        while($ar_enum_list = $db_enum_list->GetNext())
        {
            $arResult['PRODUCT_GROUPS'][$ar_enum_list['ID']] = $ar_enum_list['VALUE'];
        }


        $arResult['FILTER'] = [
            'F_DATE_TYPE' => 'week',
            'F_DATE_FROM' => '',
            'F_DATE_TO'   => '',
            'F_DATE_DAYS' => '',
            'F_USER'      => '',
            'F_DEAL_CATEGORY'=> '',
            'F_PRODUCT_GROUPS'=> '',
        ];

        $params = \SP\Helper::getFromRequest([
            'F_SET_FILTER',
            'EXPORT_TO_XLS',
            'F_DEAL_CATEGORY',
            'F_PRODUCT_GROUPS',
            'F_DATE_TYPE',
            'F_DATE_FROM',
            'F_DATE_TO',
            'F_DATE_DAYS',
            'F_USER',
            'param_filter_forTable'
        ]);

         self::fLog($params, '$params');


        $param_filter_forTable = $arResult['DATA_TITLES'];
        if (!in_array("all", $params['param_filter_forTable'])) {
            $param_filter_forTable=array();
            foreach ($params['param_filter_forTable'] as $key => $value) {
                if (array_key_exists($value, $arResult['DATA_TITLES'])) {
                    $param_filter_forTable[$value] = $arResult['DATA_TITLES'][$value];
                    if ($value == 'production' || $value == 'orders_shipped') {
                        $name = $value.'_summ';
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
        if (isset($arResult['PERIODS'][ $params['F_DATE_TYPE'] ])) {
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
        if ($params['F_USER'] === 'all' or isset($arLists['users'][ $params['F_USER'] ])) {
            $arResult['FILTER']['F_USER'] = $params['F_USER'];
        } else {
            $arResult['ERROR_MSG'][] = 'Выберите сотрудника';
        }


        //категория сделки
        if ($params['F_DEAL_CATEGORY'] == 'all') {
            $arResult['FILTER']['F_DEAL_CATEGORY'] = $params['F_DEAL_CATEGORY'];
            $arResult['CATEGORY_STR'] = 'Все категории';
        } elseif ($params['F_DEAL_CATEGORY']) {
            $arResult['FILTER']['F_DEAL_CATEGORY'] = $params['F_DEAL_CATEGORY'];
            $categoryID = $params['F_DEAL_CATEGORY'];
            $arResult['CATEGORY_STR'] = $arResult['DEAL_CATEGORYS'][$categoryID];
        }



        //группы товаров
        if ($params['F_PRODUCT_GROUPS'] == 'all') {
            $arResult['FILTER']['F_PRODUCT_GROUPS'] = $params['F_PRODUCT_GROUPS'];
            $arResult['PRODUCT_GROUPS_STR'] = 'Все группы товаров';
        } elseif ($params['F_PRODUCT_GROUPS']) {
            $arResult['FILTER']['F_PRODUCT_GROUPS'] = $params['F_PRODUCT_GROUPS'];
            $product_groupsID = $params['F_PRODUCT_GROUPS'];
            $arResult['PRODUCT_GROUPS_STR'] = $arResult['PRODUCT_GROUPS'][$product_groupsID];
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

                $arResult['PERIOD_STR'] = 'c '. $dateFrom->format($dateFormat);
            }

            if ($dateTo) {
                $dateTo->setTime(23, 59, 59);
                //self::fLog($dateTo->format('Y-m-d H:i:s'), '$dateTo');

                if ($arResult['PERIOD_STR']) {
                    $arResult['PERIOD_STR'] .= ' ';
                }
                $arResult['PERIOD_STR'] .= 'по '. $dateTo->format($dateFormat);
            }
        } //
        // ### Период )

        if ($arResult['FILTER']['F_USER'] !== 'all') {
            $user_id              = $arResult['FILTER']['F_USER'];
            $user                 = $arLists['users'][ $user_id ];
            $arResult['USER_STR'] = "{$user['NAME']} {$user['LAST_NAME']}";
        } else {
            $user_id              = ''/*array_keys($arLists['users'])*/;
            $arResult['USER_STR'] = 'Все сотрудники отдела';
        }
        //$user_id = ($arResult['FILTER']['F_USER'] === 'all') ? array_keys($arLists['users']) : $arResult['FILTER']['F_USER'];

        $arResult['DATA'] = self::fReport([
            'dateFrom' => $dateFrom,
            'dateTo'   => $dateTo,
            'user_id'  => $user_id,
            'F_DEAL_CATEGORY' => $categoryID,
            'F_PRODUCT_GROUPS' => $product_groupsID,
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

    public static function get_arLists($flagClearCache=false) {
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
                $arLists['users'][ $ar['ID'] ] = $ar;
            }
            
            //print_r($arLists);

            $arLists['date'] = date('Y-m-d H:i:s');
            
            // Запишем в кеш
            global $CACHE_MANAGER;
            $CACHE_MANAGER->StartTagCache($cache_dir);
            //$CACHE_MANAGER->RegisterTag( 'iblock_id_'. \SP\Config::get('iblock_catalog_id') );
            $CACHE_MANAGER->EndTagCache();
            $obCache->EndDataCache( $arLists );
        } //
        
        self::$arLists = $arLists;
        
        return self::$arLists;
    } // function

    public static function fReport($params) {
        /*  fReport([
                'dateFrom' => $dateFrom,
                'dateTo'   => $dateTo,
                'user_id'  => $arResult['FILTER']['F_USER'],
            ]);
        */
        $result = [];

        self::$arDealInfo = [];

        self::fReport_hlp_getDealInfo($params);

        //self::fLog($params, 'fReport $params');

        // ### 1 # Менеджер # ИБ 17 "Запуск Заказа в производство" # orders_shipped_summ
        self::fReport_hlp_get_a_manager($result, $params);

        // ### 2 # Сумма сделки без НДС # ИБ 17 "Запуск Заказа в производство" # orders_shipped_summ
        self::fReport_hlp_get_b_summ_for_deal($result, $params);

        // ### 3 # Группа товаров # ИБ 17 "Запуск Заказа в производство" # orders_shipped_summ
        self::fReport_hlp_get_c_products_group($result, $params);

        // ### 4 # Категория Сделки # ИБ 17 "Запуск Заказа в производство" # orders_shipped_summ
        self::fReport_hlp_get_d_deal_category($result, $params);

        ksort($result);

        // $result[ time ]['detail'][ deal_id ]
        foreach ($result as $key => $value) {
            if ($result[ $key ]['detail']) {
                ksort($result[ $key ]['detail']);
            }
        }

       

        // Итоги по каждому показателю за весь выбранный период
        self::fReport_hlp_get_total($result);

        return $result;
    } // function

    public static function fReport_hlp_queryCommon($params) {
        /*  Общее для всех query
            self::fReport_hlp_queryCommon([
                'result'    => &$result,
                'resultKey' => 'calc_deal',
                'query'     => $query,
                'fieldDate' => 'DATE',              // Название поля с датой
                'dateFrom'  => $params['dateFrom'],
                'dateTo'    => $params['dateTo'],
            ]);
        */

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

        self::fLog_2($query->getQuery(), "query {$params['resultKey']}", ['pre'=>true]);

        $res = $query->exec();
        while ($ar = $res->fetch()) {
            $time = $ar['DATE_FOR_GROUP']->getTimestamp();
            self::fReport_hlp_AddElement([
                'result' => &$params['result'],
                'time'   => $time,
            ]);

            $params['result'][ $time ]['items'][ $params['resultKey'] ]['count'] = $ar['CNT'];
        } //

    } // function

    public static function fReport_hlp_queryDate($params) {
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

    public static function fReport_hlp_CIBlockElement_FilterDate($params) {
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

    public static function fReport_hlp_AddElement($params) {
        /*  fReport_hlp_AddElement([
                'result'     => &$result,
                'time'       => $time,

                'deal_id'    => $ar['deal_ID'],
            ])

            $result[ time ]['items']['call_outgoing']['count']
            $result[ time ]['detail'][ deal_id ]['call_outgoing']['count']
            $result[ time ]['detail'][ deal_id ]['product_testing']['items'][ element_id ]['title']

            $result[ time ] = [
                'time' => '2019-01-01',     // Для наглядности
                'items' => [
                    'call_outgoing' => [
                        'count' => 0,
                    ],
                    ...
                    'prihod_ds' => [
                        'count' => 0,
                        'price' => 0,
                    ],
                ],
                'detail' => [
                    deal_id => [
                        'call_outgoing' => [
                            'count' => 0,
                        ],
                        ...,
                        'product_testing' => [
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

        if (!self::$itemTemplate) {
            $value = ['count' => 0];

            self::$itemTemplate = [
                'time'      => '',
                'items'     => array_fill_keys(self::$itemKeys, $value),
                'detail'    => [],
                'debugInfo' => []
            ];
        }

        if (!isset($result[ $time ])) {
            $result[ $time ] = self::$itemTemplate;
            $result[ $time ]['time'] = date('Y-m-d', $time);
        }

        if (isset($params['deal_id'])) {
            if (!self::$itemDetailTemplate) {
                $value = ['count' => 0];

                self::$itemDetailTemplate = array_fill_keys(self::$itemKeys, $value);
            }

            if (!isset($result[ $time ]['detail'][ $params['deal_id'] ])) {
                $result[ $time ]['detail'][ $params['deal_id'] ] = self::$itemDetailTemplate;
            }
        }
    } // function

    // ###################################

    public static function fReport_hlp_get_a_manager(&$result, $params) {
        

        // ### 1 # Менеджер # ИБ 17 "Запуск Заказа в производство" # a_manager

        $resultKey    = 'a_manager';


            $t_arFilter = [
            'IBLOCK_ID'       => 17,
            'PROPERTY_PRIVYAZKA' => array_keys(self::$arDealInfo),
         ];

        //self::fLog($t_arFilter, '$t_arFilter');

        $t_arSelectFields = [
            'ID',
            'CREATED_USER_NAME',
            'CREATED_BY',
            'PROPERTY_PRIVYAZKA',
            'PROPERTY_KATEGORIYA_SDELKI',
            'PROPERTY_GRUPPA_TOVAROV'
        ];


         $res = \CIBlockElement::GetList( array(), $t_arFilter, false, false, $t_arSelectFields);

         while ($ar = $res->fetch()) {

            if($params['F_DEAL_CATEGORY']) {
                if ($params['F_DEAL_CATEGORY'] != $ar["PROPERTY_KATEGORIYA_SDELKI_ENUM_ID"]) continue;
            }
        
            if($params['F_PRODUCT_GROUPS']) {
                if ($params['F_PRODUCT_GROUPS'] != $ar["PROPERTY_GRUPPA_TOVAROV_ENUM_ID"]) continue;
            }

            $timeTMP = MakeTimeStamp(self::$arDealInfo[$ar['PROPERTY_PRIVYAZKA_VALUE']]['CLOSEDATE']);

            if (!$timeTMP) {
                continue; // На всякий
            }

            $timeTMP = MakeTimeStamp(self::$arDealInfo[$ar['PROPERTY_PRIVYAZKA_VALUE']]['CLOSEDATE']);
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

            $result[ $time ]['items'][ $resultKey ]['count']++;
            $result[ $time ]['detail'][ $deal_id ][ $resultKey ]['count']++;

            $title = $ar["CREATED_USER_NAME"];

            $result[ $time ]['items'][ $resultKey ]['price'] += $price;
            $result[ $time ]['detail'][ $deal_id ][ $resultKey ]['items'][ $ar['ID'] ] = [
                'title' => $title,
                'link'  => "/company/personal/user/{$ar['CREATED_BY']}/",
            ];
         };

    } // function

    public static function fReport_hlp_get_b_summ_for_deal(&$result, $params) {
        
        // ### 2 # Сумма сделки без НДС # ИБ 17 "Запуск Заказа в производство" # b_summ_for_deal

        $resultKey    = 'b_summ_for_deal';

        $t_arFilter = [
            'IBLOCK_ID'       => 17,
            'PROPERTY_PRIVYAZKA' => array_keys(self::$arDealInfo),
         ];

        //self::fLog($t_arFilter, '$t_arFilter');

        $t_arSelectFields = [
            'ID',
            'PROPERTY_SUMMA_SDELKI_BEZ_NDS_PO_SPETSIFIKATSII',
            'PROPERTY_PRIVYAZKA',
            'PROPERTY_KATEGORIYA_SDELKI',
            'PROPERTY_GRUPPA_TOVAROV'
        ];


         $res = \CIBlockElement::GetList( array(), $t_arFilter, false, false, $t_arSelectFields);

         while ($ar = $res->fetch()) {

            if($params['F_DEAL_CATEGORY']) {
                if ($params['F_DEAL_CATEGORY'] != $ar["PROPERTY_KATEGORIYA_SDELKI_ENUM_ID"]) continue;
            }

            if($params['F_PRODUCT_GROUPS']) {
                if ($params['F_PRODUCT_GROUPS'] != $ar["PROPERTY_GRUPPA_TOVAROV_ENUM_ID"]) continue;
            }

            $timeTMP = MakeTimeStamp(self::$arDealInfo[$ar['PROPERTY_PRIVYAZKA_VALUE']]['CLOSEDATE']);

            if (!$timeTMP) {
                continue; // На всякий
            }

            $timeTMP = MakeTimeStamp(self::$arDealInfo[$ar['PROPERTY_PRIVYAZKA_VALUE']]['CLOSEDATE']);
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


            $result[ $time ]['items'][ $resultKey ]['count']++;
            $result[ $time ]['detail'][ $deal_id ][ $resultKey ]['count']++;

            $str = str_replace(' ', '', ($ar["PROPERTY_SUMMA_SDELKI_BEZ_NDS_PO_SPETSIFIKATSII_VALUE"]*1));
            //self::fLog($str, '$str');
            $arTMP = explode('|', $str);
            $arTMP1 = explode(',', $str);

            if ($arTMP1[1] != '00') {
                $arTMP1[0] = floatval($arTMP1[0])+($arTMP1[1]*pow(10, -2));
            }

            $price = $arTMP1[0];
            $title = number_format($arTMP1[0], 2, ',', ' ');

            $result[ $time ]['items'][ $resultKey ]['price'] += $price;
            $result[ $time ]['detail'][ $deal_id ][ $resultKey ]['items'][ $ar['ID'] ] = [
                'price' => $title,
                'title' => $title,
            ];
         };

    } // function

    public static function fReport_hlp_get_c_products_group(&$result, $params) {
        
        // ### 3 # Группа продуктов # ИБ 17 "Запуск Заказа в производство" # c_products_group

        $resultKey    = 'c_products_group';

        $t_arFilter = [
            'IBLOCK_ID'       => 17,
            'PROPERTY_PRIVYAZKA' => array_keys(self::$arDealInfo),
         ];

        //self::fLog($t_arFilter, '$t_arFilter');

        $t_arSelectFields = [
            'ID',
            'PROPERTY_GRUPPA_TOVAROV',
            'PROPERTY_PRIVYAZKA',
            'PROPERTY_KATEGORIYA_SDELKI',
        ];


         $res = \CIBlockElement::GetList( array(), $t_arFilter, false, false, $t_arSelectFields);

         while ($ar = $res->fetch()) {

            if($params['F_DEAL_CATEGORY']) {
                if ($params['F_DEAL_CATEGORY'] != $ar["PROPERTY_KATEGORIYA_SDELKI_ENUM_ID"]) continue;
            }

            if($params['F_PRODUCT_GROUPS']) {
                if ($params['F_PRODUCT_GROUPS'] != $ar["PROPERTY_GRUPPA_TOVAROV_ENUM_ID"]) continue;
            }


            $timeTMP = MakeTimeStamp(self::$arDealInfo[$ar['PROPERTY_PRIVYAZKA_VALUE']]['CLOSEDATE']);

            if (!$timeTMP) {
                continue; // На всякий
            }

            $timeTMP = MakeTimeStamp(self::$arDealInfo[$ar['PROPERTY_PRIVYAZKA_VALUE']]['CLOSEDATE']);
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

            $result[ $time ]['items'][ $resultKey ]['count']++;
            $result[ $time ]['detail'][ $deal_id ][ $resultKey ]['count']++;

            $price = $arTMP1[0];
            $title = $ar["PROPERTY_GRUPPA_TOVAROV_VALUE"];

            $result[ $time ]['items'][ $resultKey ]['price'] += $price;
            $result[ $time ]['detail'][ $deal_id ][ $resultKey ]['items'][ $ar['ID'] ] = [
                'title' => $title,
            ];
         };

    } // function


    public static function fReport_hlp_get_d_deal_category(&$result, $params) {
        
        // ### 4 # Сумма сделки без НДС # ИБ 17 "Запуск Заказа в производство" # d_deal_category

        $resultKey    = 'd_deal_category';
        $t_arFilter = [
            'IBLOCK_ID'       => 17,
            'PROPERTY_PRIVYAZKA' => array_keys(self::$arDealInfo),
         ];

        //self::fLog($t_arFilter, '$t_arFilter');

        $t_arSelectFields = [
            'ID',
            'PROPERTY_KATEGORIYA_SDELKI',
            'PROPERTY_PRIVYAZKA',
            'PROPERTY_GRUPPA_TOVAROV'
        ];



         $res = \CIBlockElement::GetList( array(), $t_arFilter, false, false, $t_arSelectFields);

         while ($ar = $res->fetch()) {

            if($params['F_DEAL_CATEGORY']) {
                if ($params['F_DEAL_CATEGORY'] != $ar["PROPERTY_KATEGORIYA_SDELKI_ENUM_ID"]) continue;
            }

            if($params['F_PRODUCT_GROUPS']) {
                if ($params['F_PRODUCT_GROUPS'] != $ar["PROPERTY_GRUPPA_TOVAROV_ENUM_ID"]) continue;
            }

            $timeTMP = MakeTimeStamp(self::$arDealInfo[$ar['PROPERTY_PRIVYAZKA_VALUE']]['CLOSEDATE']." 00:00:00");


            if (!$timeTMP) {
                continue; // На всякий
            }

            $timeTMP = MakeTimeStamp(self::$arDealInfo[$ar['PROPERTY_PRIVYAZKA_VALUE']]['CLOSEDATE']." 00:00:00");
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

            $result[ $time ]['items'][ $resultKey ]['count']++;
            $result[ $time ]['detail'][ $deal_id ][ $resultKey ]['count']++;

            $title = $ar["PROPERTY_KATEGORIYA_SDELKI_VALUE"];


           
            $result[ $time ]['detail'][ $deal_id ][ $resultKey ]['items'][ $ar['ID'] ] = [
                'title' => $title,
            ];
 
         };


    } // function


    


    public static function fReport_hlp_get_conversion_company(&$result, $params) {

            $ar = [
                'resultKey'    => 'conversion_company',
                'IBLOCK_ID'    => 55,
                'PROP_PRICE'   => '', // Цены нет
                'PROP_TITLE'   => 'KOMPANIYA',
                'PROP_DEAL_ID' => 'SDELKA',
            ];

           

            $params = array_merge($params, $ar);
            self::fReport_hlp_get_product_testing_hlp($result, $params);

    }

    public static function fReport_hlp_get_total(&$result) {
        // Итог за выбранный период

        // $result[ time ]['items']['call_outgoing']['count']

        $value = ['count' => 0];
        $total['items'] = array_fill_keys(self::$itemKeys, $value);
        $total['items']['summ_for_deal']['price'] = 0;


        foreach ($result as $value) {
            foreach ($value['items'] as $resultKey => $value_2) {
                $total['items'][ $resultKey ]['count'] += $value_2['count'];
                if ($resultKey == 'b_summ_for_deal') {
                    $total['items'][ $resultKey ]['price'] += $value_2['price'];
                }

            }
        } //

        $result['total'] = $total;
    } // function

    public static function fReport_hlp_getDealInfo($params) {
        /*if (!self::$arDealInfo) {
            return;
        }*/



        if (is_null($params['dateTo'])) {

            $arFilter = array(
                '>=CLOSEDATE'=>$params['dateFrom']->format('d.m.Y'),
                'CLOSED'=>'Y',
                'CREATED_BY_ID'=>$params['user_id']
            );

        } else {

            $arFilter = array(
                '>=CLOSEDATE'=>$params['dateFrom']->format('d.m.Y'),
                'CLOSED'=>'Y',
                'CREATED_BY_ID'=>$params['user_id'],
                '<=CLOSEDATE'=>$params['dateTo']->format('d.m.Y'), 
            );

        }

        if ($params['user_id'] == '') {unset($arFilter['CREATED_BY_ID']);}

        $rsDeal =  CCrmDeal::GetListEx( 
            $arOrder = array('CLOSEDATE'=>'asc'),  
            $arFilter,  
            $arGroupBy = false,  
            $arNavStartParams = false,  
            $arSelectFields = array()); 
        while ($ar = $rsDeal->GetNext())
        {
            self::$arDealInfo[ $ar['ID'] ] = $ar;
            self::$arDealInfo[ $ar['ID'] ]['title'] = $ar['TITLE'];
        }

  

    } // function

    public function fExport($arResult) {
        require_once $_SERVER['DOCUMENT_ROOT'] .'/local/composer/vendor/autoload.php';

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

        // Заголовок "Категория"
        /*$sheet->setCellValueByColumnAndRow(1, 3, "Категория сделки: {$arResult['CATEGORY_STR']}");
        $sheet->getRowDimension(3)->setRowHeight(40);
        $range = self::colRowToStr(1, 3);
        $sheet->getStyle($range)->applyFromArray($style);*/

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
                    $result[ time ]['detail'][ deal_id ]['call_outgoing']['count']
                    $result[ time ]['detail'][ deal_id ]['product_testing']['items'][ element_id ]['title']
                */

                // Детализация по каждой сделке (
                foreach ($value['detail'] as $key_deal_id => $value_2) { // ['call_outgoing']['count']
                    // Сделка
                    $valueTMP = ($key_deal_id) ? $arResult['DEAL_INFO'][ $key_deal_id ]['title'] : 'Сделка не указана';
                    $sheet->setCellValueByColumnAndRow($col, $row, $valueTMP);
                    $col++;

                    foreach ($arResult['DATA_TITLES'] as $key_items => $valueNotUsed) {
                        if (!$value_2[ $key_items ]['count']) {
                            // Для данного показателя нет данных для сделки $key_deal_id в день $key_time
                            $valueTMP = '-';
                        } else {
                            if (!isset($value_2[ $key_items ]['items'])) {
                                // Только количество (звонки, e-mail, ...)
                                $valueTMP = $value_2[ $key_items ]['count'];
                            } else {
                                // Элементы (название, ссылка, сумма)
                                foreach ($value_2[ $key_items ]['items'] as $key_element_id_NotUsed => $value_3) { // ['title'=>'', 'price'=>0, 'link'=>'']
                                    $title = trim($value_3['title']);
                                    $title = (strlen($title)) ? $title : '***';

                                    if ($value_3['link']) {

                                        if ($key_items == 'orders_shipped') {
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
            for ($row=$startRow+1; $row<=$lastRow; $row++) {
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

    public function fExport_hlp($context) {
        extract($context);

        foreach ($arResult['DATA_TITLES'] as $key_items => $valueNotUsed) {
            if (!$dataRow['items'][ $key_items ]['count']) {
                // Нет данных для показателя $key_items на дату $key_time
                $valueTMP = '-';
            } else {
                if ($key_items != 'prihod_ds' || $key_items != 'prihod_ds_whithNDS') {
                    // Количество
                    $valueTMP = $dataRow['items'][ $key_items ]['count'];
                } else {
                    // Сумма
                    //$valueTMP = ($dataRow['items'][ $key_items ]['price']) ? number_format($dataRow['items'][ $key_items ]['price'], 2, ',', '') : '*';

                    $valueTMP = $dataRow['items'][ $key_items ]['price'];
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

    public static function colRowToStr($col, $row, $col_2=null, $row_2=null) {
        $result = Coordinate::stringFromColumnIndex($col) . $row;
        if ($col_2) {
            $result .= ':' . Coordinate::stringFromColumnIndex($col_2) . $row_2;
        }

        return $result;
    } //

    private static function setColumnWidth($sheet, $col, $width) {
        $sheet->getColumnDimension( Coordinate::stringFromColumnIndex($col) )->setWidth($width);
    }

    public static function getStyle($params) {
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
            $style['numberFormat']['formatCode'] = '# ##0.00';
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

    public function getFileName($arParams) {
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
        $path    = '/tmp/'. date('Y-m-d', $time);
        $pathAbs = $_SERVER['DOCUMENT_ROOT'] . $path;

        try {
            if (!file_exists($pathAbs)) {
                //self::clearTmp(); // Один раз в день очищаем папку
                if (!mkdir($pathAbs)) {
                    throw new Exception("Ошибка создания папки {$path}");
                }
            }

            $str              = date('Y-m-d_His', $time) .'_'. uniqid();
            $fileName         = "{$prefix}_{$str}.{$extension}";
            $fileNameWithPath = '/tmp/'. date('Y-m-d') .'/'. $fileName;

            $result['url']              = $fileNameWithPath;
            $result['fileAbsolutePath'] = $_SERVER['DOCUMENT_ROOT'] . $fileNameWithPath;

        } catch (Exception $e) {
            $result['error_msg'] = $e->getMessage();
        } //

        return $result;
    } // function

    // #######################################################################

    private static function fLog($msg, $label=null) {
        \SP_Log::consoleLog($msg, $label);
        //\SP_Log::fLog($msg, $label, ['prefix'=>'sp_report']);
    } //

    private static function fLog_2($msg, $label=null, $params=null) {
        //\SP_Log::fLog($msg, $label, ['prefix'=>'sp_report']);
        self::$arDebug[] = [
            'msg'    => $msg,
            'label'  => $label,
            'params' => $params,
        ];
    } //

} // class

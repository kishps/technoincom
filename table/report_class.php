<?php

namespace CSN;


class Tasks
{
    /**
     * Получить задачи с запущеным заказом в производство по фильтру
     * @param array $params ['dateFrom' => '01.01.2021','dateTo' => '31.01.2021','responsible_id' => '1']
     */
    public static function getTasks($params)
    {
        if (!\CModule::IncludeModule("tasks")) return 'not IncludeModule("tasks")';
        $arReturn = [];
        $arReturn['params'] = $params;
        /*****Фильтры***** */
        $t_arFilter = array(
            "TITLE" => "%ЗАКРЫВАЕМ ЗАДАЧУ ТОЛЬКО ПОСЛЕ ПОЛУЧЕНИЯ ЗАКАЗА ПО РАСЧЕТУ%",
        );
        if ($params['dateFrom']) $t_arFilter['>=CREATED_DATE'] = $params['dateFrom'];
        if ($params['dateTo']) $t_arFilter['<=CREATED_DATE'] = $params['dateTo'];
        if ($params['user']) $t_arFilter['RESPONSIBLE_ID'] = $params['user'];
        if ($params['deal_success']) $t_arFilter['UF_AUTO_691625133653'] = $params['deal_success'];
        if ($params['closed'] == 'Y') {
            $t_arFilter['>=STATUS'] = '4';
        } elseif ($params['closed'] == 'N') {
            $t_arFilter['<STATUS'] = '4';
        }
        $sort = ($params['sort'])?$params['sort']:'ASC';
        $arReturn['t_arFilter'] = $t_arFilter;
        /****Получение списка *****/
        $res = \CTasks::GetList(
            array("CREATED_DATE" => $sort),
            $t_arFilter,
            array('UF_CRM_TASK', 'CREATED_DATE', 'CLOSED_DATE', "TITLE", 'ID', 'RESPONSIBLE_ID','UF_AUTO_691625133653')
        );

        $i = 0;
        $limit = 10000;


        /**Обработка******/
        while ($arTask = $res->GetNext()) {

            foreach ($arTask['UF_CRM_TASK'] as $crm) {
                if (strrpos($crm, 'D_') === 0) {
                    $arTask['UF_CRM_TASK'] = str_replace('D_', '', $crm);
                }
            }
            

            $arTask['START_PROD'] = self::isProductionStart($arTask['UF_CRM_TASK']);
            
            $arTask['COUNT_DAYS'] = self::calc_count_days($arTask['CLOSED_DATE'], $arTask['CREATED_DATE']);
            if ($params['after30']) {
                if (!($params['after30'] == 'Y' && $arTask['COUNT_DAYS']>30 || $params['after30'] == 'N' && $arTask['COUNT_DAYS']<=30) ) continue;
            }
           
            if (!$arReturn['totals']['users'][$arTask['RESPONSIBLE_ID']]['start_prod']) $arReturn['totals']['users'][$arTask['RESPONSIBLE_ID']]['start_prod'] = 0;

            /**добавление по фильтру */
            if ($params['start_prod'] == 'Y' && $arTask['START_PROD'] == true) {

                
                if ($arTask['CLOSED_DATE'])  $arReturn['totals']['users'][$arTask['RESPONSIBLE_ID']]['closed']++;
    
    
                /***Подсчет тоталов */
                if ($arTask['START_PROD']) {
                    $arReturn['totals']['start_prod']['Y']++;
                    $arReturn['totals']['users'][$arTask['RESPONSIBLE_ID']]['start_prod']++;
                } else {
                    $arReturn['totals']['start_prod']['N']++;
                }

                if ($arTask['CLOSED_DATE'] && $arTask['START_PROD'] == false) {
                    $arReturn['totals']['closed']['Y']++;
                    $arReturn['totals']['closed_not_started']++;
                } elseif ($arTask['CLOSED_DATE']) {
                    $arReturn['totals']['closed']['Y']++;
                } else {
                    $arReturn['totals']['closed']['N']++;
                }




                $arReturn['totals']['users'][$arTask['RESPONSIBLE_ID']]['all']++;
                $arReturn['totals']['all']++;

                $arReturn['items'][] = $arTask;
            } elseif ($params['start_prod'] == 'N' && $arTask['START_PROD'] == false) {

                
                
                if ($arTask['CLOSED_DATE'])  $arReturn['totals']['users'][$arTask['RESPONSIBLE_ID']]['closed']++;
    
    
                /***Подсчет тоталов */
                if ($arTask['START_PROD']) {
                    $arReturn['totals']['start_prod']['Y']++;
                    $arReturn['totals']['users'][$arTask['RESPONSIBLE_ID']]['start_prod']++;
                } else {
                    $arReturn['totals']['start_prod']['N']++;
                }

                if ($arTask['CLOSED_DATE'] && $arTask['START_PROD'] == false) {
                    $arReturn['totals']['closed']['Y']++;
                    $arReturn['totals']['closed_not_started']++;
                } elseif ($arTask['CLOSED_DATE']) {
                    $arReturn['totals']['closed']['Y']++;
                } else {
                    $arReturn['totals']['closed']['N']++;
                }

                
                $arReturn['totals']['users'][$arTask['RESPONSIBLE_ID']]['all']++;
                $arReturn['totals']['all']++;

                $arReturn['items'][] = $arTask;
            } elseif (!$params['start_prod']) {


                if ($arTask['CLOSED_DATE'])  $arReturn['totals']['users'][$arTask['RESPONSIBLE_ID']]['closed']++;
    
    
                
                /***Подсчет тоталов */
                if ($arTask['START_PROD']) {
                    $arReturn['totals']['start_prod']['Y']++;
                    $arReturn['totals']['users'][$arTask['RESPONSIBLE_ID']]['start_prod']++;
                } else {
                    $arReturn['totals']['start_prod']['N']++;
                }

                if ($arTask['CLOSED_DATE'] && $arTask['START_PROD'] == false) {
                    $arReturn['totals']['closed']['Y']++;
                    $arReturn['totals']['closed_not_started']++;
                } elseif ($arTask['CLOSED_DATE']) {
                    $arReturn['totals']['closed']['Y']++;
                } else {
                    $arReturn['totals']['closed']['N']++;
                }

               
                $arReturn['totals']['users'][$arTask['RESPONSIBLE_ID']]['all']++;
                $arReturn['totals']['all']++;

                $arReturn['items'][] = $arTask;
            }


            //если больше лимита сворачиваем лавочку
            if ($i >= $limit) return $arReturn;
            $i++;
        }
        
        return $arReturn;
    }

    public static function calc_count_days($closed_date, $created_date)
    {
        if (strlen($closed_date) > 0) {
            return date_diff(new \DateTime($closed_date), new \DateTime($created_date))->days;
        } else {
            return date_diff(new \DateTime(), new \DateTime($created_date))->days;
        }
    }

    public static function getUsers()
    {
        $res = \CUser::GetList(
            $t_by       = 'last_name',
            $t_order    = 'asc',
            $t_filter   = ['ACTIVE' => 'Y', 'UF_DEPARTMENT' => 3],
            $t_arParams = ['FIELDS' => ['ID', 'NAME', 'LAST_NAME', 'PERSONAL_PHOTO']]
        );
        while ($ar = $res->Fetch()) {
            $arUsers[$ar['ID']] = $ar;
            $arrFile = \CFile::ResizeImageGet($ar['PERSONAL_PHOTO'], array('width' => 40, 'height' => 40), BX_RESIZE_IMAGE_EXACT, true);
            $arUsers[$ar['ID']]['PHOTO'] = $arrFile;
        }
        return $arUsers;
    }

    public static function isProductionStart($deal_id)
    {
        
        $t_arFilter = [
            'IBLOCK_ID'       => 17,
            'PROPERTY_PRIVYAZKA' => $deal_id,
        ];

        //self::fLog($t_arFilter, '$t_arFilter');

        $t_arSelectFields = [
            'ID',
            'CREATED_USER_NAME',
            'CREATED_BY',
            'PROPERTY_PRIVYAZKA',

        ];
        $res = \CIBlockElement::GetList(array(), $t_arFilter, false, false, $t_arSelectFields);

        if ($ar = $res->fetch()) {
            return true;
        } else {
            return false;
        }
    }
}

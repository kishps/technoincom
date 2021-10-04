<?php

namespace CSN;


class TasksClosestReport
{
    /**
     * Получить задачи по фильтру
     * @param array $params ['dateFrom' => '01.01.2021','dateTo' => '31.01.2021','responsible_id' => '1']
     */
    public static function getTasks($params)
    {
        if (!\CModule::IncludeModule("tasks")) return 'not IncludeModule("tasks")';
        $arReturn = [];
        $arReturn['params'] = $params;
        /*****Фильтры******/
        $t_arFilter=[];
        $t_arFilter['>CLOSED_DATE']='01.01.1990';
        if ($params['dateFrom']) $t_arFilter['>=CREATED_DATE'] = $params['dateFrom'];
        if ($params['dateTo']) $t_arFilter['<=CREATED_DATE'] = $params['dateTo'];
        if ($params['user']) {
            $t_arFilter['RESPONSIBLE_ID'] = $params['user'];
        } else {
            $t_arFilter['RESPONSIBLE_ID'] = self::getUsersId();
        }
        

        $sort = ($params['sort']) ? $params['sort'] : 'ASC';
        $arReturn['t_arFilter'] = $t_arFilter;
        /****Получение списка *****/
        $res = \CTasks::GetList(
            array("CREATED_DATE" => $sort),
            $t_arFilter,
            array('UF_CRM_TASK', 'CREATED_DATE', 'CLOSED_DATE', "TITLE", 'ID', 'RESPONSIBLE_ID', 'UF_AUTO_691625133653')
        );

        $i = 0;
        $limit = 1000;


        /**Обработка******/
        while ($arTask = $res->GetNext()) {




            $arTask['COUNT_DAYS'] = self::calc_count_days($arTask['CLOSED_DATE'], $arTask['CREATED_DATE']);
            if ($params['after30']) {
                if (!($params['after30'] == 'Y' && $arTask['COUNT_DAYS'] > 30 || $params['after30'] == 'N' && $arTask['COUNT_DAYS'] <= 30)) continue;
            }

            



            if ($arTask['CLOSED_DATE'])  $arReturn['totals']['users'][$arTask['RESPONSIBLE_ID']]['closed']++;

            $arReturn['totals']['users'][$arTask['RESPONSIBLE_ID']]['count_days']+= $arTask['COUNT_DAYS'];

            $arReturn['totals']['users'][$arTask['RESPONSIBLE_ID']]['all']++;
            $arReturn['totals']['all']++;

            $arReturn['items'][] = $arTask;



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

    public static function getUsersId()
    {
        $res = \CUser::GetList(
            $t_by       = 'last_name',
            $t_order    = 'asc',
            $t_filter   = ['ACTIVE' => 'Y', 'UF_DEPARTMENT' => 3],
            $t_arParams = ['FIELDS' => ['ID', 'NAME', 'LAST_NAME', 'PERSONAL_PHOTO']]
        );
        while ($ar = $res->Fetch()) {
            $arUsersId[] = $ar['ID'];
        }
        return $arUsersId;
    }


}

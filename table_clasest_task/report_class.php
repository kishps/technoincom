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
        $t_arFilter = [];
        $t_arFilter['>CLOSED_DATE'] = '01.01.1990';
        if ($params['dateFrom']) $t_arFilter['>=CREATED_DATE'] = $params['dateFrom'];
        if ($params['dateTo']) $t_arFilter['<=CREATED_DATE'] = $params['dateTo'];
        if ($params['user']) {
            $t_arFilter['RESPONSIBLE_ID'] = $params['user'];
        } else {
            $t_arFilter['RESPONSIBLE_ID'] = self::getUsersId();
        }

        if ($params['group']) {
            $t_arFilter['GROUP_ID'] = $params['group'];
        }


        $sort = ($params['sort']) ? $params['sort'] : 'ASC';
        $arReturn['t_arFilter'] = $t_arFilter;


        /****Получение списка просроченных задач*****/
        $res = \CTasks::GetList(
            array("CREATED_DATE" => $sort),
            $t_arFilter + ['OVERDUED' => 'Y'],
            array('UF_CRM_TASK', 'CREATED_DATE', 'CLOSED_DATE', "TITLE", 'ID', 'RESPONSIBLE_ID', 'UF_AUTO_691625133653', 'REAL_STATUS', 'STATUS')
        );
        /**Обработка  просроченных задач******/
        while ($arTask = $res->GetNext()) {
            $arrTasksIdOVERDUED[$arTask['ID']] = 1;
        }


        /****Получение списка *****/
        $res = \CTasks::GetList(
            array("CREATED_DATE" => $sort),
            $t_arFilter,
            array('UF_CRM_TASK', 'CREATED_DATE', 'CLOSED_DATE', "TITLE", 'ID', 'RESPONSIBLE_ID', 'UF_AUTO_691625133653', 'REAL_STATUS', 'STATUS')
        );

        $i = 0;
        $limit = 500000;


        /**Обработка******/
        while ($arTask = $res->GetNext()) {
            $arTask['COUNT_DAYS'] = self::calc_count_days($arTask['CLOSED_DATE'], $arTask['CREATED_DATE']);
            if ($params['after30']) {
                if (!($params['after30'] == 'Y' && $arTask['COUNT_DAYS'] > 30 || $params['after30'] == 'N' && $arTask['COUNT_DAYS'] <= 30)) continue;
            }

            $arReturn['items'][] = $arTask;

            $arrTasksID[] = $arTask['ID'];

            //если больше лимита сворачиваем лавочку
            if ($i >= $limit) return $arReturn;
            $i++;
        }

        $tasksLog = self::getLogTasks($arrTasksID);

        foreach ($arReturn['items'] as $key => $arTask) {




            if ($arTask['CLOSED_DATE'])  $arReturn['totals']['users'][$arTask['RESPONSIBLE_ID']]['closed']++;

            $arReturn['totals']['users'][$arTask['RESPONSIBLE_ID']]['count_days'] += $arTask['COUNT_DAYS'];

            if ($arrTasksIdOVERDUED[$arTask['ID']]) {
                $arReturn['totals']['users'][$arTask['RESPONSIBLE_ID']]['count_overdued'] ++;
                $arReturn['totals']['all_overdued']++;
                if ($tasksLog[$arTask['ID']]) {
                    $arReturn['totals']['users'][$arTask['RESPONSIBLE_ID']]['count_days_changed'] += $tasksLog[$arTask['ID']];
                }
                $arReturn['items'][$key]['IS_OVERDUED'] = true;
            } elseif ($tasksLog[$arTask['ID']]) {
                $arReturn['totals']['users'][$arTask['RESPONSIBLE_ID']]['count_changed_deadline']++;
                $arReturn['totals']['users'][$arTask['RESPONSIBLE_ID']]['count_days_changed'] += $tasksLog[$arTask['ID']];
                $arReturn['totals']['all_changed_dedline']++;
                $arReturn['items'][$key]['IS_CHANGED_DEADLINE'] = true;
            }


            $arReturn['totals']['users'][$arTask['RESPONSIBLE_ID']]['all']++;
            $arReturn['totals']['all']++;
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
            $t_filter   = ['ACTIVE' => 'Y'],
            $t_arParams = ['FIELDS' => ['ID', 'NAME', 'LAST_NAME', 'PERSONAL_PHOTO']]
        );
        while ($ar = $res->Fetch()) {
            $arUsers[$ar['ID']] = $ar;
            $arrFile = \CFile::ResizeImageGet($ar['PERSONAL_PHOTO'], array('width' => 40, 'height' => 40), BX_RESIZE_IMAGE_EXACT, true);
            $arUsers[$ar['ID']]['PHOTO'] = $arrFile;
        }
        return $arUsers;
    }

    public static function getGroups()
    {
        \CModule::IncludeModule("socialnetwork");
        $res = \CSocNetGroup::GetList(array("DATE_UPDATE" => "DESC"), [], false, false, ['ID', 'NAME']);
        while ($fields = $res->fetch()) {
            $arRet[] = $fields;
        }
        return $arRet;
    }

    public static function getUsersId()
    {
        $res = \CUser::GetList(
            $t_by       = 'last_name',
            $t_order    = 'asc',
            $t_filter   = ['ACTIVE' => 'Y'],
            $t_arParams = ['FIELDS' => ['ID', 'NAME', 'LAST_NAME', 'PERSONAL_PHOTO']]
        );
        while ($ar = $res->Fetch()) {
            $arUsersId[] = $ar['ID'];
        }
        return $arUsersId;
    }

    public static function getLogTasks($arrTasksID)
    {
        if (\CModule::IncludeModule("tasks")) {
            $res = \CTaskLog::GetList(
                array(),
                array("TASK_ID" => $arrTasksID, 'FIELD' => 'DEADLINE')
            );

            while ($arLog = $res->GetNext()) {
                if ($arLog['FROM_VALUE'])
                    $rr[$arLog['TASK_ID']] += round(abs($arLog['TO_VALUE'] - $arLog['FROM_VALUE']) / 86400, 1);
            }
            return $rr;
        }
    }
}

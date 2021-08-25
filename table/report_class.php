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
        if (!\CModule::IncludeModule("tasks")) return;

        $t_arFilter = array(
            "TITLE" => "%ЗАКРЫВАЕМ ЗАДАЧУ ТОЛЬКО ПОСЛЕ ПОЛУЧЕНИЯ ЗАКАЗА ПО РАСЧЕТУ%",
        );
        if ($params['dateFrom']) $t_arFilter['>=CREATED_DATE'] = $params['dateFrom'];
        if ($params['dateTo']) $t_arFilter['<=CREATED_DATE'] = $params['dateTo'];
        if ($params['responsible_id']) $t_arFilter['RESPONSIBLE_ID'] = $params['responsible_id'];
        //if ($params['closed'] == true) $t_arFilter['>=REAL_STATUS'] = '5';

        $res = \CTasks::GetList(
            array("UF_AUTO_841972304973" => "ASC"),
            $t_arFilter,
            array('UF_CRM_TASK', 'CREATED_DATE', 'CLOSED_DATE', "TITLE", 'ID', 'RESPONSIBLE_ID')
        );

        $i = 0;
        $limit = 100;

        while ($arTask = $res->GetNext()) {

            foreach ($arTask['UF_CRM_TASK'] as $crm) {
                if (strrpos($crm, 'D_') === 0) {
                    $arTask['UF_CRM_TASK'] = str_replace('D_', '', $crm);
                }
            }
            if (self::isProductionStart($arTask['UF_CRM_TASK'])) {
                $arTask['START_PROD'] = true;
            }
            if (strlen($arTask['CLOSED_DATE']) > 0) {
                $arTask['COUNT_DAYS'] = date_diff(new \DateTime($arTask['CLOSED_DATE']), new \DateTime($arTask['CREATED_DATE']))->days;
            } else {
                $arTask['COUNT_DAYS'] = date_diff(new \DateTime(), new \DateTime($arTask['CREATED_DATE']))->days;
            }

            if ($params['start_prod'] == true && $arTask['START_PROD'] == true) {
                $arReturn[] = $arTask;
            } elseif ($params['start_prod'] == false) {
                $arReturn[] = $arTask;
            }


            //если больше лимита сворачиваем лавочку
            if ($i >= $limit) return $arReturn;
            $i++;
        }
        return $arReturn;
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

        while ($ar = $res->fetch()) {
            return true;
        }
    }
}

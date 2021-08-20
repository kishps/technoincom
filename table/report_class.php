<?php

namespace CSN;


class Tasks
{
    public static function getTasks($params)
    {
        if (!\CModule::IncludeModule("tasks")) return;
        if ($params['dateFrom'] && $params['dateTo']) {
            $t_arFilter = array(
                "TITLE" => "%ЗАКРЫВАЕМ ЗАДАЧУ ТОЛЬКО ПОСЛЕ ПОЛУЧЕНИЯ ЗАКАЗА ПО РАСЧЕТУ%",
                '>=CREATED_DATE' => $params['dateFrom'],
                '<=CREATED_DATE' => $params['dateTo'],
                'RESPONSIBLE_ID' => $params['responsible_id']
            );
        } else {
            $t_arFilter = array(
                "TITLE" => "%ЗАКРЫВАЕМ ЗАДАЧУ ТОЛЬКО ПОСЛЕ ПОЛУЧЕНИЯ ЗАКАЗА ПО РАСЧЕТУ%",

            );
        }

        $res = CTasks::GetList(
            array("UF_AUTO_841972304973" => "ASC"),
            $t_arFilter,
            array('UF_CRM_TASK', 'DESCRIPTION', 'CLOSED_DATE', "TITLE", 'ID','RESPONSIBLE_ID')
        );

        while ($arTask = $res->GetNext()) {
            foreach ($arTask['UF_CRM_TASK'] as $crm) {
                if (strrpos($crm, 'D_') === 0) {
                    $arTask['UF_CRM_TASK'] = str_replace('D_', '', $crm);
                }
            }
            if (self::isProductionStart($arTask['UF_CRM_TASK'])) $arReurn[] = $arTask;
        }
        return $arReurn;
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
         $res = \CIBlockElement::GetList( array(), $t_arFilter, false, false, $t_arSelectFields);

         while ($ar = $res->fetch()) {
            return true;
         }
    }
}

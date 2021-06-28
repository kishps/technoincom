<?
//title: addInfoInDealAndSendSMS

function findTodayDeals()
{
    global $APPLICATION;
    if (!\Bitrix\Main\Loader::includeModule('crm')) return;
    $res = CCrmDeal::GetListEx( 
            $arOrder = array(),  
            $arFilter = array('>=DATE_CREATE' => date("d.m.Y"), "CATEGORY_ID" => 1),  
            $arGroupBy = false,  
            $arNavStartParams = false,  
            $arSelectFields = array()); 
    while($ob = $res->GetNext())
    {
        $arrDeals[$ob['ID']] = $ob['ASSIGNED_BY_ID'];
    }

    print_r($arrDeals);

    foreach ($arrDeals as $idDeal => $assignedDeal ) {
        $tpm = $APPLICATION->IncludeComponent(
            'sp_csn:report_to_sms',
            '',
            Array(
                'SMS_DATE'=>date("d.m.Y"),
                'SMS_USER_ID'=>27,
                'SMS_ID_DEAL'=>$idDeal
            )
        );
    }

}




findTodayDeals();
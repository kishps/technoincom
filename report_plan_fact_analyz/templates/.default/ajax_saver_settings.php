<?php
define('PUBLIC_AJAX_MODE', true);
define('STOP_STATISTICS', true);
define('NO_AGENT_CHECK', true);

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

//check_bitrix_sessid() || die;

//print_r($_REQUEST);
//echo "</br>";
//print_r(json_encode($_REQUEST['sectionId']) );
//echo "</br>";

use Bitrix\Main\Loader; 

Loader::includeModule("highloadblock"); 

use Bitrix\Highloadblock as HL; 
use Bitrix\Main\Entity;

$hlbl = 2; // Указываем ID нашего highloadblock блока к которому будет делать запросы.
        $hlblock = HL\HighloadBlockTable::getById($hlbl)->fetch(); 
        
        $entity = HL\HighloadBlockTable::compileEntity($hlblock); 
        $entity_data_class = $entity->getDataClass(); 
        
        $rsData = $entity_data_class::getList(array(
           "select" => array("*"),
           "order" => array("ID" => "ASC"),
           "filter" => array('UF_PLAN_USERID'=>array_keys($_REQUEST['userPlans']),'UF_PLAN_TIME'=>$_REQUEST['time'])  // Задаем параметры фильтра выборки
        ));


while($arPlan = $rsData->Fetch()){
 $arrPlans[$arPlan['UF_PLAN_USERID']]=$arPlan;
}


foreach ($_REQUEST['userPlans'] as $key => $itemPlan) {
	//print_r($arrPlans[$key]['ID']."\n");
      if(in_array($key, array_keys($arrPlans)) && $arrPlans[$key]['UF_PLAN_USER'] !== $itemPlan){

            $data = array(
               //"UF_FILTER_SECTION_ID"=>$_REQUEST['sectionId'],
               "UF_PLAN_USER" => $itemPlan,
            );
			
		  $result = $entity_data_class::update($arrPlans[$key]['ID'], $data); // 
      }elseif(in_array($key, array_keys($arrPlans)) && $arrPlans[$key]['UF_PLAN_USER'] == $itemPlan){
      } else {
            $data = array(
               "UF_PLAN_TIME"=>$_REQUEST['time'],
               "UF_PLAN_USER"=>$itemPlan,
               "UF_PLAN_USERID"=>$key,
               "UF_PLAN_TIME_TYPE"=>$_REQUEST['timeType']
            );
         
            $result = $entity_data_class::add($data);
      } 
}
print_r($result);


$year  = $_REQUEST['time'];
$year = explode("-", $year);

$year=$year[1];
$hlbl = 2; // Указываем ID нашего highloadblock блока к которому будет делать запросы.
        $hlblock = HL\HighloadBlockTable::getById($hlbl)->fetch();
       
       $entity = HL\HighloadBlockTable::compileEntity($hlblock);
       $entity_data_class = $entity->getDataClass();
       
       $rsData = $entity_data_class::getList(array(
          "select" => array("*"),
          "order" => array("ID" => "ASC"),
          "filter" => array('UF_PLAN_TIME'=>['01-'.$year,'03-'.$year,'02-'.$year,'04-'.$year,'05-'.$year,'06-'.$year,'07-'.$year,'08-'.$year,'09-'.$year,'10-'.$year,'11-'.$year,'12-'.$year,]) // Задаем параметры фильтра выборки
       ));


while($arPlan = $rsData->Fetch()){
    
    $month  = $arPlan['UF_PLAN_TIME'];
    $month = explode("-", $month);
    if ($month[0]<=3) {
   	$arrPlans['quarter']['01'][$arPlan['UF_PLAN_USERID']]+=$arPlan['UF_PLAN_USER'];
    } elseif ($month[0]<=6 && $month[0]>3) {
      if ($arPlan['UF_PLAN_USERID']==27) {echo "Тесля месяц ".$month[0]." план был ".$arrPlans['quarter']['02'][$arPlan['UF_PLAN_USERID']]." добавится ".$arPlan['UF_PLAN_USER']."\n";}
    	$arrPlans['quarter']['02'][$arPlan['UF_PLAN_USERID']]+=$arPlan['UF_PLAN_USER'];
       if ($arPlan['UF_PLAN_USERID']==27) {echo "Тесля месяц ".$month[0]." план стал ".$arrPlans['quarter']['02'][$arPlan['UF_PLAN_USERID']]."\n";}
    } elseif ($month[0]<=9 && $month[0]>6) {
    	$arrPlans['quarter']['03'][$arPlan['UF_PLAN_USERID']]+=$arPlan['UF_PLAN_USER'];
    } elseif ($month[0]<=12 && $month[0]>9) {
    	$arrPlans['quarter']['04'][$arPlan['UF_PLAN_USERID']]+=$arPlan['UF_PLAN_USER'];
    }
    $arrPlans['year'][$month[1]][$arPlan['UF_PLAN_USERID']]+=$arPlan['UF_PLAN_USER'];

}
print_r($arrPlans);

       $rsData = $entity_data_class::getList(array(
          "select" => array("*"),
          "order" => array("ID" => "ASC"),
            "filter" => array('UF_PLAN_TIME'=>['01/'.$year,'02/'.$year,'03/'.$year,'04/'.$year, $year,]) // Задаем параметры фильтра выборки
       ));


while($arPlan = $rsData->Fetch()){
	if ( $arPlan['UF_PLAN_TIME_TYPE'] == 'quarter') {
		$Quarter  = $arPlan['UF_PLAN_TIME'];
		$Quarter = explode("/", $Quarter);

		$arrPlansQuarter['quarter'][$Quarter[0]][$arPlan['UF_PLAN_USERID']]=$arPlan;

	} elseif ( $arPlan['UF_PLAN_TIME_TYPE'] == 'year') {
		$arrPlansQuarter['year'][$Quarter[1]][$arPlan['UF_PLAN_USERID']]=$arPlan;
	}


}
print_r($arrPlansQuarter);


foreach ($arrPlans as $tymeType => $tymeTypeItem) {
    foreach ($tymeTypeItem as $tyme => $arPlans) {
		foreach ($arPlans as $usID => $planSumm) {
			  if(($arrPlansQuarter[$tymeType][$tyme][$usID]) && $arrPlansQuarter[$tymeType][$tyme][$usID]['UF_PLAN_USER'] != $planSumm){

				echo $arrPlansQuarter[$tymeType][$tyme][$usID]['UF_PLAN_USER']." суммаСтар; \n";
				echo $planSumm." суммаНов; \n";

				  $data = array(
					   //"UF_FILTER_SECTION_ID"=>$_REQUEST['sectionId'],
					   "UF_PLAN_USER" => $planSumm,
					);

					$result = $entity_data_class::update($arrPlansQuarter[$tymeType][$tyme][$usID]['ID'], $data); 
				  //print_r($result);

					echo "тип-".$tymeType." время-".$tyme." usId-".$usID." обновить; \n";
			  }elseif(($arrPlansQuarter[$tymeType][$tyme][$usID]) && $arrPlansQuarter[$tymeType][$tyme][$usID]['UF_PLAN_USER'] == $planSumm){
					echo "тип-".$tymeType." время-".$tyme." usId-".$usID." ничего не делать; \n";
			  } else {
				  $data = array(
					  "UF_PLAN_TIME"=>$tyme."/".$year,
					   "UF_PLAN_USER"=>$planSumm,
					   "UF_PLAN_USERID"=>$usID,
					   "UF_PLAN_TIME_TYPE"=>$tymeType
					);
				
					$result = $entity_data_class::add($data);
				  //print_r($result);
				echo "тип-".$tymeType." время-".$tyme." usId-".$usID." ДОБАВИТЬ; \n";

			  }
		}
    }
}

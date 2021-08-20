<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style;
use Bitrix\Main\Diag;
use Bitrix\Main\Application;

class ReportReceipt extends CBitrixComponent {

    public function executeComponent(){
	    $arParams = &$this->arParams;
	    $arResult = &$this->arResult;

        Bitrix\Main\Loader::registerAutoLoadClasses(null, [
            '\SP_Log'    => '/local/classes/sp/SP_Log.php',
            '\SP\Config' => '/local/classes/sp/Config.php',
            '\SP\Helper' => '/local/classes/sp/Helper.php',
        ]);

        $context = Application::getInstance()->getContext();
        $request = $context->getRequest();

        $F_CONTACT = $request->get('F');
        $CONTACT = $F_CONTACT['CONTACT'];

        $params = \SP\Helper::getFromRequest([
            'F_SET_FILTER',
            'EXPORT_TO_XLS',
            
            'F_DATE_TYPE',
            'F_DATE_FROM',
            'F_DATE_TO',
            'F_DATE_DAYS',
            'F_USER',
            'F_CHOICE_COMPANY',
            'param_filter_forTable'
        ]);
        
        $params['F_CHOICE_CONTACT'] = $CONTACT;

	    $arResult['COMPANIES'] = $this->getCompanyFilter();
        $arResult['CONTACTS'] = $this->getContactsFilter();

	    $arResult['PERIODS'] = [
	        'month'     => 'этот месяц',
	        'month_ago' => 'прошл. месяц',
	        'week'      => 'эта неделя',
	        'week_ago'  => 'прошл. неделя',
	        'days'      => 'за послед.',
	        'after'     => 'позже',
	        'before'    => 'раньше',
	        'interval'  => 'интервал',
	        'all'       => 'за все время',
	    ];

        $arResult['DATA_TITLES'] = [
            'ID'            => 'ID',
            'company'       => 'Компания',
            'summ_s_nds'    => 'Сумма с НДС',
            'manager'       => 'Менеджер',
            'date'          => 'Факт. Дата оплаты',
        ];

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

        // Компания и сотрудник

        if($params['F_CHOICE_COMPANY'] == "" && $params['F_CHOICE_CONTACT'] == "" && $params['F_DATE_TYPE'] == ""){
            $arResult['ERROR_MSG'][] = "Выберите период компанию или менеджера";
        }else{
            $arResult['FILTER']['F_CHOICE_COMPANY'] = $params['F_CHOICE_COMPANY'];
            $arResult['FILTER']['F_CHOICE_CONTACT'] = $params['F_CHOICE_CONTACT'];
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

        $this->report([
            'companyId' => $arResult['FILTER']['F_CHOICE_COMPANY'],
            'userId' => $arResult['FILTER']['F_CHOICE_CONTACT'],
            'dateFrom'  => $dateFrom,
            'dateTo'    => $dateTo,
        ], $arResult);


        if ($params['EXPORT_TO_XLS']) {
            $res = self::excelExport($params, $arResult);
        }
	    
	    $this->IncludeComponentTemplate();
    }

    function report($params, &$arResult){


        if(!empty($params['dateFrom'])){
            $filterDate['>=PROPERTY_FAKTICHESKAYA_DATA_OPLATY'] = $params['dateFrom']->format('Y-m-d');
        }

        if(!empty($params['dateTo'])){
            $filterDate['<=PROPERTY_FAKTICHESKAYA_DATA_OPLATY'] = $params['dateTo']->format('Y-m-d'); 
        }

        if(!empty($params['companyId'])){
            $filterProp['=PROPERTY_KOMPANIYA'] = $params['companyId'];
        }
        if(!empty($params['userId'])){
            $filterProp['=PROPERTY_MENEDZHER'] = $params['userId'];
        }

        $rs = CIBlockElement::GetList(['SORT' => "ASC"], 
            [
                "ACTIVE" => "Y", 
                "IBLOCK_ID" => "53", 
                $filterProp,
                $filterDate
        ], 
            false, 
            false, 
            [
            
            "ID", 
            "NAME", 
            "PROPERTY_FAKTICHESKAYA_DATA_OPLATY", 
            "PROPERTY_KOMPANIYA", 
            "PROPERTY_MENEDZHER", 
            "PROPERTY_SUMMA_S_NDS",
            "PROPERTY_SUMMA_BEZ_NDS",
            "PROPERTY_SDELKA",
        ]);

        $cnt = 0;
        echo "<pre style=display:none>";
        while ($ar = $rs->GetNext()){
            print_r($ar);
            $idsCompany[] = $ar['PROPERTY_KOMPANIYA_VALUE'];
            $idsManager[] = $ar['PROPERTY_MENEDZHER_VALUE'];

            $arResult['DATA'][$ar['ID']]['ID_MANAGER'] = $ar['PROPERTY_MENEDZHER_VALUE'];
            $arResult['DATA'][$ar['ID']]['DATE'] = $ar['PROPERTY_FAKTICHESKAYA_DATA_OPLATY_VALUE'];
            $arResult['DATA'][$ar['ID']]['ID_COMPANY'] = $ar['PROPERTY_KOMPANIYA_VALUE'];
            $arResult['DATA'][$ar['ID']]['SUMM_S_NDS'] = explode("|" , $ar['PROPERTY_SUMMA_S_NDS_VALUE'])[0];
            $arResult['DATA'][$ar['ID']]['SUMM_BEZ_NDS'] = explode("|" , $ar['PROPERTY_SUMMA_BEZ_NDS_VALUE'])[0];
            $arResult['DATA'][$ar['ID']]['URL_DEAL'] = "/crm/deal/details/" . $ar['PROPERTY_SDELKA_VALUE'] . "/";
            $arResult['DATA'][$ar['ID']]['URL_COMPANY'] = "/crm/company/details/" . $ar['PROPERTY_KOMPANIYA_VALUE'] . "/";
            $arResult['DATA'][$ar['ID']]['ID_SDELKA'] =  $ar['PROPERTY_SDELKA_VALUE'];
            $arResult["SUMM_ITOGO_S_NDS"] += explode("|" , $ar['PROPERTY_SUMMA_S_NDS_VALUE'])[0];
            $arResult["SUMM_ITOGO_BEZ_NDS"] += explode("|" , $ar['PROPERTY_SUMMA_BEZ_NDS_VALUE'])[0];

            $cnt++;
        }
        echo "</pre>";
        $arResult['COUNT_ROWS'] = $cnt;

        $arResult['COMPANY_LIST'] = self::getListCompany($idsCompany);
        $arResult['USER_LIST'] = self::getUserList($idsManager);


        // для фильтра
        $arResult['FILTER_COMPANY']['ID'] = $arResult['FILTER']['F_CHOICE_COMPANY'];
        $arResult['FILTER_COMPANY']['NAME'] = $arResult['COMPANY_LIST'][$arResult['FILTER']['F_CHOICE_COMPANY']];

        $arResult['FILTER_CONTACT']['ID'] = $arResult['FILTER']['F_CHOICE_CONTACT'];
        $arResult['FILTER_CONTACT']['NAME'] = self::getUserName($arResult['FILTER']['F_CHOICE_CONTACT']);
    
    }
     
    function getCompanyFilter(){
    	//CCrmCompany
    	$arCompanyTypeList = CCrmStatus::GetStatusList('COMPANY_TYPE');
    	$arCompanyIndustryList = CCrmStatus::GetStatusList('INDUSTRY');
    	$obRes = CCrmCompany::GetListEx(
    		array('ID' => 'DESC'),
    		array(),
    		false,
    		array('nTopCount' => 50),
    		array('ID', 'TITLE', 'COMPANY_TYPE', 'INDUSTRY',  'LOGO')
    	);
    	$arCompanies = array();
    	while ($arRes = $obRes->Fetch())
    	{
    		if (!empty($arRes['LOGO']) && !isset($arFiles[$arRes['LOGO']]))
    		{
    			if ($arFile = CFile::GetFileArray($arRes['LOGO']))
    			{
    				$arFiles[$arRes['LOGO']] = CHTTP::URN2URI($arFile['SRC']);
    			}
    		}

    		$arRes['SID'] = $arRes['ID'];

    		$arDesc = Array();
    		if (isset($arCompanyTypeList[$arRes['COMPANY_TYPE']]))
    			$arDesc[] = $arCompanyTypeList[$arRes['COMPANY_TYPE']];
    		if (isset($arCompanyIndustryList[$arRes['INDUSTRY']]))
    			$arDesc[] = $arCompanyIndustryList[$arRes['INDUSTRY']];

    		$arCompanies[] = array(
    			'title' => (str_replace(array(';', ','), ' ', $arRes['TITLE'])),
    			'desc' => implode(', ', $arDesc),
    			'id' => $arRes['SID'],
    			'url' => CComponentEngine::MakePathFromTemplate(
    				COption::GetOptionString('crm', 'path_to_company_show'),
    				array('company_id' => $arRes['ID'])
    			),
    			'image' => isset($arFiles[$arRes['LOGO']]) ? $arFiles[$arRes['LOGO']] : '',
    			'type'  => 'company',
    			'selected' => false
    		);
    	}
    	return $arCompanies;
    }

    function getContactsFilter(){
        //CrmContact
        $arContactTypeList = CCrmStatus::GetStatusList('CONTACT_TYPE');
        $obRes = CCrmContact::GetListEx(
            array('LAST_NAME' => 'ASC', 'NAME' => 'ASC'),
            array(),
            false,
            array('nTopCount' => 50),
            array('ID', 'NAME', 'SECOND_NAME', 'LAST_NAME', 'COMPANY_TITLE', 'PHOTO')
        );
        $arContacts = array();
        
        while ($arRes = $obRes->Fetch())
        {
            if (!empty($arRes['PHOTO']) && !isset($arFiles[$arRes['PHOTO']]))
            {
                if ($arFile = CFile::GetFileArray($arRes['PHOTO']))
                {
                    $arFiles[$arRes['PHOTO']] = CHTTP::URN2URI($arFile['SRC']);
                }
            }

            $arContacts[] = array(
                'id' => $arRes['ID'],
                'url' => CComponentEngine::MakePathFromTemplate(
                    COption::GetOptionString('crm', 'path_to_contact_show'),
                    array('contact_id' => $arRes['ID'])
                ),
                'title' => (str_replace(array(';', ','), ' ', CCrmContact::PrepareFormattedName($arRes))),
                'desc' => empty($arRes['COMPANY_TITLE'])? '': $arRes['COMPANY_TITLE'],
                'image' => isset($arFiles[$arRes['PHOTO']])? $arFiles[$arRes['PHOTO']] : '',
                'type' => 'contact',
                'selected' => false
            );
        }
        return $arContacts;
    }
    
    function getListCompany(){
         
        $rsComp = CCrmCompany::GetList(["SORT" => "ASC"], [], [], false);
          
        while($arComp = $rsComp->GetNext()){
            $data[$arComp['ID']] = $arComp['~TITLE'];
        }
        return $data;
    }
    
    function getUserList($idsUsers){
        
        $rsUsers = CUser::GetList(
            $by = [],
            $order = "asc", 
            ["ID" => $idsUsers], 
            ["SELECT"=>[], "FIELDS" => ["ID", "LAST_NAME", "NAME", "SECOND_NAME"]]);

        while ($arUser = $rsUsers->GetNext()) {
            $data[$arUser['ID']] = $arUser['LAST_NAME'] . ' ' . $arUser['NAME'] . ' ' . $arUser['SECOND_NAME'];
        }
        return $data;
    }

    public function excelExport(&$params, &$arResult){
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
        $sheet->setTitle('Отчет поступления');

        $startCol = 1;
        $startRow = 4;

        $col = $startCol;
        $row = $startRow;
        $count = 0;

        // Заголовок "Период"
        $sheet->setCellValueByColumnAndRow(1, 1, "Период: {$arResult['PERIOD_STR']}");
        $sheet->getRowDimension(1)->setRowHeight(40);
        $range = self::colRowToStr(1, 1);
        $style = self::getStyle([
            'font_size' => 14,
            'v_align'   => 'center',
        ]);
        $sheet->getStyle($range)->applyFromArray($style);

        // Заголовок Компания
        $sheet->setCellValueByColumnAndRow(1, 2, "Компания: {$arResult['COMPANY_LIST'][$params['F_CHOICE_COMPANY']]}");
        $sheet->getRowDimension(1)->setRowHeight(40);
        $range = self::colRowToStr(1, 2);
        $style = self::getStyle([
            'font_size' => 14,
            'v_align'   => 'center',
        ]);
        $sheet->getStyle($range)->applyFromArray($style);

        $nameManager = self::getUserName($arResult['FILTER']['F_CHOICE_CONTACT']);
        // Заголовок Сотрудник
        $sheet->setCellValueByColumnAndRow(1, 3, "Сотрудник: {$nameManager}");
        $sheet->getRowDimension(1)->setRowHeight(40);
        $range = self::colRowToStr(1, 3);
        $style = self::getStyle([
            'font_size' => 14,
            'v_align'   => 'center',
        ]);
        $sheet->getStyle($range)->applyFromArray($style);

        if (!$arResult['DATA']) {
            $sheet->setCellValueByColumnAndRow($col, $row, 'Нет данных');

        }else{

            foreach ($arResult['DATA_TITLES'] as $value) {
                $sheet->setCellValueByColumnAndRow($col, $row, $value);
                $col++;
            }


            $row++;
            $col = $startCol;

            // Итого
            $sheet->setCellValueByColumnAndRow($startCol, $row, $arResult['COUNT_ROWS']);
            $sheet->setCellValueByColumnAndRow($startCol + 2, $row,  $arResult["SUMM_ITOGO_S_NDS"]);
            $sheet->setCellValueByColumnAndRow($startCol + 3, $row,  $arResult["SUMM_ITOGO_BEZ_NDS"]);

            $range = self::colRowToStr($startCol, $row, 6, $row);
            $style = self::getStyle(['fill' => 'dddddd']);
            $sheet->getStyle($range)->applyFromArray($style);

            
            $row++;

            foreach($arResult['DATA'] as $idCell => $cell){

                $sheet->setCellValueByColumnAndRow($col++, $row, $cell["ID_SDELKA"]);
                $sheet->setCellValueByColumnAndRow($col++, $row, $arResult['COMPANY_LIST'][$cell['ID_COMPANY']]);
                $sheet->setCellValueByColumnAndRow($col++, $row, $cell["SUMM_S_NDS"]);
                $sheet->setCellValueByColumnAndRow($col++, $row, $cell["SUMM_BEZ_NDS"]);
                $sheet->setCellValueByColumnAndRow($col++, $row, $arResult['USER_LIST'][$cell['ID_MANAGER']]);
                $sheet->setCellValueByColumnAndRow($col++, $row, $cell["DATE"]);
                
                $row++;
                
                $lastCol = $col;

                $col = $startCol;

                $count++;
            }


            $lastRow = $row - 1;

            // Сетка
            $range = self::colRowToStr($startCol, $startRow, $lastCol - 1, $lastRow);
            $style = self::getStyle(['borders' => true]);
            $sheet->getStyle($range)->applyFromArray($style);

            // Ширина колонок
            self::setColumnWidth($sheet, $startCol, 45);
            $col = $startCol + 1;
            foreach ($arResult['DATA_TITLES'] as $value) {
                self::setColumnWidth($sheet, $col, 35);
                $col++;
            }
            // Diag\Debug::dumpToFile($lastRow, $varName = "res", $fileName = "debug.txt");
            // Шапка
            $range = self::colRowToStr($startCol, $startRow, $lastCol - 1, $startRow);
            $style = self::getStyle([
                'v_align'  => 'center',
                'fill'     => 'cccccc',
                'wrapText' => true,
            ]);
            $sheet->getStyle($range)->applyFromArray($style);

            // Выравнивание по центру, перенос слов
            $range = self::colRowToStr($startCol + 1, $startRow, $lastCol, $lastRow);
            $style = self::getStyle([
                'align'   => 'center',
                'v_align' => 'center',
                //'wrapText' => true,
            ]);
            $sheet->getStyle($range)->applyFromArray($style);

            $range = self::colRowToStr($startCol, $startRow, $startCol, $lastRow + 2);
            $style = self::getStyle([
                'v_align' => 'center',
            ]);
            $sheet->getStyle($range)->applyFromArray($style);


            // Высота авто
            $sheet->getRowDimension($startRow)->setRowHeight(40);
            for ($row = $startRow + 1; $row <= $lastRow + 2; $row++) {
                $sheet->getRowDimension($row)->setRowHeight(30); // -1
            }

            // В браузер
            ob_end_clean();
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="report.xlsx"');
            header('Cache-Control: max-age=0');

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save('php://output');
            exit;
        }
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
    }

    function getUserName($userId){
        $rsUser = CUser::GetByID($userId);
        
        if($arUser = $rsUser->Fetch()){
            return $arUser['NAME'] . " " . $arUser['LAST_NAME'];
        }

        return "";
    }

    public static function colRowToStr($col, $row, $col_2=null, $row_2=null){
        $result = Coordinate::stringFromColumnIndex($col) . $row;
        if ($col_2) {
            $result .= ':' . Coordinate::stringFromColumnIndex($col_2) . $row_2;
        }

        return $result;
    } //

    private static function setColumnWidth($sheet, $col, $width){
        $sheet->getColumnDimension( Coordinate::stringFromColumnIndex($col) )->setWidth($width);
    }
}
<?
namespace SP\Report;

/*
    https://bitrix.technoincom.ru/local/components/sp/report/test.php?oper=get_arLists
*/

class Test {

    private static $component;

    public static function run($component) {
        self::$component = $component;

        echo "<pre>\n";

        $oper = \SP\Helper::getFromRequest('oper');
        
        switch ($oper) {
            case 'get_arLists':
                $res = $component->get_arLists(true);
                //$res = $component->get_arLists(false);
                \SP_Log::consoleLog( $res, $oper );
                break;

            default:
                self::defaultOper();
        } // switch
        
        if ($oper) {
            echo "\n\n<br><hr><br>\n oper: {$oper}";
        }
        
        echo "\n</pre>\n";
    } // function

    public static function defaultOper() {
        if (1) {
            require_once $_SERVER['DOCUMENT_ROOT'] .'/local/composer/vendor/autoload.php';

            $ar = ['number_2_digit'];
            $ar2 = [
                'font_size' => 14,
                'v_align'   => 'center',
            ];
            $res = self::$component::getStyle($ar);
            self::fLog($res, '$res');
        }

        if (0) {
            //self::fReport_hlp_AddElement();
            $res = self::$component::getFileName([
                'prefix'    => 'file_one',
                'extension' => 'txt',
            ]);
            self::fLog($res, '$res');
        }
    } // function

    public static function fReport_hlp_AddElement() {
        $result = [];

        $time = strtotime('2019-01-01');

        self::$component->fReport_hlp_AddElement([
            'result'     => &$result,
            'time'       => $time,

            'deal_id'    => 1,
            'deal_title' => '111',
        ]);

        self::$component->fReport_hlp_AddElement([
            'result'     => &$result,
            'time'       => $time,

            'deal_id'    => 2,
            'deal_title' => '222',
        ]);

        $time = strtotime('2019-01-02');

        self::$component->fReport_hlp_AddElement([
            'result'     => &$result,
            'time'       => $time,
        ]);

        \SP_Log::consoleLog($result, 'fReport_hlp_AddElement');
    } // function

    private static function fLog($msg, $label=null) {
        \SP_Log::consoleLog($msg, $label);
    } //

} // class

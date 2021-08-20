function OnTaskIntervalChange(select)
{
    // original: var isPeriodHidden = <? echo ($isPeriodHidden ? 'true' : 'false'); ?>;
    var isPeriodHidden = 'false';
    var periodSelect = BX('task-interval-filter');
    var hide = isPeriodHidden && periodSelect && periodSelect === select;
    select.parentNode.className = "filter-field" +
        ((hide) ? " filter-field-hidden" : "") +
        " filter-field-date-combobox" +
        " filter-field-date-combobox-" + select.value;

    var dateInterval = BX.findNextSibling(select, { "tag": "span", 'className': "filter-date-interval" });
    var dayInterval = BX.findNextSibling(select, { "tag": "span", 'className': "filter-day-interval" });

    BX.removeClass(dateInterval, "filter-date-interval-after filter-date-interval-before");
    BX.removeClass(dayInterval, "filter-day-interval-selected");

    if (select.value == "interval")
        BX.addClass(dateInterval, "filter-date-interval-after filter-date-interval-before");
    else if(select.value == "before")
        BX.addClass(dateInterval, "filter-date-interval-before");
    else if(select.value == "after")
        BX.addClass(dateInterval, "filter-date-interval-after");
    else if(select.value == "days")
        BX.addClass(dayInterval, "filter-day-interval-selected");
}

BX.ready(function() {
    BX.bind(BX("filter-date-interval-calendar-from"), "click", function(e) {
        if (!e) e = window.event;

        var curDate = new Date();
        var curTimestamp = Math.round(curDate / 1000) - curDate.getTimezoneOffset()*60;

        BX.calendar({
            node: this,
            field: BX('REPORT_INTERVAL_F_DATE_FROM'),
            bTime: false
        });

        BX.PreventDefault(e);
    });

    BX.bind(BX("filter-date-interval-calendar-to"), "click", function(e) {
        if (!e) e = window.event;

        var curDate = new Date();
        var curTimestamp = Math.round(curDate / 1000) - curDate.getTimezoneOffset()*60;

        BX.calendar({
            node: this,
            field: BX('REPORT_INTERVAL_F_DATE_TO'),
            bTime: false
        });

        BX.PreventDefault(e);
    });

    jsCalendar.InsertDate = function(value) {
        BX.removeClass(this.field.parentNode.parentNode, "webform-field-textbox-empty");
        var value = this.ValueToString(value);
        this.field.value = value.substr(11, 8) == "00:00:00" ? value.substr(0, 10) : value.substr(0, 16);
        this.Close();
    }

    OnTaskIntervalChange(BX('task-interval-filter'));
});

BX.ready(function() {
    $(document).ready(function(){
        var objMain = $('.sp-report');

        function fFilterShowHide(flag) {
            $('#sidebar').toggle(flag);
            objMain.find('.js-btn-filter-hide').toggle(flag);
            objMain.find('.js-btn-filter-show').toggle(!flag);
        }
        objMain.find('.js-btn-filter-hide').click(function(){
            fFilterShowHide(false);
        });
        objMain.find('.js-btn-filter-show').click(function(){
            fFilterShowHide(true);
        });

        function fDetailFoldUnfold(flag) {
            // true: fold (свернуть)
            objMain.find('.m-data .row-main').toggleClass('open', !flag);
            objMain.find('.m-data .row-detail').toggle(!flag);
        }
        objMain.find('.js-btn-detail-fold').click(function(){
            fDetailFoldUnfold(true);
        });
        objMain.find('.js-btn-detail-unfold').click(function(){
            fDetailFoldUnfold(false);
        });

        objMain.find('.m-data .row-main').click(function(){
            var flag = $(this).hasClass('open'),
                date = $(this).data('date');

            objMain.find('.m-data .row-detail[data-date="' + date + '"]').toggle(!flag);
            
            $(this).toggleClass('open');
        });

        $(".fakeSelect_item input").change(function (e) {
            $(".fakeSelect_item_all input").removeAttr("checked");
        });

        if ($(".fakeSelect_item input").prop("checked")) {
            $(".fakeSelect_item_all input").removeAttr("checked");
        }
    });
});

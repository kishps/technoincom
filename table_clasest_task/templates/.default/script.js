class Report {
    /**
     * Фильтр вида {dateFrom:'01.07.2021', dateTo:'31.08.2021', closed:true , start_prod:true, user:12}
     */
    filter = {
        sort: "DESC",
    };
    $reportContainer = {};
    $arrUsersSelect = [];
    objUsers = [];
    data = {};
    filterTitles = {
        dateFrom: 'Интервал от',
        dateTo: 'Интервал до',
        user: 'Сотрудник',
        sort: 'Сортировка по дате',
        after30: 'Больше 30 дней',

    }
    paramsList = {};
    chartData = []; //данные для графика
    chartDataPie = []; //данные для графика
    firstDay;
    objSort = {
        property: '',
        sort: ''
    }

    constructor() {
        this.$reportContainer = $('#report');
        let date = new Date(),
            y = date.getFullYear(),
            m = date.getMonth();
        let firstDayTMP = new Date(y, m, 1);
        this.firstDay = BX.date.format("d.m.Y", firstDayTMP);
        let lastDay = new Date(y, m + 1, 0);
        this.init();

    }

    async init() {
        await this.createUsersList();
        let filterTitles = this.filterTitles;

        let setDateDiv = this.createSetDateDiv();
        //this.setFilter({ dateFrom: this.firstDay });

        this.$reportContainer
            .append(`   <div class="left-side">
                                <div class="filter">
                                    ${setDateDiv}
                                    <div class="filter-item" style="display:none">
                                        <label class="label" for="dateFrom">${filterTitles.dateFrom}:</label>
                                        <input type="text" onclick="BX.calendar({node: this, field: this, bTime: false});" class="js-filter-input" name="dateFrom" id="dateFrom" >
                                    </div>
                                    <div class="filter-item dateTo" style="display:none">
                                        <label class="label" for="dateTo">${filterTitles.dateTo}:</label>
                                        <input type="text" onclick="BX.calendar({node: this, field: this, bTime: false});" class="js-filter-input" name="dateTo"  id="dateTo">
                                    </div>
                                        
                                </div>
                                <div>
                                    <div class="filter-item" data-filter="after30">
                                        <label class="label" for="after30">${filterTitles.after30}:</label>
                                    </div>
                                    <div class="filter-item" data-filter="user">
                                        <label class="label" for="user">${filterTitles.user}:</label>
                                    </div>
                                </div>
                                <div class="params-list"></div>
                                <div class="totals"><table class="totals-table"><tbody></tbody></table></div>
                        </div>`)
            .append(`<div class="charts"><div id="chartdiv_pie"></div><div id="chartdiv"></div></div>`)
            .append(`<table class="table-tasks">
                        <thead>
                            <tr>
                                <th>
                                    Задача
                                </th>
                                <th>
                                    Ответственный
                                </th>
                                <th class="th_create sortable desc" data-sort="DESC">
                                    Дата создания
                                </th>
                                <th>
                                    Дата закрытия
                                </th>
                                <th class="th_count">
                                    Количество дней
                                </th>
                                
                            </tr>
                        </thead>
                        <tbody>

                        </tbody>
                    </table>`);
        $('[data-filter="user"]').append(this.$arrUsersSelect);

        $('[data-filter="after30"]').append(this.createSelect('after30'));

        this.bindInputChange();

        let nowDate = new Date();
        let year = nowDate.getFullYear();
        let month = nowDate.getMonth() * 1 + 1;
        $('.mounth-year').text(year);
        $(`.select-mounth-mounth button[data-val="${month}"]`).click();

        //this.renderReport();

    }

    createSetDateDiv() {
        return `<div class="setDateDiv">
                    <button data-open="select-mounth" class="btn_mounth">Месяц</button>
                    <div class="select select-mounth">
                        <div class="select-mounth-year">
                            <span class="minus">-</span>
                            <span class="mounth-year year">2021</span>
                            <span class="plus">+</span>
                        </div>
                        <div class="select-mounth-mounth">
                            <button data-val="01">Янв.</button>
                            <button data-val="02">Фев.</button>
                            <button data-val="03">Мар.</button>
                            <button data-val="04">Апр.</button>
                            <button data-val="05">Май</button>
                            <button data-val="06">Июнь</button>
                            <button data-val="07">Июль</button>
                            <button data-val="08">Авг.</button>
                            <button data-val="09">Сен.</button>
                            <button data-val="10">Окт.</button>
                            <button data-val="11">Ноя.</button>
                            <button data-val="12">Дек.</button>
                        </div>
                    </div>
                    <button data-open="select-quarter" class="btn_quarter">Квартал</button>
                    <div class="select select-quarter">
                        <div class="select-quarter-year">
                            <span class="minus">-</span>
                            <span class="quarter-year year">2021</span>
                            <span class="plus">+</span>
                        </div>
                        <div class="select-quarter-quarter">
                            <button data-val="1">I</button>
                            <button data-val="4">II</button>
                            <button data-val="7">III</button>
                            <button data-val="10">IV</button>
                        </div>
                    </div>
                    <button data-open="select-year" class="btn_year">Год</button>
                    <div class="select select-year">
                        <div class="select-year">
                            <span class="minus">-</span>
                            <span class="year-year year">2021</span>
                            <span class="plus">+</span>
                        </div>
                        <button class="set-year">Выбрать</button>
                    </div>
                </div>`;
    }

    startRender() {
        let params = {
            dateFrom: $('input[name="dateFrom"]').val(),
            dateTo: $('input[name="dateTo"]').val(),
            closed: $('select[name="closed"]').val(),
            start_prod: $('select[name="start_prod"]').val(),
            user: $('select[name="user"]').val(),
            sort: $('.th_create').data('sort'),
            after30: $('select[name="after30"]').val(),
            deal_success: $('select[name="deal_success"]').val(),
        }
        console.log("🚀 ~ file: script.js ~ line 59 ~ Report ~ $ ~ params", params)

        this.setFilter(params);
        this.renderReport();
    }

    defaultButtons() {
        $("button[data-open]").each(function () {
            if ($(this).data("open") == 'select-mounth') $(this).text('Месяц');
            if ($(this).data("open") == 'select-quarter') $(this).text('Квартал');
            if ($(this).data("open") == 'select-year') $(this).text('Год');
        });
    }

    bindInputChange() {
        let This = this;
        $('.js-filter-input').on('change', function () {

            This.startRender();

        });


        $("button[data-open]").click(function () {
            let open = $(this).data("open");
            $('.filter .select').removeClass('active');
            $("." + open).addClass("active");
            $("button[data-open]").removeClass("active-btn");
        });

        $(".select-mounth-mounth button").click(function () {
            let m = $(this).data("val") - 1,
                y = $(".mounth-year").text(),
                firstDayTMP = new Date(y, m, 1),
                lastDayTMP = new Date(y, m + 1, 0);

            $("#dateFrom").val(BX.date.format("d.m.Y", firstDayTMP));
            $("#dateTo").val(BX.date.format("d.m.Y", lastDayTMP));

            This.defaultButtons();

            $(".btn_mounth")
                .text(`${$(this).text()} ${y}`)
                .addClass("active-btn");
            $(".select").removeClass("active");



            This.startRender();
        });

        $(".select-quarter-quarter button").click(function () {
            let m = $(this).data("val") - 1,
                y = $(".quarter-year").text(),
                firstDayTMP = new Date(y, m, 1),
                lastDayTMP = new Date(y, m + 3, 0);

            $("#dateFrom").val(BX.date.format("d.m.Y", firstDayTMP));
            $("#dateTo").val(BX.date.format("d.m.Y", lastDayTMP));

            This.defaultButtons();

            $(".btn_quarter")
                .text(`${$(this).text()} квартал ${y}`)
                .addClass("active-btn");
            $(".select").removeClass("active");

            This.startRender();
        });

        $(".set-year").click(function () {
            let m = 0,
                y = $(".year-year").text(),
                firstDayTMP = new Date(y, m, 1),
                lastDayTMP = new Date(y, m + 12, 0);

            $("#dateFrom").val(BX.date.format("d.m.Y", firstDayTMP));
            $("#dateTo").val(BX.date.format("d.m.Y", lastDayTMP));

            This.defaultButtons();

            $(".btn_year").text(`${y}`).addClass("active-btn");
            $(".select").removeClass("active");

            This.startRender();
        });

        $(".plus").click(function () {
            let $year = $(this).siblings(".year");
            $year.text(parseInt($year.text()) + 1);
        });

        $(".minus").click(function () {
            let $year = $(this).siblings(".year");
            $year.text(parseInt($year.text()) - 1);
        });


        $('.th_create').click(function () {
            $('th').removeClass('sortable');
            if ($(this).hasClass('desc')) {
                $(this).removeClass('desc').addClass('asc');
                $(this).data('sort', 'ASC');
            } else {
                $(this).removeClass('asc').addClass('desc');
                $(this).data('sort', 'DESC');
            }
            $(this).addClass('sortable');
            This.objSort = {};
            if ($(this).data('sort')) This.startRender();
        });

        $('.th_count').click(function () {
            $('th').removeClass('sortable');
            if ($(this).hasClass('desc')) {
                $(this).removeClass('desc').addClass('asc');
                This.objSort = {
                    property: 'COUNT_DAYS',
                    sort: 'asc'
                }
            } else {
                $(this).removeClass('asc').addClass('desc');
                This.objSort = {
                    property: 'COUNT_DAYS',
                    sort: 'desc'
                }
            }
            $(this).addClass('sortable');
            This.startRender();
        });

    }


    async createUsersList() {
        let objUsers = await this.getUsers();
        if (this.IsJsonString(objUsers)) objUsers = JSON.parse(objUsers);

        let arrUsers = Object.values(objUsers);


        let $select = $('<select name="user" class="js-filter-input" id="user-filt"><option value="">Все сотрудники ОП</option></select>');

        for (let user of arrUsers) {
            $select.append(`<option value="${user.ID}"><img src="${user.PHOTO.src}" class="personal-photo">${user.NAME}  ${user.LAST_NAME}</option>`);
        }

        this.$arrUsersSelect = $select;
        this.objUsers = objUsers;
    }

    createSelect(field) {

        let arrOptions = [{
            value: 'Y',
            name: 'Да'
        },
        {
            value: 'N',
            name: 'Нет'
        },
        ]

        let $select = $(`<select name="${field}" class="js-filter-input"><option value="">Все</option></select>`);

        for (let option of arrOptions) {
            $select.append(`<option value="${option.value}">${option.name}</option>`);
        }

        return $select;
    }

    IsJsonString(str) {
        try {
            JSON.parse(str);
        } catch (e) {
            return false;
        }
        return true;
    }

    IsIterrable(iterable) {
        try {
            for (let i of iterable) { }
        } catch (e) {
            return false;
        }
        return true;
    }

    num_word(value, words) {
        value = Math.abs(value) % 100;
        var num = value % 10;
        if (value > 10 && value < 20) return words[2];
        if (num > 1 && num < 5) return words[1];
        if (num == 1) return words[0];
        return words[2];
    }

    async getTasks() {
        return $.ajax({
            url: "/local/components/sp_csn/table_clasest_task/ajax.php",
            type: "POST",
            dataType: "html",
            data: {
                sessid: BX.bitrix_sessid(),
                action: 'getTasks',
                filter: this.filter
            },
        });
    }

    async getUsers() {
        return $.ajax({
            url: "/local/components/sp_csn/table_clasest_task/ajax.php",
            type: "POST",
            dataType: "html",
            data: {
                sessid: BX.bitrix_sessid(),
                action: 'getUsers',
            },
        });
    }

    /**
     * Задает фильтр 
     * @param {Array} params фильтр вида {dateFrom:'01.07.2021', dateTo:'31.08.2021', closed:true , start_prod:true, user:12}
     */
    setFilter(params) {
        this.filter = params;
    }


    getStage(params) {
        let closedDate = params.closedDate;
        let start_prod = params.start_prod;
        let stage = {};
        if (closedDate && start_prod == true) {
            stage.bar = `<div class="complete-stage"></div><div class="complete-stage"></div><div class="complete-stage"></div>`;
            stage.title = `Задача закрыта, запущен БП производства`;
        } else if (closedDate && start_prod == false) {
            stage.bar = `<div class="not_prod-stage"></div><div class="not_prod-stage"></div><div class="not_prod-stage"></div>`;
            stage.title = `Задача закрыта, не запущен БП производства`;
        } else if (start_prod == true) {
            stage.bar = `<div class="start_prod-stage"></div><div class="start_prod-stage"></div><div class="next-stage"></div>`;
            stage.title = `Запущен БП производства`;
        } else {
            stage.bar = `<div class="new-stage"></div><div class="next-stage"></div><div class="next-stage"></div>`;
            stage.title = `Новая задача`;
        }
        return stage;
    }

    createTotals() {
        let chartdata = [];
        let chartdataPie = [];
        let objUsers = this.objUsers;
        let usersTotal = this.data.totals.users;
        let totals = this.data.totals;

        for (let user_id in usersTotal) {

            if (!objUsers[user_id]) continue;
            chartdata.push({
                "name": `${objUsers[user_id].NAME} ${objUsers[user_id].LAST_NAME}`,
                'steps': usersTotal[user_id]['count_days'] / usersTotal[user_id]['all'],

            });
        }

        this.chartData = chartdata;


        for (let user_id in usersTotal) {

            if (!objUsers[user_id]) continue;
            chartdataPie.push({
                "name": `${objUsers[user_id].NAME} ${objUsers[user_id].LAST_NAME}`,
                'tasks': usersTotal[user_id]['all'],

            });
        }

        this.chartDataPie = chartdataPie;

        totals.meanDays = this.data.items.reduce(function (accumulator, item) {
            return accumulator + item.COUNT_DAYS;
        }, 0) / this.data.items.length;

        $('.totals .totals-table tbody').html(`
                                <tr>
                                <td>Всего задач</td><td>${(totals.all) ? totals.all : 0}</td>
                                </tr>
                                <tr>
                                <td>Среднее время продолжительности задачи (дн.)</td><td>${(totals.meanDays) ? totals.meanDays.toFixed(2) : ''}</td>
                                </tr>
                                
                                `);


    }

    createTable() {
        let items = this.data.items;

        if (this.objSort.property && this.objSort.sort) {
            items = this.sortArrayObjects(items, this.objSort.property, this.objSort.sort)
        }

        if (!this.IsIterrable(items)) {
            $("#report .table-tasks tbody").html('');
            $("#report .params-list").html('');
            alert('Данные по фильтру не найдены');
            return console.error(items);
        };
        $("#report .table-tasks tbody").html('');
        for (let item of items) {

            let count_days = (item.COUNT_DAYS) ? `${item.COUNT_DAYS} ${this.num_word(item.COUNT_DAYS, ['день', 'дня', 'дней'])}` : '0 дней';

            //let stage = this.getStage({ closedDate: item.CLOSED_DATE, start_prod: item.START_PROD });

            let user = this.objUsers[item.RESPONSIBLE_ID];

            item.UF_AUTO_691625133653 = (item.UF_AUTO_691625133653 == 'Y') ? 'Да' : item.UF_AUTO_691625133653;
            item.UF_AUTO_691625133653 = (item.UF_AUTO_691625133653 == 'N') ? 'Нет' : item.UF_AUTO_691625133653;


            let userinfoDiv = (user) ? `<div data-user="${item.RESPONSIBLE_ID}"><img src="${user.PHOTO.src}" class="personal-photo">${user.NAME}  ${user.LAST_NAME}</div>` : 'Сотрудник не из отдела продаж';
            $("#report .table-tasks tbody").append(`
                <tr data-task_id="${item.ID}">
                    <td>
                        <a href="/company/personal/user/63/tasks/task/view/${item.ID}/">${item.TITLE}</a>
                    </td>
                    <td>
                        ${userinfoDiv}
                    </td>
                    <td>
                        ${item.CREATED_DATE}
                    </td>
                    <td>
                        ${(item.CLOSED_DATE) ? item.CLOSED_DATE : '---'}
                    </td>
                    <td>
                        ${count_days}
                    </td>
                  
       

                </tr>
            `);
        }
    }

    createParamsList() {
        let params = this.data.params;
        let filterTitles = this.filterTitles;
        let paramsList = {};

        let values = { "Y": "Да", "N": 'Нет', "DESC": 'Новые', "ASC": 'Старые' };

        for (let param_code in params) {
            if (params[param_code] == "Y" || params[param_code] == "N" || params[param_code] == "DESC" || params[param_code] == "ASC") params[param_code] = values[params[param_code]]
            if (params[param_code]) paramsList[param_code] = {
                title: filterTitles[param_code],
                value: params[param_code]
            };

        }

        this.renderParamsList(paramsList);
    }

    renderParamsList(paramsList) {
        console.log('paramsList', paramsList);
        $('.params-list').html('').append(`<table class="params-table">
                                    <tbody>
                                    </tbody>
                                </table>`);
        for (let param_code in paramsList) {
            $('.params-table tbody')
                .append(`<tr>
                            <td>${paramsList[param_code].title}</td>
                            <td>${paramsList[param_code].value}</td>
                        </tr>`)
        }
    }

    async renderReport() {
        let response = await this.getTasks();
        if (!this.IsJsonString(response)) {
            $("#report .table-tasks tbody").html('');
            alert('Данные по фильтру не найдены');
            return console.error(response);
        };

        this.data = JSON.parse(response);

        this.createTable();

        this.createParamsList();

        this.createTotals();

        this.createChart();

        this.createChartPie();
    }

    sortArrayObjects(arSortable, sortableProperty, sort = 'asc') {
        return arSortable.sort(function (a, b) {
            if (sortableProperty == 'CREATED_DATE') {
                let aa = new Date(a[sortableProperty]);
                let bb = new Date(b[sortableProperty]);
                if (aa > bb) {
                    return (sort == 'asc') ? 1 : -1;
                }
                if (aa < bb) {
                    return (sort == 'asc') ? -1 : 1;
                }
                // a должно быть равным b
                return 0;
            } else {
                a[sortableProperty] = (a[sortableProperty]) ? a[sortableProperty] : 0;
                b[sortableProperty] = (b[sortableProperty]) ? b[sortableProperty] : 0;
                if (a[sortableProperty] > b[sortableProperty]) {
                    return (sort == 'asc') ? 1 : -1;
                }
                if (a[sortableProperty] < b[sortableProperty]) {
                    return (sort == 'asc') ? -1 : 1;
                }
                // a должно быть равным b
                return 0;
            }


        });
    }

    createChart() {
        let This = this;
        am4core.ready(function () {

            // Themes begin
            am4core.useTheme(am4themes_animated);
            // Themes end

            var chart = am4core.create("chartdiv", am4charts.XYChart);
            chart.padding(40, 40, 40, 40);

            var categoryAxis = chart.yAxes.push(new am4charts.CategoryAxis());
            categoryAxis.renderer.grid.template.location = 0;
            categoryAxis.dataFields.category = "name";
            categoryAxis.renderer.minGridDistance = 1;
            categoryAxis.renderer.inversed = true;
            categoryAxis.renderer.grid.template.disabled = true;

            var valueAxis = chart.xAxes.push(new am4charts.ValueAxis());
            valueAxis.min = 0;

            var series = chart.series.push(new am4charts.ColumnSeries());
            series.dataFields.categoryY = "name";
            series.dataFields.valueX = "steps";
            series.tooltipText = "{valueX.value}"
            series.columns.template.strokeOpacity = 0;
            series.columns.template.column.cornerRadiusBottomRight = 5;
            series.columns.template.column.cornerRadiusTopRight = 5;

            var labelBullet = series.bullets.push(new am4charts.LabelBullet())
            labelBullet.label.horizontalCenter = "left";
            labelBullet.label.dx = 10;
            labelBullet.label.text = "{values.valueX.workingValue.formatNumber('#.00')}";
            labelBullet.locationX = 1;

            // as by default columns of the same series are of the same color, we add adapter which takes colors from chart.colors color set
            series.columns.template.adapter.add("fill", function (fill, target) {
                return chart.colors.getIndex(target.dataItem.index);
            });

            categoryAxis.sortBySeries = series;
            chart.data = This.chartData;



        }); // end am4core.ready()
    }

    createChartPie() {
        let This = this;
        am4core.ready(function () {

            // Themes begin
            am4core.useTheme(am4themes_animated);
            // Themes end

            // Create chart
            var chartPie = am4core.create("chartdiv_pie", am4charts.PieChart);
            chartPie.data = This.chartDataPie;
            // Add and configure Series
            var pieSeries = chartPie.series.push(new am4charts.PieSeries());
            pieSeries.dataFields.value = "tasks";
            pieSeries.dataFields.category = "name";
            pieSeries.slices.template.stroke = am4core.color("#fff");
            pieSeries.slices.template.strokeOpacity = 1;

            // This creates initial animation
            pieSeries.hiddenState.properties.opacity = 1;
            pieSeries.hiddenState.properties.endAngle = -90;
            pieSeries.hiddenState.properties.startAngle = -90;

            chartPie.hiddenState.properties.radius = am4core.percent(0);


        }); // end am4core.ready()
    }
}



var ReportMaster = BX.namespace("ReportMaster"); // объявляем пространство имен
ReportMaster = new Report;
class Report {
    /**
     * Фильтр вида {dateFrom:'01.07.2021', dateTo:'31.08.2021', closed:true , start_prod:true, user:12}
     */
    filter = [];
    $reportContainer = {};
    $arrUsersSelect = [];
    objUsers = [];
    data = {};
    filterTitles = {
        dateFrom: 'Интервал от',
        dateTo: 'Интервал до',
        closed: 'Закрытые',
        start_prod: 'Запущен БП производства',
        user: 'Сотрудник',
    }
    paramsList = {};
    chartData = []; //данные для графика
    firstDay;

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

        //this.setFilter({ dateFrom: this.firstDay });

        this.$reportContainer
            .append(`<div class="filter">
                                    <div class="filter-item">
                                        <label class="label" for="dateFrom">${filterTitles.dateFrom}:</label>
                                        <input type="text" onclick="BX.calendar({node: this, field: this, bTime: false});" class="js-filter-input" name="dateFrom" id="dateFrom" >
                                    </div>
                                    <div class="filter-item dateTo">
                                        <label class="label" for="dateTo">${filterTitles.dateTo}:</label>
                                        <input type="text" onclick="BX.calendar({node: this, field: this, bTime: false});" class="js-filter-input" name="dateTo"  id="dateTo">
                                    </div>
                                    <div class="filter-item" data-filter="closed">
                                        <label class="label" for="closed">${filterTitles.closed}:</label>
                 
                                    </div>
                                    <div class="filter-item" data-filter="start_prod">
                                        <label class="label" for="start_prod">${filterTitles.start_prod}:</label>
                                        
                                    </div>
                                    <div class="filter-item" data-filter="user">
                                        <label class="label" for="user">${filterTitles.user}:</label>

                                    </div>
                                </div>`)
            .append(`<div class="params-list"><div>`)
            .append(`<div class="totals"><table class="totals-table"><tbody></tbody></table><div id="chartdiv"></div><div>`)
            .append(`<table class="table-tasks">
                        <thead>
                            <tr>
                                <th>
                                    Задача
                                </th>
                                <th>
                                    Ответственный
                                </th>
                                <th>
                                    Дата создания
                                </th>
                                <th>
                                    Дата закрытия
                                </th>
                                <th>
                                    Количество дней
                                </th>
                                <th>
                                    Стадия
                                </th>
                            </tr>
                        </thead>
                        <tbody>

                        </tbody>
                    </table>`);
        $('[data-filter="user"]').append(this.$arrUsersSelect);
        $('[data-filter="start_prod"]').append(this.createSelect('start_prod'));
        $('[data-filter="closed"]').append(this.createSelect('closed'));
        this.bindInputChange();
        this.renderReport();

    }

    bindInputChange() {
        let This = this;
        $('.js-filter-input').on('change', function() {


            let params = {
                dateFrom: $('input[name="dateFrom"]').val(),
                dateTo: $('input[name="dateTo"]').val(),
                closed: $('select[name="closed"]').val(),
                start_prod: $('select[name="start_prod"]').val(),
                user: $('select[name="user"]').val(),
            }
            console.log("🚀 ~ file: script.js ~ line 59 ~ Report ~ $ ~ params", params)

            This.setFilter(params);
            This.renderReport();
        });

    }


    async createUsersList() {
        let objUsers = await this.getUsers();
        if (this.IsJsonString(objUsers)) objUsers = JSON.parse(objUsers);

        let arrUsers = Object.values(objUsers);


        let $select = $('<select name="user" class="js-filter-input" id="user-filt"><option value="">Все сотрудники</option></select>');

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
            for (let i of iterable) {}
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
            url: "/local/components/sp_csn/table/ajax.php",
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
            url: "/local/components/sp_csn/table/ajax.php",
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
        let objUsers = this.objUsers;
        let usersTotal = this.data.totals.users;
        let totals = this.data.totals;

        for (let user_id in usersTotal) {
            if (!objUsers[user_id]) continue;
            chartdata.push({
                "name": `${objUsers[user_id].NAME} ${objUsers[user_id].LAST_NAME}`,
                'steps': usersTotal[user_id],
                "href": objUsers[user_id].PHOTO.src
            });
        }

        this.chartData = chartdata;

        totals.meanDays = this.data.items.reduce(function(accumulator, item) {
            return accumulator + item.COUNT_DAYS;
        }, 0) / this.data.items.length;

        $('.totals .totals-table tbody').html(`
                                <tr>
                                <td>Всего задач</td><td>${(totals.all) ? totals.all : 0}</td>
                                </tr>
                                <tr>
                                <td>Закрытых</td><td>${(totals.closed.Y) ? totals.closed.Y : 0}</td>
                                </tr>
                                <tr>
                                <td>Запущено БП производства</td><td>${(totals.start_prod.Y) ? totals.start_prod.Y : 0}</td>
                                </tr>
                                <tr>
                                <td>Закрытых задач без запуска БП производства</td><td>${(totals.closed_not_started) ? totals.closed_not_started : 0}</td>
                                </tr>
                                <tr>
                                <td>Среднее время продолжительности задачи (дн.)</td><td>${(totals.meanDays) ? totals.meanDays.toFixed(2) : ''}</td>
                                </tr>
                                `);


    }

    createTable() {
        let items = this.data.items;

        if (!this.IsIterrable(items)) {
            $("#report .table-tasks tbody").html('');
            $("#report .params-list").html('');
            alert('Данные по фильтру не найдены');
            return console.error(items);
        };
        $("#report .table-tasks tbody").html('');
        for (let item of items) {

            let count_days = (item.COUNT_DAYS) ? `${item.COUNT_DAYS} ${this.num_word(item.COUNT_DAYS, ['день', 'дня', 'дней'])}` : '';

            let stage = this.getStage({ closedDate: item.CLOSED_DATE, start_prod: item.START_PROD });

            let user = this.objUsers[item.RESPONSIBLE_ID];

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
                    <td class="stap-stage" title="${stage.title}">
                        ${stage.bar}
                    </td>
                </tr>
            `);
        }
    }

    createParamsList() {
        let params = this.data.params;
        let filterTitles = this.filterTitles;
        let paramsList = {};

        let values = { "Y": "Да", "N": 'Нет' };

        for (let param_code in params) {
            if (params[param_code] == "Y" || params[param_code] == "N") params[param_code] = values[params[param_code]]
            if (params[param_code]) paramsList[param_code] = {
                title: filterTitles[param_code],
                value: params[param_code]
            };

        }

        this.renderParamsList(paramsList);
    }

    renderParamsList(paramsList) {
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
    }

    createChart() {
        let This = this;
        am4core.ready(function() {

            // Themes begin
            am4core.useTheme(am4themes_animated);
            // Themes end

            /**
             * Chart design taken from Samsung health app
             */

            var chart = am4core.create("chartdiv", am4charts.XYChart);
            chart.hiddenState.properties.opacity = 0; // this creates initial fade-in

            chart.paddingBottom = 30;

            chart.data = This.chartData;

            var categoryAxis = chart.xAxes.push(new am4charts.CategoryAxis());
            categoryAxis.dataFields.category = "name";
            categoryAxis.renderer.grid.template.strokeOpacity = 0;
            categoryAxis.renderer.minGridDistance = 10;
            categoryAxis.renderer.labels.template.dy = 35;
            categoryAxis.renderer.tooltip.dy = 35;

            var valueAxis = chart.yAxes.push(new am4charts.ValueAxis());
            valueAxis.renderer.inside = true;
            valueAxis.renderer.labels.template.fillOpacity = 0.3;
            valueAxis.renderer.grid.template.strokeOpacity = 0;
            valueAxis.min = 0;
            valueAxis.cursorTooltipEnabled = false;
            valueAxis.renderer.baseGrid.strokeOpacity = 0;

            var series = chart.series.push(new am4charts.ColumnSeries);
            series.dataFields.valueY = "steps";
            series.dataFields.categoryX = "name";
            series.tooltipText = "{valueY.value}";
            series.tooltip.pointerOrientation = "vertical";
            series.tooltip.dy = -6;
            series.columnsContainer.zIndex = 100;

            var columnTemplate = series.columns.template;
            columnTemplate.width = am4core.percent(50);
            columnTemplate.maxWidth = 44;
            columnTemplate.column.cornerRadius(60, 60, 10, 10);
            columnTemplate.strokeOpacity = 0;

            series.heatRules.push({ target: columnTemplate, property: "fill", dataField: "valueY", min: am4core.color("#e5dc36"), max: am4core.color("#5faa46") });
            series.mainContainer.mask = undefined;

            var cursor = new am4charts.XYCursor();
            chart.cursor = cursor;
            cursor.lineX.disabled = true;
            cursor.lineY.disabled = true;
            cursor.behavior = "none";

            var bullet = columnTemplate.createChild(am4charts.CircleBullet);
            bullet.circle.radius = 20;
            bullet.valign = "bottom";
            bullet.align = "center";
            bullet.isMeasured = true;
            bullet.mouseEnabled = false;
            bullet.verticalCenter = "bottom";
            bullet.interactionsEnabled = false;

            var hoverState = bullet.states.create("hover");
            var outlineCircle = bullet.createChild(am4core.Circle);
            outlineCircle.adapter.add("radius", function(radius, target) {
                var circleBullet = target.parent;
                return circleBullet.circle.pixelRadius + 5;
            })

            var image = bullet.createChild(am4core.Image);
            image.width = 40;
            image.height = 40;
            image.horizontalCenter = "middle";
            image.verticalCenter = "middle";
            image.propertyFields.href = "href";

            image.adapter.add("mask", function(mask, target) {
                var circleBullet = target.parent;
                return circleBullet.circle;
            })

            var previousBullet;
            chart.cursor.events.on("cursorpositionchanged", function(event) {
                var dataItem = series.tooltipDataItem;

                if (dataItem.column) {
                    var bullet = dataItem.column.children.getIndex(1);

                    if (previousBullet && previousBullet != bullet) {
                        previousBullet.isHover = false;
                    }

                    if (previousBullet != bullet) {

                        var hs = bullet.states.getKey("hover");
                        hs.properties.dy = -bullet.parent.pixelHeight + 30;
                        bullet.isHover = true;

                        previousBullet = bullet;
                    }
                }
            })

        }); // end am4core.ready()
    }
}



var ReportMaster = BX.namespace("ReportMaster"); // объявляем пространство имен
ReportMaster = new Report;
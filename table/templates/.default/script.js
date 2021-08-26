class Report {
    /**
     * Фильтр вида {dateFrom:'01.07.2021', dateTo:'31.08.2021', closed:true , start_prod:true, user:12}
     */
    filter = [];
    $reportContainer = {};
    $arrUsersSelect = [];
    arrUsers = [];

    constructor() {
        this.$reportContainer = $('#report');
        this.init();
    }

    async init() {
        await this.createUsersList();
        this.$reportContainer
            .append(`<div class="filter">
                                    <div class="filter-item">
                                        <label class="label" for="dateFrom">Интервал от:</label>
                                        <input type="text" onclick="BX.calendar({node: this, field: this, bTime: false});" class="js-filter-input" name="dateFrom" id="dateFrom">
                                    </div>
                                    <div class="filter-item dateTo">
                                        <label class="label" for="dateTo">Интервал до:</label>
                                        <input type="text" onclick="BX.calendar({node: this, field: this, bTime: false});" class="js-filter-input" name="dateTo"  id="dateTo">
                                    </div>
                                    <div class="filter-item" data-filter="closed">
                                        <label class="label" for="dateTo">Закрытые:</label>
                 
                                    </div>
                                    <div class="filter-item" data-filter="start_prod">
                                        <label class="label" for="start_prod">Запущен БП производства:</label>
                                        
                                    </div>
                                    <div class="filter-item" data-filter="user">
                                        <label class="label" for="user-filt">Сотрудник :</label>

                                    </div>
                                </div>`)
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

    }

    bindInputChange() {
        let This = this;
        $('.js-filter-input').on('change', function() {


            let params = {
                dateFrom: $('input[name="dateFrom"]').val(),
                dateTo: $('input[name="dateTo"]').val(),
                closed: $('select[name="closed"]').val(),
                start_prod: $('select[name="start_prod"]').val(),
                responsible_id: $('select[name="user"]').val(),
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

    async renderReport() {
        let response = await this.getTasks();

        if (!this.IsJsonString(response)) return console.error(response);
        let data = JSON.parse(response);

        if (!this.IsIterrable(data)) return console.error(data);

        $("#report .table-tasks tbody").html('');
        for (let item of data) {

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
                        ${(item.CLOSED_DATE)?item.CLOSED_DATE:'---'}
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
}


var ReportMaster = BX.namespace("ReportMaster"); // объявляем пространство имен
ReportMaster = new Report;
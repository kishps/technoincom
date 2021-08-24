class Report {
    /**
     * Фильтр вида [dateFrom:'01.07.2021', dateTo:'31.08.2021', closed:true , start_prod:true]
     */
    filter = [];
    reportContainer = {};

    init() {
        this.reportContainer = $('#report');

        $('#report').append(`<div class="filter">
                                
                            </div>`)

    }

    IsJsonString(str) {
        try {
            JSON.parse(str);
        } catch (e) {
            return false;
        }
        return true;
    }

    IsIterrable(iterable){
        try {
            for (let i of iterable) {
            }
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


    setFilter(params) {
        this.filter = params;
    }


    async renderReport() {
        let response = await this.getTasks();

        if (!this.IsJsonString(response)) return 'not json';

        let data = JSON.parse(response);

        if(!this.IsIterrable(data)) console.error(data);

        $('#report').html('');
        for (let item of data) {
            let count_days = `${item.COUNT_DAYS} ${this.num_word(item.COUNT_DAYS, ['день', 'дня', 'дней'])}`
            $("#report").append(`
                    <div style="padding: 25px;" class="${(item.START_PROD) ? 'start' : ''}">
                        <div>
                            <b>Задача</b> : <a href="/company/personal/user/63/tasks/task/view/${item.ID}/">${item.TITLE}</a>
                        </div>
                        <div>
                            <b>Дата создания</b> : ${item.CREATED_DATE}
                        </div>
                        <div>
                            <b>Дата закрытия</b> : ${item.CLOSED_DATE}
                        </div>
                        <div>
                            <b>Длительность задачи</b> : ${count_days}
                        <div>
                    </div>
                `);
        }
    }
}


var ReportMaster = BX.namespace("ReportMaster");  // объявляем пространство имен
ReportMaster = new Report;

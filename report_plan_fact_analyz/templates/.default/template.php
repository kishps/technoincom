<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();
CJSCore::Init(array("jquery2", "amcharts4_theme_animated", "amcharts4", "amcharts4_maps"));
?><?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();
CJSCore::Init(array("jquery2", "amcharts4_theme_animated", "amcharts4", "amcharts4_maps"));
?>
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
<link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
<script src="/bitrix/js/main/amcharts/4.8.5//themes/material.js"></script>
<script src="/bitrix/js/main/amcharts/4.8.5//themes/frozen.js"></script>
<style>
    .pushthebutton {
        border: 2px solid #46db01 !important;
    }
</style>
<!-- Resources -->
<div>
    <h1 class="report-title"></h1>
    <div class="js-allreport">
        <svg viewBox="0 0 100 80" width="40" height="40">
            <rect width="100" height="15" rx="0"></rect>
            <rect y="30" width="100" height="15" rx="0"></rect>
            <rect y="60" width="100" height="15" rx="0"></rect>
        </svg>
    </div>
    <div style="clear: both;"></div>
</div>
<section id="report_block">
    <article class="report">
        <div id="plans"></div>
    </article>
    <aside class="settings">
        <span class="cls_btn"></span>
        <h2>Настройки</h2>
        <div class="time-type">
            <div class="form_radio_group">
                <div class="form_radio_group-item">
                    <input id="radio-1" type="radio" name="time-type" value="mounth" checked>
                    <label for="radio-1">Месяц</label>
                </div>
                <div class="form_radio_group-item">
                    <input id="radio-2" type="radio" name="time-type" value="quarter">
                    <label for="radio-2">Квартал</label>
                </div>
                <div class="form_radio_group-item">
                    <input id="radio-3" type="radio" name="time-type" value="year">
                    <label for="radio-3">Год</label>
                </div>
            </div>
        </div>
        <div class="time">
            <label for="time"></label>
            <select name="time">
                <option value="01">1</option>
                <option value="02">2</option>
                <option value="03">3</option>
                <option value="04">4</option>
                <option value="05">5</option>
                <option value="06">6</option>
                <option value="07">7</option>
                <option value="08">8</option>
                <option value="09">9</option>
                <option value="10">10</option>
                <option value="11">11</option>
                <option value="12">12</option>
            </select>
        </div>
        <div class="time-year">
            <label for="time-year">Год</label>
            <select name="time-year">
                <option value="2021">2021</option>
                <option value="2022">2022</option>
                <option value="2023">2023</option>
                <option value="2024">2024</option>
                <option value="2025">2025</option>
            </select>
        </div>
        <div class="submit-button">
            <button id="submit-button">Показать</button>
        </div>
        <div class="users">
            <form id="form-user-settings">
                <? foreach ($arResult['USERS']['users'] as $user) { ?>
                    <div class="user" data-user="<?= $user['ID'] ?>">
                        <div class="user-info">
                            <?= $user['NAME'] . " " . $user['LAST_NAME'] ?>
                        </div>
                        <div class="user-plan">
                            <label for="<?= $user['ID'] ?>-user"></label>
                            <input id="<?= $user['ID'] ?>-user" type="text" name="<?= $user['ID'] ?>" value="">
                        </div>
                    </div>
                <? } ?>
                <div class="user" data-user="0">
                    <div class="user-info">
                        <b>Компания</b>
                    </div>
                    <div class="user-plan">
                        <label for="0-user"></label>
                        <input id="0-user" type="text" name="0" value="" readonly>
                    </div>
                </div>
            </form>
            <div class="submit-users-settings">
                <button class="button-users-settings">Сохранить</button>
                <div style="color:green;display:none" class="setting-success">Настройки плана сохранены<div>
                    </div>
                </div>
    </aside>
</section>



<script>
    var PlanFactAnalyz = BX.namespace("PlanFactAnalyz");
    PlanFactAnalyz.data = <?= json_encode($arResult['DATA']['PLAN']) ?>;
    PlanFactAnalyz.templateFolder = <?= json_encode($templateFolder) ?>;
    PlanFactAnalyz.settings = {};

    function makeKards() {
        $('#plans').html('');
        arrFact = PlanFactAnalyz.fact.DATA.PLAN;
        for (element in arrFact) {
            arrFact[element].printed = true;
            //console.log(element);
            if (element !== 'ALL') {
                $('#plans').append(`<div class="user-card" data-id="${arrFact[element].ID}">
										<div class="user-plan">
											<div class="photo-img"><img src="${arrFact[element].PHOTO}" alt="${arrFact[element].NAME}"></div>
											<div class="user-plan-name">
												${arrFact[element].NAME}
											</div>
											<div data-id="${arrFact[element].ID}" class="chart-plan"><div class="total-fact-label"></div><div class="progress-label"></div><div class="plan-label"></div></div>
									</div>

                                    <div data-id="${arrFact[element].ID}" class="open-click"></div>
                                    <div class="summ">
                                        <div class="total-summ"><div>Всего продаж</div><div></div>${(arrFact[element].TOTAL)?new Intl.NumberFormat().format(arrFact[element].TOTAL):''}</div>
                                        <div class="total-plan"><div>План на <span class="time-title">месяц<span></div><div></div>${(PlanFactAnalyz.settings.current)?((PlanFactAnalyz.settings.current[element])?new Intl.NumberFormat().format(PlanFactAnalyz.settings.current[element].PLAN_USER):''):''}</div>
                                        <div class="total-ostatok"><div>Осталось</div><div></div>${(PlanFactAnalyz.settings.current && arrFact[element].TOTAL && PlanFactAnalyz.settings.current[element])?(new Intl.NumberFormat().format(PlanFactAnalyz.settings.current[element].PLAN_USER-arrFact[element].TOTAL)):''}</div>
                                        <div class="total-count"><div>Количество отгрузок</div><div></div>${(arrFact[element].TASKS)?(Object.keys(arrFact[element].TASKS).length):''}</div>
                                    </div>
                                    <div class="table">
                                        <table data-id="${arrFact[element].ID}">
                                            <thead>
                                                <td>
                                                    Задача
                                                </td>
                                                <td>
                                                    Сделка
                                                </td>
                                                <td>
                                                    Дата закрытия
                                                </td>
                                                <td>
                                                    Группа товаров
                                                </td>
                                                <td>
                                                    Сумма
                                                </td>
                                            </thead>
                                            <tbody>
                                            </tbody>
                                        </table>
										<div>
                                       <div id="${arrFact[element].ID}-chart" class="chart-groups"></div>
										<div class="chart-title">Группы товаров</div>
										</div>
										<div>
                                       <div id="${arrFact[element].ID}-chart-category" class="chart-groups"></div>
										<div class="chart-title">
                                                ${(PlanFactAnalyz.fact.DATA.REPORT_TOTALS && PlanFactAnalyz.fact.DATA.REPORT_TOTALS[element] && PlanFactAnalyz.fact.DATA.REPORT_TOTALS[element].DEALS_SUPPORT_TOTAL)?`<span>Всего продаж сопровождения<span><br>${new Intl.NumberFormat().format(PlanFactAnalyz.fact.DATA.REPORT_TOTALS[element].DEALS_SUPPORT_TOTAL)} ₽`:'Категории сделок' }
                                        </div>
									   </div>
									</div>
                                </div>`);
                for (task in arrFact[element].TASKS) {
                    $(`table[data-id="${arrFact[element].ID}"] tbody`).append(`
                        <tr>
                            <td>
                                <a href="${arrFact[element].TASKS[task].LINK}">${arrFact[element].TASKS[task].TITLE}</a>
                            </td>
                            <td>
                                <a href="${arrFact[element].TASKS[task].DEAL_LINK}">${arrFact[element].TASKS[task].DEAL_TITLE}</a>
                            </td>
                            <td>
                                ${arrFact[element].TASKS[task].DATE_CLOSED}
                            </td>
                            <td>
                                ${arrFact[element].TASKS[task].PRODUCT_GROUP}
                            </td>
                            <td>
                                ${(arrFact[element].TASKS[task].SUMM_FOR_DEAL)?new Intl.NumberFormat().format(arrFact[element].TASKS[task].SUMM_FOR_DEAL)+"&nbsp;₽":''}
                            </td>
                        </tr>
                        `);
                }
                if (PlanFactAnalyz.settings.current && PlanFactAnalyz.settings.current[element] && PlanFactAnalyz.settings.current[element].PLAN_USER) {
                    let progressVal = parseInt((arrFact[element].TOTAL / PlanFactAnalyz.settings.current[element].PLAN_USER) * 100);
                    //console.log(progressVal);
                    let totalFactLabel = $(`.chart-plan[data-id="${element}"] .total-fact-label`);
                    let progressLabel = $(`.chart-plan[data-id="${element}"] .progress-label`);
                    let planLabel = $(`.chart-plan[data-id="${element}"] .plan-label`);
                    $(`.chart-plan[data-id="${element}"]`).progressbar({
                        value: progressVal
                    });
                    progressLabel.text(progressVal + "%");
                    totalFactLabel.text((arrFact[element] && arrFact[element].TOTAL) ? new Intl.NumberFormat().format(arrFact[element].TOTAL) + " ₽" : '');
                    planLabel.text((PlanFactAnalyz.settings.current && PlanFactAnalyz.settings.current[element].PLAN_USER) ? new Intl.NumberFormat().format(PlanFactAnalyz.settings.current[element].PLAN_USER) + " ₽" : '');

                }
                /*[{
                    "Группа товаров": "Lithuania",
                    "руб.": 501.9
                }, {
                    "Группа товаров": "The Netherlands",
                    "руб.": 50
                }]*/

                let dataGrouTovars = [];
                for (let gru in arrFact[element].TOTAL_GROUPS) {
                    dataGrouTovars.push({
                        "Группа товаров": gru,
                        "руб.": arrFact[element].TOTAL_GROUPS[gru]
                    });
                }
                chartGroupsRender(element, dataGrouTovars);

                let dataDealCategory = [{
                    "Категория сделки": "Сопровождение",
                    "руб.": (PlanFactAnalyz.fact.DATA.REPORT_TOTALS[element].DEALS_SUPPORT_TOTAL) ? PlanFactAnalyz.fact.DATA.REPORT_TOTALS[element].DEALS_SUPPORT_TOTAL : '',
                    "color": am4core.color("#ff6347")
                }, {
                    "Категория сделки": "Собственная",
                    "руб.": (PlanFactAnalyz.fact.DATA.REPORT_TOTALS[element].DEALS_OWN_TOTAL) ? PlanFactAnalyz.fact.DATA.REPORT_TOTALS[element].DEALS_OWN_TOTAL : '',
                    "color": am4core.color("#96da07")
                }];
                chartDealCategory(element, dataDealCategory);



            } else {
                $('#plans').append(`<div class="user-card  company" data-id="0">
									<div class="company-plan">
                                    <div class="user-plan-name">
                                        Компания
                                    </div>
                                    <div data-id="0" class="chart-plan"><div class="total-fact-label"></div><div class="progress-label"></div><div class="plan-label"></div></div>
									</div>
									 <div data-id="0" class="open-click"></div>
									<div class="0-totals">
                                        <div class="total-group">
                                            <div class="groupname">Итого</div>
                                            <div class="groupsumm total-company">${(arrFact[element].TOTAL)?new Intl.NumberFormat().format(arrFact[element].TOTAL):'0'} ₽</div>
                                         </div>
										<div class="total-group">
                                            <div class="groupname">Количество отгрузок</div>
                                            <div class="groupsumm  total-company">${arrFact[element].TOTAL_TASKS}</div>
                                         </div>
                                         <div class="total-group">
                                            <div class="groupname">ИТОГО собственные + сопровождение</div>
                                            <div class="groupsumm  total-company">${(PlanFactAnalyz.fact && PlanFactAnalyz.fact.DATA && PlanFactAnalyz.fact.DATA.REPORT_TOTALS &&PlanFactAnalyz.fact.DATA.REPORT_TOTALS[element] && PlanFactAnalyz.fact.DATA.REPORT_TOTALS[element].DEALS_TOTAL)?new Intl.NumberFormat().format(PlanFactAnalyz.fact.DATA.REPORT_TOTALS[element].DEALS_TOTAL)+" ₽":''}</div>
                                         </div>
                                    </div>		
									<div>
										<div id="0-chart" class="chart-groups"></div>
										<div class="chart-title">Группы товаров</div>
									</div>
									<div>
                                    <div id="0-chart-category" class="chart-groups"></div>
									<div class="chart-title">Категории сделок</div>
									</div>
                                </div>`);
                for (group in arrFact[element].TOTAL_GROUPS) {
                    $('.0-totals').prepend(`<div class="total-group">

                                            <div class="groupname">${group}</div>
                                            <div class="groupsumm">${new Intl.NumberFormat().format(arrFact[element].TOTAL_GROUPS[group])} ₽</div>
                                            
                                         </div>`)
                }

                if (PlanFactAnalyz.settings.current && PlanFactAnalyz.settings.current[0] && PlanFactAnalyz.settings.current[0].PLAN_USER && PlanFactAnalyz.settings.current[0].PLAN_USER > 0) {
                    let totalFactLabel = $(`.chart-plan[data-id="0"] .total-fact-label`);
                    let progressVal = parseInt((arrFact[element].TOTAL / PlanFactAnalyz.settings.current[0].PLAN_USER) * 100);
                    //console.log(progressVal);
                    let progressLabel = $(`.chart-plan[data-id="0"] .progress-label`);
                    let planLabel = $(`.chart-plan[data-id="0"] .plan-label`);
                    $(`.chart-plan[data-id="0"]`).progressbar({
                        value: progressVal
                    });
                    progressLabel.text(progressVal + "%");
                    totalFactLabel.text((arrFact[element] && arrFact[element].TOTAL) ? new Intl.NumberFormat().format(arrFact[element].TOTAL) + " ₽" : '');
                    planLabel.text(new Intl.NumberFormat().format(PlanFactAnalyz.settings.current[0].PLAN_USER) + " ₽");

                }
                let dataGrouTovars = [];
                for (let gru in arrFact[element].TOTAL_GROUPS) {
                    dataGrouTovars.push({
                        "Группа товаров": gru,
                        "руб.": arrFact[element].TOTAL_GROUPS[gru]
                    })
                }
                chartGroupsRender(0, dataGrouTovars);

                let dataDealCategory = [{
                    "Категория сделки": "Сопровождение",
                    "руб.": (PlanFactAnalyz.fact.DATA.REPORT_TOTALS[element].DEALS_SUPPORT_TOTAL) ? PlanFactAnalyz.fact.DATA.REPORT_TOTALS[element].DEALS_SUPPORT_TOTAL : '',
                    "color": am4core.color("#ff6347")
                }, {
                    "Категория сделки": "Собственная",
                    "руб.": (PlanFactAnalyz.fact.DATA.REPORT_TOTALS[element].DEALS_OWN_TOTAL) ? PlanFactAnalyz.fact.DATA.REPORT_TOTALS[element].DEALS_OWN_TOTAL : '',
                    "color": am4core.color("#96da07")
                }];
                chartDealCategory(0, dataDealCategory);

            }
        }
        bindClickActionAcordeon();
    }

    function makeOtherKards() {
        let arrCurrPlan = PlanFactAnalyz.settings.current;
        for (let planItem in arrCurrPlan) {
            if (PlanFactAnalyz.fact && PlanFactAnalyz.fact.DATA && PlanFactAnalyz.fact.DATA.PLAN && PlanFactAnalyz.fact.DATA.PLAN[planItem] && PlanFactAnalyz.fact.DATA.PLAN[planItem].printed) continue;
            if (planItem == 0 || planItem === 0) continue;

            let cardPlanItem = `<div class="user-card" data-id="${planItem}">
                                    <div class="user-plan">
                                        <div class="photo-img"><img src="${PlanFactAnalyz.fact.USERS[planItem].PHOTO.src}" alt="${PlanFactAnalyz.fact.USERS[planItem].NAME}"></div>
                                        <div class="user-plan-name">
                                            ${PlanFactAnalyz.fact.USERS[planItem].NAME} ${PlanFactAnalyz.fact.USERS[planItem].LAST_NAME}
                                        </div>
                                        <div data-id="${planItem}" class="chart-plan"><div class="progress-label"></div><div class="plan-label"></div>
                                        </div>
                                    </div>
                                </div>
            `;

            if ($('.user-card.company').length > 0) {
                $('.user-card.company').before(cardPlanItem);
                console.log('before');
            } else {
                $('#plans').prepend(cardPlanItem);
                console.log('prepend');
            }

            if (PlanFactAnalyz.settings.current && PlanFactAnalyz.settings.current[planItem] && PlanFactAnalyz.settings.current[planItem].PLAN_USER) {
                let progressVal = 0;
                //console.log(progressVal);
                let progressLabel = $(`.chart-plan[data-id="${planItem}"] .progress-label`);
                let planLabel = $(`.chart-plan[data-id="${planItem}"] .plan-label`);
                $(`.chart-plan[data-id="${planItem}"]`).progressbar({
                    value: progressVal
                });
                progressLabel.text(progressVal + "%");
                planLabel.text((PlanFactAnalyz.settings.current && PlanFactAnalyz.settings.current[planItem].PLAN_USER) ? new Intl.NumberFormat().format(PlanFactAnalyz.settings.current[planItem].PLAN_USER) + " ₽" : '');

            }

        }

        let usersEmpty = PlanFactAnalyz.fact.USERS;
        for (let planItem in usersEmpty) {
            if (PlanFactAnalyz.settings && PlanFactAnalyz.settings.current && PlanFactAnalyz.settings.current[planItem] && PlanFactAnalyz.settings.current[planItem].PLAN_USER) continue;
            if (planItem == 0 || planItem === 0) continue;

            let cardPlanItem = `<div class="user-card" data-id="${planItem}">
                                    <div class="user-plan">
                                        <div class="photo-img"><img src="${PlanFactAnalyz.fact.USERS[planItem].PHOTO.src}" alt="${PlanFactAnalyz.fact.USERS[planItem].NAME}"></div>
                                        <div class="user-plan-name">
                                            ${PlanFactAnalyz.fact.USERS[planItem].NAME} ${PlanFactAnalyz.fact.USERS[planItem].LAST_NAME}
                                        </div>
                                       
                                        </div>
                                    </div>
                                </div>
            `;

            if ($('.user-card.company').length > 0) {
                $('.user-card.company').before(cardPlanItem);
                console.log('before');
            } else {
                $('#plans').prepend(cardPlanItem);
                console.log('prepend');
            }

        }
    }

    function render_report(dFrom, dTo) {
        $.ajax({
            url: PlanFactAnalyz.templateFolder + '/ajax_fact_otgruz.php',
            type: "POST",
            dataType: "html",
            data: {
                sessid: BX.bitrix_sessid(), //отправляем id сессии
                F_DATE_FROM: dFrom,
                F_DATE_TO: dTo,

            },
        }).done(function(data) {
            PlanFactAnalyz.fact = JSON.parse(data.trim());
            makeKards();
            makeOtherKards();
            $('.report-title').text('Отчет за ' + PlanFactAnalyz.settings.currentTypeRu + " " + PlanFactAnalyz.settings.currentDate);
            $('.time-title').text(PlanFactAnalyz.settings.currentTypeRu);
            /**сортировка как в плане  */
            Object.entries($('.user[data-user]'))
                .map((el) => {
                    if ($(el).hasClass('user')) {
                        return $(el[1]).data('user')
                    }
                })
                .forEach((el1) => $('#plans').append($(`.user-card[data-id="${el1}"`)));
        });
    }



    function typeTimeEdit() {
        let timeType = $('input[name="time-type"]:checked').val();
        switch (timeType) {
            case 'mounth':
                $('label[for="time"]').html('Месяц');
                $('.time').show();
                $('select[name="time"] option').show();
                break;
            case 'year':
                $('.time').hide();
                break;
            case 'quarter':
                $('label[for="time"]').html('Квартал');
                $('.time').show();
                for (let i = 5; i <= 12; i++) {
                    let st = (i <= 9) ? '0' : '';
                    $('select[name="time"] option[value="' + st + i + '"]').hide();
                }
                break;
        }
        $('#submit-button').addClass('pushthebutton');
    }

    function setInputsPlan() {
        if (PlanFactAnalyz.settings.current) {
            for (userId in PlanFactAnalyz.settings.current) {
                $('#' + userId + '-user').val('');
                $('#' + userId + '-user').val(PlanFactAnalyz.settings.current[userId].PLAN_USER);
            }
        } else {
            $('.users input').val('');
        }
    }

    function getSetPlan() {

        console.log('getSetPlan');
        switch (PlanFactAnalyz.settings.timeType) {
            case 'mounth':
                PlanFactAnalyz.settings.current = (PlanFactAnalyz.data && PlanFactAnalyz.data.mounth && PlanFactAnalyz.data.mounth[PlanFactAnalyz.settings.time + "-" + PlanFactAnalyz.settings.year]) ? PlanFactAnalyz.data.mounth[PlanFactAnalyz.settings.time + "-" + PlanFactAnalyz.settings.year] : false;
                PlanFactAnalyz.settings.currentDate = PlanFactAnalyz.settings.time + "-" + PlanFactAnalyz.settings.year;
                PlanFactAnalyz.settings.currentTypeRu = 'месяц';
                break;
            case 'year':
                PlanFactAnalyz.settings.current = (PlanFactAnalyz.data && PlanFactAnalyz.data.year && PlanFactAnalyz.data.year[PlanFactAnalyz.settings.year]) ? PlanFactAnalyz.data.year[PlanFactAnalyz.settings.year] : false;
                PlanFactAnalyz.settings.currentDate = PlanFactAnalyz.settings.year;
                PlanFactAnalyz.settings.currentTypeRu = 'год';
                break;
            case 'quarter':
                PlanFactAnalyz.settings.current = (PlanFactAnalyz.data && PlanFactAnalyz.data.quarter && PlanFactAnalyz.data.quarter[PlanFactAnalyz.settings.time + "/" + PlanFactAnalyz.settings.year]) ? PlanFactAnalyz.data.quarter[PlanFactAnalyz.settings.time + "/" + PlanFactAnalyz.settings.year] : false;
                PlanFactAnalyz.settings.currentDate = PlanFactAnalyz.settings.time + "/" + PlanFactAnalyz.settings.year;
                PlanFactAnalyz.settings.currentTypeRu = 'квартал';
                break;
        }
        setInputsPlan();
    }

    function blockedInputs() {
        $('#form-user-settings input').attr('readonly', 'true');
        $('.button-users-settings').hide();
    }

    function unblockedInputs() {
        $('.button-users-settings').show();
        $('#form-user-settings input').removeAttr('readonly');
        $('#0-user').attr('readonly', 'true');
    }

    function hideinputs() {
        $('#form-user-settings').hide();
    }

    function showinputs() {
        $('#form-user-settings').show();
    }

    function parseTimeFilter() {
        PlanFactAnalyz.settings.timeType = $('input[name="time-type"]:checked').val();
        PlanFactAnalyz.settings.time = $('select[name="time"]').val();
        PlanFactAnalyz.settings.year = $('select[name="time-year"]').val();
        document.location.hash = '#-' + PlanFactAnalyz.settings.time + '-' + PlanFactAnalyz.settings.timeType + '-' + PlanFactAnalyz.settings.year;

        switch (PlanFactAnalyz.settings.timeType) {
            case 'mounth':
                let d = new Date;
                d.setMonth(parseInt(PlanFactAnalyz.settings.time));
                d.setDate(0);
                PlanFactAnalyz.settings.timeFrom = `01.${PlanFactAnalyz.settings.time}.${PlanFactAnalyz.settings.year}`;
                PlanFactAnalyz.settings.timeTo = `${d.getDate()}.${PlanFactAnalyz.settings.time}.${PlanFactAnalyz.settings.year}`;
                getSetPlan();
                render_report(PlanFactAnalyz.settings.timeFrom, PlanFactAnalyz.settings.timeTo);
                unblockedInputs();
                break;
            case 'year':
                PlanFactAnalyz.settings.timeFrom = `01.01.${PlanFactAnalyz.settings.year}`;
                PlanFactAnalyz.settings.timeTo = `31.12.${PlanFactAnalyz.settings.year}`;
                getSetPlan();
                render_report(PlanFactAnalyz.settings.timeFrom, PlanFactAnalyz.settings.timeTo);
                blockedInputs();
                break;
            case 'quarter':
                getSetPlan();
                switch (PlanFactAnalyz.settings.time) {
                    case '01':
                        PlanFactAnalyz.settings.timeFrom = `01.01.${PlanFactAnalyz.settings.year}`;
                        PlanFactAnalyz.settings.timeTo = `31.03.${PlanFactAnalyz.settings.year}`;
                        render_report(PlanFactAnalyz.settings.timeFrom, PlanFactAnalyz.settings.timeTo);
                        break;
                    case '02':
                        PlanFactAnalyz.settings.timeFrom = `01.04.${PlanFactAnalyz.settings.year}`;
                        PlanFactAnalyz.settings.timeTo = `30.06.${PlanFactAnalyz.settings.year}`;
                        render_report(PlanFactAnalyz.settings.timeFrom, PlanFactAnalyz.settings.timeTo);
                        break;
                    case '03':
                        PlanFactAnalyz.settings.timeFrom = `01.07.${PlanFactAnalyz.settings.year}`;
                        PlanFactAnalyz.settings.timeTo = `31.09.${PlanFactAnalyz.settings.year}`;
                        render_report(PlanFactAnalyz.settings.timeFrom, PlanFactAnalyz.settings.timeTo);
                        break;
                    case '04':
                        PlanFactAnalyz.settings.timeFrom = `01.10.${PlanFactAnalyz.settings.year}`;
                        PlanFactAnalyz.settings.timeTo = `31.12.${PlanFactAnalyz.settings.year}`;
                        render_report(PlanFactAnalyz.settings.timeFrom, PlanFactAnalyz.settings.timeTo);
                        break;
                    default:
                        $('select[name="time"] option[value="01"]').prop('selected', true);
                        break;
                }
                blockedInputs();
                break;
        }
    }

    function submitSettings() {
        let gets = (function() {
            let a = '&' + $('#form-user-settings').serialize();
            let b = new Object();
            a = a.substring(1).split("&");
            for (let i = 0; i < a.length; i++) {
                c = a[i].split("=");
                b[c[0]] = c[1];
            }
            return b;
        })();
        $.ajax({
            url: PlanFactAnalyz.templateFolder + '/ajax_saver_settings.php',
            type: "POST",
            dataType: "html",
            data: {
                sessid: BX.bitrix_sessid(), //отправляем id сессии
                userPlans: gets,
                time: PlanFactAnalyz.settings.currentDate,
                timeType: PlanFactAnalyz.settings.timeType
            }
        }).done(function(data) {
            //console.log(data);
            let timeType = PlanFactAnalyz.settings.timeType;
            let time = PlanFactAnalyz.settings.currentDate;
            document.location.hash = '#-' + PlanFactAnalyz.settings.time + '-' + PlanFactAnalyz.settings.timeType + '-' + PlanFactAnalyz.settings.year;
            if (!PlanFactAnalyz.data) PlanFactAnalyz.data = {};
            if (!PlanFactAnalyz.data[timeType]) PlanFactAnalyz.data[timeType] = {};
            for (let userkey in gets) {
                if (!PlanFactAnalyz.data[timeType][time]) PlanFactAnalyz.data[timeType][time] = {};
                PlanFactAnalyz.data[timeType][time][userkey] = {
                    'PLAN_TIME': time,
                    'PLAN_TIME_TYPE': timeType,
                    'PLAN_USER': gets[userkey],
                    'PLAN_USERID': userkey
                }
            }
            $('.setting-success').show();
            $('.setting-success').fadeOut(15000);
            document.location.reload();
            //parseTimeFilter();
        });


    }

    function chartDealCategory(userId, chartData) {

        am4core.ready(function() {
            am4core.useTheme(am4themes_animated);
            //am4core.useTheme(am4themes_material);
            let chart = am4core.createFromConfig({
                    "type": "PieChart3D",
                    "data": chartData,
                    "series": [{
                        "type": "PieSeries3D",
                        "labels": {
                            "template": {
                                "type": "AxisLabelCircular",
                                "disabled": true
                            }
                        },
                        "ticks": {
                            "template": {
                                "type": "PieTick",
                                "disabled": true
                            }
                        },
                        "dataFields": {
                            "category": "Категория сделки",
                            "value": "руб."
                        },
                        "slices": {
                            "template": {
                                "propertyFields": {
                                    "fill": 'color'
                                },
                            }
                        }
                    }],
                    "depth": 6,
                    "innerRadius": "40%",
                    "radius": "60%",

                },
                document.getElementById(`${userId}-chart-category`)
            );
            //chart.slices.template.propertyFields.fill = "color";
        });

    }





    function chartGroupsRender(userId, chartData) {

        am4core.ready(function() {
            //am4core.useTheme(am4themes_frozen);
            am4core.useTheme(am4themes_animated);

            let chart = am4core.createFromConfig({
                    "type": "PieChart3D",
                    "data": chartData,
                    "series": [{
                        "type": "PieSeries3D",
                        "labels": {
                            "template": {
                                "type": "AxisLabelCircular",
                                "disabled": true
                            }
                        },
                        "ticks": {
                            "template": {
                                "type": "PieTick",
                                "disabled": true
                            }
                        },
                        "dataFields": {
                            "category": "Группа товаров",
                            "value": "руб."
                        }
                    }],
                    "depth": 6,
                    "innerRadius": "0%",
                    "radius": "60%"
                },
                document.getElementById(`${userId}-chart`)
            );

        });


    }

    function bindClickActionAcordeon() {

        //$('#report_block .user-card').first().addClass('active');
        $('#report_block .open-click').nextAll().hide();

        $('#report_block .open-click').click(function() {
            $('#report_block .user-card[data-id="' + $(this).data('id') + '"] .open-click').parent().toggleClass('active');
            $('#report_block .user-card[data-id="' + $(this).data('id') + '"] .open-click').nextAll().slideToggle(300);
        });

        /******При изменении инпутов калькулировать в общий план*******/
        $('#form-user-settings input').bind('input', function() {
            $('#0-user').val(summInputsToCompany());
            $('.button-users-settings').addClass('pushthebutton');
        });


    }

    function summInputsToCompany() {
        let companyPlan = 0;
        $("#form-user-settings input").each(function(index) {
            if ($(this).attr('name') == '0') return true;
            console.log($(this).val());
            companyPlan += parseInt(($(this).val()) ? $(this).val() : 0);
        });
        return companyPlan;
    }




    $(document).ready(function() {
        if (document.location.hash) {
            let arSettingTime = document.location.hash.split('-');
            $('input[value="' + arSettingTime[2] + '"]').click();
            $('select[name="time"] option[value="' + arSettingTime[1] + '"]').prop('selected', true);
            $('select[name="time-year"] option[value="' + arSettingTime[3] + '"]').prop('selected', true);
        } else {
            let nowDate = new Date();
            let st = ((nowDate.getMonth() * 1 + 1) <= 9) ? '0' : '';
            $('select[name="time"] option[value="' + st + '' + (nowDate.getMonth() * 1 + 1) + '"]').prop('selected', true);
            $('select[name="time-year"] option[value="' + (nowDate.getFullYear()) + '"]').prop('selected', true);
        }

        parseTimeFilter();

        $('input[name="time-type"]').click(function() {
            typeTimeEdit();
            blockedInputs();
            hideinputs();
        });

        $('#submit-button').click(function() {
            parseTimeFilter();
            $(this).removeClass('pushthebutton');
            showinputs();
        });

        $('.button-users-settings').click(function() {
            submitSettings();
            $(this).removeClass('pushthebutton');
        });

        $('[name="time"], [class="time-year"]').change(function() {
            $('#submit-button').addClass('pushthebutton');
        });

        $('select[name="time"]').change(function() {

            if (PlanFactAnalyz.settings.time == $(this).val()) {
                unblockedInputs();
                showinputs();
            } else {
                blockedInputs();
                hideinputs();
            }
        });

        $('select[name="year"]').change(function() {

            if (PlanFactAnalyz.settings.year == $(this).val()) {
                unblockedInputs();
                showinputs();
            } else {
                blockedInputs();
                hideinputs();
            }
        });

        $('.js-allreport').click(function() {
            $('#report_block .open-click').nextAll().slideToggle();
        });


    });
</script>

<script>
    $('#report_block aside .cls_btn').click(function() {
        $(this).parent().toggleClass('active');
    });
</script>
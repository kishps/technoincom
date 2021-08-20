// ##### Звонки ###################################################################

b_voximplant_statistic
    
    CALL_CATEGORY       # external | internal # Всего три записи internal
    CALL_DURATION       # Длительность (сек)
    CALL_FAILED_CODE    # 200 - Успешный звонок; 304 (и другие) - Пропущенный звонок
    CALL_START_DATE     # \Bitrix\Main\Type\DateTime $ar['CALL_START_DATE']->toString()
    INCOMING            # 1 - исходящий; 2 - входящий
    PORTAL_USER_ID      

// ##### Почта ###################################################################

b_mail_mailbox # Почтовые ящики
    ID
    USER_ID
    OPTIONS
        [imap][outcome] => [список папок (названия папок)]

\Bitrix\Mail\MailboxTable::getUserMailboxes($userId = null)
    Список ящиков пользователя
        Личный (может быть несколько; у USER_ID=1 два ящика: 1, 10)
        Те к которым имеет доступ через b_mail_mailbox_access (USER_ID=17 доступ к 27-му, но это его личный)

b_mail_mailbox_access
    MAILBOX_ID
    ACCESS_CODE # U30 - Пользователь ID 30

b_mail_message_uid
    MAILBOX_ID
    DIR_MD5      # Хэш названия папки ("Исходящие", "Папка первая", ...)
    INTERNALDATE # Почти равен "b_mail_message.FIELD_DATE". Т.е. это дата письма. Сверил через https://bitrix.technoincom.ru/mail/list/, потом в БД. Отличается на секунды.
    MESSAGE_ID

b_mail_message
    FIELD_DATE # Дата письма

Bitrix\Mail\Helper\Message::getTotalUnseenForMailboxes
\bitrix\components\bitrix\mail.client.message.list\class.php

iblock 14 Расчет заказа Клиента
    CREATED_BY  # Ответственный Менеджер
    DATE_CREATE # Дата запуска

// ##### Сделка провалена ###################################################################

bitrix:crm.deal
    ELEMENT_ID
    details.php
        'ENTITY_TYPE_ID' => CCrmOwnerType::Deal, // CCrmOwnerType: Deal=2; Contact=3; SuspendedActivity=25;
		'ENTITY_ID' => $arResult['VARIABLES']['deal_id'],
        
        bitrix:crm.entity.details.frame
            bitrix:crm.deal.details
                bitrix:crm.entity.details
                    bitrix:crm.entity.editor
                    bitrix:crm.timeline

b_crm_timeline
    Bitrix\Crm\Timeline\Entity\TimelineTable
        ID
        TYPE_ID             # Bitrix\Crm\Timeline\TimelineType      # CREATION=2; MODIFICATION=3; MARK=6; //WAITING/IGNORED/SUCCESS/RENEW/FAILED
        TYPE_CATEGORY_ID    # \Bitrix\Crm\Timeline\TimelineMarkType # UNDEFINED=0; SUCCESS=2; FAILED=5;
        CREATED             # Дата
        AUTHOR_ID  
        ASSOCIATED_ENTITY_ID
        ASSOCIATED_ENTITY_TYPE_ID

    Фильтр
        TYPE_CATEGORY_ID            # 5 # \Bitrix\Crm\Timeline\TimelineMarkType::FAILED
        CREATED                     # Дата
        AUTHOR_ID                   # Пользователь
        ASSOCIATED_ENTITY_TYPE_ID   # 2 # \CCrmOwnerType::Deal

b_crm_timeline_bind
    Bitrix\Crm\Timeline\Entity\TimelineBindingTable
        OWNER_ID        # b_crm_timeline.ID
        ENTITY_TYPE_ID  # Тип сущности (сделка и т.п.)
        ENTITY_ID       # Идентификатор сущности

b_crm_status
    Bitrix\Crm\StatusTable
   
    ID
    ENTITY_ID   DEAL_STAGE | DEAL_TYPE ...
    STATUS_ID   PREPARATION	Подготовка документов | LOSE Сделка провалена
    NAME

    Bitrix\Crm\Category\DealCategory::getStageList
        CCrmStatus::GetStatusList
            b_crm_status

Bitrix\Crm\DealTable
    b_crm_deal
        STAGE_ID

// ##### Завершено Испытаний ##################################################

b_bp_workflow_instance # Запущенные
    \Bitrix\Bizproc\WorkflowInstanceTable

b_bp_workflow_state # Все
    \Bitrix\Bizproc\WorkflowStateTable

    MODULE_ID              = lists
    ENTITY                 = BizprocDocument
    WORKFLOW_TEMPLATE_ID   = 29
    STATE                  = Completed
    MODIFIED               Дата
    STARTED_BY             User

    DOCUMENT_ID            ID элемента инфоблока

\Bitrix\Main\Loader::includeModule('lists');
\Bitrix\Main\Loader::includeModule('bizproc');

$documentType   = BizProcDocument::generateDocumentComplexType($iblockTypeId, $iblockId);
$documentId     = BizProcDocument::getDocumentComplexId($iblockTypeId, $ar['ID']);
$documentStates = CBPDocument::GetDocumentStates($documentType, $documentId);

// ########################################################################

/^((?!Pull).)*$/

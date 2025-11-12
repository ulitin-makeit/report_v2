<?php

    namespace Brs\Report\Model\Orm;

    use Bitrix\Main\ORM\Data\DataManager;
    use Bitrix\Main\ORM\Fields;

    class UniversalTable extends DataManager {

        // поля отчёта (соответствие коду)
        public static array $codeHeaderFields = array(

            'NOMER_SDELKI' => 'Номер сделки',
            'TITLE_DEAL' => 'Название сделки',
            'UCHASTIYA_AGENTA_V_PRODAZHE' => '% участия агента в продаже*',
            'DATA_SOZDANIYA_SDELKI' => 'Дата создания сделки',
            'STATUS_SDELKI' => 'Статус сделки',
            'OTVETSTVENNOE_LITSO' => 'Ответственное лицо',
            'TIP_KLIENTA' => 'Тип клиента',
            'KLIENT' => 'Клиент',
            'MANAGER' => 'Менеджер',
            'ID_KLIENTA' => 'ID клиента',
            'DATA_SOZDANIYA_FIN_KARTY' => 'Дата создания фин.карты',
            'SOZDATEL_KARTY' => 'Создатель карты',
            'DATA_OPLATY_KLIENTOM' => 'Дата оплаты Клиентом',
            'TIP_KARTY' => 'Тип карты',
            'DATA_OTMENY_OPERATSII_VOZVRAT' => 'Дата отмены операции (возврат)',
            'DATA_VOZVRATA' => 'Дата возврата',
            'PARTNER' => 'Партнер',
            'KOLICHESTVO_BRONEY_1' => 'Количество броней (1)',
            'TIP_BRONI' => 'Тип брони',
            'STRANA' => 'Страна',
            'GOROD' => 'Город',
            'GOSTINITSA' => 'Гостиница',
            'RESTAURANT' => 'Ресторан',
            'FULL_NAME_ORGANIZATION' => 'Полное наименование организации',
            'TSEPOCHKA' => 'Цепочка',
            'DATA_ZAEZDA' => 'Дата заезда',
            'DATA_VYEZDA' => 'Дата выезда',
            'KOLICHESTVO_NOCHEY' => 'Количество ночей',
            'FULL_NUMBER_OF_NIGHTS' => 'Общее количество ночей',
            'KATEGORIYA' => 'Категория',
            'KANAL_SVYAZI' => 'Канал связи',
            'MARKETINGOVIY_KANAL' => 'Маркетинговый канал',
            'TOTAL_PAID_CLIENT' => 'Итого оплачено клиентом',
            'SUMMA_PRODAZHI_VSEGO_K_OPLATE_KLIENTOM' => 'Сумма продажи',
            'PAYMENT_SUPPLIER' => 'Оплата поставщику',
            'STATUS_CARD_REFUND' => 'Статус карты возврата',
            'REFOUND_AMOUNT_SUPPLIER' => 'Сумма возврата поставщиком',
            'FINE_SUPPLIER' => 'Штраф от поставщика',
            'SUPPLIER_FEE_REFOUND' => 'Сбор поставщика на возврат',
            'PRODUCTS_FEE_REFOUND' => 'Продукты за сбор возврата',
            'FINE_CLIENT_RSTLS' => 'Штраф клиенту РС ТЛС',
            'RSTLS_FEE_REFOUND' => 'Возврат сбора РС ТЛС',
            'REMAINDER_COLLECTION_RSTLS' => 'Остаток сбора РС ТЛС',
            'PROFIT_RSTLS_REFOUND' => 'Прибыль РС ТЛС с учетом возврата',
            'WITHHELD_SUPPLIER' => 'Удержал поставщик',
            'REFOUND_AMOUNT_CLIENT' => 'Сумма возврата клиенту',
            'PRIBYL_SERVISNYY_SBOR_KOMISSIYA_DOPOLNITELNAYA_VYGODA' => 'Прибыль',
            'PRIBYL_BEZ_NDS_RAZMER_NDS' => 'Прибыль без НДС',
            'THE_AMOUNT_OF_PROFIT_INCLUDING_REFUND_WITHOUT_VAT' => 'Сумма прибыли с учетом возврата без НДС',
            'KOMISSIYA' => 'Комиссия',
            'KOMISSIYA_BEZ_NDS_RAZMER_NDS' => 'Комиссия без НДС',
            'DOPOLNITELNAYA_VYGODA' => 'Дополнительная выгода',
            'DOPOLNITELNAYA_VYGODA_BEZ_NDS_RAZMER_NDS' => 'Дополнительная выгода без НДС',
            'SERVISNYY_SBOR' => 'Сервисный сбор',
            'SERVISNYY_SBOR_BEZ_NDS_RAZMER_NDS' => 'Сервисный сбор без НДС',
            'NET_SUPPLIER_CURRENCY' => 'Нетто в Валюте поставщика',
            'GROSS_SUPPLIER_CURRENCY' => 'Брутто в Валюте поставщика',
            'COMMISION_SUPPLIER_CURRENCY' => 'Комиссия поставщика в Валюте',
            'NET_RUBLES' => 'Нетто в рублях',
            'CURRENCY' => 'Валюта сделки',
            'CURRENCY_ORIGINAL' => 'Название валюты сделки',
            'RATE_PAYMENT' => 'Курс оплаты',
            'RATE_PAYMENT_CENTRAL_BANK' => 'Курс оплаты ЦБ',
            'TRAVELER' => 'Путешественник',
            'SUM_NDS' => 'Сумма НДС',
            'SUMMA_TID' => 'Сумма TID',
            'SR' => 'SR',
            'LR' => 'LR',
            'TYPE_REQUEST' => 'Тип запроса',
            'BIND_DEAL' => 'Связанные сделки',
            'LEAD_ID' => 'Лид',
            'TOUR' => 'Тур',
            'ACCOUNT_NUMBER' => 'Номер счёта',
            'TYPE_PAYMENT' => 'Тип оплаты',
            'DATE_SERVICE_PROVISION' => 'Дата оказания услуги',
            'SALE_AMOUNT_AFTER_REFOUND' => 'Сумма продажи после возврата',
            'DEPOZIT' => 'Депозит',
            'BALLY_AX' => 'Баллы AX',
            'BALLY_MR' => 'Баллы MR',
            'BALLY_IMP' => 'Баллы IMP',
            'BEZNAL' => 'безнал',
            'KARTA' => 'Карта',
            'SERTIFIKAT' => 'Сертификат',
            'UBYTOK_NA_KOMPANIYU' => 'Убыток на компанию',
            'UBYTOK_NA_SOTRUDNIKA' => 'Убыток на сотрудника',
            'KOD_FHR' => 'Код FHR',
            'KLASS' => 'Класс',
            'PASSAZHIR' => 'Пассажир',
            'DATA_VYLETA' => 'Дата вылета',
            'DATA_PRILETA' => 'Дата прилета',
            'AVIAKOMPANIYA' => 'Авиакомпания',
            'STRANA_PRILETA_KONECHNAYA_TOCHKA' => 'Страна прилета (Конечная точка)',
            'GOROD_PRILETA_KONECHNAYA_TOCHKA' => 'Город прилета  (Конечная точка)',
            'PRIVILEGII' => 'Привилегии',
            'NALICHIE_DOGOVORA' => 'Наличие договора',
            'REZULTAT_SDELKI' => 'Результат сделки',
            'PRICHINA_STADII_SDELKA_PROIGRANA' => 'Причина стадии Сделка проиграна',
            'KOLICHESTVO_SEGMENTOV' => 'Количество сегментов',
            'DATA_OPLATY_PARTNERU_POSTAVSHCHIKU' => 'Дата оплаты партнеру (поставщику)',
            'COMMENT_TEAMLEADER' => 'Комментарий Тимлидеру',
            'IS_CROSS_SELLING' => 'Кросс-продажа',
            'CROSS_SELLING_REASON' => 'Кросс-продажа причина',
            'FINANCIAL_CARD_SCHEME_WORK' => 'Схема финансовой карты',
            'DEFERRED_DATE_ACTIVE_FINISH' => 'Дата отложенной оплаты',
            'DEFERRED_CURRENCY' => 'Валюта отложенной оплаты',
            'DEFERRED_AMOUNT' => 'Сумма отложенной оплаты, руб',
            'DEFERRED_AMOUNT_CURRENCY' => 'Сумма отложенной оплаты, валюта',
            'NUMBER_ROOMS' => 'Количество номеров',
            'DATE_START' => 'Дата начала',
            'DATE_FINISH' => 'Дата окончания',
            'DATE_CLOSE' => 'Дата завершения',
			'AVERAGE_RATE' => 'Средний курс для возврата',
			'SUPPLIER' => 'Сбор поставщика'
        );

        public static function getTableName(): string {
            return 'brs_report_universal';
        }

        public static function getMap(): array {

            return [

                new Fields\IntegerField('DEAL_ID', [ // номер сделки
                    'primary' => true,
                    'autocomplete' => true,
                    'column_name' => 'DEAL_ID'
                ]),

                // сгненерированные поля отчёта
                new Fields\StringField('NOMER_SDELKI'),
                new Fields\StringField('TITLE_DEAL'), // название сделки
                new Fields\StringField('UCHASTIYA_AGENTA_V_PRODAZHE'), // % участия агента в продаже*
                new Fields\DateField('DATA_SOZDANIYA_SDELKI'), // Дата создания сделки
                new Fields\StringField('STATUS_SDELKI'), // Статус сделки
                new Fields\StringField('OTVETSTVENNOE_LITSO'), // Ответственное лицо
                new Fields\StringField('TIP_KLIENTA'), // Тип клиента
                new Fields\StringField('KLIENT'), // Клиент
                new Fields\StringField('MANAGER'), // Менеджер
                new Fields\StringField('ID_KLIENTA'), // ID клиента
                new Fields\DateField('DATA_SOZDANIYA_FIN_KARTY'), // Дата создания фин.карты
                new Fields\StringField('SOZDATEL_KARTY'), // Создатель карты
                new Fields\DateField('DATA_OPLATY_KLIENTOM'), // Дата оплаты Клиентом
                new Fields\StringField('TIP_KARTY'), // Тип карты
                new Fields\StringField('DATA_OTMENY_OPERATSII_VOZVRAT'), // Дата отмены операции (возврат)
                new Fields\DateField('DATA_VOZVRATA'), // Дата возврата
                new Fields\StringField('PARTNER'), // Партнер
                new Fields\StringField('KOLICHESTVO_BRONEY_1'), // Количество броней (1)
                new Fields\StringField('TIP_BRONI'), // Тип брони
                new Fields\StringField('STRANA'), // Страна
                new Fields\StringField('GOROD'), // Город
                new Fields\StringField('GOSTINITSA'), // Гостиница
                new Fields\StringField('RESTAURANT'), // Ресторан
                new Fields\StringField('FULL_NAME_ORGANIZATION'), // Полное наименование организации
                new Fields\StringField('TSEPOCHKA'), // Цепочка
                new Fields\DateField('DATA_ZAEZDA'), // Дата заезда
                new Fields\DateField('DATA_VYEZDA'), // Дата выезда
                new Fields\StringField('KOLICHESTVO_NOCHEY'), // Количество ночей
                new Fields\StringField('FULL_NUMBER_OF_NIGHTS'), // Общее количество ночей
                new Fields\StringField('KATEGORIYA'), // Категория
                new Fields\StringField('KANAL_SVYAZI'), // Канал связи
                new Fields\StringField('MARKETINGOVIY_KANAL'), // маркетинговый канал
                new Fields\StringField('TOTAL_PAID_CLIENT'), // итого оплачено клиентом
                new Fields\StringField('SUMMA_PRODAZHI_VSEGO_K_OPLATE_KLIENTOM'), // Сумма продажи
                new Fields\StringField('PAYMENT_SUPPLIER'), // Оплата поставщику
                new Fields\StringField('STATUS_CARD_REFUND'), // Статус карты возврата
                new Fields\StringField('REFOUND_AMOUNT_SUPPLIER'), // Сумма возврата поставщиком
                new Fields\StringField('FINE_SUPPLIER'), // Штраф от поставщика
                new Fields\StringField('SUPPLIER_FEE_REFOUND'), // Сбор поставщика на возврат
                new Fields\StringField('PRODUCTS_FEE_REFOUND'), // Продукты за сбор возврата
                new Fields\StringField('FINE_CLIENT_RSTLS'), // Штраф клиенту РС ТЛС
                new Fields\StringField('RSTLS_FEE_REFOUND'), // Возврат сбора РС ТЛС
                new Fields\StringField('REMAINDER_COLLECTION_RSTLS'), // Остаток сбора РС ТЛС
                new Fields\StringField('PROFIT_RSTLS_REFOUND'), // Прибыль РС ТЛС с учетом возврата'
                new Fields\StringField('WITHHELD_SUPPLIER'), // Удержал поставщик
                new Fields\StringField('REFOUND_AMOUNT_CLIENT'), // Сумма возврата клиенту
                new Fields\StringField('PRIBYL_SERVISNYY_SBOR_KOMISSIYA_DOPOLNITELNAYA_VYGODA'), // Прибыль
                new Fields\StringField('PRIBYL_BEZ_NDS_RAZMER_NDS'), // Прибыль без НДС
                new Fields\StringField('THE_AMOUNT_OF_PROFIT_INCLUDING_REFUND_WITHOUT_VAT'), // Сумма прибыли с учетом возврата без НДС
                new Fields\StringField('KOMISSIYA'), // Комиссия
                new Fields\StringField('KOMISSIYA_BEZ_NDS_RAZMER_NDS'), // Комиссия без НДС
                new Fields\StringField('DOPOLNITELNAYA_VYGODA'), // Дополнительная выгода
                new Fields\StringField('DOPOLNITELNAYA_VYGODA_BEZ_NDS_RAZMER_NDS'), // Дополнительная выгода без НДС
                new Fields\StringField('SERVISNYY_SBOR'), // Сервисный сбор
                new Fields\StringField('SERVISNYY_SBOR_BEZ_NDS_RAZMER_NDS'), // Сервисный сбор без НДС
                new Fields\StringField('NET_SUPPLIER_CURRENCY'), // Нетто в Валюте поставщика
                new Fields\StringField('GROSS_SUPPLIER_CURRENCY'), // Брутто в Валюте поставщика
                new Fields\StringField('COMMISION_SUPPLIER_CURRENCY'), // Комиссия поставщика в Валюте
                new Fields\StringField('NET_RUBLES'), // Нетто в рублях
                new Fields\StringField('RATE_PAYMENT'), // Курс оплаты
                new Fields\StringField('RATE_PAYMENT_CENTRAL_BANK'), // Курс оплаты ЦБ
                new Fields\StringField('CURRENCY_ORIGINAL'), // Название валюты сделки
                new Fields\StringField('CURRENCY'), // Отчёт по продажам
                new Fields\StringField('TRAVELER'), // Путешественник
                new Fields\StringField('SUM_NDS'), // Сумма НДС
                new Fields\StringField('SUMMA_TID'), // Сумма TID
                new Fields\StringField('SR'), // SR
                new Fields\StringField('LR'), // LR
                new Fields\StringField('TYPE_REQUEST'), // Тип запроса
                new Fields\StringField('BIND_DEAL'), // Связанные сделки
                new Fields\StringField('LEAD_ID'), // Лид
                new Fields\StringField('TOUR'), // Тур
                new Fields\StringField('ACCOUNT_NUMBER'), // Номер счёта
                new Fields\StringField('TYPE_PAYMENT'), // Тип оплаты
                new Fields\DateField('DATE_SERVICE_PROVISION'), // Дата оказания услуги
                new Fields\StringField('SALE_AMOUNT_AFTER_REFOUND'), // Сумма продажи после возврата
                new Fields\StringField('DEPOZIT'), // Депозит
                new Fields\StringField('BALLY_AX'), // Баллы AX
                new Fields\StringField('BALLY_MR'), // Баллы MR
                new Fields\StringField('BALLY_IMP'), // Баллы IMP
                new Fields\StringField('BEZNAL'), // безнал
                new Fields\StringField('KARTA'), // Карта
                new Fields\StringField('SERTIFIKAT'), // Сертификат
                new Fields\StringField('UBYTOK_NA_KOMPANIYU'), // Убыток на компанию
                new Fields\StringField('UBYTOK_NA_SOTRUDNIKA'), // Убыток на сотрудника
                new Fields\StringField('KOD_FHR'), // Код FHR
                new Fields\StringField('KLASS'), // Класс
                new Fields\StringField('PASSAZHIR'), // Пассажир
                new Fields\ArrayField('DATA_VYLETA'), // Дата вылета
                new Fields\DateTimeField('DATA_PRILETA'), // Дата прилета
                new Fields\StringField('AVIAKOMPANIYA'), // Авиакомпания
                new Fields\StringField('STRANA_PRILETA_KONECHNAYA_TOCHKA'), // Страна прилета (Конечная точка)
                new Fields\StringField('GOROD_PRILETA_KONECHNAYA_TOCHKA'), // Город прилета  (Конечная точка)
                new Fields\StringField('PRIVILEGII'), // Привилегии
                new Fields\StringField('NALICHIE_DOGOVORA'), // Наличие договора
                new Fields\StringField('REZULTAT_SDELKI'), // Результат сделки
                new Fields\StringField('PRICHINA_STADII_SDELKA_PROIGRANA'), // Причина стадии Сделка проиграна
                new Fields\StringField('KOLICHESTVO_SEGMENTOV'), // Количество сегментов
                new Fields\StringField('FINANCIAL_CARD_SCHEME_WORK'), // Схема фин карты
                new Fields\DateField('DATA_OPLATY_PARTNERU_POSTAVSHCHIKU'), // Дата оплаты партнеру (поставщику)
                new Fields\StringField('COMMENT_TEAMLEADER'), // Комментарий Тимлидеру
                new Fields\StringField('IS_CROSS_SELLING'), // Кросс-продажа
                new Fields\StringField('CROSS_SELLING_REASON'), // Кросс-продажа (причина)
                new Fields\DateField('DEFERRED_DATE_ACTIVE_FINISH'), // Дата отложенной оплаты
                new Fields\StringField('DEFERRED_CURRENCY'), // Валюта отложенной оплаты
                new Fields\StringField('DEFERRED_AMOUNT'), // Сумма отложенной оплаты, руб
                new Fields\StringField('DEFERRED_AMOUNT_CURRENCY'), // Сумма отложенной оплаты, валюта
                new Fields\StringField('NUMBER_ROOMS'), // количество номеров
                new Fields\DateField('DATE_START'), // Дата начала
                new Fields\DateField('DATE_FINISH'), // Дата окончания
                new Fields\DateField('DATE_CLOSE'), // Дата завершения
				new Fields\StringField('AVERAGE_RATE'), // Средний курс для возврата
				new Fields\StringField('SUPPLIER'), // Сбор поставщика

            ];
        }

    }
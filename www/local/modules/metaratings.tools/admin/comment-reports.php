<?php
/**
 * @var string $REQUEST_METHOD
 * @var null|string $save
 * @var null|string $tabs_active_tab
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

IncludeModuleLangFile(__FILE__);
global $APPLICATION;

$APPLICATION->SetTitle('Отчеты по комментариям');

\CJSCore::Init(['jquery']);

\Bitrix\Main\Loader::includeModule('iblock');

$arData = \Metaratings\Helpers\Cache::remember('tools-comment', MINUTE_IN_SECONDS, static function () {
    global $DB;

    $iIBlockId = \Metaratings\IBlock::getId('COMMENTS');

    $arData = [
        'COUNTS' => [
            'COMMENTS' => 0,
            'REPLIES' => 0,
            'COMMENT_NOT_MODERATION' => 0,
            'REPLIES_NOT_MODERATION' => 0,
        ],

        /** количество комментариев за последний месяц по дням */
        'COMMENTS_BY_DATE' => [
            'COMMENTS' => getCommentsByDays(false),
            'REPLIES' => getCommentsByDays(true),
            'COMMENTS_NOT_MODERATION' => getCommentsByDays(false, 'ACTIVE = \'N\''),
            'REPLIES_NOT_MODERATION' => getCommentsByDays(true, 'ACTIVE = \'N\''),

            'TROLLS_TOTAL' => [],
            'TROLLS_COMMENTS' => [],
            'TROLLS_REPLIES' => [],
        ],
        'USERS_GROUPS' => [],
        'TOP_ELEMENTS' => [],
    ];

    $rsCommentsUsers = CIBlockElement::GetList(
        [],
        [
            'IBLOCK_ID' => $iIBlockId,
            '>=DATE_CREATE' => \Bitrix\Main\Type\DateTime::createFromTimestamp(time() - DAY_IN_SECONDS),

        ],
        'PROPERTY_USER',
        [],
        ['PROPERTY_USER']
    );

    $arUsersId = [];
    while ($arUser = $rsCommentsUsers->Fetch()) {
        $arUsersId[] = $arUser['PROPERTY_USER_VALUE'];
    }

    $arUsersGroupId = [];
    foreach ($arUsersId as $arUserId) {
        $arUserGroups = CUser::GetUserGroup($arUserId);
        $arUsersGroupId = array_merge($arUsersGroupId, $arUserGroups);
    }

    $arUsersGroupId = array_unique($arUsersGroupId);

    $rsGroups = \Bitrix\Main\GroupTable::getList([
        'filter' => [
            'ID' => $arUsersGroupId,
        ],
        'select' => ['NAME'],
    ]);


    while ($arGroup = $rsGroups->Fetch()) {
        /** Группы пользователей оставивших коментарии */
        if (!in_array($arGroup['ID'], [2, 3, 4]))
            $arData['USERS_GROUPS'][] = $arGroup['NAME'];
    }


    /** Получаем id всех троллей */
    $arTrollsUsers = \Bitrix\Main\UserTable::getList([
        'filter' => [
            'GROUPS.GROUP.STRING_ID' => 'trolls',
        ],
        'select' => ['ID'],
    ])->fetchAll();
    $iIdPropUser = \Metaratings\IBlock::getPropertyIdByCode($iIBlockId, 'USER');
    $trollsFilter = 'PROP_TABLE.PROPERTY_' . $iIdPropUser . ' IN (' . implode(', ', array_column($arTrollsUsers, 'ID')) . ')';

    /** количество комментариев троллей за последний месяц по дням */
    $arData['COMMENTS_BY_DATE']['TROLLS_TOTAL'] = getCommentsByDays(null, $trollsFilter);
    $arData['COMMENTS_BY_DATE']['TROLLS_COMMENTS'] = getCommentsByDays(false, $trollsFilter);
    $arData['COMMENTS_BY_DATE']['TROLLS_REPLIES'] = getCommentsByDays(true, $trollsFilter);


    /** топ элементов с наибольшим числом комментарие за последние 7 дней */
    $rsTopElements = $DB->Query('
        SELECT
            b_iblock_element_prop_s' . $iIBlockId . '.PROPERTY_' . \Metaratings\IBlock::getPropertyIdByCode($iIBlockId, 'ELEMENT') . ' AS ELEMENT_ID,
            COUNT(*) AS COUNT
        FROM b_iblock_element
        INNER JOIN b_iblock_element_prop_s' . $iIBlockId . ' 
            ON b_iblock_element.ID = b_iblock_element_prop_s' . $iIBlockId . '.IBLOCK_ELEMENT_ID
        WHERE b_iblock_element.IBLOCK_ID = ' . $iIBlockId . ' AND b_iblock_element.DATE_CREATE > now() - 608000
        GROUP BY ELEMENT_ID
        ORDER BY COUNT DESC
        LIMIT 5
    ');

    $arTopElements = [];
    while ($arTopElement = $rsTopElements->Fetch()) {
        $arTopElements[$arTopElement['ELEMENT_ID']] = $arTopElement['COUNT'];
    }

    $rsItems = \CIBlockElement::GetList(
        [],
        [
            'ACTIVE' => 'Y',
            'ID' => array_keys($arTopElements),
        ],
        false,
        false,
        [
            'ID',
            'IBLOCK_ID',
            'NAME',
            'CODE',
            'IBLOCK_SECTION_ID',
            'DETAIL_PAGE_URL',
            'PROPERTY_REGION',
        ]
    );

    while ($arItem = $rsItems->GetNext()) {
        $arItem['COUNT'] = $arTopElements[$arItem['ID']];

        $arData['TOP_ELEMENTS'][] = $arItem;
    }

    return $arData;
});


function getCommentsByDays(?bool $hasParent = null, $filterRaw = '', $interval = 30)
{
    global $DB;

    $iIBlockId = \Metaratings\IBlock::getId('COMMENTS');

    $query = ['SELECT COUNT(*) AS COUNT, DATE_FORMAT(DATE_CREATE, "%d.%m.%Y") AS DATE'];

    $query[] = 'FROM b_iblock_element';

    if (is_bool($hasParent) || $filterRaw) {
        $query[] = 'INNER JOIN b_iblock_element_prop_s' . $iIBlockId . ' as PROP_TABLE
            ON b_iblock_element.ID = PROP_TABLE.IBLOCK_ELEMENT_ID';
    }

    $query[] = 'WHERE b_iblock_element.IBLOCK_ID = ' . $iIBlockId . ' AND DATE_CREATE > CURDATE() - INTERVAL ' . $interval . ' DAY';

    if ($filterRaw) {
        $query[] = 'AND ' . $filterRaw;
    }

    if (is_bool($hasParent)) {
        $iPropParentId = \Metaratings\IBlock::getPropertyIdByCode($iIBlockId, 'PARENT');

        $query[] = 'AND PROP_TABLE.PROPERTY_' . $iPropParentId . ' IS ' . ($hasParent ? 'NOT ' : '') . 'null';
    }

    $query[] = 'GROUP BY DATE_FORMAT(DATE_CREATE, "%d.%m.%Y")';

    $query[] = 'ORDER BY DATE_CREATE ASC';

    $rsComments = $DB->Query(implode(' ', $query));

    $arComments = [];
    while ($arComment = $rsComments->Fetch()) {
        $arComments[$arComment['DATE']] = $arComment['COUNT'];
    }

    return $arComments;
}

$arAllDates = range(time() - MONTH_IN_SECONDS, time(), DAY_IN_SECONDS);

$arAllDates = array_map(function ($time) {
    return date('d.m.Y', $time);
}, $arAllDates);


require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';

$arTabs = [
    [
        'DIV' => 'comment-reports',
        'TAB' => 'Отчеты по комментариям',
        'TITLE' => 'Отчеты по комментариям',
    ],
];
$obTabs = new CAdminTabControl('tabs', $arTabs);
?>
    <form method="POST" action="<?= $APPLICATION->GetCurPageParam() ?>" name="bform" enctype="multipart/form-data">
        <?= bitrix_sessid_post() ?>
        <input type="hidden" name="lang" value="<?= LANGUAGE_ID; ?>">
        <?php
        $obTabs->Begin();
        $obTabs->BeginNextTab();
        ?>
        <div id="chart_1" style="width: 100%; height: 500px;"></div>
        <div id="chart_2" style="width: 100%; height: 500px;"></div>

        <p>Группы пользователей оставивших коментарии за последние
            сутки: <?= implode(', ', $arData['USERS_GROUPS']); ?></p>
        <h2>Топ 5 самых комментируемых элементов за последние 7 дней</h2>

        <ul style="list-style-type:decimal;">
            <?php foreach ($arData['TOP_ELEMENTS'] as $arItem) { ?>
                <?php
                $sEditLink = '/bitrix/admin/iblock_element_edit.php?IBLOCK_ID=' . $arItem['IBLOCK_ID'] . '&type=' . $arItem['IBLOCK_TYPE_ID'] . '&ID=' . $arItem['ID'];
                ?>

                <li>
                    <p>
                        <a href="<?= $arItem['DETAIL_PAGE_URL']; ?>"
                           target="_blank">
                            <?= $arItem['NAME']; ?>
                        </a>

                        <span title="Количество комментариев">(<?= $arItem['COUNT']; ?>)</span>

                        <sup>
                            <a href="<?= $sEditLink; ?>" target="_blank">Ред.</a>
                        </sup>
                    </p>
                </li>
            <?php } ?>
        </ul>

        <?php $obTabs->End(); ?>
    </form>
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
    <script type="text/javascript">
        google.charts.load('current', {'packages': ['corechart', 'bar']});
        google.charts.setOnLoadCallback(drawChart);

        function drawChart() {
            let data = google.visualization.arrayToDataTable([
                ['Дни', 'Комментарии', 'Ответы', 'Комментарии не прошедшие модерацию', 'Ответы не прошедшие модерацию'],
                <?php foreach ($arAllDates as $sDate) { ?>
                [
                    '<?=$sDate;?>',
                    <?=(int)$arData['COMMENTS_BY_DATE']['COMMENTS'][$sDate];?>,
                    <?=(int)$arData['COMMENTS_BY_DATE']['REPLIES'][$sDate];?>,
                    <?=(int)$arData['COMMENTS_BY_DATE']['COMMENTS_NOT_MODERATION'][$sDate];?>,
                    <?=(int)$arData['COMMENTS_BY_DATE']['REPLIES_NOT_MODERATION'][$sDate];?>,
                ],
                <?php } ?>
            ]);

            let options = {
                title: 'Количество комментариев за последний месяц',
                hAxis: {title: 'Дата', titleTextStyle: {color: '#333'}},
                vAxis: {title: 'Количество', minValue: 0},
                crosshair: {
                    trigger: 'both',
                    orientation: 'vertical',
                },
                focusTarget: 'category',
            };

            let chart = new google.visualization.AreaChart(document.getElementById('chart_1'));
            chart.draw(data, options);
        }

        google.charts.setOnLoadCallback(drawChart2);

        function drawChart2() {
            let data = google.visualization.arrayToDataTable([
                ['Дни', 'Всего', 'Комментарии', 'Ответы'],
                <?php foreach ($arAllDates as $sDate) { ?>
                [
                    '<?=$sDate;?>',
                    <?=(int)$arData['COMMENTS_BY_DATE']['TROLLS_TOTAL'][$sDate];?>,
                    <?=(int)$arData['COMMENTS_BY_DATE']['TROLLS_COMMENTS'][$sDate];?>,
                    <?=(int)$arData['COMMENTS_BY_DATE']['TROLLS_REPLIES'][$sDate];?>,
                ],
                <?php } ?>
            ]);

            let options = {
                title: 'Количество комментариев троллей за последний месяц',
                hAxis: {title: 'Дата', titleTextStyle: {color: '#333'}},
                vAxis: {title: 'Количество', minValue: 0},
                crosshair: {
                    trigger: 'both',
                    orientation: 'vertical',
                },
                focusTarget: 'category',
            };

            let chart = new google.visualization.AreaChart(document.getElementById('chart_2'));
            chart.draw(data, options);
        }
    </script>
<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';
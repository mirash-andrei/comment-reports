<?php
IncludeModuleLangFile(__FILE__);

$arMenu = [];

/** @var CMain $APPLICATION */
if ($APPLICATION->GetGroupRight('metaratings.tools') != 'D') {
    $arMenu = [
        [
            'parent_menu' => 'global_menu_services',
            'section' => 'metaratings_tools',
            'sort' => 200,
            'text' => 'Отчеты по комментариям',
            'title' => 'Отчеты по комментариям',
            'icon' => '',
            'page_icon' => '',
            'items_id' => 'mr_metaratings_tools',
            'url' => 'mr-tools-comment-reports.php?lang=' . LANGUAGE_ID,
            'more_url' => [
                'mr-tools-comment-reports.php',
            ],
        ],
    ];

}

if (!empty($arMenu))
    return $arMenu;

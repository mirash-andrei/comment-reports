<?php

namespace Sprint\Migration;


class Version20210625170422 extends Version
{
    protected $description = "Создание группы \"Тролли\" и добавление в группу пользователей";

    public function up()
    {
        $helper = $this->getHelperManager();
        $iIdGroupTrolls = $helper->UserGroup()->addGroupIfNotExists('trolls', [
            'NAME' => 'Тролли',
        ]);

        $arTrolls = 'zol49950@gmail.com | dzubaidina@gmail.com | akrymskaa6@gmail.com | metacommentarii@gmail.com | vantuzdominator@gmail.com | o.kovalskiy@brl.ru | fedunprodayspartak@gmail.com | anovik12@gmail.com | feluxzava123@yandex.ru | sexynovik@yandex.ru | p.mich893@gmail.com | ameheev0@gmail.com | spanteleevv0@gmail.com';

        $rsUsers = \CUser::GetList($by = "", $order = "", ['EMAIL' => $arTrolls]);

        while ($arUser = $rsUsers->Fetch()) {
            $arGroups = \CUser::GetUserGroup($arUser['ID']);
            $arGroups[] = $iIdGroupTrolls;
            \CUser::SetUserGroup($arUser['ID'], $arGroups);
        }
    }

    public function down()
    {
        $helper = $this->getHelperManager();
        $helper->UserGroup()->deleteGroup('trolls');
    }
}
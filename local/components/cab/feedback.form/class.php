<?php

use \Bitrix\Main\Loader,
    \Bitrix\Main\Localization\Loc,
    \Bitrix\Main\Application,
    Bitrix\Main\SystemException,
    \Bitrix\Main\Web\Uri,
    \Bitrix\Iblock,
    \Bitrix\Main\Context,
    \Bitrix\Main\Page\Asset,

    Bitrix\Main\Mail\Event;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

class FeedbackFormHandler extends CBitrixComponent
{
    private $iCacheTime = 3600000;
    private $sCacheId = 'FeedBackForm';
    private $sCachePath = 'FeedBackForm/';
    private $errors = array();

    public $arResult = array();
    private $arResultPropertyListFull = array();
    private $_request;

    /**
     * Подготовка параметров компонента
     * @param $arParams
     * @return mixed
     */
    public function onPrepareComponentParams($arParams)
    {
        $arParams["EVENT_NAME"] = trim($arParams["EVENT_NAME"]);
        if (strlen($arParams["EVENT_NAME"]) <= 0)
            $arParams["EVENT_NAME"] = "INFOPORTAL_ADD_ELEMENT";

        $arParams["ID"] = intval($_REQUEST["CODE"]);
        $arParams["MAX_FILE_SIZE"] = intval($arParams["MAX_FILE_SIZE"]);
        $arParams["PREVIEW_TEXT_USE_HTML_EDITOR"] = $arParams["PREVIEW_TEXT_USE_HTML_EDITOR"] === "Y" && CModule::IncludeModule("fileman");
        $arParams["DETAIL_TEXT_USE_HTML_EDITOR"] = $arParams["DETAIL_TEXT_USE_HTML_EDITOR"] === "Y" && CModule::IncludeModule("fileman");
        $arParams["RESIZE_IMAGES"] = $arParams["RESIZE_IMAGES"] === "Y";

        if (!is_array($arParams["PROPERTY_CODES"])) {
            $arParams["PROPERTY_CODES"] = array();
        } else {
            foreach ($arParams["PROPERTY_CODES"] as $i => $k)
                if (strlen($k) <= 0)
                    unset($arParams["PROPERTY_CODES"][$i]);
        }

        $arParams["PROPERTY_CODES_REQUIRED"] = is_array($arParams["PROPERTY_CODES_REQUIRED"]) ? $arParams["PROPERTY_CODES_REQUIRED"] : array();
        foreach ($arParams["PROPERTY_CODES_REQUIRED"] as $key => $value)
            if (strlen(trim($value)) <= 0)
                unset($arParams["PROPERTY_CODES_REQUIRED"][$key]);

        $arParams["USER_MESSAGE_ADD"] = trim($arParams["USER_MESSAGE_ADD"]);
        if (strlen($arParams["USER_MESSAGE_ADD"]) <= 0)
            $arParams["USER_MESSAGE_ADD"] = Loc::getMessage("IBLOCK_USER_MESSAGE_ADD_DEFAULT");

        $arParams["USER_MESSAGE_EDIT"] = trim($arParams["USER_MESSAGE_EDIT"]);
        if (strlen($arParams["USER_MESSAGE_EDIT"]) <= 0)
            $arParams["USER_MESSAGE_EDIT"] = Loc::getMessage("IBLOCK_USER_MESSAGE_EDIT_DEFAULT");

        if ($arParams["STATUS_NEW"] != "N" && $arParams["STATUS_NEW"] != "NEW") $arParams["STATUS_NEW"] = "ANY";


        if (!is_array($arParams["STATUS"])) {
            if ($arParams["STATUS"] === "INACTIVE")
                $arParams["STATUS"] = array("INACTIVE");
            else
                $arParams["STATUS"] = array("ANY");
        }

        if (!is_array($arParams["GROUPS"]))
            $arParams["GROUPS"] = array();


        return $arParams;
    }

    /**
     * Точка входа в компонент
     * Должна содержать только последовательность вызовов вспомогательых ф-ий и минимум логики
     * всю логику стараемся разносить по классам и методам
     */
    public function executeComponent()
    {

        $this->_checkModules();

        $this->arResult['getRequest'] = Application::getInstance()->getContext()->getRequest();

        // что-то делаем и результаты работы помещаем в arResult, для передачи в шаблон
        $this->arResult = $this->getResult($this->arParams);

        $this->includeComponentTemplate();
    }

    /**
     * Логика работы
     * Формирование фильтра с учетом фильтрации в PROPERTIES,
     * 404
     *
     * @param $arParams
     * @return array
     */
    private function getResult($arParams)
    {
        $arResult = [];


        return $arResult;
    }




}

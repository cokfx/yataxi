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

        //$this->_checkModules();

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
        $arResult['SECTION_LIST'] = $this->getIblockSectionsList();
        // ib getFieldsList for form fields create
        $arResult["PROPERTY_LIST_FULL"] = $this->getPropertiesListFull();
        $arResult["PROPERTY_LIST"] = $this->getPropertiesListsForEdition()['PROPERTY_ID_LIST'];
        $arResult["PROPERTY_REQUIRED"] = is_array($this->arParams["PROPERTY_CODES_REQUIRED"]) ? $this->arParams["PROPERTY_CODES_REQUIRED"] : array();


        // ib getPropertiesList for form Properties create

        // ib element add
        // if ib element exists
        //ib getFieldsListValue
        // ib getPropertiesListValue
        // ib element update

        return $arResult;
    }

    /**
     * Обертка над глобальной переменной
     * @return CAllUser|CUser
     */
    private function _user()
    {
        global $USER;
        return $USER;
    }

    private function allowAccess()
    {
        $arGroups = $this->_user()->GetUserGroupArray();
        // check whether current user can have access to add/edit elements
        if ($this->arParams["ID"] == 0) {
            $bAllowAccess = count(array_intersect($arGroups, $this->arParams["GROUPS"])) > 0 || $this->_user()->IsAdmin();
        } else {
            // rights for editing current element will be in element get filter
            $bAllowAccess = $this->_user()->GetID() > 0;
        }
        return $bAllowAccess;
    }
    private function getIblockSectionsList()
    {
        if ($this->allowAccess()) {
            $rsSection = \Bitrix\Iblock\SectionTable::getList(array(
                'order' => array('LEFT_MARGIN' => 'ASC'),
                'filter' => array(
                    'IBLOCK_ID' => $this->arParams['IBLOCK_ID'],
                    'ACTIVE' => 'Y',
                ),
                'select' => array(
                    'ID',
                    'NAME',
                    'DEPTH_LEVEL',
                ),
            ));
            $arrResult = array();
            while ($arSection = $rsSection->fetch()) {
                $arSection["NAME"] = str_repeat(" . ", $arSection["DEPTH_LEVEL"]) . $arSection["NAME"];
                $arSections[$arSection["ID"]] = array(
                    "VALUE" => $arSection["NAME"]
                );
            }
        }
        return $arSections;
    }

    private function getFieldsList(){
        $COL_COUNT = intval($this->arParams["DEFAULT_INPUT_SIZE"]);
        if ($COL_COUNT < 1)
            $COL_COUNT = 30;
        $arResult= array(
            "NAME" => array(
                "PROPERTY_TYPE" => "S",
                "MULTIPLE" => "N",
                "COL_COUNT" => $COL_COUNT,
            ),

            "TAGS" => array(
                "PROPERTY_TYPE" => "S",
                "MULTIPLE" => "N",
                "COL_COUNT" => $COL_COUNT,
            ),

            "DATE_ACTIVE_FROM" => array(
                "PROPERTY_TYPE" => "S",
                "MULTIPLE" => "N",
                "USER_TYPE" => "DateTime",
            ),

            "DATE_ACTIVE_TO" => array(
                "PROPERTY_TYPE" => "S",
                "MULTIPLE" => "N",
                "USER_TYPE" => "DateTime",
            ),

            /*"IBLOCK_SECTION" => array(
                "PROPERTY_TYPE" => "L",
                "ROW_COUNT" => "8",
                "MULTIPLE" => $this->arParams["MAX_LEVELS"] == 1 ? "N" : "Y",
                "ENUM" => $arResult["SECTION_LIST"],
            ),*/

            "PREVIEW_TEXT" => array(
                "PROPERTY_TYPE" => ($this->arParams["PREVIEW_TEXT_USE_HTML_EDITOR"] ? "HTML" : "T"),
                "MULTIPLE" => "N",
                "ROW_COUNT" => "5",
                "COL_COUNT" => $COL_COUNT,
            ),
            "PREVIEW_PICTURE" => array(
                "PROPERTY_TYPE" => "F",
                "FILE_TYPE" => "jpg, gif, bmp, png, jpeg",
                "MULTIPLE" => "N",
            ),
            "DETAIL_TEXT" => array(
                "PROPERTY_TYPE" => ($this->arParams["DETAIL_TEXT_USE_HTML_EDITOR"] ? "HTML" : "T"),
                "MULTIPLE" => "N",
                "ROW_COUNT" => "5",
                "COL_COUNT" => $COL_COUNT,
            ),
            "DETAIL_PICTURE" => array(
                "PROPERTY_TYPE" => "F",
                "FILE_TYPE" => "jpg, gif, bmp, png, jpeg",
                "MULTIPLE" => "N",
            ),
        );

        return $arResult;
    }
    private function getFieldsForEdition()
    {
        foreach ($this->getFieldsList() as $key => $arField) {
            if (in_array($key, $this->arParams["PROPERTY_CODES"])) {
                $arResult[$key] = $arField;
            }
        }
        return $arResult;
    }

    private function getPropertiesListsForEdition()
    {
        try {
            $rsProperty = \Bitrix\Iblock\PropertyTable::getList(array(
                'filter' => array('IBLOCK_ID' => $this->arParams['IBLOCK_ID'], 'ACTIVE' => 'Y'),
            ));
            while ($arProperty = $rsProperty->fetch()) {

                if (in_array($arProperty["ID"], $this->arParams["PROPERTY_CODES"])) {
                    if ($arProperty['PROPERTY_TYPE'] == 'L') {
                        //$arProperty['ENUM'] = $this->getPropsListVal(39);
                        $rsEnum = \Bitrix\Iblock\PropertyEnumerationTable::getList(array(
                            'filter' => array('PROPERTY_ID' => $arProperty["ID"]),
                        ));
                        while ($arEnum = $rsEnum->fetch()) {
                            $arProperty['ENUM'][$arEnum['ID']] = $arEnum;
                        }
                    }
                    $arPropertiesEdit["PROPERTY_LIST_FULL"][$arProperty["ID"]] = $arProperty;
                    $arPropertiesEdit["PROPERTY_ID_LIST"][] = $arProperty["ID"];
                    $arPropertiesEdit["PROPERTY_CODE_LIST"][] = $arProperty["CODE"];
                }
            }
            // if (is_array($arProperty)){}else{throw new SystemException("Not array");}

        } catch (SystemException $e) {
            echo $e->getMessage();
        }
        return $arPropertiesEdit;
    }
    private function getPropertiesListFull(){
        try {
            if (is_array($this->getPropertiesListsForEdition()["PROPERTY_LIST_FULL"])) {
                $arResult = $this->getFieldsForEdition() + $this->getPropertiesListsForEdition()["PROPERTY_LIST_FULL"];
            }else{
                $arResult = $this->getFieldsForEdition()["PROPERTY_LIST_FULL"];
            }
        }catch (Exception $e){
            echo $e->getMessage();
        }

        return $arResult;
    }

}

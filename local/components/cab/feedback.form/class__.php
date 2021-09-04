<?php

use \Bitrix\Main\Loader,
    \Bitrix\Main\Localization\Loc,
    \Bitrix\Main\Application,
    \Bitrix\Iblock,
    \Bitrix\Main\Context,
    \Bitrix\Main\Page\Asset,
    \Bitrix\Main\Web\Uri,
    Bitrix\Main\Mail\Event;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

class ExampleCompSimple extends CBitrixComponent
{
    private $iCacheTime = 3600000;
    private $sCacheId = 'FeedBackForm';
    private $sCachePath = 'FeedBackForm/';
    private $errors = array();

    public $arResult = array();
    private $arResultPropertyListFull = array();
    private $_request;

    /**
     * Проверка наличия модулей требуемых для работы компонента
     * @return bool
     * @throws Exception
     */
    private function _checkModules()
    {
        if (!Loader::includeModule('iblock')
            || !Loader::includeModule('sale')
        ) {
            throw new \Exception('Не загружены модули необходимые для работы модуля');
        }

        return true;
    }

    /**
     * Обертка над глобальной переменной
     * @return CAllMain|CMain
     */
    private function _app()
    {
        global $APPLICATION;
        return $APPLICATION;
    }


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
        $arResult["PROPERTY_LIST"] = $this->preparePropertyListId();
        $arResult["PROPERTY_LIST_FULL"] = $this->preparePropertyListFull();

        $arResult["PROPERTY_REQUIRED"] = is_array($this->arParams["PROPERTY_CODES_REQUIRED"]) ? $this->arParams["PROPERTY_CODES_REQUIRED"] : array();

        $arResult['ERRORS'] = $this->errors;
        $arResult['SECTION_LIST'] = $this->getIblockSectionsList();

        $arResult['ELEMENT'] = $this->getElementFields();

        return $arResult;
    }

    private function preparePropertyListId()
    {
        $arResult = array_merge(
            $this->addFieldsEdit()["PROPERTY_ID_LIST"],
            $this->setPropertiesListEdit()["PROPERTY_ID_LIST"]
        );
        return $arResult;
    }

    private function preparePropertyListFull()
    {
        $arResult = $this->addFieldsEdit()["PROPERTY_LIST_FULL"] +
            $this->setPropertiesListEdit()["PROPERTY_LIST_FULL"];
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

    private function setPropertyListFull()
    {
        $COL_COUNT = intval($this->arParams["DEFAULT_INPUT_SIZE"]);
        if ($COL_COUNT < 1)
            $COL_COUNT = 30;
        // customize "virtual" properties
        $arResult["PROPERTY_LIST"] = array();

        $this->arResultPropertyListFull = array(
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

            "IBLOCK_SECTION" => array(
                "PROPERTY_TYPE" => "L",
                "ROW_COUNT" => "8",
                "MULTIPLE" => $this->arParams["MAX_LEVELS"] == 1 ? "N" : "Y",
                "ENUM" => $arResult["SECTION_LIST"],
            ),

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

        return $this->arResultPropertyListFull;
    }

    private function addFieldsEdit()
    {
        foreach ($this->setPropertyListFull() as $key => $arr) {
            if (in_array($key, $this->arParams["PROPERTY_CODES"])) {
                $arFieldsEdit["PROPERTY_ID_LIST"][] = $key;
                $arFieldsEdit["PROPERTY_LIST_FULL"][$key] = $arr;

            }
        }
        return $arFieldsEdit;
    }

    private function setPropertiesListEdit()
    {
        $rsProperty = \Bitrix\Iblock\PropertyTable::getList(array(
            'filter' => array('IBLOCK_ID' => $this->arParams['IBLOCK_ID'], 'ACTIVE' => 'Y'),
        ));
        while ($arProperty = $rsProperty->fetch()) {

            $key = $arProperty["ID"];
            if (in_array($arProperty["ID"], $this->arParams["PROPERTY_CODES"])) {
                $arPropertiesEdit["PROPERTY_LIST_FULL"][$arProperty["ID"]] = $arProperty;
                $arPropertiesEdit["PROPERTY_ID_LIST"][] = $arProperty["ID"];
            }
        }
        return $arPropertiesEdit;
    }

    private function prepairAddFieldsProperties($arParams){

        $SEF_URL = $_REQUEST["SEF_APPLICATION_CUR_PAGE_URL"];
        $arResult["SEF_URL"] = $SEF_URL;

        $arProperties = $_REQUEST["PROPERTY"];

        $arUpdateValues = array();
        $arUpdatePropertyValues = array();
        $arPropertyListFull=$this->setPropertyListFull();
        // process properties list
        foreach ($arParams["PROPERTY_CODES"]  as $i => $propertyID)
        {
            $arPropertyValue = $arProperties[$propertyID];
            // check if property is a real property, or element field
            if (intval($propertyID) > 0){
                // for non-file properties
                if ($arPropertyListFull[$propertyID]["PROPERTY_TYPE"] != "F")
                {
                    // for multiple properties
                    if ($arPropertyListFull[$propertyID]["MULTIPLE"] == "Y")
                    {
                        $arUpdatePropertyValues[$propertyID] = array();
                        if (!is_array($arPropertyValue))
                        {
                            $arUpdatePropertyValues[$propertyID][] = $arPropertyValue;
                        }else{
                            foreach ($arPropertyValue as $key => $value)
                            {
                                if (
                                    $arPropertyListFull[$propertyID]["PROPERTY_TYPE"] == "L" && intval($value) > 0
                                    ||
                                    $arPropertyListFull[$propertyID]["PROPERTY_TYPE"] != "L" && !empty($value)
                                )
                                {
                                    $arUpdatePropertyValues[$propertyID][] = $value;
                                }
                            }
                        }

                    }else{// for single properties

                        if ($arPropertyListFull[$propertyID]["PROPERTY_TYPE"] != "L")
                            $arUpdatePropertyValues[$propertyID] = $arPropertyValue[0];
                        else
                            $arUpdatePropertyValues[$propertyID] = $arPropertyValue;

                    }
                }
            }
        }
    }
    private function prepairElementData(){
        $this->getElementList([

        ]);
    }


    private function prepairDataForForm(){
        if ($this->arParams["ID"] > 0)
        {
            // $arElement is defined before in elements rights check
            $rsElementSections = CIBlockElement::GetElementGroups($arElement["ID"]);
            $arElement["IBLOCK_SECTION"] = array();
            while ($arSection = $rsElementSections->GetNext())
            {
                $arElement["IBLOCK_SECTION"][] = array("VALUE" => $arSection["ID"]);
            }

            $arResult["ELEMENT"] = array();
            foreach($arElement as $key => $value)
            {
                $arResult["ELEMENT"]["~".$key] = $value;
                if(!is_array($value) && !is_object($value))
                    $arResult["ELEMENT"][$key] = htmlspecialcharsbx($value);
                else
                    $arResult["ELEMENT"][$key] = $value;
            }

            //Restore HTML if needed
            if(
                $arParams["DETAIL_TEXT_USE_HTML_EDITOR"]
                && array_key_exists("DETAIL_TEXT", $arResult["ELEMENT"])
                && strtolower($arResult["ELEMENT"]["DETAIL_TEXT_TYPE"]) == "html"
            )
                $arResult["ELEMENT"]["DETAIL_TEXT"] = $arResult["ELEMENT"]["~DETAIL_TEXT"];

            if(
                $arParams["PREVIEW_TEXT_USE_HTML_EDITOR"]
                && array_key_exists("PREVIEW_TEXT", $arResult["ELEMENT"])
                && strtolower($arResult["ELEMENT"]["PREVIEW_TEXT_TYPE"]) == "html"
            )
                $arResult["ELEMENT"]["PREVIEW_TEXT"] = $arResult["ELEMENT"]["~PREVIEW_TEXT"];


            //$arResult["ELEMENT"] = $arElement;

            // load element properties
            $rsElementProperties = CIBlockElement::GetProperty($arParams["IBLOCK_ID"], $arElement["ID"], $by="sort", $order="asc");
            $arResult["ELEMENT_PROPERTIES"] = array();
            while ($arElementProperty = $rsElementProperties->Fetch())
            {
                if(!array_key_exists($arElementProperty["ID"], $arResult["ELEMENT_PROPERTIES"]))
                    $arResult["ELEMENT_PROPERTIES"][$arElementProperty["ID"]] = array();

                if(is_array($arElementProperty["VALUE"]))
                {
                    $htmlvalue = array();
                    foreach($arElementProperty["VALUE"] as $k => $v)
                    {
                        if(is_array($v))
                        {
                            $htmlvalue[$k] = array();
                            foreach($v as $k1 => $v1)
                                $htmlvalue[$k][$k1] = htmlspecialcharsbx($v1);
                        }
                        else
                        {
                            $htmlvalue[$k] = htmlspecialcharsbx($v);
                        }
                    }
                }
                else
                {
                    $htmlvalue = htmlspecialcharsbx($arElementProperty["VALUE"]);
                }

                $arResult["ELEMENT_PROPERTIES"][$arElementProperty["ID"]][] = array(
                    "ID" => htmlspecialcharsbx($arElementProperty["ID"]),
                    "VALUE" => $htmlvalue,
                    "~VALUE" => $arElementProperty["VALUE"],
                    "VALUE_ID" => htmlspecialcharsbx($arElementProperty["PROPERTY_VALUE_ID"]),
                    "VALUE_ENUM" => htmlspecialcharsbx($arElementProperty["VALUE_ENUM"]),
                );
            }

            // process element property files
            $arResult["ELEMENT_FILES"] = array();
            foreach ($arResult["PROPERTY_LIST"] as $propertyID)
            {
                $arProperty = $arResult["PROPERTY_LIST_FULL"][$propertyID];
                if ($arProperty["PROPERTY_TYPE"] == "F")
                {
                    $arValues = array();
                    if (intval($propertyID) > 0)
                    {
                        foreach ($arResult["ELEMENT_PROPERTIES"][$propertyID] as $arProperty)
                        {
                            $arValues[] = $arProperty["VALUE"];
                        }
                    }
                    else
                    {
                        $arValues[] = $arResult["ELEMENT"][$propertyID];
                    }

                    foreach ($arValues as $value)
                    {
                        if ($arFile = CFile::GetFileArray($value))
                        {
                            $arFile["IS_IMAGE"] = CFile::IsImage($arFile["FILE_NAME"], $arFile["CONTENT_TYPE"]);
                            $arResult["ELEMENT_FILES"][$value] = $arFile;
                        }
                    }
                }
            }

            $bShowForm = true;
        }
        else
        {
            $bShowForm = true;
        }
    }

    /**
     * Получение значения из POST
     *
     * @return mixed
     */
    private function getPost()
    {
        $obRequest = Application::getInstance()->getContext()->getRequest();
        $arValues = $obRequest->getPostList()->getValues();
        return $arValues;
    }


    /**
     * Получение выбранных значений из GET запроса
     *
     * @return array
     */
    private function getPropertiesGet()
    {
        $arPost = $this->getGet();
        $arPropertiesPost = [];
        if (!empty($arPost)) {
            foreach ($arPost as $sProperty => $sValue) {
                $arPropertiesPost[$sProperty] = $sValue;
            }
        }
        return $arPropertiesPost;
    }


    /**
     * Получение значения из GET
     *
     * @return mixed
     */
    private function getGet()
    {
        $obRequest = Application::getInstance()->getContext()->getRequest();
        $arValues = $obRequest->getQueryList()->getValues();
        return $arValues;
    }



    private function debug()
    {
        echo '<pre>';
        var_dump($_REQUEST);
        echo '</pre>';
        die();
    }


    private function addElement($data)
    {
        new CIBlockElement;
        CIBlockElement::add($data);
    }

    private function updateElement($PRODUCT_ID, $data)
    {
        new CIBlockElement;
        CIBlockElement::update($PRODUCT_ID, $data);
    }


    /**
     * Получение элементов
     *
     * @param $arSelect
     * @param $arFilter
     * @param $iLimit
     * @return array
     */
    private function getElementList($arOrder = [], $arSelect = ['*'], $arFilter, $iLimit, $arGroup = [])
    {
        $arElements = [];
        try {
            $dbItems = \Bitrix\Iblock\ElementTable::getList(array(
                'order' => $arOrder,
                'select' => $arSelect,
                'filter' => $arFilter,
                'limit' => $iLimit,
                'group' => $arGroup, // группировка по полю, order должен быть пустой
                'offset' => 0, // целое число, указывающее номер первого столбца в результате
                'count_total' => 1, // дает возможность получить кол-во элементов через метод getCount()
                'runtime' => array(), // массив полей сущности, создающихся динамически
                'data_doubling' => false, // разрешает получение нескольких одинаковых записей
                'cache' => array( // Кеш запроса, почему-то в офф. документации об этом умалчивают
                    'ttl' => 3600,
                    'cache_joins' => true
                ),
            ));

            if ($arResult = $dbItems->fetch()) {
                $arElements[] = $arResult;
            }
            //$dbItems->fetch(); // или $dbItems->fetchRaw() получение одной записи, можно перебрать в цикле while ($arItem = $dbItems->fetch())

            //$dbItems->fetchAll(); // получение всех записей

            // $dbItems->getCount(); // кол-во найденных записей без учета limit, доступно если при запросе было указано count_total = 1

            //$dbItems->getSelectedRowsCount(); // кол-во полученных записей с учетом limit
        } catch (\Error $e) {
        }

        return $arElements;
    }
}

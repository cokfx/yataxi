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

    private function prepairParams()
    {
        if (!empty($this->getRequestData())) {
            $arUpdateValues = array();
            $arUpdatePropertyValues = array();
            // process properties list
            $arProperties = $this->getRequestData();
            // $arProperties = $_REQUEST["PROPERTY"];
            foreach ($this->arParams["PROPERTY_CODES"] as $i => $propertyID) {
                $arPropertyValue = $arProperties[$propertyID];
                $arPropertyListFull = $this->preparePropertyListFull();

                $arPropertyValue = $arProperties[$propertyID];
                // check if property is a real property, or element field
                if (intval($propertyID) > 0) {
                    // for non-file properties
                    if ($arPropertyListFull[$propertyID]["PROPERTY_TYPE"] != "F") {
                        // for multiple properties
                        if ($arPropertyListFull[$propertyID]["MULTIPLE"] == "Y") {
                            $arUpdatePropertyValues[$propertyID] = array();

                            if (!is_array($arPropertyValue)) {
                                $arUpdatePropertyValues[$propertyID][] = $arPropertyValue;
                            } else {
                                foreach ($arPropertyValue as $key => $value) {
                                    if (
                                        $arPropertyListFull[$propertyID]["PROPERTY_TYPE"] == "L" && intval($value) > 0
                                        ||
                                        $arPropertyListFull[$propertyID]["PROPERTY_TYPE"] != "L" && !empty($value)
                                    ) {
                                        $arUpdatePropertyValues[$propertyID][] = $value;
                                    }
                                }
                            }
                        } // for single properties
                        else {
                            if ($arPropertyListFull[$propertyID]["PROPERTY_TYPE"] != "L")
                                $arUpdatePropertyValues[$propertyID] = $arPropertyValue[0];
                            else
                                $arUpdatePropertyValues[$propertyID] = $arPropertyValue;
                        }
                    } // for file properties
                    else {
                        $arUpdatePropertyValues[$propertyID] = array();
                        foreach ($arPropertyValue as $key => $value) {
                            $arFile = $_FILES["PROPERTY_FILE_" . $propertyID . "_" . $key];
                            $arFile["del"] = $_REQUEST["DELETE_FILE"][$propertyID][$key] == "Y" ? "Y" : "";
                            $arUpdatePropertyValues[$propertyID][$key] = $arFile;

                            if (($this->arParams["MAX_FILE_SIZE"] > 0) && ($arFile["size"] > $this->arParams["MAX_FILE_SIZE"]))
                                $arResult["ERRORS"][] = GetMessage("IBLOCK_ERROR_FILE_TOO_LARGE");
                        }

                        if (count($arUpdatePropertyValues[$propertyID]) == 0)
                            unset($arUpdatePropertyValues[$propertyID]);
                    }
                } else {
                    // for "virtual" properties
                    if ($propertyID == "IBLOCK_SECTION") {
                        if (!is_array($arProperties[$propertyID]))
                            $arProperties[$propertyID] = array($arProperties[$propertyID]);
                        $arUpdateValues[$propertyID] = $arProperties[$propertyID];

                        if ($this->arParams["LEVEL_LAST"] == "Y" && is_array($arUpdateValues[$propertyID])) {
                            foreach ($arUpdateValues[$propertyID] as $section_id) {
                                $rsChildren = CIBlockSection::GetList(
                                    array("SORT" => "ASC"),
                                    array(
                                        "IBLOCK_ID" => $this->arParams["IBLOCK_ID"],
                                        "SECTION_ID" => $section_id,
                                    ),
                                    false,
                                    array("ID")
                                );
                                if ($rsChildren->SelectedRowsCount() > 0) {
                                    $arResult["ERRORS"][] = GetMessage("IBLOCK_ADD_LEVEL_LAST_ERROR");
                                    break;
                                }
                            }
                        }

                        if ($this->arParams["MAX_LEVELS"] > 0 && count($arUpdateValues[$propertyID]) > $this->arParams["MAX_LEVELS"]) {
                            $arResult["ERRORS"][] = str_replace("#MAX_LEVELS#", $this->arParams["MAX_LEVELS"], GetMessage("IBLOCK_ADD_MAX_LEVELS_EXCEEDED"));
                        }
                    } else {
                        if ($arPropertyListFull[$propertyID]["PROPERTY_TYPE"] == "F") {
                            $arFile = $_FILES["PROPERTY_FILE_" . $propertyID . "_0"];
                            $arFile["del"] = $_REQUEST["DELETE_FILE"][$propertyID][0] == "Y" ? "Y" : "";
                            $arUpdateValues[$propertyID] = $arFile;
                            if ($this->arParams["MAX_FILE_SIZE"] > 0 && $arFile["size"] > $this->arParams["MAX_FILE_SIZE"])
                                $arResult["ERRORS"][] = GetMessage("IBLOCK_ERROR_FILE_TOO_LARGE");
                        } elseif ($arPropertyListFull[$propertyID]["PROPERTY_TYPE"] == "HTML") {
                            if ($propertyID == "DETAIL_TEXT")
                                $arUpdateValues["DETAIL_TEXT_TYPE"] = "html";
                            if ($propertyID == "PREVIEW_TEXT")
                                $arUpdateValues["PREVIEW_TEXT_TYPE"] = "html";
                            $arUpdateValues[$propertyID] = $arProperties[$propertyID][0];
                        } else {
                            if ($propertyID == "DETAIL_TEXT")
                                $arUpdateValues["DETAIL_TEXT_TYPE"] = "text";
                            if ($propertyID == "PREVIEW_TEXT")
                                $arUpdateValues["PREVIEW_TEXT_TYPE"] = "text";
                            $arUpdateValues[$propertyID] = $arProperties[$propertyID][0];
                        }
                    }
                }


            }
        }
        $arUpdateValues['IBLOCK_ID'] = $this->arParams['IBLOCK_ID'];
        $arResult['UPDATE_VALUES'] = $arUpdateValues;
        $arResult['UPDATE_PROPERTY_VALUES'] = $arUpdatePropertyValues;
        return $arResult;
    }

    private function prepairCurrentUrl($key, $val)
    {
        $request = Application::getInstance()->getContext()->getRequest();
        $uri = new Uri($request->getRequestUri());
        $uri->addParams(array($key => $val));
        $sRedirect = $uri->getUri();
        return $sRedirect;
    }

    private function addElement()
    {
        if ($this->arParams["ID"] <= 0) {
            if (!empty($this->getRequestData())) {

                $el = new CIBlockElement;
                $PROP = $this->prepairParams()['UPDATE_PROPERTY_VALUES'];

                $arLoadProductArray = $this->prepairParams()['UPDATE_VALUES'];

                $arLoadProductArray["PROPERTY_VALUES"] = $PROP;


                if ($PRODUCT_ID = $el->Add($arLoadProductArray)) {
                    $arResult['addedElementId'] = $PRODUCT_ID;
                    $sCurrentPage = $this->prepairCurrentUrl('CODE', $PRODUCT_ID);

                    header('Location: ' . $sCurrentPage);
                } else {
                    $arResult['addedElementError'] = $el->LAST_ERROR;
                }
            }
        }
        return $arResult;
    }

    private function editElement()
    {
        if (!empty($this->getRequestData())) {
            if ($this->arParams["ID"] > 0) {
                $el = new CIBlockElement;
                $PROP = $this->prepairParams()['UPDATE_PROPERTY_VALUES'];
                $arLoadProductArray = $this->prepairParams()['UPDATE_VALUES'];
                $arLoadProductArray["PROPERTY_VALUES"] = $PROP;
                if (!$el->Update($this->arParams["ID"], $arLoadProductArray)) {
                    $arResult['addedElementError'] = $el->LAST_ERROR;
                }
            }
        }
        return $arResult;
    }

    private function getRequestData()
    {
        $arProperties = [];
        $arProperties = $_REQUEST["PROPERTY"];

        return $arProperties;
    }

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

    private function getElems()
    {

        $iIblockId = $this->arParams['IBLOCK_ID'] ? (int)$this->arParams['IBLOCK_ID'] : 6;
        $iElementId = $this->arParams['ID'];

        $sIblockObj = \Bitrix\Iblock\Iblock::wakeUp($iIblockId)->getEntityDataClass();

        $dbProps = \Bitrix\Iblock\PropertyTable::getList(['filter' => ['IBLOCK_ID' => $iIblockId]]);
        while ($arProps = $dbProps->fetch()) {
            $arrPropsCode[] = $arProps['CODE'];
        }
        $arrPropsSelect = array('ID', 'IBLOCK_ID') + $arrPropsCode;

        $elements = $sIblockObj::getList([
            'filter' => array('IBLOCK_ID' => $iIblockId),
            'select' => $arrPropsSelect
        ])->fetchCollection();

        foreach ($elements as $element) {
            //$elems[]= $element->getIblockSection()->getCode();
            $elem[$element->getId()] = $element->getClientId()->getValue(); // MY_PROP
        }
        return $elem;

    }

    private function getElement()
    {

        $iIblockId = $this->arParams['IBLOCK_ID'] ? (int)$this->arParams['IBLOCK_ID'] : 6;
        $iElementId = $this->arParams['ID'];

        $dbProps = \Bitrix\Iblock\PropertyTable::getList(['filter' => ['IBLOCK_ID' => $iIblockId]]);
        while ($arProps = $dbProps->fetch()) {
            $arrPropsCode[] = $arProps['CODE'];
        }

        $sIblockObj = \Bitrix\Iblock\Iblock::wakeUp($iIblockId)->getEntityDataClass();
        $oRes = $sIblockObj::getList(array(
            'filter' => array('ID' => $iElementId),
            'select' => array('*')
        ));
        if ($arRes = $oRes->fetch()) {
            $arResult = $arRes;
        }

        return $arResult;
    }

    private function getElementProperties()
    {
        $iIblockId = $this->arParams['IBLOCK_ID'] ? (int)$this->arParams['IBLOCK_ID'] : 6;
        $iElementId = $this->arParams['ID'];

        $dbProps = \Bitrix\Iblock\PropertyTable::getList(['filter' => ['IBLOCK_ID' => $iIblockId]]);
        while ($arProps = $dbProps->fetch()) {
            //$arrPropsCode[] = $arProps['CODE'];
            $arrProps[] = $arProps;
        }

        $arrPropsCode = $this->getPropertiesListEdit()["PROPERTY_CODE_LIST"];
        if (!is_array($arrPropsCode)){$arrPropsCode=['*'];}
        $sIblockObj = \Bitrix\Iblock\Iblock::wakeUp($iIblockId)->getEntityDataClass();
        $rsElements = $sIblockObj::getList(array(
            'filter' => array('ID' => $iElementId),
            'select' => $arrPropsCode
        ));
        // TODO: VALUE_ENUM ID : 27 (string)
        //VALUE : e19d549e69f548c6b4aad5bae570b4ba (string)
        //~VALUE : e19d549e69f548c6b4aad5bae570b4ba (string)
        //VALUE_ID : 317:27 (string)
        //VALUE_ENUM : (string)
        /* getEnum prop type list */
        /* $rsEnum = \Bitrix\Iblock\PropertyEnumerationTable::getList(array(

   'filter' => array('PROPERTY_ID'=>10),

   ));

   while($arEnum=$rsEnum->fetch())

   {

   print_r($arEnum);

   }*/
        $arrElements = $rsElements->fetchAll();
        foreach ($arrElements[0] as $key => $arrElement) {
            //if ($arrElement['PROPERTY_TYPE'] == 'S') {
            foreach ($arrProps as $arItem) {
                if (strpos($key, $arItem['CODE'])) {

                    $arItem['VALUE'] = $arrElement;
                    $arElems[$arItem['ID']] = $arItem;
                }
            }
            // }
        }
        $arResult = [];
        foreach ($arElems as $value) {
            if ($value['PROPERTY_TYPE'] == 'S') {
                $arResult[$value['ID']][0] = $value;
            } elseif ($value['PROPERTY_TYPE'] == 'L') {
                $rsEnum = \Bitrix\Iblock\PropertyEnumerationTable::getList(array(
                    'filter' => array('PROPERTY_ID' => $value['ID'], 'ID' => $value['VALUE']),
                ));
                while ($arEnum = $rsEnum->fetch()) {
                    $value['VALUE_ENUM'] = $arEnum['VALUE'];
                    $arResult[$value['ID']][0] = $value;
                }

            }
        }
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

    private function preparePropertyListId()
    {
        $arResult = array_merge(
            $this->getFieldsEdit()["PROPERTY_ID_LIST"],
            $this->getPropertiesListEdit()["PROPERTY_ID_LIST"]
        );
        return $arResult;
    }

    private function preparePropertyListFull()
    {
        try {
            if (is_array($this->getPropertiesListEdit()["PROPERTY_LIST_FULL"])) {
                $arResult = $this->addFieldsEdit()["PROPERTY_LIST_FULL"] + $this->getPropertiesListEdit()["PROPERTY_LIST_FULL"];
            }else{
                $arResult = $this->addFieldsEdit()["PROPERTY_LIST_FULL"];
            }
        }catch (Exception $e){
            echo $e->getMessage();
        }
        return $arResult;
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

    private function getFieldsEdit()
    {
        foreach ($this->setPropertyListFull() as $key => $arr) {
            if (in_array($key, $this->arParams["PROPERTY_CODES"])) {
                $arFieldsEdit["PROPERTY_ID_LIST"][] = $key;
                $arFieldsEdit["PROPERTY_LIST_FULL"][$key] = $arr;

            }
        }
        return $arFieldsEdit;
    }

    private function getPropertiesListEdit()
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

    private function getPropsListVal($elemId = 39)
    {
        $rsEnum = \Bitrix\Iblock\PropertyEnumerationTable::getList(array(
            'filter' => array('PROPERTY_ID' => $elemId),
        ));
        while ($arEnum = $rsEnum->fetch()) {
            $arEnum[$arEnum['ID']] = $arEnum;
        }

        return $arEnum;
    }


}

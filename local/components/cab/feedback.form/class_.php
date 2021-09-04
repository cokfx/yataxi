<?php

use \Bitrix\Main\Loader,
    \Bitrix\Main\Localization\Loc,
    \Bitrix\Main\Application,
    \Bitrix\Iblock,
    \Bitrix\Main\Context,
    \Bitrix\Main\Page\Asset,
    \Bitrix\Main\Web\Uri,
    Bitrix\Main\Mail\Event;

Loc::loadMessages(__FILE__);

if (!Loader::includeModule('iblock')) {
    ShowError(Loc::getMessage('IBLOCK_MODULE_NOT_INSTALLED'));
    return;
}

if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

class FeedBackForm extends \CBitrixComponent
{

    private $iCacheTime = 3600000;
    private $sCacheId = 'FeedBackForm';
    private $sCachePath = 'FeedBackForm/';
    private $iIblockId = 6;
    public $arResult = array();
    private $arResultPropertyListFull = array();


    /**
     * Component constructor.
     * @param CBitrixComponent | null $component
     */
    public function __construct($component = null)
    {
        parent::__construct($component);
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

        if ($this->request->isPost()) {
            // some post actions
        }
        $this->arResult = $this->getResult();

        $this->includeComponentTemplate();
    }

    private function getResult()
    {
        $arrResult["PROPERTY_LIST"] = array_merge(
            $this->addFieldsEdit()["PROPERTY_ID_LIST"],
            $this->setPropertiesListEdit()["PROPERTY_ID_LIST"]
        );
        $arrResult["PROPERTY_LIST_FULL"] = $this->addFieldsEdit()["PROPERTY_LIST_FULL"] +
            $this->setPropertiesListEdit()["PROPERTY_LIST_FULL"];

        $arrResult['ERRORS'] = $this->getArrErrors();
        $arrResult['SECTION_LIST'] = $this->getIblockSectionsList();
        return $arrResult;
    }

    private function getArrErrors()
    {
        $arErrors = array();
        return $arErrors;
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
                    'IBLOCK_ID' => $this->iIblockId,
                    'ACTIVE' => 'Y',
                ),
                'select' => array(
                    'ID',
                    'NAME',
                    'DEPTH_LEVEL',
                ),
            ));
            $arrResult= array();
            while ($arSection = $rsSection->fetch()) {
                $arSection["NAME"] = str_repeat(" . ", $arSection["DEPTH_LEVEL"]) . $arSection["NAME"];
                $arrResult[$arSection["ID"]] = array(
                    "VALUE" => $arSection["NAME"]
                );
            }
        }
        return $arrResult;
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
            'filter' => array('IBLOCK_ID' => $this->iIblockId, 'ACTIVE' => 'Y'),
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


    private function processPostData()
    {
        if ($this->allowAccess()) {
            // process POST data
            $PostData=array();
            if (check_bitrix_sessid() && (!empty($_REQUEST["iblock_submit"]) || !empty($_REQUEST["iblock_apply"]))) {
                $SEF_URL = $_REQUEST["SEF_APPLICATION_CUR_PAGE_URL"];
                $PostData["SEF_URL"] = $SEF_URL;

                $arProperties = $_REQUEST["PROPERTY"];

                $arUpdateValues = array();
                $arUpdatePropertyValues = array();

                // process properties list
                foreach ($this->arParams["PROPERTY_CODES"] as $i => $propertyID) {
                    $arPropertyValue = $arProperties[$propertyID];
                    // check if property is a real property, or element field
                    if (intval($propertyID) > 0) {
                        // for non-file properties
                        if ($this->setPropertiesListEdit()["PROPERTY_LIST_FULL"][$propertyID]["PROPERTY_TYPE"] != "F") {
                            // for multiple properties
                            if ($this->setPropertiesListEdit()["PROPERTY_LIST_FULL"][$propertyID]["MULTIPLE"] == "Y") {
                                $arUpdatePropertyValues[$propertyID] = array();

                                if (!is_array($arPropertyValue)) {
                                    $arUpdatePropertyValues[$propertyID][] = $arPropertyValue;
                                } else {
                                    foreach ($arPropertyValue as $key => $value) {
                                        if (
                                            $arResult["PROPERTY_LIST_FULL"][$propertyID]["PROPERTY_TYPE"] == "L" && intval($value) > 0
                                            ||
                                            $arResult["PROPERTY_LIST_FULL"][$propertyID]["PROPERTY_TYPE"] != "L" && !empty($value)
                                        ) {
                                            $arUpdatePropertyValues[$propertyID][] = $value;
                                        }
                                    }
                                }
                            } // for single properties
                            else {
                                if ($this->setPropertiesListEdit()["PROPERTY_LIST_FULL"][$propertyID]["PROPERTY_TYPE"] != "L")
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
                                    $this->getArrErrors()[] = Loc::getMessage("IBLOCK_ERROR_FILE_TOO_LARGE");
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

                                    $rsChildren = \Bitrix\Iblock\SectionTable::getList(array(
                                        'order' => array("SORT" => "ASC"),
                                        'filter' => array(
                                            "IBLOCK_ID" => $this->arParams["IBLOCK_ID"],
                                            "SECTION_ID" => $section_id,
                                        ),
                                        'select' => array('ID' ),
                                    ))->fetchAll();
                                    if (count($rsChildren) > 0) {
                                        $this->getArrErrors()[] = Loc::getMessage("IBLOCK_ADD_LEVEL_LAST_ERROR");
                                        break;
                                    }
                                }
                            }

                            if ($this->arParams["MAX_LEVELS"] > 0 && count($arUpdateValues[$propertyID]) > $this->arParams["MAX_LEVELS"]) {
                                $arResult["ERRORS"][] = str_replace("#MAX_LEVELS#", $this->arParams["MAX_LEVELS"], GetMessage("IBLOCK_ADD_MAX_LEVELS_EXCEEDED"));
                            }
                        } else {
                            if ($this->setPropertiesListEdit()["PROPERTY_LIST_FULL"][$propertyID]["PROPERTY_TYPE"] == "F") {
                                $arFile = $_FILES["PROPERTY_FILE_" . $propertyID . "_0"];
                                $arFile["del"] = $_REQUEST["DELETE_FILE"][$propertyID][0] == "Y" ? "Y" : "";
                                $arUpdateValues[$propertyID] = $arFile;
                                if ($this->arParams["MAX_FILE_SIZE"] > 0 && $arFile["size"] > $this->arParams["MAX_FILE_SIZE"])
                                    $this->getArrErrors()[] = GetMessage("IBLOCK_ERROR_FILE_TOO_LARGE");
                            } elseif ($arResult["PROPERTY_LIST_FULL"][$propertyID]["PROPERTY_TYPE"] == "HTML") {
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

                // check required properties
                foreach ($this->arParams["PROPERTY_CODES_REQUIRED"] as $key => $propertyID) {
                    $bError = false;
                    $propertyValue = intval($propertyID) > 0 ? $arUpdatePropertyValues[$propertyID] : $arUpdateValues[$propertyID];

                    //Files check
                    if ($arResult["PROPERTY_LIST_FULL"][$propertyID]['PROPERTY_TYPE'] == 'F') {
                        //New element
                        if ($this->arParams["ID"] <= 0) {
                            $bError = true;
                            if (is_array($propertyValue)) {
                                if (array_key_exists("tmp_name", $propertyValue) && array_key_exists("size", $propertyValue)) {
                                    if ($propertyValue['size'] > 0) {
                                        $bError = false;
                                    }
                                } else {
                                    foreach ($propertyValue as $arFile) {
                                        if ($arFile['size'] > 0) {
                                            $bError = false;
                                            break;
                                        }
                                    }
                                }
                            }
                        } //Element field
                        elseif (intval($propertyID) <= 0) {
                            if ($propertyValue['size'] <= 0) {
                                if (intval($arElement[$propertyID]) <= 0 || $propertyValue['del'] == 'Y')
                                    $bError = true;
                            }
                        } //Element property
                        else {
                            $dbProperty = CIBlockElement::GetProperty(
                                $arElement["IBLOCK_ID"],
                                $this->arParams["ID"],
                                "sort", "asc",
                                array("ID" => $propertyID)
                            );

                            $bCount = 0;
                            while ($arProperty = $dbProperty->Fetch())
                                $bCount++;

                            foreach ($propertyValue as $arFile) {
                                if ($arFile['size'] > 0) {
                                    $bCount++;
                                    break;
                                } elseif ($arFile['del'] == 'Y') {
                                    $bCount--;
                                }
                            }

                            $bError = $bCount <= 0;
                        }
                    } //multiple property
                    elseif ($arResult["PROPERTY_LIST_FULL"][$propertyID]["MULTIPLE"] == "Y" || $arResult["PROPERTY_LIST_FULL"][$propertyID]["PROPERTY_TYPE"] == "L") {
                        if (is_array($propertyValue)) {
                            $bError = true;
                            foreach ($propertyValue as $value) {
                                if (strlen($value) > 0) {
                                    $bError = false;
                                    break;
                                }
                            }
                        } elseif (strlen($propertyValue) <= 0) {
                            $bError = true;
                        }
                    } //single
                    elseif (is_array($propertyValue) && array_key_exists("VALUE", $propertyValue)) {
                        if (strlen($propertyValue["VALUE"]) <= 0)
                            $bError = true;
                    } elseif (!is_array($propertyValue)) {
                        if (strlen($propertyValue) <= 0)
                            $bError = true;
                    }

                    if ($bError) {
                        $this->getArrErrors()[] = str_replace("#PROPERTY_NAME#", intval($propertyID) > 0 ? $arResult["PROPERTY_LIST_FULL"][$propertyID]["NAME"] : (!empty($arParams["CUSTOM_TITLE_" . $propertyID]) ? $arParams["CUSTOM_TITLE_" . $propertyID] : Loc::getMessage("IBLOCK_FIELD_" ). $propertyID)), Loc::getMessage("IBLOCK_ADD_ERROR_REQUIRED"));
                    }
                }

                // check captcha
                if ($this->arParams["USE_CAPTCHA"] == "Y" && $arParams["ID"] <= 0) {
                    if (!$APPLICATION->CaptchaCheckCode($_REQUEST["captcha_word"], $_REQUEST["captcha_sid"])) {
                        $this->getArrErrors()[] = Loc::getMessage("IBLOCK_FORM_WRONG_CAPTCHA");
                    }
                }

                if (count($this->getArrErrors()) == 0) {
                    if ($this->arParams["ELEMENT_ASSOC"] == "PROPERTY_ID")
                        $arUpdatePropertyValues[$this->arParams["ELEMENT_ASSOC_PROPERTY"]] = $this->_user()->GetID();
                    $arUpdateValues["MODIFIED_BY"] = $this->_user()->GetID();

                    $arUpdateValues["PROPERTY_VALUES"] = $arUpdatePropertyValues;


                    if ($this->arParams["STATUS_NEW"] == "ANY") {
                        $arUpdateValues["ACTIVE"] = "N";
                    } elseif ($this->arParams["STATUS_NEW"] == "N") {
                        $arUpdateValues["ACTIVE"] = "Y";
                    } else {
                        if ($this->arParams["ID"] <= 0) $arUpdateValues["ACTIVE"] = "N";
                        //$arUpdateValues["ACTIVE"] = $arParams["ID"] > 0 ? "Y" : "N";
                    }


                    // update existing element
                    $oElement = new CIBlockElement();
                    if ($arParams["ID"] > 0) {
                        $sAction = "EDIT";

                        $bFieldProps = array();
                        foreach ($arUpdateValues["PROPERTY_VALUES"] as $prop_id => $v) {
                            $bFieldProps[$prop_id] = true;
                        }
                        $dbPropV = CIBlockElement::GetProperty($arParams["IBLOCK_ID"], $arParams["ID"], "sort", "asc", array("ACTIVE" => "Y"));
                        while ($arPropV = $dbPropV->Fetch()) {
                            if (!array_key_exists($arPropV["ID"], $bFieldProps) && $arPropV["PROPERTY_TYPE"] != "F") {
                                if ($arPropV["MULTIPLE"] == "Y") {
                                    if (!array_key_exists($arPropV["ID"], $arUpdateValues["PROPERTY_VALUES"]))
                                        $arUpdateValues["PROPERTY_VALUES"][$arPropV["ID"]] = array();
                                    $arUpdateValues["PROPERTY_VALUES"][$arPropV["ID"]][$arPropV["PROPERTY_VALUE_ID"]] = array(
                                        "VALUE" => $arPropV["VALUE"],
                                        "DESCRIPTION" => $arPropV["DESCRIPTION"],
                                    );
                                } else {
                                    $arUpdateValues["PROPERTY_VALUES"][$arPropV["ID"]] = array(
                                        "VALUE" => $arPropV["VALUE"],
                                        "DESCRIPTION" => $arPropV["DESCRIPTION"],
                                    );
                                }
                            }
                        }


                    } // add new element
                    else {
                        $arUpdateValues["IBLOCK_ID"] = $this->arParams["IBLOCK_ID"];

                        // set activity start date for new element to current date. Change it, if ya want ;-)
                        if (strlen($arUpdateValues["DATE_ACTIVE_FROM"]) <= 0) {
                            $arUpdateValues["DATE_ACTIVE_FROM"] = ConvertTimeStamp(false, "FULL");
                        }

                        $sAction = "ADD";
                        if (!$arParams["ID"] = $oElement->Add($arUpdateValues, $bWorkflowIncluded, true, $this->arParams["RESIZE_IMAGES"])) {
                            $this->getArrErrors()["ERRORS"][] = $oElement->LAST_ERROR;
                        } else {
                            if ($arParams["SEND_EMAIL"] == "Y") {
                                if (!empty($arUpdateValues["IBLOCK_SECTION"]["0"])) {
                                    $SECTION_ID = $arUpdateValues["IBLOCK_SECTION"]["0"];
                                } else {
                                    $SECTION_ID = 0;
                                }

                                $arFields = array(
                                    "NAME" => $arUpdateValues["NAME"],
                                    "SUBJECT" => $arParams["SUBJECT"],
                                    "EMAIL_TO" => $arParams["EMAIL_TO"],
                                    "IBLOCK_TYPE" => $arParams["IBLOCK_TYPE"],
                                    "IBLOCK_ID" => $arParams["IBLOCK_ID"],
                                    "ID" => $arParams["ID"],
                                    "SECTION_ID" => $SECTION_ID,
                                );
                                if (!empty($arParams["EVENT_MESSAGE_ID"])) {
                                    foreach ($arParams["EVENT_MESSAGE_ID"] as $v)
                                        if (IntVal($v) > 0)
                                            CEvent::Send($arParams["EVENT_NAME"], SITE_ID, $arFields, "N", IntVal($v));
                                } else
                                    CEvent::Send($arParams["EVENT_NAME"], SITE_ID, $arFields);
                                /*use Bitrix\Main\Mail\Event;
                                Event::send(array(
                                    "EVENT_NAME" => "NEW_USER",
                                    "LID" => "s1",
                                    "C_FIELDS" => array(
                                        "EMAIL" => "info@intervolga.ru",
                                        "USER_ID" => 42
                                    ),
                                ));*/


                            }
                        }

                        if (!empty($_REQUEST["iblock_apply"]) && strlen($SEF_URL) > 0) {
                            if (strpos($SEF_URL, "?") === false) $SEF_URL .= "?edit=Y";
                            elseif (strpos($SEF_URL, "edit=") === false) $SEF_URL .= "&edit=Y";
                            $SEF_URL .= "&CODE=" . $arParams["ID"];
                        }
                    }
                }

                // redirect to element edit form or to elements list
                if (count($this->getArrErrors()) == 0) {
                    if (!empty($_REQUEST["iblock_submit"])) {
                        if (strlen($arParams["LIST_URL"]) > 0) {
                            $sRedirectUrl = $arParams["LIST_URL"];
                        } else {
                            if (strlen($SEF_URL) > 0) {
                                $SEF_URL = str_replace("edit=Y", "", $SEF_URL);
                                $SEF_URL = str_replace("?&", "?", $SEF_URL);
                                $SEF_URL = str_replace("&&", "&", $SEF_URL);
                                $sRedirectUrl = $SEF_URL;
                            } else {
                                $sRedirectUrl = $APPLICATION->GetCurPageParam("", array("edit", "CODE"), $get_index_page = false);
                            }

                        }
                    } else {
                        if (strlen($SEF_URL) > 0)
                            $sRedirectUrl = $SEF_URL;
                        else
                            $sRedirectUrl = $APPLICATION->GetCurPageParam("edit=Y&CODE=" . $arParams["ID"], array("edit", "CODE"), $get_index_page = false);
                    }

                    $sAction = $sAction == "ADD" ? "ADD" : "EDIT";
                    $sRedirectUrl .= (strpos($sRedirectUrl, "?") === false ? "?" : "&") . "strIMessage=";
                    $sRedirectUrl .= urlencode($arParams["USER_MESSAGE_" . $sAction]);

                    //echo $sRedirectUrl;
                    LocalRedirect($sRedirectUrl);
                    exit();
                }
            }

            //prepare data for form
            $arPropertyRequired=array();
            $arPropertyRequired = is_array($this->arParams["PROPERTY_CODES_REQUIRED"]) ? $this->arParams["PROPERTY_CODES_REQUIRED"] : array();

            if ($this->arParams["ID"] > 0) {
                // $arElement is defined before in elements rights check


                $rsElementSections = CIBlockElement::GetElementGroups($arElement["ID"]);
                $arElement["IBLOCK_SECTION"] = array();
                while ($arSection = $rsElementSections->GetNext()) {
                    $arElement["IBLOCK_SECTION"][] = array("VALUE" => $arSection["ID"]);
                }

                $element = \Bitrix\Iblock\Elements\ElementTable::getList([
                    'select' => ['ID', 'SECTIONS'],
                    'filter' => [
                        'ID' => $elementId,
                    ],
                ])->fetchObject();

                foreach ($element->getSections()->getAll() as $section) {
                    var_dump($section->getId());
                    // int(8)
                    var_dump($section->getCode());
                    // string(5) "pants"
                    var_dump($section->getName());
                    // string(10) "Штаны"
                }

                $arResult["ELEMENT"] = array();
                foreach ($arElement as $key => $value) {
                    $arResult["ELEMENT"]["~" . $key] = $value;
                    if (!is_array($value) && !is_object($value))
                        $arResult["ELEMENT"][$key] = htmlspecialcharsbx($value);
                    else
                        $arResult["ELEMENT"][$key] = $value;
                }

                //Restore HTML if needed
                if (
                    $this->arParams["DETAIL_TEXT_USE_HTML_EDITOR"]
                    && array_key_exists("DETAIL_TEXT", $arResult["ELEMENT"])
                    && strtolower($arResult["ELEMENT"]["DETAIL_TEXT_TYPE"]) == "html"
                )
                    $arResult["ELEMENT"]["DETAIL_TEXT"] = $arResult["ELEMENT"]["~DETAIL_TEXT"];

                if (
                    $this->arParams["PREVIEW_TEXT_USE_HTML_EDITOR"]
                    && array_key_exists("PREVIEW_TEXT", $arResult["ELEMENT"])
                    && strtolower($arResult["ELEMENT"]["PREVIEW_TEXT_TYPE"]) == "html"
                )
                    $arResult["ELEMENT"]["PREVIEW_TEXT"] = $arResult["ELEMENT"]["~PREVIEW_TEXT"];


                //$arResult["ELEMENT"] = $arElement;

                // load element properties
                $rsElementProperties = CIBlockElement::GetProperty($arParams["IBLOCK_ID"], $arElement["ID"], $by = "sort", $order = "asc");
                $arResult["ELEMENT_PROPERTIES"] = array();
                while ($arElementProperty = $rsElementProperties->Fetch()) {
                    if (!array_key_exists($arElementProperty["ID"], $arResult["ELEMENT_PROPERTIES"]))
                        $arResult["ELEMENT_PROPERTIES"][$arElementProperty["ID"]] = array();

                    if (is_array($arElementProperty["VALUE"])) {
                        $htmlvalue = array();
                        foreach ($arElementProperty["VALUE"] as $k => $v) {
                            if (is_array($v)) {
                                $htmlvalue[$k] = array();
                                foreach ($v as $k1 => $v1)
                                    $htmlvalue[$k][$k1] = htmlspecialcharsbx($v1);
                            } else {
                                $htmlvalue[$k] = htmlspecialcharsbx($v);
                            }
                        }
                    } else {
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
                foreach ($arResult["PROPERTY_LIST"] as $propertyID) {
                    $arProperty = $arResult["PROPERTY_LIST_FULL"][$propertyID];
                    if ($arProperty["PROPERTY_TYPE"] == "F") {
                        $arValues = array();
                        if (intval($propertyID) > 0) {
                            foreach ($arResult["ELEMENT_PROPERTIES"][$propertyID] as $arProperty) {
                                $arValues[] = $arProperty["VALUE"];
                            }
                        } else {
                            $arValues[] = $arResult["ELEMENT"][$propertyID];
                        }

                        foreach ($arValues as $value) {
                            if ($arFile = CFile::GetFileArray($value)) {
                                $arFile["IS_IMAGE"] = CFile::IsImage($arFile["FILE_NAME"], $arFile["CONTENT_TYPE"]);
                                $arResult["ELEMENT_FILES"][$value] = $arFile;
                            }
                        }
                    }
                }

                $bShowForm = true;
            } else {
                $bShowForm = true;
            }

            if ($bShowForm) {
                // prepare form data if some errors occured
                if (count($arResult["ERRORS"]) > 0) {
                    //echo "<pre>",htmlspecialcharsbx(print_r($arUpdateValues, true)),"</pre>";
                    foreach ($arUpdateValues as $key => $value) {
                        if ($key == "IBLOCK_SECTION") {
                            $arResult["ELEMENT"][$key] = array();
                            if (!is_array($value)) {
                                $arResult["ELEMENT"][$key][] = array("VALUE" => htmlspecialcharsbx($value));
                            } else {
                                foreach ($value as $vkey => $vvalue) {
                                    $arResult["ELEMENT"][$key][$vkey] = array("VALUE" => htmlspecialcharsbx($vvalue));
                                }
                            }
                        } elseif ($key == "PROPERTY_VALUES") {
                            //Skip
                        } elseif ($arResult["PROPERTY_LIST_FULL"][$key]["PROPERTY_TYPE"] == "F") {
                            //Skip
                        } elseif ($arResult["PROPERTY_LIST_FULL"][$key]["PROPERTY_TYPE"] == "HTML") {
                            $arResult["ELEMENT"][$key] = $value;
                        } else {
                            $arResult["ELEMENT"][$key] = htmlspecialcharsbx($value);
                        }
                    }

                    foreach ($arUpdatePropertyValues as $key => $value) {
                        if ($arResult["PROPERTY_LIST_FULL"][$key]["PROPERTY_TYPE"] != "F") {
                            $arResult["ELEMENT_PROPERTIES"][$key] = array();
                            if (!is_array($value)) {
                                $value = array(
                                    array("VALUE" => $value),
                                );
                            }
                            foreach ($value as $vv) {
                                if (is_array($vv)) {
                                    if (array_key_exists("VALUE", $vv))
                                        $arResult["ELEMENT_PROPERTIES"][$key][] = array(
                                            "~VALUE" => $vv["VALUE"],
                                            "VALUE" => htmlspecialcharsbx($vv["VALUE"]),
                                        );
                                    else
                                        $arResult["ELEMENT_PROPERTIES"][$key][] = array(
                                            "~VALUE" => $vv,
                                            "VALUE" => $vv,
                                        );
                                } else {
                                    $arResult["ELEMENT_PROPERTIES"][$key][] = array(
                                        "~VALUE" => $vv,
                                        "VALUE" => htmlspecialcharsbx($vv),
                                    );
                                }
                            }
                        }
                    }
                }

                // prepare captcha
                if ($this->arParams["USE_CAPTCHA"] == "Y" && $arParams["ID"] <= 0) {
                    $arResult["CAPTCHA_CODE"] = htmlspecialcharsbx($APPLICATION->CaptchaGetCode());
                }

                $arResult["MESSAGE"] = htmlspecialcharsex($_REQUEST["strIMessage"]);


            }
        }
    }
}

?>
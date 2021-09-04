<?php
namespace Bitrix\Iblock;

use Bitrix\Main,
    Bitrix\Main\Localization\Loc;
Loc::loadMessages(__FILE__);
/**
 * Class ElementTable
 *
 * Fields:
 * <ul>
 * <li> ID int mandatory
 * <li> TIMESTAMP_X datetime optional
 * <li> MODIFIED_BY int optional
 * <li> DATE_CREATE datetime optional
 * <li> CREATED_BY int optional
 * <li> IBLOCK_ID int mandatory
 * <li> IBLOCK_SECTION_ID int optional
 * <li> ACTIVE bool optional default 'Y'
 * <li> ACTIVE_FROM datetime optional
 * <li> ACTIVE_TO datetime optional
 * <li> SORT int optional default 500
 * <li> NAME string(255) mandatory
 * <li> PREVIEW_PICTURE int optional
 * <li> PREVIEW_TEXT string optional
 * <li> PREVIEW_TEXT_TYPE enum ('text', 'html') optional default 'text'
 * <li> DETAIL_PICTURE int optional
 * <li> DETAIL_TEXT string optional
 * <li> DETAIL_TEXT_TYPE enum ('text', 'html') optional default 'text'
 * <li> SEARCHABLE_CONTENT string optional
 * <li> WF_STATUS_ID int optional default 1
 * <li> WF_PARENT_ELEMENT_ID int optional
 * <li> WF_NEW string(1) optional
 * <li> WF_LOCKED_BY int optional
 * <li> WF_DATE_LOCK datetime optional
 * <li> WF_COMMENTS string optional
 * <li> IN_SECTIONS bool optional default 'N'
 * <li> XML_ID string(255) optional
 * <li> CODE string(255) optional
 * <li> TAGS string(255) optional
 * <li> TMP_ID string(40) optional
 * <li> WF_LAST_HISTORY_ID int optional
 * <li> SHOW_COUNTER int optional
 * <li> SHOW_COUNTER_START datetime optional
 * <li> PREVIEW_PICTURE reference to {@link \Bitrix\File\FileTable}
 * <li> DETAIL_PICTURE reference to {@link \Bitrix\File\FileTable}
 * <li> IBLOCK reference to {@link \Bitrix\Iblock\IblockTable}
 * <li> WF_PARENT_ELEMENT reference to {@link \Bitrix\Iblock\IblockElementTable}
 * <li> IBLOCK_SECTION reference to {@link \Bitrix\Iblock\IblockSectionTable}
 * <li> MODIFIED_BY reference to {@link \Bitrix\User\UserTable}
 * <li> CREATED_BY reference to {@link \Bitrix\User\UserTable}
 * <li> WF_LOCKED_BY reference to {@link \Bitrix\User\UserTable}
 * </ul>
 *
 * @package Bitrix\Iblock
 **/

class ElementAddparkTable extends Main\Entity\DataManager
{
    /**
     * Returns DB table name for entity.
     *
     * @return string
     */
    public static function getTableName()
    {
        return 'b_iblock_element';
    }

    /**
     * Returns entity map definition.
     *
     * @return array
     */
    public static function getMap()
    {
        return array(
            'ID' => array(
                'data_type' => 'integer',
                'primary' => true,
                'autocomplete' => true,
                'title' => Loc::getMessage('ELEMENT_ENTITY_ID_FIELD'),
            ),
            'TIMESTAMP_X' => array(
                'data_type' => 'datetime',
                'title' => Loc::getMessage('ELEMENT_ENTITY_TIMESTAMP_X_FIELD'),
            ),
            'MODIFIED_BY' => array(
                'data_type' => 'integer',
                'title' => Loc::getMessage('ELEMENT_ENTITY_MODIFIED_BY_FIELD'),
            ),
            'DATE_CREATE' => array(
                'data_type' => 'datetime',
                'title' => Loc::getMessage('ELEMENT_ENTITY_DATE_CREATE_FIELD'),
            ),
            'CREATED_BY' => array(
                'data_type' => 'integer',
                'title' => Loc::getMessage('ELEMENT_ENTITY_CREATED_BY_FIELD'),
            ),
            'IBLOCK_ID' => array(
                'data_type' => 'integer',
                'required' => true,
                'title' => Loc::getMessage('ELEMENT_ENTITY_IBLOCK_ID_FIELD'),
            ),
            'IBLOCK_SECTION_ID' => array(
                'data_type' => 'integer',
                'title' => Loc::getMessage('ELEMENT_ENTITY_IBLOCK_SECTION_ID_FIELD'),
            ),
            'ACTIVE' => array(
                'data_type' => 'boolean',
                'values' => array('N', 'Y'),
                'title' => Loc::getMessage('ELEMENT_ENTITY_ACTIVE_FIELD'),
            ),
            'ACTIVE_FROM' => array(
                'data_type' => 'datetime',
                'title' => Loc::getMessage('ELEMENT_ENTITY_ACTIVE_FROM_FIELD'),
            ),
            'ACTIVE_TO' => array(
                'data_type' => 'datetime',
                'title' => Loc::getMessage('ELEMENT_ENTITY_ACTIVE_TO_FIELD'),
            ),
            'SORT' => array(
                'data_type' => 'integer',
                'title' => Loc::getMessage('ELEMENT_ENTITY_SORT_FIELD'),
            ),
            'NAME' => array(
                'data_type' => 'string',
                'required' => true,
                'validation' => array(__CLASS__, 'validateName'),
                'title' => Loc::getMessage('ELEMENT_ENTITY_NAME_FIELD'),
            ),
            'PREVIEW_PICTURE' => array(
                'data_type' => 'integer',
                'title' => Loc::getMessage('ELEMENT_ENTITY_PREVIEW_PICTURE_FIELD'),
            ),
            'PREVIEW_TEXT' => array(
                'data_type' => 'text',
                'title' => Loc::getMessage('ELEMENT_ENTITY_PREVIEW_TEXT_FIELD'),
            ),
            'PREVIEW_TEXT_TYPE' => array(
                'data_type' => 'enum',
                'values' => array('text', 'html'),
                'title' => Loc::getMessage('ELEMENT_ENTITY_PREVIEW_TEXT_TYPE_FIELD'),
            ),
            'DETAIL_PICTURE' => array(
                'data_type' => 'integer',
                'title' => Loc::getMessage('ELEMENT_ENTITY_DETAIL_PICTURE_FIELD'),
            ),
            'DETAIL_TEXT' => array(
                'data_type' => 'text',
                'title' => Loc::getMessage('ELEMENT_ENTITY_DETAIL_TEXT_FIELD'),
            ),
            'DETAIL_TEXT_TYPE' => array(
                'data_type' => 'enum',
                'values' => array('text', 'html'),
                'title' => Loc::getMessage('ELEMENT_ENTITY_DETAIL_TEXT_TYPE_FIELD'),
            ),
            'SEARCHABLE_CONTENT' => array(
                'data_type' => 'text',
                'title' => Loc::getMessage('ELEMENT_ENTITY_SEARCHABLE_CONTENT_FIELD'),
            ),
            'WF_STATUS_ID' => array(
                'data_type' => 'integer',
                'title' => Loc::getMessage('ELEMENT_ENTITY_WF_STATUS_ID_FIELD'),
            ),
            'WF_PARENT_ELEMENT_ID' => array(
                'data_type' => 'integer',
                'title' => Loc::getMessage('ELEMENT_ENTITY_WF_PARENT_ELEMENT_ID_FIELD'),
            ),
            'WF_NEW' => array(
                'data_type' => 'string',
                'validation' => array(__CLASS__, 'validateWfNew'),
                'title' => Loc::getMessage('ELEMENT_ENTITY_WF_NEW_FIELD'),
            ),
            'WF_LOCKED_BY' => array(
                'data_type' => 'integer',
                'title' => Loc::getMessage('ELEMENT_ENTITY_WF_LOCKED_BY_FIELD'),
            ),
            'WF_DATE_LOCK' => array(
                'data_type' => 'datetime',
                'title' => Loc::getMessage('ELEMENT_ENTITY_WF_DATE_LOCK_FIELD'),
            ),
            'WF_COMMENTS' => array(
                'data_type' => 'text',
                'title' => Loc::getMessage('ELEMENT_ENTITY_WF_COMMENTS_FIELD'),
            ),
            'IN_SECTIONS' => array(
                'data_type' => 'boolean',
                'values' => array('N', 'Y'),
                'title' => Loc::getMessage('ELEMENT_ENTITY_IN_SECTIONS_FIELD'),
            ),
            'XML_ID' => array(
                'data_type' => 'string',
                'validation' => array(__CLASS__, 'validateXmlId'),
                'title' => Loc::getMessage('ELEMENT_ENTITY_XML_ID_FIELD'),
            ),
            'CODE' => array(
                'data_type' => 'string',
                'validation' => array(__CLASS__, 'validateCode'),
                'title' => Loc::getMessage('ELEMENT_ENTITY_CODE_FIELD'),
            ),
            'TAGS' => array(
                'data_type' => 'string',
                'validation' => array(__CLASS__, 'validateTags'),
                'title' => Loc::getMessage('ELEMENT_ENTITY_TAGS_FIELD'),
            ),
            'TMP_ID' => array(
                'data_type' => 'string',
                'validation' => array(__CLASS__, 'validateTmpId'),
                'title' => Loc::getMessage('ELEMENT_ENTITY_TMP_ID_FIELD'),
            ),
            'WF_LAST_HISTORY_ID' => array(
                'data_type' => 'integer',
                'title' => Loc::getMessage('ELEMENT_ENTITY_WF_LAST_HISTORY_ID_FIELD'),
            ),
            'SHOW_COUNTER' => array(
                'data_type' => 'integer',
                'title' => Loc::getMessage('ELEMENT_ENTITY_SHOW_COUNTER_FIELD'),
            ),
            'SHOW_COUNTER_START' => array(
                'data_type' => 'datetime',
                'title' => Loc::getMessage('ELEMENT_ENTITY_SHOW_COUNTER_START_FIELD'),
            ),
            'PREVIEW_PICTURE' => array(
                'data_type' => 'Bitrix\File\File',
                'reference' => array('=this.PREVIEW_PICTURE' => 'ref.ID'),
            ),
            'DETAIL_PICTURE' => array(
                'data_type' => 'Bitrix\File\File',
                'reference' => array('=this.DETAIL_PICTURE' => 'ref.ID'),
            ),
            'IBLOCK' => array(
                'data_type' => 'Bitrix\Iblock\Iblock',
                'reference' => array('=this.IBLOCK_ID' => 'ref.ID'),
            ),
            'WF_PARENT_ELEMENT' => array(
                'data_type' => 'Bitrix\Iblock\IblockElement',
                'reference' => array('=this.WF_PARENT_ELEMENT_ID' => 'ref.ID'),
            ),
            'IBLOCK_SECTION' => array(
                'data_type' => 'Bitrix\Iblock\IblockSection',
                'reference' => array('=this.IBLOCK_SECTION_ID' => 'ref.ID'),
            ),
            'MODIFIED_BY' => array(
                'data_type' => 'Bitrix\User\User',
                'reference' => array('=this.MODIFIED_BY' => 'ref.ID'),
            ),
            'CREATED_BY' => array(
                'data_type' => 'Bitrix\User\User',
                'reference' => array('=this.CREATED_BY' => 'ref.ID'),
            ),
            'WF_LOCKED_BY' => array(
                'data_type' => 'Bitrix\User\User',
                'reference' => array('=this.WF_LOCKED_BY' => 'ref.ID'),
            ),
        );
    }

    /**
     * Returns validators for NAME field.
     *
     * @return array
     */
    public static function validateName()
    {
        return array(
            new Main\Entity\Validator\Length(null, 255),
        );
    }

    /**
     * Returns validators for WF_NEW field.
     *
     * @return array
     */
    public static function validateWfNew()
    {
        return array(
            new Main\Entity\Validator\Length(null, 1),
        );
    }

    /**
     * Returns validators for XML_ID field.
     *
     * @return array
     */
    public static function validateXmlId()
    {
        return array(
            new Main\Entity\Validator\Length(null, 255),
        );
    }

    /**
     * Returns validators for CODE field.
     *
     * @return array
     */
    public static function validateCode()
    {
        return array(
            new Main\Entity\Validator\Length(null, 255),
        );
    }

    /**
     * Returns validators for TAGS field.
     *
     * @return array
     */
    public static function validateTags()
    {
        return array(
            new Main\Entity\Validator\Length(null, 255),
        );
    }

    /**
     * Returns validators for TMP_ID field.
     *
     * @return array
     */
    public static function validateTmpId()
    {
        return array(
            new Main\Entity\Validator\Length(null, 40),
        );
    }
}
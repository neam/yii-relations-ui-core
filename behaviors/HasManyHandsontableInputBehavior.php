<?php

namespace neam\yii_relations_ui_core\behaviors;

use Yii;
use CException;
use CActiveRecord;
use CActiveRecordBehavior;
use CValidator;

/**
 * Behavior that eases editing has-many relations using handsontable
 *
 * Adds virtual attributes - each of the configured attributes prefixed with "handsontable_input_"
 * to use together with a HandsontableInput
 *
 * The virtual behavior will list list the related records in the handsontable so that they can be
 * edited, new records can be created etc. These records are then simply saved as is.
 *
 * Class HasManyHandsontableInputBehavior
 *
 * @uses CActiveRecordBehavior
 * @license BSD-3-Clause
 * @author See https://github.com/neam/yii-relations-ui-core/graphs/contributors
 */
class HasManyHandsontableInputBehavior extends CActiveRecordBehavior
{

    public $attributes = array();

    protected $_toSave = array();

    public function virtualToActualAttribute($name)
    {
        return str_replace("handsontable_input_", "", $name);
    }

    public function actualToVirtualAttribute($attribute)
    {
        return "handsontable_input_" . $attribute;
    }

    /**
     * Expose temporary handsontable input attributes as readable
     */
    public function canGetProperty($name)
    {
        return $this->handlesProperty($name);
    }

    /**
     * Expose temporary handsontable input attributes as writable
     */
    public function canSetProperty($name)
    {
        return $this->handlesProperty($name);
    }

    /**
     *
     * @param string $name
     * @return bool
     */
    protected function handlesProperty($name)
    {
        if (in_array($this->virtualToActualAttribute($name), $this->attributes)) {
            return true;
        }
    }

    /**
     * Mark the handsontable input attributes as safe, so that forms that rely
     * on setting attributes from post values works without modification.
     *
     * @param CActiveRecord $owner
     * @throws Exception
     */
    public function attach($owner)
    {

        parent::attach($owner);
        if (!($owner instanceof CActiveRecord)) {
            throw new CException('Owner must be a CActiveRecord class');
        }

        $validators = $owner->getValidatorList();

        foreach ($this->attributes as $attribute) {
            $validators->add(CValidator::createValidator('safe', $owner, $this->actualTovirtualAttribute($attribute), array()));
        }

    }

    /**
     * Make temporary handsontable input attributes readable
     */
    public function __get($name)
    {

        if (!$this->handlesProperty($name)) {
            return parent::__get($name);
        }

        if (!empty($this->_toSave[$name])) {
            return $this->_toSave[$name];
        }

        $handsontableData = array();

        $relationAttribute = $this->virtualToActualAttribute($name);

        if (count($this->getOwner()->$relationAttribute) > 0) {

            foreach ($this->owner->$relationAttribute as $item) {
                $handsontableData[] = $item->attributes;
            }

        }

        $json = json_encode($handsontableData);
        return $json;

    }

    /**
     * Make temporary handsontable input attributes writable
     */
    public function __set($name, $value)
    {
        if (!$this->handlesProperty($name)) {
            return parent::__set($name, $value);
        }
        $this->_toSave[$name] = $value;
    }

    /**
     * Finds and populates a list of active records matching the grid data
     * @param $value
     */
    protected function validatedActiveRecords($relationAttribute, $json, &$validationErrors = null)
    {

        $value = json_decode($json, true);

        // Get metadata for the relation we are editing
        $relations = $this->owner->relations();
        $relation = $relations[$relationAttribute];
        switch ($relation[0]) // relation type such as BELONGS_TO, HAS_ONE, HAS_MANY, MANY_MANY
        {
            // HAS_MANY: if the relationship between table A and B is one-to-many, then A has many B
            //           (e.g. User has many Post);
            case CActiveRecord::HAS_MANY:
                $relationClass = $relation[1];
                $relationModel = $relationClass::model();
                $pkAttribute = $relationModel->tableSchema->primaryKey;
                if (!isset($relation["through"])) {
                    $linkAttribute = $this->owner->tableSchema->primaryKey;
                    $defaultLinkAttributeValue = $this->owner->$pkAttribute;
                } else {
                    $linkAttribute = $relation[2][$pkAttribute];
                    $defaultLinkAttributeValue = $this->owner->$linkAttribute;
                }
                break;
            default:
                throw new CException("Unsupported relation type");
        }

        // Find or create new active records
        $activeRecords = array();
        foreach ($value as $k => $row) {

            // ignore completely empty rows, since we can't really do anything with them
            $emptyCheck = implode("", $row);
            if (empty($emptyCheck)) {
                Yii::log("Row $k had only empty values so it was removed", 'info', __METHOD__);
                continue;
            }

            $pkVal = $row[$pkAttribute];
            $ar = null;
            // create new attributes when the primary key is empty
            if (empty($pkVal)) {
                $ar = new $relationClass;
            } else {
                $ar = $relationModel->findByPk($pkVal);
                if (empty($ar)) {
                    $ar = new $relationClass;
                }
            }
            // set the attributes to the grid data row's data
            $ar->attributes = $row;
            // set default link attribute - this is what makes newly created records link to the item we are editing
            if (empty($ar->$linkAttribute)) {
                $ar->$linkAttribute = $defaultLinkAttributeValue;
            }
            // validate and collect any validation errors
            $ar->validate();
            if ($ar->hasErrors()) {
                $validationErrors[$k] = $ar->errors;
            }
            $activeRecords[] = $ar;
        }

        return $activeRecords;

    }

    public function beforeValidate($event)
    {

        foreach ($this->_toSave as $name => $json) {

            if (empty($json)) {
                Yii::log("$name was empty so no attempt to validate related items will be made", 'info', __METHOD__);
                continue;
            }

            $relationAttribute = $this->virtualToActualAttribute($name);
            try {

                $validationErrors = array();
                $activeRecords = $this->validatedActiveRecords($relationAttribute, $json, $validationErrors);
                if (!empty($validationErrors)) {
                    $this->owner->addError($relationAttribute, "Related records did not validate: " . print_r($validationErrors, true));
                }

            } catch (Exception $e) {
                $this->owner->addError($relationAttribute, "Exception during ar validation: " . $e->getMessage());
            }

        }
    }

    public function beforeSave($event)
    {

        foreach ($this->_toSave as $name => $json) {

            if (empty($json)) {
                Yii::log("$name was empty so no attempt to save related items will be made", 'info', __METHOD__);
                continue;
            }

            // Workaround for "through" relations (until they are supported: https://github.com/yiiext/activerecord-relation-behavior/issues/14)
            // is to set the related records on the "through" relation. Works only when the relation is through has one
            // and the "through" relation needs to have the same relation defined, albeit as a simple has many relation.

            $relationAttribute = $this->virtualToActualAttribute($name);
            try {

                $activeRecords = $this->validatedActiveRecords($relationAttribute, $json);

                $validationErrors = array();
                foreach ($activeRecords as $k => $ar) {
                    $ar->save();
                }

                if ($ar->hasErrors()) {
                    $validationErrors[$k] = $ar->errors;
                }

                if (!empty($validationErrors)) {
                    $this->owner->addError($relationAttribute, "Related records did not validate: " . print_r($validationErrors, true));
                }

                $this->owner->{$relationAttribute} = $activeRecords;
                unset($this->_toSave[$name]);

            } catch (Exception $e) {
                $this->owner->addError($relationAttribute, "Exception during ar validation: " . $e->getMessage());
            }

        }

        return true;

    }

}
<?php

namespace neam\yii_relations_ui_core\behaviors;

use Yii;
use CException;
use CActiveRecord;
use CActiveRecordBehavior;
use CValidator;

/**
 * Behavior that eases editing relations using handsontable
 *
 * Adds virtual attributes - each of the configured attributes prefixed with "handsontable_input_"
 * to use together with HandsontableInput
 *
 * Class HandsontableInputBehavior
 *
 * @uses CActiveRecordBehavior
 * @license BSD-3-Clause
 * @author See https://github.com/neam/yii-relations-ui-core/graphs/contributors
 */
class HandsontableRelationInputBehavior extends CActiveRecordBehavior
{

    public $attributes = array();

    protected $_toSave = array();

    public function init()
    {
        var_dump(__LINE__, $this->attributes);die();
        foreach ($this->attributes as $attribute) {
            $this->_toSave[$attribute] = null;
        }
        var_dump(__LINE__, $this->attributes);die();
        return parent::init();
    }

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
        if (in_array($this->virtualToActualAttribute($name), array_keys($this->attributes))) {
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

        $handsontableData = array();

        $relationAttribute = $this->virtualToActualAttribute($name);

        if (count($this->getOwner()->$relationAttribute) > 0) {

            foreach ($this->owner->$relationAttribute as $item) {
                $handsontableData[] = array_values($item->attributes);
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
        var_dump(__LINE__, $name, $value);die();
        if (!$this->handlesProperty($name)) {
            return parent::__set($name, $value);
        }
        $this->_toSave[$name] = $value;
    }

    public function beforeSave($event)
    {
var_dump($this->_toSave);
        die();
        foreach ($this->_toSave as $name => $value) {

            if (empty($value)) {
                Yii::log("$name was empty so no attempt to save related items will be made", 'info', __METHOD__);
                continue;
            }

            // TODO
            throw new CException("TODO");

            unset($this->_toSave[$name]);
        }

        return true;

    }

}
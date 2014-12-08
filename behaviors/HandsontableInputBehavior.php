<?php

namespace neam\yii_relations_ui_core\behaviors;

use Yii;
use CException;
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
class HandsontableInputBehavior extends CActiveRecordBehavior
{

    public $attributes = array();

}
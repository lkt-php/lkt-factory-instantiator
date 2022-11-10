<?php

namespace Lkt\Factory\Instantiator\Instances\AccessDataTraits;

use function Lkt\Factory\factory;


trait ColumnForeignTrait
{
    /**
     * @param $type
     * @param $id
     * @return mixed
     */
    protected function _getForeignVal($type = '', $id = 0)
    {
//        if ($type === 'opportunity-register-sites') {
//            dump(['_getForeignVal', $type, $id]);
//        }
        if (!$type || $id <= 0) {
//            if ($type === 'opportunity-register-sites') {
//                dump(['_getForeignVal exit', $type, $id]);
//            }
            return null;
        }
        return factory($type, $id)->instance();
    }

    /**
     * @param $type
     * @param $id
     * @return bool
     */
    protected function _hasForeignVal($type = '', $id = 0): bool
    {
        return is_object($this->_getForeignVal($type, $id));
    }
}
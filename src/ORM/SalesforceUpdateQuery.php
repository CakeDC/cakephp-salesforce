<?php
/**
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @since         3.0.0
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */

namespace Salesforce\ORM;

use Cake\Database\ValueBinder;
use Cake\Datasource\ResultSetInterface;
use Cake\ORM\Query\UpdateQuery;
use Salesforce\ORM\Traits\SalesforceQueryTrait;

/**
 * Extends the base UpdateQuery class to provide new methods related to association
 * loading, automatic fields selection, automatic type casting and to wrap results
 * into a specific iterator that will be responsible for hydrating results if
 * required.
 *
 */
class SalesforceUpdateQuery extends UpdateQuery
{
    use SalesforceQueryTrait;

    /**
     * @inheritDoc
     */
    public function sql(?ValueBinder $binder = null): string {
        if ($this->_type === 'update' && empty($this->_parts['update'])) {
            $repository = $this->getRepository();
            $this->update($repository->getTable());
        }

        return $this->customSql($binder);
    }

    /**
     * @inheritDoc
     */
    protected function _execute(): ResultSetInterface
    {
        return $this->customExecute();
    }
}

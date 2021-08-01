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

use ArrayObject;
use Cake\Datasource\ResultSetInterface;
use Salesforce\ORM\SalesforceResultSet;
use Salesforce\Database\SalesforceQuery as SalesforceDatabaseQuery;
use Cake\ORM\Query;

use Cake\Database\ValueBinder;


/**
 * Extends the base Query class to provide new methods related to association
 * loading, automatic fields selection, automatic type casting and to wrap results
 * into a specific iterator that will be responsible for hydrating results if
 * required.
 *
 */
class SalesforceQuery extends Query
{
    public $queryString = "";

    /**
     * {@inheritDoc}
     */
    public function sql(?ValueBinder $binder = null): string
    {
        $this->triggerBeforeFind();

        $this->_transformQuery();

        // Not a static function, and can't be made static because of base class,
        // so we @hide the warning about a non-static function used in a static
        // context.
        $sql = @SalesforceDatabaseQuery::sql(null, $this);

        return $sql;
    }

    /**
     * {@inheritDoc}
     */
    protected function _execute(): ResultSetInterface
    {
        $this->triggerBeforeFind();
        if ($this->_results) {
            $decorator = $this->_decoratorClass();
            return new $decorator($this->_results);
        }
        $statement = $this->getEagerLoader()
                          ->loadExternal($this, $this->execute());

        return new SalesforceResultSet($this, $statement);
    }
}
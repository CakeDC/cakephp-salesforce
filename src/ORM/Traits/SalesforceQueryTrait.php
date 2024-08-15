<?php
namespace Salesforce\ORM\Traits;

use Cake\Database\ValueBinder;
use Cake\Datasource\ResultSetInterface;
use Salesforce\ORM\SalesforceResultSet;

/**
 * SalesforceQuery Trait
 */
trait SalesforceQueryTrait
{
    public string $queryString = "";

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

        //$sql = SalesforceDatabaseQuery::sql(null, $this);

        $generator = $this->getValueBinder();
        $generator->resetCount();

        $sql = $this->getConnection()->compileQuery($this, $generator);

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
        $statement = $this->getEagerLoader()->loadExternal($this, $this->execute());

        return new SalesforceResultSet($this, $statement);
    }
}

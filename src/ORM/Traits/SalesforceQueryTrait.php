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
     * Returns the SQL representation of this object.
     *
     * This function will compile this query to make it compatible
     * with the SQL dialect that is used by the connection, This process might
     * add, remove or alter any query part or internal expression to make it
     * executable in the target platform.
     *
     * The resulting query may have placeholders that will be replaced with the actual
     * values when the query is executed, hence it is most suitable to use with
     * prepared statements.
     *
     * @param \Cake\Database\ValueBinder|null $binder Value binder that generates parameter placeholders
     * @return string
     */
    public function customSql(?ValueBinder $binder = null): string
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
     * Executes this query and returns a ResultSet object containing the results.
     * This will also set up the correct statement class in order to eager load deep
     * associations.
     *
     * @return \Cake\Datasource\ResultSetInterface
     */
    protected function customExecute(): ResultSetInterface
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

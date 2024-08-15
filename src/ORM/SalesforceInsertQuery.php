<?php

namespace Salesforce\ORM;

use Cake\Database\ValueBinder;
use Cake\Datasource\ResultSetInterface;
use Cake\ORM\Query\InsertQuery;
use Salesforce\ORM\Traits\SalesforceQueryTrait;

/**
 * Extends the base InsertQuery class to provide new methods related to association
 * loading, automatic fields selection, automatic type casting and to wrap results
 * into a specific iterator that will be responsible for hydrating results if
 * required.
 */
class SalesforceInsertQuery extends InsertQuery
{
    use SalesforceQueryTrait;

    /**
     * @inheritDoc
     */
    public function sql(?ValueBinder $binder = null): string {
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

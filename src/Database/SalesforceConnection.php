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

namespace Salesforce\Database;

use Cake\Database\Query;
use Cake\Database\StatementInterface;
use Cake\Database\ValueBinder;
use Cake\Database\Connection;

/**
 * Represents a connection with a database server.
 */
class SalesforceConnection extends Connection
{
    /**
     * {@inheritDoc}
     */
    public function compileQuery(Query $query, ValueBinder $binder): string
    {
        return $this->getDriver()
                    ->compileQuery($query, $binder)[1];
    }

    /**
     * {@inheritDoc}
     */
    public function newQuery(): Query
    {
        return new SalesforceQuery($this);
    }

    /**
     * {@inheritDoc}
     */
    public function run(Query $query): StatementInterface
    {
        $statement = $this->prepare($query);
        $query->getValueBinder()
              ->attachTo($statement);
        $statement->execute();

        return $statement;
    }

    /**
     * {@inheritDoc}
     */
    public function supportsQuoting(): bool
    {
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function quoteIdentifier($identifier): string
    {
        return $identifier;
    }
}

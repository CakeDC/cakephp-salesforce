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

namespace Salesforce\Database\Driver;

use Cake\Database\Query;
use Cake\Database\QueryCompiler;
use Cake\Database\StatementInterface;
use Cake\Database\ValueBinder;
use Salesforce\Database\Dialect\SalesforceDialectTrait;
use Salesforce\Database\Driver\SalesforceDriverTrait;
use Salesforce\Database\SalesforceQueryCompiler;
use Salesforce\Database\SalesforceQuery;
use Salesforce\Database\Statement\SalesforceStatement;
use Cake\Database\Driver;

class Salesforce extends Driver
{
    use SalesforceDialectTrait;
    use SalesforceDriverTrait;

    /**
     * Base configuration settings for MySQL driver
     *
     * @var array
     */
    protected $_baseConfig = [
        'persistent' => true,
        'host' => 'localhost',
        'username' => 'root',
        'password' => '',
        'database' => 'cake',
        'port' => '3306',
        'flags' => [],
        'encoding' => 'utf8',
        'timezone' => null,
        'init' => [],
    ];

    /**
     * Returns whether php is able to use this driver for connecting to database
     *
     * @return bool true if it is valid to use this driver
     */
    public function enabled(): bool
    {
        return true;
    }

    /**
     * Prepares a sql statement to be executed
     *
     * @param string|\Cake\Database\Query $query The query to prepare.
     * @return \Cake\Database\StatementInterface
     * @throws \ErrorException
     */
    public function prepare($query): StatementInterface
    {
        $this->connect();
        $isObject = $query instanceof SalesforceQuery;
        //$statement = $this->_connection->prepare($isObject ? $query->sql() : $query);
        $result = new SalesforceStatement($query, $this);
        if ($isObject && $query->isBufferedResultsEnabled() === false) {
            $result->bufferResults(false);
        }
        return $result;
    }

    /**
     * Establishes a connection to the database server
     *
     * @return bool true on success
     * @throws \ErrorException
     */
    public function connect(): bool
    {
        if ($this->_connection) {
            return true;
        }
        $config = $this->_config;

        $this->_connect('', $config);

        if (!empty($config['init'])) {
            $connection = $this->connection();
            foreach ((array)$config['init'] as $command) {
                $connection->exec($command);
            }
        }
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function supportsDynamicConstraints(): bool
    {
        return true;
    }

    /**
     * Transforms the passed query to this Driver's dialect and returns an instance
     * of the transformed query and the full compiled SQL string
     *
     * @param \Cake\Database\Query $query The query to compile.
     * @param \Cake\Database\ValueBinder $generator The value binder to use.
     * @return array containing 2 entries. The first entity is the transformed query
     * and the second one the compiled SQL
     */
    public function compileQuery(Query $query, ValueBinder $generator): array
    {
        $processor = $this->newCompiler();
        $translator = $this->queryTranslator($query->type());
        $query = $translator($query);
        return [$query, $processor->compile($query, $generator)];
    }

    /**
     * Returns an instance of a QueryCompiler
     *
     * @return SalesforceQueryCompiler
     */
    public function newCompiler(): QueryCompiler
    {
        return new SalesforceQueryCompiler();
    }
}

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

namespace Salesforce\Database\Statement;

use AssignmentRuleHeader;
use Cake\Database\DriverInterface;
use Cake\Database\Statement\StatementDecorator;
use Cake\Database\StatementInterface;

/**
 * Statement class meant to be used by a Mysql PDO driver
 *
 * @property \Cake\Database\Driver|\Salesforce\Database\Driver\SalesforceDriverTrait $_driver
 * @internal
 */
class SalesforceStatement extends StatementDecorator
{

    protected $last_rows_affected = 0;

    protected $last_result; //pretty sure this is awful!

    protected $last_row_returned = 0;

    private $_last_insert_id = [];

    public function __construct($statement, DriverInterface $driver)
    {
        $this->_statement = $statement;
        $this->_driver = $driver;
    }

    /**
     * {@inheritDoc}
     *
     * @throws \Exception
     */
    public function execute(?array $params = null): bool
    {
        $sql = $this->_statement->sql();
        $bindings = $this->_statement->getValueBinder()
                                     ->bindings();
        $this->_driver->errors = null;

        //intercept Update here
        if ($this->_statement->type() == 'update') {
            $results = $this->_driver->client->update([$this->_buildObjectFromUpdate($sql, $bindings)],
                $this->_statement->repository()->name);
            if (is_object($results)) {
                trigger_error('Unexpected object results', E_USER_ERROR);
            }
            $result = new \stdClass();
            if ($results[0]->success) {
                $result->size = 1;
            } else {
                $result->size = 0;
                $this->_driver->errors = $results[0]->errors;
            }
        } else {
            if ($this->_statement->type() == 'insert') {
                $object = $this->_buildObjectFromInsert($sql, $bindings);
                if (empty($object->OwnerId)) {
                    $header = new AssignmentRuleHeader(null, true);    // run the default lead assignment rule
                    $this->_driver->client->setAssignmentRuleHeader($header);
                }
                $results = $this->_driver->client->create([$object], $this->_statement->repository()->name);
                if (is_object($results)) {
                    trigger_error('Unexpected object results', E_USER_ERROR);
                }
                $result = new \stdClass();
                if ($results[0]->success) {
                    $result->size = 1;
                    $this->_last_insert_id[$this->_statement->repository()->name] = $results[0]->id;
                } else {
                    $result->size = 0;
                    $this->_driver->errors = $results[0]->errors;
                }
            } else {
                if ($this->_statement->type() == 'delete') {
                    if (count($bindings) == 1 && preg_match('/DELETE FROM .* WHERE Id = :c0/', $sql)) {
                        $id = $bindings[':c0']['value'];
                        $results = $this->_driver->client->delete([$id]);
                        if (is_object($results)) {
                            trigger_error('Unexpected object results', E_USER_ERROR);
                        }
                        $result = new \stdClass();
                        if ($results[0]->success) {
                            $result->size = 1;
                        } else {
                            $result->size = 0;
                            $this->_driver->errors = $results[0]->errors;
                        }
                    } else {
                        throw new \Exception('Unsupported delete query. Only where ID clauses are supported');
                    }
                } else {
                    // Use epilog('@queryAll') with CakePHP's query build to include deleted records.
                    if (substr($sql, -10) === ' @queryAll') {
                        $sql = substr($sql, 0, strlen($sql) - 10);
                        $result = $this->_driver->client->queryAll($this->_interpolate($sql, $bindings));
                    } else {
                        $result = $this->_driver->client->query($this->_interpolate($sql, $bindings));
                    }
                }
            }
        }

        $this->last_rows_affected = $result->size;
        $this->last_result = $result;

        return true;
    }

    /**
     * Helper function used to build an sObject from an update query.
     *
     * @param string $sql The sql query
     * @param array $bindings List of placeholder replacement values
     * @return mixed
     */
    protected function _buildObjectFromUpdate($sql, $bindings)
    {
        preg_match('/UPDATE .* SET (.*) WHERE (.*)/', $sql, $parts);
        $cleanedSQL = explode(' , ', $parts[1]);
        $cleanedSQL[] = $parts[2];
        $newSQL = ['fieldsToNull' => []];
        foreach ($cleanedSQL as $row) {
            [$fieldName, $value] = explode(' = ', $row);
            $fieldName = trim($fieldName);
            $value = $this->_replacement($bindings[trim($value)]);
            if ($value === '') {
                $newSQL['fieldsToNull'][] = $fieldName;
            } else {
                $newSQL[$fieldName] = $value;
            }
        }

        if (count($newSQL['fieldsToNull']) === 0) {
            unset($newSQL['fieldsToNull']);
        }

        //return as object
        return (object)$newSQL;
    }

    protected function _replacement($binding, $quote = false)
    {
        switch ($binding['type']) {
            case 'boolean':
                return $binding['value'] ? 'true' : 'false';
            case 'integer':
                return (int)$binding['value'];
            case 'float':
                return (float)$binding['value'];
            case 'datetime':
            case 'date':
                $ret = (string)$binding['value'];
                break;
            case 'string':
                $ret = trim($binding['value']);
                break;
            default:
                $ret = addslashes(trim($binding['value']));
                break;
        }
        if ($quote) {
            return "'$ret'";
        }
        return $ret;
    }

    /**
     * Helper function used to build an sObject from an insert query.
     *
     * @param string $sql The sql query
     * @param array $bindings List of placeholder replacement values
     * @return mixed
     */
    protected function _buildObjectFromInsert($sql, $bindings)
    {
        preg_match('/\((.*)\) VALUES \((.*)\)/', $sql, $blobs);
        $fields = explode(', ', $blobs[1]);
        $placeholders = explode(', ', $blobs[2]);
        $newSQL = [];
        foreach ($fields as $key => $field) {
            $newSQL[$field] = $this->_replacement($bindings[$placeholders[$key]]);
        }

        //remove empty / null values
        $newSQL = array_filter($newSQL, 'strlen');

        //return as object
        return (object)$newSQL;
    }

    /**
     * Helper function used to replace query placeholders by the real
     * params used to execute the query.
     *
     * @param string $sql The sql query
     * @param array $bindings List of placeholder replacement values
     * @return string
     */
    protected function _interpolate($sql, $bindings)
    {
        foreach ($bindings as $binding) {
            $sql = preg_replace('/:' . $binding['placeholder'] . '\b/i', $this->_replacement($binding, true), $sql);
        }

        return $sql;
    }

    public function rowCount(): int
    {
        return $this->last_rows_affected;
    }

    /**
     * Returns the next row for the result set after executing this statement.
     * Rows can be fetched to contain columns as names or positions. If no
     * rows are left in result set, this method will return false
     *
     * ### Example:
     *
     * ```
     *  $statement = $connection->prepare('SELECT id, title from articles');
     *  $statement->execute();
     *  print_r($statement->fetch('assoc')); // will show ['id' => 1, 'title' => 'a title']
     * ```
     *
     * @param string $type 'num' for positional columns, 'assoc' for named columns
     * @return mixed Result array containing columns and values or false if no results
     * are left
     */
    public function fetch($type = 'num')
    {
        if ($type === 'num') {
            $result = (array)$this->last_result->records[$this->last_row_returned];
        }
        if ($type === 'assoc') {
            $result = (array)$this->last_result->records[$this->last_row_returned];
        }

        $this->last_row_returned++;
        return $result;
    }

    /**
     * Returns the error code for the last error that occurred when executing this statement.
     *
     * @return int|string
     */
    public function errorCode()
    {
        if (!empty($this->_driver->errors)) {
            return $this->_driver->errors[0]->statusCode;
        }
        return '00000';
    }

    /**
     * Returns the error information for the last error that occurred when executing
     * this statement.
     *
     * @return array
     */
    public function errorInfo(): array
    {
        if (!empty($this->_driver->errors)) {
            return $this->_driver->errors[0]->message;
        }
        return [
            'Salesforce Datasource doesnt produce PDO error codes - exceptions are usually thrown'
        ];
    }

    /**
     * Closes a cursor in the database, freeing up any resources and memory
     * allocated to it. In most cases you don't need to call this method, as it is
     * automatically called after fetching all results from the result set.
     *
     * @return void
     */
    public function closeCursor(): void
    {
    }

    /**
     * Returns the latest primary inserted using this statement.
     *
     * @param string|null $table table name or sequence to get last insert value from
     * @param string|null $column the name of the column representing the primary key
     * @return string
     */
    public function lastInsertId(?string $table = null, ?string $column = null)
    {
        return $this->_last_insert_id[$table];
    }
}

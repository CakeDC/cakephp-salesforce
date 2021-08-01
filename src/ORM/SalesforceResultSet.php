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

use Cake\ORM\Query;
use Cake\ORM\ResultSet;


/**
 * Represents the results obtained after executing a query for a specific table
 * This object is responsible for correctly nesting result keys reported from
 * the query, casting each field to the correct type and executing the extra
 * queries required for eager loading external associations.
 *
 */
class SalesforceResultSet extends ResultSet
{
    /**
     * {@inheritDoc}
     */
    public function first()
    {
        foreach ($this as $result) {
            if ($this->_statement && !$this->_useBuffering) {
                $this->_statement->closeCursor();
            }
            $result = new $this->_entityClass($result);
            $result->clean();

            return $result;
        }

        return null;
    }

    /**
     * {@inheritDoc}
     */
    protected function _calculateColumnMap(Query $query): void
    {
        $map = []; //My one
        foreach ($query->clause('select') as $key => $field) {
            $key = trim($key, '"`[]');
            if (strpos($key, '__') > 0) {
                $parts = explode('__', $key, 2);
                $map[$parts[0]][$parts[1]] = $parts[1];
            } else {
                $map[$this->_defaultAlias][$key] = $key;
            }
        }

        foreach ($this->_matchingMap as $alias => $assoc) {
            if (!isset($map[$alias])) {
                continue;
            }
            $this->_matchingMapColumns[$alias] = $map[$alias];
            unset($map[$alias]);
        }

        $this->_map = $map;
    }

    /**
     * {@inheritDoc}
     */
    protected function _fetchResult()
    {
        if (!$this->_statement) {
            return false;
        }

        $row = $this->_statement->fetch('assoc');
        if ($row === false) {
            return $row;
        }
        return $this->_groupResult($row);
    }

    /**
     * {@inheritDoc}
     */
    protected function _groupResult($row)
    {
        return $row;
    }
}

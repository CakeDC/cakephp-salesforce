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
use Cake\Database\QueryCompiler;
use Cake\Database\ValueBinder;

/**
 * Responsible for compiling a Query object into its SQL representation
 *
 * @internal
 */
class SalesforceQueryCompiler extends QueryCompiler
{
    /**
     * {@inheritDoc}
     */
    public function compile(Query $query, ValueBinder $binder): string
    {
        $sql = '';
        $type = $query->type();
        $query->traverseParts($this->_sqlCompiler($sql, $query, $binder), $this->{'_' . $type . 'Parts'});

        return $sql;
    }

    /**
     * {@inheritDoc}
     */
    protected function _buildSelectPart(array $parts, Query $query, ValueBinder $binder): string
    {
        $driver = $query->getConnection()
                        ->getDriver();
        $select = 'SELECT %s%s%s';
        if ($this->_orderedUnion && $query->clause('union')) {
            $select = '(SELECT %s%s%s';
        }
        $distinct = $query->clause('distinct');
        $modifiers = $query->clause('modifier') ?: null;

        $normalized = [];
        $parts = $this->_stringifyExpressions($parts, $binder);
        foreach ($parts as $k => $p) {
            if (!is_numeric($k)) {
                $p = $p; //Leave it alone
            }
            $normalized[] = $p;
        }

        if ($distinct === true) {
            $distinct = 'DISTINCT ';
        }

        if (is_array($distinct)) {
            $distinct = $this->_stringifyExpressions($distinct, $binder);
            $distinct = sprintf('DISTINCT ON (%s) ', implode(', ', $distinct));
        }
        if ($modifiers !== null) {
            $modifiers = $this->_stringifyExpressions($modifiers, $binder);
            $modifiers = implode(' ', $modifiers) . ' ';
        }

        return sprintf($select, $distinct, $modifiers, implode(', ', $normalized));
    }
}
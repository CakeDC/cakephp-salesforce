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

use \ArrayObject;
use Cake\Datasource\EntityInterface;
use Cake\ORM\Marshaller;
use Cake\ORM\Query;
use Cake\ORM\Query\DeleteQuery;
use Cake\ORM\Query\InsertQuery;
use Cake\ORM\Query\SelectQuery;
use Cake\ORM\Query\UpdateQuery;
use Cake\ORM\Table;

class SalesforceTable extends Table
{
    /**
     * {@inheritDoc}
     */
    public function query(): Query
    {
        return new SalesforceQuery($this->getConnection(), $this);
    }

    /**
     * Creates a new DeleteQuery instance for a table.
     *
     * @return \Cake\ORM\Query\DeleteQuery
     */
    public function deleteQuery(): DeleteQuery
    {
        return new SalesforceDeleteQuery($this->getConnection(), $this);
    }

    /**
     * Creates a new InsertQuery instance for a table.
     *
     * @return \Cake\ORM\Query\InsertQuery
     */
    public function insertQuery(): InsertQuery
    {
        return new SalesforceInsertQuery($this->getConnection(), $this);
    }

    /**
     * Creates a new SelectQuery instance for a table.
     *
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function selectQuery(): SelectQuery
    {
        return new SalesforceSelectQuery($this->getConnection(), $this);
    }

    /**
     * Creates a new UpdateQuery instance for a table.
     *
     * @return \Cake\ORM\Query\UpdateQuery
     */
    public function updateQuery(): UpdateQuery
    {
        return new SalesforceUpdateQuery($this->getConnection(), $this);
    }

    /**
     * {@inheritDoc}
     */
    public function exists($conditions): bool
    {
        return (bool)count($this->find()
            ->select(['Id'])
            ->where($conditions)
            ->limit(1)
            ->enableHydration(false)
            ->toArray());
    }

    /**
     * {@inheritDoc}
     */
    public function save(EntityInterface $entity, $options = [])
    {
        $options = new ArrayObject($options + [
                'atomic' => true,
                'associated' => true,
                'checkRules' => true,
                'checkExisting' => true,
                '_primary' => true
            ]);

        if (is_array($entity)) {
            $entity = $this->newEntity($entity);
        }
        if ($entity->getErrors()) {
            return false;
        }

        if ($entity->isNew() === false && !$entity->isDirty()) {
            return $entity;
        }

        $success = $this->_processSave($entity, $options);

        if ($success) {
            if ($options['atomic'] || (!$options['atomic'] && $options['_primary'])) {
                $this->dispatchEvent('Model.afterSaveCommit', compact('entity', 'options'));
            }

            if ($options['atomic'] || $options['_primary']) {
                $entity->setNew(false);
                $entity->setSource($this->getRegistryAlias());
            }
        } else {
            $errors = $this->getConnection()
                           ->getDriver()->errors[0];
            if (!empty($errors->fields)) {
                $field = $errors->fields[0];
            } else {
                // For lack of anything better...
                $field = 'id';
            }
            $entity->setError($field, $errors->message);
        }

        return $success;
    }

    /**
     * {@inheritDoc}
     */
    public function newEntity(array $data, array $options = []): EntityInterface
    {
        if ($data === null) {
            $class = $this->getEntityClass();

            return new $class([], ['source' => $this->getRegistryAlias()]);
        }
        if (!isset($options['associated'])) {
            $options['associated'] = $this->_associations->keys();
        }
        $marshaller = $this->marshaller();

        return $marshaller->one($data, $options);
    }

    /**
     * {@inheritDoc}
     */
    public function marshaller(): Marshaller
    {
        return new SalesforceMarshaller($this);
    }
}

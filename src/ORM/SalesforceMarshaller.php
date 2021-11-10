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

use Cake\Database\TypeFactory;
use Cake\Datasource\EntityInterface;
use Cake\Datasource\InvalidPropertyInterface;
use Cake\ORM\Marshaller;
use Cake\ORM\PropertyMarshalInterface;
use Salesforce\Database\SalesforceType;

/**
 * Contains logic to convert array data into entities.
 *
 * Useful when converting request data into entities.
 *
 * @see \Cake\ORM\Table::newEntity()
 * @see \Cake\ORM\Table::newEntities()
 * @see \Cake\ORM\Table::patchEntity()
 * @see \Cake\ORM\Table::patchEntities()
 */
class SalesforceMarshaller extends Marshaller
{

    /**
     * {@inheritDoc}
     */
    public function one(array $data, array $options = []): EntityInterface
    {
        list($data, $options) = $this->_prepareDataAndOptions($data, $options);

        $primaryKey = (array)$this->_table->getPrimaryKey();
        $entityClass = $this->_table->getEntityClass();
        /** @var EntityInterface $entity */
        $entity = new $entityClass();
        $entity->setSource($this->_table->getRegistryAlias());

        if (isset($options['accessibleFields'])) {
            foreach ((array)$options['accessibleFields'] as $key => $value) {
                $entity->setAccess($key, $value);
            }
        }
        $errors = $this->_validate($data, $options, true);

        $options['isMerge'] = false;
        $propertyMap = $this->_buildPropertyMap($data, $options);
        $properties = [];
        foreach ($data as $key => $value) {
            if (!empty($errors[$key])) {
                if ($entity instanceof InvalidPropertyInterface) {
                    $entity->setInvalidField($key, $value);
                }
                continue;
            }

            if ($value === '' && in_array($key, $primaryKey, true)) {
                // Skip marshalling '' for pk fields.
                continue;
            } elseif (isset($propertyMap[$key])) {
                $properties[$key] = $propertyMap[$key]($value, $entity);
            } else {
                $columnType = $this->_table->getSchema()
                                           ->getColumnType($key);
                if ($columnType) {
                    $converter = SalesforceType::build($columnType);
                    $properties[$key] = $converter->marshal($value);
                }
            }
        }

        if (!isset($options['fieldList'])) {
            $entity->set($properties);
            $entity->setErrors($errors);

            return $entity;
        }

        foreach ((array)$options['fieldList'] as $field) {
            if (array_key_exists($field, $properties)) {
                $entity->set($field, $properties[$field]);
            }
        }

        $entity->setErrors($errors);

        return $entity;
    }

    protected function _buildPropertyMap(array $data, array $options): array
    {
        $map = [];
        $schema = $this->_table->getSchema();

        // Is a concrete column?
        foreach (array_keys($data) as $prop) {
            $prop = (string)$prop;
            $columnType = $schema->getColumnType($prop);
            if ($columnType) {
                $map[$prop] = function ($value, $entity) use ($columnType) {
                    return SalesforceType::build($columnType)->marshal($value);
                };
            }
        }

        // Map associations
        if (!isset($options['associated'])) {
            $options['associated'] = [];
        }
        $include = $this->_normalizeAssociations($options['associated']);
        foreach ($include as $key => $nested) {
            if (is_int($key) && is_scalar($nested)) {
                $key = $nested;
                $nested = [];
            }
            // If the key is not a special field like _ids or _joinData
            // it is a missing association that we should error on.
            if (!$this->_table->hasAssociation($key)) {
                if (substr($key, 0, 1) !== '_') {
                    throw new InvalidArgumentException(sprintf(
                        'Cannot marshal data for "%s" association. It is not associated with "%s".',
                        (string)$key,
                        $this->_table->getAlias()
                    ));
                }
                continue;
            }
            $assoc = $this->_table->getAssociation($key);

            if (isset($options['forceNew'])) {
                $nested['forceNew'] = $options['forceNew'];
            }
            if (isset($options['isMerge'])) {
                $callback = function ($value, $entity) use ($assoc, $nested) {
                    /** @var \Cake\Datasource\EntityInterface $entity */
                    $options = $nested + ['associated' => [], 'association' => $assoc];

                    return $this->_mergeAssociation($entity->get($assoc->getProperty()), $assoc, $value, $options);
                };
            } else {
                $callback = function ($value, $entity) use ($assoc, $nested) {
                    $options = $nested + ['associated' => []];

                    return $this->_marshalAssociation($assoc, $value, $options);
                };
            }
            $map[$assoc->getProperty()] = $callback;
        }

        $behaviors = $this->_table->behaviors();
        foreach ($behaviors->loaded() as $name) {
            $behavior = $behaviors->get($name);
            if ($behavior instanceof PropertyMarshalInterface) {
                $map += $behavior->buildMarshalMap($this, $map, $options);
            }
        }

        return $map;
    }
}

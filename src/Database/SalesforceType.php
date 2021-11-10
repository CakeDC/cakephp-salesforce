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

use Cake\Database\Type;
use Cake\Database\TypeInterface;
use InvalidArgumentException;

/**
 * Encapsulates all conversion functions for values coming from database into PHP and
 * going from PHP into database.
 */
class SalesforceType extends Type
{

    /**
     * List of supported database types. A human readable
     * identifier is used as key and a complete namespaced class name as value
     * representing the class that will do actual type conversions.
     *
     * @var array
     */
    protected static $_typesMap = [
        'biginteger' => 'Cake\Database\Type\IntegerType',
        'binary' => 'Cake\Database\Type\BinaryType',
        'boolean' => 'Cake\Database\Type\BoolType',
        'date' => 'Salesforce\Database\Type\SalesforceDateType',
        'datetime' => 'Salesforce\Database\Type\SalesforceDateTimeType',
        'decimal' => 'Cake\Database\Type\FloatType',
        'float' => 'Cake\Database\Type\FloatType',
        'integer' => 'Cake\Database\Type\IntegerType',
        'string' => 'Salesforce\Database\Type\SalesforceStringType',
        'text' => 'Cake\Database\Type\StringType',
        'time' => 'Cake\Database\Type\TimeType',
        'timestamp' => 'Cake\Database\Type\DateTimeType',
        'uuid' => 'Cake\Database\Type\UuidType'
    ];

    /**
     * List of basic type mappings, used to avoid having to instantiate a class
     * for doing conversion on these
     *
     * @var array
     * @deprecated 3.1 All types will now use a specific class
     */
    protected static $_basicTypes = [
        'string' => ['callback' => ['\Salesforce\Database\Type', 'strval']],
        'text' => ['callback' => ['\Cake\Database\Type', 'strval']],
        'boolean' => [
            'callback' => ['\Cake\Database\Type', 'boolval']
        ],
    ];

    /**
     * Contains a map of type object instances to be reused if needed.
     *
     * @var \Cake\Database\TypeInterface[]
     */
    protected static $_builtSalesforceTypes = [];

    /**
     * {@inheritDoc}
     */
    public static function build(string $name): TypeInterface
    {
        //force rebuild of string and dates type
        if (!in_array($name, ['string', 'date', 'datetime'])) {
            if (isset(static::$_builtSalesforceTypes[$name])) {
                return static::$_builtSalesforceTypes[$name];
            }
            if (!isset(static::$_typesMap[$name])) {
                throw new InvalidArgumentException(sprintf('Unknown type "%s"', $name));
            }
        }

        return static::$_builtSalesforceTypes[$name] = new static::$_typesMap[$name]($name);
    }


    /**
     * Returns an arrays with all the mapped type objects, indexed by name.
     *
     * @return \Cake\Database\TypeInterface[]
     */
    public static function buildAll(): array
    {
        $result = [];
        foreach (static::$_typesMap as $name => $type) {
            $result[$name] = static::$_builtSalesforceTypes[$name] ?? static::build($name);
        }

        return $result;
    }

    /**
     * Set TypeInterface instance capable of converting a type identified by $name
     *
     * @param string $name The type identifier you want to set.
     * @param \Cake\Database\TypeInterface $instance The type instance you want to set.
     * @return void
     */
    public static function set(string $name, TypeInterface $instance): void
    {
        static::$_builtSalesforceTypes[$name] = $instance;
        static::$_typesMap[$name] = get_class($instance);
    }

    /**
     * Registers a new type identifier and maps it to a fully namespaced classname.
     *
     * @param string $type Name of type to map.
     * @param string $className The classname to register.
     * @return void
     * @psalm-param class-string<\Cake\Database\TypeInterface> $className
     */
    public static function map(string $type, string $className): void
    {
        static::$_typesMap[$type] = $className;
        unset(static::$_builtSalesforceTypes[$type]);
    }

    /**
     * Set type to classname mapping.
     *
     * @param string[] $map List of types to be mapped.
     * @return void
     * @psalm-param array<string, class-string<\Cake\Database\TypeInterface>> $map
     */
    public static function setMap(array $map): void
    {
        static::$_typesMap = $map;
        static::$_builtSalesforceTypes = [];
    }

    /**
     * Get mapped class name for given type or map array.
     *
     * @param string|null $type Type name to get mapped class for or null to get map array.
     * @return string[]|string|null Configured class name for given $type or map array.
     */
    public static function getMap(?string $type = null)
    {
        if ($type === null) {
            return static::$_typesMap;
        }

        return static::$_typesMap[$type] ?? null;
    }

    /**
     * Clears out all created instances and mapped types classes, useful for testing
     *
     * @return void
     */
    public static function clear(): void
    {
        static::$_typesMap = [];
        static::$_builtSalesforceTypes = [];
    }
}

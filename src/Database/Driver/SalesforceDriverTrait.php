<?php

namespace Salesforce\Database\Driver;

use Cake\Database\Query;
use Cake\Cache\Cache;
use Cake\Database\StatementInterface;
use Cake\Log\Log;
use PDO;

/**
 * SF driver trait
 */
trait SalesforceDriverTrait
{
    public $config;

    /**
     * @var \SforceEnterpriseClient
     */
    public $client;

    /**
     * @var null|\PDO $connection The PDO connection instance
     */
    protected $_connection;

    /**
     * {@inheritDoc}
     */
    public function connection($connection = null)
    {
        if ($connection !== null) {
            $this->_connection = $connection;
        }
        return $this->_connection;
    }

    /**
     * {@inheritDoc}
     */
    public function disconnect(): void
    {
        $this->_connection = null;
    }

    /**
     * {@inheritDoc}
     */
    public function prepare($query): StatementInterface
    {
        $this->connect();
        $isObject = $query instanceof Query;
        $statement = $this->_connection->prepare($isObject ? $query->sql() : $query);
        return new SalesforceStatement($statement, $this);
    }

    /**
     * {@inheritDoc}
     */
    public function beginTransaction(): bool
    {
        $this->connect();
        if ($this->_connection->inTransaction()) {
            return true;
        }
        return $this->_connection->beginTransaction();
    }

    /**
     * {@inheritDoc}
     */
    public function commitTransaction(): bool
    {
        $this->connect();
        if (!$this->_connection->inTransaction()) {
            return false;
        }

        return $this->_connection->commit();
    }

    /**
     * {@inheritDoc}
     */
    public function rollbackTransaction(): bool
    {
        if (!$this->_connection->inTransaction()) {
            return false;
        }

        return $this->_connection->rollback();
    }

    /**
     * {@inheritDoc}
     */
    public function quote($value, $type = PDO::PARAM_STR): string
    {
        $this->connect();

        return $this->_connection->quote($value, $type);
    }

    /**
     * {@inheritDoc}
     */
    public function lastInsertId(?string $table = null, ?string $column = null)
    {
        $this->connect();
        return $this->_connection->lastInsertId($table);
    }

    /**
     * Checks if the driver supports quoting, as PDO_ODBC does not support it.
     *
     * @return bool
     */
    public function supportsQuoting(): bool
    {
        return false;
    }

    /**
     * Establishes a connection to the salesforce server
     *
     * @param string $dsn A Driver-specific PDO-DSN
     * @param array $config configuration to be used for creating connection
     * @return bool true on success
     * @throws \ErrorException
     */
    protected function _connect(string $dsn, array $config): bool
    {
        $this->config = $config;

        if (empty($this->config['my_wsdl'])) {
            throw new \ErrorException ("A WSDL needs to be provided");
        } else {
            $wsdl = CONFIG . DS . $this->config['my_wsdl'];
        }

        $mySforceConnection = new \SforceEnterpriseClient();
        $mySforceConnection->createConnection($wsdl);

        $cache_key = $config['name'] . '_login';
        $sflogin = (array)Cache::read($cache_key, 'salesforce');

        if (!empty($sflogin['sessionId'])) {
            $mySforceConnection->setSessionHeader($sflogin['sessionId']);
            $mySforceConnection->setEndPoint($sflogin['serverUrl']);
        } else {
            try {
                $mylogin = $mySforceConnection->login($this->config['username'], $this->config['password']);
                $sflogin = array('sessionId' => $mylogin->sessionId, 'serverUrl' => $mylogin->serverUrl);
                Cache::write($cache_key, $sflogin, 'salesforce');
            } catch (\Exception $e) {
                Log::write('error', "Error logging into salesforce - Salesforce down?");
                Log::write('error', "Username: " . $this->config['username']);
                Log::write('error', "Password: " . $this->config['password']);
            }
        }

        $this->client = $mySforceConnection;
        $this->connected = true;
        return $this->connected;
    }
}

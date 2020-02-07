<?php

namespace Salesforce\Database\Driver;

use Cake\Database\Query;
use Cake\Cache\Cache;
/**
 * SF driver trait
 */
trait SalesforceDriverTrait
{
    protected $_connection;
    public $config;

	/**
	 * @var \SforceEnterpriseClient
	 */
    public $client;

	/**
	 * Establishes a connection to the salesforce server
	 *
	 * @param string $dsn A Driver-specific PDO-DSN
	 * @param array $config configuration to be used for creating connection
	 * @return bool true on success
	 * @throws \ErrorException
	 */
    protected function _connect($dns, array $config)
    {
        $this->config = $config;

        if (empty($this->config['my_wsdl'])) {
            throw new \ErrorException ("A WSDL needs to be provided");
        } else {
            $wsdl = CONFIG .DS  . $this->config['my_wsdl'];
        }

        $mySforceConnection = new \SforceEnterpriseClient();
        $mySforceConnection->createConnection($wsdl);

        $cache_key = $config['name'] . '_login';
        $sflogin = (array)Cache::read($cache_key, 'salesforce');

        if(!empty($sflogin['sessionId'])) {
            $mySforceConnection->setSessionHeader($sflogin['sessionId']);
            $mySforceConnection->setEndPoint($sflogin['serverUrl']);
        } else {
            try{
                $mylogin = $mySforceConnection->login($this->config['username'], $this->config['password']);
                $sflogin = array('sessionId' => $mylogin->sessionId, 'serverUrl' => $mylogin->serverUrl);
                Cache::write($cache_key, $sflogin, 'salesforce');
            } catch (\Exception $e) {
                \Cake\Cake\Log::write('error', "Error logging into salesforce - Salesforce down?");
                \Cake\Cake\Log::write('error', "Username: " . $this->config['username']);
                \Cake\Cake\Log::write('error', "Password: " . $this->config['password']);
            }
        }

        $this->client = $mySforceConnection;
        $this->connected = true;
        return $this->connected;
    }

    /**
     * Returns correct connection resource or object that is internally used
     * If first argument is passed, it will set internal connection object or
     * result to the value passed
     *
     * @param null|\PDO $connection The PDO connection instance.
     * @return mixed connection object used internally
     */
    public function connection($connection = null)
    {
        if ($connection !== null) {
            $this->_connection = $connection;
        }
        return $this->_connection;
    }

    /**
     * Disconnects from database server
     *
     * @return void
     */
    public function disconnect()
    {
        $this->_connection = null;
    }

    /**
     * Prepares a sql statement to be executed
     *
     * @param string|\Cake\Database\Query $query The query to turn into a prepared statement.
     * @return \Cake\Database\StatementInterface
     */
    public function prepare($query)
    {
        $this->connect();
        $isObject = $query instanceof Query;
        $statement = $this->_connection->prepare($isObject ? $query->sql() : $query);
        return new SalesforceStatement($statement, $this);
    }

    /**
     * Starts a transaction
     *
     * @return bool true on success, false otherwise
     */
    public function beginTransaction()
    {
        $this->connect();
        if ($this->_connection->inTransaction()) {
            return true;
        }
        return $this->_connection->beginTransaction();
    }

    /**
     * Commits a transaction
     *
     * @return bool true on success, false otherwise
     */
    public function commitTransaction()
    {
        $this->connect();
        if (!$this->_connection->inTransaction()) {
            return false;
        }
        return $this->_connection->commit();
    }

    /**
     * Rollsback a transaction
     *
     * @return bool true on success, false otherwise
     */
    public function rollbackTransaction()
    {
        if (!$this->_connection->inTransaction()) {
            return false;
        }
        return $this->_connection->rollback();
    }

    /**
     * Returns a value in a safe representation to be used in a query string
     *
     * @param mixed $value The value to quote.
     * @param string $type Type to be used for determining kind of quoting to perform
     * @return string
     */
    public function quote($value, $type)
    {
        $this->connect();
        return $this->_connection->quote($value, $type);
    }

    /**
     * Returns last id generated for a table or sequence in database
     *
     * @param string|null $table table name or sequence to get last insert value from
     * @param string|null $column the name of the column representing the primary key
     * @return string|int
     */
    public function lastInsertId($table = null, $column = null)
    {
        $this->connect();
        return $this->_connection->lastInsertId($table);
    }

    /**
     * Checks if the driver supports quoting, as PDO_ODBC does not support it.
     *
     * @return bool
     */
    public function supportsQuoting()
    {
        return false;
    }
}

<?php
namespace Salesforce\Model\Table;

use Cake\Datasource\EntityInterface;
use Salesforce\Model\Entity\Salesforce;
use Salesforce\ORM\SalesforceQuery;
use Salesforce\ORM\SalesforceTable;
use Cake\Cache\Cache;
use Cake\Log\LogTrait;

/**
 * Questions Model
 *
 * @property \Cake\ORM\Association\HasMany $PathwaysAnswers
 * @property \Cake\ORM\Association\HasMany $QuestionParts
 * @property \Cake\ORM\Association\BelongsToMany $Pathways
 */
class SalesforcesTable extends SalesforceTable
{
    use LogTrait;

    private $_fields = [];

    public static function defaultConnectionName() {
        return 'salesforce';
    }

    /**
     * Initialize method
     *
     * @param  array $config The configuration for the Table.
     * @return void
     * @throws \Exception
     */
    public function initialize(array $config)
    {
        parent::initialize($config);

        $this->table(false);
        $this->displayField('Id');
        $this->primaryKey('Id');

        if(!empty($config['connection']->config()['my_wsdl'])) {
            $wsdl = CONFIG . DS  . $config['connection']->config()['my_wsdl'];
        } else {
            throw new \Exception('You need to provide a WSDL');
        }

        $mySforceConnection = new \SforceEnterpriseClient();
        $mySforceConnection->createConnection($wsdl);

        $cache_key = $config['connection']->config()['name'] . '_login';
        $sflogin = (array)Cache::read($cache_key, 'salesforce');
        if(!empty($sflogin['sessionId'])) {
            $mySforceConnection->setSessionHeader($sflogin['sessionId']);
            $mySforceConnection->setEndpoint($sflogin['serverUrl']);
        } else {
            try{
                $mylogin = $mySforceConnection->login($config['connection']->config()['username'],$config['connection']->config()['password']);
                $sflogin = ['sessionId' => $mylogin->sessionId, 'serverUrl' => $mylogin->serverUrl];
                Cache::write($cache_key, $sflogin, 'salesforce');
            } catch (\Exception $e) {
                $this->log('Error logging into salesforce from Table - Salesforce down?');
                throw $e;
            }
        }

        if (!$sObject = Cache::read($this->name.'_sObject', 'salesforce')) {
            $sObject = $mySforceConnection->describeSObject($this->name);
            Cache::write($this->name.'_sObject', $sObject, 'salesforce');
        }

        $this->_fields = Cache::remember($this->name.'_schema', function () use ($sObject) {
            $fields = [];

            foreach ($sObject->fields as $field) {
                if(substr($field->soapType,0,3) != 'ens') { //we dont want type of ens
                    if(substr($field->soapType,4) == 'int') {
                        $type_name = 'integer';
                    } elseif (substr($field->soapType,4) == 'double') {
                        $type_name = 'float';
                    } elseif (substr($field->soapType,4) == 'boolean') {
                        $type_name = 'boolean';
                    } elseif (substr($field->soapType,4) == 'dateTime') {
                        $type_name = 'datetime';
                    } elseif (substr($field->soapType,4) == 'date') {
                        $type_name = 'date';
                    } else {
                        $type_name = 'string';
                    }
                    if($field->createable || $field->name == 'Id') {
                        $fields['creatable'][$field->name] = ['type' => $type_name, 'length' => $field->length, 'null' => $field->nillable];
                    }
                    if($field->updateable || $field->name == 'Id') {
                        $fields['updatable'][$field->name] = ['type' => $type_name, 'length' => $field->length, 'null' => $field->nillable];
                    }
                    $fields['selectable'][$field->name] = ['type' => $type_name, 'length' => $field->length, 'null' => $field->nillable];
                }
            }

            return $fields;
        }, 'salesforce');

        //Cache select fields right away as most likely need them immediately
        $this->schema($this->_fields['selectable']);
    }

    /**
     * {@inheritDoc}
     */
    public function newEntity($data = null, array $options = []) {
        $this->schema($this->_fields['creatable']);
        return parent::newEntity($data, $options);
    }

    /**
     * {@inheritDoc}
     */
    public function newEntities(array $data, array $options = []) {
        $this->schema($this->_fields['creatable']);
        return parent::newEntities($data, $options);
    }

    /**
     * {@inheritDoc}
     */
    public function patchEntity(EntityInterface $entity, array $data, array $options = []) {
        $this->schema($this->_fields['updatable']);
        return parent::patchEntity($entity, $data, $options);
    }

    /**
     * {@inheritDoc}
     */
    public function patchEntities($entities, array $data, array $options = []) {
        $this->schema($this->_fields['updatable']);
        return parent::patchEntities($entities, $data, $options);
    }

    public function beforeFind($event, $query, $options, $primary) {
        $this->schema($this->_fields['selectable']);
    }

    public function beforeSave($event, $entity, $options) {
        if($options['atomic']) {
            throw new \Exception('Salesforce API does not support atomic transactions; set atomic to false.');
        }
        if ($entity->isNew()) {
            $this->schema($this->_fields['creatable']);
        } else {
            $this->schema($this->_fields['updatable']);
        }
    }

    public function toCompositeSObject(EntityInterface $entity, $includeId = false)
    {
        $object = new CompositeSObject($this->getTable());
        foreach ($entity->extract($entity->getDirty()) as $name => $value) {
            $object->{$name} = $value;
        }
        if ($includeId) {
            $object->Id = $entity['Id'];
        }

        return $object;
    }

    /**
     * @param EntityInterface[] $records
     * @return CollectionResponse[]
     */
    public function createBulk(array $records): array
    {
        $items = collection($records)->map(function($item) { return $this->toCompositeSObject($item);})->toArray();
        $request = new CollectionRequest($items, true);

        $client = $this->getConnection()->getDriver()->getRestClient();
        $result = $client->getCompositeClient()->create($request);

        foreach ($result as $id => $resultItem) {
            if ($resultItem->isSuccess()) {
                $records[$id]->set('Id', $resultItem->getId());
                $records[$id]->clean();
            }
        }

        return $result;
    }

    /**
     * @param EntityInterface[] $records
     * @return CollectionResponse[]
     */
    public function updateBulk(array $records): array
    {
        $items = collection($records)->map(function($item) {
            return $this->toCompositeSObject($item, true);
        })->toArray();

        return $this->getConnection()
                    ->getDriver()
                    ->getRestClient()
                    ->getCompositeClient()
                    ->update(new CollectionRequest($items, true));
    }

    /**
     * @param array $ids
     * @return CollectionResponse[]
     */
    public function readBulk(array $ids, $fields = null): array
    {
        $this->schema($this->_fields['selectable']);
        if ($fields === null) {
            $fields = array_keys($this->_fields['selectable']);
        }
        $response = $this->getConnection()
                       ->getDriver()
                       ->getRestClient()
                       ->getCompositeClient()
                       ->read($this->getTable(), $ids, $fields);

        return collection($response)->map(function (CompositeSObject $item) {
            return $this->marshaller()->one($item->getFields());
        })->toArray();
    }

    /**
     * @param array $ids
     * @return CollectionResponse[]
     */
    public function deleteBulk(array $ids): array
    {
        $items = collection($ids)->map(function($id) {
            $object = new CompositeSObject($this->getTable());
            $object->Id = $id;

            return $object;
        })->toArray();

        return $this->getConnection()
                    ->getDriver()
                    ->getRestClient()
                    ->getCompositeClient()
                    ->delete(new CollectionRequest($items, true));
    }

}

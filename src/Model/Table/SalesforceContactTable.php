<?php

namespace Salesforce\Model\Table;

use Salesforce\Model\Entity\Salesforce;

class SalesforceContactTable extends SalesforcesTable
{
    public $name = "Contact";

    /**
     * {@inheritDoc}
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('Contact');
        $this->setDisplayField('Name');
        $this->setPrimaryKey('Id');
    }
}

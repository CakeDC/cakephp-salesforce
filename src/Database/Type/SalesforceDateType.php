<?php
declare(strict_types=1);

namespace Salesforce\Database\Type;

use Cake\Database\Type\DateType;

/**
 * Date type converter.
 *
 * Use to convert date instances to strings & back.
 */
class SalesforceDateType extends DateType
{

    /**
     * The DateTime formats allowed by `marshal()`.
     *
     * @var array
     */
    protected $_marshalFormats = [
        'Y-m-d H:i',
        'Y-m-d H:i:s',
        'Y-m-d\TH:i',
        'Y-m-d\TH:i:s',
        'Y-m-d\TH:i:sP',
    ];

}

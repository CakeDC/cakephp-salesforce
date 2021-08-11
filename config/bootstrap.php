<?php
use Cake\Cache\Cache;

if (Cache::getConfig('salesforce') === null) {
    Cache::setConfig('salesforce', [
        'className' => 'Cake\Cache\Engine\FileEngine',
        'duration' => '+1 hours',
        'probability' => 100,
        'path' => CACHE . 'salesforce' . DS,
    ]);
}

<?php

declare(strict_types=1);

namespace Jengo\Search\Config;

use CodeIgniter\Config\BaseService;
use Jengo\Search\Support\SearchManager;

class Services extends BaseService
{
    /**
     * SearchManager coordinator service.
     */
    public static function search(bool $getShared = true): SearchManager
    {
        if ($getShared) {
            return static::getSharedInstance('search');
        }

        return new SearchManager();
    }
}

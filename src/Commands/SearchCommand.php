<?php

declare(strict_types=1);

namespace Jengo\Search\Commands;

use Jengo\Base\Commands\Core\AbstractMasterCommand;

class SearchCommand extends AbstractMasterCommand
{
    protected $group       = 'Jengo';
    protected $name        = 'jengo:search';
    protected $description = 'Search engine management (import, flush, sync-settings, status).';
    protected $usage       = 'jengo:search <variant> [options]';

    protected string $variantPath = 'Commands/Variants';
}

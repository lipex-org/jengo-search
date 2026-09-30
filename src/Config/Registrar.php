<?php

declare(strict_types=1);

namespace Jengo\Search\Config;

class Registrar
{
    /**
     * Register Jengo Search installer with Base installer hub.
     */
    public static function Jengo(): array
    {
        return [
            'installers' => [
                'search' => \Jengo\Search\Installers\SearchInstaller::class,
            ],
        ];
    }
}

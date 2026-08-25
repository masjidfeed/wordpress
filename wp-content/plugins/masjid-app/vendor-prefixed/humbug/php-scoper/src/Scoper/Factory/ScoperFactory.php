<?php

declare (strict_types=1);
/*
 * This file is part of the humbug/php-scoper package.
 *
 * Copyright (c) 2017 Théo FIDRY <theo.fidry@gmail.com>,
 *                    Pádraic Brady <padraic.brady@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Masjid_App\Dependencies\Humbug\PhpScoper\Scoper\Factory;

use Masjid_App\Dependencies\Humbug\PhpScoper\Configuration\Configuration;
use Masjid_App\Dependencies\Humbug\PhpScoper\Scoper\Scoper;
use Masjid_App\Dependencies\Humbug\PhpScoper\Symbol\SymbolsRegistry;
use Masjid_App\Dependencies\PhpParser\PhpVersion;
interface ScoperFactory
{
    public function createScoper(Configuration $configuration, SymbolsRegistry $symbolsRegistry, ?PhpVersion $phpVersion = null): Scoper;
}

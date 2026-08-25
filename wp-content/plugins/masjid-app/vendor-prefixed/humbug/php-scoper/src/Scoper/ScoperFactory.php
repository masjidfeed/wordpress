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
namespace Masjid_App\Dependencies\Humbug\PhpScoper\Scoper;

use Masjid_App\Dependencies\Humbug\PhpScoper\Configuration\Configuration;
use Masjid_App\Dependencies\Humbug\PhpScoper\PhpParser\Printer\Printer;
use Masjid_App\Dependencies\Humbug\PhpScoper\PhpParser\TraverserFactory;
use Masjid_App\Dependencies\Humbug\PhpScoper\Scoper\Composer\AutoloadPrefixer;
use Masjid_App\Dependencies\Humbug\PhpScoper\Scoper\Composer\InstalledPackagesScoper;
use Masjid_App\Dependencies\Humbug\PhpScoper\Scoper\Composer\JsonFileScoper;
use Masjid_App\Dependencies\Humbug\PhpScoper\Symbol\EnrichedReflectorFactory;
use Masjid_App\Dependencies\Humbug\PhpScoper\Symbol\SymbolsRegistry;
use Masjid_App\Dependencies\PhpParser\Parser;
/**
 * @final
 *
 * @deprecated Deprecated in favour of \Humbug\PhpScoper\Scoper\Factory\ScoperFactory
 */
class ScoperFactory
{
    public function __construct(private readonly Parser $parser, private readonly EnrichedReflectorFactory $enrichedReflectorFactory, private readonly Printer $printer)
    {
    }
    public function createScoper(Configuration $configuration, SymbolsRegistry $symbolsRegistry): Scoper
    {
        $prefix = $configuration->getPrefix();
        $symbolsConfiguration = $configuration->getSymbolsConfiguration();
        $enrichedReflector = $this->enrichedReflectorFactory->create($symbolsConfiguration);
        $autoloadPrefixer = new AutoloadPrefixer($prefix, $enrichedReflector);
        return new PatchScoper(new PhpScoper($this->parser, new JsonFileScoper(new InstalledPackagesScoper(new SymfonyScoper(new NullScoper(), $prefix, $enrichedReflector, $symbolsRegistry), $autoloadPrefixer), $autoloadPrefixer), new TraverserFactory($enrichedReflector, $prefix, $symbolsRegistry), $this->printer), $prefix, $configuration->getPatcher());
    }
}

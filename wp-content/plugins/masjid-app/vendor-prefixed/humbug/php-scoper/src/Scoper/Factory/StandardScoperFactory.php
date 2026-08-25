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
use Masjid_App\Dependencies\Humbug\PhpScoper\PhpParser\Parser\ParserFactory;
use Masjid_App\Dependencies\Humbug\PhpScoper\PhpParser\Printer\PrinterFactory;
use Masjid_App\Dependencies\Humbug\PhpScoper\PhpParser\TraverserFactory;
use Masjid_App\Dependencies\Humbug\PhpScoper\Scoper\Composer\AutoloadPrefixer;
use Masjid_App\Dependencies\Humbug\PhpScoper\Scoper\Composer\InstalledPackagesScoper;
use Masjid_App\Dependencies\Humbug\PhpScoper\Scoper\Composer\JsonFileScoper;
use Masjid_App\Dependencies\Humbug\PhpScoper\Scoper\NullScoper;
use Masjid_App\Dependencies\Humbug\PhpScoper\Scoper\PatchScoper;
use Masjid_App\Dependencies\Humbug\PhpScoper\Scoper\PhpScoper;
use Masjid_App\Dependencies\Humbug\PhpScoper\Scoper\Scoper;
use Masjid_App\Dependencies\Humbug\PhpScoper\Scoper\SymfonyScoper;
use Masjid_App\Dependencies\Humbug\PhpScoper\Symbol\EnrichedReflectorFactory;
use Masjid_App\Dependencies\Humbug\PhpScoper\Symbol\SymbolsRegistry;
use Masjid_App\Dependencies\PhpParser\PhpVersion;
final readonly class StandardScoperFactory implements ScoperFactory
{
    public function __construct(private EnrichedReflectorFactory $enrichedReflectorFactory, private ParserFactory $parserFactory, private PrinterFactory $printerFactory)
    {
    }
    public function createScoper(Configuration $configuration, SymbolsRegistry $symbolsRegistry, ?PhpVersion $phpVersion = null): Scoper
    {
        $prefix = $configuration->getPrefix();
        $symbolsConfiguration = $configuration->getSymbolsConfiguration();
        $enrichedReflector = $this->enrichedReflectorFactory->create($symbolsConfiguration);
        $parser = $this->parserFactory->createParser($phpVersion);
        $printer = $this->printerFactory->createPrinter($phpVersion);
        $autoloadPrefixer = new AutoloadPrefixer($prefix, $enrichedReflector);
        return new PatchScoper(new PhpScoper($parser, new JsonFileScoper(new InstalledPackagesScoper(new SymfonyScoper(new NullScoper(), $prefix, $enrichedReflector, $symbolsRegistry), $autoloadPrefixer), $autoloadPrefixer), new TraverserFactory($enrichedReflector, $prefix, $symbolsRegistry), $printer), $prefix, $configuration->getPatcher());
    }
}

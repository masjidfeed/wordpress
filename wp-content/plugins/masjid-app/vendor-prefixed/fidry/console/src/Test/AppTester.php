<?php

/*
 * This file is part of the Fidry\Console package.
 *
 * (c) Théo FIDRY <theo.fidry@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare (strict_types=1);
namespace Masjid_App\Dependencies\Fidry\Console\Test;

use Masjid_App\Dependencies\Fidry\Console\Application\Application as ConsoleApplication;
use Masjid_App\Dependencies\Fidry\Console\Bridge\Application\SymfonyApplication;
use Masjid_App\Dependencies\Fidry\Console\Bridge\Command\BasicSymfonyCommandFactory;
use Masjid_App\Dependencies\Fidry\Console\Bridge\CommandLoader\CommandLoaderFactory;
use Masjid_App\Dependencies\Fidry\Console\Bridge\CommandLoader\SymfonyFactoryCommandLoaderFactory;
use Masjid_App\Dependencies\Fidry\Console\DisplayNormalizer;
use Masjid_App\Dependencies\Symfony\Component\Console\Tester\ApplicationTester;
/**
 * @psalm-suppress PropertyNotSetInConstructor
 */
final class AppTester extends ApplicationTester
{
    public static function fromConsoleApp(ConsoleApplication $application, CommandLoaderFactory $commandFactory = new SymfonyFactoryCommandLoaderFactory(new BasicSymfonyCommandFactory())): self
    {
        return new self(new SymfonyApplication($application, $commandFactory));
    }
    /**
     * @param callable(string):string $extraNormalizers
     */
    public function getNormalizedDisplay(callable ...$extraNormalizers): string
    {
        return DisplayNormalizer::removeTrailingSpaces($this->getDisplay(), ...$extraNormalizers);
    }
}

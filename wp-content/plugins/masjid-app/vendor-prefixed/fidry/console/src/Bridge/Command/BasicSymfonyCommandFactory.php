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
namespace Masjid_App\Dependencies\Fidry\Console\Bridge\Command;

use Closure;
use Masjid_App\Dependencies\Fidry\Console\Command\Command as FidryCommand;
use Masjid_App\Dependencies\Fidry\Console\Command\LazyCommand as FidryLazyCommand;
use Masjid_App\Dependencies\Symfony\Component\Console\Command\Command as BaseSymfonyCommand;
use Masjid_App\Dependencies\Symfony\Component\Console\Command\LazyCommand as SymfonyLazyCommand;
final class BasicSymfonyCommandFactory implements SymfonyCommandFactory
{
    public function crateSymfonyCommand(FidryCommand $command): BaseSymfonyCommand
    {
        return $command instanceof FidryLazyCommand ? new SymfonyLazyCommand($command::getName(), [], $command::getDescription(), \false, static fn() => new SymfonyCommand($command), \true) : new SymfonyCommand($command);
    }
    public function crateSymfonyLazyCommand(string $name, string $description, Closure $factory): BaseSymfonyCommand
    {
        return new SymfonyLazyCommand($name, [], $description, \false, static fn() => new SymfonyCommand($factory()), \true);
    }
}

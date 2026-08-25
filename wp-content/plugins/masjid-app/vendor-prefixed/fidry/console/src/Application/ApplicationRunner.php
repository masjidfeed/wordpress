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
namespace Masjid_App\Dependencies\Fidry\Console\Application;

use Masjid_App\Dependencies\Fidry\Console\Bridge\Application\SymfonyApplication;
use Masjid_App\Dependencies\Fidry\Console\Bridge\Command\BasicSymfonyCommandFactory;
use Masjid_App\Dependencies\Fidry\Console\Bridge\CommandLoader\CommandLoaderFactory;
use Masjid_App\Dependencies\Fidry\Console\Bridge\CommandLoader\SymfonyFactoryCommandLoaderFactory;
use Masjid_App\Dependencies\Fidry\Console\IO;
use Masjid_App\Dependencies\Symfony\Component\Console\Input\ArgvInput;
use Masjid_App\Dependencies\Symfony\Component\Console\Input\InputInterface;
use Masjid_App\Dependencies\Symfony\Component\Console\Output\ConsoleOutput;
use Masjid_App\Dependencies\Symfony\Component\Console\Output\OutputInterface;
final class ApplicationRunner
{
    private SymfonyApplication $application;
    public function __construct(Application $application, ?CommandLoaderFactory $commandLoaderFactory = null)
    {
        $this->application = new SymfonyApplication($application, $commandLoaderFactory ?? new SymfonyFactoryCommandLoaderFactory(new BasicSymfonyCommandFactory()));
    }
    /**
     * @psalm-suppress PossiblyUnusedReturnValue
     *
     * Executes the given application command.
     *
     * @return int Zero if everything went fine, or an exit code
     *
     * @see ExitCode
     */
    public static function runApplication(Application $application, ?InputInterface $input = null, ?OutputInterface $output = null, ?CommandLoaderFactory $commandLoaderFactory = null): int
    {
        $runner = new self($application, $commandLoaderFactory);
        return $runner->run(new IO($input ?? new ArgvInput(), $output ?? new ConsoleOutput()));
    }
    /**
     * @see ApplicationRunner::runApplication()
     */
    public function run(?IO $io = null): int
    {
        if (null === $io) {
            $input = null;
            $output = null;
        } else {
            $input = $io->getInput();
            $output = $io->getOutput();
        }
        return $this->application->run($input, $output);
    }
}

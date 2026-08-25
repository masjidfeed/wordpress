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

use Masjid_App\Dependencies\Fidry\Console\DisplayNormalizer;
use Masjid_App\Dependencies\PHPUnit\Framework\Assert;
use Masjid_App\Dependencies\Symfony\Component\Console\Tester\ApplicationTester as SymfonyAppTester;
use Masjid_App\Dependencies\Symfony\Component\Console\Tester\CommandTester as SymfonyCommandTester;
final class OutputAssertions
{
    private function __construct()
    {
    }
    /**
     * @param AppTester|SymfonyAppTester|CommandTester|SymfonyCommandTester $actual
     * @param callable(string):string                                       $extraNormalizers
     */
    public static function assertSameOutput(string $expectedOutput, int $expectedStatusCode, $actual, callable ...$extraNormalizers): void
    {
        $actualOutput = DisplayNormalizer::removeTrailingSpaces($actual->getDisplay(), ...$extraNormalizers);
        Assert::assertSame($expectedOutput, $actualOutput);
        Assert::assertSame($expectedStatusCode, $actual->getStatusCode());
    }
    /**
     * This allows to test both the regular output and the error output rather than both aggregated together. Do note
     * that it requires to set the "capture_stderr_separately" option to be set when executing the command.
     *
     * @param AppTester|SymfonyAppTester|CommandTester|SymfonyCommandTester $actual
     * @param callable(string):string                                       $extraNormalizers
     */
    public static function assertSameSeparateOutputs(string $expectedOutput, string $expectedErrorOutput, int $expectedStatusCode, $actual, callable ...$extraNormalizers): void
    {
        $actualOutput = DisplayNormalizer::removeTrailingSpaces($actual->getDisplay(), ...$extraNormalizers);
        $actualErrorOutput = DisplayNormalizer::removeTrailingSpaces($actual->getErrorOutput(), ...$extraNormalizers);
        Assert::assertSame($expectedOutput, $actualOutput);
        Assert::assertSame($actualErrorOutput, $expectedErrorOutput);
        Assert::assertSame($expectedStatusCode, $actual->getStatusCode());
    }
}

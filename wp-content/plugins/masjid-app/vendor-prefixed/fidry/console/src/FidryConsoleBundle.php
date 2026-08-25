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
namespace Masjid_App\Dependencies\Fidry\Console;

use Masjid_App\Dependencies\Fidry\Console\DependencyInjection\Compiler\AddConsoleCommandPass;
use Masjid_App\Dependencies\Fidry\Console\DependencyInjection\FidryConsoleExtension;
use Masjid_App\Dependencies\Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Masjid_App\Dependencies\Symfony\Component\DependencyInjection\ContainerBuilder;
use Masjid_App\Dependencies\Symfony\Component\DependencyInjection\Extension\Extension;
use Masjid_App\Dependencies\Symfony\Component\HttpKernel\Bundle\Bundle;
final class FidryConsoleBundle extends Bundle
{
    public function getContainerExtension(): Extension
    {
        return new FidryConsoleExtension();
    }
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(
            new AddConsoleCommandPass(),
            PassConfig::TYPE_BEFORE_REMOVING,
            // Priority must be higher than Symfony\Component\Console\DependencyInjection\AddConsoleCommandPass in FrameworkBundle
            10
        );
    }
}

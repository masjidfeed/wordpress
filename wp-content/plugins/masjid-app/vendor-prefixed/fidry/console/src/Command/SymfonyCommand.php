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

use Masjid_App\Dependencies\Fidry\Console\Bridge\Command\SymfonyCommand as BridgeSymfonyCommand;
use Masjid_App\Dependencies\Fidry\Console\Command\SymfonyCommand as PreviousSymfonyCommand;
use Masjid_App\Dependencies\Fidry\Console\Deprecation;
use function class_alias;
class_alias(BridgeSymfonyCommand::class, PreviousSymfonyCommand::class);
Deprecation::classRenamed(PreviousSymfonyCommand::class, BridgeSymfonyCommand::class, '0.5');

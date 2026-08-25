<?php

namespace Masjid_App\Dependencies;

use Masjid_App\Dependencies\CuyZ\Valinor\QA\PHPStan\Extension\SuppressPureErrors;
require_once 'Extension/SuppressPureErrors.php';
return ['services' => [['class' => SuppressPureErrors::class, 'tags' => ['phpstan.ignoreErrorExtension']]]];

<?php

namespace Masjid_App\Dependencies;

use Masjid_App\Dependencies\DG\BypassFinals;
use Masjid_App\Dependencies\Google\ApiCore\Testing\MessageAwareArrayComparator;
use Masjid_App\Dependencies\Google\ApiCore\Testing\ProtobufGPBEmptyComparator;
use Masjid_App\Dependencies\Google\ApiCore\Testing\ProtobufMessageComparator;
\date_default_timezone_set('UTC');
\Masjid_App\Dependencies\SebastianBergmann\Comparator\Factory::getInstance()->register(new MessageAwareArrayComparator());
\Masjid_App\Dependencies\SebastianBergmann\Comparator\Factory::getInstance()->register(new ProtobufMessageComparator());
\Masjid_App\Dependencies\SebastianBergmann\Comparator\Factory::getInstance()->register(new ProtobufGPBEmptyComparator());
// Make sure that while testing we bypass the `final` keyword for the GAPIC client.
// Only run this if the individual component has the helper package installed
if (\class_exists(BypassFinals::class)) {
    BypassFinals::enable();
}

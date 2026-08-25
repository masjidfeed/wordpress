<?php

namespace Masjid_App\Dependencies\StubTests\Framework\Parsers\Reflection;

use Masjid_App\Dependencies\StubTests\Framework\Model\PHPClass;
use Masjid_App\Dependencies\StubTests\Framework\Parsers\Reflection\Wrappers\AdaptedReflectionClassReference;
use Masjid_App\Dependencies\StubTests\Framework\Parsers\Parser;
/**
 * @template-implements Parser<AdaptedReflectionClassReference>
 */
class ReflectionParentClassParser implements Parser
{
    public function canParse($object): bool
    {
        return \false;
    }
    /**
     * Parse an AdaptedReflectionClassReference (parent class reference) into a PHPClass model
     *
     * @param AdaptedReflectionClassReference $object
     * @return PHPClass
     */
    public function parse($object): PHPClass
    {
        $class = new PHPClass();
        $class->setName($object->getName());
        return $class;
    }
}

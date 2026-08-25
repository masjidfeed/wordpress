<?php

namespace Masjid_App\Dependencies\StubTests\Framework\Parsers\Reflection;

use Masjid_App\Dependencies\StubTests\Framework\Model\PHPConstant;
use Masjid_App\Dependencies\StubTests\Framework\Parsers\Parser;
/**
 * @template-implements Parser<array>
 */
class ReflectionDefineConstantParser implements Parser
{
    public function canParse(mixed $object): bool
    {
        return \false;
    }
    public function parse($object): PHPConstant
    {
        $parsedConstant = new PHPConstant();
        $parsedConstant->setName($object[0]);
        if (is_resource($object[1])) {
            $parsedConstant->setValue('PHPSTORM_RESOURCE');
        } else {
            $parsedConstant->setValue($object[1]);
        }
        $parsedConstant->setNamespace('\\');
        $parsedConstant->setId('\\' . $parsedConstant->getName());
        return $parsedConstant;
    }
}

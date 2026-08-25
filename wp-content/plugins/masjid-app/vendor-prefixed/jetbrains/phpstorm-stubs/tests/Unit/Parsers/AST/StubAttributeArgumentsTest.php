<?php

namespace Masjid_App\Dependencies\StubTests\Unit\Parsers\AST;

use Masjid_App\Dependencies\PHPUnit\Framework\TestCase as BaseTestCase;
use Masjid_App\Dependencies\StubTests\Framework\Parsers\Stubs\StubClassParser;
/**
 * Verifies that class-level attributes - in particular the `#[Attribute(...)]` target
 * flags - are captured on the parsed model with their arguments resolved to the same
 * evaluated values Reflection reports.
 */
class StubAttributeArgumentsTest extends BaseTestCase
{
    private StubClassParser $parser;
    protected function setUp(): void
    {
        $this->parser = new StubClassParser();
    }
    public function testCapturesAttributeWithBitwiseOrFlags(): void
    {
        $code = <<<'PHP'
<?php

namespace Masjid_App\Dependencies;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_PROPERTY | \Attribute::TARGET_CLASS_CONSTANT)]
final class Override
{
    public function __construct()
    {
    }
}
\class_alias('Masjid_App\Dependencies\Override', 'Override', \false);
PHP;
        $class = $this->parser->parse($code);
        $attributes = $class->getAttributes();
        self::assertCount(1, $attributes);
        self::assertSame('Attribute', $attributes[0]['name']);
        self::assertSame(\Attribute::TARGET_METHOD | \Attribute::TARGET_PROPERTY | \Attribute::TARGET_CLASS_CONSTANT, $attributes[0]['arguments'][0]);
    }
    public function testCapturesSingleTargetConstant(): void
    {
        $code = <<<'PHP'
<?php

namespace Masjid_App\Dependencies;

#[\Attribute(\Attribute::TARGET_PARAMETER)]
final class SensitiveParameter
{
}
\class_alias('Masjid_App\Dependencies\SensitiveParameter', 'SensitiveParameter', \false);
PHP;
        $class = $this->parser->parse($code);
        $attributes = $class->getAttributes();
        self::assertCount(1, $attributes);
        self::assertSame(\Attribute::TARGET_PARAMETER, $attributes[0]['arguments'][0]);
    }
    public function testBareAttributeHasNoArguments(): void
    {
        $code = <<<'PHP'
<?php

namespace Masjid_App\Dependencies;

#[\Attribute]
final class AllowDynamicProperties
{
}
\class_alias('Masjid_App\Dependencies\AllowDynamicProperties', 'AllowDynamicProperties', \false);
PHP;
        $class = $this->parser->parse($code);
        $attributes = $class->getAttributes();
        self::assertCount(1, $attributes);
        self::assertSame('Attribute', $attributes[0]['name']);
        self::assertSame([], $attributes[0]['arguments']);
    }
    public function testClassWithoutAttributesHasEmptyList(): void
    {
        $code = <<<'PHP'
<?php

namespace Masjid_App\Dependencies;

final class PlainClass
{
}
\class_alias('Masjid_App\Dependencies\PlainClass', 'PlainClass', \false);
PHP;
        $class = $this->parser->parse($code);
        self::assertSame([], $class->getAttributes());
    }
}

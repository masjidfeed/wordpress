<?php

namespace Masjid_App\Dependencies\StubTests\Unit\Validator\Enums;

use Masjid_App\Dependencies\StubTests\Framework\Runner\PhpVersions;
use Masjid_App\Dependencies\StubTests\Framework\Validator\Classes\Methods\ClassMethodsParameterNamesCheck;
use Masjid_App\Dependencies\StubTests\Framework\Validator\Contracts\EntityTypeConfig;
use Masjid_App\Dependencies\StubTests\Unit\Validator\CheckTestCase;
class EnumMethodsParameterNamesCheckTest extends CheckTestCase
{
    // ── supports() ────────────────────────────────────────────────────────────
    public function testSupportsPhp80AndAbove(): void
    {
        $check = new ClassMethodsParameterNamesCheck(entityTypeConfig: EntityTypeConfig::forEnum());
        $this->assertFalse($check->supports(PhpVersions::PHP_5_6->value));
        $this->assertFalse($check->supports(PhpVersions::PHP_7_4->value));
        $this->assertTrue($check->supports(PhpVersions::PHP_8_0->value));
        $this->assertTrue($check->supports(PhpVersions::PHP_8_1->value));
        $this->assertTrue($check->supports(PhpVersions::LATEST->value));
    }
    // ── Not found ─────────────────────────────────────────────────────────────
    public function testEnumNotFoundInReflectionIsFailure(): void
    {
        $enumId = 'Masjid_App\Dependencies\Dom\AdjacentPosition';
        $stubEnum = $this->makeEnum($enumId);
        $provider = $this->createMockReflectionProviderWithEnums([]);
        $stubs = $this->createMockStorageManager();
        $stubs->method('getEnums')->willReturn([$stubEnum]);
        $result = (new ClassMethodsParameterNamesCheck(reflectionProvider: $provider, entityTypeConfig: EntityTypeConfig::forEnum()))->run($stubs, $enumId, '8.1');
        $this->assertTrue($result->hasFailures());
        $this->assertStringContainsString('Enum', $result->getFailures()[$enumId]);
        $this->assertStringNotContainsString('Class', $result->getFailures()[$enumId]);
    }
    public function testEnumNotFoundInStubsIsFailure(): void
    {
        $enumId = 'Masjid_App\Dependencies\Dom\AdjacentPosition';
        $reflEnum = $this->makeEnum($enumId);
        $provider = $this->createMockReflectionProviderWithEnums([$reflEnum]);
        $stubs = $this->createMockStorageManager();
        $stubs->method('getEnums')->willReturn([]);
        $result = (new ClassMethodsParameterNamesCheck(reflectionProvider: $provider, entityTypeConfig: EntityTypeConfig::forEnum()))->run($stubs, $enumId, '8.1');
        $this->assertTrue($result->hasFailures());
        $this->assertStringContainsString('Enum', $result->getFailures()[$enumId]);
    }
    // ── Name matching ─────────────────────────────────────────────────────────
    public function testMatchingParameterNamesPasses(): void
    {
        $enumId = 'Masjid_App\Dependencies\Dom\AdjacentPosition';
        $reflEnum = $this->makeEnum($enumId, [$this->makeMethod('from', parameters: [$this->makeParam('value')])]);
        $stubEnum = $this->makeEnum($enumId, [$this->makeMethod('from', parameters: [$this->makeParam('value')])]);
        $provider = $this->createMockReflectionProviderWithEnums([$reflEnum]);
        $stubs = $this->createMockStorageManager();
        $stubs->method('getEnums')->willReturn([$stubEnum]);
        $result = (new ClassMethodsParameterNamesCheck(reflectionProvider: $provider, entityTypeConfig: EntityTypeConfig::forEnum()))->run($stubs, $enumId, '8.1');
        $this->assertFalse($result->hasFailures());
    }
    public function testParameterNameMismatchFails(): void
    {
        $enumId = 'Masjid_App\Dependencies\Dom\AdjacentPosition';
        $reflEnum = $this->makeEnum($enumId, [$this->makeMethod('from', parameters: [$this->makeParam('value')])]);
        $stubEnum = $this->makeEnum($enumId, [$this->makeMethod('from', parameters: [$this->makeParam('val')])]);
        $provider = $this->createMockReflectionProviderWithEnums([$reflEnum]);
        $stubs = $this->createMockStorageManager();
        $stubs->method('getEnums')->willReturn([$stubEnum]);
        $result = (new ClassMethodsParameterNamesCheck(reflectionProvider: $provider, entityTypeConfig: EntityTypeConfig::forEnum()))->run($stubs, $enumId, '8.1');
        $this->assertTrue($result->hasFailures());
        $failures = $result->getFailures();
        $this->assertArrayHasKey($enumId . '::from', $failures);
        $this->assertStringContainsString('$value', $failures[$enumId . '::from']);
        $this->assertStringContainsString('$val', $failures[$enumId . '::from']);
        $this->assertStringNotContainsString('Class', $failures[$enumId . '::from']);
    }
}

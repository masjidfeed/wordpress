<?php

namespace Masjid_App\Dependencies\StubTests\Framework\Parsers\Stubs\Types;

use Masjid_App\Dependencies\StubTests\Framework\Model\Types\IntersectionType;
use Masjid_App\Dependencies\StubTests\Framework\Model\Types\NoType;
use Masjid_App\Dependencies\StubTests\Framework\Model\Types\NullableType;
use Masjid_App\Dependencies\StubTests\Framework\Model\Types\StandaloneType;
use Masjid_App\Dependencies\StubTests\Framework\Model\Types\UnionType;
/**
 * Value object representing parsed type information from multiple sources.
 * Consolidates type data from:
 * - Signature type hints (native PHP types)
 * - PhpDoc type annotations
 * - LanguageLevelTypeAware attributes (version-specific types)
 */
class ParsedType
{
    /**
     * Type object from the actual PHP signature/declaration
     */
    public StandaloneType|UnionType|NullableType|NoType|IntersectionType|null $typeFromSignature = null;
    /**
     * Type extracted from PhpDoc (`@var`, `@param`, `@return`)
     */
    public ?string $typeFromPhpDoc = null;
    /**
     * Version-specific type map from LanguageLevelTypeAware attribute
     * Format: ['8.0' => 'CurlHandle', '8.1' => 'CurlHandle|false']
     */
    public ?array $languageLevelTypes = null;
    /**
     * Default type from LanguageLevelTypeAware attribute
     * Used when no version-specific match is found
     */
    public ?string $defaultType = null;
}

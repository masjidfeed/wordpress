<?php

namespace Masjid_App\Dependencies\StubTests\Unit\Parsers\Stubs\PhpDoc;

use Masjid_App\Dependencies\PHPUnit\Framework\Attributes\DataProvider;
use Masjid_App\Dependencies\PHPUnit\Framework\TestCase;
use Masjid_App\Dependencies\StubTests\Framework\Parsers\Stubs\PhpDoc\PhpDocumentorParser;
/**
 * Regression tests for {@see PhpDocumentorParser} faithfully preserving generic types that
 * phpDocumentor's Collection value object collapses.
 *
 * phpDocumentor models a collection as `Base<value>` or `Base<key, value>` only, so generics with
 * more than two type arguments are truncated (and reordered): e.g.
 * `\Generator<int, list<string>, void, void>` was rendered as `\Generator<void,void>`. The parser
 * recovers such types verbatim from the raw docblock while leaving the types phpDocumentor handles
 * correctly untouched.
 */
class PhpDocumentorParserTypeRecoveryTest extends TestCase
{
    private PhpDocumentorParser $parser;
    protected function setUp(): void
    {
        $this->parser = new PhpDocumentorParser();
    }
    private function returnTypeOf(string $type): ?string
    {
        return $this->parser->parseDocComment("/**\n * @return {$type}\n */")->returnType;
    }
    /**
     * The motivating case: a four-argument Generator must not lose its key/value/send types.
     */
    public function testGeneratorWithFourArgumentsIsRecoveredVerbatim(): void
    {
        self::assertSame('\Generator<int, list<string>, void, void>', $this->returnTypeOf('\Generator<int, list<string>, void, void>'));
    }
    /**
     * @return list<array{0: string}>
     */
    public static function recoveredMultiArgGenerics(): array
    {
        return [['\Generator<int, list<string>, void, void>'], ['\Generator<int, string, mixed, void>'], ['\Iterator<int, TNode, mixed>']];
    }
    #[DataProvider('recoveredMultiArgGenerics')]
    public function testMultiArgumentGenericsArePreservedVerbatim(string $type): void
    {
        self::assertSame($type, $this->returnTypeOf($type));
    }
    /**
     * Types phpDocumentor renders correctly must be left as-is so the parsed cache does not churn.
     *
     * @return list<array{0: string, 1: string}>
     */
    public static function nonLossyTypes(): array
    {
        return ['two-arg generator (phpDocumentor normalizes spacing)' => ['\Generator<int, string>', '\Generator<int,string>'], 'array key+value' => ['array<int, string>', 'array<int,string>'], 'single-arg list' => ['list<string>', 'list<string>'], 'int range' => ['int<0, max>', 'int<0, max>'], 'plain union' => ['string|int', 'string|int'], 'scalar' => ['bool', 'bool']];
    }
    #[DataProvider('nonLossyTypes')]
    public function testNonLossyTypesAreNotRewritten(string $type, string $expected): void
    {
        self::assertSame($expected, $this->returnTypeOf($type));
    }
    /**
     * The recovery applies to @param as well, not only @return.
     */
    public function testMultiArgumentGenericParamIsRecoveredVerbatim(): void
    {
        $doc = "/**\n * @param \\Generator<int, list<string>, void, void> \$gen\n */";
        $parsed = $this->parser->parseDocComment($doc);
        self::assertSame('\Generator<int, list<string>, void, void>', $parsed->paramTypes['gen'] ?? null);
    }
}

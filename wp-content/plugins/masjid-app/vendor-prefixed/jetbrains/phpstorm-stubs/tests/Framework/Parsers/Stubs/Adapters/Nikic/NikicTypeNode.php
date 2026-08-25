<?php

namespace Masjid_App\Dependencies\StubTests\Framework\Parsers\Stubs\Adapters\Nikic;

use Masjid_App\Dependencies\PhpParser\Node;
use Masjid_App\Dependencies\StubTests\Framework\Parsers\Stubs\Nodes\TypeNode;
/**
 * Adapter for nikic/php-parser type nodes.
 */
class NikicTypeNode implements TypeNode
{
    private Node $typeNode;
    public function __construct(Node $typeNode)
    {
        $this->typeNode = $typeNode;
    }
    public function toString(): string
    {
        return $this->parseType($this->typeNode);
    }
    private function parseType(Node $typeNode): string
    {
        if ($typeNode instanceof \Masjid_App\Dependencies\PhpParser\Node\Identifier) {
            return $typeNode->toString();
        } elseif ($typeNode instanceof \Masjid_App\Dependencies\PhpParser\Node\Name\FullyQualified) {
            // Preserve the leading backslash so TypeNodeConverter knows it's already FQN
            return '\\' . $typeNode->toString();
        } elseif ($typeNode instanceof \Masjid_App\Dependencies\PhpParser\Node\Name) {
            return $typeNode->toString();
        } elseif ($typeNode instanceof \Masjid_App\Dependencies\PhpParser\Node\UnionType) {
            $types = [];
            foreach ($typeNode->types as $type) {
                $types[] = $this->parseType($type);
            }
            return implode('|', $types);
        } elseif ($typeNode instanceof \Masjid_App\Dependencies\PhpParser\Node\NullableType) {
            return $this->parseType($typeNode->type) . '|null';
        } elseif ($typeNode instanceof \Masjid_App\Dependencies\PhpParser\Node\IntersectionType) {
            $types = [];
            foreach ($typeNode->types as $type) {
                $types[] = $this->parseType($type);
            }
            return '(' . implode('&', $types) . ')';
        }
        return '';
    }
}

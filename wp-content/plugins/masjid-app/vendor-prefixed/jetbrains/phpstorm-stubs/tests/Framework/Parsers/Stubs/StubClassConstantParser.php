<?php

namespace Masjid_App\Dependencies\StubTests\Framework\Parsers\Stubs;

use Masjid_App\Dependencies\StubTests\Framework\Model\Access\AccessModifier;
use Masjid_App\Dependencies\StubTests\Framework\Parsers\Stubs\PhpDoc\PhpDocParserInterface;
use Masjid_App\Dependencies\StubTests\Framework\Parsers\Stubs\PhpDoc\PhpDocumentorParser;
use Masjid_App\Dependencies\StubTests\Framework\Parsers\Stubs\Versions\AvailableVersionParserInterface;
use Masjid_App\Dependencies\StubTests\Framework\Parsers\Stubs\Versions\DefaultAvailableVersionParser;
use Masjid_App\Dependencies\StubTests\Framework\Model\PHPClassConstant;
use Masjid_App\Dependencies\StubTests\Framework\Parsers\Stubs\Nodes\ConstantNode;
/**
 * Parses ConstantNode AST nodes into PHPClassConstant domain objects.
 * Extracts all constant metadata: name, visibility modifiers (PHP 7.1+), final flag (PHP 8.1+),
 * and version availability from @since/@removed PhpDoc tags and PhpStormStubsElementAvailable attributes.
 */
class StubClassConstantParser
{
    private PhpDocParserInterface $phpDocParser;
    private AvailableVersionParserInterface $versionParser;
    public function __construct(?PhpDocParserInterface $phpDocParser = null, ?AvailableVersionParserInterface $versionParser = null)
    {
        $this->phpDocParser = $phpDocParser ?? new PhpDocumentorParser();
        $this->versionParser = $versionParser ?? new DefaultAvailableVersionParser();
    }
    /**
     * Parses a constant AST node into PHPClassConstant domain object.
     *
     * @param ConstantNode $node    The constant AST node
     * @param array        $imports Map of import aliases to fully qualified names
     * @return PHPClassConstant
     */
    public function parseNode(ConstantNode $node, array $imports = []): PHPClassConstant
    {
        $constant = new PHPClassConstant();
        $constant->setName($node->getName());
        // Visibility (PHP 7.1+)
        if ($node->isPublic()) {
            $constant->setAccess(AccessModifier::PUBLIC);
        } elseif ($node->isProtected()) {
            $constant->setAccess(AccessModifier::PROTECTED);
        } elseif ($node->isPrivate()) {
            $constant->setAccess(AccessModifier::PRIVATE);
        }
        $constant->setValue($node->getValue());
        // Final flag (PHP 8.1+)
        $constant->setIsFinal($node->isFinal());
        // Parse version availability from PhpDoc + attributes
        $parsedPhpDoc = $this->phpDocParser->parseElementPhpDoc($node->getDocComment());
        $versions = $this->versionParser->parseAvailableVersion($parsedPhpDoc, $node->getAttributes(), $imports);
        $constant->initStubsMetadata()->setSinceVersion($versions['sinceVersion']);
        $constant->initStubsMetadata()->setRemovedVersion($versions['removedVersion']);
        return $constant;
    }
}

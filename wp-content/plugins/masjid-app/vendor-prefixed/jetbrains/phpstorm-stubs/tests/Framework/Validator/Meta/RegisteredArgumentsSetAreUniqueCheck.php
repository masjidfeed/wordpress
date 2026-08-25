<?php

namespace Masjid_App\Dependencies\StubTests\Framework\Validator\Meta;

use Masjid_App\Dependencies\PhpParser\Node\Expr\FuncCall;
use Masjid_App\Dependencies\PhpParser\Node\Name;
use Masjid_App\Dependencies\PhpParser\Node\Scalar\String_;
use Masjid_App\Dependencies\PhpParser\Node\Stmt;
use Masjid_App\Dependencies\StubTests\Framework\MetaFile\MetaFileWalkerTrait;
final class RegisteredArgumentsSetAreUniqueCheck
{
    use MetaFileWalkerTrait;
    /**
     * @return string[] violation messages
     */
    public function check(string $rootDir): array
    {
        /** @var array<string, string> name => first location */
        $seen = [];
        $violations = [];
        foreach ($this->getMetaNamespaceBlocks($rootDir) as [$file, $stmts]) {
            foreach ($stmts as $stmt) {
                if (!$stmt instanceof Stmt\Expression) {
                    continue;
                }
                $expr = $stmt->expr;
                if (!$expr instanceof FuncCall) {
                    continue;
                }
                if (!$expr->name instanceof Name) {
                    continue;
                }
                if ($expr->name->toLowerString() !== 'registerargumentsset') {
                    continue;
                }
                $args = $expr->getArgs();
                if (count($args) < 1) {
                    continue;
                }
                $nameArg = $args[0]->value;
                if (!$nameArg instanceof String_) {
                    continue;
                }
                $setName = $nameArg->value;
                $location = $file . ':' . $expr->getStartLine();
                if (isset($seen[$setName])) {
                    $violations[] = "Duplicate registerArgumentsSet '{$setName}': {$location} (first seen at {$seen[$setName]})";
                } else {
                    $seen[$setName] = $location;
                }
            }
        }
        return $violations;
    }
}

<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\QA\PHPStan\Extension;

use Masjid_App\Dependencies\PhpParser\Node;
use Masjid_App\Dependencies\PHPStan\Analyser\Scope;
use Masjid_App\Dependencies\PHPStan\Node\InClassNode;
use Masjid_App\Dependencies\PHPStan\Rules\Rule;
use Masjid_App\Dependencies\PHPStan\Rules\RuleErrorBuilder;
use function preg_match;
use function str_contains;
use function str_starts_with;
/**
 * @implements Rule<InClassNode>
 */
final class ApiAndInternalAnnotationCheck implements Rule
{
    public function getNodeType(): string
    {
        return InClassNode::class;
    }
    public function processNode(Node $node, Scope $scope): array
    {
        $reflection = $scope->getClassReflection();
        if (!$reflection) {
            return [];
        }
        if ($reflection->isAnonymous()) {
            return [];
        }
        if (str_contains($reflection->getFileName() ?? '', '/tests/')) {
            return [];
        }
        if (str_starts_with($reflection->getName(), 'Masjid_App\Dependencies\CuyZ\Valinor\QA')) {
            return [];
        }
        if (!preg_match('/@(api|internal)\s+/', $reflection->getResolvedPhpDoc()?->getPhpDocString() ?? '')) {
            return [RuleErrorBuilder::message('Missing annotation `@api` or `@internal`.')->identifier('valinor.apiOrInternalAnnotation')->build()];
        }
        return [];
    }
}

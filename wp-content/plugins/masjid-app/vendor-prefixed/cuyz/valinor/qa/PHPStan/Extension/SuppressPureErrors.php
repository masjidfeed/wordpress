<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\QA\PHPStan\Extension;

use Masjid_App\Dependencies\CuyZ\Valinor\MapperBuilder;
use Masjid_App\Dependencies\CuyZ\Valinor\NormalizerBuilder;
use Masjid_App\Dependencies\CuyZ\Valinor\Utility\Polyfill;
use Masjid_App\Dependencies\PhpParser\Node;
use Masjid_App\Dependencies\PHPStan\Analyser\Error;
use Masjid_App\Dependencies\PHPStan\Analyser\IgnoreErrorExtension;
use Masjid_App\Dependencies\PHPStan\Analyser\Scope;
use function in_array;
/**
 * This extension should not be used by default, as it suppresses errors that
 * are typically indicative of purity issues in the codebase. It is advised to
 * first address the root causes of these errors before enabling this extension.
 *
 * When enabled, it will ignore all purity-related errors of method calls in the
 * context of `MapperBuilder` and `NormalizerBuilder`.
 */
final class SuppressPureErrors implements IgnoreErrorExtension
{
    public function shouldIgnore(Error $error, Node $node, Scope $scope): bool
    {
        if ($error->getIdentifier() !== 'argument.type') {
            return \false;
        }
        if (!$node instanceof Node\Expr\MethodCall) {
            return \false;
        }
        $type = $scope->getType($node->var);
        if (!$type->isObject()->yes()) {
            return \false;
        }
        if (!Polyfill::array_find($type->getObjectClassNames(), fn(string $className) => $className === MapperBuilder::class || $className === NormalizerBuilder::class)) {
            return \false;
        }
        if (!$node->name instanceof Node\Identifier) {
            return \false;
        }
        $methodName = $node->name->toString();
        if (!in_array($methodName, ['infer', 'registerConstructor', 'registerConverter', 'registerTransformer'], \true)) {
            return \false;
        }
        if ($node->args === []) {
            return \false;
        }
        $callableArgumentIndex = ['infer' => 1, 'registerConstructor' => 0, 'registerConverter' => 0, 'registerTransformer' => 0][$methodName];
        $argument = $node->args[$callableArgumentIndex];
        if (!$argument instanceof Node\Arg) {
            return \false;
        }
        $argumentType = $scope->getType($argument->value);
        if ($argumentType->isCallable()->yes()) {
            return \true;
        }
        if ($argumentType->isArray()->yes() && $argumentType->getIterableValueType()->isCallable()->yes()) {
            return \true;
        }
        return \false;
    }
}

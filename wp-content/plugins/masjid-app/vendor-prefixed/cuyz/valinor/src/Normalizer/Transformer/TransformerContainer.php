<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Normalizer\Transformer;

use Masjid_App\Dependencies\CuyZ\Valinor\Definition\AttributeDefinition;
use Masjid_App\Dependencies\CuyZ\Valinor\Definition\FunctionDefinition;
use Masjid_App\Dependencies\CuyZ\Valinor\Definition\MethodDefinition;
use Masjid_App\Dependencies\CuyZ\Valinor\Definition\Repository\FunctionDefinitionRepository;
use Masjid_App\Dependencies\CuyZ\Valinor\Normalizer\Exception\KeyTransformerHasTooManyParameters;
use Masjid_App\Dependencies\CuyZ\Valinor\Normalizer\Exception\KeyTransformerParameterInvalidType;
use Masjid_App\Dependencies\CuyZ\Valinor\Normalizer\Exception\TransformerHasInvalidCallableParameter;
use Masjid_App\Dependencies\CuyZ\Valinor\Normalizer\Exception\TransformerHasNoParameter;
use Masjid_App\Dependencies\CuyZ\Valinor\Normalizer\Exception\TransformerHasTooManyParameters;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\StringType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\CallableType;
/** @internal */
final class TransformerContainer
{
    private bool $transformersCallablesWereChecked = \false;
    public function __construct(
        private FunctionDefinitionRepository $functionDefinitionRepository,
        /** @var list<callable> */
        private array $transformers
    )
    {
    }
    public function hasTransformers(): bool
    {
        return $this->transformers !== [];
    }
    /**
     * @return list<callable>
     */
    public function transformers(): array
    {
        if (!$this->transformersCallablesWereChecked) {
            $this->transformersCallablesWereChecked = \true;
            foreach ($this->transformers as $transformer) {
                $function = $this->functionDefinitionRepository->for($transformer);
                self::checkTransformer($function);
            }
        }
        return $this->transformers;
    }
    public static function filterTransformerAttributes(AttributeDefinition $attribute): bool
    {
        return $attribute->class->methods->has('normalize') && self::checkTransformer($attribute->class->methods->get('normalize'));
    }
    public static function filterKeyTransformerAttributes(AttributeDefinition $attribute): bool
    {
        return $attribute->class->methods->has('normalizeKey') && self::checkKeyTransformer($attribute->class->methods->get('normalizeKey'));
    }
    private static function checkTransformer(MethodDefinition|FunctionDefinition $method): bool
    {
        $parameters = $method->parameters;
        if ($parameters->count() === 0) {
            throw new TransformerHasNoParameter($method);
        }
        if ($parameters->count() > 2) {
            throw new TransformerHasTooManyParameters($method);
        }
        if ($parameters->count() > 1 && !$parameters->at(1)->nativeType instanceof CallableType) {
            throw new TransformerHasInvalidCallableParameter($method, $parameters->at(1)->nativeType);
        }
        return \true;
    }
    private static function checkKeyTransformer(MethodDefinition $method): bool
    {
        $parameters = $method->parameters;
        if ($parameters->count() > 1) {
            throw new KeyTransformerHasTooManyParameters($method);
        }
        if ($parameters->count() > 0) {
            $type = $parameters->at(0)->type;
            if (!$type instanceof StringType) {
                throw new KeyTransformerParameterInvalidType($method);
            }
        }
        return \true;
    }
}

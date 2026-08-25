<?php

namespace Masjid_App\Dependencies\StubTests\Framework\Model;

use Masjid_App\Dependencies\StubTests\Framework\Model\Access\AccessModifier;
use Masjid_App\Dependencies\StubTests\Framework\Model\Types\IntersectionType;
use Masjid_App\Dependencies\StubTests\Framework\Model\Types\NoType;
use Masjid_App\Dependencies\StubTests\Framework\Model\Types\NullableType;
use Masjid_App\Dependencies\StubTests\Framework\Model\Types\StandaloneType;
use Masjid_App\Dependencies\StubTests\Framework\Model\Types\UnionType;
class PHPProperty extends BasePHPElement
{
    private ?AccessModifier $accessModifier = null;
    private bool $isStatic = \false;
    private bool $isReadonly = \false;
    private StandaloneType|UnionType|NullableType|NoType|IntersectionType|null $type = null;
    private mixed $defaultValue = null;
    private bool $hasDefaultValue = \false;
    public function getAccess(): ?AccessModifier
    {
        return $this->accessModifier;
    }
    public function setAccess(AccessModifier $accessModifier): void
    {
        $this->accessModifier = $accessModifier;
    }
    public function isStatic(): bool
    {
        return $this->isStatic;
    }
    public function setIsStatic(bool $isStatic): void
    {
        $this->isStatic = $isStatic;
    }
    public function isReadonly(): bool
    {
        return $this->isReadonly;
    }
    public function setIsReadonly(bool $isReadonly): void
    {
        $this->isReadonly = $isReadonly;
    }
    public function setTypeFromSignature(StandaloneType|UnionType|NullableType|NoType|IntersectionType $type): void
    {
        $this->type = $type;
    }
    public function getType(): StandaloneType|UnionType|NullableType|NoType|IntersectionType|null
    {
        return $this->type;
    }
    public function getDefaultValue(): mixed
    {
        return $this->defaultValue;
    }
    public function setDefaultValue(mixed $defaultValue): void
    {
        $this->defaultValue = $defaultValue;
        $this->hasDefaultValue = \true;
    }
    public function hasDefaultValue(): bool
    {
        return $this->hasDefaultValue;
    }
    public function setHasDefaultValue(bool $hasDefaultValue): void
    {
        $this->hasDefaultValue = $hasDefaultValue;
    }
}

<?php

namespace Teddytrombone\IdeCompanion\Parser;

class ParsedTagResult
{
    public const STATUS_NO_FLUID_TAG = 0;

    public const STATUS_NAMESPACE = 1;

    public const STATUS_TAG = 2;

    public const STATUS_ATTRIBUTE = 3;

    public const STATUS_INSIDE_ATTRIBUTE = 4;


    /**
     * @var ?string
     */
    protected $namespace = null;

    /**
     * @var ?string
     */
    protected $tag = null;

    /**
     * @var string[]
     */
    protected $properties = [];

    /**
     * @var int
     */
    protected $status = self::STATUS_NO_FLUID_TAG;

    public function getNamespace(): ?string
    {
        return $this->namespace;
    }

    public function setNamespace(?string $namespace): self
    {
        $this->namespace = $namespace;
        return $this;
    }

    public function getTag(): ?string
    {
        return $this->tag;
    }

    public function setTag(?string $tag): self
    {
        $this->tag = $tag;
        return $this;
    }
    /**
     * @return string[]
     */
    public function getProperties(): array
    {
        return $this->properties;
    }

    /**
     * @param string[] $properties
     */
    public function setProperties(array $properties): self
    {
        $this->properties = $properties;
        return $this;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function setStatus(int $status): self
    {
        $this->status = $status;
        return $this;
    }
}

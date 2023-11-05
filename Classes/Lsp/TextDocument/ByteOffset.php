<?php

namespace Teddytrombone\IdeCompanion\Lsp\TextDocument;

use Teddytrombone\IdeCompanion\Lsp\TextDocument\Exception\InvalidByteOffset;

/**
 * Taken from phpactor/phpactor
 */
class ByteOffset
{
    private int $offset;

    private function __construct(int $offset)
    {
        if ($offset < 0) {
            throw new InvalidByteOffset(sprintf(
                'Offset must be greater than or equal to zero, got "%s"',
                $offset
            ));
        }
        $this->offset = $offset;
    }

    public static function fromInt(int $offset): self
    {
        return new self($offset);
    }

    /**
     *
     * @param int|ByteOffset $value
     */
    public static function fromUnknown($value): self
    {
        if ($value instanceof ByteOffset) {
            return $value;
        }

        return self::fromInt($value);
    }

    /**
     * @param int|ByteOffset $offset
     */
    public static function fromIntOrByteOffset($offset): self
    {
        if ($offset instanceof ByteOffset) {
            return $offset;
        }

        return self::fromInt($offset);
    }

    public function toInt(): int
    {
        return $this->offset;
    }

    public function add(int $amount): self
    {
        return new self($this->offset + $amount);
    }
}

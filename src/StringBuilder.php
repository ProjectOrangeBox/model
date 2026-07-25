<?php

declare(strict_types=1);

namespace orange\model;

class StringBuilder
{
    protected array $append;

    public function __construct(protected string $separator = ' ', protected bool $autoTrim = true)
    {
        $this->clear();
    }

    public function clear(): self
    {
        $this->append = [];

        return $this;
    }

    public function separator(string $separator): self
    {
        $this->separator = $separator;

        return $this;
    }

    public function append(): self
    {
        foreach (func_get_args() as $append) {
            $append = (string)$append;

            if ($this->autoTrim) {
                $append = trim($append);
            }

            // only a genuinely empty string is dropped - empty() would also
            // discard a literal 0, which is how 'OFFSET 0' used to disappear
            if ($append !== '') {
                $this->append[] = $append;
            }
        }

        return $this;
    }

    public function get(string $prefix = '', string $suffix = '', ?string $separator = null): string
    {
        return $prefix . implode($separator ?? $this->separator, $this->append) . $suffix;
    }

    public function getIfHas(string $prefix = '', string $suffix = '', ?string $separator = null): string
    {
        return $this->has() ? $this->get($prefix, $suffix, $separator) : '';
    }

    public function has(): bool
    {
        return !empty($this->append);
    }
}

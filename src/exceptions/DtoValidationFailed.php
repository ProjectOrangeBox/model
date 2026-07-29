<?php

declare(strict_types=1);

namespace orange\model\exceptions;

use Throwable;
use orange\model\exceptions\Model;

/**
 * Raised when a DtoModel operation is handed input its Dto rejects.
 *
 * Carries the Dto's own error set so the caller - or the framework's exception
 * handler - can report which fields failed and why, rather than just that
 * something did.
 */
class DtoValidationFailed extends Model
{
    /**
     * @param array<string, array<int, string>> $errors Field name => list of
     *     messages, as returned by Dto::allErrors()
     */
    public function __construct(
        string $message = '',
        protected array $errors = [],
        int $code = 406,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * The field names that failed.
     *
     * @return array<int, string>
     */
    public function getKeys(): array
    {
        return array_keys($this->errors);
    }

    /**
     * Whether anything actually failed.
     *
     * A DtoValidationFailed can legitimately carry no errors - getDtoClass()
     * raises one for an unregistered operation, which is a wiring mistake rather
     * than a field-level failure - so this is worth asking before formatting.
     */
    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    /**
     * Every message, flattened, one per line.
     *
     * @return array<int, string>
     */
    public function getErrorsAsArray(): array
    {
        return array_merge(...array_values($this->errors)) ?: [];
    }

    /**
     * Every message wrapped for display, in field order.
     *
     * Mirrors orange\validate's ValidationFailed so a caller that formatted its
     * errors that way - bin/aclCli.php, a form re-render - reads the same either
     * side of a model moving from the validate service to a Dto.
     */
    public function getErrorsAsHtml(string $prefix = '', string $suffix = '', string $separator = PHP_EOL): string
    {
        $lines = [];

        foreach ($this->getErrorsAsArray() as $message) {
            $lines[] = $prefix . $message . $suffix;
        }

        return implode($separator, $lines);
    }

    /**
     * 406 Not Acceptable by default - read by the framework's exception handler.
     */
    public function getHttpCode(): int
    {
        return $this->getCode();
    }

    /**
     * The JSON body the framework's exception handler sends for this failure.
     */
    public function getOutput(): string
    {
        return (string) json_encode([
            'has' => $this->errors !== [],
            'count' => count($this->errors),
            'keys' => $this->getKeys(),
            'errors' => $this->errors,
        ]);
    }
}

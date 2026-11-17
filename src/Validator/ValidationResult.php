<?php

declare(strict_types=1);

namespace Boneradf\Validator;

/**
 * Validation result — holds per-field errors.
 */
final class ValidationResult
{
    /** @var array<string, string[]> field => [errors] */
    private array $errors;

    public function __construct (array $errors = [])
    {
        $this->errors = $errors;
    }

    public function passes (): bool
    {
        return $this->errors === [];
    }

    public function fails (): bool
    {
        return $this->errors !== [];
    }

    /**
     * @return array<string, string[]>
     */
    public function errors (): array
    {
        return $this->errors;
    }

    /**
     * Errors for a specific field, or null if clean.
     *
     * @return string[]|null
     */
    public function fieldErrors (string $field): ?array
    {
        return $this->errors[$field] ?? null;
    }
}

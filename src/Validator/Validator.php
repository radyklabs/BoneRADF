<?php

declare(strict_types=1);

namespace Boneradf\Validator;

use DateTime;
use DateTimeZone;

/**
 * Strict input validator.
 *
 * Rules are pipe-delimited strings, e.g. 'required|email|min:3|max:80'.
 * A failing rule produces a per-field error in the result.
 *
 * Supported rules:
 *   required, nullable
 *   string, int, float, bool, array, email
 *   min:N, max:N  (length for strings, value for int/float)
 *   pattern:/regex/
 *   in:a,b,c
 *   callback:fn_name
 */
final class Validator
{
    /** @var array<string, string> field => data */
    private array $data;

    public function __construct (array $data)
    {
        $this->data = $data;
    }

    /**
     * Validate $data against $rules.
     *
     * @param array<string, string> $rules  field => 'rule|rule:param|...'
     */
    public function validate (array $rules): ValidationResult
    {
        $errors = [];

        foreach ($rules as $field => $ruleStr)
        {
            $value = $this->data[$field] ?? null;
            $rulesForField = explode ('|', $ruleStr);

            $isNullable = in_array ('nullable', $rulesForField, true);
            if ($value === null && $isNullable && !in_array ('required', $rulesForField, true))
            {
                continue; // nullable and absent → skip remaining rules
            }

            foreach ($rulesForField as $rule)
            {
                if ($rule === '' || $rule === 'nullable')
                {
                    continue;
                }

                [$name, $param] = $this->splitRule ($rule);
                $err = $this->applyRule ($field, $value, $name, $param);
                if ($err !== null)
                {
                    $errors[$field][] = $err;
                    break; // one error per field is enough; stop on first failure
                }
            }
        }

        return new ValidationResult ($errors);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitRule (string $rule): array
    {
        if (str_starts_with ($rule, 'pattern:'))
        {
            // Keep the full regex (including delimiters) as the param
            return ['pattern', substr($rule, 8)];
        }

        if (str_contains ($rule, ':'))
        {
            [$name, $param] = explode (':', $rule, 2);
            return [$name, $param];
        }
        return [$rule, ''];
    }

    /**
     * @return string|null  error message, or null if pass
     */
    private function applyRule (string $field, mixed $value, string $name, string $param): ?string
    {
        switch ($name)
        {
            case 'required':
                if ($value === null || $value === '')
                {
                    return "The {$field} field is required.";
                }

                return null;

            case 'string':
                if (!is_string ($value))
                {
                    return "The {$field} field must be a string.";
                }

                return null;

            case 'int':
                if (!is_int ($value) && !is_string ($value))
                {
                    return "The {$field} field must be an integer.";
                }

                $int = filter_var ((string) $value, FILTER_VALIDATE_INT);
                if ($int === false)
                {
                    return "The {$field} field must be an integer.";
                }

                return null;

            case 'float':
                if (!is_float ($value) && !is_int ($value) && !is_string ($value))
                {
                    return "The {$field} field must be a float.";
                }

                $f = filter_var ((string) $value, FILTER_VALIDATE_FLOAT);
                if ($f === false)
                {
                    return "The {$field} field must be a float.";
                }

                return null;

            case 'bool':
                if (!is_bool ($value) && !in_array ($value, [0, 1, '0', '1', 'true', 'false'], true))
                {
                    return "The {$field} field must be a boolean.";
                }

                return null;

            case 'array':
                if (!is_array ($value))
                {
                    return "The {$field} field must be an array.";
                }

                return null;

            case 'email':
                if (!is_string ($value) || filter_var ($value, FILTER_VALIDATE_EMAIL) === false)
                {
                    return "The {$field} field must be a valid email address.";
                }

                return null;

            case 'date':
                if (!is_string ($value) || (new DateTime ())->setTimezone (new DateTimeZone ('UTC')) === null)
                {
                    // fall through to strtotime check
                }

                if (!is_string ($value) || strtotime ($value) === false)
                {
                    return "The {$field} field must be a valid date.";
                }

                return null;

            case 'min':
                $min = (int) $param;
                if (is_string ($value) && strlen ($value) < $min)
                {
                    return "The {$field} field must be at least {$min} characters.";
                }

                if (is_int ($value) || is_float ($value))
                {
                    if ((float) $value < $min)
                    {
                        return "The {$field} field must be at least {$min}.";
                    }
                }

                return null;

            case 'max':
                $max = (int) $param;
                if (is_string ($value) && strlen ($value) > $max)
                {
                    return "The {$field} field may not exceed {$max} characters.";
                }

                if (is_int ($value) || is_float ($value))
                {
                    if ((float) $value > $max)
                    {
                        return "The {$field} field may not be greater than {$max}.";
                    }
                }

                return null;

            case 'in':
                $allowed = explode (',', $param);
                if (!in_array ((string) $value, $allowed, true))
                {
                    return "The {$field} field must be one of: {$param}.";
                }

                return null;

            case 'pattern':
                // param is a full PCRE regex, e.g. '/^[a-z0-9-]+$/' or '~abc~'
                if (!is_string ($value) || !preg_match ($param, $value))
                {
                    return "The {$field} field must match the required pattern.";
                }

                return null;

            case 'callback':
                if (!is_callable ($param))
                {
                    return "The {$field} field has an invalid callback '{$param}'.";
                }

                $result = $param ($value, $this->data);
                if ($result === false)
                {
                    return "The {$field} field failed custom validation.";
                }

                if (is_string ($result) && $result !== '')
                {
                    return $result;
                }

                return null;

            default:
                return "Unknown validation rule '{$name}'.";
        }
    }
}

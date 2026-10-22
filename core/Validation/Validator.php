<?php

declare(strict_types=1);

namespace Mphp\Validation;

/**
 * Validator::validate($request->all(), ['name' => 'required|string|max:50', 'age' => 'nullable|integer|min:0'])
 *
 * Returns only the validated keys, cast to their declared type. Throws ValidationException on failure.
 * Rules: required nullable string integer numeric boolean email url array date in:a,b regex:/x/ min:n max:n
 * (min/max measure length for strings, value for numbers, count for arrays).
 * Use the array form ['regex:/a|b/'] when a rule contains a pipe.
 */
final class Validator
{
    /**
     * @param array<string, mixed> $data
     * @param array<string, string|list<string>> $rules
     * @return array<string, mixed>
     * @throws ValidationException
     */
    public static function validate(array $data, array $rules): array
    {
        $errors = [];
        $validated = [];

        foreach ($rules as $field => $spec) {
            $list = is_array($spec) ? $spec : explode('|', $spec);
            $value = $data[$field] ?? null;
            $blank = $value === null || $value === '' || $value === [];

            if ($blank) {
                if (in_array('required', $list, true)) {
                    $errors[$field][] = "The $field field is required.";
                } elseif (array_key_exists($field, $data)) {
                    $validated[$field] = null;
                }
                continue;
            }

            $failed = false;
            foreach ($list as $rule) {
                if ($rule === 'required' || $rule === 'nullable') {
                    continue;
                }
                if (($message = self::check($field, $value, $rule, $list)) !== null) {
                    $errors[$field][] = $message;
                    $failed = true;
                }
            }

            if (!$failed) {
                $validated[$field] = self::cast($value, $list);
            }
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return $validated;
    }

    /** @param list<string> $all */
    private static function check(string $field, mixed $value, string $rule, array $all): ?string
    {
        [$name, $argument] = array_pad(explode(':', $rule, 2), 2, null);

        $ok = match ($name) {
            'string' => is_string($value),
            'integer' => filter_var($value, FILTER_VALIDATE_INT) !== false && !is_bool($value),
            'numeric' => is_numeric($value),
            'boolean' => in_array($value, [true, false, 0, 1, '0', '1', 'true', 'false'], true),
            'email' => is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            'url' => is_string($value) && filter_var($value, FILTER_VALIDATE_URL) !== false,
            'array' => is_array($value),
            'date' => is_string($value) && strtotime($value) !== false,
            'in' => in_array((string) $value, explode(',', (string) $argument), true),
            'regex' => is_string($value) && preg_match((string) $argument, $value) === 1,
            'min' => self::size($value, $all) >= (float) $argument,
            'max' => self::size($value, $all) <= (float) $argument,
            default => throw new \InvalidArgumentException("Unknown validation rule [$name]."),
        };

        return $ok ? null : match ($name) {
            'min' => "The $field field must be at least $argument.",
            'max' => "The $field field must not be greater than $argument.",
            'in' => "The selected $field is invalid.",
            default => "The $field field must be a valid $name.",
        };
    }

    /** @param list<string> $rules */
    private static function size(mixed $value, array $rules): float
    {
        if (is_array($value)) {
            return count($value);
        }
        if (is_numeric($value) && (in_array('integer', $rules, true) || in_array('numeric', $rules, true))) {
            return (float) $value;
        }

        return mb_strlen((string) $value);
    }

    /** @param list<string> $rules */
    private static function cast(mixed $value, array $rules): mixed
    {
        return match (true) {
            in_array('integer', $rules, true) => (int) $value,
            in_array('numeric', $rules, true) => $value + 0,
            in_array('boolean', $rules, true) => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            default => $value,
        };
    }
}

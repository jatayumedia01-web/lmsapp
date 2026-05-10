<?php
declare(strict_types=1);

namespace Devithor;

/**
 * Tiny rule-based input validator. Returns either an array of errors or null.
 * Rules:
 *   required              — non-empty string / non-null
 *   email                 — RFC-ish email
 *   min:N                 — string length >= N
 *   max:N                 — string length <= N
 *   int                   — castable to integer
 *   in:a,b,c              — value must be one of the listed options
 *   url                   — well-formed URL
 *
 * Usage:
 *   $errors = Validator::check($request->body, [
 *       'email'  => ['required', 'email'],
 *       'name'   => ['required', 'min:2', 'max:120'],
 *   ]);
 *   if ($errors) Response::json(['errors' => $errors], 422);
 */
final class Validator
{
    /** @return array<string,string>|null */
    public static function check(array $input, array $rules): ?array
    {
        $errors = [];
        foreach ($rules as $field => $fieldRules) {
            foreach ($fieldRules as $rule) {
                $error = self::apply($input, $field, $rule);
                if ($error) {
                    $errors[$field] = $error;
                    break; // stop at first failure per field
                }
            }
        }
        return empty($errors) ? null : $errors;
    }

    private static function apply(array $input, string $field, string $rule): ?string
    {
        $value = $input[$field] ?? null;

        if ($rule === 'required') {
            if ($value === null || $value === '' || (is_array($value) && empty($value))) {
                return "$field is required";
            }
            return null;
        }
        if ($value === null || $value === '') return null; // skip non-required rules on empty fields

        if ($rule === 'email') {
            return filter_var($value, FILTER_VALIDATE_EMAIL) ? null : "$field must be a valid email";
        }
        if ($rule === 'int') {
            return (filter_var($value, FILTER_VALIDATE_INT) !== false) ? null : "$field must be an integer";
        }
        if ($rule === 'url') {
            return filter_var($value, FILTER_VALIDATE_URL) ? null : "$field must be a valid URL";
        }
        if (str_starts_with($rule, 'min:')) {
            $n = (int) substr($rule, 4);
            return mb_strlen((string) $value) >= $n ? null : "$field must be at least $n characters";
        }
        if (str_starts_with($rule, 'max:')) {
            $n = (int) substr($rule, 4);
            return mb_strlen((string) $value) <= $n ? null : "$field must be at most $n characters";
        }
        if (str_starts_with($rule, 'in:')) {
            $opts = explode(',', substr($rule, 3));
            return in_array((string) $value, $opts, true) ? null : "$field must be one of: " . implode(', ', $opts);
        }
        return null;
    }
}

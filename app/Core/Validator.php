<?php

declare(strict_types=1);

namespace App\Core;

final class Validator
{
    /** @var array<string, string> */
    private array $errors = [];

    /** @var array<string, mixed> */
    private array $data = [];

    /** @param array<string, mixed> $data */
    public function validate(array $data, array $rules): self
    {
        $this->data = $data;

        foreach ($rules as $field => $ruleSet) {
            $value = $data[$field] ?? null;
            $rulesList = is_string($ruleSet) ? explode('|', $ruleSet) : $ruleSet;

            foreach ($rulesList as $rule) {
                if (is_string($rule)) {
                    $this->applyRule($field, $value, $rule);
                }
            }
        }

        return $this;
    }

    public function fails(): bool
    {
        return count($this->errors) > 0;
    }

    /** @return array<string, string> */
    public function errors(): array
    {
        return $this->errors;
    }

    public function error(string $field, string $message): void
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = $message;
        }
    }

    /** @param mixed $value */
    private function applyRule(string $field, mixed $value, string $rule): void
    {
        $parts = explode(':', $rule, 2);
        $ruleName = $parts[0];
        $param = $parts[1] ?? null;

        $valueStr = is_string($value) ? trim($value) : '';
        $valueLen = mb_strlen($valueStr);

        switch ($ruleName) {
            case 'required':
                if ($value === null || $value === '' || (is_numeric($value) && $value == 0)) {
                    $this->error($field, ucfirst($field) . ' wajib diisi.');
                }
                break;

            case 'email':
                if ($valueStr !== '' && filter_var($valueStr, FILTER_VALIDATE_EMAIL) === false) {
                    $this->error($field, 'Format email tidak valid.');
                }
                break;

            case 'min':
                $min = (int) $param;
                if ($valueStr !== '' && $valueLen < $min) {
                    $this->error($field, ucfirst($field) . ' minimal ' . $min . ' karakter.');
                }
                break;

            case 'max':
                $max = (int) $param;
                if ($valueLen > $max) {
                    $this->error($field, ucfirst($field) . ' maksimal ' . $max . ' karakter.');
                }
                break;

            case 'in':
                $allowed = explode(',', (string) $param);
                if ($valueStr !== '' && !in_array($valueStr, $allowed, true)) {
                    $this->error($field, ucfirst($field) . ' tidak valid.');
                }
                break;

            case 'regex':
                if ($valueStr !== '' && @preg_match((string) $param, $valueStr) === false) {
                    $this->error($field, ucfirst($field) . ' tidak valid.');
                }
                break;
        }
    }

    public static function clean(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);

        return $value ?? '';
    }

    public static function isValidUtf8(string $value): bool
    {
        return mb_check_encoding($value, 'UTF-8');
    }
}

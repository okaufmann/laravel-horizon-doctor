<?php

namespace Okaufmann\LaravelHorizonDoctor\Checks;

final readonly class EnvironmentCheckResult
{
    /**
     * @param  list<string>  $errors
     * @param  list<string>  $warnings
     */
    public function __construct(
        public array $errors = [],
        public array $warnings = [],
    ) {}

    public static function ok(): self
    {
        return new self();
    }

    /**
     * @param  list<string>  $errors
     */
    public static function errors(array $errors): self
    {
        return new self($errors, []);
    }

    /**
     * @param  list<string>  $warnings
     */
    public static function warnings(array $warnings): self
    {
        return new self([], $warnings);
    }

    public function merge(self $other): self
    {
        return new self(
            array_merge($this->errors, $other->errors),
            array_merge($this->warnings, $other->warnings),
        );
    }
}

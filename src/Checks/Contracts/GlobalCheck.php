<?php

namespace Okaufmann\LaravelHorizonDoctor\Checks\Contracts;

interface GlobalCheck
{
    /**
     * @return list<string>
     */
    public function check(): array;
}

<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Support;

use LaraGram\Support\Facades\Validator;
use LaraGram\Validation\ValidationException;

class ValidationMessages
{
    public static function from(ValidationException $exception): string
    {
        $messages = collect($exception->errors())->flatten()->all();

        if (count($messages) === 0 || ! is_string($messages[0])) {
            $translator = Validator::getTranslator();

            return $translator->get('The given data was invalid.');
        }

        return implode(' ', $messages);
    }
}

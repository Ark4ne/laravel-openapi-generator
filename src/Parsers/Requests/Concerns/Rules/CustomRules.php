<?php

namespace Ark4ne\OpenApi\Parsers\Requests\Concerns\Rules;

use Ark4ne\OpenApi\Parsers\Common\EnumToRef;
use Ark4ne\OpenApi\Support\Config;
use Ark4ne\OpenApi\Support\Reflection;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rules;

trait CustomRules
{
    use CommonRules;

    private static array $LARAVEL_RULES = [
        Rules\AnyOf::class => 'parseCustomAnyOf',
        Rules\Can::class => 'parseCustomCan',
        Rules\DatabaseRule::class => 'parseCustomDatabaseRule',
        Rules\Email::class => 'parseCustomEmail',
        Rules\Enum::class => 'parseCustomEnum',
        Rules\File::class => 'parseCustomFile',
        Rules\ImageFile::class => 'parseCustomImageFile',
        Rules\Password::class => 'parseCustomPassword',
    ];

    /**
     * @param Rule|ValidationRule $rule
     * @param array $parameters
     * @return void
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function parseCustomRules(object $rule, array $parameters)
    {
        if (isset(self::$LARAVEL_RULES[$rule::class])) {
            $method = self::$LARAVEL_RULES[$rule::class];
            $this->$method($rule);
            return;
        }

        foreach (config('openapi.parsers.rules') as $ruleClass => $parserClass) {
            if ($rule instanceof $ruleClass) {
                app()->make($parserClass)->parse($this->parameter, $rule, $parameters, $this->rules);
            }
        }
    }

    /**
     * Parse the AnyOf rule.
     *
     * @param Rules\AnyOf $anyOf
     * @return void
     */
    public function parseCustomAnyOf($anyOf): void
    {
        // TODO: Implement AnyOf rule specific parsing if needed
    }

    /**
     * Parse the Can rule.
     *
     * @param Rules\Can $can
     * @return void
     */
    public function parseCustomCan($can): void
    {
        // TODO: Implement can rule specific parsing if needed
    }

    /**
     * Parse the DatabaseRule rule.
     *
     * @param Rules\DatabaseRule $databaseRule
     * @return void
     */
    public function parseCustomDatabaseRule($databaseRule): void
    {
        // TODO: Implement database rule specific parsing if needed
    }

    /**
     * Parse the Email rule.
     *
     * @param Rules\Email $email
     * @return void
     */
    public function parseCustomEmail($email): void
    {
        $this->parseEmail([]);
    }

    /**
     * Parse the Enum rule.
     *
     * @param Rules\Enum $enum
     * @return void
     */
    public function parseCustomEnum($enum): void
    {
        $type = Reflection::property($enum, 'type')->getValue($enum);

        if (Config::useRef()) {
            $this->parameter->ref((new EnumToRef($type))->toRef());
        } else {
            $this->parseEnum(collect($type::cases())->map(fn($case) => $case->value)->toArray());
        }
    }

    /**
     * Parse the File rule.
     *
     * @param Rules\File $file
     * @return void
     */
    public function parseCustomFile($file): void
    {
        // TODO: Implement image file specific parsing if needed
        $this->parseFile([]);
    }

    /**
     * Parse the ImageFile rule.
     *
     * @param Rules\ImageFile $imageFile
     * @return void
     */
    public function parseCustomImageFile($imageFile): void
    {
        // TODO: Implement image file specific parsing if needed
        $this->parseFile([]);
    }

    /**
     * Parse the Password rule.
     *
     * @param Rules\Password $password
     * @return void
     */
    public function parseCustomPassword($password): void
    {
        // TODO: Implement password specific parsing if needed
        $this->parsePassword([]);
    }
}

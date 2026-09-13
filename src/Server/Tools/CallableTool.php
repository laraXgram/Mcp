<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Server\Tools;

use BackedEnum;
use Generator;
use InvalidArgumentException;
use LaraGram\Container\Container;
use LaraGram\Contracts\JsonSchema\JsonSchema;
use LaraGram\Contracts\Support\Arrayable;
use LaraGram\JsonSchema\Types\Type;
use LaraGram\Mcp\Request;
use LaraGram\Mcp\Response;
use LaraGram\Mcp\ResponseFactory;
use LaraGram\Mcp\Server\Attributes\AsTool;
use LaraGram\Mcp\Server\Attributes\Description;
use LaraGram\Mcp\Server\Attributes\Name;
use LaraGram\Mcp\Server\Attributes\RequiresAbility;
use LaraGram\Mcp\Server\Attributes\Title;
use LaraGram\Mcp\Server\Tool;
use LaraGram\Mcp\Server\Tools\Annotations\ToolAnnotation;
use LaraGram\Support\Str;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionUnionType;

/**
 * A tool backed by a public method marked with #[AsTool], so existing application
 * code can be exposed without writing a Tool class. The method's #[Name], #[Title],
 * #[Description], tool annotations and #[RequiresAbility] attributes are honored,
 * and #[Description] may also describe its parameters.
 *
 *     $this->tools = [...CallableTool::fromClass(OrderService::class)];
 */
class CallableTool extends Tool
{
    protected const TYPES = [
        'int' => 'integer',
        'float' => 'number',
        'bool' => 'boolean',
        'string' => 'string',
        'array' => 'array',
    ];

    protected ReflectionMethod $reflection;

    /**
     * @param  class-string|object  $target
     */
    public function __construct(protected string|object $target, protected string $method)
    {
        $this->reflection = new ReflectionMethod($target, $method);

        if (! $this->reflection->isPublic() || $this->reflection->isStatic()) {
            throw new InvalidArgumentException("Method [{$this->reflection->class}::{$method}] must be a public, non-static method.");
        }
    }

    /**
     * Create a tool for every #[AsTool] method of the given class.
     *
     * @param  class-string|object  $target
     * @return array<int, static>
     */
    public static function fromClass(string|object $target): array
    {
        $tools = [];

        foreach ((new ReflectionClass($target))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if (! $method->isStatic() && $method->getAttributes(AsTool::class) !== []) {
                $tools[] = new static($target, $method->getName());
            }
        }

        return $tools;
    }

    public function name(): string
    {
        return $this->methodAttribute(Name::class)?->value ?? Str::snake($this->method);
    }

    public function title(): string
    {
        return $this->methodAttribute(Title::class)?->value ?? Str::headline($this->method);
    }

    public function description(): string
    {
        return $this->methodAttribute(Description::class)?->value ?? Str::headline($this->method);
    }

    /**
     * @return array<string, mixed>
     */
    public function annotations(): array
    {
        $annotations = [];

        foreach ($this->reflection->getAttributes() as $attribute) {
            $instance = $attribute->newInstance();

            if ($instance instanceof ToolAnnotation) {
                $annotations[$instance->key()] = $instance->value; // @phpstan-ignore property.notFound
            }
        }

        return $annotations;
    }

    /**
     * @return array<int, string>
     */
    public function abilities(): array
    {
        return array_map(
            fn ($attribute): string => $attribute->newInstance()->value,
            $this->reflection->getAttributes(RequiresAbility::class),
        );
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        $properties = [];

        foreach ($this->inputParameters() as $parameter) {
            $property = $this->parameterType($schema, $parameter);

            if (($description = $this->parameterDescription($parameter)) !== null) {
                $property->description($description);
            }

            $properties[$parameter->getName()] = $this->isRequired($parameter) ? $property->required() : $property;
        }

        return $properties;
    }

    public function handle(Request $request): mixed
    {
        $request->validate($this->rules());

        return $this->invoke($this->arguments($request));
    }

    /**
     * Get the validated, cast arguments keyed by parameter name.
     *
     * @return array<string, mixed>
     */
    protected function arguments(Request $request): array
    {
        $arguments = [];

        foreach ($this->inputParameters() as $parameter) {
            if ($request->has($parameter->getName())) {
                $arguments[$parameter->getName()] = $this->castArgument($parameter, $request->get($parameter->getName()));
            }
        }

        return $arguments;
    }

    /**
     * Call the method with the given arguments and convert its result to a response.
     *
     * @param  array<string, mixed>  $arguments
     */
    protected function invoke(array $arguments): mixed
    {
        $container = Container::getInstance();
        $target = is_string($this->target) ? $container->make($this->target) : $this->target;

        return $this->toResponse($container->call([$target, $this->method], $arguments));
    }

    /**
     * Parameters filled from the tool arguments; class-typed parameters are injected by the container.
     *
     * @return array<int, ReflectionParameter>
     */
    protected function inputParameters(): array
    {
        return array_values(array_filter(
            $this->reflection->getParameters(),
            fn (ReflectionParameter $parameter): bool => $this->enumClass($parameter) !== null || ! $this->isClassTyped($parameter),
        ));
    }

    protected function parameterType(JsonSchema $schema, ReflectionParameter $parameter): Type
    {
        if (($enum = $this->enumClass($parameter)) !== null) {
            $values = array_map(fn (BackedEnum $case): int|string => $case->value, $enum::cases());

            return (is_int($values[0] ?? null) ? $schema->integer() : $schema->string())->enum($values);
        }

        $type = $parameter->getType();

        $names = match (true) {
            $type instanceof ReflectionNamedType => [$type->getName()],
            $type instanceof ReflectionUnionType => array_map(fn ($type): string => (string) $type, $type->getTypes()),
            default => ['mixed'],
        };

        $types = array_values(array_unique(array_filter(array_map(fn (string $name): ?string => self::TYPES[$name] ?? null, $names))));

        return match (count($types)) {
            0 => $schema->union(['string', 'integer', 'number', 'boolean', 'object', 'array']),
            1 => match ($types[0]) {
                'integer' => $schema->integer(),
                'number' => $schema->number(),
                'boolean' => $schema->boolean(),
                'array' => $schema->array(),
                default => $schema->string(),
            },
            default => $schema->union($types),
        };
    }

    /**
     * @return array<string, array<int, string>>
     */
    protected function rules(): array
    {
        $rules = [];

        foreach ($this->inputParameters() as $parameter) {
            $rule = [$this->isRequired($parameter) ? 'required' : 'nullable'];

            if (($enum = $this->enumClass($parameter)) !== null) {
                $rule[] = 'in:'.implode(',', array_map(fn (BackedEnum $case): string => (string) $case->value, $enum::cases()));
            } elseif ($parameter->getType() instanceof ReflectionNamedType) {
                $rule[] = match ($parameter->getType()->getName()) {
                    'int' => 'integer',
                    'float' => 'numeric',
                    'bool' => 'boolean',
                    'string' => 'string',
                    'array' => 'array',
                    default => 'present',
                };
            }

            $rules[$parameter->getName()] = $rule;
        }

        return $rules;
    }

    protected function castArgument(ReflectionParameter $parameter, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (($enum = $this->enumClass($parameter)) !== null) {
            return $enum::from($value);
        }

        $type = $parameter->getType();

        return $type instanceof ReflectionNamedType ? match ($type->getName()) {
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            default => $value,
        } : $value;
    }

    protected function toResponse(mixed $result): mixed
    {
        return match (true) {
            $result instanceof Response, $result instanceof ResponseFactory, $result instanceof Generator => $result,
            is_string($result) => Response::text($result),
            $result === null => Response::text('Done.'),
            $result instanceof Arrayable => Response::structured(['result' => $result->toArray()]),
            default => Response::structured(['result' => $result]),
        };
    }

    protected function isRequired(ReflectionParameter $parameter): bool
    {
        return ! $parameter->isOptional() && ! $parameter->allowsNull();
    }

    protected function isClassTyped(ReflectionParameter $parameter): bool
    {
        $type = $parameter->getType();

        return $type instanceof ReflectionNamedType && ! $type->isBuiltin();
    }

    /**
     * @return class-string<BackedEnum>|null
     */
    protected function enumClass(ReflectionParameter $parameter): ?string
    {
        $type = $parameter->getType();

        return $type instanceof ReflectionNamedType && ! $type->isBuiltin() && is_subclass_of($type->getName(), BackedEnum::class)
            ? $type->getName()
            : null;
    }

    protected function parameterDescription(ReflectionParameter $parameter): ?string
    {
        $attributes = $parameter->getAttributes(Description::class);

        return $attributes === [] ? null : $attributes[0]->newInstance()->value;
    }

    /**
     * @template T of object
     *
     * @param  class-string<T>  $class
     * @return T|null
     */
    protected function methodAttribute(string $class): ?object
    {
        $attributes = $this->reflection->getAttributes($class);

        return $attributes === [] ? null : $attributes[0]->newInstance();
    }
}

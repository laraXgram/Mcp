<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Telegram\MTProto;

use LaraGram\Contracts\JsonSchema\JsonSchema;
use LaraGram\JsonSchema\Types\Type;
use LaraGram\Mcp\Request;
use LaraGram\Mcp\Response;
use LaraGram\Mcp\Server\Tools\CallableTool;
use LaraGram\MTProto\Contracts\Invoker;
use LaraGram\MTProto\Core\Client;
use LaraGram\Support\Str;
use ReflectionParameter;

/**
 * A curated tool backed by a high-level method of the MTProto Client, run through
 * the session's invoker (in-process, or forwarded to the pump process).
 */
class ClientMethodTool extends CallableTool
{
    /**
     * @var array<string, array{description: string, methods: array<int, string>, name?: string, readOnly?: bool, destructive?: bool, required?: array<int, string>, max?: array<string, int>}>
     */
    public const METHODS = [
        'getMe' => ['description' => 'Get the user account the session is logged in as.', 'methods' => ['users.getUsers'], 'readOnly' => true],
        'iterateDialogs' => ['name' => 'get_dialogs', 'description' => 'List the most recent chats (dialogs) of the account.', 'methods' => ['messages.getDialogs'], 'readOnly' => true, 'required' => ['limit'], 'max' => ['limit' => 200, 'pageSize' => 100]],
        'iterateHistory' => ['name' => 'get_history', 'description' => 'Get the most recent messages of a chat, newest first.', 'methods' => ['messages.getHistory'], 'readOnly' => true, 'required' => ['limit'], 'max' => ['limit' => 200, 'pageSize' => 100]],
        'getMessages' => ['description' => 'Get messages of a chat by their ids.', 'methods' => ['messages.getMessages'], 'readOnly' => true],
        'searchMessages' => ['description' => 'Search the messages of a chat. "params" accepts raw messages.search parameters such as "filter" or "limit".', 'methods' => ['messages.search'], 'readOnly' => true],
        'searchGlobal' => ['description' => 'Search messages across all chats of the account.', 'methods' => ['messages.searchGlobal'], 'readOnly' => true],
        'getFullChat' => ['description' => 'Get full information about a user, group or channel.', 'methods' => ['messages.getFullChat'], 'readOnly' => true],
        'getChatMember' => ['description' => 'Get a member of a group or channel.', 'methods' => ['channels.getParticipant'], 'readOnly' => true],
        'sendPhoto' => ['description' => 'Send a photo from a local file.', 'methods' => ['messages.sendMedia']],
        'sendDocument' => ['description' => 'Send a local file as a document.', 'methods' => ['messages.sendMedia']],
        'sendVideo' => ['description' => 'Send a video from a local file.', 'methods' => ['messages.sendMedia']],
        'sendAudio' => ['description' => 'Send an audio file from a local file.', 'methods' => ['messages.sendMedia']],
        'sendVoice' => ['description' => 'Send a voice message from a local file.', 'methods' => ['messages.sendMedia']],
        'sendLocation' => ['description' => 'Send a location.', 'methods' => ['messages.sendMedia']],
        'sendContact' => ['description' => 'Send a contact.', 'methods' => ['messages.sendMedia']],
        'sendPoll' => ['description' => 'Send a poll.', 'methods' => ['messages.sendMedia']],
        'forwardMessages' => ['description' => 'Forward messages from one chat to another.', 'methods' => ['messages.forwardMessages']],
        'editMessage' => ['description' => 'Edit the text of a message.', 'methods' => ['messages.editMessage']],
        'markAsRead' => ['description' => 'Mark the messages of a chat as read.', 'methods' => ['messages.readHistory']],
        'sendChatAction' => ['description' => 'Show a chat action such as "typing" or "upload_photo".', 'methods' => ['messages.setTyping']],
        'pinMessage' => ['description' => 'Pin a message in a chat.', 'methods' => ['messages.updatePinnedMessage']],
        'unpinMessage' => ['description' => 'Unpin a message in a chat.', 'methods' => ['messages.updatePinnedMessage']],
        'deleteChatMessages' => ['description' => 'Delete messages from a chat.', 'methods' => ['messages.deleteMessages'], 'destructive' => true],
        'joinChat' => ['description' => 'Join a public group or channel.', 'methods' => ['channels.joinChannel']],
        'leaveChat' => ['description' => 'Leave a group or channel.', 'methods' => ['channels.leaveChannel'], 'destructive' => true],
        'banChatMember' => ['description' => 'Ban a member from a group or channel, optionally until a unix time.', 'methods' => ['channels.editBanned'], 'destructive' => true],
        'unbanChatMember' => ['description' => 'Lift the ban of a member in a group or channel.', 'methods' => ['channels.editBanned']],
    ];

    /** Parameters holding peers, checked against the peer allowlist. */
    public const PEER_PARAMETERS = ['peer', 'fromPeer', 'toPeer', 'user'];

    /** The session requested for the current call. */
    protected ?string $session = null;

    /** The current request. */
    protected ?Request $request = null;

    /**
     * @param  array{description: string, methods: array<int, string>, name?: string, readOnly?: bool, destructive?: bool, required?: array<int, string>, max?: array<string, int>}  $definition
     */
    public function __construct(protected Toolset $toolset, string $method, protected array $definition)
    {
        parent::__construct(Client::class, $method);
    }

    public function name(): string
    {
        return $this->toolset->prefixValue().($this->definition['name'] ?? Str::snake($this->method));
    }

    public function title(): string
    {
        return Str::headline($this->definition['name'] ?? $this->method);
    }

    public function description(): string
    {
        $description = $this->definition['description'];

        if (array_intersect(self::PEER_PARAMETERS, array_map(fn (ReflectionParameter $p): string => $p->getName(), $this->inputParameters())) !== []) {
            $description .= ' Peers may be given as @username, phone number or numeric id.';
        }

        return $description;
    }

    /**
     * @return array<string, bool>
     */
    public function annotations(): array
    {
        return [
            'readOnlyHint' => $this->definition['readOnly'] ?? false,
            'destructiveHint' => $this->definition['destructive'] ?? false,
            'openWorldHint' => true,
        ];
    }

    /**
     * @return array<int, string>
     */
    public function abilities(): array
    {
        return array_merge(...array_map($this->toolset->abilitiesFor(...), $this->definition['methods']));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        $properties = parent::schema($schema);

        if ($this->toolset->selectsSession()) {
            $properties['session'] = $schema->string()->enum($this->toolset->sessions())->description('The MTProto session to use.');
        }

        return $properties;
    }

    public function handle(Request $request): mixed
    {
        $session = $request->get('session');

        $this->session = is_string($session) ? $session : null;
        $this->request = $request;

        return parent::handle($request);
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    protected function invoke(array $arguments): mixed
    {
        foreach ($this->definition['max'] ?? [] as $name => $max) {
            if (isset($arguments[$name])) {
                $arguments[$name] = max(1, min($max, (int) $arguments[$name]));
            }
        }

        if (($peer = $this->toolset->disallowedPeer($arguments, self::PEER_PARAMETERS)) !== null) {
            return Response::error("Peer [{$peer}] is not allowed for this server.");
        }

        if (isset($arguments['path']) && ! $this->toolset->fileAllowed((string) $arguments['path'])) {
            return Response::error('The file does not exist or is outside the allowed directories.');
        }

        if ($this->request !== null && ($confirmation = $this->toolset->confirmation($this->request, $this->method, $arguments, $this->definition['destructive'] ?? false)) !== null) {
            return $confirmation;
        }

        return $this->toolset->run($this->session, fn (Invoker $invoker): mixed => $invoker->call($this->method, $arguments));
    }

    /**
     * Callbacks (e.g. upload progress) cannot be passed by a client.
     *
     * @return array<int, ReflectionParameter>
     */
    protected function inputParameters(): array
    {
        return array_values(array_filter(
            parent::inputParameters(),
            fn (ReflectionParameter $parameter): bool => ! in_array((string) $parameter->getType(), ['callable', '?callable', 'Closure', '?Closure'], true),
        ));
    }

    protected function parameterType(JsonSchema $schema, ReflectionParameter $parameter): Type
    {
        return match (true) {
            in_array($parameter->getName(), self::PEER_PARAMETERS, true) => $schema->union(['integer', 'string']),
            $parameter->getName() === 'params' => $schema->object()->description('Extra raw TL parameters.'),
            $parameter->getName() === 'path' => $schema->string()->description('The path of a local file inside the allowed directories.'),
            default => parent::parameterType($schema, $parameter),
        };
    }

    protected function isRequired(ReflectionParameter $parameter): bool
    {
        return parent::isRequired($parameter) || in_array($parameter->getName(), $this->definition['required'] ?? [], true);
    }
}

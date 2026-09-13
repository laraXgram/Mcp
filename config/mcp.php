<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Expose Errors
    |--------------------------------------------------------------------------
    |
    | Whether exception messages are sent to MCP clients (and unexpected
    | exceptions rethrown). Defaults to "app.debug" when null. Keep it off
    | when the application runs in debug mode but serves real clients.
    |
    */

    'expose_errors' => env('MCP_EXPOSE_ERRORS'),

    /*
    |--------------------------------------------------------------------------
    | Redirect Domains
    |--------------------------------------------------------------------------
    |
    | These domains are the domains that OAuth clients are permitted to use
    | for redirect URIs. Each domain should be specified with its scheme
    | and host. Domains not in this list will raise validation errors.
    |
    | An "*" may be used to allow all domains.
    |
    */

    'redirect_domains' => [
        '*',
        // 'https://example.com',
        // 'http://localhost',
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed Custom Schemes
    |--------------------------------------------------------------------------
    |
    | Native desktop OAuth clients like Cursor and VS Code use private-use URI
    | schemes (RFC 8252) for redirect callbacks instead of standard schemes
    | like HTTPS. Here, you may list which custom schemes you will allow.
    |
    */

    'custom_schemes' => [
        // 'claude',
        // 'cursor',
        // 'vscode',
    ],

    /*
    |--------------------------------------------------------------------------
    | Authorization Server
    |--------------------------------------------------------------------------
    |
    | Here you may configure the OAuth authorization server issuer identifier
    | per RFC 8414. This value appears in your protected resource and auth
    | server metadata endpoints. When null, this defaults to `url('/')`.
    |
    */

    'authorization_server' => null,

    /*
    |--------------------------------------------------------------------------
    | Tool Search
    |--------------------------------------------------------------------------
    |
    | Here you may configure the limits enforced during tool search. The maximum
    | number of tool calls limits how many tools each search request can run
    | while the maximum output bytes value caps the size of every result.
    |
    */

    'tool_search' => [
        'max_tool_calls' => 10,
        'max_output_bytes' => 65_536,
    ],

    /*
    |--------------------------------------------------------------------------
    | Subscriptions
    |--------------------------------------------------------------------------
    |
    | Change notifications published with Mcp::toolsListChanged(), etc. are
    | kept in this cache store so every process can deliver them. Streams
    | poll it at the given interval and close gracefully after the timeout
    | (clients then listen again). Idle HTTP streams send a keep-alive
    | comment so disconnected clients free the worker. Use a shared store
    | such as redis.
    |
    */

    'subscriptions' => [
        'store' => env('MCP_SUBSCRIPTIONS_STORE'),
        'poll_interval' => 1.0,
        'keep_alive' => 15,
        'timeout' => 300,
    ],

];

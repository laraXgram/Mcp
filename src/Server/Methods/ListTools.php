<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Server\Methods;

use LaraGram\Mcp\Server\Contracts\Method;
use LaraGram\Mcp\Server\Pagination\CursorPaginator;
use LaraGram\Mcp\Server\ServerContext;
use LaraGram\Mcp\Transport\JsonRpcRequest;
use LaraGram\Mcp\Transport\JsonRpcResponse;

class ListTools implements Method
{
    public function handle(JsonRpcRequest $request, ServerContext $context): JsonRpcResponse
    {
        $paginator = new CursorPaginator(
            items: $context->tools(),
            perPage: $context->perPage($request->get('per_page')),
            cursor: $request->cursor(),
        );

        return JsonRpcResponse::result($request->id, $paginator->paginate('tools'));
    }
}

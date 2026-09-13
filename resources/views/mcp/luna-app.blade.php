<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @if(! empty($title))
    <title>{{ $title }}</title>
    @endif
    <script>{!! app('mcp.sdk') !!}</script>
    @if(($bundle['css'] ?? '') !== '')
    <style>{!! $bundle['css'] !!}</style>
    @endif
    {!! $ssr?->head ?? '' !!}
</head>
<body>
    @if($ssr)
    {!! $ssr->body !!}
    @else
    <script data-page="app" type="application/json">{!! json_encode($page, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
    <div id="app"></div>
    @endif
    <script type="module">{!! str_replace('</script', '<\/script', $bundle['js'] ?? '') !!}</script>
</body>
</html>

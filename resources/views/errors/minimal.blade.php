<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('code') · @yield('heading')</title>
    <style>body{margin:0;background:#f8f6ef;color:#173b33;font:18px/1.6 system-ui,sans-serif}main{max-width:680px;margin:12vh auto;padding:24px}h1{font-size:clamp(28px,5vw,44px);line-height:1.15}a{color:inherit;text-underline-offset:4px}nav{display:flex;gap:24px;flex-wrap:wrap}.code{font-weight:700;color:#a64c2a}</style>
</head>
<body><main>
    <p class="code">Journal notice · @yield('code')</p>
    <h1>@yield('heading')</h1><p>@yield('message')</p>
    <nav><a href="/">Return home</a><a href="/search">Search the archive</a></nav>
</main>
@include('components.error-popup', ['popupErrors' => [['code' => $__env->yieldContent('error-code') ?: \App\Support\ErrorCodes::forStatus((int) $__env->yieldContent('code')), 'message' => $__env->yieldContent('message')]]])
</body>
</html>

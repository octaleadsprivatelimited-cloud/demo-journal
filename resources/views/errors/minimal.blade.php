@extends('layouts.public')

@section('title')@yield('code') · @yield('heading')@endsection
@section('description')@yield('message')@endsection
@section('robots', 'noindex, nofollow')

@section('content')
    <section class="error-page">
        <div class="error-ornament" aria-hidden="true"><span>@yield('code')</span></div>
        <div class="container error-content">
            <p class="eyebrow">Journal notice · @yield('code')</p>
            <h1>@yield('heading')</h1>
            <p>@yield('message')</p>
            <div><a class="button button-primary" href="{{ route('home') }}">Return home</a><a class="button button-outline" href="{{ route('search') }}">Search the archive</a></div>
        </div>
    </section>
@endsection

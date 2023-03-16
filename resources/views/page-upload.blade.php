@extends('layouts.app')

@section('header')
{{--    @include('partials.sections.header')--}}
@endsection

@section('content')
    <main class="main">
        @if(!empty(get_the_content()))
            @include('partials.content.content-page')
        @endif
    </main>
@endsection

@section('footer')
{{--    @include('partials.sections.footer')--}}
@endsection

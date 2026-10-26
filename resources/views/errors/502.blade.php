@extends('errors::layout')

@section('code', '502')
@section('title', 'Bad gateway')
@section('message', 'The server received an invalid response from an upstream service. Please try again shortly.')

@section('actions')
    <a class="btn btn-primary" href="javascript:location.reload()">Try again</a><a class="btn" href="{{ url('/') }}">Go to dashboard</a>
@endsection

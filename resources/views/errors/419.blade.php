@extends('errors::layout')

@section('code', '419')
@section('title', 'Page expired')
@section('message', 'Your session timed out. Refresh the page and try again.')

@section('actions')
    <a class="btn btn-primary" href="javascript:location.reload()">Try again</a><a class="btn" href="{{ url('/') }}">Go to dashboard</a>
@endsection

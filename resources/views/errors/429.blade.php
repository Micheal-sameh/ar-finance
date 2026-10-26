@extends('errors::layout')

@section('code', '429')
@section('title', 'Too many requests')
@section('message', 'You are doing that too often. Please wait a moment and try again.')

@section('actions')
    <a class="btn btn-primary" href="javascript:location.reload()">Try again</a><a class="btn" href="{{ url('/') }}">Go to dashboard</a>
@endsection

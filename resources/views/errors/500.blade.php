@extends('errors::layout')

@section('code', '500')
@section('title', 'Something went wrong')
@section('message', 'An unexpected error occurred on our side. Please try again in a moment.')

@section('actions')
    <a class="btn btn-primary" href="javascript:location.reload()">Try again</a><a class="btn" href="{{ url('/') }}">Go to dashboard</a>
@endsection

@extends('errors::layout')

@section('code', '504')
@section('title', 'Gateway timeout')
@section('message', 'The server took too long to respond. Please try again.')

@section('actions')
    <a class="btn btn-primary" href="javascript:location.reload()">Try again</a><a class="btn" href="{{ url('/') }}">Go to dashboard</a>
@endsection

@extends('errors::layout')

@section('code', '503')
@section('title', 'Down for maintenance')
@section('message', 'We are performing scheduled maintenance and will be back shortly. Thank you for your patience.')

@section('actions')
    <a class="btn btn-primary" href="javascript:location.reload()">Try again</a><a class="btn" href="{{ url('/') }}">Go to dashboard</a>
@endsection

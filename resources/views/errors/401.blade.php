@extends('errors::layout')

@section('code', '401')
@section('title', 'Sign in required')
@section('message', 'You need to sign in to view this page.')

@section('actions')
    <a class="btn btn-primary" href="{{ url('/login') }}">Sign in</a>
@endsection

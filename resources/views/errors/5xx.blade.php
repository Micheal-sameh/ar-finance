@extends('errors::layout')

@section('code', $exception->getStatusCode())
@section('title', 'Server error')
@section('message', 'The server could not complete your request. Please try again in a moment.')

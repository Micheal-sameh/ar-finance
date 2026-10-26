@extends('errors::layout')

@section('code', $exception->getStatusCode())
@section('title', 'Request error')
@section('message', 'We could not process this request. Please go back and try again.')

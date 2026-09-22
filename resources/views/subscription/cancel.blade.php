@extends('layouts.master')
@section('title', __('site.subscription_checkout.cancel_title'))
@section('content')
<div class="container text-center py-5">
    <h2 class="text-danger">{{ __('site.subscription_checkout.cancel_heading') }}</h2>
    <p>{{ __('site.subscription_checkout.cancel_text') }}</p>
    <a href="/" class="btn btn-secondary mt-3">{{ __('site.subscription_checkout.cancel_cta') }}</a>
</div>
@endsection
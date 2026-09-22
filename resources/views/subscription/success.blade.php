@extends('layouts.master')

@section('content')
<div class="container text-center py-5">
    <h2 class="text-success">{{ __('site.subscription_checkout.success_title') }}</h2>
    <p>{{ __('site.subscription_checkout.success_text') }}</p>
    <a href="/" class="btn btn-primary mt-3">{{ __('site.subscription_checkout.success_cta') }}</a>
</div>
@endsection
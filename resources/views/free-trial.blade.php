@extends('layouts.master')

@section('title', 'Prueba gratuita')

@section('content')

<style>

    .site-header .site-main-menu li > a{
        color: #000000;
    }
</style>
    <section class="page-banner-contact">
        <div class="container">
            <div class="row">
                <div class="col-lg-8">
                    <div class="page-title-wrapper">
                        <div class="page-title-inner">
                            <h1 class="page-title">Empieza tu prueba gratuita</h1>
                            <p>Déjanos tus datos y te contactamos para activar tu prueba.</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="animate-element-contact">
                        <img src="media/animated/001.png" alt="" class="wow pixFadeDown" data-wow-duration="1s">
                        <img src="media/animated/002.png" alt="" class="wow pixFadeUp" data-wow-duration="2s">
                        <img src="media/animated/003.png" alt="" class="wow pixFadeLeft" data-wow-delay="0.3s" data-wow-duration="2s">
                        <img src="media/animated/004.png" alt="man" class="wow pixFadeUp" data-wow-duration="2s">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="contactus">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <div class="contact-infos">
                        <div class="contact-info">
                            <h3 class="title">Prueba gratuita</h3>
                            <div class="info">
                                <i class="ei ei-icon_mail_alt"></i>
                                <span>contact@fakturalista.com</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="contact-froms">
                        @if (session('status'))
                            <div class="alert alert-success mb-4">
                                {{ session('status') }}
                            </div>
                        @endif
                        @if ($errors->any())
                            <div class="alert alert-danger mb-4">
                                Por favor revisa los campos e intenta de nuevo.
                            </div>
                        @endif
                        <form method="POST" action="{{ route('free-trial.send') }}" class="contact-form" data-pixsaas="contact-froms">
                            @csrf
                            <div class="row">
                                <div class="col-md-6">
                                    <input type="text" name="name" placeholder="Nombre" value="{{ old('name') }}" required>
                                </div>

                                <div class="col-md-6">
                                    <input type="email" name="email" placeholder="Correo Electrónico" value="{{ old('email') }}" required>
                                </div>
                            </div>

                            <input type="text" name="company" placeholder="Nombre de la empresa" value="{{ old('company') }}" required>

                            <button type="submit" class="pix-btn submit-btn">
                                <span class="btn-text">Solicitar prueba</span>
                                <i class="fas fa-spinner fa-spin"></i>
                            </button>
                            <input type="hidden" name="recaptcha_response" id="recaptchaResponse">

                            <div class="row">
                                <div class="form-result alert">
                                    <div class="content"></div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

@endsection

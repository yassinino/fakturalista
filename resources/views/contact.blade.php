@extends('layouts.master')

@section('title', 'Contato')

@section('content')

<style>

    .site-header .site-main-menu li > a{
        color: #000000;
    }
</style>


        <!--==========================-->
        <!--=         Banner         =-->
        <!--==========================-->
        <section class="page-banner-contact">
            <div class="container">
                <div class="row">
                    <div class="col-lg-8">
                        <div class="page-title-wrapper">
                            <div class="page-title-inner">
                                <h1 class="page-title">¿En qué podemos ayudarte?</h1>

                                <p>
                                   ¿Tienes alguna pregunta, sugerencia o necesitas ayuda? <br>
                                   Estamos aquí para ayudarte.
                                </p>
                                <p>
                                   Nuestro equipo estará encantado de responderte y acompañarte en el uso de la aplicación.
                                </p>
                            </div>
                            <!-- /.page-title-inner -->
                        </div>
                        <!-- /.page-title-wrapper -->
                    </div>
                    <!-- /.col-lg-8 -->

                    <div class="col-lg-4">
                        <div class="animate-element-contact">
                            <img src="media/animated/001.png" alt="" class="wow pixFadeDown" data-wow-duration="1s">
                            <img src="media/animated/002.png" alt="" class="wow pixFadeUp" data-wow-duration="2s">
                            <img src="media/animated/003.png" alt="" class="wow pixFadeLeft" data-wow-delay="0.3s" data-wow-duration="2s">
                            <img src="media/animated/004.png" alt="man" class="wow pixFadeUp" data-wow-duration="2s">
                        </div>
                        <!-- /.animate-element-contact -->
                    </div>
                    <!-- /.col-lg-4 -->
                </div>
                <!-- /.row -->

            </div>
            <!-- /.container -->

            <svg class="circle" data-parallax='{"y" : 250}' xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="950px" height="950px">
                <path fill-rule="evenodd" stroke="rgb(250, 112, 112)" stroke-width="100px" stroke-linecap="butt" stroke-linejoin="miter" opacity="0.051" fill="none" d="M450.000,50.000 C670.914,50.000 850.000,229.086 850.000,450.000 C850.000,670.914 670.914,850.000 450.000,850.000 C229.086,850.000 50.000,670.914 50.000,450.000 C50.000,229.086 229.086,50.000 450.000,50.000 Z" />
            </svg>

            <ul class="animate-ball">
                <li class="ball"></li>
                <li class="ball"></li>
                <li class="ball"></li>
                <li class="ball"></li>
                <li class="ball"></li>
            </ul>
        </section>
        <!-- /.page-banner -->

        <!--===========================-->
        <!--=         Contact         =-->
        <!--===========================-->
        <section class="contactus">
            <div class="container">
                <div class="row">
                    <div class="col-md-4">
                        <div class="contact-infos">

                            <div class="contact-info">
                                <h3 class="title">Contáctanos</h3>
                                <div class="info">
                                    <i class="ei ei-icon_mail_alt"></i>
                                    <span>contact@fakturalista.com</span>
                                </div>
                                 <div class="info">
                                    <i class="ei ei-icon_phone"></i>
                                    <span>+34 912 345 678</span>
                                </div>
                            </div>
                            <!-- /.contact-info -->
                        </div>
                        <!-- /.contact-infos -->
                    </div>
                    <!-- /.col-md-4 -->
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
                            <form method="POST" action="{{ route('contact.send') }}" class="contact-form" data-pixsaas="contact-froms">
                                @csrf
                                <div class="row">
                                    <div class="col-md-6">
                                        <input type="text" name="name" placeholder="Nombre" value="{{ old('name') }}" required>
                                    </div>

                                    <div class="col-md-6">
                                        <input type="email" name="email" placeholder="Correo Electrónico" value="{{ old('email') }}" required>
                                    </div>
                                </div>

                                <input type="text" name="subject" placeholder="Asunto" value="{{ old('subject') }}">
                                <textarea name="content" placeholder="Tu Comentario" required>{{ old('content') }}</textarea>

                                <button type="submit" class="pix-btn submit-btn">
                                    <span class="btn-text">Enviar Tu Mensaje</span>
                                    <i class="fas fa-spinner fa-spin"></i>
                                </button>
                                <input type="hidden" name="recaptcha_response" id="recaptchaResponse">


                                <div class="row">
                                    <div class="form-result alert">
                                        <div class="content"></div>
                                    </div>
                                </div>
                            </form>
                            <!-- /.contact-froms -->
                        </div>
                        <!-- /.faq-froms -->
                    </div>
                    <!-- /.col-md-8 -->
                </div>
                <!-- /.row -->
            </div>
            <!-- /.container -->
        </section>
        <!-- /.contactus -->

@endsection

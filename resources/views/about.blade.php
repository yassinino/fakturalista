@extends('layouts.master')

@section('title', 'Sobre Fakturalista')

@section('content')

<style>
    .site-header .site-main-menu li > a{
        color: #000000;
    }
</style>

        <!--==========================-->
        <!--=         Banner         =-->
        <!--==========================-->
        <section class="page-banner">
            <div class="container">
                <div class="page-title-wrapper">
                    <h1 class="page-title">Sobre Fakturalista</h1>
                    <p>
                        Fakturalista es una plataforma web de facturacion y gestion pensada para aut&oacute;nomos y pymes.
                        Crea, env&iacute;a y controla tus facturas en minutos, con clientes, productos e informes en un solo lugar.
                    </p>
                    <p>
                        Te damos 1 mes de prueba gratis para que pruebes todo sin presi&oacute;n.
                    </p>
                    <a href="{{ url('/free-trial') }}" class="pix-btn">Prueba gratis 1 mes</a>
                </div>
                <!-- /.page-title-wrapper -->
            </div>
            <!-- /.container -->

            <svg class="circle" data-parallax='{"x" : -200}' xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="950px" height="950px">
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
        <!--=         Feature         =-->
        <!--===========================-->
        <section class="featured">
            <div class="container">
                <div class="section-title text-center wow pixFade">
                    <h2 class="title">Todo lo que necesitas para facturar mejor</h2>
                </div>
                <!-- /.section-title -->

                <div class="row">
                    <div class="col-md-4">
                        <div class="saaspik-icon-box-wrapper style-one wow pixFadeLeft" data-wow-delay="0.3s">
                            <div class="saaspik-icon-box-icon">
                                <img src="{{ url('assets/icon_1.png') }}" alt="">
                            </div>
                            <div class="pixsass-icon-box-content">
                                <h3 class="pixsass-icon-box-title"><a href="#">Facturas profesionales en segundos</a></h3>
                                <p>Plantillas claras, c&aacute;lculos autom&aacute;ticos y env&iacute;o r&aacute;pido.</p>
                            </div>
                        </div>
                        <!-- /.pixsass-box style-one -->
                    </div>
                    <!-- /.col-md-4 -->

                    <div class="col-md-4">
                        <div class="saaspik-icon-box-wrapper style-one wow pixFadeLeft" data-wow-delay="0.5s">
                            <div class="saaspik-icon-box-icon">
                                <img src="{{ url('assets/icon_2.png') }}" alt="">
                            </div>
                            <div class="pixsass-icon-box-content">
                                <h3 class="pixsass-icon-box-title"><a href="#">Clientes y productos siempre ordenados</a></h3>
                                <p>Accede a tu base de datos, reutiliza datos y ahorra tiempo.</p>
                            </div>
                        </div>
                        <!-- /.pixsass-box style-one -->
                    </div>
                    <!-- /.col-md-4 -->

                    <div class="col-md-4">
                        <div class="saaspik-icon-box-wrapper style-one wow pixFadeLeft" data-wow-delay="0.7s">
                            <div class="saaspik-icon-box-icon">
                                <img src="{{ url('assets/icon_3.png') }}" alt="">
                            </div>
                            <div class="pixsass-icon-box-content">
                                <h3 class="pixsass-icon-box-title"><a href="#">Informes claros para decidir mejor</a></h3>
                                <p>Control de ingresos, gastos y rendimiento en tiempo real.</p>
                            </div>
                        </div>
                        <!-- /.pixsass-box style-one -->
                    </div>
                    <!-- /.col-md-4 -->
                </div>
                <!-- /.row -->
            </div>
            <!-- /.container -->
        </section>
        <!-- /.featured -->

        <!--=================================-->
        <!--=         Editor Design         =-->
        <!--=================================-->
        <section class="editor-design">
            <div class="container">
                <div class="row">
                    <div class="editure-feature-image wow pixFadeRight">
                        <div class="image-one" data-parallax='{"x" : 30}'>
                            <img src="{{ url('assets/section_1.png') }}" class="wow pixFadeRight" data-wow-delay="0.3s" alt="feature-image">
                        </div>
                    </div>

                    <div class="col-lg-6 offset-lg-6">
                        <div class="editor-content">
                            <div class="section-title style-two">
                                <h2 class="title wow pixFadeUp" data-wow-delay="0.3s">
                                    Facturacion simple, resultados grandes
                                </h2>
                            </div>

                            <div class="description wow pixFadeUp" data-wow-delay="0.7s">
                                <p>
                                    Centraliza tu facturacion, pagos y reportes. Con Fakturalista reduces errores,
                                    ganas tiempo y puedes dedicarte a hacer crecer tu negocio.
                                </p>
                                <a href="{{ url('/free-trial') }}" class="pix-btn wow pixFadeUp" data-wow-delay="0.9s">
                                    Quiero mi prueba gratis
                                </a>
                            </div>
                        </div>
                        <!-- /.editor-content -->
                    </div>
                    <!-- /.col-lg-6 -->
                </div>
                <!-- /.row -->
            </div>
            <!-- /.container -->
            <div class="shape-bg">
                <img src="media/background/shape_bg.png" class="wow fadeInLeft" alt="shape-bg">
            </div>
        </section>
        <!-- /.editor-design -->

        <!--===================================-->
        <!--=         Genera Informes         =-->
        <!--===================================-->
        <section class="genera-informes">
            <div class="container">
                <div class="row">
                    <div class="col-lg-6 pix-order-one">
                        <div class="section-title style-two">
                            <h2 class="title wow pixFadeUp">
                                Transparencia total en cada movimiento
                            </h2>

                            <p class="wow pixFadeUp" data-wow-delay="0.3s">
                                Consulta tus ventas, controla impuestos y crea reportes con un clic.
                                Todo queda organizado y listo para compartir con tu gestor&iacute;a.
                            </p>
                        </div>
                        <!-- /.section-title style-two -->

                        <ul class="list-items wow pixFadeUp" data-wow-delay="0.4s">
                            <li>Acceso inmediato a tus datos</li>
                            <li>Historial de facturas y clientes</li>
                            <li>Exportaci&oacute;n en PDF</li>
                        </ul>
                    </div>
                    <!-- /.col-lg-6 -->

                    <div class="informes-feature-image">
                        <div class="image-one" data-parallax='{"y" : 20}'>
                            <img src="{{ url('assets/section_2.png') }}" class="wow pixFadeDown" alt="informes">
                        </div>
                    </div>
                </div>
                <!-- /.row -->
            </div>
            <!-- /.container -->

            <div class="shape-bg">
                <img src="media/background/shape.png" class="wow fadeInRight" alt="shape-bg">
            </div>
        </section>
        <!-- /.genera-informes -->

        <!--==================================-->
        <!--=         Call To Action         =-->
        <!--==================================-->
        <section class="call-to-action">
            <div class="overlay-bg"><img src="media/background/ellipse.png" alt="bg"></div>
            <div class="container">
                <div class="action-content text-center wow pixFadeUp">
                    <h2 class="title">
                        Prueba Fakturalista gratis durante 1 mes
                    </h2>

                    <p>
                        Empieza hoy, crea tu primera factura y decide con calma. La prueba es r&aacute;pida de activar.
                    </p>

                    <a href="{{ url('/free-trial') }}" class="pix-btn btn-light">Solicitar prueba</a>
                </div>
                <!-- /.action-content -->
            </div>
            <!-- /.container -->

            <div class="scroll-circle">
                <img src="media/background/circle13.png" data-parallax='{"y" : -130}' alt="circle">
            </div>
            <!-- /.scroll-circle -->
        </section>
        <!-- /.call-to-action -->

@endsection

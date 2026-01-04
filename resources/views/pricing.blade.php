@extends('layouts.master')

@section('title', 'Precios')

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
                    <h1 class="page-title">Precios</h1>
                    <p>
                      Empieza gratis y elige el plan que mejor se adapte a tu negocio. Sin letra pequeña.
                    </p>
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



        <!--==============================-->
        <!--=         Pricin Two         =-->
        <!--==============================-->
        <section class="pricing-two-single">
            <div class="container">
                <div class="section-title text-center">
                    <h3 class="sub-title wow pixFadeUp">Planes</h3>
                    <h2 class="title wow pixFadeUp" data-wow-delay="0.3s">
                      Planes simples para facturar sin complicaciones
                    </h2>
                </div>
                <!-- /.section-title -->
                {{-- <nav class="pricing-tab wow pixFadeUp" data-wow-delay="0.4s">
                    <span class="monthly_tab_title tab-btn">
                        Monthly
                    </span>
                    <span class="pricing-tab-switcher"></span>
                    <span class="annual_tab_title tab-btn">
                        Annual
                    </span>
                </nav> --}}

                <div class="row advanced-pricing-table">
                    <div class="col-lg-4">
                        <div class="pricing-table style-two wow pixFadeLeft" data-wow-delay="0.5s">
                            <div class="pricing-header pricing-amount">
                                <div class="annual_price">
                                    <h2 class="price">$0.00</h2>
                                </div>
                                <!-- /.annual_price -->

                                <div class="monthly_price">
                                    <h2 class="price">9€</h2>
                                </div>
                                <!-- /.monthly_price -->

                                <h3 class="price-title">Básico</h3>
                                <p>por mes</p>
                            </div>
                            <!-- /.pricing-header -->

                            <ul class="price-feture">
                                <li class="have">10 Facturas</li>
                                <li class="have">1 Usuario</li>
                                <li class="have">15 Clientes</li>
                                <li class="have">Soporte por email</li>
                                <li class="have">Historial de facturas</li>
                                <li class="have">Personalización de template PDF</li>
                            </ul>

                            <div class="action text-left">
                                <a href="{{ url('/free-trial') }}" class="pix-btn mb-2">Prueba gratis</a>
                                <a href="#" class="pix-btn btn-outline">Elegir este plan</a>
                            </div>
                        </div>
                        <!-- /.pricing-table -->
                    </div>
                    <!-- /.col-lg-4 -->

                    <div class="col-lg-4">
                        <div class="pricing-table color-two style-two featured wow pixFadeLeft" data-wow-delay="0.7s">
                            <div class="trend">
                                <p>Popular</p>
                            </div>


                            <div class="pricing-header pricing-amount">
                                <div class="annual_price">
                                    <h2 class="price">$80.50</h2>
                                </div>
                                <!-- /.annual_price -->

                                <div class="monthly_price">
                                    <h2 class="price">19€</h2>
                                </div>
                                <!-- /.monthly_price -->

                                <h3 class="price-title">Profesional</h3>
                                <p>por mes</p>
                            </div>
                            <!-- /.pricing-header -->

                            <ul class="price-feture">
                                <li class="have">100 Facturas</li>
                                <li class="have">3 Usuarios</li>
                                <li class="have">100 Clientes</li>
                                <li class="have">Soporte completo</li>
                                <li class="have">Historial de facturas</li>
                                <li class="have">Personalización de template PDF</li>
                            </ul>

                            <div class="action text-left">
                                <a href="{{ url('/free-trial') }}" class="pix-btn mb-2">Prueba gratis</a>
                                <a href="#" class="pix-btn btn-outline">Elegir este plan</a>
                            </div>
                        </div>
                        <!-- /.pricing-table -->
                    </div>
                    <!-- /.col-lg-4 -->

                    <div class="col-lg-4">
                        <div class="pricing-table color-three style-two  wow pixFadeLeft" data-wow-delay="0.9s">


                            <div class="pricing-header pricing-amount">
                                <div class="annual_price">
                                    <h2 class="price">$180.70</h2>
                                </div>
                                <!-- /.annual_price -->

                                <div class="monthly_price">
                                    <h2 class="price">29€</h2>
                                </div>
                                <!-- /.monthly_price -->

                                <h3 class="price-title">Empresa</h3>
                                <p>por mes</p>
                            </div>
                            <!-- /.pricing-header -->

                            <ul class="price-feture">
                                <li class="have">Facturas Ilimitadas</li>
                                <li class="have">10 Usuarios</li>
                                <li class="have">1000 Clientes</li>
                                <li class="have">Soporte prioritario</li>
                                <li class="have">Historial de facturas</li>
                                <li class="have">Personalización de template PDF</li>
                            </ul>

                            <div class="action text-left">
                                <a href="{{ url('/free-trial') }}" class="pix-btn mb-2">Prueba gratis</a>
                            <a href="#" class="pix-btn btn-outline">Elegir este plan</a>
                            </div>
                        </div>


                        <!-- /.pricing-table -->
                    </div>


                    <!-- /.col-lg-4 -->


                </div>
                <!-- /.advanced-pricing-table -->
            </div>
            <!-- /.container -->
        </section>
        <!-- /.pricing -->


@endsection

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <!-- Meta Data -->
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=1" />
    <title>@yield('title', 'Fakturalista') - Fakturalista</title>
    <link rel="apple-touch-icon" sizes="180x180" href="{{ url('assets/icon.svg') }}" />
    <link rel="icon" type="image/png" sizes="32x32" href="{{ url('assets/icon.svg') }}" />
    <link rel="icon" type="image/png" sizes="16x16" href="{{ url('assets/icon.svg') }}" />
    <link rel="mask-icon" href="assets/img/fav/safari-pinned-tab.svg" color="#fa7070" />

    <meta name="msapplication-TileColor" content="#fa7070" />
    <meta name="theme-color" content="#fa7070" />
    <meta name="description" content="Fakturalista es una plataforma moderna de facturación online que te permite crear, enviar y gestionar facturas profesionales en segundos.">
    <meta name="keywords" content="facturación, facturas online, SaaS, gestión de gastos, pagos instantáneos, software de facturación, Fakturalista">
    <meta name="author" content="Fakturalista">
    <!-- Dependency Styles -->
    <link rel="stylesheet" href="{{ url('dependencies/bootstrap/css/bootstrap.min.css') }}" type="text/css" />
    <link rel="stylesheet" href="{{ url('dependencies/fontawesome/css/all.min.css') }}" type="text/css" />
    <link rel="stylesheet" href="{{ url('dependencies/swiper/css/swiper.min.css') }}" type="text/css" />
    <link rel="stylesheet" href="{{ url('dependencies/wow/css/animate.css') }}" type="text/css" />
    <link rel="stylesheet" href="{{ url('dependencies/magnific-popup/css/magnific-popup.css') }}" type="text/css" />
    <link rel="stylesheet" href="{{ url('dependencies/components-elegant-icons/css/elegant-icons.min.css') }}" type="text/css" />
    <link rel="stylesheet" href="{{ url('dependencies/simple-line-icons/css/simple-line-icons.css') }}" type="text/css" />

    <meta property="og:title" content="@yield('title', 'Fakturalista – Software de facturación online')" />
    <meta property="og:description" content="Crea y envía facturas profesionales, controla tus gastos y acepta pagos instantáneos con Fakturalista." />
    <meta property="og:image" content="{{ asset('images/og-image.jpg') }}" />
    <meta property="og:url" content="{{ url()->current() }}" />
    <meta property="og:type" content="website" />
    <meta property="og:site_name" content="Fakturalista" />

    <!-- Site Stylesheet -->
    <link rel="stylesheet" href="{{ url('front/assets/css/app.css') }}" type="text/css" />

    <!-- Google Web Fonts -->
    <link rel="preconnect" href="https://fonts.gstatic.com" />
    <link href="https://fonts.googleapis.com/css2?family=Montserrat+Alternates:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Satisfy&display=swap" rel="stylesheet" />

    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-YY0S2MP872"></script>
    <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());

    gtag('config', 'G-YY0S2MP872');
    </script>

</head>

<body id="home-version-1" class="home-version-4" data-style="default">
    <a href="#main_content" data-type="section-switch" class="return-to-top">
        <i class="fa fa-chevron-up"></i>
    </a>

    <div class="page-loader">
        <div class="loader">
            <!-- Loader -->
            <div class="blobs">
                <div class="blob-center"></div>
                <div class="blob"></div>
                <div class="blob"></div>
                <div class="blob"></div>
                <div class="blob"></div>
                <div class="blob"></div>
                <div class="blob"></div>
            </div>
            <svg xmlns="http://www.w3.org/2000/svg" version="1.1">
                <defs>
                    <filter id="goo">
                        <feGaussianBlur in="SourceGraphic" stdDeviation="10" result="blur" />
                        <feColorMatrix in="blur" values="1 0 0 0 0  0 1 0 0 0  0 0 1 0 0  0 0 0 18 -7" result="goo" />
                        <feBlend in="SourceGraphic" in2="goo" />
                    </filter>
                </defs>
            </svg>
        </div>
    </div>
    <!-- /.page-loader -->

    <div id="main_content">

        <!--=========================-->
        <!--=        Navbar         =-->
        <!--=========================-->
        <header class="site-header header_trans-fixed" data-top="992">
            <div class="container">
                <div class="header-inner">
                    <div class="toggle-menu">
                        <span class="bar"></span>
                        <span class="bar"></span>
                        <span class="bar"></span>
                    </div>
                    <!-- /.toggle-menu -->

                    <div class="site-mobile-logo">
                        <a href="index.html" class="logo">
                            <img src="{{ url('assets/icon.svg') }}" alt="site logo" class="main-logo" />
                            <img src="{{ url('assets/icon.svg') }}" alt="site logo" class="sticky-logo" />
                        </a>
                    </div>

                    <nav class="site-nav">
                        <div class="close-menu">
                            <span>Close</span>
                            <i class="ei ei-icon_close"></i>
                        </div>

                        <div class="site-logo">
                            <a href="{{ url('/') }}" class="logo">
                                <img src="{{ url('assets/logo.svg') }}" alt="site logo" class="main-logo" />
                                <img src="{{ url('assets/logo.svg') }}" alt="site logo" class="sticky-logo" />
                            </a>
                        </div>
                        <!-- /.site-logo -->

                        <div class="menu-wrapper" data-top="992">
                            <ul class="site-main-menu">
                                 <li><a href="{{ url('/pricing') }}">Precios</a></li>
                                <li><a href="{{ url('/about') }}">Nosotros</a></li>
                                <li><a href="{{ url('/blog') }}">Blog</a></li>
                                <li><a href="{{ url('/contact') }}">Contacto</a></li>
                            </ul>

                            <div class="nav-right">
                                <a href="{{ url('/free-trial') }}" class="nav-btn">Prueba gratis</a>
                            </div>
                        </div>
                        <!-- /.menu-wrapper -->
                    </nav>
                    <!-- /.site-nav -->
                </div>
                <!-- /.header-inner -->
            </div>
            <!-- /.container -->
        </header>
        <!-- /.site-header -->

        @yield('content')

        <!--=========================-->
        <!--=        Footer         =-->
        <!--=========================-->
        <footer id="footer">
            <div class="container">
                <div class="footer-inner wow pixFadeUp">
                    <div class="row">
                        <div class="col-lg-3 col-md-6">
                            <div class="widget footer-widget">
                                <div class="site-logo">
                            <a href="{{ url('/') }}" class="logo">
                                <img src="{{ url('assets/logo.svg') }}" alt="site logo" class="main-logo" />
                                <img src="{{ url('assets/logo.svg') }}" alt="site logo" class="sticky-logo" />
                            </a>
                        </div>
                            </div>
                            <!-- /.widget footer-widget -->
                        </div>
                        <!-- /.col-lg-3 col-md-6 -->

                        <div class="col-lg-3 col-md-6">
                            <div class="widget footer-widget">
                                <h3 class="widget-title">Empresa</h3>

                                <ul class="footer-menu">
                                    <li><a href="{{ url('/about') }}">Sobre nosotros</a></li>
                                    <li><a href="{{ url('/blog') }}">Blog</a></li>
                                    <li><a href="{{ url('/contact') }}">Contacto</a></li>
                                </ul>
                            </div>
                            <!-- /.widget footer-widget -->
                        </div>
                        <!-- /.col-lg-3 col-md-6 -->

                        <div class="col-lg-3 col-md-6">
                            <div class="widget footer-widget">
                                <h3 class="widget-title">Producto</h3>

                                <ul class="footer-menu">
                                    <li><a href="{{ url('/pricing') }}">Precios</a></li>
                                    <li><a href="#">Seguridad</a></li>
                                    <li><a href="#">Integraciones</a></li>
                                    <li><a href="{{ url('/faq') }}">Preguntas frecuentes (FAQ)</a></li>
                                </ul>
                            </div>
                            <!-- /.widget footer-widget -->
                        </div>
                        <!-- /.col-lg-3 col-md-6 -->

                        <div class="col-lg-3 col-md-6">
                            <div class="widget footer-widget">
                                <h3 class="widget-title">Conecta con Fakturalista</h3>
                                <ul class="footer-social-link">
                                    <li><a href="#"><i class="fab fa-facebook-f"></i></a></li>
                                    <li><a href="#"><i class="fab fa-google-plus-g"></i></a></li>
                                    <li><a href="#"><i class="fab fa-instagram"></i></a></li>
                                </ul>
                            </div>
                            <!-- /.widget footer-widget -->
                        </div>
                        <!-- /.col-lg-3 col-md-6 -->
                    </div>
                    <!-- /.row -->

                </div><!-- /.footer-inner -->

                <div class="site-info">
                    <div class="copyright">
                        <p>© 2025 Fakturalista — Todos los derechos reservados.</p>
                    </div>

                    <ul class="site-info-menu">
                        <li><a href="#">Términos y cond.</a></li>
                        <li><a href="#">Política de priva.</a></li>
                        <li><a href="#">Aviso l.</a></li>
                    </ul>

                    <div class="site-language" style="display: flex; align-items: center; gap: 8px;">
                        <label for="site-locale">Idioma</label>
                        <form method="POST" action="{{ url('/locale') }}">
                            @csrf
                            <select id="site-locale" name="locale" onchange="this.form.submit()">
                                <option value="es" {{ app()->getLocale() === 'es' ? 'selected' : '' }}>Español</option>
                                <option value="fr" {{ app()->getLocale() === 'fr' ? 'selected' : '' }}>Français</option>
                                <option value="en" {{ app()->getLocale() === 'en' ? 'selected' : '' }}>English</option>
                            </select>
                        </form>
                    </div>
                </div><!-- /.site-info -->
            </div><!-- /.container -->
        </footer><!-- /#footer -->

    </div>
    <!-- /#site -->

    <!-- Dependency Scripts -->
    <script src="{{ url('dependencies/jquery/jquery.min.js') }}"></script>
    <script src="{{ url('dependencies/bootstrap/js/bootstrap.min.js') }}"></script>
    <script src="{{ url('dependencies/swiper/js/swiper.min.js') }}"></script>
    <script src="{{ url('dependencies/jquery.appear/jquery.appear.js') }}"></script>
    <script src="{{ url('dependencies/wow/js/wow.min.js') }}"></script>
    <script src="{{ url('dependencies/countUp.js/countUp.min.js') }}"></script>
    <script src="{{ url('dependencies/isotope-layout/isotope.pkgd.min.js') }}"></script>
    <script src="{{ url('dependencies/imagesloaded/imagesloaded.pkgd.min.js') }}"></script>
    <script src="{{ url('dependencies/jquery.parallax-scroll/js/jquery.parallax-scroll.js') }}"></script>
    <script src="{{ url('dependencies/magnific-popup/js/jquery.magnific-popup.min.js') }}"></script>
    <script src="{{ url('dependencies/gmap3/js/gmap3.min.js') }}"></script>
    <script type="text/javascript" src="https://maps.googleapis.com/maps/api/js?key=AIzaSyDk2HrmqE4sWSei0XdKGbOMOHN3Mm2Bf-M&#038;ver=2.1.6"></script>

    <!-- Site Scripts -->
    <script src="{{ url('front/assets/js/header.js') }}"></script>
    <script src="{{ url('front/assets/js/app.js') }}"></script>

</body>

</html>

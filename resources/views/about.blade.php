@extends('layouts.master')

@section('title', __('site.about.page_title'))

@section('meta')
<meta name="description" content="{{ __('site.about.meta_desc') }}" />
<meta property="og:title" content="{{ __('site.about.banner_title') }} - Fakturalista" />
<meta property="og:description" content="{{ __('site.about.meta_desc') }}" />
<meta property="og:type" content="website" />
<link rel="canonical" href="{{ url('/about') }}" />
@endsection

@section('content')

<style>
    .site-header .site-main-menu li > a{
        color: #000000;
    }
</style>

        <!--==========================-->
        <!--=         Banner         =-->
        <!--==========================-->
        @include('partials.page-hero', [
            'title'      => __('site.about.banner_title'),
            'paragraphs' => [__('site.about.banner_p1'), __('site.about.banner_p2')],
            'cta_text'   => __('site.about.banner_cta'),
            'cta_url'    => url('/free-trial'),
        ])

        <!--===========================-->
        <!--=         Feature         =-->
        <!--===========================-->
        <section class="featured">
            <div class="container">
                <div class="section-title text-center wow pixFade">
                    <h2 class="title">{{ __('site.about.feat_title') }}</h2>
                </div>
                <!-- /.section-title -->

                <div class="row">
                    <div class="col-md-4">
                        <div class="saaspik-icon-box-wrapper style-one wow pixFadeLeft" data-wow-delay="0.3s">
                            <div class="saaspik-icon-box-icon">
                                <img src="{{ url('assets/icon_1.png') }}" alt="">
                            </div>
                            <div class="pixsass-icon-box-content">
                                <h3 class="pixsass-icon-box-title"><a href="#">{{ __('site.about.feat_1_title') }}</a></h3>
                                <p>{{ __('site.about.feat_1_text') }}</p>
                            </div>
                        </div>
                    </div>
                    <!-- /.col-md-4 -->

                    <div class="col-md-4">
                        <div class="saaspik-icon-box-wrapper style-one wow pixFadeLeft" data-wow-delay="0.5s">
                            <div class="saaspik-icon-box-icon">
                                <img src="{{ url('assets/icon_2.png') }}" alt="">
                            </div>
                            <div class="pixsass-icon-box-content">
                                <h3 class="pixsass-icon-box-title"><a href="#">{{ __('site.about.feat_2_title') }}</a></h3>
                                <p>{{ __('site.about.feat_2_text') }}</p>
                            </div>
                        </div>
                    </div>
                    <!-- /.col-md-4 -->

                    <div class="col-md-4">
                        <div class="saaspik-icon-box-wrapper style-one wow pixFadeLeft" data-wow-delay="0.7s">
                            <div class="saaspik-icon-box-icon">
                                <img src="{{ url('assets/icon_3.png') }}" alt="">
                            </div>
                            <div class="pixsass-icon-box-content">
                                <h3 class="pixsass-icon-box-title"><a href="#">{{ __('site.about.feat_3_title') }}</a></h3>
                                <p>{{ __('site.about.feat_3_text') }}</p>
                            </div>
                        </div>
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
                                    {{ __('site.about.editor_title') }}
                                </h2>
                            </div>

                            <div class="description wow pixFadeUp" data-wow-delay="0.7s">
                                <p>{{ __('site.about.editor_p') }}</p>
                                <a href="{{ url('/free-trial') }}" class="pix-btn wow pixFadeUp" data-wow-delay="0.9s">
                                    {{ __('site.about.editor_cta') }}
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
        <!--=         Reports Section         =-->
        <!--===================================-->
        <section class="genera-informes">
            <div class="container">
                <div class="row">
                    <div class="col-lg-6 pix-order-one">
                        <div class="section-title style-two">
                            <h2 class="title wow pixFadeUp">
                                {{ __('site.about.informes_title') }}
                            </h2>

                            <p class="wow pixFadeUp" data-wow-delay="0.3s">
                                {{ __('site.about.informes_p') }}
                            </p>
                        </div>
                        <!-- /.section-title style-two -->

                        <ul class="list-items wow pixFadeUp" data-wow-delay="0.4s">
                            <li>{{ __('site.about.informes_li_1') }}</li>
                            <li>{{ __('site.about.informes_li_2') }}</li>
                            <li>{{ __('site.about.informes_li_3') }}</li>
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
                    <h2 class="title">{{ __('site.about.cta_title') }}</h2>

                    <p>{{ __('site.about.cta_p') }}</p>

                    <a href="{{ url('/free-trial') }}" class="pix-btn btn-light">{{ __('site.about.cta_btn') }}</a>
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

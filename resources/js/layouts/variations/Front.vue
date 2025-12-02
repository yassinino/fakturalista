<script setup>
import { useTemplateStore } from "@/stores/template";

import BaseLayout from "@/layouts/BaseLayout.vue";

// Main store
const store = useTemplateStore();

// Set default elements for this layout
store.setLayout({
  header: false,
  sidebar: false,
  sideOverlay: false,
  footer: false,
});

// Set various template options for this layout variation
store.mainContent({ mode: "full" });
</script>

<template>
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
                            <img src="assets/img/main-logo.png" alt="site logo" class="main-logo" />
                            <img src="assets/img/sticky-logo.png" alt="site logo" class="sticky-logo" />
                        </a>
                    </div>

                    <nav class="site-nav">
                        <div class="close-menu">
                            <span>Close</span>
                            <i class="ei ei-icon_close"></i>
                        </div>

                        <div class="site-logo">
                            <a href="index.html" class="logo">
                                <img src="assets/img/main-logo.png" alt="site logo" class="main-logo" />
                                <img src="assets/img/sticky-logo.png" alt="site logo" class="sticky-logo" />
                            </a>
                        </div>
                        <!-- /.site-logo -->

                        <div class="menu-wrapper" data-top="992">
                            <ul class="site-main-menu">
                                <li class="menu-item-has-children">
                                    <a href="index.html">Home</a>
                                    <ul class="sub-menu">
                                        <li><a href="index.html">Home One</a></li>
                                        <li><a href="index-two.html">Home Two</a></li>
                                        <li>
                                            <a href="index-three.html">Home Three</a>
                                        </li>
                                        <li><a href="index-four.html">Home Four</a></li>
                                        <li><a href="index-five.html">Home Five</a></li>
                                        <li><a href="index-six.html">Home Six</a></li>
                                        <li>
                                            <a href="index-seven.html">Home Seven</a>
                                        </li>
                                        <li>
                                            <a href="index-eight.html">Home Eight</a>
                                        </li>
                                        <li><a href="index-nine.html">Home Nine</a></li>
                                        <li><a href="index-ten.html">Home Ten</a></li>
                                        <li>
                                            <a href="index-eleven.html">Home Eleven</a>
                                        </li>
                                        <li>
                                            <a href="index-twelve.html">Home Twelve</a>
                                        </li>
                                        <li>
                                            <a href="index-thirteen.html">Home Thirteen</a>
                                        </li>
                                        <li>
                                            <a href="index-fourteen.html">Home Fourteen</a>
                                        </li>
                                    </ul>
                                </li>
                                <li><a href="about.html">About</a></li>
                                <li class="menu-item-has-children">
                                    <a href="blog.html">Blog</a>
                                    <ul class="sub-menu">
                                        <li><a href="blog.html">Blog Standard</a></li>
                                        <li><a href="blog-grid.html">Blog Grid</a></li>
                                        <li>
                                            <a href="blog-single.html">Blog Single</a>
                                        </li>
                                    </ul>
                                </li>
                                <li class="menu-item-has-children">
                                    <a href="#">Pages</a>

                                    <ul class="sub-menu">
                                        <li><a href="about.html">About</a></li>
                                        <li><a href="service.html">Service</a></li>
                                        <li><a href="team.html">Our Team</a></li>
                                        <li><a href="pricing.html">Pricing</a></li>
                                        <li class="menu-item-has-children">
                                            <a href="portfolio.html">Portfolio</a>
                                            <ul class="sub-menu">
                                                <li>
                                                    <a href="portfolio-one.html">Style One</a>
                                                </li>
                                                <li>
                                                    <a href="portfolio-two.html">Style Two</a>
                                                </li>
                                                <li>
                                                    <a href="portfolio-three.html">Style Three</a>
                                                </li>
                                                <li>
                                                    <a href="portfolio-single.html">Portfolio Single</a>
                                                </li>
                                            </ul>
                                        </li>
                                        <li><a href="faq.html">Faq's</a></li>
                                        <li><a href="error.html">Error 404</a></li>
                                        <li><a href="signin.html">Sing In</a></li>
                                        <li><a href="signup.html">Sing Up</a></li>
                                    </ul>
                                </li>

                                <li><a href="contact.html">Contact</a></li>
                            </ul>

                            <div class="nav-right">
                                <a href="signup.html" class="nav-btn">Free Sign Up</a>
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

        <RouterView />

                <section class="call-to-action">
            <div class="overlay-bg"><img src="media/background/ellipse.png" alt="bg"></div>
            <div class="container">
                <div class="action-content text-center wow pixFadeUp">
                    <h2 class="title">
                        We are optimists who<br>
                        love to work together
                    </h2>

                    <p>
                        Spiffing good time nice one mush ummm I'm telling down the pub spend<br>
                        a penny only a quid such a fibber tomfoolery, on your bike.
                    </p>

                    <a href="#" class="pix-btn btn-light">Free Consultation</a>
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

        <!--=========================-->
        <!--=        Footer         =-->
        <!--=========================-->
        <footer id="footer">
            <div class="container">
                <div class="footer-inner wow pixFadeUp">
                    <div class="row">
                        <div class="col-lg-3 col-md-6">
                            <div class="widget footer-widget">
                                <h3 class="widget-title">Company</h3>

                                <ul class="footer-menu">
                                    <li><a href="#">Features</a></li>
                                    <li><a href="#">Dashboard & Tool</a></li>
                                    <li><a href="#">Our Portfolio</a></li>
                                    <li><a href="#">About Us</a></li>
                                    <li><a href="#">Get In Touch</a></li>
                                </ul>
                            </div>
                            <!-- /.widget footer-widget -->
                        </div>
                        <!-- /.col-lg-3 col-md-6 -->

                        <div class="col-lg-3 col-md-6">
                            <div class="widget footer-widget">
                                <h3 class="widget-title">Services</h3>

                                <ul class="footer-menu">
                                    <li><a href="#">Features</a></li>
                                    <li><a href="#">Dashboard & Tool</a></li>
                                    <li><a href="#">Our Portfolio</a></li>
                                    <li><a href="#">About Us</a></li>
                                    <li><a href="#">Get In Touch</a></li>
                                </ul>
                            </div>
                            <!-- /.widget footer-widget -->
                        </div>
                        <!-- /.col-lg-3 col-md-6 -->

                        <div class="col-lg-3 col-md-6">
                            <div class="widget footer-widget">
                                <h3 class="widget-title">Digital Experience</h3>

                                <ul class="footer-menu">
                                    <li><a href="#">Features</a></li>
                                    <li><a href="#">Dashboard & Tool</a></li>
                                    <li><a href="#">Our Portfolio</a></li>
                                    <li><a href="#">About Us</a></li>
                                    <li><a href="#">Get In Touch</a></li>
                                </ul>
                            </div>
                            <!-- /.widget footer-widget -->
                        </div>
                        <!-- /.col-lg-3 col-md-6 -->

                        <div class="col-lg-3 col-md-6">
                            <div class="widget footer-widget">
                                <h3 class="widget-title">Our Address</h3>

                                <p>
                                    Lavaca Street, Suite 24, Road Apt New York, USA
                                </p>

                                <ul class="footer-social-link">
                                    <li><a href="#"><i class="fab fa-facebook-f"></i></a></li>
                                    <li><a href="#"><i class="fab fa-twitter"></i></a></li>
                                    <li><a href="#"><i class="fab fa-google-plus-g"></i></a></li>
                                    <li><a href="#"><i class="fab fa-linkedin-in"></i></a></li>
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
                        <p>© 2025 All Rights Reserved Design by <a href="http://www.pixelsigns.art" target="_blank">PixelSigns</a></p>
                    </div>

                    <ul class="site-info-menu">
                        <li><a href="#">Privacy & Policy.</a></li>
                        <li><a href="#">Faq.</a></li>
                        <li><a href="#">Terms.</a></li>
                    </ul>
                </div><!-- /.site-info -->
            </div><!-- /.container -->
        </footer><!-- /#footer -->

    </div>

</template>

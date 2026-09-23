<section class="page-banner">
    <div class="container">
        <div class="page-title-wrapper">
            @if (!empty($eyebrow))
                <div class="page-eyebrow">
                    @if (!empty($eyebrow_icon))
                        <i class="{{ $eyebrow_icon }}" aria-hidden="true"></i>
                    @endif
                    {{ $eyebrow }}
                </div>
            @endif
            <h1 class="page-title">{!! $title_html ?? e($title) !!}</h1>
            @foreach ($paragraphs ?? [] as $para)
                <p>{{ $para }}</p>
            @endforeach
            @foreach ($paragraphs_html ?? [] as $para)
                <p>{!! $para !!}</p>
            @endforeach
            @if (!empty($cta_text) && !empty($cta_url))
                <a href="{{ $cta_url }}" class="pix-btn">{{ $cta_text }}</a>
            @endif
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

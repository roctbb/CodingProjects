<nav id="navbar-main" class="navbar navbar-main navbar-expand-lg navbar-dark navbar-theme-primary headroom py-lg-2 px-lg-6">
    <div class="container">
        <a class="navbar-brand @@logo_classes" href="{{ url('rocket/index.html') }}">
            <div class="d-flex align-items-center">
                <img class="navbar-brand-dark rotate-logo" src="{{ url('/images/icons/icons8-idea-64.png') }}" alt="Logo light">
                <img class="navbar-brand-light rotate-logo" src="{{ url('/images/icons/icons8-idea-64.png') }}" alt="Logo dark">
            </div>
        </a>
        <div class="navbar-collapse collapse" id="navbar_global">
            <div class="navbar-collapse-header">
                <div class="row">
                    <div class="col-6 collapse-brand">
                        <a class="d-flex align-items-center" href="{{ url('rocket/index.html') }}">
                            <img src="{{ url('/images/icons/icons8-idea-64.png') }}" alt="Logo dark">
                        </a>
                    </div>
                    <div class="col-6 collapse-close">
                        <a href="#navbar_global" class="fas fa-times" data-toggle="collapse"
                           data-target="#navbar_global" aria-controls="navbar_global" aria-expanded="false"
                           aria-label="Toggle navigation"></a>
                    </div>
                </div>
            </div>
            <ul class="navbar-nav navbar-nav-hover justify-content-center">
                <li class="nav-item">
                    <a href="{{ url('/') }}" class="nav-link">{{ config('app.name') }}</a>
                </li>
                <li class="nav-item dropdown">
                    <a href="@@path/index.html" class="nav-link dropdown-toggle" id="dashboardPagesDropdown" aria-expanded="false" data-toggle="dropdown">
                        <span class="nav-link-inner-text mr-1">Курсы</span>
                        <i class="fas fa-angle-down nav-link-arrow"></i>
                    </a>

                    <div class="dropdown-menu dropdown-megamenu dropdown-megamenu-sm pl-3 py-3 p-lg-4" aria-labelledby="dashboardPagesDropdown">
                        <div class="row no-gutters">
                            <div class="col-6">
                                <h6 class="d-block mb-3 text-primary">Искусственный интеллект и машинное обучение</h6>
                                <ul class="list-style-none">
                                    <li class="mb-2 megamenu-item">
                                        <a class="megamenu-link" href="@@path/pages/basicml.html">Введение в науки о данных</a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                </li>
            </ul>
        </div>
        <div class="d-none d-lg-block">
            <a href="https://t.me/roctbb" target="_blank" class="btn btn-secondary btn-pricing-plan animate-up-2 mr-3"><i class="fas fa-paper-plane mr-2"></i> Задать вопрос</a>
        </div>
        <div class="d-flex d-lg-none align-items-center ml-auto">
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbar_global"
                    aria-controls="navbar_global" aria-expanded="false" aria-label="Toggle navigation"><span
                        class="navbar-toggler-icon"></span></button>
        </div>
    </div>
</nav>
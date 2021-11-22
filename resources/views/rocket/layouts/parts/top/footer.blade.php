<footer class="footer section pt-6 pt-md-8 pt-lg-10 pb-3 bg-primary text-white overflow-hidden">
    <div class="pattern top pattern-soft"></div>
    <div class="container">
        <div class="row">
            <div class="col-lg-4 mb-4 mb-lg-0">
                <a class="footer-brand mr-lg-5 d-flex" href="{{ url('rocket/index.html') }}">
                    <img src="{{ url('rocket/assets/img/brand/light.svg" class="mr-3') }}" alt="Footer logo">
                    <h4>Rocket</h4>
                </a>
                <p class="my-4">Create, prototype, collaborate and turn your ideas into incredible products with the definitive platform for digital design.</p>
                <div class="dropdown mb-4 mb-lg-0">
                    <a id="langsDropdown" href="#" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" class="dropdown-toggle footer-language-link pb-2">
                        <img src="{{ url('rocket/assets/img/flags/united-states-of-america.svg" alt="USA Flag') }}" class="language-flag"> United States
                        <i class="fas fa-chevron-down ml-1"></i>
                    </a>
                    <div aria-labelledby="langsDropdown" class="dropdown-menu dropdown-menu-center mt-0">
                        <a href="#" class="dropdown-item text-gray text-sm"><img src="{{ url('rocket/assets/img/flags/germany.svg" alt="Germany Flag') }}" class="language-flag"> German</a>
                        <a href="#" class="dropdown-item text-gray text-sm"><img src="{{ url('rocket/assets/img/flags/spain.svg" alt="Spain Flag') }}" class="language-flag"> Spanish</a>
                        <a href="#" class="dropdown-item text-gray text-sm"><img src="{{ url('rocket/assets/img/flags/france.svg" alt="France Flag') }}" class="language-flag"> French</a>
                    </div>
                </div>
            </div>
            <div class="col-6 col-sm-3 col-lg-2 mb-4 mb-lg-0">
                <h5>Themesberg</h5>
                <ul class="links-vertical">
                    <li><a target="_blank" href="https://themesberg.com/blog">Blog</a></li>
                    <li><a target="_blank" href="https://themesberg.com/products">Products</a></li>
                    <li><a target="_blank" href="https://themesberg.com/contact">Contact Us</a></li>
                </ul>
            </div>
            <div class="col-6 col-sm-3 col-lg-2 mb-4 mb-lg-0">
                <h5>Rocket</h5>
                <ul class="links-vertical">
                    <li><a href="{{ url('rocket/pages/about.html') }}">About</a></li>
                    <li><a href="{{ url('rocket/pages/pricing.html') }}">Pricing</a></li>
                    <li><a href="https://themesberg.com/docs/rocket/getting-started/overview/">Documentation</a></li>
                    <li><a href="{{ url('rocket/pages/contact.html') }}">Contact</a>
                    </li>
                </ul>
            </div>
            <div class="col-12 col-sm-6 col-lg-4">
                <h5>Subscribe</h5>
                <p class="font-small">The latest Rocket news, articles, and resources, sent straight to your inbox every month.</p>
                <form action="#">
                    <div class="form-row">
                        <div class="col-8">
                            <input type="email" class="form-control mb-2" placeholder="Email Address" name="email" required>
                        </div>
                        <div class="col-4">
                            <button type="submit" class="btn btn-secondary"><span>Subscribe</span></button>
                        </div>
                    </div>
                </form>
                <small class="mt-2 form-text">We’ll never share your details. See our <a href="{{ url('rocket/pages/terms.html') }}" class="font-weight-bold text-underline">Privacy Policy</a></small>
            </div>
        </div>
        <hr class="my-4 my-lg-5">
        <div class="row">
            <div class="col pb-4 mb-md-0">
                <a href="https://themesberg.com" target="_blank" class="d-flex justify-content-center">
                    <img src="{{ url('rocket/assets/img/themesberg.svg" height="25" width="25" class="mb-3') }}" alt="Themesberg Logo">
                </a>
                <div class="d-flex text-center justify-content-center align-items-center">
                    <p class="font-weight-normal font-small mb-0">Copyright © <a href="https://themesberg.com" target="_blank">Themesberg</a> <span class="current-year"></span>. All rights
                        reserved.</p>
                </div>
            </div>
        </div>
    </div>
</footer>
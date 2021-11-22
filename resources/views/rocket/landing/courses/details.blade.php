@extends('rocket.layouts.top')

@section('title')
{{ $course->name }}
@endsection

@section('content')
    <!-- Hero -->

    <section class="section-header pb-10 pb-lg-11 mb-4 mb-lg-6 bg-primary text-white">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-12 text-center mb-4 mb-lg-5">
                    <h1 class="display-2 font-weight-extreme mb-4">{{ $course->name }}</h1>
                    <div class="d-flex flex-column flex-lg-row justify-content-center">
                        <span class="h5 mb-3 mb-lg-0"><i class="fas fa-map-marker-alt"></i><span class="ml-3">{{ $course->landing_timetable }}</span></span>
                        <span class="ml-lg-5 mb-3 mb-lg-0 h5"><i class="fas fa-map-marked"></i><span class="ml-3">{{ $course->landing_group_size }}</span></span>
                        <span class="ml-lg-5 mb-3 mb-lg-0 h5"><i class="fas fa-ruble-sign"></i><span class="ml-3">{{ $course->landing_price }}</span></span>
                    </div>
                </div>
                <div class="col col-12 text-center">
                    <a href="{{ url('/courses') }}" class="btn btn-secondary text-white animate-up-2 mr-3"><i
                                class="fas fa-arrow-left mr-2"></i>Все курсы</a>
                    <a href="{{ $course->landing_enrollment_link }}" target="_blank"
                       class="btn btn-white text-primary animate-up-2"><i
                                class="fas fa-clipboard-list mr-2"></i>Оставить заявку</a>
                </div>
            </div>
        </div>
        <div class="pattern bottom"></div>
    </section>
    <section class="section section-lg pt-0">
        <div class="container mt-n8 mt-lg-n11 z-2">
            <div class="row justify-content-center">
                <div class="col">
                    <div class="card shadow-soft border-light p-4 p-md-5">
                        <p class="lead"><strong class="font-weight-extreme">Науки о данных</strong> - это область
                            на стыке математики и программирования, одним из практических применений которой является
                            разработка искусственного интеллекта. </p>
                        <p class="lead">Эксперты в этой области занимаются машинным обучением: разработкой алгоритмов,
                            которые обучаясь на больших объемах данных (Big Data), способны предсказывать курсы акций,
                            диагностировать заболевания, собирать интересные материалы в "умную ленту" или рекомендовать
                            вам интересные сериалы.</p>

                        <p class="lead">На этом курсе, я за три месяца научу Вас использовать язык программирования
                            Python 3 и стэк scipy для анализа данных, предсказания будущего и обучения собственных
                            моделей машинного обучения.</p>

                        <hr>
                        <h4 class="py-3">План действий:</h4>
                        <ul class="list-unstyled">
                            <li class="py-1 lead">
                                <div class="media">
                                    <span class="icon icon-sm mr-3 mt-2"><i
                                                class="fas fa-arrow-alt-circle-right"></i></span>
                                    <div>
                                        <strong>Сбор и визуализация данных</strong>
                                        <p>Знакомимся с Collab и Jupyter, парсим веб-сайты, собираем и анализируем
                                            собственный датасет в Pandas и Matplotlib.</p>
                                    </div>
                                </div>
                            </li>
                            <li class="py-1 lead">
                                <div class="media"><span class="icon icon-sm mr-3 mt-2"><i
                                                class="fas fa-arrow-alt-circle-right"></i></span>
                                    <div><strong>Классификация и регрессия</strong><br>
                                        <p>Предсказываем стоимости кроссовок и результаты киберспортивных матчей.</p>

                                        <p>Задачи машинного обучения (с учителем, без учителя): классификация,
                                            регрессия, кластеризация. Обучающая и тестовые выборки, модель, обобщающая
                                            способность, обучение, недообучение, переобучение, сложность. Предобработка
                                            данных, шкалирование признаков, разбиение категориальные признаки, работа с
                                            пропущенными значениями, конструирование и отбор признаков, решетчатый
                                            поиск.</p>
                                        <p><i>
                                                kNN, линейные модели (линейная, гребневая и логистическая регрессия),
                                                деревья, ансамбли (случайный лес, xgboost, catboost), SVM.</i></p>
                                    </div>
                                </div>
                            </li>
                            <li class="py-1 lead">
                                <div class="media"><span class="icon icon-sm mr-3 mt-2"><i
                                                class="fas fa-arrow-alt-circle-right"></i></span>
                                    <div><strong>Обработка текстовых данных</strong><br>
                                        <p><i>Лемматизация, стемминг, n-граммы, векторизация мешком слов, TF/IDF и
                                                word2vec.</i></p>
                                    </div>
                                </div>
                            </li>
                            <li class="py-3 lead">
                                <div class="media"><span class="icon icon-sm mr-3 mt-2"><i
                                                class="fas fa-arrow-alt-circle-right"></i></span>
                                    <div><strong>Кластеризация</strong><br>
                                        <p>Автоматически находим похожие анекдоты в большом датасете.</p>
                                        <p><i>PCA, kmeans, иерахическая кластеризация, DBSCAN.</i></p>
                                    </div>
                                </div>
                            </li>
                            <li class="py-1 lead">
                                <div class="media">
                                    <span class="icon icon-sm mr-3 mt-2"><i
                                                class="fas fa-arrow-alt-circle-right"></i></span>
                                    <div>
                                        <strong>Рекомендательные системы</strong>
                                        <p>Используем SVD для поиска новых сериалов.</p>
                                    </div>
                                </div>
                            </li>
                        </ul>

                        <hr>

                        <p class="lead">На курсе вы изучите математические и технологические подходы к разработке
                            искусственного
                            интеллекта. Но под искусственным интеллектом здесь подразумевается не симуляция
                            человеческого
                            разума, а программы, способные самостоятельно обучаться и изменяться в процессе собственной
                            работы.</p>

                        <p class="lead">К таким программам, например, относятся рекламные системы, которые запоминают
                            ваши предпочтения,
                            самоуправляемые автомобили, голосовые ассистенты (Siri, Алиса), программы, автоматически
                            подбирающие план лечения для сложных заболеваний, ИИ, обыгрывающие самых крутых
                            кибер-спортсменов и роботы, предсказывающие курсы акций. Такие алгоритмы сначала "обучаются"
                            на
                            больших объемах данных и автоматически "запоминают" закономерности, а затем используют эти
                            данные для будущих "предсказаний".</p>

                        <p class="lead">Курс предназначен для школьников 8 класса и старше, хорошо знакомых с языком
                            Python 3.</p>

                        <p class="lead">Курс состоит из 24 онлайн занятий (включая промежуточный и итоговый зачеты)
                            длительностью в два
                            академических часа. Помимо них вам в обязательном порядке потребуется около 4 часов в неделю
                            на
                            выполнение домашних заданий и самоподготовку.</p>

                        <p class="lead">Основой курса станет работа с наиболее популярными библиотеками для анализа,
                            визуализации и
                            машинного обучения: Pandas, Matplotlib и SciKit-learn. В процессе курса будут рассмотрены
                            типовые подходы к задачам машинного обучения, процессы сбора, предобработки данных, подбора,
                            настройки и обучения различных моделей машинного обучения. Будут рассмотрены как
                            классические
                            линейные модели, так и супер-современные алгоритмы ансамблевой классификации.</p>

                        <div id="apply" class="row">
                            <div class="col">
                                <!-- Card -->
                                <div class="card bg-soft shadow-soft border-0 text-black py-4 p-lg-5">
                                    <div class="card-body p-4">
                                        <div class="mb-5 mb-lg-6 text-center">
                                            <h2 class="h1">Интересно?</h2>
                                        </div>
                                        <div class="text-center">
                                            <a href="{{ $course->landing_enrollment_link }}" target="_blank"
                                               class="btn btn-secondary mt-4"><span
                                                        class="mr-2"><i class="fas fa-paper-plane"></i></span>
                                                Записаться!
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

@endsection
